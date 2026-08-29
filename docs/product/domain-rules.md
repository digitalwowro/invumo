# Invumo Core Domain Rules

Status: Approved product rules  
Last updated: 2026-08-26

This document is a concise implementation-facing companion to the [master build brief](master-build-brief.md). If a future implementation decision changes one of these rules, update both documents and record the decision in the memory repository.

Across the domain, unusual but internally valid workflows should remain possible. Block only unsupported actions or actions that violate authorization, tenant isolation, financial/data integrity, or another approved invariant; otherwise prefer warning, confirmation, and audit history.

## Tenant and ownership boundaries

- Every registered User owns exactly one personal Account in v1. Registration creates it transactionally with the default placeholder plan; the User cannot own a second Account.
- An Account belongs to its account owner, carries plan entitlements, and may own multiple Companies.
- A Company is an independent tenant containing all company business data and settings.
- A CompanyMembership connects a user and company with Owner, Admin, or Member role.
- One account owner may manage multiple companies.
- A user may belong to companies owned by different accounts.
- Each company has exactly one owning Account and one Owner membership held by that Account's owner; Admin and Member memberships may be multiple.
- Transferring a company to a different account must not rewrite its customers, documents, transactions, or audit history.
- Transfer targets only an existing Admin or Member of that Company, validates the destination member's Account and current Plan eligibility, makes that User the sole Company Owner, and retains the former Owner as Admin by default unless explicitly removed in the confirmed transfer.
- Other existing members remain attached during transfer by default.
- Every server-side business-data operation must verify company scope, membership, and permission.
- Never trust a client-provided company identifier without server-side authorization.
- Tenant-owned business tables also use forced PostgreSQL Row-Level Security. Every tenant-owned row, including child rows, carries `company_id`; same-company composite foreign keys prevent cross-company parent/child links.
- The Laravel runtime database role is not the schema owner and cannot bypass RLS. Tenant context is set transaction-locally only after authorization, is required by queue jobs, and denies access when absent.
- Control-plane, public-token, and scheduler bootstrap paths are narrow and must never grant a general tenant-data bypass. The approved mechanism is defined in [`../architecture/tenant-isolation.md`](../architecture/tenant-isolation.md).
- Authentication uses the official Laravel React starter kit with built-in Fortify and covers registration, email verification, sign-in/out, password reset/confirmation, rate limiting, and secure session invalidation. Verification and recovery messages use the User's Laravel-authored English/Romanian locale, encrypted after-commit queue payloads, bounded retries, and delivery-time link-validity rechecks. WorkOS AuthKit, the starter kit's Teams domain, TOTP two-factor authentication, and recovery codes are excluded from v1.
- Company invitations are email-addressed, expire exactly 7 days after their most recent issue or resend, are revocable, single-use, and company-bound; they assign Admin or Member, never Owner.
- Ownership transfer requires explicit confirmation and cannot be performed as an ordinary role change.
- v1 has only the fixed Owner, Admin, and Member roles; it has no custom permissions, per-user overrides, creator-only records, or per-record sharing within a Company.
- Owner has every Company permission and, among Company roles, alone controls owning-Account plan views/actions, ownership transfer, and permanent Company erasure; separate Platform Owner lifecycle administration follows the Platform Operations boundary.
- Admin manages normal Company operations/settings and may invite, change, or remove non-Owner members other than itself; it cannot affect the Owner membership.
- Member may manage Customers and routine Quotes/Invoices; send documents; manage their public links and Invoice reminder overrides; correct Quote lifecycle; cancel/reopen Invoices; record/edit/delete Payments and Refunds; and prepare Draft recurring templates.
- Member may use active Products & Services or enter manual lines but cannot manage the catalog, Company settings, Adjustments, Active recurring automation, permanent deletion, Quote provenance unlinking, exceptional duplicate/issued numbering, Company-wide operations, or the full audit trail.
- If a Member cannot cancel an Invoice because reaching zero net paid requires an Adjustment, keep cancellation blocked and show that Owner/Admin action is required; never suggest a Refund beyond actual refundable cash.
- The complete approved abilities, guards, system/public actors, and policy-test requirements are defined in [`../architecture/role-permission-matrix.md`](../architecture/role-permission-matrix.md).

