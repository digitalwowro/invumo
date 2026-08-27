# Invumo Codebase Map

Status: Approved implementation contract

Last updated: 2026-08-27

This is the living map of Invumo's code ownership and dependency boundaries. It explains where new code belongs without duplicating the product specification or the development tracker.

The map is intentionally Invumo-specific. It follows this application's quotation, invoicing, tenancy, delivery, and financial boundaries; it is not a template imported from another application. Create a directory only when it contains real code. Empty module scaffolds, a generic module framework, and one service provider per module are not required.

## 1. Structural principles

- Invumo is one Laravel modular monolith, one React/Inertia interface, one repository, and one deployment.
- Organize business behavior by feature/domain, not by an ever-growing global collection of controllers, models, or helpers.
- Keep Laravel framework entry points conventional when that is clearer. Authentication bootstrap, global middleware, providers, routes, and migrations do not need artificial module wrappers.
- A module owns its write workflows, policies, validation, persistence models, queries, and tests.
- Reuse crosses an explicit boundary. Do not reach into another module's controllers, requests, policies, rules, jobs, or internal support code.
- Refactor structure when the approved change needs it. Do not reorganize unrelated code opportunistically.

## 2. Backend map

```text
app/
├── Modules/          # Business modules, created only as features are implemented
├── Foundation/       # Small stable technical/domain foundations shared by modules
├── Integrations/     # Concrete external-provider and renderer adapters
├── Http/             # Global Laravel HTTP entry points and middleware
├── Actions/Fortify/  # Conventional starter-kit authentication actions
├── Models/User.php   # Framework identity model until a real module move is useful
├── Providers/        # Laravel bootstrapping and adapter bindings
└── Support/          # Narrow application-wide framework support
```

### 2.1 Business modules

The approved module catalog is:

| Module         | Owns                                                                                                                    |
| -------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `Identity`     | Account-level identity and entitlement behavior beyond Fortify's framework authentication entry points                  |
| `Companies`    | Companies, memberships, invitations, ownership, company switching, configuration, and private Company assets            |
| `Customers`    | Customers, contacts, delivery recipients, defaults, and customer search                                                 |
| `Catalog`      | Products and services, catalog defaults, search, and archive behavior                                                   |
| `Documents`    | Shared document lines, snapshots, numbering, calculation orchestration, and common document behavior                    |
| `Quotes`       | Quote lifecycle, acceptance/rejection/expiry, conversion eligibility, and quote-specific workflows                      |
| `Invoices`     | Invoice lifecycle, balance/state coordination, cancellation/reopening, overdue behavior, and invoice-specific workflows |
| `Transactions` | Payments, refunds, adjustments, receipts, cash bounds, and transaction correction history                               |
| `Recurring`    | Recurring templates, occurrence generation, schedule resolution, and automation controls                                |
| `Delivery`     | Public document access, PDF composition, email/reminder workflows, delivery history, and provider-event normalization   |
| `Audit`        | Append-only audit events and authorized operational/audit queries                                                       |
| `Platform`     | Operator authorization, control-plane administration, Account lifecycle/suspension, full User impersonation, and audit  |

This catalog describes ownership, not deployment. A cross-cutting workflow still runs inside one database transaction and one application. Split or merge a module only through an approved architecture change backed by actual code pressure.

A module may use only the subdirectories it needs:

```text
app/Modules/Invoices/
├── Actions/          # Named write use cases and workflow transaction owners
├── Contracts/        # Interfaces intentionally exposed across a boundary
├── Data/             # Typed input/result/snapshot data objects
├── Http/
│   ├── Controllers/  # Thin Inertia/HTTP adapters
│   └── Requests/     # Input shape and validation
├── Models/           # Eloquent persistence owned by the module
├── Policies/         # Authorization decisions
├── Queries/          # Read-only, purpose-specific page/report queries
└── Rules/            # Cohesive domain validation or calculation rules
```

Do not add `Services`, `Managers`, `Helpers`, or repositories as default buckets. Name code after the business operation or responsibility. Extract a new class when it creates a real boundary, not merely to reduce line count.

### 2.2 Foundation

`app/Foundation` contains only stable building blocks required by several modules:

