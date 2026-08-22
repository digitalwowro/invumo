# Quote, Invoice, and Financial State Specification

Status: Draft for owner review
Last updated: 2026-08-22

This document defines the exact v1 state model for Quotes, Invoices, payments, refunds, adjustments, overdue behavior, cancellation, reopening, reminders, public access, mutable current documents, and destructive actions. It translates the approved [master build brief](../product/master-build-brief.md), [domain rules](../product/domain-rules.md), [calculation specification](calculation-and-rounding.md), [scheduling specification](scheduling-and-jobs.md), and [requirements assessment](requirements-risk-assessment.md) into one implementation-facing state contract.

Rules marked **Approved** already follow an explicit owner decision. Rules marked **Proposed — owner approval required** are recommendations only. This document and its Phase 0 tracker task remain incomplete until every proposed rule is approved or changed.

## 1. Modeling principles

### Approved

- Quote lifecycle, Invoice lifecycle, Invoice payment state, and overdue state are distinct concepts; one overloaded status column must not represent all of them.
- Financial state is derived from the authoritative Invoice total and valid linked transaction rows. It is never accepted from the browser as authoritative.
- Laravel performs every transition through named application actions. Each action authorizes first, establishes the Company RLS context, locks the affected aggregate, validates the complete resulting state, writes business/audit rows, and queues external work only after commit.
- Automatic jobs, browser retries, provider callbacks, and public actions use stable idempotency keys. A repeated request returns or observes the original result rather than creating another state change.
- Every significant edit records understandable audit before/after data. Audit history is not a document revision system.
- A successful edit updates the one current persisted document, public representation, and regenerated PDF. Already-delivered email bodies and attachments remain unchanged historical artifacts.
- Flexible but internally valid actions should use confirmation, warning, and audit rather than arbitrary blocking.

### Proposed — owner approval required: stale-editor protection

Quotes and Invoices use optimistic concurrency through a monotonically increasing version. A save based on an older version is rejected with a clear reload/review message; Invumo never silently overwrites another tab's or user's newer edit. Financial transitions additionally lock the Invoice and its transaction aggregate inside the database transaction.

## 2. Common document validity

### Approved

A sendable Quote and an issuable Invoice require:

- An authorized Company and Customer snapshot
- A document number
- Issue date
- Valid-until date for a Quote or due date for an Invoice
- Currency and stored currency precision
- Document language
- At least one valid billable line
- A deterministic total that fits the approved decimal storage envelope

The valid-until/due date cannot precede the issue date. Drafts may temporarily omit required values, but if both dates are present they must already satisfy that ordering.

Payment terms and Quote validity are non-negative whole calendar-day offsets. The resolved date is stored and remains editable.

### Proposed — owner approval required: date-range limit

v1 has no arbitrary maximum number of days. Validation rejects only a negative offset or a resulting date outside the inclusive application date range `0001-01-01` through `9999-12-31`. This four-digit-year range is used consistently by PostgreSQL constraints, Laravel date objects, JSON/Inertia serialization, browser inputs, and rendered documents; the relational-schema document must record the same bound.

## 3. Quote state model

### Stored and derived state

Store one Quote lifecycle value:

- `DRAFT`
- `SENT`
- `ACCEPTED`
- `REJECTED`

`EXPIRED` is derived, not stored:

```text
is_expired = company_local_date > valid_until
             AND lifecycle_state NOT IN (ACCEPTED, REJECTED)
```

Customer-facing status precedence is Accepted or Rejected first, then Expired, then Draft or Sent. Extending `valid_until` can remove Expired without inventing a second transition.

### Approved transitions

| Action | Preconditions | Result | External/history effects |
| --- | --- | --- | --- |
| Create | Authorized Company context | Persist incomplete `DRAFT` with an allocated number | Audit creation; idempotent Draft creation |
| Send a Draft | Complete, valid, non-expired Quote; valid recipient/link | Provider acceptance changes `DRAFT → SENT` | Immediate provider failure leaves Draft; later delivery failure leaves Sent; record every attempt |
| Resend | Complete existing Quote | Lifecycle unchanged | New immutable email attempt/artifact |
| Public Accept | Valid public action under the proposed public-state rule below | `SENT → ACCEPTED` | Required name/email, timestamp, idempotency, audit |
| Public Reject | Valid public action under the proposed public-state rule below | `SENT → REJECTED` | Required name/email, timestamp, idempotency, audit |
| Edit | Any non-deleted lifecycle state; field invariants pass | Lifecycle does not change automatically | Audit significant changes; invalidate current PDF cache |
| Derive expiry | Current Company-local date passes `valid_until` | Display/action eligibility becomes Expired | No stored lifecycle mutation required |
| Convert to Invoice | Accepted normally; Owner/Admin confirmation for Draft, Sent, or Expired; never Rejected | Create linked Draft Invoice | Copy approved snapshots; audit provenance |
| Unlink derived Invoice | Invoice remains Draft and has never been issued/sent, publicly shared, or associated with a transaction | Invoice becomes independent Draft | Confirm/audit; preserve copied Invoice data; recalculate Quote allocation |

