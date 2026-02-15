# Architecture Reference

> Este es un documento de **REFERENCIA** que el agente consulta para principios, patrones y criterios de calidad arquitectónica.
> Para análisis específico del proyecto, ver `openspec/specs/architecture-profile.yaml`.
> Para verificación automatizada, usar `/workflow-skill:solid-analyzer`.

---

## Principio Fundamental

**Cambio Fácil = Cambio Localizado**

Una arquitectura es buena cuando:
- Un cambio simple toca **pocos archivos** (≤3 para cambios triviales)
- Un cambio no genera **efectos cascada** inesperados
- Los patrones de diseño **reducen** la complejidad, no la aumentan

---

## SOLID Principles Reference

### S - Single Responsibility Principle

> "Una clase debe tener una, y solo una, razón para cambiar"

#### Indicadores

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Líneas por clase/módulo | ≤200 | 201-400 | >400 |
| Métodos públicos por clase | ≤7 | 8-12 | >12 |
| Dependencias en constructor | ≤7 | 8-10 | >10 |
| Razones para cambiar | 1 | 2 | >2 |

**Test Rápido**: "¿Puedo describir esta clase en UNA frase sin usar 'y'?"
- ✅ "Gestiona la persistencia de usuarios"
- ❌ "Gestiona usuarios y envía emails y valida permisos"

#### Violations and Corrective Patterns

**Violation: God Class**

Symptoms:
- Clase con >200 líneas, >7 métodos públicos, >10 dependencias
- Múltiples `#region` o secciones comentadas
- Nombre genérico: `Manager`, `Handler`, `Processor`, `Service`

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Strategy** | Múltiples algoritmos/comportamientos | `PaymentStrategy` → `CreditCardPayment`, `PayPalPayment` |
| **Extract Class** | Responsabilidades claramente separables | `UserService` → `UserValidator`, `UserNotifier` |
| **Facade** | Simplificar interfaz compleja | `OrderFacade` coordina `Inventory`, `Payment`, `Shipping` |
| **Mediator** | Reducir comunicación N-to-N | `ChatMediator` entre `User` objects |

Decision Tree:
```
¿Tiene múltiples algoritmos intercambiables?
  → SÍ: Strategy
  → NO: ¿Las responsabilidades son independientes?
    → SÍ: Extract Class
    → NO: ¿Coordina múltiples subsistemas?
      → SÍ: Facade
      → NO: ¿Hay comunicación N-to-N entre objetos?
        → SÍ: Mediator
        → NO: Extract Class con composición
```

**Violation: Mixed Concerns**

Symptoms:
- Lógica de negocio mezclada con acceso a datos
- HTTP handling en domain logic
- Logging/caching inline en métodos de negocio

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Repository** | Separar persistencia de dominio | `UserRepository` abstrae DB access |
| **Service Layer** | Coordinar casos de uso | `OrderService` usa `Repository` + `PaymentGateway` |
| **Decorator** | Cross-cutting concerns (logging, cache) | `CachedRepository` wraps `Repository` |

---

### O - Open/Closed Principle

> "Abierto para extensión, cerrado para modificación"

#### Indicadores

| Indicador | Bueno | Malo |
|-----------|-------|------|
| Añadir comportamiento | Crear nueva clase | Modificar clase existente |
| Switch/if-else por tipo | 0 | >0 (usar polimorfismo) |
| instanceof chains | 0 | >0 (usar Strategy) |

**Test Rápido**: "¿Puedo añadir un nuevo tipo de X sin modificar código existente?"

#### Violations and Corrective Patterns

**Violation: Type Switching**

Symptoms:
- `switch(type)` o `if-else` chains por tipo
- Añadir nuevo tipo requiere modificar código existente
- Métodos con múltiples `instanceof` checks

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Strategy** | Algoritmos intercambiables en runtime | `DiscountStrategy` → `PercentDiscount`, `FixedDiscount` |
| **Factory Method** | Creación de objetos por tipo | `NotificationFactory.create(type)` |
| **Chain of Responsibility** | Procesar por múltiples handlers | `ValidationChain` para validadores |
| **Visitor** | Operaciones sobre jerarquía de objetos | `ReportVisitor` genera diferentes reportes |

