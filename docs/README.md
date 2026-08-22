# Invumo Documentation

## Product

- [`product/master-build-brief.md`](product/master-build-brief.md) — canonical v1 product and delivery brief
- [`product/domain-rules.md`](product/domain-rules.md) — concise, implementation-facing domain invariants

The master brief is the canonical statement of scope. The domain-rules document restates only invariants that implementation work must preserve. The decision log in the sibling memory repository records why decisions were made and may retain superseded history; it is not a second product specification. If these sources appear to conflict, reconcile them explicitly before implementation rather than choosing one silently.

## Approved architecture baseline

- [`architecture/application-architecture.md`](architecture/application-architecture.md) — approved stack, modular-monolith boundary, runtime, deployment shape, and future mobile path
- [`architecture/tenant-isolation.md`](architecture/tenant-isolation.md) — application authorization plus PostgreSQL RLS, database roles, tenant context, public-link bootstrap, and isolation tests
- [`architecture/numbering-and-concurrency.md`](architecture/numbering-and-concurrency.md) — counter-row locking, manual overrides, resets, idempotent Draft creation, and concurrency tests
- [`architecture/scheduling-and-jobs.md`](architecture/scheduling-and-jobs.md) — company-local scheduling, DST, queue/worker behavior, retries, downtime recovery, and duplicate suppression

These are reviewed constraints. Later architecture documents may refine names and implementation composition but must not silently change their behavior.

## Remaining architecture package

The following documents still need to be produced or completed before broad application implementation:

- Requirements assessment and risk register
- Relational data model
- Complete role/action permission matrix
- Route and navigation map
- Calculation, rounding, document-state, and payment-state specification
- Remaining email, webhook, PDF, upload, and public-token integration design
- Security, operations, migration, backup, and recovery plan
- Implementation and verification plan with phase acceptance gates

Do not treat a remaining planned document as an approved decision until it has been written and reviewed.