Repeated identical public decisions are idempotent. A replay must not create another decision record or audit effect.

### Proposed — owner approval required: Quote corrections and public eligibility

- Public Accept/Reject is allowed only while the Quote's stored lifecycle is `SENT` and it is not Expired. An opposite public decision after acceptance/rejection is rejected and requires an internal correction.
- An authorized internal user may correct the stored lifecycle to Draft, Sent, Accepted, or Rejected in either direction after confirmation and with a required reason/audit record. Expired is never selected directly because it remains derived.
- Sending or resending an Accepted or Rejected Quote is allowed after a warning and does not change its lifecycle; the current status is clearly shown in the composer.

### Quote editing and allocation

- Quotes remain editable in every lifecycle state.
- Editing never erases decision or send history.
- Quote currency cannot change while linked Invoices remain.
- Invoiced amount is the sum of every non-Cancelled linked Draft or Issued Invoice total.
- Remaining amount is Quote total minus invoiced amount and may be negative.
- Exceeding the Quote total warns but does not block.
- Once a linked Invoice is Issued, Cancelled, sent, publicly shared, or associated with a financial transaction, its Quote provenance is permanent.

## 4. Invoice lifecycle model

Store exactly one lifecycle value:

- `DRAFT`
- `ISSUED`
- `CANCELLED`

Payment state and Overdue remain derived as specified below.

### Approved transitions

| Action | Preconditions | Result | External/history effects |
| --- | --- | --- | --- |
| Create | Authorized Company context | Persist incomplete `DRAFT` with allocated number | Audit creation; idempotent Draft creation |
| Issue without email | Complete valid Draft | `DRAFT → ISSUED` | Materialize reminder schedule; generate current PDF as needed; audit |
| Send a Draft | Complete valid Draft and send inputs | Commit `DRAFT → ISSUED` before delivery | Dispatch failure never reverts issue; record retryable email failure |
| Resend Issued | Valid recipient/link and no delivery-safety block | Remains `ISSUED` | New immutable email attempt/artifact |
| Edit Draft | Result may remain incomplete but all populated fields are valid | Remains `DRAFT` | Recalculate current values |
| Edit Issued | Complete result; total not below net paid; currency fixed while transactions exist | Remains `ISSUED` | Recalculate state/reminders; audit; invalidate current PDF cache |
| Cancel | `ISSUED`, net paid exactly zero | `ISSUED → CANCELLED` | Suppress pending reminders; block new transactions; preserve history |

Sending is an external effect, not a lifecycle state beyond `ISSUED`. Provider Delivered/Opened/Failed values belong to immutable delivery attempts rather than the Invoice lifecycle.

### Proposed — owner approval required: reopening a Cancelled Invoice

- Reopen always changes `CANCELLED → ISSUED`; it never returns a previously issued number to Draft.
- The number, issue date, existing transactions, audit history, and public-link identity remain attached.
- While Cancelled, existing transactions are read-only. Reopening restores their normal edit/delete eligibility, subject to the complete ledger invariants.
- Reopening does not send email automatically.
- A valid, non-revoked public link remains viewable while Cancelled and clearly displays Cancelled; after reopening it displays the current Issued/payment state.
- Reopening recalculates pending reminders from the current due date and balance. It never recreates reminders already sent. Past before-due reminders become stale; at most the newest currently eligible after-due reminder is scheduled for the next Company automation time.
- Reopening requires confirmation, a reason, authorization defined by the permission matrix, and an audit record.

## 5. Derived Invoice state

For valid transaction rows, define:

```text
payment_sum             = sum(PAYMENT.amount)
refund_sum              = sum(REFUND.amount)
increase_adjustment_sum = sum(ADJUSTMENT.amount where direction = INCREASE_PAID)
decrease_adjustment_sum = sum(ADJUSTMENT.amount where direction = DECREASE_PAID)

net_paid = payment_sum
           + increase_adjustment_sum
           - refund_sum
           - decrease_adjustment_sum

cash_available_to_refund = payment_sum - refund_sum
outstanding              = invoice_total - net_paid
```

### Proposed — owner approval required: complete-ledger invariants

