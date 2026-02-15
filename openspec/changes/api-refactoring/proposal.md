# Proposal: SNAAPI API Refactoring - Two-Layer Architecture with Resilience

> Phase 1 output from `/workflows:plan api-refactoring`
> Generated: 2026-02-15
> Planning depth: full

## Request Analysis

**Original Request**: Decompose the EditorialOrchestrator god class (536 LOC, 19 constructor dependencies) into a two-layer architecture (Aggregator → Presenter) with typed DTOs, extensible presentation formats, resilient service calls, and hybrid caching with reactive invalidation.

**Request Type**: Refactoring/Improvement (Architectural)
**Affected Areas**: Orchestrator, new Aggregator layer, new Presenter layer, Infrastructure (cache, messaging), DependencyInjection
**Confidence Level**: 95% (shaped brief exists with full requirements and approved slices)

---

## Problem Statement

### What We're Building

A clean two-layer architecture that separates **data aggregation** (fetching from 7+ microservices) from **data presentation** (serializing to format-specific JSON), connected by a typed, immutable domain aggregate (`ResolvedEditorial`).

Additionally:
- **Resilient service calls** with circuit breaker pattern and criticality-based degradation
- **Hybrid cache** with httplug + Couchbase for transparent HTTP caching
- **Reactive invalidation** via AMQP events from microservices

### Why It's Needed

1. **EditorialOrchestrator is a god class** (536 lines, 19 constructor deps) that violates SRP by mixing aggregation, transformation, async promise handling, error handling, and presentation concerns in a single `execute()` method.

2. **No typed intermediate model exists**. Data flows through `$resolveData` (an untyped array) from HTTP clients through "Apps" transformers to `array<string, mixed>`. This makes it impossible to:
   - Support different output formats (web, AMP, RSS)
   - Test aggregation independently from presentation
   - Reuse aggregated data for different consumers

3. **Duplicated logic** between insertedNews (lines 124-161) and recommendedEditorials (lines 163-207) - nearly identical aggregation patterns with no shared abstraction.

4. **Silent error handling** (`catch (\Throwable) { continue; }`) provides no observability into service failures and no explicit degradation policy.

5. **No cache strategy** for external service calls - every request hits all 7+ microservices directly.

### Who Benefits

- **API consumers**: Faster responses (cache), graceful degradation (resilience), same API contract
- **Development team**: Smaller classes, clearer responsibilities, testable layers, extensible architecture
- **Operations**: Observability of service failures, configurable degradation, reactive cache invalidation

### Constraints

**Technical**:
- PHP 8.1+ / Symfony 6.4 (no framework changes)
- API response format MUST remain identical (backward compatible)
- External client libraries (vendor/ec/*) MUST NOT be modified
- Existing 30+ DataTransformer classes reused without modification
- PHPStan Level 9 must pass
- Mutation testing MSI ≥ 79%

**Business**:
- Zero downtime deployment
- No API contract changes visible to mobile apps
- All microservices owned by same team (simplifies AMQP event design)

**Architecture** (from openspec mandatory constraints):
- Aggregators NEVER import from DataTransformer/ (APR-001)
- Presenters NEVER call external services (APR-002)
- DTOs MUST be `final readonly class` (APR-003)
- New formats added by implementing EditorialPresenterInterface only (APR-004)
- CRITICAL services fail the request; LOW services degrade gracefully (RES-001, RES-002)
- All external HTTP calls through httplug cache (CACHE-001)
- Cache invalidation event-driven via AMQP (CACHE-003)

### Success Criteria

1. **SC-01**: All existing tests pass (`make test_unit`, `make test_stan`, `make test_infection`)
2. **SC-02**: API response format is byte-identical for the same input data
3. **SC-03**: EditorialOrchestrator ≤ 50 LOC, ≤ 4 constructor dependencies
4. **SC-04**: `ResolvedEditorial` is `final readonly class` with typed properties
5. **SC-05**: No imports of `DataTransformer/` in `src/Aggregator/`
6. **SC-06**: No imports of `Client/Http` in `src/Presenter/`
7. **SC-07**: Adding a new output format requires ONLY: new Presenter class + service tag
8. **SC-08**: CRITICAL service failure returns 503; LOW service failure returns degraded response with logged warning
9. **SC-09**: httplug cache configured for all external clients with stale-if-error
10. **SC-10**: AMQP handlers exist for editorial, section, multimedia, journalist, tag events
11. **SC-11**: Aggregator-Presenter pattern documented as mandatory product standard in openspec

---

## Scope Summary

| Dimension | Count |
|-----------|-------|
| New PHP classes | ~20 |
| Modified PHP classes | 2 (EditorialOrchestrator, EditorialOrchestratorTest) |
| New config files | ~4 (aggregator.yaml, presenters.yaml, httplug.yaml, messenger routing) |
| New test classes | ~12 |
| Slices | 8 (independently deliverable) |
| Existing code unchanged | 30+ DataTransformer classes, all Controllers, all external clients |

---

## Source Evidence

- `openspec/changes/api-refactoring/01_shaped_brief.md` (routing + shaping output)
- `openspec/specs/architectural-constraints/aggregator-presenter-pattern.yaml`
- `openspec/specs/architectural-constraints/resilience-strategy.yaml`
- `openspec/specs/architectural-constraints/cache-strategy.yaml`
- `openspec/specs/business-rules/editorial-aggregation.yaml`
- `snaapi-develop/src/Orchestrator/Chain/EditorialOrchestrator.php` (current implementation)