## Plan boundary

- Plans belong to the account, not individual companies.
- Free, Pro, and Enterprise are placeholder plan names.
- v1 keeps one current Plan on each Account plus provider-independent lifecycle status and optional trial/access-end dates for internal operational visibility.
- Platform Owner may manually assign an active seeded Plan and lifecycle state with confirmation, reason, row locking, and platform audit.
- Plan expiry/past-due state never deletes data, silently changes plan, or automatically suspends access in v1; suspension is an explicit separate action.
- Self-service checkout, automated billing/payment collection, renewal/dunning, provider webhooks, and a plan-builder interface are excluded.

## Platform Operations boundary

- Platform Owner is a separate internal role attached to a verified User through a protected operator record; no Company role or Account ownership grants it.
- Platform Operations may search and review approved User, Account, Company, membership-count, plan-lifecycle, suspension, and platform-audit control-plane metadata before an explicit impersonation begins.
- Platform Operations never receives a general tenant-RLS bypass. Its own pages do not expose tenant Customer, document, Transaction, settings, delivery, file, or tenant-audit content.
- Platform Owner may begin full-action impersonation of an existing User who is not an active Platform Owner only within Laravel's shared recent-password confirmation window and behind a 10-attempts-per-minute action throttle. The action never accepts a password; when the window is stale, one localized dialog establishes it through the separately throttled Fortify confirmation endpoint and automatically submits impersonation. No support reason, separate action-specific confirmation, special duration, or restriction on the selected User's ordinary actions is added. The session receives exactly that User's permissions and RLS context, permits every normally available Company action/external effect, shows a persistent exit banner, cannot nest, and cannot access Platform Operations. Confirmation state is cleared when entering and leaving. Successful exit restores the Platform Owner directly to Platform Users rather than Company-home routing.
- Platform audit records impersonation start/end, and every normally audited impersonated mutation retains both the effective User and original Platform Owner identities.
- User suspension invalidates that User's sessions, blocks authentication, and preserves all data/history. A Platform Owner cannot suspend itself or the last active Platform Owner.
- Account suspension blocks all members from Companies owned by that Account while preserving memberships/data and leaving their access to Companies owned by other active Accounts intact.
- Every sensitive Platform mutation uses Laravel's shared recent-password window through `RequirePassword`, accepts no password in its mutation payload, requires current operator revalidation and its action-specific guards, remains throttled, locks rows where applicable, and writes an append-only platform audit event. Impersonation requires no support reason, separate action-specific confirmation, or special duration.
- Web UI cannot grant/revoke Platform Owner in v1; a protected confirmed command performs it and cannot remove the last active operator.
- The complete approved contract is [`../architecture/platform-operations.md`](../architecture/platform-operations.md).

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
- Bank accounts require a label, bank name, account holder, and IBAN/account number. Normalized uppercase SWIFT/BIC, currency, and local routing details are optional.
- Local routing details are a flat allowlisted object containing at most these eight fields: routing number, sort code, bank code, branch code, transit number, institution number, BSB, and IFSC. Each retained value is trimmed, non-empty, and at most 64 characters; custom keys, nested values, and provider payloads are rejected.
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
- A Customer has at most one active primary Contact and one active billing Contact. Contacts may be stored without email, but delivery may reference only an active Contact with a valid email; recipient addresses are deduplicated case-insensitively across To, CC, and BCC.
- Each customer has one structured billing/legal address: address line 1, optional address line 2, city, state/province/region, postal code, and country.
- Customer identity supports phone, an optional general/primary email, optional external reference/code, tax registration label and identifier, and business registration label and number. An Individual's primary email may be its default recipient; Company recipients normally resolve from contacts or an explicitly stored address.
- Customer defaults include currency, document language, payment terms, tax preset, billing recipient, CC recipients, BCC recipients, and PDF email-delivery mode.
- Customer currency, document-language, payment-term, and tax overrides are nullable. An unset Customer source resolves from the current active Company default without rewriting the stored Customer record. A referenced currency cannot be deactivated and a referenced tax preset cannot be archived until every Customer override is changed or cleared; Laravel returns a dependency warning and PostgreSQL independently rejects the invalid transition. Newly selected currencies must be active, newly selected tax presets must be unarchived, document languages must be authored supported locales, and payment-term days use the approved application-date-range envelope.
- Saved ordered Customer recipient rows are the authoritative recipient preference and resolve Contact-backed rows from the Contact's current valid email. An empty list remains unresolved until a valid recipient is selected; sending still requires at least one valid `TO` recipient.
- PDF email-delivery mode is secure link only or attach PDF.
- The Company PDF email-delivery fallback defaults to secure link only and may be changed by an Owner/Admin. Resolution remains per-send override, then Customer preference, then Company fallback.
- A Customer PDF email-delivery preference is optional. An unset preference inherits the current Company fallback rather than copying it.
- Internal customer notes are limited to 5,000 characters and are never rendered automatically on documents, public pages, or email.
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
- Names are limited to 160 Unicode characters, internal codes/SKUs to 120, units to 80, descriptions to 5,000, and catalog searches to 120. Laravel and PostgreSQL enforce the same persisted envelopes.
- A missing default price means “enter on the document” and is distinct from an explicit zero price.
- Quote, invoice, and recurring-invoice editors provide searchable selection of active entries while still allowing fully manual lines.
- Users may create a product/service inline from those editors without losing document progress; successful creation selects it automatically, and validation failures retain both forms.
- Selecting an entry copies applicable values onto the line. The line remains completely editable and financially authoritative.
- Product/service name and optional description initialize the customer-visible line description; price, unit, period unit, and tax initialize their corresponding line fields.
- Document and recurring-template lines are snapshots, not live catalog links. Editing or archiving a product/service never rewrites existing lines or invoices generated later from already-snapshotted recurring-template lines.
- Copy a default price only when its currency matches the document currency. On mismatch, copy non-price defaults and require manual price entry or confirmation; never perform FX conversion.
- Owner/Admin roles manage entries by default. Members may search and use active entries subject to the approved permission matrix.
- Archive a previously used entry rather than hard-deleting it by default.
- A referenced Company currency cannot become inactive and a referenced tax preset cannot be archived while any Customer or Product/Service still stores that default. The root source mutation reports the dependency, and deferred PostgreSQL validation independently prevents direct or concurrent writes from creating silent fallback state.
- Reducing a Company currency's precision is rejected while any Product/Service default price in that currency needs more fractional digits. Users must update those catalog prices explicitly; configuration saves never round or rewrite them silently.
- Catalog audit records retain changed-field names and only non-sensitive operational facts: price presence, currency code, period unit, tax-presence, and archive/delete state. They never retain product/service name, description, internal code/SKU, unit, price, or tax-preset name.
- v1 excludes product URLs/customer-visible product links, inventory and stock movements, tags/categories, variants, bundles, supplier/purchasing data, cost/margin tracking, tiered/customer-specific price lists, product images, and catalog CSV import/export.

