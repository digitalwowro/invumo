# Invumo — Master Build Brief

Status: Approved product brief

Last updated: 2026-08-26

- Marketing website: `https://invumo.com`
- SaaS application: `https://app.invumo.com`

Invumo is a streamlined quotation and invoicing SaaS. Its core philosophy is:

> Anything that does not help the user create, send, manage, or get paid for an invoice probably should not exist.

The application should have as few moving parts as reasonably possible.

Within approved scope, favor flexible workflows. Block an action only when v1 cannot genuinely support it or when it would violate authorization, tenant isolation, financial/data integrity, or another approved internal invariant. Prefer a clear warning, confirmation, and audit record for unusual but valid actions.

## Instructions before implementation

Before implementing application code:

1. Analyze this complete brief.
2. Identify architectural risks, contradictions, unresolved calculation details, and unnecessarily complex requirements.
3. Recommend the simplest appropriate technology stack.
4. Explain the recommendation briefly, including major trade-offs.
5. Prefer boring, mature, maintainable technology over unnecessary infrastructure.
6. Do not assume a frontend framework, ORM, authentication library, deployment platform, or application architecture solely because it is popular.
7. Use PostgreSQL from day one.
8. Complete and review the schema-shaping architecture, state, permission, and data documents before domain migrations or business workflows. Complete navigation, integration, and operational design gates just before their named implementation phase, as defined by the canonical development tracker.
9. Proceed incrementally through the approved tracker unless there is a genuine blocking ambiguity.

Do not add unrequested features.

The technology/application baseline, tenant-isolation mechanism, numbering concurrency, scheduler behavior, financial/document state, permission matrix, and relational schema have been approved and are recorded in [`../architecture/`](../architecture/). The canonical [development tracker](../development/development-tracker.md) defines the current implementation phase and each just-in-time design gate.

## 1. Core product

Invumo is a multi-tenant SaaS for:

1. User registration and login
2. Managing one or more companies
3. Managing customers
4. Managing reusable products and services
5. Creating quotations
6. Creating invoices from a quotation or independently
7. Recording payments against invoices
8. Creating recurring invoices
9. Sending quotes and invoices by email
10. Generating PDFs
11. Publishing public quote and invoice links
12. Operating the hosted SaaS through a restricted internal Platform Operations area

Do not implement in v1:

- Inventory or stock management
- Vendors, purchasing, or purchase-order entities and workflows; the optional customer reference / PO number on sales documents is metadata only
- Expenses, bookkeeping, a general ledger, or full accounting
- CRM
- Currency conversion or exchange-rate services
- Analytics or reports beyond the small operational dashboard
- CSV import/export
- Electronic invoicing standards
- Country-specific fiscal compliance engines
- Automatic translation of user-entered content
- Customer SMTP
- TOTP two-factor authentication and recovery codes
- Self-service or automated subscription billing and payment collection for Invumo itself; provider-independent manual plan-lifecycle administration is included
- Custom document title/header overrides
- Company-uploaded signature or stamp images
- Custom company favicons
- Custom fonts and print-layout controls such as padding, logo size, or print scale
- Online-payment/Pay buttons
- Viewer-facing Share buttons on public document pages
- A fixed-on-every-page PDF footer option
- Company theming of the internal Invumo dashboard/application
- Invumo-branding removal controls
- Customer tags
- Customer-specific manual date formats
- Credit notes
- Overall document discounts
- Automatic late fees
- Payment-processing fees
- Tax-inclusive prices
- User-editable system translation dictionaries
- QR codes in generated PDFs
- Invoice-status labels in generated PDFs
- Arbitrary custom footer-element builders

## 2. Account model

Separate these concepts:

- User
- Account
- Company
- Company membership

An Account belongs to an account owner. The account owner has an Invumo plan. In v1, registration creates exactly one personal Account for the new User in the same transaction, using the default placeholder plan. A User cannot own a second Account, but their Account may own multiple Companies and the User may also belong to Companies owned by other Accounts.

v1 authentication uses the official Laravel React starter kit with built-in Fortify sessions. It must cover registration, email verification, sign-in, sign-out, password reset, password confirmation, secure session invalidation, and rate limiting. Verification and recovery email must use the User's Laravel-authored English/Romanian locale, encrypted after-commit queue payloads, bounded retries, and delivery-time suppression when the verification state or recovery token is no longer valid. Do not use WorkOS AuthKit or the starter kit's Teams domain; Invumo owns its Account, Company, and Membership model.

TOTP two-factor authentication and recovery codes are explicitly deferred from v1.

Initial placeholder plans:

- Free
- Pro
- Enterprise

These are placeholders only. Additional plans must be possible later.

Do not build subscription checkout/payment collection, Stripe integration, automated renewals/dunning, or a plan-creator interface in v1. Platform Operations includes manual assignment of the seeded Plans and provider-independent lifecycle/status/date tracking so the operator can see active, trialing, past-due, canceled, expired, and upcoming-expiry Accounts.

## 2.1 Platform Operations

Invumo requires an internal back office for its operator. This is not a fourth Company role.

- A verified User receives Platform Owner authority only through a separate protected operator record.
- Company Owner/Admin/Member roles never imply platform authority, and Platform Owner never implies Company membership.
- Platform Operations uses a distinct `/platform` shell and exposes only approved control-plane User, Account, Company, plan-lifecycle, and platform-audit metadata before an explicit impersonation begins.
- Its own pages do not expose tenant Customers, documents, Transactions, settings, email/PDF content, or tenant audit payloads and never receive a general PostgreSQL RLS bypass.
- v1 supports guarded User and Account suspension/reactivation, session invalidation, manual current-Plan/lifecycle administration, and append-only platform audit.
- v1 supports full-action User impersonation behind Laravel's shared recent-password confirmation window and a 10-attempts-per-minute action throttle. The impersonation mutation never accepts a password. When the window is stale, one localized dialog confirms through the separately throttled Fortify endpoint and automatically submits the guarded action; there is no support reason, separate action-specific confirmation, impersonation-specific timeout, or restriction on the selected User's ordinary actions. Confirmation state is cleared at both identity transitions. Active Platform Owners cannot be impersonated, and Platform Operations remains unavailable throughout every impersonated session. The session otherwise has exactly the selected User's permissions/RLS context, permits all real Company actions and external effects available to that User, shows a persistent exit banner, prohibits nesting, restores a still-valid original operator directly to Platform Users, and retains original Platform Owner plus effective User attribution in audit.
- v1 excludes a general tenant-data/RLS bypass, self-service billing, payment collection, provider webhooks, and Plan creation/editing.
- The first Platform Owner is granted only through an explicitly authorized protected application command after the User has registered and verified their email.

The complete security, data, status, route, and implementation sequence is defined in [Platform Operations](../architecture/platform-operations.md).

## 3. Companies and tenant model

An account owner can manage multiple companies. A company is an independent business entity and tenant inside Invumo.

Each company has independent:

- Customers
- Contacts
- Products and services
- Quotations
- Invoices
- Recurring invoice templates
- Transactions
- Bank accounts
- Email templates and delivery history
- Reminder rules and scheduled reminder instances
- Public document links
- Document numbering
- Currency settings
- Tax settings
- Company timezone
- Language settings
- Payment terms
- Quote validity
- Branding and logo
- Document settings
- Email defaults
- Audit history

Use one shared PostgreSQL database unless there is a strong architectural reason not to. Every company-owned object must be scoped correctly, and protection against cross-company data access is critical.

Tenant isolation uses two mandatory layers: Laravel company scoping, membership checks, and Policies; plus PostgreSQL Row-Level Security on tenant-owned business tables. Every tenant-owned row, including child rows, carries `company_id`, with same-company composite foreign keys where appropriate. The production runtime database role cannot own tenant tables or bypass RLS. Tenant context is transaction-local and fail-closed when absent. See the approved [tenant-isolation specification](../architecture/tenant-isolation.md).

## 4. Company ownership and members

A company can have multiple members with these roles:

- Owner
- Admin
- Member

v1 uses only these fixed roles. There are no custom permissions, per-user overrides, creator-only records, or per-record sharing rules inside a Company. Permissions follow the active Company membership, and permitted Company records are shared regardless of who created them.