- `Tenancy` — company context and restricted-role/RLS plumbing;
- `Auth` — request-session identity-transition context shared by Platform and audit boundaries;
- `Database` — UUIDv7 domain identifiers; the tested tenant-table migration, exact-decimal storage-envelope, same-Company foreign-key, forced-RLS, and restricted-grant contract; the production-backup boundary with isolated restore verification; and environment-configured, major-version-verified PostgreSQL client binaries;
- `Configuration` — production-readiness assertions used by the deploy/runtime gate and health diagnosis, identifying unsafe keys without disclosing their values;
- `Diagnostics` — database-aware health diagnosis and the bounded operational logging contract;
- `Http` — framework-independent shared HTTP response behavior such as the Inertia error surface;
- `Money` — exact-decimal and currency-precision primitives;
- `Jobs` — tenant-safe dispatch, idempotency, and shared execution context.

Foundation code must not depend on a business module or concrete integration. A utility used by only one module stays in that module.

### 2.3 Integrations

`app/Integrations` contains concrete adapters such as `ZeptoMail` and `Dompdf`. Integrations translate vendor or renderer behavior into Invumo-owned contracts and data. They do not own document state, business rules, or tenant authorization. `Delivery` owns the shared `OutwardDocument` data contract, exact display formatter, kind-aware current-representation Queries, PDF contract, Blade template, outward CSS, public-link credential lifecycle, and event/language Company email templates. Template resolution uses Laravel-authored fallbacks, exact event-specific placeholder allowlists, plain-text authored content, and a side-effect-free preview Query; Company overrides are changed only through named root Actions. Its public-document boundary uses encrypted recoverable tokens with hashed lookup, a transaction-local forced-RLS bootstrap, global pre-routing request-token redaction, and thin anonymous HTML/PDF controllers. `Quotes` and `Invoices` own their authenticated representation routes and thin link-management controllers, while their aggregate deletion/unlink Actions call or query the Delivery-owned lifecycle boundary. React consumes the same resolved data contract through `components/domain/outward-document*.tsx`, while the Blade template consumes it for PDF output. Source-owned PDF fonts live under `resources/fonts/atkinson-hyperlegible` with pinned origins and checksums.

A module declares the narrow contract it needs; a provider binds a concrete integration implementation. This keeps provider values out of domain workflows without surrounding normal internal Laravel code with unnecessary interfaces.

### 2.4 Public module boundaries

Another module may import only the owning module's:

- `Actions` for an explicitly named mutation/use case;
- `Queries` for an explicitly named read;
- `Contracts` for a stable boundary;
- `Data` for typed exchange values;
- `Models` where an Eloquent relationship or approved database query genuinely requires the persisted type.

Controllers, Requests, Policies, Rules, Jobs, and internal support code are never cross-module APIs. Cross-module model access must not become a shortcut for mutating another module's state; use its Action.

Dependency direction is:

```text
Laravel entry points / Inertia pages
                 ↓
          Business modules
            ↓          ↑ contracts/data only
       Foundation   Integrations
```

`Foundation` depends on neither modules nor integrations. Modules may depend on Foundation but not concrete integrations. Integrations may depend on Foundation and the module contracts/data they implement, but not on module Actions, Models, HTTP code, or Policies.

`Platform` may coordinate approved control-plane mutations through the owning `Identity` or `Companies` module Action when that domain owns the invariant. It never mutates another module's models as an authorization shortcut and never bypasses tenant context to inspect Company business data.

The persisted Quote and Invoice workspaces follow the same boundary. `Quotes` and `Invoices` own their root Draft mutations and editor/list Queries; `Invoices` also owns the named issue, cancel, reopen, and guarded deletion Actions plus typed lifecycle/payment/overdue derivation and the permission-aware cancellation workflow Query. `Transactions` owns Invoice financial-row CRUD, the complete exact-decimal ledger, stable aggregate locking, per-Invoice presentation, and the read-only Company operational-list Query; Invoice Actions and Queries consume its contracts to enforce zero-net-paid cancellation and derive current state without storing a balance cache. Quote-to-Invoice conversion is a `Quotes` coordinating root Action because Quote eligibility/provenance owns the workflow; it authorizes both document abilities, owns the outer transaction, and uses the transaction-neutral `Documents` snapshot copier plus number-allocation contract rather than calling another root Action. `Documents` owns shared configuration locking, aggregate persistence types, line reconciliation, the transaction-neutral automatic-number allocator contract, counter realignment Action, snapshot copying, and reusable line presentation. Root document Actions acquire Company configuration before counters and the Document aggregate through the shared lock boundary. Shared document-kind data maps Quote/Invoice routes to their specific view/manage abilities, including inline Customer/Product creation. The React editor controls live in `components/domain/documents`, so Quote and Invoice features compose the same selector, line, default, and inline-creation behavior without importing each other's feature internals. Recurring later reuses these shared boundaries rather than either document feature.