## Default resolution and snapshot timing

- Resolve available company defaults into stored draft fields at creation and customer defaults when a customer is selected.
- Resolve product/service defaults into a line when the entry is selected.
- Later source changes never propagate silently to an existing ordinary quote or invoice.
- A recurring template stores inheritance-versus-override intent for every Customer-derived field. At generation, explicit template/line overrides remain fixed; inherited identity, address, registration, contacts, recipients, CC/BCC, delivery, currency, language, payment terms, and default tax resolve from the current Customer, then Company fallback. Already-generated invoices never change.
- Recurring line price and quantity inputs stay at the shared exact source envelopes and are not rewritten when inherited currency or Company precision changes. Save-time currency precision is preview-only. Each occurrence recalculates from those inputs using either the current inherited precision or the template's fixed explicit currency/precision snapshot; no FX conversion or silent re-quantization occurs.
- Changing the selected customer requires confirmation of the resulting identity/default/recipient changes and must not silently replace lines or unrelated manual edits.
- Reapplying current defaults is an explicit user action with a clear preview of what will change.

## Quote workflow

Stored lifecycle states:

- Draft
- Sent
- Accepted
- Rejected

Expired is derived separately and is not stored as a lifecycle value.

Rules:

- Quotes remain editable after sending or acceptance.
- A quote stores company, customer snapshot, number, issue date, valid-until date, currency, document language, lines, Terms & Conditions, notes, an optional trimmed customer reference / PO number of up to 120 Unicode characters, and any displayed bank-details snapshot.
- Sending requires all required fields and at least one valid billable line. A Draft quote becomes Sent after provider dispatch acceptance; immediate dispatch failure leaves it Draft and records the attempt, while later delivery failure does not revert it.
- Expired is derived after `valid_until` in the company timezone when the quote is neither Accepted nor Rejected.
- Expired public quotes cannot be accepted or rejected until an internal user extends validity or changes status.
- Public Accept/Reject is allowed only for a non-expired Sent Quote. Identical replays are idempotent; an opposite later public decision requires an internal correction.
- An authorized internal user may correct the stored lifecycle to Draft, Sent, Accepted, or Rejected in either direction after confirmation, with a required reason and audit record. Expired is never selected directly. Resending an Accepted or Rejected Quote is allowed after warning and does not change lifecycle.
- Editing a sent/accepted quote does not reset its status automatically; significant changes are audited.
- v1 has one mutable current quote, not revisions. A successful edit updates its current customer-facing representation and freshly rendered current PDF from persisted snapshots; the currency code, precision, display style, language, identity, brand, defaults, lines, and totals used by those representations are document snapshots rather than live Company styling/configuration. Phase 8 adds token-authorized public access to the same representation. Already-delivered email bodies/attachments remain unchanged. There is no version-history screen.
- A quote may generate multiple invoices. Accepted is the normal conversion state. Owner/Admin may confirm conversion from Draft, Sent, or Expired; Rejected cannot be converted until moved to an allowed state.
- Each generated invoice initially copies the quote's customer reference / PO number without creating a live link back to the quote.
- Linked invoices use the quote currency; there is no cross-currency allocation.
- Quote currency cannot change after linked invoices exist unless those links are removed through a valid workflow.
- A Quote-derived Invoice may be unlinked only while it remains Draft and has never been sent/issued, shared through a public link, or associated with a financial transaction. Unlinking is confirmed and audited, preserves the Invoice as an independent Draft, and recalculates the Quote allocation. After disqualifying activity, Issue, or Cancellation, the provenance link is immutable.
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
- v1 has one mutable current invoice, not revisions. A successful edit updates its current public page and regenerated current PDF; already-delivered email bodies/attachments remain unchanged. There is no version-history screen.
- Financial edits recalculate balance and payment state. Do not reduce invoice total below net paid until the necessary refund or corrective adjustment is recorded.
- Invoice currency cannot change while valid payment/refund/adjustment transactions exist.
- Payment state is derived from the net total of valid payments, refunds, and explicit adjustments.
- An Issued zero-total Invoice is immediately Paid, is never Overdue, requires no payment, and receives no payment reminders.
- Cancellation is allowed only when net paid is exactly zero. A positive net-paid amount requires a refund or corrective adjustment before cancellation.
- Cancellation suppresses pending reminders and blocks new payment, refund, and adjustment records while the invoice remains Cancelled.
- Cancellation retains the invoice, every existing linked transaction, and audit history. No invoice may be permanently deleted while any linked transaction records remain, regardless of lifecycle state.
- Existing transactions are read-only while the Invoice remains Cancelled.
- Reopening a Cancelled Invoice returns it to Issued; preserves its number, dates, transactions, audit history, and public-link identity; and sends no email automatically. Transaction edits/deletions become eligible again under the complete ledger rules. A valid non-revoked public link remains viewable and visibly Cancelled until reopening.
- Reopening never repeats sent reminders. Past before-due reminders become stale; if already overdue, schedule only the newest currently eligible unsent after-due reminder for the next Company automation time. Reopening requires confirmation, a reason, audit history, and permission-matrix authorization.
- v1 does not enforce jurisdiction-specific invoice immutability or numbering law.

