# Design: SNAAPI API Refactoring - Two-Layer Architecture

> Phase 3 output from `/workflows:plan api-refactoring`
> Generated: 2026-02-15
> Planning depth: full

---

## SOLID Baseline (Current State)

| Principle | Current State | Violation | Severity |
|-----------|--------------|-----------|----------|
| **SRP** | EditorialOrchestrator: 536 LOC, 19 deps, mixes fetching + transformation + async + error handling | God class | HIGH |
| **OCP** | New output format requires modifying EditorialOrchestrator | Closed for extension | HIGH |
| **LSP** | 4-level inheritance in body transformers | Minor concern | LOW |
| **ISP** | EditorialOrchestratorInterface is small (2 methods) | Compliant | OK |
| **DIP** | Constructor injection via interfaces | Mostly compliant | OK |

---

## Solutions

### Solution for SPEC-F01: Typed Domain Aggregate (ResolvedEditorial)

**Approach**: Create `final readonly class` DTOs using PHP 8.1+ constructor promotion. Properties map 1:1 to the data currently in `$resolveData` array.

**SOLID Compliance**:
- **SRP**: COMPLIANT - DTOs hold data only, zero logic
- **OCP**: COMPLIANT - New properties can be added without breaking existing presenters (nullable)
- **LSP**: N/A - No inheritance, final classes
- **ISP**: N/A - No interfaces on DTOs
- **DIP**: COMPLIANT - DTOs use domain model types from vendor clients, not infrastructure

**Files to Create**:
| File | Purpose |
|------|---------|
| `src/Aggregator/DTO/ResolvedEditorial.php` | Main aggregate DTO (~40 LOC) |
| `src/Aggregator/DTO/ResolvedInsertedNews.php` | Sub-DTO for body-embedded editorials (~20 LOC) |
| `src/Aggregator/DTO/ResolvedRecommendedEditorial.php` | Sub-DTO for recommended editorials (~20 LOC) |
| `tests/Aggregator/DTO/ResolvedEditorialTest.php` | DTO construction tests |

**Key Design Decision**: Properties that come from LOW-criticality services are nullable (`?Section`, `?array` for multimedia). This allows the aggregator to construct a valid `ResolvedEditorial` even when some services are degraded.

```php
final readonly class ResolvedEditorial {
    public function __construct(
        public NewsBase $editorial,           // CRITICAL - never null
        public ?Section $section,             // LOW - nullable for degradation
        public array $tags,                   // LOW - empty array on failure
        public array $signatures,             // LOW - empty array on failure
        public ?array $multimedia,            // LOW - null on failure
        public ?array $multimediaOpening,     // LOW - null on failure
        public array $insertedNews,           // ResolvedInsertedNews[]
        public array $recommendedEditorials,  // ResolvedRecommendedEditorial[]
        public array $photoFromBodyTags,      // extracted from body
        public array $membershipLinks,        // LOW - empty on failure
        public int $commentCount,             // fallback: 0
    ) {}
}
```

---

### Solution for SPEC-F02 + SPEC-F03: Aggregation Layer

**Approach**: Extract all fetching logic from EditorialOrchestrator into EditorialAggregator + 4 sub-services. Each sub-service handles one concern and encapsulates its own error handling based on service criticality.

**SOLID Compliance**:
- **SRP**: COMPLIANT - Each sub-service has one job: resolve one type of data
  - `EditorialAggregator`: orchestrates sub-services → ResolvedEditorial
  - `SignatureResolver`: journalist alias → signature array
  - `InsertedNewsResolver`: body tags → ResolvedInsertedNews[]
  - `RecommendedEditorialsResolver`: editorial refs → ResolvedRecommendedEditorial[]
  - `MultimediaResolver`: multimedia IDs → resolved multimedia data
- **OCP**: COMPLIANT - New data sources added by creating new resolvers, aggregator composes them
- **LSP**: N/A - No inheritance
- **ISP**: COMPLIANT - EditorialAggregatorInterface has 1 method
- **DIP**: COMPLIANT - Aggregator depends on interface. Sub-services depend on vendor client interfaces

**Files to Create**:
| File | Purpose |
|------|---------|
| `src/Aggregator/EditorialAggregatorInterface.php` | Contract: `aggregate(string): ResolvedEditorial` |
| `src/Aggregator/EditorialAggregator.php` | Implementation (~120 LOC) |
| `src/Aggregator/Service/SignatureResolver.php` | Shared signature resolution (~50 LOC) |
| `src/Aggregator/Service/InsertedNewsResolver.php` | Inserted news aggregation (~80 LOC) |
| `src/Aggregator/Service/RecommendedEditorialsResolver.php` | Recommended editorial aggregation (~70 LOC) |
| `src/Aggregator/Service/MultimediaResolver.php` | Async multimedia + promise settlement (~60 LOC) |
| `tests/Aggregator/EditorialAggregatorTest.php` | Aggregator integration test |
| `tests/Aggregator/Service/SignatureResolverTest.php` | Unit test |
| `tests/Aggregator/Service/InsertedNewsResolverTest.php` | Unit test |
| `tests/Aggregator/Service/RecommendedEditorialsResolverTest.php` | Unit test |
| `tests/Aggregator/Service/MultimediaResolverTest.php` | Unit test |