Every financial mutation must leave the complete Invoice ledger in this state:

```text
0 <= net_paid <= invoice_total
0 <= cash_available_to_refund
0 <= outstanding
```

### Payment state

| Lifecycle/amount condition | Derived payment state |
| --- | --- |
| Draft | None; do not present a customer payment status |
| Issued and `invoice_total = 0` | Paid immediately |
| Issued and `invoice_total > 0` and `net_paid = 0` | Unpaid |
| Issued and `0 < net_paid < invoice_total` | Partially Paid |
| Issued and `net_paid = invoice_total` | Paid |
| Cancelled | Financial calculations remain available internally, but the primary customer-facing lifecycle is Cancelled |

### Overdue

```text
is_overdue = lifecycle_state = ISSUED
             AND outstanding > 0
             AND due_date < current_company_local_date
```

Partially Paid and Overdue may both be true. Paid, zero-total, Draft, and Cancelled Invoices are never Overdue.

## 6. Transaction state rules

Transaction kinds are:

- `PAYMENT`
- `REFUND`
- `ADJUSTMENT` with `INCREASE_PAID` or `DECREASE_PAID`

The already-approved storage direction is a non-negative amount expressed in the linked Invoice currency/precision, with financial direction represented explicitly. Adjustment reason and audit data are required.

### Proposed — owner approval required: operation limits

Every executable Payment, Refund, or Adjustment uses a strictly positive amount; zero-amount transaction rows are invalid. Together with the complete-ledger invariants, apply these operation-specific limits:

| Operation | Maximum/required result |
| --- | --- |
| Create Payment | Amount cannot exceed current outstanding |
| Create Refund | Amount cannot exceed both current cash available and current net paid |
| Create Increase-Paid Adjustment | Amount cannot exceed current outstanding |
| Create Decrease-Paid Adjustment | Amount cannot exceed current net paid |
| Edit any transaction | Recompute the complete ledger as though the edited row already had its new values; every invariant must pass |
| Delete any transaction | Recompute the complete ledger without that row; every invariant must pass |

A positive adjustment changes balance but never refundable cash. A Refund can make a previously Paid Invoice Partially Paid or Unpaid again. Editing or deleting a Payment is rejected if retained Refunds or other rows would make the resulting ledger invalid.

### Proposed — owner approval required: transaction eligibility and dates

- Financial rows may be created, edited, or deleted only while the Invoice is `ISSUED`. Draft and Cancelled Invoices reject all financial mutations.
- A transaction date may precede the Invoice issue date to support advance or backfilled records, but it cannot be later than the current Company-local date.
- A previously sent payment-received email does not freeze the transaction. A later edit/delete remains possible after warning, full invariant validation, and audit; the delivered email remains an immutable historical artifact.

### Proposed — owner approval required: zero-total financial rows

A zero-total Invoice rejects Payment, Refund, and Adjustment rows because it has neither outstanding balance nor refundable cash. This rule is enforced explicitly even though the complete-ledger limits would also prevent any positive row from being added.

## 7. Financial edits to an Issued Invoice

- Recalculate all lines and totals using the approved exact-decimal service.
- Reject a result where `invoice_total < net_paid`.
- Currency cannot change while any transaction row exists.
- Increasing total may move Paid to Partially Paid/Unpaid and may resume eligible reminders.
- Reducing total to exactly net paid derives Paid and suppresses pending reminders.
- Reducing total to zero is valid only when net paid is also zero; the Invoice becomes Paid under the zero-total rule.
- Customer, dates, lines, tax, notes, Terms & Conditions, bank snapshot, and metadata remain editable when their resulting state is valid.
- Significant edits require confirmation where customer-visible meaning changes, audit before/after values, and current PDF invalidation.

## 8. Reminder state reactions

### Approved

- Issue materializes reminders from the resolved Invoice rules.
- Changing due date or reminder rules replaces only pending instances transactionally; sent/failed history remains.
- Paid, zero-total, and Cancelled Invoices suppress pending reminders.
- Partially Paid and Unpaid Invoices remain eligible while outstanding is positive.
- Every send rechecks lifecycle, balance, due date, recipients, public-link eligibility, and idempotency immediately before delivery.

### Proposed — owner approval required as part of the reopening/transaction rules

When an edit, Refund, transaction correction, or reopening makes an Invoice collectible again:

- Do not replay already-sent reminders.
- Do not backfill before-due reminders whose commercial moment passed.
- If the Invoice is already overdue, schedule only the newest currently eligible after-due reminder for the next Company automation time; older eligible instances are superseded.
- If the due date is still in the future, schedule only future reminder instances normally.

## 9. Public page and email behavior

