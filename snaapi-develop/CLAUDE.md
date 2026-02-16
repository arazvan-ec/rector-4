# CLAUDE.md

Agent instructions for SNAAPI - Symfony 6.4 API Gateway.

## Project Overview

SNAAPI aggregates content from multiple microservices to serve editorial data for mobile applications. It does NOT persist data locally - all content is fetched via HTTP clients from external services.

## Development Commands

```bash
# Full test suite (CS, YAML, container, unit, static analysis, mutation)
make tests

# Individual test commands
make test_unit       # PHPUnit tests
make test_cs         # PHP-CS-Fixer (auto-fixes code style)
make test_stan       # PHPStan level 9
make test_yaml       # Lint YAML config files
make test_container  # Lint DI container
make test_infection  # Mutation testing (MSI threshold: 79%)

# Run a single test file
./bin/phpunit tests/Path/To/TestFile.php

# Run tests matching a filter
./bin/phpunit --filter testMethodName

# Docker commands
make up              # Start containers
make down            # Stop containers
make cli             # Shell into PHP container as www-data
make cliroot         # Shell as root
```

## Architectural Principles

This project adheres to the following principles. All code contributions MUST follow them:

### DDD (Domain-Driven Design)
- **Ubiquitous Language**: Use domain terms consistently (Editorial, Multimedia, Widget, Section, Tag)
- **Bounded Contexts**: Each external client represents a bounded context (Editorial, Multimedia, Membership)
- **Value Objects**: Prefer immutable value objects over primitive obsession
- **Domain Events**: Use Symfony Messenger for domain event handling
- **Anti-Corruption Layer**: Data transformers isolate external service responses from domain models

### Clean Code
- **Meaningful Names**: Classes, methods, and variables must reveal intent
- **Small Functions**: Each method should do one thing well (max ~20 lines)
- **No Comments for Bad Code**: Refactor instead of explaining with comments
- **DRY**: Extract common logic into reusable services or traits
- **Boy Scout Rule**: Leave code cleaner than you found it

### SOLID Principles
- **S** - Single Responsibility: One reason to change per class (e.g., transformers only transform)
- **O** - Open/Closed: Extend via interfaces, not modification (see Compiler Passes pattern)
- **L** - Liskov Substitution: Subtypes must be substitutable (all orchestrators implement same interface)
- **I** - Interface Segregation: Small, focused interfaces (e.g., `MultimediaOrchestratorInterface`)
- **D** - Dependency Inversion: Depend on abstractions (inject interfaces, not concrete classes)

### Symfony Best Practices
- Use constructor injection exclusively (no `@required` or setter injection)
- Configure services as `autowire: true`, `autoconfigure: true`
- Use Compiler Passes for dynamic service registration
- Leverage service tags for extensibility patterns
- Keep controllers thin - delegate to orchestrators/services
- Use `#[Route]` attributes over YAML routing

### REST API Best Practices
- Use proper HTTP status codes (200, 201, 400, 404, 500)
- Return consistent JSON structure with `data`, `meta`, `errors` keys
- Use plural nouns for resources (`/editorials`, `/sections`)
- Version APIs via URL prefix (`/v1/`, `/v2/`)
- Document endpoints with OpenAPI/Swagger attributes
- Implement proper cache headers (Cache-Control, ETag)

### PHP Best Practices
- PHP 8.2+ with strict types: `declare(strict_types=1);`
- Use readonly properties and constructor promotion
- Prefer `match` over `switch`, null coalescing over ternary chains
- Use named arguments for clarity in complex calls
- Type everything: parameters, returns, properties (no mixed when avoidable)
- Use enums for fixed sets of values

## Architecture

### Request Flow (Aggregator-Presenter Pattern)

This is the **mandatory** architectural pattern for all features exposing data via API:

```
Controller → Orchestrator → EditorialAggregator → ResolvedEditorial DTO → EditorialPresenterRegistry → Response
                               ↓                                              ↓
                          External Clients                              DataTransformers
                          (7+ microservices)                            (format-specific)
```

**Two-layer separation**:
1. **Aggregator** (`src/Aggregator/`): Fetches and resolves data from external services → typed `ResolvedEditorial` DTO
2. **Presenter** (`src/Presenter/`): Translates the DTO to a format-specific response (apps, web, etc.)

**Rules**:
- Aggregators NEVER format data for a specific consumer
- Presenters NEVER call external services
- The DTO (`ResolvedEditorial`) is the contract between the two layers

### Adding a New Output Format

To add a new format (e.g. `web`):
1. Create `src/Presenter/Web/WebEditorialPresenter.php` implementing `EditorialPresenterInterface`
2. Tag with `app.editorial.presenter` in services config
3. No changes to Aggregator or existing Presenters (Open/Closed Principle)

