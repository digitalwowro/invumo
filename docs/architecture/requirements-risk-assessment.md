# Requirements Contradiction Assessment and Risk Register

Status: Approved
Last updated: 2026-08-23

This assessment reconciles the approved [master build brief](../product/master-build-brief.md), [domain rules](../product/domain-rules.md), existing architecture decisions, canonical [development tracker](../development/development-tracker.md), and durable decision log. It identifies contradictions, schema-shaping ambiguities, accepted risks, and decisions intentionally deferred to a just-in-time gate.

This document does not create product or technical scope by itself. It records the owner's explicit resolutions and names the downstream Phase 0 or just-in-time specification required for details not settled here. No finding remains in **Open — owner decision required** status.

## Executive outcome

- No contradiction invalidates the approved Laravel/Inertia/React/PostgreSQL architecture.
- The approved tenancy, exact-decimal, numbering, scheduling, UUID, authentication-scope, localization, and infrastructure decisions resolve the highest architectural risks identified earlier.
- One documentation-sequencing conflict is resolved by the newer canonical tracker: navigation is a Phase 1 just-in-time gate, while domain migrations and business workflows wait for the four schema-shaping Phase 0 deliverables.
- All owner choices and follow-up boundaries are approved. The exact financial/document state specification, complete Owner/Admin/Member permission matrix, and relational schema/snapshot-boundary specification complete the Phase 0 architecture baseline.
- Public-token implementation details, Phase 9 document-email/webhook details, and public-launch operations verification remain valid just-in-time gates. The route/composition, upload, PDF-renderer, and hosted-runtime gates are complete; foundational ZeptoMail SMTP is operational. None of the remaining gates reopens the approved schema.

## Status and severity

| Label                                        | Meaning                                                                                                                |
| -------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Open — owner decision required               | The current requirements permit more than one materially different behavior; the recommendation is not approved yet    |
| Approved resolution                          | The owner explicitly approved the resolution; canonical product/domain/memory documents must contain it                |
| Approved direction; downstream specification | The product choice is approved, but exact mechanics must still be proposed and approved in the named Phase 0 document  |
| Phase 0 specification                        | The requirement was approved in principle and required exact rules in its named Phase 0 document before implementation |
| Documentation correction                     | Later approved direction is clear, but an older document must be reconciled                                            |
| Just-in-time gate                            | Must be resolved before its feature or deployment boundary, not before unrelated domain work                           |
| Resolved                                     | Existing approved documentation already supplies a sufficient decision and mitigation                                  |

Severity describes the impact of implementing the wrong behavior:

| Severity | Meaning                                                                                                                |
| -------- | ---------------------------------------------------------------------------------------------------------------------- |
| Critical | Could create cross-tenant disclosure, unreconciled money, duplicate business effects, or unrecoverable production loss |
| High     | Could force a costly schema rewrite, corrupt historical meaning, or create inconsistent authorization/state behavior   |
| Medium   | Could create avoidable UX, maintenance, operational, or support problems without corrupting core data                  |
| Low      | Localized implementation detail with a contained correction path                                                       |

## Blocking findings and recommendations

### RA-001 — Architecture sequencing is stated inconsistently

- Severity: Medium
- Status: Approved resolution; documentation correction applied
- Evidence: D-018 says navigation and all architecture work precede application implementation. The canonical tracker, approved later, permits the starter scaffold/tooling foundation during Phase 0 and makes routes/navigation a just-in-time gate before the custom application shell.
- Risk: A future agent could either begin business migrations too early or unnecessarily block safe scaffold/tooling work.
- Existing direction: The development tracker is the single source of truth for permitted work and phase gates.
- Approved resolution: Mark D-018 as partially superseded by the canonical Phase 0/just-in-time-gate sequencing and clarify the master brief's introductory implementation wording. Preserve the rule that domain migrations, models, and business workflows wait for the four Phase 0 schema-shaping deliverables.

