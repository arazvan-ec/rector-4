# Tasks: SNAAPI API Refactoring

> Feature: api-refactoring
> Created: 2026-02-15

## Workflow State

| Phase | Status | Updated |
|-------|--------|---------|
| Routing | COMPLETED | 2026-02-15 |
| Shaping | COMPLETED | 2026-02-15 |
| Planning | PENDING | - |
| Work | PENDING | - |
| Review | PENDING | - |
| Compound | PENDING | - |

## Slices

### Slice 1: Extract SignatureResolver + EditorialResolutionData
- [ ] Create `src/Orchestrator/DTO/EditorialResolutionData.php`
- [ ] Create `src/Orchestrator/Service/SignatureResolver.php`
- [ ] Update `EditorialOrchestrator` to use new classes
- [ ] Create unit tests for SignatureResolver
- [ ] Create unit tests for EditorialResolutionData
- [ ] Update EditorialOrchestratorTest
- [ ] Run full test suite

### Slice 2: Extract InsertedNewsResolver
- [ ] Create `src/Orchestrator/Service/InsertedNewsResolver.php`
- [ ] Move insertedNews logic from EditorialOrchestrator
- [ ] Create unit tests for InsertedNewsResolver
- [ ] Update EditorialOrchestratorTest
- [ ] Run full test suite

### Slice 3: Extract RecommendedEditorialsResolver + MultimediaResolver
- [ ] Create `src/Orchestrator/Service/RecommendedEditorialsResolver.php`
- [ ] Create `src/Orchestrator/Service/MultimediaResolver.php`
- [ ] Move recommended + multimedia logic from EditorialOrchestrator
- [ ] Create unit tests for both services
- [ ] Update EditorialOrchestratorTest
- [ ] Run full test suite

### Slice 4: Simplify EditorialOrchestrator + ErrorHandlingStrategy
- [ ] Create error handling strategy interface
- [ ] Refactor EditorialOrchestrator as thin coordinator
- [ ] Verify constructor deps ≤ 7
- [ ] Update all tests
- [ ] Run full test suite + PHPStan

### Slice 5: Flatten Transformer Hierarchy (Optional)
- [ ] Refactor body transformer inheritance to composition
- [ ] Update affected tests
- [ ] Run full test suite

## Success Criteria

- [ ] All existing tests pass (make test_unit)
- [ ] PHPStan Level 9 passes (make test_stan)
- [ ] EditorialOrchestrator ≤ 200 LOC
- [ ] No class has > 7 constructor dependencies
- [ ] API response format unchanged (backward compatible)
- [ ] Mutation testing threshold maintained (79% MSI)