### Core Design Patterns

**Aggregator-Presenter** - Two-layer data pipeline (MANDATORY):
- `EditorialAggregator` orchestrates data fetching from 7+ microservices
- `EditorialPresenterRegistry` dispatches to format-specific presenters
- `EditorialPresenterCompiler` auto-registers presenters via `app.editorial.presenter` tag

**Chain of Responsibility** - Content type routing:
- `OrchestratorChainHandler` routes requests by content type to registered orchestrators
- `MultimediaOrchestratorHandler` routes multimedia processing by media type (photo, video, widget)
- Registration via Compiler Passes + service tags

**Strategy Pattern** - Body element transformation:
- `BodyElementDataTransformerHandler` dispatches to type-specific transformers
- Each `BodyElement` subclass (Paragraph, SubHead, BodyTagPicture, etc.) has its own transformer
- Tagged with `app.data_transformer`, auto-registered via `BodyDataTransformerCompiler`

### Layer Structure (DDD)
```
src/
├── Controller/          # Infrastructure: HTTP entry points (thin)
├── Aggregator/          # Aggregation Layer: Fetch + resolve from external services
│   ├── DTO/             # Typed immutable DTOs (ResolvedEditorial, ResolvedInsertedNews, etc.)
│   └── Service/         # Sub-services (SignatureResolver, MultimediaResolver, etc.)
├── Presenter/           # Presentation Layer: Format-specific translation
│   └── Apps/            # Apps format presenter (uses existing DataTransformers)
├── Application/         # Application Layer: Use cases, DTOs, Transformers
│   └── DataTransformer/ # Transform domain → API response (reused by Presenters)
├── Orchestrator/        # Application Layer: Thin coordinators
├── Message/             # AMQP message DTOs for cache invalidation
├── MessageHandler/      # AMQP handlers for reactive cache invalidation
├── Infrastructure/      # Infrastructure: External services, caching, enums
└── DependencyInjection/ # Framework: Compiler passes, configuration
```

### Resilience Strategy

Services have **criticality levels** (`ServiceCriticality` enum):

| Service | Criticality | On Failure |
|---------|-------------|------------|
| editorial-client | CRITICAL | 503 (abort) |
| section-client | LOW | Degraded response, log warning |
| multimedia-client | LOW | No multimedia, log warning |
| journalist-client | LOW | No signatures, log warning |
| tag-client | LOW | No tags, log warning |
| membership-client | LOW | No membership links, log warning |
| legacy-client (comments) | LOW | 0 comments, log warning |

### Cache Strategy (3 Levels)

1. **Varnish/CDN** — HTTP response caching (existing)
2. **httplug + Couchbase** — Transparent HTTP cache for microservice calls (`config/packages/httplug.yaml`)
3. **Circuit breaker fallback** — Aggregator sub-service degradation when cache miss + service down

Cache invalidation is **reactive via AMQP**: microservices publish events, SNAAPI handlers invalidate cache keys.

### Compiler Passes (`src/DependencyInjection/Compiler/`)
- `EditorialOrchestratorCompiler` - Registers content orchestrators
- `EditorialPresenterCompiler` - Registers editorial presenters (tag: `app.editorial.presenter`)
- `BodyDataTransformerCompiler` - Registers body transformers (tag: `app.data_transformer`)
- `MediaDataTransformerCompiler` - Registers media transformers (tag: `app.media_data_transformer`)
- `MultimediaOrchestratorCompiler` - Registers multimedia handlers

### External Clients (Bounded Contexts)
- `QueryEditorialClient` - Editorial content (CRITICAL)
- `QuerySectionClient` - Section hierarchy (LOW)
- `QueryMultimediaClient` - Photos, videos, widgets (LOW)
- `QueryJournalistClient` - Author information (LOW)
- `QueryTagClient` - Tags (LOW)
- `QueryMembershipClient` - Subscription/membership links (LOW)
- `QueryLegacyClient` - Legacy API (comments) (LOW)

## Development Workflow

### After Writing Code
1. **Run tests**: `make test_unit` to verify functionality
2. **Invoke `/code-simplifier`**: Simplify and refine recently modified code for clarity and maintainability
3. **Run static analysis**: `make test_stan` to catch type issues
4. **Run full suite**: `make tests` before committing

### When to Use `/code-simplifier`
- After implementing a new feature or fixing a bug
- When refactoring existing code
- Before creating a PR
- When code feels complex or hard to follow

The skill analyzes recent changes and suggests improvements for clarity, consistency, and maintainability while preserving functionality.

## Code Quality Gates

All PRs must pass:
- PHPStan Level 9 (strict)
- PSR-12 + Symfony coding standards
- PHPUnit 10 with strict mode
- Mutation testing: 79% MSI minimum
- Tests use DataProviders for parameterized data
