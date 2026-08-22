# Invumo Core Domain Rules

Status: Approved product rules  
Last updated: 2026-08-22

This document is a concise implementation-facing companion to the [master build brief](master-build-brief.md). If a future implementation decision changes one of these rules, update both documents and record the decision in the memory repository.

## Tenant and ownership boundaries

- An Account belongs to an account owner and carries plan entitlements.
- A Company is an independent tenant containing all company business data and settings.
- A CompanyMembership connects a user and company with Owner, Admin, or Member role.
- One account owner may manage multiple companies.
- A user may belong to companies owned by different accounts.
- Each company has exactly one owning Account and one Owner membership held by that Account's owner; Admin and Member memberships may be multiple.
- Transferring a company to a different account must not rewrite its customers, documents, transactions, or audit history.
- Transfer validates destination-plan entitlements, makes the destination account owner the sole company Owner, and retains the former Owner as Admin by default unless explicitly removed in the confirmed transfer.
- Other existing members remain attached during transfer by default.
- Every server-side business-data operation must verify company scope, membership, and permission.
- Never trust a client-provided company identifier without server-side authorization.
- Tenant-owned business tables also use forced PostgreSQL Row-Level Security. Every tenant-owned row, including child rows, carries `company_id`; same-company composite foreign keys prevent cross-company parent/child links.
- The Laravel runtime database role is not the schema owner and cannot bypass RLS. Tenant context is set transaction-locally only after authorization, is required by queue jobs, and denies access when absent.
- Control-plane, public-token, and scheduler bootstrap paths are narrow and must never grant a general tenant-data bypass. The approved mechanism is defined in [`../architecture/tenant-isolation.md`](../architecture/tenant-isolation.md).
- Authentication covers registration, email verification, sign-in/out, password reset, and secure session invalidation.
- Company invitations are email-addressed, expiring, revocable, single-use, and company-bound; they assign Admin or Member, never Owner.
- Ownership transfer requires explicit confirmation and cannot be performed as an ordinary role change.

## Plan boundary

- Plans belong to the account, not individual companies.
- Free, Pro, and Enterprise are placeholder plan names.
- v1 needs an extensible entitlement model only.
- Billing, payment collection, and a plan-builder interface are excluded.

## Company identity and locale

- Store company addresses as address line 1, optional address line 2, city, state/province/region, postal code, and country.
- Support a user-defined tax registration label and identifier.
- Support a user-defined business registration label and number.
- Each company has an IANA timezone.
- Each company has one automation-local time, default `09:00`, used for recurring generation and reminders in v1.
- Store timestamps in UTC; interpret recurring schedules and other company-timed automation in the company timezone.
- Canonical recurrence/reminder rules remain company-local calendar values; stored UTC run timestamps are derived execution indexes.
- Document date formatting derives from the selected document language/locale. v1 has no separate manual date-format setting.

## Bank accounts

- A company may have multiple bank accounts.
- Bank account fields include label, bank name, account holder, IBAN/account number, SWIFT/BIC, optional currency, and optional local routing details.
- A company may designate a default bank account; each quote or invoice may override it or omit bank details.
- A quote or invoice stores a snapshot of any bank details presented on the document.
- Editing, archiving, or deleting the source bank account must not rewrite an existing document snapshot.

## Customer selection and creation

- A quote, invoice, or recurring-invoice-template editor must support selecting an existing customer.
- The user may create a customer from the editor in a vertically scrollable modal containing the complete customer form without losing the in-progress document/template.
- After a successful modal save, close the modal and select the new customer automatically.
- Validation failure must retain both the customer form values and the in-progress document/template.

## Customer identity and defaults

- Customer type is `INDIVIDUAL` or `COMPANY`.
- Individual customers use first and last name.
- Company customers use a company/legal name and may have multiple contacts.
- Contacts may be designated as primary contact and billing contact/default recipient.
- Each customer has one structured billing/legal address: address line 1, optional address line 2, city, state/province/region, postal code, and country.
- Customer identity supports phone, an optional general/primary email, optional external reference/code, tax registration label and identifier, and business registration label and number. An Individual's primary email may be its default recipient; Company recipients normally resolve from contacts or an explicitly stored address.
- Customer defaults include currency, document language, payment terms, tax preset, billing recipient, CC recipients, BCC recipients, and PDF email-delivery mode.
- PDF email-delivery mode is secure link only or attach PDF.
- Internal customer notes are never rendered automatically on documents, public pages, or email.
- v1 excludes separate shipping/service addresses, customer tags, customer-specific manual date formats, and an ambiguous free-form legal-info field.
- A quote or invoice snapshots the customer identity, billing/legal address, and registration details used on that document.
- Editing or deleting the customer must not silently rewrite an existing document snapshot.

