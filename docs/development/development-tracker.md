# Invumo Development Tracker

Status: Active canonical tracker
Last updated: 2026-08-23

This is the single source of truth for development sequence, phase status, task completion, acceptance gates, and implementation evidence. Product scope remains canonical in the [master build brief](../product/master-build-brief.md), implementation invariants remain canonical in [domain rules](../product/domain-rules.md), and approved technical constraints remain canonical in the [architecture documents](../README.md#approved-architecture-baseline).

Do not maintain a second phase checklist or progress table in another file. Other documents may explain requirements or link here, but all implementation progress is updated here.

## Current position

- Current phase: Phase 1 — Core platform and cross-cutting foundations
- Current phase status: In progress
- Application implementation: In progress
- Next gate: Complete the token-backed shared component and localization foundation, then verify the approved application shell in English, Romanian, desktop, and narrow layouts

Phase 0 is complete. Domain migrations, models, and business workflows may now follow the approved relational schema and phase dependencies below. Route/UI, integration, and production-operations decisions still follow the just-in-time gates below and do not block unrelated development.

## Status definitions

| Status | Meaning |
| --- | --- |
| Not started | No implementation work has begun |
| In progress | Work is actively underway; incomplete tasks remain |
| Blocked | Work cannot continue until a named dependency or decision is resolved |
| Complete | Every task and the phase acceptance gate have verified evidence |

## Tracking rules

- Keep at most one numbered phase `In progress` unless an explicit dependency-safe exception is recorded in the progress log.
- Change a task to `[x]` only when its implementation and proportionate automated verification exist.
- Mark a phase `Complete` only when its acceptance gate passes; code presence alone is insufficient.
- Record evidence using commit IDs, test commands/results, migration names, or durable document links.
- Record scope or architecture changes in the relevant canonical document and decision log before changing this sequence.
- Mark a new scope or technical decision `[x]` or describe it as approved only after the owner explicitly approves that specific decision. A documentation reorganization, migration, or bundled approval does not imply approval of newly introduced choices; split mixed decisions into independently reviewable items.
- Every phase includes relevant authorization, tenant isolation, audit history, localization, responsive UX states, automated tests, error handling, and operational logging. These are completion requirements, not Phase 12 cleanup.
- Never place credentials, tokens, production data, or environment secrets in this tracker.

## Phase map

| Phase | Name | Status | Required predecessors |
| --- | --- | --- | --- |
| 0 | Architecture readiness | Complete | None |
| 1 | Core platform and cross-cutting foundations | In progress | Phase 0 |
| 2 | Company configuration | Not started | Phase 1 |
| 3 | Customers and contacts | Not started | Phase 2 |
| 4 | Products & Services | Not started | Phase 2; scheduled after Phase 3 |
| 5 | Shared document core and quotes | Not started | Phases 2–4 |
| 6 | Invoices and quote conversion | Not started | Phase 5 |
| 7 | Payments | Not started | Phase 6 |
| 8 | Public documents | Not started | Phases 5–6 |
| 9 | Document email and reminders | Not started | Phases 6 and 8 |
| 10 | Recurring invoices | Not started | Phases 2–6 and 9 |
| 11 | Dashboard, audit UX, and data lifecycle | Not started | Phases 1–10 |
| 12 | Release readiness | Not started | Phases 1–11 |

## Phase 0 — Architecture readiness

- Status: Complete
- Started: 2026-08-22
- Completed: 2026-08-22

### Tasks

- [x] Complete product discovery, v1 scope, explicit exclusions, and domain invariants.
- [x] Approve the Laravel/Inertia/React/PostgreSQL application and deployment baseline.
- [x] Approve the official React starter-kit foundation, built-in Fortify baseline, Wayfinder, WorkOS/Teams exclusion, Composer/npm lockfiles, testing, quality, CI, and development-only Boost profile.
- [x] Explicitly defer TOTP two-factor authentication and recovery codes from v1.
- [x] Approve Laravel language files as the only authored translation source, with resolved common and page-specific strings passed to React through Inertia props and no `react-i18next` dependency.
- [x] Approve exact-decimal storage, currency-precision snapshots, rounding order, and cross-runtime calculation rules.
- [x] Approve native UUIDv7 domain identifiers and foreign-key policy.
- [x] Approve tenant isolation and forced PostgreSQL RLS mechanisms.
- [x] Approve document-number allocation and concurrency mechanisms.
- [x] Approve recurrence, reminders, timezone/DST, retry, downtime, and idempotency mechanisms.
- [x] Review and approve the requirements contradiction assessment and risk register.
- [x] Produce and review the relational data model, migrations strategy, same-company constraints, and snapshot boundaries.
- [x] Produce and review the complete Owner/Admin/Member role-action permission matrix.
- [x] Produce and review the exact quote, invoice, payment, refund, adjustment, overdue, and cancellation state specification.
- [x] Establish this complete implementation sequence, acceptance gates, and tracking protocol.

### Acceptance gate

Passed on 2026-08-22. The approved relational-schema/snapshot-boundary specification is mutually consistent with the requirements assessment, financial/document state specification, and permission matrix and is linked from the documentation index. Together they define domain table shape, snapshot boundaries, Owner/Admin/Member authorization, and financial lifecycle/history rules sufficiently to begin domain migrations and business features. The phase-specific design gates below remain mandatory before their named implementation boundaries.

### Evidence

- [Approved Requirements Contradiction Assessment and Risk Register](../architecture/requirements-risk-assessment.md)
- [Application architecture baseline](../architecture/application-architecture.md)
- [Calculation, Decimal Precision, and Rounding](../architecture/calculation-and-rounding.md)
- [Domain Identifier Policy](../architecture/identifier-policy.md)
- [Tenant Isolation and PostgreSQL Row-Level Security](../architecture/tenant-isolation.md)
- [Document Numbering and Concurrency](../architecture/numbering-and-concurrency.md)
- [Scheduling, Recurrence, Reminders, and Downtime](../architecture/scheduling-and-jobs.md)
- [Approved Quote, Invoice, and Financial State Specification](../architecture/document-and-financial-state.md)
- [Approved Owner/Admin/Member Permission Matrix](../architecture/role-permission-matrix.md)
- [Approved Relational Schema and Snapshot Boundaries](../architecture/relational-schema-and-snapshots.md)

## Just-in-time design gates

These decisions must be completed before the named implementation boundary, not before all development begins.

| Design gate | Must be complete before | Tracked in |
| --- | --- | --- |
| Central design-system contract, semantic tokens, typography, shared component ownership, responsive/accessibility behavior, and enforcement | Building custom application UI beyond the starter scaffold | Phase 1 |
| Route hierarchy, navigation, operational-list behavior, and shared-editor composition | Building the custom application shell and feature navigation beyond the starter scaffold | Phase 1 |
| Upload validation, storage visibility, size/type limits, serving, replacement, and cleanup rules | Implementing the shared file-upload foundation | Phase 1 |
| PDF renderer compatibility proof and final renderer selection | Committing to and implementing the shared production PDF renderer | Phase 5 |
| Public-token hashing, tenant bootstrap, expiry, revocation, regeneration, and rate-limit design | Implementing public document access | Phase 8 |
| ZeptoMail transport, webhook authentication, event mapping, retry, and idempotency design | Implementing document email delivery and provider webhooks | Phase 9 |
| Hosted secrets, restricted database roles, health endpoint, and worker/scheduler supervision | Running the pre-launch hosted production application | Phase 1 |
| Evidence that externally managed rollback, off-server backup/restore, uptime/error monitoring, and alerts are active | Public launch and reverified before v1 release | Phase 12 |
| Separate development/production environments and a repeatable application release process | Before real users make direct-production development unsafe | Post-launch transition gate; no Phase 1 blocker |

Security, tenant isolation, auditability, error handling, and observability are designed and verified with every phase. The complete release security and operational review remains a Phase 12 gate.

## Phase 1 — Core platform and cross-cutting foundations

- Status: In progress
- Started: 2026-08-23
- Completed: —

### Tasks

- [x] Review and approve the centralized [Invumo Design System Contract](../design/design-system.md) before implementing custom application UI beyond the starter scaffold.
- [x] Confirm the source-reference `Invuma` typo is absent from application code, copy, asset/file names, and metadata; enforce the correct `Invumo` name in the design-contract CI guard.
- [x] Scaffold the official Laravel React starter kit with Laravel 13, PHP 8.5, Inertia 3, React 19, strict TypeScript, Tailwind CSS 4, shadcn/ui, Vite, and Wayfinder.
- [ ] Implement the approved semantic token/font foundation, source-owned shadcn primitive customization, shared Invumo component layers, component-state gallery, raw-colour/component-boundary guards, and core visual-regression coverage before building the custom application shell.
- [x] Before building the custom application shell and feature navigation, produce and review the approved route, navigation, operational-list, and shared-editor composition map.
- [x] Use built-in Fortify authentication; exclude WorkOS AuthKit and the starter-kit Teams domain.
- [x] Commit Composer and npm lockfiles; do not introduce an alternative JavaScript package manager.
- [x] Install development-only Laravel Boost and commit Invumo-specific agent rules while keeping generated/local agent configuration out of production runtime concerns.
- [x] Configure Pint, Larastan/PHPStan, strict TypeScript, ESLint, Prettier, Pest 4, Vitest, and Pest Browser/Playwright.
- [x] Add GitHub Actions checks for PHP tests/static analysis/formatting, TypeScript tests/type checking/linting, browser smoke tests where appropriate, and production asset builds.
- [ ] Establish the conventional modular-monolith structure and application-action transaction boundaries.
- [ ] Build the PostgreSQL schema foundation using UUIDv7 domain identifiers and approved exact-decimal types, separate migration/runtime roles, forced RLS, repeatable migrations, and a PostgreSQL test-data strategy.
- [ ] Implement registration, email verification, sign-in/out, password reset, secure sessions, and session invalidation without TOTP or recovery-code scope.
- [ ] Implement foundational system-email delivery for verification, recovery, and company invitations.
- [ ] Implement users, accounts, companies, memberships, invitations, company switching, and ownership-transfer safeguards.
- [ ] Implement tenant-context, authorization, same-company foreign-key, restricted-role RLS, and cross-company denial primitives.
- [ ] Establish English/Romanian localization with Laravel as the only authored source, small common and page-specific Inertia translation bags, a typed React accessor/interpolation helper, browser-native live plural selection, and locale/key/placeholder tests.
- [ ] Implement audit-event infrastructure used by every later business operation.
- [ ] Implement shared validation, error handling, logging, health checks, and configuration/secrets boundaries.
- [ ] Implement server-authoritative `brick/math` calculation primitives, `decimal.js` preview primitives, string decimal transport, and shared golden calculation vectors.
- [ ] Configure the PostgreSQL-backed queue, one linger-backed `invumo` user-level systemd worker, `invumo` user cron-triggered scheduler, and job idempotency/observability primitives.
- [ ] Complete the upload/storage design gate, then implement the validated file-upload foundation required for company logos.
- [x] Establish the hosted pre-launch runtime and record the approved temporary direct-production workflow: private secrets, restricted database roles, public health checks, and unprivileged worker/scheduler supervision are in place; rollback, backup/restore, and monitoring remain externally managed; repeatable deployment automation is deferred until the development/production split.

### Acceptance gate

Authentication, email verification, recovery, and secure-session paths work without TOTP/recovery-code scope. Laravel authorization and PostgreSQL RLS independently deny cross-company access using the restricted runtime role. Migrations are repeatable; queue/scheduler context cannot leak between companies; CI enforces the approved quality stack and design-contract boundaries; the token-backed shared component foundation propagates consistently in English, Romanian, and representative narrow layouts; and audit, localization, exact-decimal, and file foundations are usable by Phase 2.

## Phase 2 — Company configuration

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement structured company identity, IANA timezone, and automation-local time.
- [ ] Implement currency settings and display/precision configuration.
- [ ] Implement tax presets.
- [ ] Implement payment terms, quote validity, Terms & Conditions, and quote/invoice note defaults.
- [ ] Implement numbering-series settings and reset-period counter records.
- [ ] Implement bank accounts and default selection.
- [ ] Implement company logo and primary brand color.
- [ ] Implement email and public-link defaults required by later phases.
- [ ] Add settings authorization, validation, audit coverage, and tenant-isolation tests.

### Acceptance gate

All defaults required to create stable customer, product, and document snapshots exist, are company-scoped, are authorized, and can be consumed without inventing configuration in later phases.

## Phase 3 — Customers and contacts

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement customer/contact CRUD, archive/delete rules, search, sorting, and pagination.
- [ ] Implement Individual/Company identity and structured registration/address fields.
- [ ] Implement primary/billing contacts and email-delivery preferences.
- [ ] Implement currency, language, tax, payment-term, and recipient defaults.
- [ ] Add customer/contact authorization, audit coverage, validation, and tenant-isolation tests.

### Acceptance gate

Customer defaults resolve predictably, customer records are ready to be snapshotted by documents, and all list and destructive actions preserve tenant and dependency rules. Inline creation starts with the shared editor in Phase 5 and is reused by recurring templates in Phase 10.

## Phase 4 — Products & Services

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement company-scoped product/service CRUD.
- [ ] Implement search, sorting, pagination, and archive/delete behavior.
- [ ] Implement price/currency, unit, tax, period, description, and code/SKU defaults.
- [ ] Add catalog permissions, audit coverage, validation, and tenant-isolation tests.

### Acceptance gate

Active entries can initialize detached, editable line data with safe currency-mismatch behavior, and source edits/archiving cannot rewrite snapshots. Inline creation starts with Phase 5 and is reused by Phase 10.

## Phase 5 — Shared document core and quotes

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement the shared document editor and exact-decimal line/document calculation engine.
- [ ] Before finalizing the `document_lines` position constraint and drag-and-drop action, select and document either a deferred unique constraint or a collision-free update strategy; preserve atomic final-order uniqueness and test swaps, moves, concurrency, and stale editors.
- [ ] Implement manual lines, searchable product/service selection, and inline customer/product creation without losing editor progress.
- [ ] Implement customer, product/service, tax, bank, Terms & Conditions, notes, currency-precision, and settings snapshots.
- [ ] Implement locked counter-row numbering with idempotent persisted Draft creation, first applied to quotes.
- [ ] Implement quote CRUD, non-negative day-offset validity, lifecycle, derived expiry, one mutable current public/PDF representation, and operational list controls.
- [ ] Implement the optional customer reference / PO number, including list search and customer-facing rendering when present.
- [ ] Build the shared outward-facing renderer and complete the pure-PHP PDF compatibility proof before committing to the renderer.
- [ ] Add quote authorization, audit coverage, calculation vectors, concurrency tests, PDF tests, and browser workflow tests.

### Acceptance gate

Quotes calculate deterministically, preserve every required snapshot, render consistently across editor/public/PDF output, remain isolated under concurrent company activity, and receive distinct automatic numbers under overlapping creation requests.

## Phase 6 — Invoices and quote conversion

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement invoice CRUD with one mutable current public/PDF representation and immutable already-delivered email artifacts; permanent deletion of a transaction-free Invoice that was already issued, sent, or shared must use the approved highest-friction confirmation pattern.
- [ ] Reuse the shared editor, calculations, numbering, renderer, and PDF pipeline.
- [ ] Implement Draft/Issued/Cancelled lifecycle, zero-total Paid behavior, derived payment state, day-offset due-date validation, and overdue flag.
- [ ] Implement quote-to-one-or-many-invoices with normal Accepted conversion, confirmed Owner/Admin Draft/Sent/Expired overrides, Rejected blocking, Draft-only unused unlinking with permanent provenance afterward, quoted/invoiced/remaining allocation, and customer-reference inheritance.
- [ ] Implement invoice search, filtering, sorting, pagination, and operational list controls.
- [ ] Add invoice authorization, audit coverage, state tests, calculation tests, and tenant-isolation tests.

### Acceptance gate

Independent and quote-derived invoices produce the same authoritative calculations and snapshots, and invoice lifecycle/payment/overdue state behaves correctly around company-local dates.

## Phase 7 — Payments

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement invoice transaction records with explicit payment, refund, and adjustment direction.
- [ ] Implement partial payments, cash-only refund capacity, non-refundable adjustments, overpayment prevention, and outstanding-balance derivation.
- [ ] Implement derived invoice payment state.
- [ ] Implement transaction-aware cancellation guards, post-cancellation transaction blocking, authorized reopening under the approved state rules, history retention, deletion constraints, and the expected Member-to-Owner/Admin escalation when an Adjustment is required to reach zero net paid.
- [ ] Implement the company Transactions screen with operational list controls.
- [ ] Add transaction authorization, audit coverage, precision validation, reconciliation tests, and tenant-isolation tests.

### Acceptance gate

Transaction history reconciles exactly to invoice balance and state under create, edit, delete, adjustment, and refund paths. Cancellation cannot retain a positive net-paid balance, accept new transactions, or erase linked history.

## Phase 8 — Public documents

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Complete the public-token design gate before implementing public access.
- [ ] Implement unpredictable hashed secure links.
- [ ] Implement transaction-local token-hash bootstrap into the correct RLS tenant context.
- [ ] Implement expiry, revocation, and regeneration.
- [ ] Implement public quote acceptance/rejection with required identity.
- [ ] Implement public invoice viewing and downloading.
- [ ] Add rate limits, replay protection, lifecycle/validity enforcement, idempotency, audit attribution, and cross-tenant tests.

### Acceptance gate

Public access cannot cross tenants, revoked tokens stay revoked, expired quote actions are blocked, and repeated customer actions cannot create duplicate effects.

## Phase 9 — Document email and reminders

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Complete the ZeptoMail transport and webhook design gate before implementing provider integration.
- [ ] Integrate Zoho ZeptoMail for document delivery, reusing the platform email transport where appropriate.
- [ ] Implement event- and language-specific default templates, safe placeholders, and preview.
- [ ] Implement editable direct-send composition.
- [ ] Implement recipient/PDF-delivery precedence and valid-link handling.
- [ ] Implement authenticated, idempotent delivery/open webhooks and email history.
- [ ] Implement automated before/after-due reminders, excluding zero-total Paid invoices.
- [ ] Implement explicitly optional payment-received messages.
- [ ] Implement company-local schedule materialization, transactional dispatch claiming, approved retries, stale/superseded downtime behavior, failure visibility, and duplicate suppression.
- [ ] Add authorization, audit, localization, webhook-authentication, retry, idempotency, and browser tests.

### Acceptance gate

Direct and automated sends are recoverable and observable, reminder links remain valid without overriding explicit revocation, and duplicate jobs or webhooks cannot duplicate customer-visible effects.

## Phase 10 — Recurring invoices

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement template CRUD with required internal-only searchable name, optional customer reference / PO number, line snapshots, Draft/Active/Paused/Completed states, and terminal Completed duplication.
- [ ] Reuse shared customer/product selection, inline creation, editor, calculations, and snapshot behavior.
- [ ] Implement local-calendar scheduling with explicit DST resolution, no pre-activation backfill for past start dates, bounded Active-time downtime catch-up, and no implicit pause backfill.
- [ ] Implement idempotent one-invoice-per-occurrence generation.
- [ ] Implement automatic issue, optional automatic email, and visible last/next-run outcomes.
- [ ] Implement source-aware recurring inheritance for all Customer values, including currency/precision, language, payment terms, and default tax, while preserving explicit template/line overrides, the no-FX rule, customer reference, delivery, and reminder inheritance; template edits affect future occurrences only.
- [ ] Implement the inherited-currency automatic-email safety latch: issue-only generation, visible review state, continued delivery suppression, manual-send confirmation, concurrency recheck, and explicit-override bypass.
- [ ] Add authorization, audit, recurrence, retry, overlap, pause/resume, downtime, currency-review-latch, and duplicate-suppression tests.

### Acceptance gate

Templates are identifiable without customer-visible naming, generated invoices preserve the intended snapshots and customer reference, and retries, overlaps, pause/resume, missed occurrences, or unconfirmed inherited-currency changes cannot create unintended duplicate or wrong-currency emails.

## Phase 11 — Dashboard, audit UX, and data lifecycle

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Implement the currency-grouped operational dashboard without cross-currency aggregation.
- [ ] Implement searchable audit-history UI and review audit completeness across prior phases.
- [ ] Complete archive/delete flows, dependency warnings, and user-data deletion paths.
- [ ] Review and complete ownership-transfer and membership-management UX.
- [ ] Add authorization, tenant-isolation, audit-integrity, destructive-action, and browser tests.

### Acceptance gate

Users can understand operational state and safely complete every destructive, data-lifecycle, membership, and ownership-sensitive action without erasing required history.

## Phase 12 — Release readiness

- Status: Not started
- Started: —
- Completed: —

### Tasks

- [ ] Complete English and Romanian translation coverage.
- [ ] Complete accessibility, responsive behavior, and cross-browser refinement.
- [ ] Complete the critical-path, edge-case, concurrency, scheduling, calculation, and tenant-isolation test suites.
- [ ] Complete the security review and abuse/rate-limit verification.
- [ ] Verify production migrations and restricted database roles.
- [ ] Verify evidence that the externally managed off-server database/file backup and full-restore process is active and usable.
- [ ] Verify evidence that the externally managed rollback process and recovery ownership are active and usable.
- [ ] Verify external uptime/error monitoring and alert delivery; reverify the application health endpoint and internal queue/scheduler supervision.
- [ ] Before real users make direct-production development unsafe, establish separate development and production environments plus a repeatable application release process; this is intentionally not a Phase 1 blocker during the no-user pre-launch period.
- [ ] Run end-to-end acceptance against the successful-v1 definition in both launch languages.

### Acceptance gate

The release can be deployed, observed, backed up, restored, rolled back, and operated safely. Every successful-v1 journey passes in English and Romanian, and no critical security, data-integrity, accessibility, or recovery issue remains open.

## Progress log

| Date | Phase | Change | Evidence |
| --- | --- | --- | --- |
| 2026-08-23 | 1 | Configured the production foundational account-email transport through ZeptoMail's regional authenticated SMTP endpoint with TLS, a verified sender, bounded timeout, private environment/cache permissions, atomic rollback-on-failure setup, and unprivileged queue restart; the provider accepted the test and the recipient confirmed delivery; actual verification, recovery, and invitation flows remain open | [Production runtime baseline](../operations/production-runtime.md#system-email-through-zeptomail); `scripts/configure-zeptomail.sh`; cached-setting checks; received test message; active queue worker; `scripts/verify-production-runtime.sh` |
| 2026-08-23 | 1 | Added a physically separate `invumo_test` database and a pre-migration test guard requiring `APP_ENV=testing` plus `_test` PostgreSQL targets on both runtime/schema connections; proved that cached production configuration aborts before `RefreshDatabase`, then ran the complete CI suite safely against the isolated database, restored private production caches, restarted the user worker, and confirmed the production database remained unchanged | `composer ci:check` (Vitest 10 passed; Pest 34 passed, 139 assertions; build and all checks passed); production before/after verification; `scripts/bootstrap-test-database.sh`; `scripts/verify-production-runtime.sh` |
| 2026-08-23 | 1 | Established and verified the initial hosted runtime at `app.invumo.com`: PHP 8.5/PostgreSQL 18, private production environment/config cache, separate non-superuser schema/runtime database roles, successful starter migrations, secure host-only sessions, public health/login checks, a linger-backed `invumo` user-level queue service, and an overlap-protected `invumo` user scheduler cron with journal output; approved temporary direct-production development before launch, external ownership of rollback/backups/monitoring, and deferral of repeatable deployment until the development/production split; real email, RLS business schema, and job-observability work remains open | [Production runtime baseline](../operations/production-runtime.md); `scripts/verify-production-runtime.sh`; `/up` HTTP 200; `/` HTTP 302; `systemctl --user is-active invumo-queue`; scheduler journal evidence |
| 2026-08-23 | 1 | Confirmed that `Invuma` exists only in explanatory documentation identifying the supplied-reference typo, with no occurrence in application code, copy, asset/file names, or metadata; extended the design-contract check to reject future filename, application-content, or metadata leaks; clarified that the approved design contract supersedes all earlier ledger/paper/bookmark visual exploration | `git grep -in 'invuma'`; `git ls-files \| rg -i 'invuma'`; `npm run design:check`; [Approved design-system authority](../design/design-system.md#1-authority-and-precedence) |
| 2026-08-23 | 1 | Began the approved single-source localization foundation with English/Romanian Laravel catalogs, a bounded common Inertia bag, typed React lookup/interpolation, browser-native plural selection, locale key/placeholder tests, and localized shell accessibility labels; frontend checks, PHP formatting/static analysis, the isolated PHP localization test, and the production asset build pass; the complete database-backed PHP suite awaits local test-database credentials | `vendor/bin/pint --test`; `vendor/bin/phpstan analyse --no-progress`; `php artisan test tests/Unit/Localization/TranslationCatalogTest.php` (1 passed, 49 assertions); `npm run format:check`; `npm run design:check`; `npm run lint:check`; `npm run types:check`; `npm run test:unit` (10 passed); `npm run build` |
| 2026-08-23 | 1 | Added and verified Composer/npm lockfiles, development-only Laravel Boost, the approved PHP/TypeScript quality toolchain, and the GitHub Actions PostgreSQL 18 CI workflow; database-backed tests are configured to run with CI-owned credentials and application-key generation | `composer validate --strict`; `composer.lock`; `package-lock.json`; [GitHub Actions workflow](../../.github/workflows/tests.yml) |
| 2026-08-23 | 1 | Approved explicit Company routes; Dashboard, Quotes, Invoices, Transactions, Customers, Recurring, Products, Settings sidebar order; compact Create menu; Member Products visibility; canonical workspaces; and cursor-versus-numbered pagination behavior, closing the composition gate | [Approved composition gate](../architecture/routes-navigation-and-editor-composition.md) |
| 2026-08-23 | 1 | Produced the draft route, authorized navigation, operational-list, and shared-editor composition map; it remains unapproved and the custom application shell remains blocked | [Draft composition gate](../architecture/routes-navigation-and-editor-composition.md) |
| 2026-08-23 | 1 | Scaffolded the approved official Laravel React foundation, selected Fortify registration/verification/password features without TOTP, WorkOS, or Teams, installed the approved quality tooling, and began the shared design-system implementation; frontend design guard, lint, strict types, unit tests, and production build pass | `npm run design:check`; `npm run lint:check`; `npm run types:check`; `npm run test:unit` (7 passed); `npm run build`; Laravel 13.26.1, Inertia Laravel 3.3.1, React 19.2.8, Tailwind 4.3.3, Vite 8.2.2 |
| 2026-08-23 | 1 | Approved the centralized design-system implementation contract, completed its just-in-time review gate, and advanced Phase 1 to In progress; application implementation remains not started | [Approved design-system contract](../design/design-system.md) |
| 2026-08-22 | 0 | Approved the complete relational schema and snapshot boundaries, including all six composition/search/deletion/dispatch choices; passed the Phase 0 acceptance gate and advanced the canonical current position to Phase 1 | [Approved relational schema](../architecture/relational-schema-and-snapshots.md) |
| 2026-08-22 | 0 | Documented the expected Member-to-Owner/Admin escalation when cancellation requires an Adjustment: keep cancellation blocked, explain the required role, and never suggest a Refund beyond actual refundable cash | [State escalation rule](../architecture/document-and-financial-state.md#permission-aware-cancellation-escalation) |
| 2026-08-22 | 0 | Approved the complete fixed-role permission matrix, including Member Refund and Payment/Refund correction access, Owner/Admin-only Adjustments, Member Quote correction and Invoice cancel/reopen access, and the remaining governance/automation/audit boundaries; only the relational schema/snapshot specification still blocks Phase 0 | [Approved permission matrix](../architecture/role-permission-matrix.md) |
| 2026-08-22 | 0 | Produced the draft complete Owner/Admin/Member permission matrix; eight grouped role decisions remain open, so its Phase 0 task is not marked complete | [Draft permission matrix](../architecture/role-permission-matrix.md) |
| 2026-08-22 | 0 | Flagged the Phase 6 UI requirement that permanent deletion of a transaction-free Invoice already issued, sent, or shared must use materially stronger confirmation friction than an ordinary warning | [Approved state specification](../architecture/document-and-financial-state.md#flexible-document-deletion) |
| 2026-08-22 | 0 | Approved all seven open Quote, Invoice, and financial-state decisions; promoted the exact state specification and reduced the Phase 0 gate to the permission matrix and relational schema/snapshot boundaries | [Approved state specification](../architecture/document-and-financial-state.md) |
| 2026-08-22 | 0 | Produced the draft exact Quote, Invoice, and financial state specification; seven owner decisions remain open, so its Phase 0 task is not marked complete | [Draft state specification](../architecture/document-and-financial-state.md) |
| 2026-08-22 | 0 | Added the approved recurring inherited-currency safety latch: generation/issue continues, automatic email stays suppressed until a reviewed Invoice is manually sent, and explicit template currency overrides remain unaffected | Product, domain, scheduling, and risk documentation |
| 2026-08-22 | 0 | Approved the requirements contradiction assessment after resolving full latest-Customer recurring inheritance and Draft-only unused Quote/Invoice unlinking; three Phase 0 schema-shaping specifications remain | [Approved assessment](../architecture/requirements-risk-assessment.md) |
| 2026-08-22 | 0 | Recorded all nine owner-selected top-level risk resolutions; kept the recurring-Customer refresh boundary and Quote-derived Draft unlink rule explicitly open rather than inferring omitted details | [Draft assessment](../architecture/requirements-risk-assessment.md) |
| 2026-08-22 | 0 | Produced the draft requirements contradiction assessment and risk register; owner review and proposed-resolution approvals remain open | [Draft assessment](../architecture/requirements-risk-assessment.md) |
| 2026-08-22 | 0 | Explicitly deferred TOTP/recovery codes from v1 and approved Laravel as the sole authored translation source with page-scoped Inertia translation props | Product and architecture approval recorded in work and memory documentation |
| 2026-08-22 | 0 | Reopened TOTP scope and localization-plumbing decisions that had been bundled into a documentation approval without explicit sign-off; added the explicit-approval tracking rule | Work and memory documentation |
| 2026-08-22 | 0 | Limited the Phase 0 blocking gate to the four schema-shaping specifications and moved route/UI, integration, and production-operations decisions to explicit just-in-time gates | This tracker |
| 2026-08-22 | 0 | Created the canonical tracker, migrated all implementation phases and acceptance gates from the master brief, and recorded the approved bootstrap profile | Documentation commit recorded in repository history |