The Owner has every Company permission and, among Company roles, exclusively controls owning-Account plan views/actions, ownership transfer, and permanent Company erasure. The separate Platform Owner may administer the current Plan/lifecycle only through Platform Operations. Admin manages ordinary Company operations, settings, and non-Owner membership administration but cannot affect the Owner membership or itself through those actions. Member performs day-to-day Customer, Quote, Invoice, Payment, and Refund work; may correct Quote lifecycle and cancel/reopen Invoices; may prepare Draft recurring templates; and may use active catalog entries or manual lines. Member cannot manage Company settings/catalog entries, Adjustments, Active recurring automation, permanent deletion, Quote provenance unlinking, exceptional duplicate/issued numbering, Company-wide operations, or the full audit trail.

Members may create, edit, and delete Payments and Refunds under the complete ledger rules. Creating, editing, or deleting an Adjustment remains entirely Owner/Admin-only. The exact approved action matrix and enforcement requirements are defined in [Owner, Admin, and Member Permission Matrix](../architecture/role-permission-matrix.md).

Each company has exactly one owning Account and exactly one Owner membership, held by that Account's owner. Admin and Member memberships may be multiple.

Company invitations must be email-addressed, expire exactly 7 days after their most recent issue or resend, be revocable and single-use, and remain safe for both existing and newly registered users. Acceptance must attach the intended user to the intended company only. Invitations assign Admin or Member; Owner is assigned only through the explicit ownership-transfer workflow and must not be achievable through an ordinary invitation or membership-role change.

Company ownership must be transferable. A company should be movable from one account owner/account to another without rewriting or duplicating its business data.

During transfer:

- Existing company data remains intact.
- Existing company members remain attached by default.
- The destination must already be an Admin or Member of that Company; ownership cannot be transferred by entering an arbitrary registered email address.
- The destination account owner becomes the sole company Owner.
- The former Owner remains attached as Admin by default unless the confirmed transfer explicitly removes them.
- Destination-plan entitlements and limits are validated before the transfer commits.
- Historical business data must not remain tightly bound to the original account owner.

## 5. User and account settings

Separate overall user/account settings from company configuration.

User/account examples:

- Profile
- Application language
- Account-level preferences
- Plan and entitlements

Company examples:

- Legal and tax identity
- Bank accounts
- Branding
- Currencies and precision
- Document defaults
- Numbering
- Members

## 6. Company information

Company settings support at least:

- Legal name
- Trading name
- Address line 1
- Address line 2, optional
- City
- State/province/region
- Postal code
- Country
- Tax registration label, such as VAT ID, CUI, ABN, or EIN
- Tax registration identifier
- Business registration label
- Business registration number
- Email
- Phone
- Website
- Logo
- Multiple bank accounts

Company defaults include:

- Default currency
- Currency display style: ISO code or symbol
- Company timezone
- Company automation-local time, defaulting to `09:00`
- Default document language
- Payment terms
- Terms & Conditions
- Default tax rate/settings
- Default bank account
- Default quote validity
- Default invoice notes
- Default quote notes
- Document numbering settings
- Currency precision settings
- Default PDF email-delivery mode: secure link only or attach PDF; new Companies default to secure link only
- Public-link defaults

### Default resolution and snapshot timing

Resolve available company defaults into stored draft fields when a quote, invoice, or recurring template is created, and resolve customer defaults when a customer is selected. Selecting a product/service resolves its defaults into the line at selection time. Later source edits never propagate silently to an existing ordinary quote or invoice.

Recurring templates preserve whether each Customer-derived value is inherited or explicitly overridden. At every generation, explicit template/line overrides remain fixed; every inherited value resolves again from the current Customer, then the current Company fallback. This includes identity, address, registration, contacts, recipients, CC/BCC, delivery preference, currency, document language, payment terms, and default tax. Already-generated invoices never change.

Changing the selected customer on an in-progress document must show the resulting default/snapshot changes and require confirmation before replacing customer identity, currency, language, payment terms, tax defaults, recipients, or delivery choices. Users may explicitly reapply current defaults; existing lines and other manual edits remain unchanged unless the action clearly says otherwise.

## 7. Bank accounts

Bank accounts are part of company settings.

- A company can have multiple bank accounts.
- Each account requires a user-facing label, bank name, account holder, and IBAN/account number. Normalized uppercase SWIFT/BIC and the same-Company currency association are optional.
- Optional local routing details are limited to routing number, sort code, bank code, branch code, transit number, institution number, BSB, and IFSC. They remain a flat allowlisted object with at most eight trimmed non-empty values of at most 64 characters each; custom keys and nested/provider payloads are not accepted.
- A company may designate one default bank account. A quote or invoice may select a different account or omit bank details.
- Relevant bank information must be selectable for and displayable on quotes and invoices.
- Historical documents must retain the bank details that were issued on them even if the company later edits or removes the source bank account.

## 8. Customers

Support both company and individual customers.

Customer type is explicit:

- Individual: first name and last name
- Company: company/legal name with one or more contacts

A customer may have multiple contacts. Support primary-contact and billing-contact/default-recipient designation.

Each customer has one structured billing/legal address:

- Address line 1
- Address line 2, optional
- City
- State/province/region
- Postal code
- Country

Customer identity also supports:

- Phone number
- Optional general/primary email; for an Individual this may be the default recipient
- Optional external customer reference/code
- Tax registration label and identifier
- Business registration label and number
- Internal customer notes, limited to 5,000 characters

From the quote, invoice, or recurring-invoice-template editor, the user must be able to create a new customer without abandoning the document/template. Open the complete customer form in a vertically scrollable modal, preserve the in-progress work, and automatically select the newly created customer after a successful save.

Customer-level defaults:

- Currency
- Default document language
- Payment terms
- Tax settings
- Billing/default recipient selected from the customer's valid contact/email options
- Default CC recipients
- Default BCC recipients
- PDF email-delivery preference: secure link only or attach PDF

Do not build separate billing, service, or delivery addresses in v1.

Do not add an ambiguous free-form `legal info` field. Use structured registration fields and internal notes.

Do not add customer tags or a customer-specific date-format preference in v1. Document date formatting follows document language/locale.

Internal customer notes are limited to 5,000 characters and must never appear automatically on quotes, invoices, PDFs, public pages, or emails.

Quotes and invoices must snapshot customer identity, address, and registration information used on the document. Later customer edits must not silently rewrite existing documents.

Customer records with historical documents should normally be archived. If dependent historical data has been removed, permanent deletion should be possible so users can ultimately delete their data.

### Document customer reference / PO number

Quotes, invoices, and recurring-invoice templates may contain an optional customer reference / PO number. This document-level field is distinct from the customer's permanent external reference/code: it identifies the customer's reference for a particular commercial document or recurring arrangement.

Copy the field from a quote to each invoice created from that quote and from a recurring template to each generated invoice. The copied value remains independently editable. Include it in relevant quote, invoice, and recurring-template list searches and display it on customer-facing PDFs and public pages when present.

This field does not create a Purchase Order entity, vendor workflow, approval process, fulfilment state, PO matching, or purchasing module in v1.

## 9. Products & Services

v1 includes a lightweight company-specific Products & Services library for reusable quote, invoice, and recurring-invoice line defaults. It is a convenience catalog, not inventory or product-management software.

Each entry supports:

- Required name
- Optional description
- Optional internal code/SKU
- Optional default unit price
- Required ISO currency when a default price is present
- Optional default unit
- Optional default company tax preset
- Optional default billing period unit: None/N/A, Month, or Year
- Active or archived state

Catalog input is bounded for operational search, snapshots, and later document rendering: name 160 characters, internal code/SKU 120, default unit 80, description 5,000, and list search 120. These limits apply at both Laravel and PostgreSQL boundaries.

A missing default price means “enter the price on the document”; it is distinct from an explicit zero price.

Quote, invoice, and recurring-invoice editors must provide searchable product/service selection. Search at least by name and internal code/SKU, and include description when practical. Users must also remain free to enter document lines manually without creating catalog entries.