## 3. Write and read boundaries

### 3.1 Mutations

Each externally triggered mutation maps to one clearly named application Action, for example `IssueInvoice`, `RecordPayment`, or `ActivateRecurringTemplate`.

The root Action:

1. receives validated typed input and the authorized actor/company context;
2. rechecks business preconditions that cannot be trusted to HTTP validation;
3. owns one outer database transaction;
4. acquires the required row/advisory locks in a consistent order;
5. performs state changes through the owning modules;
6. records audit/outbox work in the same transaction when required;
7. returns a typed result rather than an HTTP response.

Controllers, console commands, scheduled tasks, and queued jobs adapt input/output and call the Action; they do not reimplement the workflow. Models do not send email, dispatch external requests, or open hidden transactions.

Avoid Action-to-Action chains that obscure the transaction owner. A multi-module use case has one coordinating root Action; reusable subordinate behavior is transaction-neutral and named for its rule or operation. External effects run after commit through the approved outbox/job path, never midway through the financial transaction.

### 3.2 Reads

Purpose-specific Query classes assemble list, detail, editor, and report data. Queries are read-only, company-scoped, permission-aware, and return only fields required by the consumer.

For an Inertia page, Laravel coordinates independent reads and returns one deliberate page-prop payload. Do not make React mount and fan out into several client requests for data the initial page already requires. Large optional regions may use an approved Inertia partial/deferred prop when there is measured value.

## 4. Frontend map

```text
resources/js/
├── pages/             # Thin Inertia route entries: compose and bind props/actions
├── features/          # Feature workflows and feature-local components/hooks
├── components/
│   ├── ui/            # Source-owned shadcn primitives
│   ├── app/           # Reusable app chrome and non-domain application patterns
│   ├── domain/        # Reusable Invumo domain presentation and behavior
│   └── design-system/ # Development/test gallery and contract verification
├── layouts/           # Authenticated/auth/settings shells
├── hooks/             # Truly application-wide React hooks
├── lib/               # Framework-neutral browser helpers
└── types/             # Shared transport and UI types
```

Frontend dependencies flow upward:

```text
ui → app → domain → features → pages/layouts
```

Higher layers may consume lower layers; lower layers must not import higher ones. `domain` may use both `app` and `ui`. Features must not import another feature's internal files. Promote genuinely shared domain behavior to `components/domain`, a shared type, or a server-owned contract instead of creating cross-feature coupling.

Use direct file imports; do not add barrel `index.ts` files for components or features. Generated Wayfinder route/action files are framework output and are not an architectural public-module pattern.

Pages remain small composition boundaries. A page does not own a bespoke table, status treatment, form pattern, modal style, or money formatter. The [Invumo Design System Contract](../design/design-system.md) remains authoritative for visual-layer responsibilities.

## 5. Test ownership

PHP tests mirror the code they protect:

```text
tests/
├── Feature/Modules/<Module>/...
├── Unit/Modules/<Module>/...
├── Feature/Foundation/...
└── Feature/Integrations/...
```

React/TypeScript tests stay beside the source when the behavior is local. Browser tests are organized by user journey, not by implementation class. Shared golden vectors remain in a neutral fixture location and are consumed by both authoritative PHP and preview TypeScript tests.

Every vertical slice updates the relevant validation, policy, Action/Query, UI composition, tests, audit behavior, and documentation together. Do not build every database layer first and postpone the usable workflow until the end of a phase.

## 6. Keeping this map current

Update this file in the same change when:

- a module or Foundation/Integration area is created, renamed, split, or removed;
- ownership of a business concept changes;
- a new cross-module public boundary is introduced;
- frontend layer ownership changes;
- the automated boundary rules need an approved exception.

Do not update it for every class or component. Its purpose is orientation and dependency ownership, not a generated file listing or a second development tracker.

The `modules:check` structural guard enforces the dependency directions it can verify statically. Passing the guard does not replace design review: ownership, transaction integrity, authorization, and side effects still require behavioral tests and review.
