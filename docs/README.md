# Invumo Documentation

## Product

- [`product/master-build-brief.md`](product/master-build-brief.md) — canonical v1 product scope and behavior brief
- [`product/domain-rules.md`](product/domain-rules.md) — concise, implementation-facing domain invariants

The master brief is the canonical statement of scope. The domain-rules document restates only invariants that implementation work must preserve. The decision log in the sibling memory repository records why decisions were made and may retain superseded history; it is not a second product specification. If these sources appear to conflict, reconcile them explicitly before implementation rather than choosing one silently.

## Development tracking

- [`development/development-tracker.md`](development/development-tracker.md) — the only canonical phase sequence, progress checklist, dependency map, acceptance-gate record, and implementation log

Update the tracker as work advances. Do not create or maintain phase status, task completion, or a competing implementation sequence in another document.

## Approved architecture baseline

- [`architecture/application-architecture.md`](architecture/application-architecture.md) — approved stack, modular-monolith boundary, runtime, deployment shape, and future mobile path
- [`architecture/calculation-and-rounding.md`](architecture/calculation-and-rounding.md) — exact decimal storage, currency-precision snapshots, step rounding, authoritative libraries, and reconciliation tests
- [`architecture/identifier-policy.md`](architecture/identifier-policy.md) — native UUIDv7 domain identifiers, UUID foreign keys, exceptions, and security boundaries
- [`architecture/tenant-isolation.md`](architecture/tenant-isolation.md) — application authorization plus PostgreSQL RLS, database roles, tenant context, public-link bootstrap, and isolation tests
- [`architecture/numbering-and-concurrency.md`](architecture/numbering-and-concurrency.md) — counter-row locking, manual overrides, resets, idempotent Draft creation, and concurrency tests
- [`architecture/scheduling-and-jobs.md`](architecture/scheduling-and-jobs.md) — company-local scheduling, DST, queue/worker behavior, retries, downtime recovery, and duplicate suppression

These are reviewed constraints. Later architecture documents may refine names and implementation composition but must not silently change their behavior.

## Phase 0 working documents

- [`architecture/requirements-risk-assessment.md`](architecture/requirements-risk-assessment.md) — draft contradiction assessment, risk register, and proposed resolutions awaiting owner review

Current work, unresolved architecture gates, and later implementation status are recorded only in the development tracker. Do not treat a planned document as an approved decision until it has been written and reviewed.
