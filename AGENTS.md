# Invumo repository instructions

Before changing this repository:

1. Read `docs/product/master-build-brief.md`.
2. Read `docs/product/domain-rules.md` for financial and workflow invariants.
3. Read `docs/development/development-tracker.md` for the current phase, permitted work, dependencies, and acceptance gate.
4. Read `docs/architecture/codebase-map.md` before adding or moving application code.
5. Read the durable project context in the sibling `../invumo-agents` repository when it is available.

Project rules:

- Keep Invumo focused on quotation and invoicing; do not introduce accounting-suite features without explicit approval.
- PostgreSQL is required from day one.
- Do not silently choose or expand the application stack before completing the required architecture assessment.
- Treat authentication and invitations, tenant isolation, authorization, document snapshots, money calculations, document/payment state, numbering concurrency, recurring/reminder execution, webhooks, and public tokens as security/data-integrity boundaries.
- Prefer a single application, one database, simple deployment, and minimal infrastructure.
- Record meaningful approved product or architecture changes in documentation.
- Never treat a documentation reorganization, copied text, or bundled approval as approval for a newly introduced scope or technical choice. Obtain explicit owner approval for each new decision before labeling it approved or marking its tracker item complete.
- Treat `docs/development/development-tracker.md` as the only implementation-progress record. Update it with verified evidence when work advances; do not create competing phase checklists elsewhere.
- Follow the Invumo-specific module ownership and dependency directions in `docs/architecture/codebase-map.md`. Create feature/module directories only when real code needs them; do not add a generic module framework, empty scaffolds, barrel files, or default `Services`/`Helpers` buckets.
- Each externally triggered mutation has one named root application Action that owns the workflow and outer database transaction. Keep controllers, commands, jobs, and Inertia pages thin; use purpose-specific, read-only Queries for page/list data. Cross-module mutation goes through the owning module's Action.
- Build phase work as coherent vertical slices across validation, authorization, Action/Query, persistence, UI, audit/outbox behavior, tests, and documentation. Do not restructure unrelated code opportunistically while implementing a slice.
- Add comments for non-obvious intent, invariants, ordering, or safety constraints. Do not narrate obvious syntax; a comment that cannot explain why the code exists should usually be omitted.
- Keep handwritten/source-owned PHP, TypeScript/React, JavaScript, test, and stylesheet files at or below the 300-line soft limit whenever practical. Files from 301 through 500 lines require an explicit refactor review and visible guard warning; files above the 500-line hard limit are prohibited unless the owner explicitly approves a narrow documented exception. Generated code, lockfiles, dependencies, compiled assets, documentation, and authored translation catalogs are excluded. Do not use exclusions or exceptions to hide code that should be separated by responsibility.
- Authorization to inspect or edit does not authorize committing, pushing, deploying, releasing, sending communications, writing to an external provider, or mutating production data. Each applicable external action requires explicit authorization.
- The owner's approved-batch closeout instruction is standing explicit authorization for one narrow exception: after an owner-approved automated implementation batch passes the complete local quality gate, apply that batch's production migrations through the approved schema connection, verify production runtime/data boundaries, save and commit all batch work plus durable memory, push both repositories, and monitor the resulting GitHub Actions runs to success. Fix, reverify, recommit, and repush any failure. This does not authorize automatic migration, commit, or push for manual changes, review corrections, or ad hoc improvements outside an approved batch.
- External/provider writes may occur only through explicit user actions or approved scheduled/system workflows. Rendering, reads, polling, previews, tests, and browser verification must remain side-effect free unless the owner explicitly authorizes a specific write-capable verification workflow.
- Keep provider-specific behavior behind narrow adapters and translate provider values into Invumo-owned concepts. Keep environment access in Laravel configuration; when an environment variable changes, update validation, `.env.example`, operations documentation, and relevant tests together. Do not hard-code origins, endpoints, ports, or credentials.
- Logs and diagnostics must use allowlisted, redacted metadata and must not expose secrets, tokens, cookies, customer document content, email bodies, recipient lists, full provider payloads, or sensitive identifiers.
- Before changing schemas, stored data, permissions, external integrations, or public behavior, identify migration, compatibility, and rollout impact; then update the affected contract, validation, implementation, UI, tests, and documentation coherently.
- Never refresh a canonical visual snapshot merely to make CI pass. View the pinned-runner expected, actual, and difference images; confirm the rendering is intended; then update the exact GitHub-produced baseline and its hash-bound evidence entry with the protected screens, causal code change, intended visual differences, and inspection source. A snapshot change without matching evidence must fail the quality gate.
- Never commit credentials, tokens, production data, or environment secrets.
- Never run `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, or `db:wipe` in the hosted production checkout. Invumo permits destructive database commands only when Laravel is in the `testing` environment and both PostgreSQL connection targets end in `_test`; a CLI environment override must never be treated as database isolation.