## Document numbering

Quotes and Invoices carry a monotonically increasing edit version. Saving from a stale browser state is rejected with a reload/review message rather than silently overwriting a newer change. Financial mutations also lock and recalculate the complete Invoice aggregate inside one database transaction.

- Suggest the next logical number based on the current relevant sequence.
- Allow manual number entry and renumbering.
- Permit deletion where the application allows it.
- Audit meaningful numbering changes and deletions.
- Warn whenever a number duplicates another non-deleted document in the same company and document type, while permitting an intentional override.
- Do not silently reuse a removed number without clear user intent.
- Keep numbering configurable without building an unnecessarily complex rules engine.
- Quote and invoice sequences are separate per company.
- Each Company may customize separate Quote and Invoice patterns. Defaults are `Q-{YEAR}-{NUMBER}` and `I-{YEAR}-{NUMBER}`; `{NUMBER}` is mandatory exactly once, `{YEAR}` is optional at most once and resolves automatically to the current four-digit Company-local year, padding is separately configurable from 1–12 with a default of 4, and the pattern is bounded to 120 characters without control characters or unknown braces.
- Reset policy is explicit, defaults to never, and may instead use the Company-local calendar year. `{YEAR}` does not imply annual reset, while annual reset requires `{YEAR}` so rendered numbers remain distinct across reset periods.
- Settings previews use the server-resolved Company timezone and must not fall back to the browser timezone or UTC. Persisted assigned numbers never change merely because the year changes.
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