From those editors, users may create a product/service inline in a compact modal without losing the in-progress document. After a successful save, close the modal and select the new entry automatically. Validation failures retain both the modal values and document progress.

Selecting an entry copies its applicable values onto the document or recurring-template line. The resulting line remains completely editable and is a self-contained snapshot, not a live link. Editing or archiving the source entry must never rewrite existing quote lines, invoice lines, recurring-template lines, or invoices later generated from already-snapshotted recurring-template lines.

When selected, the product/service name and optional description initialize the line's customer-visible description, while price, unit, period unit, and tax initialize their corresponding line fields. Every copied value remains editable.

Copy the default price only when its currency matches the document currency. On a mismatch, copy the non-price defaults and require the user to enter or confirm the price. Never convert the price automatically because v1 has no foreign-exchange behavior.

Only Owner/Admin roles should manage catalog entries by default; the permission matrix may allow Members to search and use active entries. Once used, archive entries rather than hard-deleting them by default.

An active or archived Customer/Product default keeps its referenced Company currency or tax preset available until the reference is changed, cleared, or the dependent record is permanently removed. Source changes fail closed instead of silently replacing a stored choice with a fallback.

Reducing a Company currency's configured precision is blocked while any Product/Service price in that currency cannot be represented exactly at the proposed precision. The Company must edit those catalog prices first; the configuration workflow never silently rounds live defaults.

Do not include in v1:

- Product URLs or customer-visible product hyperlinks
- Inventory, stock counts, or stock movements
- Tags or categories
- Variants or bundles
- Supplier, purchasing, cost, or margin data
- Tiered or customer-specific price lists
- Product images
- CSV import/export

## 10. Quotations

Stored Quote lifecycle:

- Draft
- Sent
- Accepted
- Rejected

Expired is a separate derived customer-facing state.

Rules:

- A quote contains at least its company, customer snapshot, number, issue date, valid-until date, currency, document language, lines, Terms & Conditions, and notes. It may also contain a customer reference / PO number and selected bank-details snapshot.
- Draft quotes may be incomplete. Sending requires a customer, number, issue date, valid-until date, currency, language, and at least one valid billable line.
- Sending a Draft quote changes it to Sent only after the email provider accepts the dispatch. A later delivery failure does not revert the quote; an immediate dispatch failure leaves it Draft and records the failed attempt. Resending does not create a second lifecycle state.
- Expired is derived when the company-local date is later than `valid_until` and the quote has not been Accepted or Rejected. Expired public quotes cannot be accepted or rejected until an internal user extends validity or changes the status.
- Public Accept/Reject is allowed only for a non-expired Quote whose stored lifecycle is Sent. Replayed identical decisions are idempotent; an opposite later public decision is rejected.
- An authorized internal user may correct the stored Quote lifecycle to Draft, Sent, Accepted, or Rejected in either direction after confirmation, with a required reason and audit record. Expired remains derived rather than directly selected. Resending an Accepted or Rejected Quote is allowed after warning and does not change its lifecycle.
- Quotes remain editable after sending or acceptance. Editing does not silently reset the status; significant changes after sending or a customer decision are audited.
- v1 keeps one mutable current quote rather than a revision history. A successful edit updates the current public page and causes the current PDF to be regenerated from the edited persisted values. Email bodies or PDF attachments already delivered remain unchanged historical delivery artifacts. There is no version-history screen in v1.
- A quote may generate multiple invoices. Accepted is the normal conversion state. Owner/Admin may confirm an intentional override from Draft, Sent, or Expired. A Rejected quote cannot create an invoice unless its status is first changed to an allowed state.
- Each invoice created from a quote initially copies the quote's customer reference / PO number; editing the invoice value later does not rewrite the quote or sibling invoices.
- Linked invoices use the quote currency; v1 does not compare or allocate quote value across currencies.
- After a quote has linked invoices, its currency cannot change unless those links are removed through a valid workflow.
- A Quote-derived Invoice may be unlinked only while it remains Draft and has never been sent/issued, shared through a public link, or associated with a financial transaction. Unlinking requires confirmation and audit, leaves its copied data intact as an independent Draft, and recalculates the Quote allocation immediately. After any disqualifying activity, or once the Invoice is Issued or Cancelled, the provenance link is permanent.
- Track quoted amount, invoiced amount as the sum of non-Cancelled linked invoice totals (including Draft invoices), and remaining amount as quote total minus invoiced amount.
- Warn rather than block if generated invoices exceed the quotation total.
- Default validity is 30 days.
- Validity is configurable at company level and overridable per quote.
- Quote numbering is configurable, suggested automatically, and manually overridable.

Example:

```text
Quote total: €10,000
Invoice A:   €4,000
Invoice B:   €6,000
Remaining:   €0
```

## 11. Invoices

Customer-facing invoice state may display Draft, Issued/Unpaid, Partially Paid, Paid, Overdue, and Cancelled, but these must not be forced into one ambiguous stored enum.

- Lifecycle state is Draft, Issued, or Cancelled.
- Payment state is derived as Unpaid, Partially Paid, or Paid from the invoice total and net valid transactions.
- Overdue is a derived flag when an Issued, non-Cancelled invoice has a positive outstanding balance and its due date is earlier than the current date in the company timezone.
- An invoice may therefore be both Partially Paid and Overdue; the UI must preserve both facts.

Invoices may be created from quotations or independently. One quote may generate multiple invoices.

An invoice contains at least its company, customer snapshot, number, issue date, due date, currency, document language, lines, Terms & Conditions, and notes. It may also contain a customer reference / PO number and selected bank-details snapshot. Draft invoices may be incomplete, but issue/send requires all required fields and at least one valid billable line. Sending a Draft invoice must issue it before delivery; if dispatch fails, the invoice remains Issued and the failed email attempt is visible for retry.

Issued invoices remain editable. Users may edit customer, lines, quantities, prices, discounts, tax, currency, dates, and document metadata. Significant changes must be captured in the audit history.

v1 keeps one mutable current invoice rather than a revision history. A successful edit updates the current public page and causes the current PDF to be regenerated from the edited persisted values. Email bodies or PDF attachments already delivered remain unchanged historical delivery artifacts. There is no version-history screen in v1.

Financial edits recalculate payment state and outstanding balance. Do not allow an edit to reduce the invoice total below net paid without first recording the necessary refund or corrective adjustment; v1 must not silently create customer credit or an overpayment balance.

Do not allow invoice currency to change while valid payment/refund/adjustment transactions exist, because v1 has no FX conversion or transaction-currency migration.

An Issued invoice whose total is zero is immediately derived as Paid, is never Overdue, requires no payment, and receives no payment reminders.

Cancelling an invoice stops pending reminders and blocks all new payment, refund, and adjustment records while the invoice remains Cancelled. Cancellation preserves the invoice, its existing transaction history, and its audit history. Allow cancellation only when net paid is exactly zero; when net paid is positive, require the necessary refund or corrective adjustment before cancellation. No invoice may be permanently deleted while any linked transaction records remain, regardless of lifecycle state.

Cancelled invoices can be reopened and changed in v1. Reopening returns the Invoice to Issued, preserves its number, dates, transactions, audit history, and public-link identity, and never sends email automatically. Transactions remain read-only while Cancelled and regain ordinary validated edit/delete eligibility after reopening. A valid non-revoked link remains viewable and clearly shows Cancelled until reopening. Reopening never repeats sent reminders: past before-due reminders become stale, and when already overdue only the newest currently eligible after-due reminder is scheduled for the next company automation time. It requires confirmation, a reason, audit history, and the role authorization assigned by the permission matrix.

This flexibility is intentional. Do not enforce country-specific legal or accounting restrictions in v1.

## 12. Document numbering

Invumo should help users manage sequential quote and invoice numbers but must not enforce accounting-law numbering rules.

Example:

1. Invoices 1, 2, and 3 exist.
2. The user deletes invoice 2.
3. The user renames invoice 3 to invoice 2.
4. The system recognizes the highest/current relevant number as 2 and suggests 3 next.

The user may always override the suggestion.

Requirements:

- Recommend the next logical number automatically.
- Allow manual numbering and renumbering.
- Allow deletion where permitted by the application.
- Record meaningful numbering changes in audit history.
- Do not silently reuse numbers without clear user intent.
- Warn whenever a quote or invoice number duplicates another non-deleted document in the same company and document type; do not silently block an intentional override.
- Start each Company with `Q-{YEAR}-{NUMBER}` for Quotes and `I-{YEAR}-{NUMBER}` for Invoices, while allowing each pattern to be customized independently, for example `INV-{NUMBER}`.
- Avoid overengineering the numbering engine.

Quote and invoice sequences are separate per company. Numeric-component parsing, reset periods, manual-override behavior, and concurrency control are defined below and in the approved architecture specification. Manual changes must not silently move the sequence backwards.

The approved mechanism is a per-company/per-document-type number series with a counter row per reset period. A pattern is at most 120 characters, permits literal text, requires `{NUMBER}` exactly once, permits `{YEAR}` at most once, and rejects control characters and unknown braces. `{YEAR}` resolves automatically to the current four-digit Company-local year. Padding is a separate 1–12 setting with a default of 4. Reset policy defaults to never and may instead be Company-local annual; the year token does not imply a reset, while annual reset requires the year token so a restarted counter cannot render duplicates from a prior year. Settings previews are server-anchored to the Company timezone, and assigned numbers remain persisted rather than changing when the year changes.

Clicking New Quote or New Invoice creates a persisted Draft and allocates its number in the same PostgreSQL transaction. The allocator locks the relevant counter row with `SELECT ... FOR UPDATE`, finds the next unoccupied automatic candidate, inserts the Draft, advances the counter, and commits. A unique idempotent creation key makes a retried request return the same Draft. This is an assigned number, not an unreserved browser preview.

Manual numbering and intentional duplicates remain possible after warning. Renumbering, deletion, and manual entry do not move the automatic counter by default. An explicit authorized continuation/realignment action may change it under the same lock, including moving it backwards after a clear reuse warning and audit event. See [Document Numbering and Concurrency](../architecture/numbering-and-concurrency.md).

## 13. Quote and invoice lines

Lines may be entered directly or initialized from the Products & Services library. Catalog selection only copies defaults; document lines remain authoritative, editable snapshots.

Line fields:

- Line number/order
- Customer-visible description, initialized from a selected product/service name and optional description when applicable
- Item price
- Quantity
- Unit (optional)
- Items subtotal
- Period quantity
- Period unit
- Items total
- Discount percentage
- Discount value
- Grand subtotal
- Tax
- Final line total

Quantity supports decimals.

Do not support free-form header/text rows between billable lines. Free-form document text may appear before or after the line table.

## 14. Period calculation

Period units:

- None / N/A
- Month
- Year

Period quantity is stored separately. Examples include N/A, 1 month, 12 months, 1 year, and 3 years.

Do not automatically convert years to months. The item price may itself be defined for the user's selected period.

If period is N/A, its multiplier is 1.

Calculation:

```text
let p = the document's stored currency precision

items subtotal = round(item price × quantity, p, HALF_UP)

if period exists:
    items total = round(items subtotal × period quantity, p, HALF_UP)
else:
    items total = items subtotal

discount value = round(items total × discount percentage ÷ 100, p, HALF_UP)
grand subtotal = items total − discount value
tax value = round(grand subtotal × line tax rate ÷ 100, p, HALF_UP)
final line total = grand subtotal + tax value
```

Example:

```text
Item price:       €100
Quantity:         10
Period:           12 months
Items subtotal:   €1,000
Items total:      €12,000
Discount:         10% = €1,200
Grand subtotal:   €10,800
Tax at 20%:       €2,160
Final line total: €12,960
```

Laravel is authoritative and uses `brick/math` with `BigDecimal::toScale($scale, RoundingMode::HALF_UP)` at every specified rounding step. Rounding each specified step instead of only the final total is intentional so printed PDF amounts reconcile when customers check the calculation by hand. React uses equivalent exact-decimal preview behavior; financial values cross the browser boundary as decimal strings, never binary floating-point numbers.

Document totals aggregate line snapshots only:

```text
document_subtotal = sum(line.grand_subtotal)
document_tax_total = sum(line.tax_value)
document_total = sum(line.final_line_total)
```

Do not reround document totals or allocate residual cents in v1. The approved storage types, precision snapshots, validations, and cross-runtime verification rules are defined in [Calculation, Decimal Precision, and Rounding](../architecture/calculation-and-rounding.md).

v1 has no overall document discount, invoice-wide tax, or document-level fee. These may be considered later only with an explicit ordering and rounding specification.

## 15. Discounts

- Support discount percentage per line.
- Calculate discount value automatically.
- Do not support an overall document discount in v1.
- Never silently produce ambiguous totals.

## 16. Tax

For v1, use tax per line only. Do not implement invoice-wide tax.

- Each line may have its own tax rate.
- Company and customer settings may provide defaults.
- A selected product/service may provide a default.
- Users may override defaults on individual document lines.
- Prices are tax-exclusive.

Each company maintains reusable tax-rate presets with:

- Name, such as VAT, TVA, or GST
- Percentage, including 0%
- Optional default designation
- Active or archived state

Users can add, edit, and archive presets. A referenced preset should be archived rather than hard-deleted.

When a preset is applied, copy its name and percentage onto the document line. Editing or archiving the preset later must not alter existing quotes or invoices.

Initial line-tax precedence is: explicit line choice, selected product/service default, customer default, then company default. Once copied, the line snapshot is authoritative.

On quotes, invoices, public pages, and PDFs, display both the applied tax name and percentage, for example `VAT 19%`. Do not add a separate visibility toggle in v1.

Do not implement:

- EU reverse charge
- Intra-community VAT
- VAT exemption workflows
- Country-specific tax engines
- Peppol
- Factur-X
- XRechnung
- RO e-Factura

Invumo is worldwide, but v1 offers flexible defaults rather than claiming legal compliance.

## 17. Currency

- Each company has a default currency, and each customer may override it.
- Initial document-currency precedence is document choice, customer default, then company default.
- Users may override currency per quote or invoice.
- There is no foreign-exchange conversion or exchange-rate service in v1.
- Decimal precision is user-configurable per currency from 0 through 8.
- Every quote and invoice snapshots its resolved currency precision. Quote-to-invoice conversion preserves the Quote precision. A recurring template stores its explicit precision override or inheritance intent: an inherited currency uses the current Customer currency and current configured precision at each generation, while an explicit template currency/precision remains fixed. Already-generated Invoices never change.
- Each company chooses whether monetary amounts primarily display an ISO currency code, such as `USD`, or a currency symbol, such as `$`.
- Currency display preference never changes the stored currency code or monetary value.
- Do not enforce ISO precision as mandatory behavior.
- Store unit prices and monetary values in PostgreSQL `numeric(30,8)`, quantities in `numeric(20,6)`, and percentage-point rates in `numeric(12,6)`, with stored calculated amounts quantized to the document precision.

Examples:

- EUR → 2 decimals
- USD → 2 decimals
- JPY → 0 decimals
- Any supported currency → user-selected precision

The complete authoritative rules are defined in [Calculation, Decimal Precision, and Rounding](../architecture/calculation-and-rounding.md).

## 18. Payment terms and Terms & Conditions

Payment terms and quote validity are stored as a non-negative whole number of calendar days after the issue date. Payment terms use document override, then customer default, then company default.

There is no arbitrary v1 maximum offset. The resulting stored date must remain inside the inclusive application date range `0001-01-01` through `9999-12-31`.

Persisted offsets are technically bounded from `0` through `3,652,058`, the total number of days in that approved application range. This is not a shorter business-policy maximum; every derived date must still fit the issue-date-specific remaining range before it is accepted.

The invoice due date and quote valid-until date derive automatically from the applicable day offset but remain editable. Neither resolved date may be before its document's issue date.

Terms & Conditions are separate customer-visible document content, not payment-term logic and not general notes.

