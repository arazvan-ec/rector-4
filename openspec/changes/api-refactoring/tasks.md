# Implementation Tasks: SNAAPI API Refactoring

> Feature: api-refactoring
> Created: 2026-02-15
> Updated: 2026-02-15T23:30:00Z

## Progress

| Task | Status | Verify | Completed At |
|------|--------|--------|--------------|
| BE-001: Create ResolvedEditorial DTO | COMPLETED | PHPStan pass | 2026-02-16T00:05:00Z |
| BE-002: Create ResolvedInsertedNews DTO | COMPLETED | PHPStan pass | 2026-02-16T00:05:00Z |
| BE-003: Create ResolvedRecommendedEditorial DTO | COMPLETED | PHPStan pass | 2026-02-16T00:05:00Z |
| BE-004: Create SignatureResolver | COMPLETED | Code review | 2026-02-16T00:05:00Z |
| BE-005: Create MultimediaResolver | COMPLETED | Code review | 2026-02-16T00:06:00Z |
| BE-006: Create InsertedNewsResolver | COMPLETED | Code review | 2026-02-16T00:06:00Z |
| BE-007: Create RecommendedEditorialsResolver | COMPLETED | Code review | 2026-02-16T00:06:00Z |
| BE-008: Create EditorialAggregator | COMPLETED | Code review | 2026-02-16T00:07:00Z |
| BE-009: Create EditorialPresenterInterface | COMPLETED | PHPStan pass | 2026-02-16T00:05:00Z |
| BE-010: Create EditorialPresenterRegistry | COMPLETED | Code review | 2026-02-16T00:05:00Z |
| BE-011: Create AppsEditorialPresenter | COMPLETED | Code review | 2026-02-16T00:07:00Z |
| BE-012: Create EditorialPresenterCompiler | COMPLETED | Code review | 2026-02-16T00:05:00Z |
| BE-013: Simplify EditorialOrchestrator | COMPLETED | Code review | 2026-02-16T00:08:00Z |
| BE-014: Create ServiceCriticality enum | COMPLETED | PHPStan pass | 2026-02-16T00:05:00Z |
| BE-015: Add degradation to sub-services | PENDING | Degradation tests pass | - |
| INF-001: Configure httplug cache | PENDING | Integration test | - |
| INF-002: Create AMQP messages | PENDING | PHPStan pass | - |
| INF-003: Create AMQP handlers | PENDING | Unit tests pass | - |
| DOC-001: Update product standard docs | PENDING | Review | - |

## Task Details

---

### BE-001: Create ResolvedEditorial DTO

**Role**: Backend Engineer
**Methodology**: TDD (Red-Green-Refactor)
**Slice**: 1
**Complexity**: simple
**Spec**: SPEC-F01

**Functional Requirement**:
- `final readonly class` with typed constructor-promoted properties
- Nullable properties for LOW-criticality service data (section, multimedia, multimediaOpening)
- Array properties typed via PHPDoc (`@param ResolvedInsertedNews[]`, etc.)

**SOLID Requirements**:
- **SRP**: Data only, zero logic
- **DIP**: Use domain model types from vendor clients

**Tests to Write FIRST**:
- [ ] `test_can_be_constructed_with_all_properties()`
- [ ] `test_nullable_properties_accept_null()`

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/DTO/ResolvedEditorial.php`
- [ ] `final readonly class`
- [ ] PHPStan Level 9 passes
- [ ] No business logic in class

**Reference**: `snaapi-develop/src/Orchestrator/Chain/EditorialOrchestrator.php` lines 119-230 (`$resolveData` array structure)

---

### BE-002: Create ResolvedInsertedNews DTO

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 1
**Complexity**: simple
**Spec**: SPEC-F01

**Functional Requirement**:
- `final readonly class` for body-embedded editorial data
- Properties: editorial (NewsBase), section (?Section), signatures (array), multimedia (?array)

**Tests to Write FIRST**:
- [ ] `test_can_be_constructed()`

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/DTO/ResolvedInsertedNews.php`
- [ ] `final readonly class`

**Reference**: EditorialOrchestrator lines 124-161 (inserted news loop data)

---

### BE-003: Create ResolvedRecommendedEditorial DTO

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 1
**Complexity**: simple
**Spec**: SPEC-F01