**Logic Extraction Map** (from EditorialOrchestrator):

| Current Location | New Location | Lines |
|-----------------|--------------|-------|
| `execute()` lines 98-118 (fetch editorial, section, visibility check) | `EditorialAggregator::aggregate()` | 98-118 |
| `execute()` lines 119-123 (signature resolution for main editorial) | `SignatureResolver::resolve()` | 119-123 |
| `execute()` lines 124-161 (inserted news loop) | `InsertedNewsResolver::resolve()` | 124-161 |
| `execute()` lines 163-207 (recommended editorials loop) | `RecommendedEditorialsResolver::resolve()` | 163-207 |
| `execute()` lines 208-230 (multimedia promises) | `MultimediaResolver::resolve()` | 208-230 |
| `retrieveAliasFormat()` lines 281-296 | `SignatureResolver::resolve()` | 281-296 |
| `getAsyncMultimedia()` lines 426-435 | `MultimediaResolver::fetchAsync()` | 426-435 |
| `fulfilledMultimedia()` lines 496-509 | `MultimediaResolver::filterFulfilled()` | 496-509 |
| `retrievePhotosFromBodyTags()` lines 306-323 | `EditorialAggregator::extractPhotosFromBodyTags()` | 306-323 |
| `getPromiseMembershipLinks()` lines 373-395 | `EditorialAggregator::resolveMembershipLinks()` | 373-395 |

**Shared Pattern** (eliminates duplication):
```php
// Both InsertedNewsResolver and RecommendedEditorialsResolver use:
$section = $this->sectionClient->findSectionById($editorial->sectionId());
$signatures = $this->signatureResolver->resolve($editorial);
$multimedia = $this->multimediaResolver->resolve($editorial);
// → construct ResolvedInsertedNews / ResolvedRecommendedEditorial
```

---

### Solution for SPEC-F04 + SPEC-F05: Presentation Layer

**Approach**: Strategy pattern with registry. `EditorialPresenterRegistry` holds tagged presenters and dispatches by format. `AppsEditorialPresenter` reuses all existing DataTransformers.

**SOLID Compliance**:
- **SRP**: COMPLIANT - Registry routes, presenter translates, transformers transform
- **OCP**: COMPLIANT - New format = new class + tag, zero changes to existing code
- **LSP**: N/A - No inheritance between presenters
- **ISP**: COMPLIANT - EditorialPresenterInterface has 2 methods (present + supports)
- **DIP**: COMPLIANT - Registry depends on interface, not concrete presenters

**Files to Create**:
| File | Purpose |
|------|---------|
| `src/Presenter/EditorialPresenterInterface.php` | Contract (~10 LOC) |
| `src/Presenter/EditorialPresenterRegistry.php` | Strategy dispatcher (~30 LOC) |
| `src/Presenter/Apps/AppsEditorialPresenter.php` | Apps format (~150 LOC, reuses transformers) |
| `src/DependencyInjection/Compiler/EditorialPresenterCompiler.php` | Tag-based DI (~25 LOC) |
| `config/packages/presenters.yaml` | Service registration |
| `tests/Presenter/EditorialPresenterRegistryTest.php` | Registry routing tests |
| `tests/Presenter/Apps/AppsEditorialPresenterTest.php` | Output compatibility tests |

**AppsEditorialPresenter Design** (reuse mapping):
```php
class AppsEditorialPresenter implements EditorialPresenterInterface {
    public function __construct(
        private AppsDataTransformer $detailsTransformer,     // reuse
        private BodyDataTransformer $bodyTransformer,         // reuse
        private StandfirstDataTransformer $standfirstTransformer, // reuse
        private RecommendedEditorialsDataTransformer $recommendedTransformer, // reuse
        private MultimediaDataTransformer $multimediaTransformer, // reuse
        private JournalistsDataTransformer $journalistsTransformer, // reuse
        private MediaDataTransformerHandler $mediaHandler,     // reuse
        private MultimediaOrchestratorHandler $multimediaTypeHandler, // reuse
    ) {}

    public function present(ResolvedEditorial $resolved): array {
        // Extract data from DTO → call existing transformers → return array
        // This is the transformation logic currently in EditorialOrchestrator lines 231-276
    }
}
```