- A company may define default Terms & Conditions.
- Each quote or invoice inherits the company default.
- The user may override the content per quote or invoice.
- Quote and invoice notes use their respective company default and remain overridable per document.
- Notes, Terms & Conditions, and structured payment terms must remain distinct concepts. Notes are an ordinary customer-visible document block, not a fixed PDF footer or arbitrary footer builder.
- Terms & Conditions allow at most 20,000 characters; Quote and Invoice notes allow at most 5,000 characters each. Company defaults, Customer overrides, document overrides, and stored snapshots share these limits.

## 19. Transactions and payments

Keep payments simple.

```text
Invoice issued
→ customer pays
→ user records payment
→ payment appears on invoice
→ payment appears in Transactions
```

One invoice may have one payment or multiple partial payments.

Transaction fields:

- Type/direction: payment, refund, or explicit adjustment
- Amount
- Currency
- Date
- Payment method
- Reference
- Notes

Store transaction amounts as non-negative values and represent financial direction explicitly. Do not overload a negative amount to mean both refund and correction.

Net paid equals payments plus positive adjustments minus refunds and negative adjustments. Outstanding balance equals invoice total minus net paid. Prevent ordinary payments from making net paid exceed the invoice total. Refunds cannot exceed actual cash recorded as paid and not already refunded; positive adjustments change the invoice balance but never create refundable cash. An explicit adjustment requires a reason and audit entry and must not silently produce a negative net-paid amount.

Do not build expenses, general accounting transactions, a chart of accounts, or a bookkeeping ledger. The Transactions page is the global company-level view of invoice payment transactions.

Invoice payment state derives from net valid payments, refunds, and explicit adjustments. Prevent ordinary payments from unintentionally exceeding the outstanding balance. If refunds make the balance unpaid again, update the derived state.

Executable Payment, Refund, and Adjustment amounts must be strictly positive and may be created, edited, or deleted only while the Invoice is Issued. Every mutation recomputes and validates the complete ledger: net paid, refundable cash, and outstanding balance must remain non-negative, and net paid cannot exceed the Invoice total. Payments and positive adjustments cannot exceed outstanding; Refunds cannot exceed both actual refundable cash and net paid; negative adjustments cannot exceed net paid. Zero-total Invoices reject every financial row. Transaction dates may precede issue for advance or backfilled records but cannot be later than the current Company-local date. A previously sent receipt does not freeze a transaction; later corrections remain possible after warning, complete validation, and audit, while the delivered email remains historical.

After recording a payment, the user may optionally send a payment-received email. Never send it automatically for historical/backfilled payments without clear user intent.

## 20. Recurring invoices

Recurring invoices use a dedicated template entity:

```text
Recurring invoice template
→ scheduled execution
→ create invoice
→ issue invoice
→ generate PDF
→ send customer email
```

The template itself is not an invoice. Editing it affects only future invoices; previously generated invoices remain unchanged.

Every recurring template has a required internal name, such as `ACME monthly hosting`. It is used in internal lists, search, selection, and audit history, does not need to be unique, and is never copied to generated invoices or exposed in PDFs, public pages, or customer email.

Recurrence options:

- Weekly
- Monthly
- Quarterly
- Yearly
- Custom interval

Support a start date, optional end date, and optional maximum occurrence count.

Templates have Draft, Active, Paused, and Completed states. Only Active templates execute. Activating with a past start date schedules the first occurrence on or after activation and never creates historical invoices for time before activation. Pausing prevents future executions; resuming continues from the next eligible occurrence and does not backfill missed occurrences unless the user explicitly requests it. Completed is terminal in v1; duplicate a Completed template into a new Draft to continue the arrangement.

A recurring template inherits company invoice defaults and may override:

- Payment terms and due-date calculation
- Terms & Conditions
- Notes
- Email delivery settings
- Reminder rules

The template may also store an optional customer reference / PO number for the recurring arrangement.

Generated invoices use the normal company invoice numbering sequence. Do not create a separate recurring-invoice document sequence.

Generated invoices materialize the current inherited Customer values, explicit template/line overrides, customer reference / PO number, and reminder schedule. Inherited values refresh identity, address, registration, contacts, delivery, currency, document language, payment terms, and default tax from the current Customer, then Company fallback. Explicit template/line overrides remain fixed. Template edits affect only future occurrences, and neither later template/default/customer edits nor source deletion rewrites an already-generated invoice.

If inherited currency changes, keep the template line inputs' numeric values, recalculate and round the generated Invoice with the current currency precision, and do not perform FX conversion. An explicitly overridden template currency remains fixed. A line with an explicit tax remains fixed; only a line marked to inherit Customer tax uses the current Customer tax default.

For templates with automatic email enabled, retain the last confirmed delivery currency. The first eligible occurrence establishes the initial baseline. If a later inherited currency differs, still generate and issue the Invoice but suppress its automatic email and display **Currency changed — review required** on both the Invoice and template. Later occurrences also remain issue-only until a user reviews and successfully sends one of the affected Invoices manually; provider acceptance confirms its currency as the new baseline for future automatic delivery. Explicit template currency overrides do not trigger this gate, and no FX conversion occurs.

Each template has an automatic-email setting. Scheduled invoices are created and issued; when automatic email is enabled and no safety gate suppresses delivery, they are also delivered using the resolved customer/template settings. When disabled or suppressed, the issued invoice remains available for manual sending.

If automatic email fails after invoice creation, retry delivery against the same generated invoice; never create a replacement invoice for the same occurrence.

Permanently deleting an eligible generated Invoice uses the ordinary guarded Invoice-deletion workflow and also removes its linked occurrence and pending occurrence-dispatch state. Deletion never rewinds the template cursor, logical ordinal, or successful-occurrence count, and stale work for the removed occurrence exits without recreating it. Later distinct occurrences continue normally within the template's schedule, end date, and maximum count. Cancelling a generated Invoice retains both the Invoice and occurrence and does not stop later scheduled occurrences.

Use the approved PostgreSQL-backed Laravel queue with one supervised PHP worker and the Laravel scheduler invoked every minute by cron; do not add an external message broker. Each occurrence has a stable idempotency key and a database uniqueness constraint so retries or overlaps create at most one invoice. Record scheduled local/UTC time, actual execution, attempts, outcome, next run, and generated invoice.

Calculate recurrences from company-local calendar rules at the company's automation time, then resolve them through its IANA timezone into UTC. Never advance monthly/quarterly/yearly schedules by adding fixed UTC durations. A nonexistent spring-forward local time shifts forward by the DST gap; an ambiguous fall-back time uses its first occurrence and executes once.

After service downtime, recover every occurrence that became due while the template remained Active, oldest first and in bounded batches, preserving the scheduled local issue date. Intentional pause time is not backfilled without explicit confirmation. Transient failures use the approved bounded retry schedule; permanent configuration failures stop visibly. See [Scheduling, Recurrence, Reminders, and Downtime](../architecture/scheduling-and-jobs.md).

A permanent occurrence failure deliberately leaves the template Active at the failed ordinal so no billed period is skipped. Owner/Admin receive a Company-wide recurring-attention count in primary navigation and can open a dedicated failed-template filter before using the existing same-occurrence retry action; routine manual inspection is not the only detection path.

## 21. Localization

Both the application UI and generated documents support multiple languages.

Launch languages:

- English
- Romanian

Adding languages later should be straightforward.

The supported-locale list in `config/localization.php` is the only application allowlist. PostgreSQL validates a safe bounded locale-code shape without embedding a second list of supported languages, so adding an authored locale does not require a catalogue-only database migration.

Initial document-language precedence is document choice, customer default, then company default. The signed-in user's application language affects the internal UI only and never silently changes a customer document's language.

Changing document language localizes system-generated terminology, date formatting, number formatting, and locale-specific presentation. User-written descriptions remain exactly as entered; do not translate user content automatically.

Each company has an IANA timezone. Store timestamps in UTC and interpret company schedules, including recurring invoice execution, in the company's timezone.

Do not add a separate manual date-format setting in v1. Document date formatting follows the selected document language/locale.

## 22. PDFs

Quotes and invoices must generate professional downloadable PDFs.

v1 includes one excellent document template with:

- Company logo and details
- Company primary brand color
- Customer details
- Document metadata, including the customer reference / PO number when present
- Lines and totals
- Relevant bank information
- Terms & Conditions
- Notes

Design the PDF system so more templates can be added later without rewriting the document model. Do not build a PDF template editor in v1.

## 23. Email

Send transactional email through Zoho ZeptoMail. Foundational account email continues through the production ZeptoMail SMTP transport over authenticated TLS. Quote, Invoice, reminder, and payment-received delivery uses ZeptoMail's HTTPS Send API under the approved [email delivery and webhook contract](../architecture/email-delivery-and-webhooks.md).

Quote and invoice emails include:

- Sensible predefined multilingual subject
- Sensible predefined multilingual body
- Editable subject before sending
- Editable body before sending

Company email templates exist per event and language for:

- Quote sent
- Invoice sent
- Payment reminder
- Payment received

Each template supports:

- Subject
- Body
- Button label
- Plain-text company email signature
- Preview
- An allowlisted set of placeholders, including relevant customer, company, document, amount, due-date, and public-URL values

Template content is authored as plain text and rendered through Invumo's safe HTML email shell. Subject is limited to 500 characters, body to 20,000, button label to 80, and signature to 5,000. Placeholders use the exact `{{snake_case_name}}` syntax and are restricted per event.

Provide safe multilingual system defaults. Companies may override them. Reject or clearly identify unknown placeholders, escape substituted content for its output context, and fall back safely when an optional value is unavailable.

Direct quote/invoice sends remain editable per send. Automated reminder sends use the saved template for the document language.

Delivery defaults follow this precedence:

1. Per-send override
2. Customer preference
3. Company default

Delivery preferences include:

- Primary/default recipient
- Optional multiple CC recipients
- Optional multiple BCC recipients
- Secure-link-only or attach-PDF mode

The Company fallback defaults to secure link only. Owner/Admin may change it to attach PDF; later Customer and per-send choices follow the precedence above.

The send composer must display the resolved recipients and attachment choice before sending and allow the user to override them for that send.

Sending requires at least one valid primary recipient. Validate and deduplicate To/CC/BCC addresses. An automated send with no valid recipient must fail visibly and record the reason rather than retrying indefinitely or silently succeeding.

Editable direct sends remain transactional rather than bulk mail: one message is limited to ten recipients, and every initial or retry submission consumes bounded Company, Account, and shared-provider recipient budgets immediately before the provider call. A suspended initiating User or Account, an archived Company, or an exhausted budget must stop the provider call and remain visible in delivery history.

Track Sent, Delivered, bounced, Opened, and clicked provider events under the approved privacy-minimal webhook contract. Authenticate webhooks and process duplicate or out-of-order provider events idempotently. An ambiguous send outcome is never retried automatically; show it as potentially delivered and require an authorized, warned manual retry.

The company's primary brand color may be used for restrained accents in transactional email where client compatibility and readable contrast permit it.

Do not build customer SMTP or a marketing-email system in v1.

### Automated invoice reminders

Companies may configure multiple reminder rules relative to the invoice due date:

- Number of days
- Before or after the due date
- Enabled/disabled state
- Associated reminder email template/language behavior

Invoices may disable or override inherited reminders. Recurring templates may also override the company defaults for invoices they generate.

When an invoice is issued, materialize its reminder schedule from the applicable rules. Company-default changes affect future schedules unless the user explicitly reapplies them.

Reminder processing must:

- Use the company timezone
- Stop unsent reminders when an invoice becomes Paid or Cancelled
- Continue for a Partially Paid invoice while a positive outstanding balance remains
- Recalculate pending reminders when the due date changes
- Prevent duplicate sends under retries or overlapping scheduler runs
- Record sends and failures in invoice/email history

Reminder instances use the company automation-local time, default `09:00`, and retain their local date/time, timezone, resolved UTC time, idempotency key, attempts, and outcome. Recheck invoice lifecycle, balance, due date, recipients, and public-link eligibility immediately before sending.

After downtime, a before-due reminder sends only while the due date has not passed. An after-due reminder may send while the invoice remains overdue and outstanding; if several accumulated, send only the newest eligible instance and mark the older ones superseded. Paid/Cancelled suppression and stale/superseded outcomes remain visible rather than disappearing.

After one initial attempt, transient failures retry up to five times: after 1 minute, 5 minutes, 15 minutes, 1 hour, and 6 hours. If the final retry fails, mark the operation failed, expose it for operational review, and permit an authorized retry using the same idempotency key. Permanent validation/configuration failures fail visibly without indefinite retries. See the approved [scheduling specification](../architecture/scheduling-and-jobs.md).

## 24. Public quote and invoice pages

Both quotes and invoices support secure public links, such as:

- `app.invumo.com/q/<secure-token>`
- `app.invumo.com/i/<secure-token>`

Public document pages belong to the SaaS application host, not the separate `invumo.com` marketing website. The exact route and token-bootstrap behavior follows the approved [public-token and access contract](../architecture/public-token-and-access.md).

Do not expose predictable database IDs.

Public links are:

- Sufficiently random and unpredictable
- Valid for 30 days by default
- Configurable by the user
- Revocable
- Regeneratable

Quote validity and public-link expiry are separate concepts. A link may be technically valid while the quote is commercially expired; public actions still follow quote validity rules.

Email delivery must not include an expired token. A direct send must create or confirm a valid link. An automated reminder may replace a naturally expired link, but it must never recreate access that a user explicitly revoked without re-enabling public access.

Public invoice pages support viewing and PDF download without a customer account.

Public quote pages support viewing, PDF download, Accept, and Reject.

Accepting or rejecting requires the customer's name and email address. Record the decision timestamp and privacy-safe audit metadata without IP address or User-Agent. Owner/Admin can irreversibly erase the retained name/email for every decision tied to a Customer while retaining the immutable decision facts and Quote.

Do not add electronic signatures in v1.

## 25. Quote acceptance

When a customer accepts or rejects through a public link:

- Update quote status accordingly.
- Record the event in the audit trail.
- Prevent duplicated or replayed actions from producing inconsistent records.
- Permit an internal user to change quote status manually when needed.

Flexibility should generally win over rigid workflows.

## 26. Dashboard

Keep the dashboard extremely simple. Show at least:

- Unpaid invoices
- Overdue invoices
- Paid this month
- Outstanding total
- Recent invoices

Do not build analytics or reporting dashboards in v1.

Dashboard calculations must respect company and currency boundaries. Do not add amounts in different currencies together without an explicit, mathematically valid basis; Invumo has no FX system.

When a company has multiple document currencies, show operational totals grouped by currency rather than a misleading combined total.

For v1 dashboard semantics, an unpaid Invoice is an Issued Invoice with a positive outstanding balance, including a Partially Paid Invoice. Paid this month is the gross value of Payment rows whose transaction date falls in the current Company-local calendar month; Refunds and Adjustments are excluded. Outstanding and overdue amounts use the authoritative Invoice ledger and remain grouped by currency.

## 27. Audit history

Maintain an audit trail for significant business operations, including:

- Invoice creation, issue, edits after issue, cancellation, deletion, and number changes
- Quote creation, edits, acceptance, and rejection
- Product/service creation, edits, and archiving
- Payment creation, deletion, and changes
- Reminder scheduling, sending, suppression, and failure where material
- Public-link generation, revocation, and regeneration
- Company transfer
- Important settings and membership changes

Audit history should answer what happened, when, who caused it, and what object was affected. Retain enough before/after information to make important edits understandable.

Audit infrastructure is a foundation capability, not a final-phase retrofit. Records must distinguish user actions, authenticated public-customer actions, provider webhooks, scheduled jobs, and other system actions.

Do not introduce a complex event-sourcing architecture unless independently justified.

## 28. Deletion and archiving

Customers, products/services, and companies with historical dependencies should normally be archived. Permanent deletion is allowed after dependent data has been removed and deletion is valid.