**Functional Requirement**:
- `final readonly class` for recommended editorial data
- Properties: editorial (NewsBase), section (?Section), signatures (array), multimedia (?array)

**Tests to Write FIRST**:
- [ ] `test_can_be_constructed()`

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/DTO/ResolvedRecommendedEditorial.php`
- [ ] `final readonly class`

**Reference**: EditorialOrchestrator lines 163-207 (recommended loop data)

---

### BE-004: Create SignatureResolver

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 1
**Complexity**: moderate
**Spec**: SPEC-F03

**Functional Requirement**:
- Extract `retrieveAliasFormat()` logic from EditorialOrchestrator (lines 281-296)
- Shared by main editorial, inserted news, and recommended editorials
- Depends on: `QueryJournalistClient`, `JournalistFactory`

**SOLID Requirements**:
- **SRP**: Only resolves journalist aliases → signature array
- **DIP**: Depends on client interfaces

**Tests to Write FIRST**:
- [ ] `test_resolve_returns_signatures_for_editorial_with_aliases()`
- [ ] `test_resolve_returns_empty_array_when_no_aliases()`
- [ ] `test_resolve_handles_journalist_not_found()`

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/Service/SignatureResolver.php`
- [ ] Constructor: `QueryJournalistClient`, `JournalistFactory`, `LoggerInterface`
- [ ] Method: `resolve(NewsBase $editorial): array`
- [ ] Unit tests pass

**Reference**: EditorialOrchestrator::retrieveAliasFormat() lines 281-296

---

### BE-005: Create MultimediaResolver

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 2
**Complexity**: moderate
**Spec**: SPEC-F03

**Functional Requirement**:
- Extract async multimedia logic from EditorialOrchestrator
- Methods: `resolve()` (fetch + settle promises), `fetchAsync()`, `filterFulfilled()`
- Handles: multimedia, multimediaOpening, meta images
- Depends on: `QueryMultimediaClient`, `QueryMultimediaOpeningClient`

**Tests to Write FIRST**:
- [ ] `test_resolve_returns_multimedia_array_for_valid_editorial()`
- [ ] `test_resolve_returns_null_when_multimedia_is_widget()`
- [ ] `test_resolve_filters_only_fulfilled_promises()`
- [ ] `test_resolve_returns_null_on_client_failure()` (LOW criticality)

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/Service/MultimediaResolver.php`
- [ ] Async promise handling preserved (Guzzle Utils::settle())
- [ ] Unit tests pass

**Reference**: EditorialOrchestrator lines 426-535 (multimedia methods)

---

### BE-006: Create InsertedNewsResolver

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 2
**Complexity**: moderate
**Spec**: SPEC-F03

**Functional Requirement**:
- Extract insertedNews loop from EditorialOrchestrator lines 124-161
- Uses `SignatureResolver` and `MultimediaResolver` (composition)
- Filters out invisible editorials silently
- Returns `ResolvedInsertedNews[]`

**Tests to Write FIRST**:
- [ ] `test_resolve_returns_resolved_array_for_valid_inserted_news()`
- [ ] `test_resolve_skips_invisible_editorials()`
- [ ] `test_resolve_returns_empty_array_when_no_body_tags()`

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/Service/InsertedNewsResolver.php`
- [ ] Delegates to SignatureResolver + MultimediaResolver (no duplication)
- [ ] Unit tests pass

**Reference**: EditorialOrchestrator::execute() lines 124-161

---

### BE-007: Create RecommendedEditorialsResolver

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 2
**Complexity**: moderate
**Spec**: SPEC-F03

**Functional Requirement**:
- Extract recommendedEditorials loop from EditorialOrchestrator lines 163-207
- Uses `SignatureResolver` and `MultimediaResolver` (composition)
- Catches and logs errors per editorial (error tolerance)
- Returns `ResolvedRecommendedEditorial[]`

