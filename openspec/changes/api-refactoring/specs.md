# Functional Specs: api-refactoring

> Phase 2 output from `/workflows:plan api-refactoring`
> Generated: 2026-02-15
> Planning depth: full

---

## Functional Specifications (WHAT)

### SPEC-F01: Typed Domain Aggregate

**Description**: The system must produce a typed, immutable domain aggregate (`ResolvedEditorial`) containing ALL resolved data from external services, before any format-specific transformation occurs.

**Acceptance Criteria**:
- [ ] `ResolvedEditorial` is a `final readonly class`
- [ ] Contains typed properties for: editorial, section, tags, signatures, multimedia, multimediaOpening, insertedNews, recommendedEditorials, photoFromBodyTags, membershipLinks, commentCount
- [ ] Sub-DTOs exist: `ResolvedInsertedNews` (editorial + section + signatures + multimedia), `ResolvedRecommendedEditorial` (editorial + section + signatures + multimedia)
- [ ] All properties use PHP native types or typed arrays (no `mixed` except where external clients force it)

**Verification**: PHPStan Level 9 passes. DTOs contain zero business logic.

---

### SPEC-F02: Aggregation Layer Separation

**Description**: All data fetching and resolution from external services must be consolidated into a dedicated Aggregator layer that returns `ResolvedEditorial`.

**Acceptance Criteria**:
- [ ] `EditorialAggregatorInterface` defines `aggregate(string $editorialId): ResolvedEditorial`
- [ ] `EditorialAggregator` implements the interface by delegating to sub-services
- [ ] Visibility check (editorial must be published) happens in the aggregator
- [ ] Legacy fallback (no sourceEditorial → legacy client) happens in the aggregator
- [ ] Aggregator has ZERO imports from `App\Application\DataTransformer\*`
- [ ] Aggregator has ZERO imports from `App\Presenter\*`

**Verification**: `grep -r 'DataTransformer\|Presenter' src/Aggregator/` returns 0 results.

---

### SPEC-F03: Unified Sub-Editorial Resolution

**Description**: The duplicated logic for fetching insertedNews and recommendedEditorials must be unified into reusable sub-services that share common resolution logic (signature resolution, multimedia fetching).

**Acceptance Criteria**:
- [ ] `SignatureResolver` resolves journalist aliases for any editorial (shared by main, inserted, recommended)
- [ ] `InsertedNewsResolver` aggregates body-embedded editorials into `ResolvedInsertedNews[]`
- [ ] `RecommendedEditorialsResolver` aggregates recommended editorials into `ResolvedRecommendedEditorial[]`
- [ ] `MultimediaResolver` handles async multimedia fetching and promise settlement
- [ ] No duplicated fetch-section-signatures-multimedia pattern exists

**Verification**: No two classes contain the same sequence of editorial + section + signatures + multimedia fetching.

---

### SPEC-F04: Extensible Presentation Layer

**Description**: Data presentation must be handled by a registry of format-specific presenters, selectable by format string. New formats are added by creating a new class only (OCP).

**Acceptance Criteria**:
- [ ] `EditorialPresenterInterface` defines `present(ResolvedEditorial): array` and `supports(string $format): bool`
- [ ] `EditorialPresenterRegistry` iterates registered presenters and delegates to the matching one
- [ ] Presenters are registered via Symfony service tag `app.editorial.presenter`
- [ ] Adding a new format requires: (1) new Presenter class, (2) service tag registration, (3) nothing else
- [ ] Presenters have ZERO imports from `Ec\*\Infrastructure\Client\*` or `GuzzleHttp\Promise\*`

**Verification**: `grep -r 'QueryClient\|Client\\Http\|Promise' src/Presenter/` returns 0 results.

---

### SPEC-F05: Apps Format Backward Compatibility

**Description**: The `AppsEditorialPresenter` must produce output identical to the current `EditorialOrchestrator.execute()` for the same input data.

**Acceptance Criteria**:
- [ ] `AppsEditorialPresenter` reuses ALL existing DataTransformer classes (DetailsAppsDataTransformer, BodyDataTransformer, StandfirstDataTransformer, RecommendedEditorialsDataTransformer, MultimediaDataTransformer, JournalistsDataTransformer, MediaDataTransformerHandler)
- [ ] Output array structure matches current format key-by-key
- [ ] Existing DataTransformer classes are NOT modified
- [ ] Existing `EditorialOrchestratorTest` data provider assertions still pass

