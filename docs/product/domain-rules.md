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

## Currency

- Each customer has a default currency.
- New customer documents inherit that currency by default.
- Currency may be overridden per quote or invoice.
- Currency decimal precision is user-configurable per currency.
- There is no FX conversion or exchange-rate service.
- Never combine amounts in different currencies into one total without a mathematically valid, explicit basis.

## Payment terms

Default precedence:

1. Document override
2. Customer default
3. Company default

The due date is derived from the applicable terms but remains editable.

## Payments and refunds

- A transaction represents a payment, refund, or adjustment attached to one invoice.
- An invoice may have one payment or multiple partial payments.
- The company Transactions section is the aggregate view of invoice transactions.
- There are no expenses, unrelated transactions, chart of accounts, or bookkeeping ledger.
- Prevent payments from unintentionally exceeding the outstanding balance.
- Refunds may make an invoice partially or fully unpaid again; status must update accordingly.
- Transaction fields include amount, currency, date, payment method, reference, and notes.
- Transaction currency must be consistent with the invoice because v1 has no FX conversion.

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
- Track Sent, Delivered, and Opened where ZeptoMail supports them.
- Authenticate webhooks and process provider events idempotently.
- Customer SMTP is excluded from v1.

## Localization

- Launch languages are English and Romanian.
- Both UI and generated documents are localized.
- Additional languages should be straightforward to add.
- Document language inherits the applicable default and is overridable per quote or invoice.
- Localize system terminology, dates, numbers, and presentation.
- Preserve user-entered descriptions exactly; do not automatically translate them.

## Audit history

Audit at least:

- Document creation, issue, cancellation, deletion, and significant edits
- Quote acceptance and rejection
- Number changes
- Payment/refund creation, change, and deletion
- Public-link generation, revocation, and regeneration
- Company transfer
- Important settings and membership changes

An audit record should explain what happened, when, who caused it, what object was affected, and enough before/after information to understand important edits. Do not introduce full event sourcing solely for this requirement.