- A valid Quote/Invoice public link always renders the current persisted document and current PDF.
- Link expiry/revocation is independent from document lifecycle. Explicit revocation remains revoked until a user re-enables public access.
- Expired Quotes remain viewable through a technically valid link but cannot receive public Accept/Reject actions.
- Under the proposed Quote rule, only non-expired Sent Quotes may receive public decisions.
- Under the proposed cancellation rule, Cancelled Invoices remain viewable/downloadable and visibly Cancelled; they expose no financial action.
- Editing does not recall or mutate previously delivered email bodies or attachments.
- Resending creates another immutable delivery attempt against current persisted values.
- Recurring inherited-currency review suppression remains authoritative over automatic email until a provider-accepted manual send clears the latch.

## 10. Deletion and retention

### Already approved

- An Invoice with any linked Payment, Refund, or Adjustment row cannot be permanently deleted in any lifecycle state.
- A Quote link cannot be removed from an Invoice after its approved Draft-only unused window.
- Cancellation never deletes an Invoice, transaction, email history, or audit history.
- Customer/Product/Tax/Bank source deletion never rewrites document snapshots.

### Proposed — owner approval required: flexible document deletion

- A Quote may be permanently deleted in any lifecycle state only when it has no linked Invoice.
- An Invoice may be permanently deleted in Draft, Issued, or Cancelled only when it has no transaction rows.
- Sent/decided/issued history causes a stronger warning but does not independently block deletion in v1.
- Deletion transactionally revokes public access, suppresses pending reminders/jobs, removes or safely detaches dependent delivery records according to the schema retention plan, and writes a minimal audit tombstone that identifies the deletion without retaining a complete customer/document copy.
- Deletion never rewinds the automatic number counter or silently reuses the removed number.
- No cascade from Customer, Quote, Company settings, or another ordinary parent operation may bypass these guards.

The relational-schema document must define exact foreign keys, archive behavior, delivery/audit retention, and whole-Company/user-erasure ordering.

## 11. Audit requirements

Record actor type, actor identifier where available, Company, action, target, timestamp, request/idempotency identity, reason where required, and understandable before/after values for:

- Quote creation, sending, lifecycle correction, public decision, significant edit, conversion, unlink, and deletion
- Invoice creation, issue, send/resend, significant edit, cancellation, reopening, and deletion
- Transaction create, edit, and delete
- Payment-state and reminder effects caused by financial changes
- Public-link generation, revocation, regeneration, and lifecycle-blocked actions
- Currency-review suppression and confirmation
- Duplicate-number confirmation and counter realignment

Rejected operations log safe operational context but must not write a successful business audit event.

## 12. Concurrency and transaction boundaries

- Lock the Invoice aggregate before transaction create/edit/delete, financial document edits, cancellation, or reopening.
- Recompute totals and the complete ledger inside the same transaction; never validate against a stale balance cached in the browser.
- Quote conversion/unlink locks the Quote and affected Invoice/provenance rows before recalculating allocation.
- State transition and audit insertion commit together.
- Reminder replacement/suppression commits with the state change that caused it.
- Email/PDF/provider work is dispatched after commit through the approved queue/outbox boundary.
- Public decisions, Draft creation, Quote conversion, occurrence generation, transaction mutation requests, and provider callbacks use stable idempotency identities appropriate to their action.

## 13. Owner decisions required before approval

1. Approve or change optimistic concurrency: reject a stale editor save rather than silently overwriting a newer edit.
2. Approve or change the date rule: no arbitrary day-offset maximum; require only a non-negative offset whose resolved date stays between `0001-01-01` and `9999-12-31`.
3. Approve or change Quote corrections: public decisions only on non-expired Sent Quotes, with authorized internal status correction in either direction.
4. Approve or change Invoice reopening: return to Issued, keep transactions read-only while Cancelled, retain the public view, send no automatic email, and recalculate only currently eligible reminders.
5. Approve or change the complete financial-entry rules: require a strictly positive amount, enforce the proposed whole-ledger and operation-specific bounds, permit mutations only on Issued Invoices, allow pre-issue/backfilled dates but no future dates, and allow audited corrections even after a receipt email.
6. Approve or change zero-total behavior: reject every Payment, Refund, and Adjustment row.
7. Approve or change flexible deletion: allow any Quote without linked Invoices and any Invoice without transactions to be deleted after the required warning/cleanup/audit behavior, regardless of prior sending or lifecycle.

After these decisions, update the product/domain rules and durable decision log, mark this specification **Approved**, and complete only its corresponding Phase 0 tracker task. The permission matrix and relational schema remain separate approval gates.