Decision Tree:
```
¿El comportamiento varía en runtime?
  → SÍ: Strategy
  → NO: ¿Es sobre creación de objetos?
    → SÍ: Factory Method / Abstract Factory
    → NO: ¿Múltiples procesadores en secuencia?
      → SÍ: Chain of Responsibility
      → NO: ¿Operaciones sobre jerarquía de tipos?
        → SÍ: Visitor
        → NO: Strategy (default)
```

**Violation: Modification for Extension**

Symptoms:
- Añadir feature = modificar clase existente
- Métodos que crecen con cada nuevo requisito
- Flags booleanos para habilitar comportamientos

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Decorator** | Añadir comportamiento sin modificar | `LoggingService` wraps `Service` |
| **Template Method** | Algoritmo fijo con pasos variables | `DataImporter` con `parse()` abstracto |
| **Plugin/Extension** | Funcionalidad pluggable | `PluginManager` carga extensiones |

---

### L - Liskov Substitution Principle

> "Los subtipos deben ser sustituibles por sus tipos base"

#### Indicadores

| Indicador | Bueno | Malo |
|-----------|-------|------|
| Subclase reemplaza a padre | Sin sorpresas | Comportamiento diferente |
| Override de métodos | Mantiene contrato | Rompe expectativas |
| Excepciones en override | Mismas que padre | Nuevas excepciones |

**Test Rápido**: "¿Puedo usar cualquier implementación donde se espera la interfaz?"

#### Violations and Corrective Patterns

**Violation: Contract Breaking Subtypes**

Symptoms:
- Override que cambia comportamiento esperado
- Subtipo que lanza excepciones no esperadas
- Precondiciones más estrictas en subtipo
- Postcondiciones más débiles en subtipo

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Composition over Inheritance** | Evitar jerarquías problemáticas | `Car` HAS-A `Engine` vs IS-A `Vehicle` |
| **Adapter** | Adaptar interfaz incompatible | `LegacyPaymentAdapter` implements `PaymentInterface` |
| **Null Object** | Evitar null checks y excepciones | `NullLogger` implements `Logger` (no-op) |
| **Interface Extraction** | Definir contrato explícito | Extract `Readable` from `File` class |

Decision Tree:
```
¿El subtipo viola el contrato del padre?
  → SÍ: ¿Es por incompatibilidad fundamental?
    → SÍ: Composition over Inheritance
    → NO: ¿Es por sistema legacy?
      → SÍ: Adapter
      → NO: Interface Extraction
  → NO: ¿El problema es con null/ausencia?
    → SÍ: Null Object / Optional
    → NO: Revisar precondiciones/postcondiciones
```

---

### I - Interface Segregation Principle

> "Los clientes no deben depender de interfaces que no usan"

#### Indicadores

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Métodos por interfaz | ≤5 | 6-8 | >8 |
| Implementaciones que usan todo | 100% | >80% | <80% |
| Métodos vacíos/NotImplemented | 0 | 0 | >0 |

**Test Rápido**: "¿Alguna implementación tiene métodos vacíos o `throw NotImplemented`?"

#### Violations and Corrective Patterns

**Violation: Fat Interface**

Symptoms:
- Interface con >5 métodos
- Implementaciones con métodos vacíos o `throw NotImplemented`
- Clientes que solo usan 2-3 métodos de una interface grande

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Role Interfaces** | Dividir por responsabilidad | `Readable`, `Writable`, `Seekable` |
| **Adapter** | Implementar solo lo necesario | `ReadOnlyFileAdapter` only implements `Readable` |
| **Facade** | Simplificar interface compleja | `SimplePrinter` wraps complex `Printer` |

Segregation Guidelines:
```
Interface original → Interfaces segregadas

IRepository<T>           →  IReader<T>
  - getById()                 - getById()
  - getAll()                  - getAll()
  - save()                    - findBy()
  - delete()               IWriter<T>
  - findBy()                  - save()
  - count()                   - delete()
                           ICounter<T>
                              - count()
```

---

### D - Dependency Inversion Principle

> "Depender de abstracciones, no de concreciones"

#### Indicadores

| Indicador | Bueno | Malo |
|-----------|-------|------|
| Dependencias en Domain | Solo abstracciones | Clases concretas de infra |
| Constructores | Reciben interfaces | Instancian dependencias |
| `new ConcreteClass()` en Domain | 0 | >0 |
| Static calls en Domain | 0 | >0 |

**Test Rápido**: "¿El Domain layer importa algo de Infrastructure?"