## Document customer reference / PO number

- A quote, invoice, or recurring-invoice template may have an optional customer reference / PO number.
- This document-level value is distinct from the customer's permanent external reference/code.
- Creating an invoice from a quote copies the quote value; generating an invoice from a recurring template copies the template value. Each destination snapshot remains independently editable.
- Include the field in relevant quote, invoice, and recurring-template searches and render it on PDFs and public pages when present.
- The field is metadata only. v1 has no Purchase Order entity, purchasing workflow, vendor approval, fulfilment state, or PO matching.

## Products & Services library

- v1 includes a lightweight, company-scoped Products & Services library for reusable line defaults; it is not inventory software.
- Each entry has a required name; optional description, internal code/SKU, default unit, default tax preset, and default period unit; optional default unit price with a required ISO currency; and active/archived state.
- A missing default price means “enter on the document” and is distinct from an explicit zero price.
- Quote, invoice, and recurring-invoice editors provide searchable selection of active entries while still allowing fully manual lines.
- Users may create a product/service inline from those editors without losing document progress; successful creation selects it automatically, and validation failures retain both forms.
- Selecting an entry copies applicable values onto the line. The line remains completely editable and financially authoritative.
- Product/service name and optional description initialize the customer-visible line description; price, unit, period unit, and tax initialize their corresponding line fields.
- Document and recurring-template lines are snapshots, not live catalog links. Editing or archiving a product/service never rewrites existing lines or invoices generated later from already-snapshotted recurring-template lines.
- Copy a default price only when its currency matches the document currency. On mismatch, copy non-price defaults and require manual price entry or confirmation; never perform FX conversion.
- Owner/Admin roles manage entries by default. Members may search and use active entries subject to the approved permission matrix.
- Archive a previously used entry rather than hard-deleting it by default.
- v1 excludes product URLs/customer-visible product links, inventory and stock movements, tags/categories, variants, bundles, supplier/purchasing data, cost/margin tracking, tiered/customer-specific price lists, product images, and catalog CSV import/export.

## Default resolution and snapshot timing

- Resolve company/customer defaults into stored draft fields when a quote, invoice, or recurring template is created.
- Resolve product/service defaults into a line when the entry is selected.
- Later source changes never propagate silently to an existing document or template.
- Changing the selected customer requires confirmation of the resulting identity/default/recipient changes and must not silently replace lines or unrelated manual edits.
- Reapplying current defaults is an explicit user action with a clear preview of what will change.

## Quote workflow

Statuses:

- Draft
- Sent
- Accepted
- Rejected
- Expired

Rules:

- Quotes remain editable after sending or acceptance.
- A quote stores company, customer snapshot, number, issue date, valid-until date, currency, document language, lines, Terms & Conditions, notes, optional customer reference / PO number, and any displayed bank-details snapshot.
- Sending requires all required fields and at least one valid billable line. A Draft quote becomes Sent after provider dispatch acceptance; immediate dispatch failure leaves it Draft and records the attempt, while later delivery failure does not revert it.
- Expired is derived after `valid_until` in the company timezone when the quote is neither Accepted nor Rejected.
- Expired public quotes cannot be accepted or rejected until an internal user extends validity or changes status.
- Editing a sent/accepted quote does not reset its status automatically; significant changes are audited.
- There is no quote versioning in v1.
- A quote may generate multiple invoices.
- Each generated invoice initially copies the quote's customer reference / PO number without creating a live link back to the quote.
- Linked invoices use the quote currency; there is no cross-currency allocation.
- Quote currency cannot change after linked invoices exist unless those links are removed through a valid workflow.
- Invoiced amount is the sum of non-Cancelled linked invoice totals, including Draft invoices; remaining amount is quote total minus invoiced amount and may be negative.
- Warn rather than block when generated invoices exceed the quoted amount.
- Default validity is 30 days, configurable by company and overridable per quote.
- Quote numbers are suggested automatically and manually overridable.

## Invoice workflow

State model:

- Stored lifecycle state: Draft, Issued, or Cancelled.
- Derived payment state: Unpaid, Partially Paid, or Paid.
- Derived Overdue flag: Issued, not Cancelled, positive outstanding balance, and due date earlier than the current company-local date.
- Partially Paid and Overdue may both be true and must both remain visible.

Rules:

- Invoices may be created from a quote or independently.
- An invoice stores company, customer snapshot, number, issue date, due date, currency, document language, lines, Terms & Conditions, notes, optional customer reference / PO number, and any displayed bank-details snapshot.
- Drafts may be incomplete; issue/send requires all required fields and at least one valid billable line.
- Sending a Draft invoice issues it before delivery. Dispatch failure leaves it Issued and records a retryable failed email attempt.
- Issued invoices remain editable, including financial fields.
- Significant edits after issue require understandable audit records, including appropriate before/after data.
- Financial edits recalculate balance and payment state. Do not reduce invoice total below net paid until the necessary refund or corrective adjustment is recorded.
- Invoice currency cannot change while valid payment/refund/adjustment transactions exist.
- Payment state is derived from the net total of valid payments, refunds, and explicit adjustments.
- Cancellation is allowed only when net paid is exactly zero. A positive net-paid amount requires a refund or corrective adjustment before cancellation.
- Cancellation suppresses pending reminders and blocks new payment, refund, and adjustment records while the invoice remains Cancelled.
- Cancellation retains the invoice, every existing linked transaction, and audit history. No invoice may be permanently deleted while any linked transaction records remain, regardless of lifecycle state.
- v1 does not enforce jurisdiction-specific invoice immutability or numbering law.

## Document numbering

- Suggest the next logical number based on the current relevant sequence.
- Allow manual number entry and renumbering.
- Permit deletion where the application allows it.
- Audit meaningful numbering changes and deletions.
- Warn whenever a number duplicates another non-deleted document in the same company and document type, while permitting an intentional override.
- Do not silently reuse a removed number without clear user intent.
- Keep numbering configurable without building an unnecessarily complex rules engine.
- Quote and invoice sequences are separate per company.
- v1 supports literal prefix/suffix, optional company-local year, one padded numeric component, and an explicit no-reset or annual-reset policy. A year in the format does not imply reset.
- Clicking New creates a persisted Draft. Automatic allocation and Draft insertion share one transaction and one idempotent creation key.
- Lock the relevant company/document-type/period counter row using `SELECT ... FOR UPDATE`, allocate the next unoccupied automatic candidate, insert the Draft, advance the counter, and commit.
- Manual duplicates remain possible after explicit warning. Manual numbering, renumbering, and deletion do not change the counter automatically.
- Counter continuation/realignment is an explicit authorized action under the same lock. Moving backwards requires a reuse warning and audit event.
- A manual override must not silently move a sequence backwards.
- The complete approved behavior and concurrency tests are defined in [`../architecture/numbering-and-concurrency.md`](../architecture/numbering-and-concurrency.md).

Example:

1. Existing invoice numbers are 1, 2, and 3.
2. The user deletes 2 and renames 3 to 2.
3. The next suggested number is 3.
4. The user may override the suggestion.

## Line inputs

Lines may be entered manually or initialized from the Products & Services library. Catalog selection only copies defaults and never limits later line editing.

Each quote or invoice line contains:

- Order/line number
- Customer-visible description, initialized from the selected product/service name and optional description when applicable
- Item price
- Quantity
- Optional unit
- Items subtotal
- Period quantity
- Period unit
- Items total
- Discount percentage
- Discount value
- Grand subtotal before tax
- Tax rate and tax value
- Final line total

Quantity and period quantity may be decimal where valid.

The unit describes what the quantity measures, such as hours, days, seats, pieces, or another user-entered label. It is independent of the recurring period unit used in price calculation.

Billable lines do not support interspersed free-form headings or text. Free-form document text may appear before or after the line table.

## Period semantics

Period units:

- `NONE` / N/A
- `MONTH`
- `YEAR`

Store period quantity and unit separately. Do not automatically convert years to months because the price may already represent the chosen period.

For N/A, the period multiplier is 1.

Examples:

- `€100 × 10 × N/A = €1,000`
- `€100 × 10 × 12 months = €12,000`
- `€100 × 10 × 1 year = €1,000`

## Line calculation

Let `p` be the document's stored currency precision. Laravel performs authoritative calculations with `brick/math` `BigDecimal` values and applies `BigDecimal::toScale($scale, RoundingMode::HALF_UP)` at every specified rounding step:

```text
items_subtotal = round(item_price × quantity, p, HALF_UP)

if period_unit = NONE:
    items_total = items_subtotal
else:
    items_total = round(items_subtotal × period_quantity, p, HALF_UP)

discount_value = round(items_total × discount_percentage ÷ 100, p, HALF_UP)
grand_subtotal = items_total − discount_value
tax_value = round(grand_subtotal × line_tax_rate ÷ 100, p, HALF_UP)
final_line_total = grand_subtotal + tax_value

document_subtotal = sum(line.grand_subtotal)
document_tax_total = sum(line.tax_value)
document_total = sum(line.final_line_total)
```

Rounding at each specified step, rather than only at the final total, is intentional so customer-visible PDF amounts reconcile when checked by hand. Document totals sum stored rounded line snapshots without further document-level rounding or residual-cent allocation.

Use PostgreSQL `numeric(30,8)` for unit prices and stored money, `numeric(20,6)` for quantities, `numeric(12,6)` percentage points for discount/tax rates, and a snapshotted currency precision from 0 through 8. Financial decimal values cross the browser boundary as strings; never use PHP or JavaScript binary floating point. The complete approved rules and test cases are defined in [`../architecture/calculation-and-rounding.md`](../architecture/calculation-and-rounding.md).

## Discounts

- Support a percentage discount per line.
- Calculate the discount value automatically.
- Do not support an overall document discount in v1.
- Do not silently produce ambiguous totals.

## Tax

- v1 supports tax per line only.
- Prices are entered tax-exclusive.
- Lines may use different tax rates.
- Company and customer settings may provide defaults; the user may override them on document lines.
- A selected product/service may also provide a default.
- No invoice-wide tax in v1.
- No country-specific tax engine or electronic-invoicing compliance claim.

Each company maintains reusable tax-rate presets:

- Name
- Percentage, including 0%
- Optional default designation
- Active or archived state

Users may add, edit, and archive presets. Referenced presets should be archived rather than hard-deleted.

Applying a preset snapshots its name and percentage onto the document line. Later preset changes must not alter existing documents.

Initial line-tax precedence is explicit line choice, selected product/service default, customer default, then company default. The copied line value is authoritative afterward.

Render the applied tax name and percentage together on customer-visible documents, for example `VAT 19%`. v1 has no separate tax-percentage visibility setting.

## Currency

- Each company has a default currency, and a customer may override it.
- Initial document-currency precedence is document choice, customer default, then company default.
- Currency may be overridden per quote or invoice.
- Currency decimal precision is user-configurable per currency from 0 through 8.
- Every quote, invoice, and recurring template snapshots its resolved currency precision. Quote conversion and recurring generation preserve that snapshot; later company-setting changes do not alter existing documents or templates.
- The company selects ISO-code or symbol display style independently from currency precision.
- Display style does not change the stored currency code or value.
- There is no FX conversion or exchange-rate service.
- Never combine amounts in different currencies into one total without a mathematically valid, explicit basis.
- The approved storage, calculation, rounding, and reconciliation rules are defined in [`../architecture/calculation-and-rounding.md`](../architecture/calculation-and-rounding.md).

## Identifiers

- Every domain entity uses a PostgreSQL-native UUIDv7 primary key generated by Laravel before insertion.
- Every domain foreign key, including every tenant-owned `company_id`, uses the native PostgreSQL `uuid` type.
- Framework infrastructure tables and identity-free pure join tables have only the explicit exceptions defined in [`../architecture/identifier-policy.md`](../architecture/identifier-policy.md).
- UUIDs are not authorization secrets. Public document access uses separate random hashed tokens, and customer-visible document numbers remain separate business identifiers.

## Payment terms and Terms & Conditions

Default precedence:

1. Document override
2. Customer default
3. Company default

The due date is derived from the applicable terms but remains editable.

Terms & Conditions are separate from structured payment terms and document notes:

- A company may define default customer-visible Terms & Conditions.
- New quotes and invoices inherit that default.
- The user may override Terms & Conditions per document.
- Changing the company default affects new documents, not already-created documents.
- Generated public pages and PDFs display the document's stored Terms & Conditions.
- Quote and invoice notes inherit their respective company defaults and may be overridden per document.
- Notes are a normal customer-visible document block, not a fixed PDF footer or arbitrary footer element.

## Payments and refunds