**Verification**: Snapshot test comparing old vs new output for the same mock data.

---

### SPEC-F06: Simplified Orchestrator

**Description**: `EditorialOrchestrator` must be reduced to a thin coordinator that delegates to the aggregator and presenter.

**Acceptance Criteria**:
- [ ] `EditorialOrchestrator.execute()` calls `aggregator.aggregate(id)` then `presenterRegistry.present(resolved, 'apps')`
- [ ] Class is ≤ 50 LOC
- [ ] Constructor dependencies ≤ 4 (aggregator, presenterRegistry, legacyClient, logger)
- [ ] Visibility check and legacy fallback are delegated to the aggregator
- [ ] `canOrchestrate()` still returns `'editorial'`

**Verification**: `wc -l EditorialOrchestrator.php` ≤ 50. Constructor has ≤ 4 params.

---

### SPEC-F07: Circuit Breaker with Service Criticality

**Description**: Each Aggregator sub-service must handle external service failures based on criticality level: CRITICAL services fail the entire request; LOW services degrade gracefully.

**Acceptance Criteria**:
- [ ] `ServiceCriticality` enum exists with CRITICAL and LOW cases
- [ ] editorial-client failures throw `ServiceUnavailableException` (503)
- [ ] multimedia-client failures return null multimedia + logged warning
- [ ] section-client failures return null section + logged warning
- [ ] journalist-client failures return empty signatures + logged warning
- [ ] tag-client failures return empty tags + logged warning
- [ ] membership-client failures return empty membership links + logged warning
- [ ] No silent `catch (\Throwable) { continue; }` exists in `src/Aggregator/`

**Verification**: Unit tests for each degradation scenario. `grep -r 'catch.*Throwable.*continue' src/Aggregator/` returns 0.

---

### SPEC-F08: Transparent HTTP Cache via httplug

**Description**: All external HTTP client calls must be cached transparently via httplug CachePlugin with Couchbase backend, with stale-if-error support.

**Acceptance Criteria**:
- [ ] httplug CachePlugin configured in `config/packages/httplug.yaml`
- [ ] Couchbase PSR-6 adapter as cache backend
- [ ] stale-if-error enabled (serves stale response when service unavailable)
- [ ] All external clients (editorial, section, multimedia, journalist, tag, membership, widget) use the cached HTTP client
- [ ] No per-client caching logic in `src/Aggregator/`

**Verification**: httplug config exists. Integration test confirms cache hit on repeated calls.

---

### SPEC-F09: Reactive Cache Invalidation via AMQP

**Description**: Content changes in microservices must trigger cache invalidation in SNAAPI via AMQP events consumed by Symfony Messenger handlers.

**Acceptance Criteria**:
- [ ] Message classes exist for: `EditorialUpdated`, `EditorialDeleted`, `SectionUpdated`, `MultimediaUpdated`, `JournalistUpdated`, `TagUpdated`
- [ ] Handler classes exist for each message, invalidating the correct httplug Couchbase keys
- [ ] Handlers also trigger Varnish BAN for affected endpoints
- [ ] Messenger routing configured for all message classes
- [ ] Pattern follows existing `PurgeEditorialHandler`

**Verification**: Unit tests for each handler verifying correct cache key invalidation and Varnish BAN calls.

---

### SPEC-F10: Product Standard Documentation

**Description**: The Aggregator-Presenter pattern must be documented as the mandatory product standard for all future features exposing data via API.

**Acceptance Criteria**:
- [ ] `aggregator-presenter-pattern.yaml` exists in openspec with rules and verification steps
- [ ] `architecture-profile.yaml` references the pattern as mandatory
- [ ] `snaapi-develop/CLAUDE.md` includes the new architecture in key patterns
- [ ] Clear instructions for "how to add a new format" included

**Verification**: Documentation exists and is referenced in manifest.

---

## Integration Analysis

### Entities Impact

#### EXTENDED (existing entities with new behavior)
| Entity | Change | Reason |
|--------|--------|--------|
| `EditorialOrchestrator` | Reduced to thin coordinator | Delegates to Aggregator + Presenter |
| `EditorialOrchestratorTest` | Simplified mocking | Only mocks aggregator + presenter |

