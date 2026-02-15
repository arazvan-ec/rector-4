# SNAAPI Refactoring Project

## Overview

This project contains the SNAAPI REST API and the multi-agent-workflow plugin for structured refactoring.

**SNAAPI** is a Symfony 6.4 API Gateway that aggregates content from multiple microservices (Editorial, Multimedia, Journalist, Membership, Section, Tag, Widget). It does NOT persist data locally - all content is fetched via HTTP clients from external services.

## Project Structure

```
├── plugins/multi-agent-workflow/  # Compound engineering plugin (v3.1.0)
├── snaapi-develop/                # REST API source code (PHP 8.1+ / Symfony 6.4)
│   ├── src/                       # 89 PHP source files
│   ├── tests/                     # 63 PHP test files
│   ├── config/                    # Symfony configuration
│   └── CLAUDE.md                  # API-specific instructions (READ THIS)
└── openspec/                      # Generated specs (after /workflows:discover)
```

## Plugin: multi-agent-workflow

The plugin provides a structured workflow for development tasks:

```
ROUTE → SHAPE → PLAN → WORK → REVIEW → COMPOUND
```

### Commands

| Command | Purpose |
|---------|---------|
| `/workflows:discover --setup` | Auto-analyze project architecture (run first) |
| `/workflows:route` | Classify request, select workflow (always first for tasks) |
| `/workflows:shape` | Problem separation for complex features |
| `/workflows:plan` | Architecture-first planning with SOLID constraint |
| `/workflows:work` | TDD implementation with Bounded Correction Protocol |
| `/workflows:review` | Multi-agent quality review (4 agents) |
| `/workflows:compound` | Capture learnings for future acceleration |
| `/workflows:status` | View progress |
| `/workflows:help` | Quick reference |

### Flow Guards

- `plan` requires routing completed
- `work` requires plan COMPLETED
- `review` requires work COMPLETED
- `compound` requires review APPROVED

## API Architecture

### Request Flow
```
Controller → OrchestratorChainHandler → EditorialOrchestrator → External Clients → DataTransformers → Response
```

### Key Patterns
- **Chain of Responsibility**: Content type routing via OrchestratorChainHandler
- **Strategy Pattern**: Body element transformation via tagged services
- **Compiler Passes**: Dynamic service registration (7 compilers)
- **Anti-Corruption Layer**: DataTransformers isolate external service responses

### Development Commands (inside snaapi-develop/)
```bash
make tests           # Full test suite
make test_unit       # PHPUnit tests
make test_cs         # Code style (PHP-CS-Fixer)
make test_stan       # PHPStan level 9
make test_infection  # Mutation testing (79% MSI)
```

## Refactoring Goals

1. Decompose `EditorialOrchestrator` god class (536 lines, 19 constructor dependencies)
2. Eliminate duplication between insertedNews and recommendedEditorials logic
3. Create Value Objects for intermediate data (`$resolveData` → typed DTOs)
4. Group constructor dependencies into cohesive services
5. Unify error handling strategy
6. Favor composition over inheritance in DataTransformer hierarchy