#### Violations and Corrective Patterns

**Violation: Concrete Dependencies**

Symptoms:
- `new ConcreteClass()` dentro de métodos
- Import de clases de Infrastructure en Domain
- Hardcoded paths, URLs, connection strings

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Dependency Injection** | Inyectar dependencias | Constructor injection via interface |
| **Abstract Factory** | Crear familias de objetos | `UIFactory` → `WindowsFactory`, `MacFactory` |
| **Ports & Adapters** | Aislar dominio de infraestructura | Domain Port + Infrastructure Adapter |

Architecture Pattern: Ports & Adapters (Hexagonal)
```
┌─────────────────────────────────────────────────────────┐
│                    APPLICATION CORE                      │
│  ┌─────────────────────────────────────────────────┐   │
│  │                   DOMAIN                         │   │
│  │  - Entities                                      │   │
│  │  - Value Objects                                 │   │
│  │  - Domain Services                               │   │
│  │  - Domain Events                                 │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────┐   │
│  │              PORTS (Interfaces)                  │   │
│  │  - RepositoryInterface                           │   │
│  │  - GatewayInterface                              │   │
│  │  - NotifierInterface                             │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                          │
         ┌────────────────┼────────────────┐
         ▼                ▼                ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  ADAPTER    │  │  ADAPTER    │  │  ADAPTER    │
│  (HTTP)     │  │  (Database) │  │  (Queue)    │
│             │  │             │  │             │
│ Controller  │  │ Repository  │  │ Publisher   │
│ Middleware  │  │ ORM Config  │  │ Consumer    │
└─────────────┘  └─────────────┘  └─────────────┘
```

---

## Paradigm Adaptation

Los principios SOLID aplican de forma diferente según el paradigma del proyecto. Consultar `openspec/specs/architecture-profile.yaml` para saber qué paradigma usa el proyecto.

### OOP (PHP, Java, C#)
- Todos los principios aplican directamente
- Interfaces explícitas → ISP es altamente relevante
- Herencia disponible → LSP es relevante
- Clases como unidad base → SRP se mide por clase

### Functional (Haskell, Elm, Clojure, JS funcional)
- **SRP**: Se mide por función/módulo, no por clase. Funciones puras con un propósito.
- **OCP**: Composición de funciones. Higher-order functions permiten extensión sin modificación.
- **LSP**: Aplica a interfaces de módulos y contratos de tipos (si hay type system).
- **ISP**: Se manifiesta en módulos que exportan solo lo necesario. APIs mínimas.
- **DIP**: Inversión via higher-order functions (inyectar funciones como dependencias).

### Mixed (TypeScript, Python, Kotlin, Scala)
- Adaptar según lo que el proyecto usa: si usa clases → OOP rules. Si usa funciones → functional rules.
- **SRP**: Aplicar tanto a clases como a módulos/funciones según el caso.
- **DIP**: Constructor injection para clases, function injection para módulos funcionales.
- **ISP**: TypeScript interfaces + module exports. Python protocols + `__all__`.

### Struct-based (Go, Rust)
- **ISP**: Go tiene interfaces implícitas → ISP se consigue naturalmente con interfaces pequeñas.
- **LSP**: No hay herencia clásica. Aplica a trait implementations (Rust) o interface satisfaction (Go).
- **DIP**: Via interfaces en Go, traits en Rust. Package boundaries como separación.
- **SRP**: Se mide por struct + sus métodos, o por package.

---

## Pattern Selection: Project-Based Approach

Cuando el agente necesita seleccionar un patrón para resolver una violación SOLID:

```
1. CONSULTAR el architecture-profile.yaml del proyecto
   └─ ¿Qué patrones ya usa el proyecto? (patterns_detected, learned_patterns)
   └─ ¿Hay reference_files que muestren cómo se resuelve este tipo de problema?

2. IDENTIFICAR la violación SOLID específica
   └─ Usar las tablas de "Symptoms" de este documento
   └─ Verificar qué principio(s) se violan

3. SELECCIONAR el patrón MÁS SIMPLE que:
   a. El proyecto ya use (preferir consistencia)
   b. Resuelva la violación identificada
   c. No introduzca complejidad innecesaria

4. Si el proyecto no tiene un patrón establecido:
   └─ Usar los Decision Trees de este documento
   └─ Elegir el patrón más simple de la tabla de Corrective Patterns

5. DOCUMENTAR la decisión
   └─ Qué principio se violaba
   └─ Qué patrón se eligió y por qué
   └─ Reference file del proyecto que ejemplifica el patrón (si existe)
```

