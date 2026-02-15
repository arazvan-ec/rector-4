# Tasks: SNAAPI API Refactoring - Two-Layer Architecture

> Feature: api-refactoring
> Created: 2026-02-15
> Updated: 2026-02-15 (Aggregator-Presenter separation)

## Workflow State

| Phase | Status | Updated |
|-------|--------|---------|
| Routing | COMPLETED | 2026-02-15 |
| Shaping | COMPLETED | 2026-02-15 |
| Planning | PENDING | - |
| Work | PENDING | - |
| Review | PENDING | - |
| Compound | PENDING | - |

## Architectural Goal

```
BEFORE: Controller → EditorialOrchestrator (536 LOC, mixed concerns) → array
AFTER:  Controller → Aggregator → ResolvedEditorial → Presenter(format) → array
```

## Slices

### Slice 1: ResolvedEditorial DTO + SignatureResolver
- [ ] Create `src/Aggregator/DTO/ResolvedEditorial.php` (final readonly class)
- [ ] Create `src/Aggregator/DTO/ResolvedInsertedNews.php` (final readonly class)
- [ ] Create `src/Aggregator/DTO/ResolvedRecommendedEditorial.php` (final readonly class)
- [ ] Create `src/Aggregator/Service/SignatureResolver.php`
- [ ] Create unit tests for ResolvedEditorial DTO
- [ ] Create unit tests for SignatureResolver
- [ ] Verify: No imports from DataTransformer/ in Aggregator/

### Slice 2: EditorialAggregator + sub-services
- [ ] Create `src/Aggregator/EditorialAggregatorInterface.php`
- [ ] Create `src/Aggregator/EditorialAggregator.php`
- [ ] Create `src/Aggregator/Service/InsertedNewsResolver.php`
- [ ] Create `src/Aggregator/Service/RecommendedEditorialsResolver.php`
- [ ] Create `src/Aggregator/Service/MultimediaResolver.php`
- [ ] Create unit tests for EditorialAggregator
- [ ] Create unit tests for InsertedNewsResolver
- [ ] Create unit tests for RecommendedEditorialsResolver
- [ ] Create unit tests for MultimediaResolver
- [ ] Verify: Aggregator returns ResolvedEditorial, not array

### Slice 3: EditorialPresenter + AppsEditorialPresenter
- [ ] Create `src/Presenter/EditorialPresenterInterface.php`
- [ ] Create `src/Presenter/EditorialPresenterRegistry.php`
- [ ] Create `src/Presenter/Apps/AppsEditorialPresenter.php`
- [ ] Create `src/DependencyInjection/Compiler/EditorialPresenterCompiler.php`
- [ ] Create `config/packages/presenters.yaml`
- [ ] Create unit tests for EditorialPresenterRegistry
- [ ] Create unit tests for AppsEditorialPresenter
- [ ] Verify: AppsPresenter output matches current EditorialOrchestrator output exactly
- [ ] Verify: No imports from Client/Http in Presenter/

### Slice 4: Integrate and simplify EditorialOrchestrator
- [ ] Create `config/packages/aggregator.yaml`
- [ ] Modify `EditorialOrchestrator` to delegate to aggregator + presenter
- [ ] Verify EditorialOrchestrator ≤ 50 LOC
- [ ] Verify constructor deps ≤ 4
- [ ] Update `EditorialOrchestratorTest.php`
- [ ] Run full test suite: `make test_unit`
- [ ] Run PHPStan: `make test_stan`
- [ ] Verify API response backward compatibility

### Slice 5: Document as product standard
- [ ] Create `openspec/specs/architectural-constraints/aggregator-presenter-pattern.yaml`
- [ ] Update `openspec/specs/architecture-profile.yaml` with two-layer pattern
- [ ] Update `snaapi-develop/CLAUDE.md` with new architecture
- [ ] Update `.ai/project/intelligence/project-profile.md`

## Success Criteria

- [ ] All existing tests pass (make test_unit)
- [ ] PHPStan Level 9 passes (make test_stan)
- [ ] EditorialOrchestrator ≤ 50 LOC, ≤ 4 constructor deps
- [ ] API response format unchanged (backward compatible)
- [ ] ResolvedEditorial is `final readonly class`
- [ ] No imports of DataTransformer/ in Aggregator/
- [ ] No imports of Client/Http in Presenter/
- [ ] Aggregator-Presenter pattern documented as mandatory in openspec/
- [ ] Adding new format requires ONLY: new class + tag (no existing code changes)