- A transaction represents a payment, refund, or explicit adjustment attached to one invoice.
- Store a non-negative amount and an explicit type/direction; do not use one signed amount to ambiguously encode transaction meaning.
- An invoice may have one payment or multiple partial payments.
- The company Transactions section is the aggregate view of invoice transactions.
- There are no expenses, unrelated transactions, chart of accounts, or bookkeeping ledger.
- Prevent payments from unintentionally exceeding the outstanding balance.
- Prevent refunds from exceeding the amount available to refund.
- Refunds may make an invoice partially or fully unpaid again; status must update accordingly.
- Transaction fields include amount, currency, date, payment method, reference, and notes.
- Transaction currency must be consistent with the invoice because v1 has no FX conversion.
- `net_paid = payments + positive adjustments − refunds − negative adjustments`; `outstanding = invoice_total − net_paid`.
- An adjustment requires a reason and audit entry and must not silently make net paid negative.
- After recording a payment, offer an optional payment-received email action.
- Do not automatically email receipts for historical/backfilled payments without clear user intent.

## Recurring invoices

The recurring template is not an invoice.

```text
Recurring template
→ scheduled execution
→ create invoice
→ issue invoice
→ generate PDF
→ send email
```

- Editing a template affects only future invoices.
- Existing generated invoices remain unchanged.
- Every template has a required internal name used for internal lists, search, selection, and audit history. It does not need to be unique and is never copied into generated invoices or exposed in PDFs, public pages, or customer email.
- Support weekly, monthly, quarterly, yearly, and custom intervals.
- Support start date, optional end date, and optional maximum occurrence count.
- Templates have Draft, Active, Paused, and Completed states; only Active templates execute.
- Pausing prevents future occurrences. Resuming continues with the next eligible occurrence and does not backfill missed occurrences without explicit user intent.
- Inherit company invoice defaults while allowing template overrides for payment terms, due-date calculation, Terms & Conditions, notes, email delivery, and reminder rules.
- A template may also store an optional customer reference / PO number for the recurring arrangement.
- Generated invoices use the normal company invoice numbering sequence.
- Generated invoices materialize the applicable defaults, customer reference / PO number, and reminder schedule; later template or company-default changes do not rewrite them.
- Scheduled invoices are created and issued. A per-template setting controls whether they are emailed automatically or left issued for manual sending.
- If automatic email fails, retry delivery against the same generated invoice rather than creating another invoice for that occurrence.
- Scheduled execution must be idempotent and safe under retries or overlapping runs.
- Use a stable occurrence idempotency key and record last run, next run, outcome, and generated invoice.
- Calculate each occurrence from its local calendar rule at the company automation time, then resolve it through the company IANA timezone into UTC; never add a fixed UTC duration for monthly/quarterly/yearly recurrence.
- A nonexistent spring-forward time shifts by the DST gap; a repeated fall-back time uses its first occurrence and executes once.
- After service downtime, catch up every occurrence due while Active, oldest first and in bounded batches. Intentional pause time is not backfilled without explicit confirmation.
- Use the approved PostgreSQL-backed Laravel queue, one supervised PHP worker, and cron-triggered scheduler. Create the occurrence and invoice transactionally; queue PDF/email only after commit.
- After one initial attempt, retry transient failures up to five times: after 1 minute, 5 minutes, 15 minutes, 1 hour, and 6 hours. Permanent failures and exhausted retries stop visibly; an authorized retry retains the same idempotency key.
- The complete approved behavior is defined in [`../architecture/scheduling-and-jobs.md`](../architecture/scheduling-and-jobs.md).

## Public documents

- Quotes and invoices use unpredictable public tokens rather than database IDs.
- Default link expiry is 30 days.
- Expiry is user-configurable.
- Links are revocable and regeneratable.
- Regeneration invalidates the old link.
- Quote validity and public-link expiry are independent; a technically valid link does not permit actions on a commercially Expired quote.
- Direct email must create or confirm a valid link before sending.
- Automated reminders may replace a naturally expired link but never recreate explicitly revoked access unless public access is re-enabled.
- Public invoice pages support viewing and PDF download.
- Public quote pages support viewing, PDF download, Accept, and Reject.
- Accept or Reject requires customer name and email address.
- Record the decision, timestamp, and appropriate audit metadata.
- Duplicate/replayed actions must not create inconsistent state.
- No customer account or electronic signature is required in v1.

## Email