**CompilerPass Pattern** (follows existing BodyDataTransformerCompiler):
```php
class EditorialPresenterCompiler implements CompilerPassInterface {
    public function process(ContainerBuilder $container): void {
        $registryDef = $container->findDefinition(EditorialPresenterRegistry::class);
        $tagged = $container->findTaggedServiceIds('app.editorial.presenter');
        foreach ($tagged as $id => $tags) {
            $registryDef->addMethodCall('addPresenter', [new Reference($id)]);
        }
    }
}
```

---

### Solution for SPEC-F06: Simplified Orchestrator

**Approach**: Strip EditorialOrchestrator to ~50 LOC thin coordinator.

**Files to Modify**:
| File | Change |
|------|--------|
| `src/Orchestrator/Chain/EditorialOrchestrator.php` | 536 → ~50 LOC |
| `tests/Orchestrator/Chain/EditorialOrchestratorTest.php` | ~1700 → ~200 LOC |

**New EditorialOrchestrator**:
```php
class EditorialOrchestrator implements EditorialOrchestratorInterface {
    public function __construct(
        private EditorialAggregatorInterface $aggregator,
        private EditorialPresenterRegistry $presenterRegistry,
        private QueryLegacyClient $queryLegacyClient,
        private LoggerInterface $logger,
    ) {}

    public function execute(Request $request): array {
        $id = $request->get('id');

        // BR-EDIT-002: Legacy fallback (must check before aggregation)
        // This stays here because it short-circuits the entire flow
        $editorial = $this->fetchEditorialOrLegacy($id);
        if (is_array($editorial)) {
            return $editorial; // legacy response
        }

        $resolved = $this->aggregator->aggregate($id);
        return $this->presenterRegistry->present($resolved, 'apps');
    }

    public function canOrchestrate(): string { return 'editorial'; }
}
```

**Note**: The legacy fallback check may stay in the orchestrator since it determines whether to use the new pipeline at all.

---

### Solution for SPEC-F07: Circuit Breaker with Service Criticality

**Approach**: Each sub-service wraps external calls in try/catch. Behavior depends on `ServiceCriticality` enum. No external circuit breaker library needed.

**SOLID Compliance**:
- **SRP**: COMPLIANT - Each resolver handles its own degradation policy
- **OCP**: COMPLIANT - New criticality levels can be added to the enum
- **DIP**: COMPLIANT - LoggerInterface injected for warning logging

**Files to Create**:
| File | Purpose |
|------|---------|
| `src/Infrastructure/Enum/ServiceCriticality.php` | Enum with CRITICAL, LOW |
| `tests/Aggregator/Service/SignatureResolverDegradationTest.php` | Failure scenario tests |
| `tests/Aggregator/Service/MultimediaResolverDegradationTest.php` | Failure scenario tests |

**Pattern in sub-services**:
```php
// In MultimediaResolver (LOW criticality):
try {
    return $this->multimediaClient->findMultimediaById($id);
} catch (\Throwable $e) {
    $this->logger->warning('Multimedia service degraded', [
        'editorialId' => $editorialId,
        'multimediaId' => $id,
        'error' => $e->getMessage(),
    ]);
    return null;
}
```

---

### Solution for SPEC-F08: httplug Cache

**Approach**: Configure httplug CachePlugin as middleware for all external HTTP clients.

**Files to Create/Modify**:
| File | Purpose |
|------|---------|
| `config/packages/httplug.yaml` | httplug cache plugin configuration |
| `src/Infrastructure/Cache/CouchbaseCacheAdapter.php` | PSR-6 adapter (if not already available) |

**Configuration**:
```yaml
# config/packages/httplug.yaml
httplug:
  plugins:
    cache:
      cache_pool: cache.couchbase
      config:
        default_ttl: 300
        respect_response_cache_directives: ['max-age', 'no-cache', 'no-store']
        cache_key_generator: null  # default HTTP method + URL
  clients:
    editorial:
      plugins: ['httplug.plugin.cache']
    section:
      plugins: ['httplug.plugin.cache']
    multimedia:
      plugins: ['httplug.plugin.cache']
    journalist:
      plugins: ['httplug.plugin.cache']
    tag:
      plugins: ['httplug.plugin.cache']
    membership:
      plugins: ['httplug.plugin.cache']
    widget:
      plugins: ['httplug.plugin.cache']
```

---

### Solution for SPEC-F09: AMQP Reactive Invalidation

**Approach**: Follow existing `PurgeEditorialHandler` pattern. One Message + Handler per event type.

**SOLID Compliance**:
- **SRP**: COMPLIANT - Each handler handles one event type
- **OCP**: COMPLIANT - New events added by creating new Message + Handler
- **DIP**: COMPLIANT - Handlers depend on cache pool interface for invalidation

