# Invumo Documentation

## Product

- [`product/master-build-brief.md`](product/master-build-brief.md) — canonical v1 product and delivery brief
- [`product/domain-rules.md`](product/domain-rules.md) — concise, implementation-facing domain invariants

The master brief is the canonical statement of scope. The domain-rules document restates only invariants that implementation work must preserve. The decision log in the sibling memory repository records why decisions were made and may retain superseded history; it is not a second product specification. If these sources appear to conflict, reconcile them explicitly before implementation rather than choosing one silently.

## Planned architecture documents

These documents do not exist yet and should be produced during the architecture phase:

- Requirements assessment and risk register
- Architecture and technology decision
- Relational data model
- Tenant and permission model
- Route and navigation map
- Calculation, rounding, document-state, and numbering specification
- Background-job, email, webhook, and public-link design
- Security, operations, migration, backup, and recovery plan
- Implementation and verification plan with phase acceptance gates

Do not treat a planned document as an approved decision until it has been written and reviewed.