**Tests to Write FIRST**:
- [ ] `test_resolve_returns_resolved_array_for_valid_recommendations()`
- [ ] `test_resolve_catches_and_logs_individual_failures()`
- [ ] `test_resolve_returns_empty_array_when_no_recommendations()`

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/Service/RecommendedEditorialsResolver.php`
- [ ] Delegates to SignatureResolver + MultimediaResolver
- [ ] Error tolerance: catch + log + continue
- [ ] Unit tests pass

**Reference**: EditorialOrchestrator::execute() lines 163-207

---

### BE-008: Create EditorialAggregator

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 2
**Complexity**: complex
**Spec**: SPEC-F02

**Functional Requirement**:
- Implements `EditorialAggregatorInterface`
- Orchestrates all sub-services to produce `ResolvedEditorial`
- Handles: visibility check (BR-EDIT-001), editorial fetch, section fetch, tag fetch
- Delegates to: SignatureResolver, InsertedNewsResolver, RecommendedEditorialsResolver, MultimediaResolver
- Also extracts: photoFromBodyTags, membershipLinks, commentCount

**SOLID Requirements**:
- **SRP**: Orchestrates sub-services only, no direct transformation
- **DIP**: Depends on interfaces for all external clients

**Tests to Write FIRST**:
- [ ] `test_aggregate_returns_resolved_editorial_for_published_editorial()`
- [ ] `test_aggregate_throws_when_editorial_not_visible()`
- [ ] `test_aggregate_delegates_to_all_sub_services()`

**Acceptance Criteria**:
- [ ] File at `src/Aggregator/EditorialAggregator.php`
- [ ] Interface at `src/Aggregator/EditorialAggregatorInterface.php`
- [ ] ≤ 120 LOC
- [ ] Zero imports from DataTransformer/ or Presenter/
- [ ] Unit tests pass

**Reference**: EditorialOrchestrator::execute() lines 98-276 (entire method)

---

### BE-009: Create EditorialPresenterInterface

**Role**: Backend Engineer
**Slice**: 3
**Complexity**: simple
**Spec**: SPEC-F04

**Functional Requirement**:
- Interface with `present(ResolvedEditorial): array` and `supports(string $format): bool`

**Acceptance Criteria**:
- [ ] File at `src/Presenter/EditorialPresenterInterface.php`

**Reference**: Similar to existing tagged service interfaces in the project

---

### BE-010: Create EditorialPresenterRegistry

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 3
**Complexity**: simple
**Spec**: SPEC-F04

**Functional Requirement**:
- Holds array of `EditorialPresenterInterface` instances
- `present(ResolvedEditorial, string $format)` iterates and finds matching presenter
- Throws `\InvalidArgumentException` if no presenter supports the format

**Tests to Write FIRST**:
- [ ] `test_present_delegates_to_matching_presenter()`
- [ ] `test_present_throws_when_no_presenter_matches()`

**Acceptance Criteria**:
- [ ] File at `src/Presenter/EditorialPresenterRegistry.php`
- [ ] Method `addPresenter(EditorialPresenterInterface)` for compiler pass
- [ ] Unit tests pass

---

### BE-011: Create AppsEditorialPresenter

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 3
**Complexity**: complex
**Spec**: SPEC-F05

**Functional Requirement**:
- Implements `EditorialPresenterInterface` for format `'apps'`
- Reuses ALL existing DataTransformer classes without modification
- Extracts transformation logic from EditorialOrchestrator lines 231-276
- Reads data from `ResolvedEditorial` DTO instead of `$resolveData` array
- Output MUST be byte-identical to current implementation

**SOLID Requirements**:
- **SRP**: Only translates DTO → apps format array
- **OCP**: Does not prevent other presenters from being added
- **DIP**: Depends on transformer interfaces

**Tests to Write FIRST**:
- [ ] `test_supports_returns_true_for_apps_format()`
- [ ] `test_supports_returns_false_for_other_formats()`
- [ ] `test_present_produces_identical_output_to_current_orchestrator()` (snapshot test)

**Acceptance Criteria**:
- [ ] File at `src/Presenter/Apps/AppsEditorialPresenter.php`
- [ ] Constructor receives same DataTransformers as current EditorialOrchestrator
- [ ] Output matches current format exactly
- [ ] Zero imports from external clients
- [ ] Unit/snapshot tests pass

**Reference**: EditorialOrchestrator::execute() lines 231-276 (transformation section)

---

### BE-012: Create EditorialPresenterCompiler

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 3
**Complexity**: simple
**Spec**: SPEC-F04

**Functional Requirement**:
- CompilerPass that collects `app.editorial.presenter` tagged services
- Registers them with `EditorialPresenterRegistry::addPresenter()`
- Follows exact pattern of existing `BodyDataTransformerCompiler`

**Tests to Write FIRST**:
- [ ] `test_process_registers_tagged_services_with_registry()`

**Acceptance Criteria**:
- [ ] File at `src/DependencyInjection/Compiler/EditorialPresenterCompiler.php`
- [ ] Config at `config/packages/presenters.yaml`
- [ ] Config at `config/packages/aggregator.yaml`
- [ ] Compiler registered in Kernel

**Reference**: `src/DependencyInjection/Compiler/BodyDataTransformerCompiler.php`

---

### BE-013: Simplify EditorialOrchestrator

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 4
**Complexity**: complex
**Spec**: SPEC-F06

**Functional Requirement**:
- Strip EditorialOrchestrator to thin coordinator
- `execute()`: fetch editorial → check legacy → aggregate → present
- Constructor: `EditorialAggregatorInterface`, `EditorialPresenterRegistry`, `QueryLegacyClient`, `LoggerInterface`
- Keep `canOrchestrate()` returning `'editorial'`
- Keep legacy fallback (BR-EDIT-002) in orchestrator

**Tests to Write FIRST**:
- [ ] `test_execute_delegates_to_aggregator_and_presenter()`
- [ ] `test_execute_returns_legacy_response_when_source_is_null()`
- [ ] `test_can_orchestrate_returns_editorial()`

**Acceptance Criteria**:
- [ ] EditorialOrchestrator ≤ 50 LOC
- [ ] Constructor deps ≤ 4
- [ ] `make test_unit` passes
- [ ] `make test_stan` passes
- [ ] API backward compatible

**Reference**: Current EditorialOrchestrator.php (entire file, to understand what to remove)

---

### BE-014: Create ServiceCriticality Enum

**Role**: Backend Engineer
**Slice**: 5
**Complexity**: simple
**Spec**: SPEC-F07

**Functional Requirement**:
- PHP 8.1 backed enum with CRITICAL and LOW cases
- Used as documentation/reference for sub-service behavior

**Acceptance Criteria**:
- [ ] File at `src/Infrastructure/Enum/ServiceCriticality.php`
- [ ] `enum ServiceCriticality: string { case CRITICAL = 'critical'; case LOW = 'low'; }`

---

### BE-015: Add Degradation Logic to Sub-Services

**Role**: Backend Engineer
**Methodology**: TDD
**Slice**: 5
**Complexity**: moderate
**Spec**: SPEC-F07

**Functional Requirement**:
- Add try/catch to each sub-service with criticality-based behavior
- CRITICAL (editorial fetch in Aggregator): re-throw exception
- LOW (all others): return null/empty + log warning
- Remove all silent `catch(\Throwable) { continue; }` patterns

**Tests to Write FIRST**:
- [ ] `test_multimedia_resolver_returns_null_on_failure()`
- [ ] `test_signature_resolver_returns_empty_on_failure()`
- [ ] `test_aggregator_throws_on_editorial_client_failure()`

**Acceptance Criteria**:
- [ ] Every LOW sub-service logs warning on failure
- [ ] No silent catches in src/Aggregator/
- [ ] Degradation tests pass

---

### INF-001: Configure httplug Cache

**Role**: Infrastructure
**Slice**: 6
**Complexity**: moderate
**Spec**: SPEC-F08

**Functional Requirement**:
- Configure httplug CachePlugin in `config/packages/httplug.yaml`
- Couchbase PSR-6 adapter as backend
- stale-if-error: 3600s
- All external clients use cached HTTP client

**Acceptance Criteria**:
- [ ] `config/packages/httplug.yaml` exists with cache configuration
- [ ] All clients configured to use cache plugin
- [ ] stale-if-error enabled

**Reference**: php-http/cache-plugin documentation

---

### INF-002: Create AMQP Messages

**Role**: Infrastructure
**Slice**: 7
**Complexity**: simple
**Spec**: SPEC-F09

**Functional Requirement**:
- Create Message DTOs: EditorialUpdated, EditorialDeleted, SectionUpdated, MultimediaUpdated, JournalistUpdated, TagUpdated
- Each message has `id` property (entity identifier)

**Acceptance Criteria**:
- [ ] 6 message classes in `src/Message/`
- [ ] Final readonly classes with typed properties

---

### INF-003: Create AMQP Handlers

**Role**: Infrastructure
**Methodology**: TDD
**Slice**: 7
**Complexity**: moderate
**Spec**: SPEC-F09

**Functional Requirement**:
- Handler per message type
- Each handler: invalidate httplug Couchbase key + trigger Varnish BAN
- Follow existing PurgeEditorialHandler pattern
- Configure Messenger routing

**Tests to Write FIRST**:
- [ ] `test_section_updated_handler_invalidates_cache_key()`
- [ ] `test_editorial_updated_handler_triggers_varnish_ban()`

**Acceptance Criteria**:
- [ ] 5 handler classes in `src/MessageHandler/`
- [ ] Messenger routing in `config/packages/messenger.yaml`
- [ ] Unit tests pass

**Reference**: `src/MessageHandler/PurgeEditorialHandler.php`

---

### DOC-001: Update Product Standard Documentation

**Role**: Documentation
**Slice**: 8
**Complexity**: simple
**Spec**: SPEC-F10

**Functional Requirement**:
- Update `snaapi-develop/CLAUDE.md` with new architecture (Aggregator → Presenter flow)
- Verify all openspec specs are up to date

**Acceptance Criteria**:
- [ ] `snaapi-develop/CLAUDE.md` reflects new architecture
- [ ] All openspec specs consistent with implementation

---

## Decision Log

| Decision | Alternatives Considered | Rationale | Phase |
|----------|------------------------|-----------|-------|
| Nullable DTO properties for LOW services | Separate DegradedEditorial DTO | Simpler, single type to handle everywhere | Phase 3 |
| Legacy fallback stays in Orchestrator | Move to Aggregator | Short-circuits entire pipeline, different response type | Phase 3 |
| No external circuit breaker library | symfony/http-client retry, ganesha | Overhead not justified; explicit try/catch per service is clearer | Phase 3 |
| httplug CachePlugin (not per-client cache) | Per-client Couchbase cache | Single config, transparent, respects HTTP semantics | Phase 3 |
| One handler per AMQP event (not generic) | Single GenericCacheInvalidationHandler | SRP, easier to test, matches existing PurgeEditorialHandler | Phase 3 |

## Workflow State

**Planner**: COMPLETED | **Implementer**: IN_PROGRESS | **Reviewer**: PENDING
**Feature**: api-refactoring
**Started**: 2026-02-15T22:15:00Z
**Last Updated**: 2026-02-15T23:30:00Z
**Last Phase**: Phase 4 | **Resume Point**: /workflows:work

### Planning Progress

| Phase | Status | Output File | Written At |
|-------|--------|-------------|------------|
| Step 0 (Load Specs) | COMPLETED | (context only) | 2026-02-15T23:00:00Z |
| Phase 1 (Understand) | COMPLETED | proposal.md | 2026-02-15T23:10:00Z |
| Phase 2 (Specs) | COMPLETED | specs.md | 2026-02-15T23:20:00Z |
| Phase 3 (Design) | COMPLETED | design.md | 2026-02-15T23:25:00Z |
| Phase 4 (Tasks) | COMPLETED | tasks.md | 2026-02-15T23:30:00Z |
| Completeness Check | COMPLETED | (verified) | 2026-02-15T23:30:00Z |

### Implementer Section
<!-- Added by /workflows:work when implementation starts -->
<!--
**Status**: PENDING
**Last Updated**: -
-->

### QA / Reviewer Section
<!-- Added by /workflows:review after implementation -->
<!--
**Status**: PENDING
**Review Date**: -
-->

## Success Criteria

- [ ] All existing tests pass (`make test_unit`)
- [ ] PHPStan Level 9 passes (`make test_stan`)
- [ ] Mutation testing MSI ≥ 79% (`make test_infection`)
- [ ] EditorialOrchestrator ≤ 50 LOC, ≤ 4 constructor deps
- [ ] API response format unchanged (backward compatible)
- [ ] ResolvedEditorial is `final readonly class`
- [ ] No imports of DataTransformer/ in Aggregator/
- [ ] No imports of Client/Http in Presenter/
- [ ] Aggregator-Presenter pattern documented as mandatory in openspec/
- [ ] Adding new format requires ONLY: new class + tag (no existing code changes)
- [ ] CRITICAL service failure returns 503
- [ ] LOW service failure returns degraded response + logged warning
- [ ] httplug cache configured for all external clients
- [ ] AMQP handlers exist for all event types
