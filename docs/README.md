# Invumo Documentation

## Product

- [`product/master-build-brief.md`](product/master-build-brief.md) — canonical v1 product scope and behavior brief
- [`product/domain-rules.md`](product/domain-rules.md) — concise, implementation-facing domain invariants

The master brief is the canonical statement of scope. The domain-rules document restates only invariants that implementation work must preserve. The decision log in the sibling memory repository records why decisions were made and may retain superseded history; it is not a second product specification. If these sources appear to conflict, reconcile them explicitly before implementation rather than choosing one silently.

## Development tracking

- [`development/development-tracker.md`](development/development-tracker.md) — the only canonical phase sequence, progress checklist, dependency map, acceptance-gate record, and implementation log
- [`development/testing-strategy.md`](development/testing-strategy.md) — approved proportionate local verification, cross-phase invariant regression review, and manual phase-only GitHub quality-gate contract
- [`development/visual-snapshot-baselines.md`](development/visual-snapshot-baselines.md) — required inspection evidence and hash-bound workflow for canonical GitHub visual-baseline updates
- [`development/design-qa.md`](development/design-qa.md) — inspected Invoice operational-list reference comparison and responsive design evidence

Update the tracker as work advances. Do not create or maintain phase status, task completion, or a competing implementation sequence in another document.

## Design system

- [`design/design-system.md`](design/design-system.md) — approved canonical visual, token, typography, component, responsive, accessibility, propagation, and enforcement contract for every Invumo interface

The design contract is system-wide. Pages compose shared components and must not create local colour, typography, status, control, or layout treatments that compete with the shared system. The owner-supplied HTML reference is illustrative only and is never an implementation source.

## Operations

- [`operations/production-runtime.md`](operations/production-runtime.md) — verified `app.invumo.com` runtime, database/test separation, user-level queue and scheduler operation, ZeptoMail account-email transport, temporary direct-production boundary, and remaining launch gates

Operational documents record how an approved runtime is installed and verified. Phase status and acceptance remain canonical only in the development tracker.

## Approved architecture baseline

- [`architecture/application-architecture.md`](architecture/application-architecture.md) — approved stack, modular-monolith boundary, runtime, deployment shape, and future mobile path
- [`architecture/codebase-map.md`](architecture/codebase-map.md) — living Invumo-specific backend/frontend ownership map, dependency direction, Action transaction contract, and module-boundary rules
- [`architecture/calculation-and-rounding.md`](architecture/calculation-and-rounding.md) — exact decimal storage, currency-precision snapshots, step rounding, authoritative libraries, and reconciliation tests
- [`architecture/identifier-policy.md`](architecture/identifier-policy.md) — native UUIDv7 domain identifiers, UUID foreign keys, exceptions, and security boundaries
- [`architecture/tenant-isolation.md`](architecture/tenant-isolation.md) — application authorization plus PostgreSQL RLS, database roles, tenant context, public-link bootstrap, and isolation tests
- [`architecture/numbering-and-concurrency.md`](architecture/numbering-and-concurrency.md) — counter-row locking, manual overrides, resets, idempotent Draft creation, and concurrency tests
- [`architecture/scheduling-and-jobs.md`](architecture/scheduling-and-jobs.md) — company-local scheduling, DST, queue/worker behavior, retries, downtime recovery, and duplicate suppression
- [`architecture/document-and-financial-state.md`](architecture/document-and-financial-state.md) — exact Quote, Invoice, transaction, payment, overdue, cancellation, reopening, reminder-reaction, public-state, concurrency, and deletion contract
- [`architecture/role-permission-matrix.md`](architecture/role-permission-matrix.md) — complete fixed-role Owner/Admin/Member authorization, guarded-action, system/public-actor, and policy-test contract
- [`architecture/relational-schema-and-snapshots.md`](architecture/relational-schema-and-snapshots.md) — approved v1 relational model, same-Company constraints, typed snapshot boundaries, deletion/retention rules, indexes, and migration strategy
- [`architecture/requirements-risk-assessment.md`](architecture/requirements-risk-assessment.md) — approved contradiction assessment, risk register, and downstream implementation obligations
- [`architecture/routes-navigation-and-editor-composition.md`](architecture/routes-navigation-and-editor-composition.md) — approved Company route boundary, authorized sidebar and Create menu, operational-list behavior, canonical workspaces, and shared document-editor composition
- [`architecture/platform-operations.md`](architecture/platform-operations.md) — approved Platform Owner boundary, control-plane visibility, full-action User impersonation, Account lifecycle, suspension, dual-identity audit, and back-office sequence
- [`architecture/uploads-and-storage.md`](architecture/uploads-and-storage.md) — approved Company-logo validation, private Laravel storage, controlled serving, immutable replacement/cleanup, and local-to-S3 migration contract
- [`architecture/public-token-and-access.md`](architecture/public-token-and-access.md) — approved Phase 8 public-token, RLS bootstrap, lifecycle, rate-limit, privacy, and public-route contract
- [`architecture/email-delivery-and-webhooks.md`](architecture/email-delivery-and-webhooks.md) — approved Phase 9 ZeptoMail API transport, ambiguous-send, webhook authentication/order, tracking, privacy, and delivery-erasure contract

These are reviewed constraints. Later architecture documents may refine names and implementation composition but must not silently change their behavior.

Current work, unresolved architecture gates, and later implementation status are recorded only in the development tracker. Do not treat a planned document as an approved decision until it has been written and reviewed.