#### NEW (entities created by this feature)
| Entity | Purpose | Layer |
|--------|---------|-------|
| `ResolvedEditorial` | Typed aggregate DTO | Aggregator/DTO |
| `ResolvedInsertedNews` | Sub-DTO for inserted news | Aggregator/DTO |
| `ResolvedRecommendedEditorial` | Sub-DTO for recommended | Aggregator/DTO |
| `EditorialAggregatorInterface` | Aggregation contract | Aggregator |
| `EditorialAggregator` | Aggregation implementation | Aggregator |
| `SignatureResolver` | Journalist alias resolution | Aggregator/Service |
| `InsertedNewsResolver` | Inserted news aggregation | Aggregator/Service |
| `RecommendedEditorialsResolver` | Recommended editorial aggregation | Aggregator/Service |
| `MultimediaResolver` | Async multimedia fetching | Aggregator/Service |
| `EditorialPresenterInterface` | Presentation contract | Presenter |
| `EditorialPresenterRegistry` | Presenter routing | Presenter |
| `AppsEditorialPresenter` | Apps format implementation | Presenter/Apps |
| `EditorialPresenterCompiler` | DI compiler pass | DependencyInjection |
| `ServiceCriticality` | Criticality enum | Infrastructure/Enum |
| `EditorialUpdated` (+ 5 more) | AMQP messages | Message |
| `EditorialUpdatedHandler` (+ 5 more) | AMQP handlers | MessageHandler |

### API Contracts Impact

#### UNCHANGED
| Endpoint | Change | Backward Compatible |
|----------|--------|---------------------|
| `GET /v1/editorials/{id}` | Internal refactoring only | YES - response format identical |
| `GET /v1/editorials/{id}/comments` | No change | YES |

#### NEW (internal contracts)
| Interface | Contract |
|-----------|----------|
| `EditorialAggregatorInterface` | `aggregate(string $id): ResolvedEditorial` |
| `EditorialPresenterInterface` | `present(ResolvedEditorial): array` + `supports(string): bool` |
| `EditorialPresenterRegistry` | `present(ResolvedEditorial, string $format): array` |

### Business Rules Impact

#### PRESERVED (no behavior change)
| Rule | Current Location | New Location |
|------|-----------------|--------------|
| BR-EDIT-001 (Visibility Gate) | EditorialOrchestrator::execute | EditorialAggregator::aggregate |
| BR-EDIT-002 (Legacy Fallback) | EditorialOrchestrator::execute | EditorialOrchestrator::execute (stays) |
| BR-EDIT-003 (Inserted News) | EditorialOrchestrator::execute | InsertedNewsResolver::resolve |
| BR-EDIT-004 (Recommended Editorials) | EditorialOrchestrator::execute | RecommendedEditorialsResolver::resolve |
| BR-EDIT-005 (Multimedia Async) | EditorialOrchestrator::execute | MultimediaResolver::resolve |
| BR-CROSS-001 (Signature Resolution) | EditorialOrchestrator::retrieveAliasFormat | SignatureResolver::resolve |

#### MODIFIED (improved behavior)
| Rule | Change | Reason |
|------|--------|--------|
| BR-EDIT-004 error handling | Silent `catch(\Throwable)` → criticality-based degradation | RES-001, RES-002 |

#### CONFLICTS: None detected

### Test Contract Sketch

| Spec | Test Type | Key Boundaries |
|------|-----------|----------------|
| SPEC-F01 | Unit | DTO construction, immutability, type enforcement |
| SPEC-F02 | Unit + Integration | Aggregator delegates correctly, returns typed DTO |
| SPEC-F03 | Unit | Each resolver handles happy path + failure + empty input |
| SPEC-F04 | Unit | Registry routes to correct presenter, throws on unknown format |
| SPEC-F05 | Integration (snapshot) | Apps presenter output matches current orchestrator output |
| SPEC-F06 | Unit | Orchestrator delegates correctly, ≤ 50 LOC |
| SPEC-F07 | Unit | Each criticality scenario (CRITICAL fail, LOW degrade) |
| SPEC-F08 | Integration | Cache hit/miss with httplug mock |
| SPEC-F09 | Unit | Each handler invalidates correct keys |
| SPEC-F10 | Manual | Documentation review |