---

## Architecture Quality Criteria

### C-BASE-01: Escalabilidad Estructural

La arquitectura permite crecer sin reescribir.

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Añadir nueva entidad | ≤5 archivos | 6-10 archivos | >10 archivos |
| Añadir nuevo endpoint | ≤4 archivos | 5-7 archivos | >7 archivos |
| Añadir nuevo campo a entidad | ≤3 archivos | 4-5 archivos | >5 archivos |

Anti-patrones a Evitar:
- God classes que requieren modificación para todo
- Configuraciones centralizadas que se tocan siempre
- Herencia profunda que propaga cambios

### C-BASE-03: Clean Code Metrics

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Líneas por función | ≤20 | 21-40 | >40 |
| Parámetros por función | ≤3 | 4-5 | >5 |
| Niveles de indentación | ≤3 | 4 | >4 |
| Complejidad ciclomática | ≤10 | 11-15 | >15 |
| Nombres descriptivos | Sí (sin comentarios) | Con comentarios | Abreviaciones crípticas |

### C-BASE-04: Responsabilidades Definidas (Separation of Concerns)

Cada capa/módulo tiene una responsabilidad clara y única.

Estructura DDD Esperada:
```
src/
├── Domain/           # Reglas de negocio PURAS (sin dependencias externas)
│   ├── Entity/       # Entidades con identidad
│   ├── ValueObject/  # Objetos inmutables sin identidad
│   ├── Repository/   # Interfaces (NO implementaciones)
│   ├── Service/      # Lógica de dominio que no cabe en entidades
│   └── Event/        # Eventos de dominio
│
├── Application/      # Orquestación de casos de uso
│   ├── Command/      # Acciones que modifican estado
│   ├── Query/        # Acciones de lectura
│   ├── DTO/          # Objetos de transferencia
│   └── Service/      # Coordinación entre dominio e infra
│
└── Infrastructure/   # Detalles técnicos
    ├── Persistence/  # Implementaciones de repositorios
    ├── Controller/   # Entry points HTTP (thin)
    ├── External/     # APIs externas, servicios third-party
    └── Config/       # Configuración técnica
```

Reglas de Dependencia:
```
Domain ← Application ← Infrastructure

Domain NO conoce Application ni Infrastructure
Application NO conoce Infrastructure (usa interfaces)
Infrastructure conoce todo (implementa interfaces)
```

### C-BASE-05: Patrones de Diseño Adecuados

Criterio de Selección de Patrón:
```
¿Necesito este patrón?
    │
    ├─ ¿Resuelve un problema REAL que tengo AHORA?
    │      NO → No usar (YAGNI)
    │      SÍ ↓
    │
    ├─ ¿La solución sin patrón requiere >3 archivos modificados para cambios?
    │      NO → Solución simple es suficiente
    │      SÍ ↓
    │
    └─ ¿El equipo conoce este patrón?
           NO → Documentar o elegir alternativa conocida
           SÍ → Usar el patrón
```

### C-BASE-06: Invasividad de Cambios

| Tipo de Cambio | Archivos Máximos | Capas Afectadas |
|----------------|------------------|-----------------|
| Nuevo campo en entidad | 3 | Domain, DTO, Migration |
| Nueva validación de negocio | 2 | Domain (Entity o VO) |
| Nuevo endpoint CRUD | 4 | Controller, UseCase, DTO, Route |
| Cambio en UI de un campo | 1 | Component |
| Nueva regla de autorización | 2 | Policy/Guard, Config |
| Cambio de proveedor externo | 1 | Infrastructure adapter |

---

## Anti-Patterns to Avoid

| Anti-Pattern | SOLID Violations | Better Alternative |
|--------------|------------------|-------------------|
| **God Class** | S, O, I | Extract Class + Strategy |
| **Service Locator** | D, I | Dependency Injection |
| **Singleton** | S, O, D | DI with scoped lifetime |
| **Anemic Domain** | S | Rich Domain Model |
| **Circular Dependencies** | D, S | Mediator, Events |
| **Primitive Obsession** | S, O | Value Objects |
| **Feature Envy** | S | Move Method |
| **Shotgun Surgery** | S, O | Extract Class |