A Quote may be permanently deleted in any lifecycle state only when it has no linked Invoice. An Invoice may be permanently deleted in Draft, Issued, or Cancelled only when it has no Payment, Refund, or Adjustment rows. Prior sending, public sharing, issuing, or customer decision triggers a stronger warning but does not independently prevent deletion. Deletion revokes public access, suppresses pending reminders/jobs, performs the schema-defined delivery cleanup/retention, writes a minimal audit tombstone, and never rewinds or silently reuses its number.

Users must ultimately be able to delete their data. Do not impose artificial permanent-retention rules in v1 unless technically required. Use clear warnings before destructive actions.

## 29. UI and UX

Use the centralized [Invumo Design System Contract](../design/design-system.md) for every internal page, shared component, public-page foundation, and customer-facing document-presentation foundation. The approved identity is modern, light-mode, high-clarity, and achromatic except for the three semantic state hues. It is a system-wide contract, not a collection of independently styled screens.

React pages compose shared token-backed components. Do not hard-code colours in feature code, create page-specific typography/control/status treatments, copy shared components to preserve a local appearance, or use Company branding to theme the internal application. A change belongs in the single owning token or component so that it propagates everywhere.

Requirements:

- Clean
- Modern
- Minimal
- High information clarity
- Little visual noise
- Fast workflows
- Desktop-first
- Usable on smaller screens
- Light mode only in v1

The v1 internal identity uses a near-black sidebar, white/light-neutral workspace, ink actions, Atkinson Hyperlegible Next for interface text, and Atkinson Hyperlegible Mono with tabular figures for financial and identifying data. Lime, red, and amber are the only saturated hues and communicate approved semantic state; no fourth saturated hue or generic internal brand-primary colour is allowed.

Do not use old-ledger, paper, bookmark, book, rubber-stamp, skeuomorphic, or nostalgic accounting motifs. Compact status badges are modern digital controls rather than literal stamps.

Avoid excessive dashboards, unnecessary cards, excessive modals, decorative complexity, and enterprise-style navigation overload.

Primary navigation should remain small. A possible starting point is:

- Dashboard
- Customers
- Products & Services
- Quotes
- Invoices
- Recurring
- Transactions
- Settings

Analyze the UX and propose the simplest navigation. Users should create an invoice or quote with very few interactions.

Customer, product/service, quote, invoice, recurring-template, and transaction lists need basic company-scoped search, relevant status filters, stable sorting, pagination, and clear empty states. Quote and invoice search includes document number, customer, and customer reference / PO number. Recurring-template search includes its internal name, customer, and customer reference / PO number. These are operational list controls, not analytics/reporting.

### Company appearance in v1

Appearance settings remain intentionally small:

- Company logo
- One primary brand color
- A small set of safe color presets
- A custom color picker/hex value
- A simple document/public-page preview

New Companies start with neutral ink `#14181C`. The safe preset shortcuts are Ink `#14181C`, Navy `#1E3A5F`, Forest `#1F5D42`, Burgundy `#7F1D1D`, and Violet `#5B3A8E`. Presets do not restrict the persisted value: a Company may save any canonical uppercase `#RRGGBB` color, and future presets can be added without a schema or data migration.

Apply the primary brand color to outward-facing PDFs, public quote/invoice pages, and restrained transactional email accents. Do not theme the internal Invumo dashboard or application per company.

Validate custom colors and maintain readable foreground/background contrast. The shared resolver selects black or white for the best foreground contrast on a colored background. If the chosen color is unsafe against white for outward text or rules, use neutral ink for that context while preserving the Company's saved color.

## 30. Settings hierarchy

Clearly separate account/user settings from company settings.

Account/user settings include profile, account preferences, application language, and plan/entitlements.

Company settings include legal details, structured address, customizable registration labels, timezone, automation-local time, tax presets, bank accounts, logo, primary brand color, currencies, currency display style, precision, numbering, document defaults, language, payment terms, quote validity, email defaults, members, and public-link defaults.

Platform Operations is a separate internal workspace. It is not placed in Account or Company settings and is not shown to ordinary Users.

A user switching between companies must always understand which company is active.

## 31. Security

Security is critical because Invumo is multi-tenant financial/business software.

Address at least:

- Tenant isolation
- Authentication
- Email verification, password recovery, invitation-token safety, and session revocation
- Authorization
- Role-based access
- Secure session handling
- CSRF where applicable
- XSS
- SQL injection
- Secure public tokens
- Appropriate rate limiting
- Secrets management
- Webhook authentication
- File and logo upload validation
- Sensitive logging
- Ownership-transfer safety
- Platform-operator isolation, last-operator protection, suspension safeguards, and platform-audit integrity

Never rely solely on client-side authorization. Every server-side business-data operation must verify company membership and permission.

Do not rely solely on application query scoping either. Tenant-owned business tables use forced PostgreSQL Row-Level Security as a second layer. Use a non-owner, non-`BYPASSRLS` runtime role and a separate schema-owner/migration role. Establish `app.current_company_id` transaction-locally only after membership/permission validation; missing context denies tenant-row access. Queue jobs establish and clear the same context. Public-token and global-scheduler bootstrap use narrow, non-enumerating paths rather than a general RLS bypass. See [Tenant Isolation and PostgreSQL Row-Level Security](../architecture/tenant-isolation.md).

## 32. Database

Use PostgreSQL from day one. Prefer a normalized, understandable relational schema and do not introduce database-per-tenant architecture without a compelling reason.

Every domain entity uses a PostgreSQL-native `uuid` primary key generated as UUIDv7 by Laravel before insertion. All domain foreign keys, including `company_id`, use native `uuid` columns. Framework infrastructure tables may retain framework-native identifiers, and a pure identity-free join may use a composite key. UUIDs are never authorization credentials, public-link tokens, or substitutes for human-facing document numbers. See [Domain Identifier Policy](../architecture/identifier-policy.md).

Likely concepts include:

- Users
- Authentication/session/recovery records or provider mappings
- Accounts
- Plans/entitlements
- Platform operators and append-only platform audit events
- Companies
- Company members
- Company invitations
- Company settings
- Company currency/precision settings
- Company numbering series
- Company numbering counters by series/reset period
- Bank accounts
- Company tax-rate presets
- Customers
- Customer contacts
- Company products and services
- Quotes and quote lines
- Invoices and invoice lines
- Transactions/payments
- Recurring invoice templates, including their internal names and optional customer reference / PO numbers, lines, and execution occurrences
- Company email templates
- Invoice reminder rules and scheduled reminder instances
- Minimal cross-tenant scheduling-dispatch records containing no customer/financial payload
- Public document links
- Email delivery events
- Audit events
- Uploaded company assets or their storage metadata

These names are conceptual, not mandatory. Analyze the domain and choose the cleanest schema.

Every tenant-owned business table, including child tables, stores a non-null UUID `company_id`. Use same-company composite UUID foreign keys so a child cannot reference a parent belonging to another company. PostgreSQL-specific isolation and concurrency tests must run against the restricted runtime role; SQLite is not an acceptable substitute for these tests.

## 33. Data integrity

Pay particular attention to:

- Tenant isolation
- Monetary precision and rounding
- Invoice and quote totals
- Discounts and taxes
- Partial payments and refunds
- Recurring invoice generation
- Number suggestions and concurrent document creation
- Document language and currency precision
- Duplicate scheduled execution
- Duplicate email and webhook events
- Duplicate or stale reminder execution
- Historical snapshots of applied tax and bank details
- Historical snapshots of customer identity, address, and registration details
- Product/service selection snapshots and currency-mismatch behavior
- Customer reference / PO-number inheritance and search behavior without a Purchase Order entity
- Internal recurring-template names remaining non-customer-visible
- Derived invoice payment/overdue state and company-local date boundaries
- Invoice cancellation guards and retention of linked transaction history
- Quote expiry versus public-link expiry
- Transaction direction, refund limits, and outstanding-balance derivation
- Company-timezone scheduling across daylight-saving transitions
- Stale Quote/Invoice editor saves and concurrent financial mutations

Use transactions for business-critical operations where appropriate.

Quotes and Invoices use a monotonically increasing edit version. Reject a save based on an older version with a clear reload/review message instead of silently overwriting a newer edit. Financial mutations additionally lock and recalculate the complete Invoice aggregate in one database transaction. The full approved behavior is defined in [Quote, Invoice, and Financial State Specification](../architecture/document-and-financial-state.md).