### RA-002 — Account ownership cardinality and registration bootstrap are undefined

- Severity: High
- Status: Approved resolution
- Evidence: An Account has an account owner and owns plan entitlements, but the brief does not say whether one User may own multiple Accounts or when an Account is created.
- Risk: Account, ownership-transfer, entitlements, and registration tables could be designed with incompatible cardinalities.
- Approved resolution: In v1, every registered User owns exactly one personal Account, created transactionally during registration with the default placeholder plan. That Account may own multiple Companies. A User may additionally be an Admin or Member of Companies owned by other Accounts. A User cannot own a second Account in v1.
- Downstream specification: Relational schema and permission matrix.

### RA-003 — Draft default and snapshot timing conflicts with incomplete Draft creation

- Severity: High
- Status: Approved resolution
- Evidence: New Quote/Invoice immediately persists an incomplete Draft, while the default rule says company/customer defaults are resolved when the Draft is created. A customer may not have been selected at that moment. Recurring templates are also described as stable snapshots, but their customer-snapshot boundary is not stated explicitly.
- Risk: Implementations may silently refresh customer data, produce partially populated snapshots, or copy different values in quote, invoice, and recurring workflows.
- Approved resolution:
    1. Immediate Draft creation snapshots company-level defaults that can be resolved without a customer.
    2. Selecting or changing a customer snapshots customer identity, address, registration, currency, language, payment terms, tax, recipients, CC/BCC, and delivery preference after showing the approved replacement confirmation.
    3. A recurring template records inheritance versus explicit override intent for every Customer-derived field. At each generation, explicit template or line overrides remain authoritative; every inherited value resolves again from the current Customer, then the current Company fallback.
    4. Refreshed Customer values include identity, address, registration, contacts, recipients, CC/BCC, delivery preference, currency, document language, payment terms, and default tax. A line tax explicitly selected on the template remains fixed; a line left to inherit Customer tax uses the current Customer default.
    5. When inherited currency changes, the generated Invoice uses the current Customer currency and its current configured precision. Stored template line inputs keep their numeric values, are recalculated and rounded for that precision, and are not foreign-exchange converted. An explicit template currency override remains fixed.
    6. Each automatic-email template retains a last-confirmed delivery currency. If an inherited currency differs from that baseline, generation and issue continue but automatic email is suppressed, the Invoice/template show a **Currency changed — review required** state, and later occurrences remain issue-only until confirmation. A reviewed Invoice's successful manual send, after provider acceptance, confirms that Invoice's currency as the new baseline. The first eligible generation establishes the initial baseline, and explicit template currency overrides do not trigger this gate.
    7. Already-generated Invoices remain unchanged. An explicit **Reapply current defaults** action remains the only way to refresh source-derived values on an ordinary Quote or Invoice, with a preview of affected fields.
- Downstream specification: Relational schema and snapshot-boundary section.

### RA-004 — Mutable issued documents do not define PDF/public-history behavior

- Severity: High
- Status: Approved resolution
- Evidence: Sent quotes and Issued invoices remain editable and v1 has no document revision system. PDFs must render persisted values, but the brief does not say what happens to a previously generated PDF after a significant edit.
- Risk: The editor, current PDF, public page, email history, and a PDF already attached to an email may represent different document contents without an explicit rule.
- Approved resolution: v1 has one mutable current document, not revisions. A successful significant edit recalculates the current stored snapshots, records understandable audit before/after data, invalidates the current generated-PDF cache, and causes the next view/download/send to render a new PDF from the edited document. The current public page shows the edited document. Previously delivered email bodies or attachments cannot be recalled and remain historical delivery artifacts; v1 does not retain a user-browsable document/PDF version history.
- Downstream specification: Quote/invoice state rules, relational schema, and later PDF/email implementation.

### RA-005 — Zero-total document and payment-state behavior is undefined