Users may add, edit, and archive presets. Referenced presets should be archived rather than hard-deleted, but Customer defaults must first be changed or cleared so their stored explicit choice never silently becomes a Company fallback.

Applying a preset snapshots its name and percentage onto the document line. Later preset changes must not alter existing documents.

Initial line-tax precedence is explicit line choice, selected product/service default, customer default, then company default. The copied line value is authoritative afterward.

Render the applied tax name and percentage together on customer-visible documents, for example `VAT 19%`. v1 has no separate tax-percentage visibility setting.

## Currency

- Each company has a default currency, and a customer may override it.
- Initial document-currency precedence is document choice, customer default, then company default.
- Currency may be overridden per quote or invoice.
- Currency decimal precision is user-configurable per currency from 0 through 8.
- Every quote and invoice snapshots its resolved currency precision, and Quote conversion preserves it. A recurring template stores an explicit currency/precision override or inheritance intent. Inherited currency uses the current Customer currency and configured precision at generation; an explicit template override remains fixed. Generated Invoices never change afterward.
- Changing a Company currency precision does not reject or rewrite recurring line inputs. It changes later inherited previews/occurrences; an explicit recurring currency retains the code and precision snapshotted when selected.
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

Payment terms and quote validity are non-negative whole calendar-day offsets from the issue date. The due date and valid-until date are derived from the applicable offset but remain editable, and neither may be before the issue date.

There is no arbitrary maximum offset. A resolved date must remain within the inclusive application range `0001-01-01` through `9999-12-31`.

Persisted day offsets use the full application-date-range envelope of `0` through `3,652,058` days. This is a technical safety bound equal to the number of days between the minimum and maximum supported dates, not a shorter business-policy maximum. Resolving an offset still validates the result against the issue-date-specific remaining range before date arithmetic is accepted.

Terms & Conditions are separate from structured payment terms and document notes:

- A company may define default customer-visible Terms & Conditions.
- New quotes and invoices inherit that default.
- The user may override Terms & Conditions per document.
- Changing the company default affects new documents, not already-created documents.
- Generated public pages and PDFs display the document's stored Terms & Conditions.
- Quote and invoice notes inherit their respective company defaults and may be overridden per document.
- Notes are a normal customer-visible document block, not a fixed PDF footer or arbitrary footer element.
- Terms & Conditions are limited to 20,000 characters. Quote and Invoice notes are each limited to 5,000 characters. The same limits apply to Company defaults and every later Customer/document override or snapshot.

## Payments and refunds