## 34. Testing

Create automated tests for critical calculations and workflows, especially:

- Registration verification, password recovery, session invalidation, and invitation expiry/revocation/single use
- Company switching, ownership transfer, and cross-company access denial
- Platform-role isolation, plan-lifecycle boundaries, User/Account suspension, and last-operator protection
- Full-action impersonation with exact target-User permissions/RLS, no nested session, safe exit, persistent identity warning, real permitted effects, and original-operator/effective-User audit attribution
- Invoice line and period calculations
- Discounts and taxes
- Tax-preset snapshot behavior
- Customer snapshot behavior
- Searchable product/service selection in quote, invoice, and recurring-template editors
- Product/service snapshot behavior after source edits or archiving
- Inline product/service creation without losing document progress
- Product-price behavior when catalog and document currencies differ
- Default precedence for currency, language, tax, payment terms, notes, bank accounts, and delivery settings
- Document totals and rounding
- Partial payments
- Invoice status transitions
- Combined Partially Paid and Overdue presentation
- Refunds
- Transaction direction, overpayment prevention, and refund limits
- Next-number suggestion
- Concurrent automatic Draft creation, annual counter creation, idempotent creation retries, manual duplicates, and explicit counter realignment
- Quote to multiple invoices
- Customer reference / PO-number inheritance from quote to invoice and recurring template to generated invoice
- Customer reference / PO-number search and conditional PDF/public-page rendering
- Recurring invoice generation
- Recurring execution in the company timezone, including daylight-saving transitions
- Recurring downtime catch-up, overlapping workers, retry exhaustion, and pause periods that do not backfill implicitly
- Tenant isolation
- PostgreSQL RLS default-deny behavior, cross-company reads/writes, same-company foreign keys, queue context reset, public-token bootstrap, and restricted runtime/migration roles
- Role authorization
- Public quote acceptance
- Quote validity expiry and reactivation after an authorized validity change
- Public-link expiry and revocation
- Full customer creation in quote, invoice, and recurring-template editor modals without losing in-progress data
- Customer email-recipient and PDF-attachment default precedence
- Recipient validation/deduplication and missing-recipient failure for automated sends
- Email-template placeholder validation and language fallback
- Quote/Invoice lifecycle behavior for immediate dispatch failure, later delivery failure, and retry
- Reminder materialization, before/after-due scheduling, due-date changes, and duplicate suppression
- Stale before-due and superseded after-due reminder behavior after downtime
- Reminder cancellation when an invoice becomes Paid or Cancelled
- Invoice cancellation at zero net paid, rejection at positive net paid, blocking of new transactions, and deletion prevention while linked transactions remain
- Optional payment-received email behavior for current versus historical payments
- Recurring-template inheritance of invoice defaults, email settings, and reminders
- Required recurring-template internal name, internal searchability, and exclusion from customer-facing output
- Recurring pause/resume, optional automatic email, missed-run behavior, and one-invoice-per-occurrence idempotency
- Expired versus explicitly revoked public-link behavior during direct and automated email
- Audit attribution for user, public-customer, webhook, scheduled-job, and system actors

Implement browser-level tests for the main journey:

```text
Register
→ create company
→ add customer
→ add a product or service
→ create quote
→ send quote
→ accept quote
→ create invoice
→ issue invoice
→ record payment
→ invoice becomes paid
```

## 35. Development philosophy

The approved baseline is one Laravel 13 modular monolith on PHP 8.5, with React 19/strict TypeScript through Inertia 3, PostgreSQL 18, Vite, Tailwind CSS 4, and source-owned shadcn/ui components. The SaaS application uses the `invumo` repository, one application deployment at `app.invumo.com`, one database, a PostgreSQL-backed Laravel queue, one supervised PHP worker, and one cron-triggered scheduler. The future `invumo.com` marketing website is outside this application's routes and deployment and uses its approved separate `invumo-web` repository and `/home/invumo/invumo-web` working directory. Node builds browser assets but does not run a production web server. See the [Invumo Application Architecture Baseline](../architecture/application-architecture.md) and living [Invumo Codebase Map](../architecture/codebase-map.md).

Until public launch, while Invumo has no real users, development may occur directly in the hosted production checkout. Source control and relevant automated checks still apply, but repeatable deployment automation is deliberately deferred. Rollback, off-server database/file backup and restore, uptime/error monitoring, and alert delivery are externally managed and must be verified before public launch. Introduce separate development and production environments plus a repeatable release process before real-user dependency makes direct-production development unsafe. Docker, a separate frontend deployment, a web API for the Inertia application, Inertia SSR, Redis, and microservices are excluded unless later evidence justifies them.

Prefer:

- One application
- One PostgreSQL database
- Simple deployments
- Minimal infrastructure
- Strong data integrity
- Maintainable code
- Clear abstractions
- Good UX

Handwritten/source-owned PHP, TypeScript/React, JavaScript, test, and stylesheet files follow a 300-line soft limit and a 500-line hard limit. Crossing the soft limit triggers a visible warning and refactor review; crossing the hard limit fails automated checks unless the owner has explicitly approved and documented a narrow exception. Generated code, lockfiles, dependencies, compiled assets, documentation, and authored translation catalogs are excluded. The full responsibility and exception contract is defined in the [application architecture baseline](../architecture/application-architecture.md#source-file-size-and-responsibility-contract).

External side effects, provider adapters, environment variables, logging privacy, material-change impact review, publication authority, and integration-evidence classification follow the approved [engineering and integration safety contract](../architecture/application-architecture.md#engineering-changes-and-external-integration-safety). Shared selector and overlay behavior follows the [design-system contract](../design/design-system.md); these are stack-neutral Invumo rules, not imported conventions from another application.

Avoid by default:

- Microservices
- Redis
- RabbitMQ
- Kafka
- Elasticsearch
- Separate worker services or clusters beyond the approved single PHP worker
- Kubernetes
- Unnecessary SaaS dependencies
- Premature abstractions

If any of these become genuinely necessary, explain why before introducing them.

## 36. Implementation process

Do not generate the application as an unstructured code dump. Work incrementally.

Implement each feature as a coherent vertical slice through its validation, authorization, application Action/Query, persistence, Inertia/React composition, audit/outbox behavior, tests, and documentation. Preserve the approved module ownership and dependency direction; do not scaffold empty modules, accumulate generic service/helper buckets, or restructure unrelated areas opportunistically.

The only canonical development sequence, progress checklist, phase status, dependency map, and acceptance-gate record is [Invumo Development Tracker](../development/development-tracker.md). Update that file as work advances; do not duplicate its phase checklist or progress state in this product brief or another document.

The tracker must continue to preserve the approved application, calculation/rounding, identifier, tenant-isolation, numbering-concurrency, and scheduling specifications. In particular, do not begin or finalize domain migrations or business features before their architecture prerequisites are satisfied.

## 37. Definition of successful v1

A new user can:

1. Register, verify their email address, sign in, and recover account access.
2. Create a company.
3. Configure its basic information.
4. Add a customer.
5. Add reusable products or services and select them in document editors.
6. Create a quotation.
7. Generate a professional PDF.
8. Email the quote.
9. Let the customer accept it online.
10. Generate one or multiple invoices from it.
11. Send an invoice and let the customer view/download it through a secure link.
12. Record one or multiple payments.
13. See the invoice become partially paid or paid automatically.
14. Find those payments under Transactions.
15. Create an invoice without a quote.
16. Create, activate, pause, resume, and complete recurring invoice templates.
17. Have recurring invoices generated and issued exactly once per occurrence, with optional automatic email.
18. Find operational records using appropriate search, filters, sorting, and pagination.
19. Manage multiple completely independent companies.
20. Invite users safely with appropriate roles.
21. Transfer a company to another account owner.
22. Work in English or Romanian.
23. Configure automatic invoice reminders before or after due dates.
24. Customize multilingual company email templates and preview their resolved content.

All of this should feel substantially simpler than traditional accounting software.

Invumo is quotation and invoicing software, not an accounting suite.