- Severity: High
- Status: Resolved
- Evidence: Explicit zero prices and 100% discounts are allowed, but invoice payment state is only described in terms of invoice total, net paid, and outstanding balance.
- Risk: A zero-total Issued invoice may alternate between Unpaid and Paid across list, reminder, public, and transaction code paths.
- Approved resolution: Allow a Quote or Invoice with at least one otherwise valid line whose final total is zero. An Issued zero-total Invoice is derived as Paid immediately, is never Overdue, requires no payment, receives no payment reminders, and rejects every Payment, Refund, and Adjustment row. A Draft zero-total Invoice has no customer-facing payment state until issue.
- Evidence: [Approved financial/document state rules](document-and-financial-state.md#zero-total-financial-rows) and calculation tests required by the tracker.

### RA-006 — Transaction direction, refund capacity, and adjustment bounds need one formula

- Severity: High
- Status: Resolved
- Evidence: Transactions may be Payment, Refund, or Adjustment; adjustments may be positive or negative; all stored amounts are non-negative. The phrase “amount available to refund” does not state whether a positive adjustment represents refundable cash.
- Risk: Different code paths could calculate net paid or refundable cash differently, permitting negative balances or refunding money that was never recorded as received.
- Approved direction and downstream requirement:
    1. Store `type = PAYMENT | REFUND | ADJUSTMENT`; Adjustment additionally stores `direction = INCREASE_PAID | DECREASE_PAID`. Other types have no adjustment direction.
    2. `net_paid = payments + increase_adjustments − refunds − decrease_adjustments`.
    3. `cash_available_to_refund = payments − refunds`; positive adjustments do not create refundable cash.
    4. A Refund cannot exceed actual recorded cash still available to refund, regardless of positive adjustments.
    5. Every executable amount is strictly positive, mutations are allowed only while the Invoice is Issued, and every create/edit/delete must preserve the complete ledger invariants and operation-specific limits.
- Evidence: [Approved complete-ledger and transaction rules](document-and-financial-state.md#complete-ledger-invariants); the relational schema must encode the required constraints.

### RA-007 — Transaction mutation and cancellation finality were incomplete

- Severity: High
- Status: Resolved, including approved role assignment
- Evidence: The brief requires audited payment changes/deletions and says cancellation preserves existing transaction history, but it does not state whether a Cancelled invoice can be restored or whether its existing transactions can still be edited/deleted.
- Risk: A cancelled invoice could change financially after cancellation, or cancellation could be reversed inconsistently after reminder and public states were suppressed.
- Approved resolution: A Cancelled Invoice may be reopened and changed in v1; cancellation is not permanently terminal. While Cancelled, it preserves existing transactions/audit history and keeps transactions read-only. Reopening returns it to Issued, preserves its identity/public view, sends no automatic email, restores validated transaction mutation eligibility, and schedules only currently relevant unsent reminders without replaying history.
- Evidence: [Approved reopening rules](document-and-financial-state.md#reopening-a-cancelled-invoice) and [approved Owner/Admin/Member permission matrix](role-permission-matrix.md). Members may cancel/reopen under the approved guards; Owner/Admin-only Adjustments create the documented escalation path when cancellation first requires a corrective Adjustment.

### RA-008 — Quote-to-invoice eligibility and unlinking are unspecified

- Severity: High
- Status: Approved resolution
- Evidence: A Quote may create multiple Invoices and its currency becomes locked while linked Invoices exist, “unless those links are removed through a valid workflow.” Neither eligible Quote states nor that unlink workflow are defined.
- Risk: Quote allocations and provenance may change after invoice issue, or an agent may impose an unnecessarily rigid conversion rule.
- Approved resolution: Accepted is the normal conversion state. Owner/Admin may explicitly confirm conversion from Draft, Sent, or Expired as an intentional override; Rejected must first be moved to another allowed state. A Quote-derived Invoice may be unlinked only while it remains Draft and has never been sent/issued, exposed through a public-link share, or associated with any financial transaction. Unlinking requires confirmation and audit, leaves the copied Invoice data intact as an independent Draft, and immediately recalculates the Quote's invoiced/remaining amounts. Once any disqualifying activity occurs—or the Invoice is Issued or Cancelled—the provenance link is immutable. v1 does not support attaching an independently created Invoice to a Quote after creation.
- Downstream specification: Quote/invoice state rules, permission matrix, and relational schema.

### RA-009 — Payment terms, quote validity, and date validation lack a v1 shape

- Severity: High
- Status: Resolved
- Evidence: Payment terms and quote validity derive customer-visible dates, but their stored configuration is not specified. Due and valid-until dates remain editable, without explicit lower bounds.
- Risk: Schema and UI may invent incompatible term types such as free text, fixed dates, or calendar rules.
- Approved resolution: v1 structured payment terms and quote validity are non-negative integer calendar-day offsets from the issue date. The resolved due date/valid-until date is stored and remains manually editable. A valid Issued Invoice requires `due_date ≥ issue_date`; a sendable Quote requires `valid_until ≥ issue_date`. Drafts may temporarily be incomplete, but may not save a populated end date earlier than a populated issue date. Terms & Conditions and notes remain unrelated text fields. There is no arbitrary business maximum offset; persistence uses the derived `0..3,652,058` full-date-range envelope, and the resulting date must remain within `0001-01-01` through `9999-12-31` inclusive. Terms & Conditions are bounded at 20,000 characters and Quote/Invoice notes at 5,000 characters each across defaults, overrides, and snapshots.
- Evidence: [Approved date-range rule](document-and-financial-state.md#date-range-limit); the relational schema must encode the same bound.

### RA-010 — Recurring-template activation and schedule-edit behavior are incomplete

- Severity: High
- Status: Approved resolution
- Evidence: Only Active templates execute and pause time is not backfilled, but the brief does not define activation with a past start date, schedule edits while Active, or whether Completed is reversible.
- Risk: Activating or editing a template could unexpectedly create historical invoices or duplicate pending occurrence records.
- Approved resolution:
    1. Activating a Draft with a past start date schedules the first occurrence on or after activation; it does not backfill time when the template was not Active.
    2. Editing recurrence, start/end date, maximum count, timezone-sensitive schedule inputs, customer, currency, or lines on an Active template requires confirmation and affects only not-yet-materialized occurrences.
    3. Pending dispatch rows are replaced transactionally; completed/failed historical occurrences remain unchanged.
    4. Completed is terminal in v1. To continue, the user duplicates the template into a new Draft rather than reopening occurrence history.
- Downstream specification: Recurring-template state rules and relational schema.

### RA-011 — Archive, hard-delete, and provenance references need consistent constraints

- Severity: High
- Status: Resolved
- Evidence: Customers, products/services, and companies with history are normally archived, while users must ultimately be able to delete their data. Documents must retain snapshots and linked transactions block Invoice deletion.
- Risk: Cascading foreign keys could erase history, while unrestricted references could make user-data deletion impossible.
- Required schema direction from existing approved rules:
    - Documents/templates keep stable snapshots plus source/provenance identifiers where useful.
    - Referenced Customers, Products/Services, tax presets, and bank accounts are archived by default and hard-deleted only after blocking dependent records are removed through valid workflows.
    - Tenant-business foreign keys default to restrictive deletion, not silent cascade, except within an explicitly confirmed whole-Company erasure workflow.
    - Invoice deletion remains impossible while any transaction exists; Cancelled Invoices with transactions remain retained.
    - A Quote without linked Invoices and an Invoice without transactions may be deleted in any lifecycle state after the approved warnings, cleanup, and minimal audit-tombstone behavior.
    - The schema document must enumerate every archive/delete rule and define the final Company/account erasure order without weakening required audit and transaction constraints before that explicit erasure.
- Evidence: The approved [relational schema and snapshot boundaries](relational-schema-and-snapshots.md#14-delete-archive-and-retention-graph) define the restrictive dependency graph, authorized document cleanup, minimal tombstones, and Company/User/Account erasure ordering without changing product behavior.

### RA-012 — Role authorization is intentionally incomplete

- Severity: Critical
- Status: Resolved
- Evidence: Only a few role hints exist: every Company has one Owner; invitations assign Admin/Member; Owner/Admin manage Products by default; transfer is ownership-only.
- Risk: Agent-written Policies, navigation, buttons, jobs, and destructive actions could disagree or accidentally grant Members financial/settings authority.
- Approved resolution: The fixed Owner/Admin/Member model assigns every Company action across settings, members/invitations, transfer, Customers, Products & Services, documents, numbering, transactions, recurring templates, public links, email, reminders, audit, archive/delete, and counter realignment. Laravel Policies/application actions and UI visibility derive from the same named abilities; RLS remains tenant isolation rather than role authorization.
- Evidence: [Approved Owner/Admin/Member Permission Matrix](role-permission-matrix.md).

## Resolved high-risk areas

These risks require careful implementation and tests but do not need another product decision before implementation.

| Area                            | Resolution already approved                                                                                                                           | Evidence                                                  |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| Cross-company disclosure        | Laravel membership/Policies plus forced PostgreSQL RLS, restricted runtime role, same-company foreign keys, and transaction-local tenant context      | [Tenant isolation](tenant-isolation.md)                   |
| Financial precision             | PostgreSQL exact decimals, stored currency-precision snapshots, `brick/math` authority, `HALF_UP` step rounding, string transport, and golden vectors | [Calculation and rounding](calculation-and-rounding.md)   |
| Concurrent numbering            | Per-company/type/period counter row locked with `SELECT ... FOR UPDATE`, persisted idempotent Draft creation, and explicit realignment                | [Numbering and concurrency](numbering-and-concurrency.md) |
| Duplicate recurrence/reminders  | Local-calendar schedules, DST rules, stable occurrence keys, uniqueness constraints, transactional claims, bounded retries, and downtime rules        | [Scheduling and jobs](scheduling-and-jobs.md)             |
| Domain identifiers              | Native UUIDv7 domain keys and native UUID foreign keys, without treating UUIDs as secrets                                                             | [Identifier policy](identifier-policy.md)                 |
| Authentication scope growth     | Fortify baseline retained; WorkOS/Teams and TOTP/recovery-code scope explicitly excluded from v1                                                      | [Application architecture](application-architecture.md)   |
| Translation drift               | Laravel is the only authored translation source; React receives small resolved translation bags through Inertia                                       | [Application architecture](application-architecture.md)   |
| Stack fragmentation             | One Laravel modular monolith, one React/Inertia interface, one PostgreSQL database, and no initial Docker/Redis/microservices/separate API            | [Application architecture](application-architecture.md)   |
| Purchase/accounting scope creep | Customer PO value remains document metadata; purchasing, inventory, expenses, ledger, credit notes, and compliance engines are excluded               | [Master build brief](../product/master-build-brief.md)    |

## Accepted product risks that must remain visible

| Risk                                                                    | Approved stance                                                                                                                                        | Required mitigation                                                                                                                                                                                                                                                                                                 |
| ----------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Issued documents and numbers are flexible rather than legally immutable | Invumo does not claim jurisdiction-specific accounting or numbering compliance in v1                                                                   | Clear product language, warnings, authorization, and detailed audit history                                                                                                                                                                                                                                         |
| Manual duplicate document numbers are allowed                           | Duplicate is permitted after explicit confirmation                                                                                                     | Prominent duplicate warning, indexed lookup, permission check, and audit event                                                                                                                                                                                                                                      |
| Public quote identity is self-asserted                                  | Name/email attribution is not an electronic signature or customer account                                                                              | Unpredictable link, rate limiting, replay protection, timestamps, and careful wording                                                                                                                                                                                                                               |
| Direct production development before launch                             | Temporarily allowed only while Invumo has no real users; no hosted development/staging environment or repeatable deployment automation is required yet | Source control and relevant automated checks during development; externally managed rollback, off-server backup/restore, monitoring, and alerts verified before public launch; separate development/production environments and repeatable releases before real-user dependency makes the temporary workflow unsafe |
| One queue worker initially                                              | Low infrastructure overhead is preferred over early horizontal scaling                                                                                 | Queue age/failure monitoring and a measured threshold for adding workers or separating queues                                                                                                                                                                                                                       |

## Just-in-time risks

The canonical tracker owns these gates. This assessment confirms that none required changing the approved core relational model and that each remains due only before its named implementation boundary.

| Area                                 | Current status                                                                | Remaining risk at its gate                                                                                                                                                                                                         |
| ------------------------------------ | ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Routes/navigation/editor composition | Complete in the approved composition document                                 | Preserve the approved route, list, and shared-editor contracts during shell implementation                                                                                                                                         |
| Upload/storage                       | Complete — Phase 1                                                            | Apply the approved private Company-asset boundary, controlled serving, immutable replacement/cleanup lifecycle, and local-to-S3 migration contract from [`uploads-and-storage.md`](uploads-and-storage.md)                            |
| PDF renderer                         | Complete — Phase 5                                                            | Dompdf 3.1.6 passed the pure-PHP proof for Romanian diacritics, embedded Atkinson fonts, long-table wrapping/repeated headings, multi-page breaks, raster logos, and resolved brand colour                                           |
| Public tokens                        | Open — Phase 8                                                                | Finalize token entropy/hash, eligibility lookup, expiry/revocation/regeneration, rate limits, and RLS bootstrap before public access                                                                                               |
| ZeptoMail/document webhooks          | Foundational account-email SMTP complete; document integration open — Phase 9 | Select SMTP reuse or API delivery and finalize webhook authentication, event ordering, idempotency, safe payload retention, and failure mapping before document email                                                              |
| Hosted runtime                       | Complete for Phase 1                                                          | Preserve private secrets, restricted roles, health checks, isolated tests, and unprivileged worker/scheduler operation                                                                                                             |
| Public-launch operations             | External ownership confirmed; verification due — Phase 12                     | Verify external rollback, off-server backup/restore, monitoring, and alerts; introduce separate development/production environments and repeatable releases before real-user dependency makes direct-production development unsafe |

## Verification obligations carried forward

Later implementation must continue to prove:

- Every tenant-owned relationship is same-company and default-deny under the restricted database role.
- Every financial transition uses one authoritative decimal/state service and revalidates the complete resulting balance.
- Saved editor, current public page, generated PDF, and email summary agree on current persisted document values.
- Idempotency is enforced by database state, not only by queue or browser behavior.
- Source edits never silently mutate an already-created Quote, Invoice, or recurring-template line snapshot. Recurring generation deliberately refreshes every inherited Customer field for a new Invoice while preserving explicit overrides; it never rewrites an already-generated Invoice.
- An inherited recurring currency change cannot auto-email unattended: the review latch suppresses delivery until a reviewed Invoice is successfully sent manually and establishes the new confirmed currency.
- Audit records identify actor type, action, target, time, and understandable before/after values for significant changes.
- English and Romanian key coverage, placeholders, and representative plurals are verified without a second authored catalog.
- Archive/delete/erasure workflows cannot bypass financial-history or tenant-integrity constraints accidentally.

## Approval outcome

The owner approved every product resolution in this assessment on 2026-08-22. RA-001's documentation correction has been applied, RA-011 is satisfied by the approved relational schema, and RA-012 is satisfied by the approved permission matrix.

The exact financial/document state specification, complete permission matrix, and relational schema/snapshot-boundary specification are approved. The canonical development tracker records Phase 0 as complete.