- Use Zoho ZeptoMail.
- Prefer the API if architecture analysis confirms it is cleaner and more reliable than SMTP.
- Provide multilingual default subject and body for quotes and invoices.
- Allow editing before sending.
- Provide company templates per language for quote sent, invoice sent, payment reminder, and payment received events.
- Template fields include subject, body, button label, plain-text company signature, and preview.
- Support only allowlisted placeholders for relevant customer, company, document, amount, due-date, and public-URL values.
- Reject or identify unknown placeholders, escape substituted values for their output context, and handle unavailable optional values safely.
- Resolve recipients and PDF-delivery mode using per-send override, then customer preference, then company default.
- Support one primary/default recipient and optional multiple CC and BCC recipients.
- Show resolved recipients and secure-link-only/attach-PDF choice in the send composer before sending.
- Require at least one valid primary recipient; validate and deduplicate To/CC/BCC addresses.
- Automated sends with no valid recipient fail visibly and record the reason rather than retrying indefinitely.
- Track Sent, Delivered, and Opened where ZeptoMail supports them.
- Authenticate webhooks and process provider events idempotently.
- Customer SMTP is excluded from v1.
- The company primary brand color may be used for restrained accents when email-client compatibility and contrast permit it.

## Automated invoice reminders

- A company may define multiple enabled/disabled reminder rules with an integer day offset before or after the due date.
- An invoice may disable or override inherited reminder rules.
- A recurring template may override the rules inherited by generated invoices.
- Issuing an invoice materializes its reminder schedule from the applicable rules.
- Company-default changes affect future schedules unless explicitly reapplied.
- Pending reminders are recalculated when the due date changes.
- Pending reminders are suppressed when an invoice becomes Paid or Cancelled.
- A Partially Paid invoice continues to receive reminders while it has a positive outstanding balance.
- Reminder jobs run in the company timezone and must be idempotent under retries or overlapping executions.
- Record sends and failures in invoice/email history.
- Schedule reminders at the company automation time and retain local/UTC schedule, timezone, attempts, idempotency key, and outcome.
- After downtime, send a before-due reminder only while the due date remains in the future. If multiple after-due reminders accumulated, send only the newest eligible one and mark older instances superseded.
- Recheck invoice state, outstanding balance, due date, recipients, and public-link eligibility immediately before sending.
- Use the approved bounded retry schedule; permanent configuration failures do not retry indefinitely.
- The complete approved behavior is defined in [`../architecture/scheduling-and-jobs.md`](../architecture/scheduling-and-jobs.md).

## Localization

- Launch languages are English and Romanian.
- Both UI and generated documents are localized.
- Additional languages should be straightforward to add.
- Initial document-language precedence is document choice, customer default, then company default.
- The signed-in user's application language affects the internal UI only, not customer document language.
- Localize system terminology, dates, numbers, and presentation.
- Preserve user-entered descriptions exactly; do not automatically translate them.

## Company appearance

- v1 supports a company logo and one primary brand color.
- Offer safe presets and a custom color/hex input.
- Provide a simple outward-facing document/public-page preview.
- Apply the brand color to PDFs, public document pages, and restrained transactional email accents.
- Do not apply company themes to the internal Invumo application.
- Validate custom colors and choose accessible foreground colors or a safe fallback for each rendered context.
- v1 excludes custom fonts, print padding/scale/logo-size controls, custom favicons, Pay buttons, viewer-facing Share buttons, fixed-per-page footers, signature/stamp images, and Invumo-branding removal controls.
- v1 also excludes credit notes, automatic late fees, payment-processing fees, tax-inclusive pricing, user-editable system translation dictionaries, PDF QR codes, PDF invoice-status labels, and arbitrary footer-element builders.

## Audit history

Audit at least:

- Document creation, issue, cancellation, deletion, and significant edits
- Quote acceptance and rejection
- Product/service creation, edits, and archiving
- Number changes
- Payment/refund creation, change, and deletion
- Reminder scheduling, sending, suppression, and material failures
- Public-link generation, revocation, and regeneration
- Company transfer
- Important settings and membership changes

An audit record should explain what happened, when, who caused it, what object was affected, and enough before/after information to understand important edits. Do not introduce full event sourcing solely for this requirement.

Audit infrastructure is established with the platform foundation and used as each feature is built. Actor types distinguish internal users, authenticated public-customer actions, provider webhooks, scheduled jobs, and other system actions.