- A transaction represents a payment, refund, or explicit adjustment attached to one invoice.
- Store a non-negative amount and an explicit type/direction; do not use one signed amount to ambiguously encode transaction meaning.
- An executable transaction amount must be strictly positive. Payment, Refund, and Adjustment rows may be created, edited, or deleted only while the Invoice is Issued; Draft and Cancelled Invoices reject those mutations.
- An invoice may have one payment or multiple partial payments.
- The company Transactions section is the aggregate view of invoice transactions.
- There are no expenses, unrelated transactions, chart of accounts, or bookkeeping ledger.
- Prevent payments from unintentionally exceeding the outstanding balance.
- Prevent refunds from exceeding actual recorded cash paid and not already refunded. Positive adjustments affect balance but never increase refundable cash.
- Refunds may make an invoice partially or fully unpaid again; status must update accordingly.
- Transaction fields include amount, currency, date, payment method, reference, and notes.
- Transaction currency must be consistent with the invoice because v1 has no FX conversion.
- `net_paid = payments + positive adjustments − refunds − negative adjustments`; `outstanding = invoice_total − net_paid`.
- `cash_available_to_refund = payments − refunds`. Every create/edit/delete recomputes the complete ledger and requires `0 <= net_paid <= invoice_total`, non-negative refundable cash, and non-negative outstanding.
- A Payment or positive adjustment cannot exceed outstanding. A Refund cannot exceed both refundable cash and net paid. A negative adjustment cannot exceed net paid. Editing/deleting a row must leave all retained rows valid.
- An adjustment requires a reason and audit entry and must not silently make net paid negative.
- A zero-total Invoice rejects every Payment, Refund, and Adjustment row.
- A transaction date may precede the Invoice issue date for advance or backfilled records but cannot be later than the current Company-local date.
- A sent payment-received email does not freeze its transaction. Later edits/deletion require warning, complete invariant validation, and audit; the delivered email remains unchanged history.
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
- Activating a template whose start date is in the past schedules the first occurrence on or after activation and never backfills pre-activation invoices.
- Pausing prevents future occurrences. Resuming continues with the next eligible occurrence and does not backfill missed occurrences without explicit user intent.
- Completed is terminal in v1; continuing requires duplicating it into a new Draft.
- Inherit company invoice defaults while allowing template overrides for payment terms, due-date calculation, Terms & Conditions, notes, email delivery, and reminder rules.
- A template may also store an optional customer reference / PO number for the recurring arrangement.
- Generated invoices use the normal company invoice numbering sequence.
- Generated invoices materialize current inherited Customer values, explicit template/line overrides, customer reference / PO number, and reminder schedule. Template edits affect future occurrences only; later template/default/customer edits do not rewrite generated invoices.
- If inherited currency changes, template line inputs keep their numeric values and the generated Invoice recalculates/rounds with the current currency precision; no FX conversion occurs. Explicit template currency remains fixed. Explicit line tax remains fixed; only a line marked to inherit Customer tax uses the current Customer default.
- An automatic-email template retains a last-confirmed delivery currency, established by its first eligible occurrence. If a later inherited currency differs, generate and issue the Invoice but suppress automatic email and mark the Invoice/template **Currency changed — review required**. All later occurrences remain issue-only until a reviewed Invoice is successfully sent manually; provider acceptance confirms its currency as the new baseline. Explicit template currency overrides do not trigger this gate.
- Scheduled invoices are created and issued. A per-template setting requests automatic email, subject to the currency-review and other delivery-safety gates; otherwise the Invoice remains issued for manual sending.
- If automatic email fails, retry delivery against the same generated invoice rather than creating another invoice for that occurrence.
- Permanently deleting an eligible generated Invoice uses the ordinary guarded Invoice-deletion workflow and deletes its linked occurrence and pending occurrence-dispatch state. The template cursor, logical ordinal, and successful-occurrence count do not rewind; stale work for the deleted occurrence is a no-op, while later distinct occurrences generate normally within the unchanged schedule/end/count limits.
- Cancelling a generated Invoice retains its occurrence and does not prevent later scheduled occurrences.
- Scheduled execution must be idempotent and safe under retries or overlapping runs.
- Use a stable occurrence idempotency key and record last run, next run, outcome, and generated invoice.
- Calculate each occurrence from its local calendar rule at the company automation time, then resolve it through the company IANA timezone into UTC; never add a fixed UTC duration for monthly/quarterly/yearly recurrence.
- A nonexistent spring-forward time shifts by the DST gap; a repeated fall-back time uses its first occurrence and executes once.
- After service downtime, catch up every occurrence due while Active, oldest first and in bounded batches. Intentional pause time is not backfilled without explicit confirmation.
- A permanent occurrence failure keeps the Active template at that ordinal until authorized retry. Owner/Admin must receive a proactive Company-wide attention count linked to the failed-template filter; do not silently advance or rely only on someone inspecting every row.
- Use the approved PostgreSQL-backed Laravel queue, one supervised PHP worker, and cron-triggered scheduler. Create the occurrence and invoice transactionally; queue PDF/email only after commit.
- After one initial attempt, retry transient failures up to five times: after 1 minute, 5 minutes, 15 minutes, 1 hour, and 6 hours. Permanent failures and exhausted retries stop visibly; an authorized retry retains the same idempotency key.
- The complete approved behavior is defined in [`../architecture/scheduling-and-jobs.md`](../architecture/scheduling-and-jobs.md).

