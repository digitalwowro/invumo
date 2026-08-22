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
- Transferring a company to a different account must not rewrite its customers, documents, transactions, or audit history.
- Existing members remain attached during transfer by default.
- Every server-side business-data operation must verify company scope, membership, and permission.
- Never trust a client-provided company identifier without server-side authorization.

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
- Store timestamps in UTC; interpret recurring schedules and other company-timed automation in the company timezone.
- Document date formatting derives from the selected document language/locale. v1 has no separate manual date-format setting.

## Bank accounts

- A company may have multiple bank accounts.
- Bank account fields include label, bank name, account holder, IBAN/account number, SWIFT/BIC, optional currency, and optional local routing details.
- A quote or invoice stores a snapshot of any bank details presented on the document.
- Editing, archiving, or deleting the source bank account must not rewrite an existing document snapshot.

## Customer selection and creation

- A quote or invoice editor must support selecting an existing customer.
- The user may create a customer from the editor in a vertically scrollable modal containing the complete customer form without losing the in-progress document.
- After a successful modal save, close the modal and select the new customer automatically.
- Validation failure must retain both the customer form values and the in-progress document.

## Customer identity and defaults

- Customer type is `INDIVIDUAL` or `COMPANY`.
- Individual customers use first and last name.
- Company customers use a company/legal name and may have multiple contacts.
- Contacts may be designated as primary contact and billing contact/default recipient.
- Each customer has one structured billing/legal address: address line 1, optional address line 2, city, state/province/region, postal code, and country.
- Customer identity supports phone, primary/billing email, optional external reference/code, tax registration label and identifier, and business registration label and number.
- Customer defaults include currency, document language, payment terms, tax settings, billing recipient, CC recipients, BCC recipients, and PDF email-delivery mode.
- PDF email-delivery mode is secure link only or attach PDF.
- Internal customer notes are never rendered automatically on documents, public pages, or email.
- v1 excludes separate shipping/service addresses, customer tags, customer-specific manual date formats, and an ambiguous free-form legal-info field.
- A quote or invoice snapshots the customer identity, billing/legal address, and registration details used on that document.
- Editing or deleting the customer must not silently rewrite an existing document snapshot.

## Quote workflow

Statuses:

- Draft
- Sent
- Accepted
- Rejected
- Expired

Rules:

- Quotes remain editable after sending or acceptance.
- There is no quote versioning in v1.
- A quote may generate multiple invoices.
- Track quoted amount, invoiced amount, and remaining amount.
- Warn rather than block when generated invoices exceed the quoted amount.
- Default validity is 30 days, configurable by company and overridable per quote.
- Quote numbers are suggested automatically and manually overridable.

## Invoice workflow

Statuses:

- Draft
- Issued
- Partially Paid
- Paid
- Overdue
- Cancelled

Rules:

- Invoices may be created from a quote or independently.
- Issued invoices remain editable, including financial fields.
- Significant edits after issue require understandable audit records, including appropriate before/after data.
- Payment state is derived from the net total of valid payments and refunds.
- Overdue behavior depends on due date and outstanding balance; the architecture specification must define precedence between Overdue and payment statuses.
- v1 does not enforce jurisdiction-specific invoice immutability or numbering law.

## Document numbering

- Suggest the next logical number based on the current relevant sequence.
- Allow manual number entry and renumbering.
- Permit deletion where the application allows it.
- Audit meaningful numbering changes and deletions.
- Warn about duplicate numbers and unintended reuse.
- Do not silently reuse a removed number without clear user intent.
- Keep numbering configurable without building an unnecessarily complex rules engine.

Example:

1. Existing invoice numbers are 1, 2, and 3.
2. The user deletes 2 and renames 3 to 2.
3. The next suggested number is 3.
4. The user may override the suggestion.

## Line inputs

Each quote or invoice line contains:

- Order/line number
- Description
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

Using exact decimal arithmetic:

```text
items_subtotal = item_price × quantity

if period_unit = NONE:
    items_total = items_subtotal
else:
    items_total = items_subtotal × period_quantity

discount_value = items_total × discount_percentage
grand_subtotal = items_total − discount_value
tax_value = grand_subtotal × line_tax_rate
final_line_total = grand_subtotal + tax_value
```

The architecture phase must define:

- Whether percentages are stored as fractions or percentage points
- Supported precision for quantities, rates, and intermediate values
- When rounding occurs
- How document totals reconcile rounded line values
- Validation ranges

These rules must be explicit before implementation; never use binary floating-point for money.

## Discounts

- Support a percentage discount per line.
- Calculate the discount value automatically.
- An overall document discount may be supported only if its ordering and tax interaction are unambiguous and can be implemented cleanly.
- Do not silently produce ambiguous totals.

## Tax

- v1 supports tax per line only.
- Prices are entered tax-exclusive.
- Lines may use different tax rates.
- Company and customer settings may provide defaults; the user may override them on document lines.
- No invoice-wide tax in v1.
- No country-specific tax engine or electronic-invoicing compliance claim.

Each company maintains reusable tax-rate presets:

- Name
- Percentage, including 0%
- Optional default designation
- Active or archived state

Users may add, edit, and archive presets. Referenced presets should be archived rather than hard-deleted.

Applying a preset snapshots its name and percentage onto the document line. Later preset changes must not alter existing documents.

Render the applied tax name and percentage together on customer-visible documents, for example `VAT 19%`. v1 has no separate tax-percentage visibility setting.

## Currency

- Each customer has a default currency.
- New customer documents inherit that currency by default.
- Currency may be overridden per quote or invoice.
- Currency decimal precision is user-configurable per currency.
- The company selects ISO-code or symbol display style independently from currency precision.
- Display style does not change the stored currency code or value.
- There is no FX conversion or exchange-rate service.
- Never combine amounts in different currencies into one total without a mathematically valid, explicit basis.

## Payment terms and Terms & Conditions

Default precedence:

1. Document override
2. Customer default
3. Company default

The due date is derived from the applicable terms but remains editable.

Terms & Conditions are separate from structured payment terms and from notes/footer:

- A company may define default customer-visible Terms & Conditions.
- New quotes and invoices inherit that default.
- The user may override Terms & Conditions per document.
- Changing the company default affects new documents, not already-created documents.
- Generated public pages and PDFs display the document's stored Terms & Conditions.

## Payments and refunds

- A transaction represents a payment, refund, or adjustment attached to one invoice.
- An invoice may have one payment or multiple partial payments.
- The company Transactions section is the aggregate view of invoice transactions.
- There are no expenses, unrelated transactions, chart of accounts, or bookkeeping ledger.
- Prevent payments from unintentionally exceeding the outstanding balance.
- Refunds may make an invoice partially or fully unpaid again; status must update accordingly.
- Transaction fields include amount, currency, date, payment method, reference, and notes.
- Transaction currency must be consistent with the invoice because v1 has no FX conversion.
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
- Support weekly, monthly, quarterly, yearly, and custom intervals.
- Support start date, optional end date, and optional maximum occurrence count.
- Inherit company invoice defaults while allowing template overrides for payment terms, due-date calculation, Terms & Conditions, notes, email delivery, and reminder rules.
- Generated invoices use the normal company invoice numbering sequence.
- Generated invoices materialize the applicable defaults and reminder schedule; later company-default changes do not rewrite them.
- Scheduled execution must be idempotent and safe under retries or overlapping runs.
- Avoid a queue or separate worker unless architecture analysis proves it necessary.

## Public documents

- Quotes and invoices use unpredictable public tokens rather than database IDs.
- Default link expiry is 30 days.
- Expiry is user-configurable.
- Links are revocable and regeneratable.
- Regeneration invalidates the old link.
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
- Reminder jobs run in the company timezone and must be idempotent under retries or overlapping executions.
- Record sends and failures in invoice/email history.
- The architecture specification must define the company-local send time and retry policy.

## Localization

- Launch languages are English and Romanian.
- Both UI and generated documents are localized.
- Additional languages should be straightforward to add.
- Document language inherits the applicable default and is overridable per quote or invoice.
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
- Number changes
- Payment/refund creation, change, and deletion
- Reminder scheduling, sending, suppression, and material failures
- Public-link generation, revocation, and regeneration
- Company transfer
- Important settings and membership changes

An audit record should explain what happened, when, who caused it, what object was affected, and enough before/after information to understand important edits. Do not introduce full event sourcing solely for this requirement.
