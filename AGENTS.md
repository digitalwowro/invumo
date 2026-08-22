# Invumo repository instructions

Before changing this repository:

1. Read `docs/product/master-build-brief.md`.
2. Read `docs/product/domain-rules.md` for financial and workflow invariants.
3. Read `docs/development/development-tracker.md` for the current phase, permitted work, dependencies, and acceptance gate.
4. Read the durable project context in the sibling `../invumo-agents` repository when it is available.

Project rules:

- Keep Invumo focused on quotation and invoicing; do not introduce accounting-suite features without explicit approval.
- PostgreSQL is required from day one.
- Do not silently choose or expand the application stack before completing the required architecture assessment.
- Treat authentication and invitations, tenant isolation, authorization, document snapshots, money calculations, document/payment state, numbering concurrency, recurring/reminder execution, webhooks, and public tokens as security/data-integrity boundaries.
- Prefer a single application, one database, simple deployment, and minimal infrastructure.
- Record meaningful approved product or architecture changes in documentation.
- Never treat a documentation reorganization, copied text, or bundled approval as approval for a newly introduced scope or technical choice. Obtain explicit owner approval for each new decision before labeling it approved or marking its tracker item complete.
- Treat `docs/development/development-tracker.md` as the only implementation-progress record. Update it with verified evidence when work advances; do not create competing phase checklists elsewhere.
- Never commit credentials, tokens, production data, or environment secrets.