## Public documents

- Public Quote and Invoice pages are served by the SaaS application at `app.invumo.com`, never by the separate `invumo.com` marketing website. Exact paths and credential/bootstrap behavior follow the approved [`public-token-and-access.md`](../architecture/public-token-and-access.md) contract.
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
- Public Accept/Reject is enabled only for non-expired Sent Quotes.
- Accept or Reject requires customer name and email address.
- The decision event remains immutable, but Owner/Admin can irreversibly redact its retained name/email through the Customer erasure action while keeping the Quote, decision, outcome, timestamp, and idempotency fact. Audit never copies the erased identity.
- Record the decision, timestamp, and appropriate audit metadata.
- Duplicate/replayed actions must not create inconsistent state.
- No customer account or electronic signature is required in v1.

## Email

- Use Zoho ZeptoMail.
- Use the already-configured authenticated SMTP transport for foundational account verification, recovery, and invitation email.
- Use ZeptoMail's HTTPS Send API for Quote, Invoice, reminder, and payment-received delivery. Keep foundational account verification, recovery, and invitation email on the existing authenticated SMTP transport.
- Provide multilingual default subject and body for quotes and invoices.
- Allow editing before sending.
- Provide company templates per language for quote sent, invoice sent, payment reminder, and payment received events.
- Template fields include subject, body, button label, plain-text company signature, and preview.
- Author templates as plain text and render them through the safe Invumo HTML email shell. Bound subject/body/button/signature at 500/20,000/80/5,000 characters.
- Placeholders use exact `{{snake_case_name}}` tokens, are allowlisted per event, and are resolved by Laravel. Unknown or malformed placeholders are rejected.
- Support only allowlisted placeholders for relevant customer, company, document, amount, due-date, and public-URL values.
- Reject or identify unknown placeholders, escape substituted values for their output context, and handle unavailable optional values safely.
- Resolve recipients and PDF-delivery mode using per-send override, then customer preference, then company default.
- Support one primary/default recipient and optional multiple CC and BCC recipients.
- Show resolved recipients and secure-link-only/attach-PDF choice in the send composer before sending.
- Require at least one valid primary recipient; validate and deduplicate To/CC/BCC addresses.
- Bound one direct document email to ten recipients and apply weighted Company, Account, and shared-provider recipient budgets at the provider-submission boundary, including every retry. Quota exhaustion or lost sender authority must fail visibly without a provider call.
- Automated sends with no valid recipient fail visibly and record the reason rather than retrying indefinitely.
- Track Sent, Delivered, soft/hard bounce, Opened, and clicked provider events. Treat open/click signals as provider-reported and potentially incomplete.
- Authenticate ZeptoMail webhooks with the pinned `X-Invumo-Webhook-Key` static secret supported by the live Agent UI, compare it in constant time before parsing, discard raw/privacy-rich provider fields, and process duplicate or out-of-order events idempotently. HTTPS protects the static secret in transit; provider event identifiers, not an unavailable signed request timestamp, make replays effect-idempotent.
- A provider outcome that may have been transmitted but cannot be confirmed becomes `UNKNOWN` and is never resent automatically. An authorized manual retry creates a new immutable attempt after a duplicate-delivery warning.
- Permanent document deletion erases delivery content, recipients, attachment artifacts, public URLs, and provider identity while retaining only non-sensitive operational facts.
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
- Laravel language files are the only authored translation source; React receives resolved common and page-specific strings through Inertia props without a separate client catalog.
- `config/localization.php` is the sole supported-locale allowlist. Database constraints validate only a bounded locale-code shape and never duplicate the configured language catalogue.
- Both UI and generated documents are localized.
- Additional languages should be straightforward to add.
- Initial document-language precedence is document choice, customer default, then company default.
- The signed-in user's application language affects the internal UI only, not customer document language.
- Localize system terminology, dates, numbers, and presentation.
- Preserve user-entered descriptions exactly; do not automatically translate them.