**Files to Create**:
| File | Purpose |
|------|---------|
| `src/Message/EditorialUpdated.php` | AMQP message DTO |
| `src/Message/SectionUpdated.php` | AMQP message DTO |
| `src/Message/MultimediaUpdated.php` | AMQP message DTO |
| `src/Message/JournalistUpdated.php` | AMQP message DTO |
| `src/Message/TagUpdated.php` | AMQP message DTO |
| `src/MessageHandler/EditorialUpdatedHandler.php` | Invalidate editorial cache + Varnish BAN |
| `src/MessageHandler/SectionUpdatedHandler.php` | Invalidate section cache + Varnish BAN |
| `src/MessageHandler/MultimediaUpdatedHandler.php` | Invalidate multimedia cache |
| `src/MessageHandler/JournalistUpdatedHandler.php` | Invalidate journalist cache |
| `src/MessageHandler/TagUpdatedHandler.php` | Invalidate tag cache |
| `tests/MessageHandler/SectionUpdatedHandlerTest.php` | Cache key invalidation test |
| `tests/MessageHandler/EditorialUpdatedHandlerTest.php` | Cache + Varnish BAN test |

---

## Architectural Impact

### Layer Analysis

| Layer | Impact Level | Changes |
|-------|-------------|---------|
| **Aggregator (NEW)** | HIGH | 9 new files (interface, impl, 3 DTOs, 4 services) |
| **Presenter (NEW)** | HIGH | 4 new files (interface, registry, apps presenter, compiler) |
| **Orchestrator** | HIGH | 1 file dramatically simplified (536 → ~50 LOC) |
| **Infrastructure** | MEDIUM | 1 new enum, 1 cache adapter |
| **Message/Handler** | MEDIUM | 10 new files (5 messages + 5 handlers) |
| **DependencyInjection** | LOW | 1 new compiler pass |
| **Config** | LOW | 3 new yaml files |
| **Controller** | NONE | Zero changes |
| **DataTransformer** | NONE | Zero changes (reused as-is) |
| **Tests** | HIGH | ~12 new test classes + 1 dramatically simplified |

### Change Scope Summary

```
Files to CREATE:    ~30 (9 aggregator + 4 presenter + 1 enum + 10 message/handler + 3 config + ~12 tests)
Files to MODIFY:      2 (EditorialOrchestrator.php + EditorialOrchestratorTest.php)
Files to DELETE:      0
─────────────────────
Total affected:     ~32

Estimated LOC added:    ~1500 (new classes + tests)
Estimated LOC removed:  ~480 (from EditorialOrchestrator simplification)
Net LOC change:         +~1020

Complexity: COMPLEX (multi-layer, 8 slices)
```

### Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| AppsPresenter output doesn't match current format | MEDIUM | CRITICAL | Snapshot test comparing old vs new output for same mock data |
| EditorialOrchestrator legacy fallback regression | LOW | HIGH | Keep legacy check in orchestrator, test explicitly |
| Async promise resolution breaks in MultimediaResolver | MEDIUM | MEDIUM | Extract exact Guzzle Utils::settle() pattern, unit test with mock promises |
| httplug cache config incompatible with existing clients | LOW | MEDIUM | Test each client individually in integration test |
| AMQP handler misses cache key | LOW | LOW | Key generation matches httplug's deterministic key pattern |

### Security Threat Analysis

| Threat | Surface | Mitigation |
|--------|---------|------------|
| Cache poisoning via httplug | Couchbase cache entries | httplug only caches GET responses, respects no-store directive |
| AMQP event spoofing | RabbitMQ queue | Internal network only, authenticated consumers |
| Stale data served after invalidation failure | httplug stale-if-error | TTL limits staleness to 1 hour max |
| Log injection via error messages | LoggerInterface | Structured logging with context array, not string interpolation |

---

## SOLID Verdict

| Principle | Status | Justification |
|-----------|--------|---------------|
| **SRP** | COMPLIANT | God class decomposed into 9+ focused classes. Each class has one reason to change. |
| **OCP** | COMPLIANT | New formats via EditorialPresenterInterface. New events via Message+Handler. New resolvers composable. |
| **LSP** | N/A | No inheritance hierarchies introduced. All new classes are final or implement interfaces. |
| **ISP** | COMPLIANT | EditorialAggregatorInterface: 1 method. EditorialPresenterInterface: 2 methods. All focused. |
| **DIP** | COMPLIANT | Aggregator depends on interface. Presenters injected via tag. Handlers depend on cache pool interface. |

**Verdict: ALL PRINCIPLES COMPLIANT. Proceed to Phase 4.**
