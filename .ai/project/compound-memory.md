# Agent Compound Memory

> Updated: 2026-02-16 (after api-refactoring feature)

## Historical Patterns

| Pattern | Reliability | Source Features | Description |
|---------|-------------|-----------------|-------------|
| Aggregator-Presenter separation | 1/1 | api-refactoring | Two-layer architecture: Aggregator fetches → DTO → Presenter formats |
| ResolvedSignature DTO | 1/1 | api-refactoring | Carry raw domain objects across boundaries; format in Presenter |
| ServiceCriticality degradation | 1/1 | api-refactoring | CRITICAL=fail request, LOW=degrade+log |
| Compiler pass + tagged services | 1/1 | api-refactoring | Extensibility via DI tags, no code changes for new formats |
| AMQP one-handler-per-event | 1/1 | api-refactoring | Separate handler class per message type (SRP) |
| buildResolveData() bridge | 1/1 | api-refactoring | Bridge method in Presenter for legacy transformer compatibility |

## Known Pain Points

| Pain Point | Frequency | Severity | Prevention |
|------------|-----------|----------|------------|
| Cross-layer DataTransformer import | 1/1 | high | Aggregators NEVER import Application/DataTransformer classes. Add grep-based CI check. |
| God class with 19+ constructor deps | 1/1 | high | Apply Aggregator-Presenter pattern early. Max 7 constructor deps. |
| Pre-formatted data in DTOs | 1/1 | medium | DTOs carry raw domain objects only; formatting in Presenter layer |
| Legacy array format bridge | 1/1 | medium | When wrapping legacy code, create explicit buildResolveData() bridge methods |

## Agent Calibration

| Agent Role | Intensity | Notes |
|------------|-----------|-------|
| Planner | default | Planning phases completed smoothly |
| Implementer | default | 19/19 tasks completed in 1 session |
| Reviewer | HIGH | Caught cross-layer violation that implementer missed. Review is critical. |
| Compounder | default | First compound capture for this project |

## Project-Specific Rules

1. **Layer separation is MANDATORY**: `src/Aggregator/` must NOT import from `src/Application/DataTransformer/` or `src/Presenter/`
2. **Presenters must NOT import external HTTP clients**: `src/Presenter/` must NOT import from `Ec\*\Infrastructure\Client\Http\`
3. **DTOs carry raw domain objects**: Never pre-format data in Aggregator DTOs
4. **New content format = 1 class + 1 tag**: No modifications to existing code required
5. **CRITICAL service failure = 503**: Only editorial-client is CRITICAL. All others are LOW.