## Company appearance

- v1 supports a company logo and one primary brand color.
- Company-logo files follow the approved raster validation, private Laravel storage, controlled serving, immutable replacement/cleanup, and local-to-S3 migration contract in [`../architecture/uploads-and-storage.md`](../architecture/uploads-and-storage.md).
- New Companies default to neutral ink `#14181C`. The built-in shortcuts are Ink `#14181C`, Navy `#1E3A5F`, Forest `#1F5D42`, Burgundy `#7F1D1D`, and Violet `#5B3A8E`; these are UI conveniences rather than a closed Company-color enum.
- Companies may instead save any custom color in canonical uppercase `#RRGGBB` notation. Adding or changing presets later requires no schema or data migration because the final hex value is persisted.
- Provide a simple outward-facing document/public-page preview.
- Apply the brand color to PDFs, public document pages, and restrained transactional email accents.
- Do not apply company themes to the internal Invumo application.
- The shared outward-theme resolver chooses black or white for the best contrast on a brand-color background. When the chosen color cannot meet the required contrast against white for outward text or rules, that context falls back to neutral ink while preserving the saved Company color.
- v1 excludes custom fonts, print padding/scale/logo-size controls, custom favicons, Pay buttons, viewer-facing Share buttons, fixed-per-page footers, signature/stamp images, and Invumo-branding removal controls.
- v1 also excludes credit notes, automatic late fees, payment-processing fees, tax-inclusive pricing, user-editable system translation dictionaries, PDF QR codes, PDF invoice-status labels, and arbitrary footer-element builders.

## Dashboard

- Keep the Company dashboard operational and bounded; do not add analytics or reporting.
- An unpaid Invoice is an Issued Invoice with a positive outstanding balance, including a Partially Paid Invoice.
- Paid this month is the gross value of Payment rows dated within the current Company-local calendar month whose Invoice remains Issued. Refunds and Adjustments do not reduce or increase this metric, and Cancelled Invoices are excluded from every operational dashboard metric.
- Outstanding and overdue values derive from the authoritative Invoice total and complete transaction ledger.
- Monetary values are grouped by currency. Never add unlike currencies or imply an FX conversion.
- Recent Invoices are a bounded Company-scoped list and use the same authoritative lifecycle, payment, and overdue derivation as the Invoice workspace.

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

## Deletion and archiving

- A Quote may be permanently deleted in any lifecycle state only when it has no linked Invoice.
- An Invoice may be permanently deleted in Draft, Issued, or Cancelled only when it has no Payment, Refund, or Adjustment rows.
- Prior sending, issuing, public sharing, or customer decision strengthens the confirmation warning but does not independently block deletion.
- Deletion revokes public access, suppresses pending reminders/jobs, applies the approved delivery/audit retention rules, and never rewinds or silently reuses the document number.
- Dependent Customers, products/services, tax presets, and bank accounts are archived by default; ordinary parent deletion never cascades through these guards.

The complete approved Quote, Invoice, financial, reminder-reaction, concurrency, public-state, and deletion contract is defined in [`../architecture/document-and-financial-state.md`](../architecture/document-and-financial-state.md).