---

## Excepciones Documentadas

Hay casos donde romper un criterio es aceptable:

| Excepción | Cuándo es OK | Debe Documentar |
|-----------|--------------|-----------------|
| Clase >200 líneas | Entidad de dominio compleja con muchas reglas | Justificación en ADR |
| >3 parámetros | DTO de request con muchos campos | N/A (DTOs son contenedores) |
| Cambio toca >5 archivos | Refactor planificado | Scope en PR description |
| Patrón "innecesario" | Preparación para feature conocida en roadmap | Link a roadmap item |

Formato de Excepción:
```markdown
## Exception: [Criterio violado]
**Razón**: [Por qué es necesario]
**Mitigación**: [Cómo se compensa]
**Revisión**: [Cuándo reconsiderar]
```

---

## Quick Reference: Violation → Pattern

| Violación SOLID | Síntoma | Patrón Correctivo | Alternativa |
|-----------------|---------|-------------------|-------------|
| **SRP** God Class | Clase >200 líneas, >7 métodos | **Strategy** + Extract Class | Facade + Services |
| **SRP** Mixed Concerns | Lógica de negocio + I/O juntos | **Repository** + Service Layer | Ports & Adapters |
| **OCP** Switch/If-else por tipo | `if (type === 'A')` chains | **Strategy** | Factory Method |
| **OCP** Modificar para extender | Añadir feature = cambiar clase | **Decorator** | Template Method |
| **LSP** Subtipo incompatible | Override que cambia contrato | **Composition over Inheritance** | Adapter |
| **LSP** Excepciones inesperadas | Subtipo lanza excepciones nuevas | **Null Object** | Optional/Maybe |
| **ISP** Interface gorda | Interface >5 métodos | **Interface Segregation** | Role Interfaces |
| **ISP** Métodos no usados | `throw NotImplemented` | **Adapter** | Facade |
| **DIP** Dependencia concreta | `new ConcreteClass()` directo | **Dependency Injection** | Abstract Factory |
| **DIP** Alto→Bajo nivel | Domain importa Infrastructure | **Ports & Adapters** | Gateway Pattern |

---

## SOLID Verdict Matrix

When evaluating SOLID compliance (in planning Phase 3, work checkpoints, and review), use this verdict protocol instead of numeric scores:

### Verdict Values

| Verdict | Meaning | Action |
|---------|---------|--------|
| **COMPLIANT** | Principle is satisfied with evidence | Proceed |
| **NEEDS_WORK** | Partially satisfied, specific improvement identified | Refactor before checkpoint |
| **NON_COMPLIANT** | Principle violated, no justification | BLOCKS advancement |
| **N/A** | Principle not relevant to this context (with justification) | Proceed |

### SOLID Justification Format

For each relevant principle, provide a one-line justification with code evidence:

```
SRP: COMPLIANT — UserEntity handles only user data. Evidence: src/Domain/Entity/User.php (no service logic)
OCP: N/A — No extension point needed for this entity (standalone value holder)
LSP: N/A — No inheritance hierarchy involved
ISP: COMPLIANT — UserRepositoryInterface has only CRUD methods. Evidence: 4 methods, all used by consumers
DIP: COMPLIANT — Domain depends on interface, not Doctrine. Evidence: UserRepositoryInterface in Domain/
```

### Gate Rules

- **Planning (Phase 3)**: All relevant principles must be COMPLIANT or N/A (with justification) to pass Quality Gate
- **Work (checkpoint)**: NON_COMPLIANT blocks the checkpoint. NEEDS_WORK triggers refactor before proceeding
- **Review**: Reviewer verifies justifications match actual code

---

## Related Documents

- `openspec/specs/architecture-profile.yaml` - Perfil arquitectónico del proyecto (generado por discover)
- `core/templates/architecture-profile-template.yaml` - Template para el perfil
- `skills/workflow-skill-solid-analyzer.md` - Análisis automático de SOLID (3 modos: BASELINE, DESIGN_VALIDATE, CODE_VERIFY)
- `agents/review/architecture-reviewer.md` - Validación de arquitecturas

---

**Versión**: 2.0
**Última Actualización**: 2026-02-15
**Origen**: Fusión de `solid-pattern-matrix.md` + `architecture-quality-criteria.md`
**Aplicable a**: Todos los proyectos que usen el plugin
