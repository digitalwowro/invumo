# Invumo — Master Build Brief

Status: Approved product brief  
Last updated: 2026-08-22  
Domain: `invumo.com`

Invumo is a streamlined quotation and invoicing SaaS. Its core philosophy is:

> Anything that does not help the user create, send, manage, or get paid for an invoice probably should not exist.

The application should have as few moving parts as reasonably possible.

## Instructions before implementation

Before implementing application code:

1. Analyze this complete brief.
2. Identify architectural risks, contradictions, unresolved calculation details, and unnecessarily complex requirements.
3. Recommend the simplest appropriate technology stack.
4. Explain the recommendation briefly, including major trade-offs.
5. Prefer boring, mature, maintainable technology over unnecessary infrastructure.
6. Do not assume a frontend framework, ORM, authentication library, deployment platform, or application architecture solely because it is popular.
7. Use PostgreSQL from day one.
8. Produce the architecture, data, tenancy, permission, navigation, calculation, implementation, and verification documents requested below.
9. After the proposal is reviewed, proceed incrementally unless there is a genuine blocking ambiguity.

Do not add unrequested features.

## 1. Core product

Invumo is a multi-tenant SaaS for:

1. User registration and login
2. Managing one or more companies
3. Managing customers
4. Creating quotations
5. Creating invoices from a quotation or independently
6. Recording payments against invoices
7. Creating recurring invoices
8. Sending quotes and invoices by email
9. Generating PDFs
10. Publishing public quote and invoice links

Do not implement in v1:

- Products or catalog
- Inventory
- Vendors
- Purchase orders
- Expense management
- Full accounting
- General ledger
- CRM
- Currency conversion
- Exchange rates
- Reports
- CSV import/export
- Electronic invoicing standards
- Country-specific fiscal compliance engines
- Automatic translation of user-entered content
- Customer SMTP
- Subscription billing or payment collection for Invumo itself
- Custom document title/header overrides

## 2. Account model

Separate these concepts:

- User
- Account
- Company
- Company membership

An Account belongs to an account owner. The account owner has an Invumo plan.

Initial placeholder plans:

- Free
- Pro
- Enterprise

These are placeholders only. Additional plans must be possible later.

Do not build subscription billing, Stripe integration, or a plan-creator/admin plan builder in v1. The plan and entitlement architecture should exist, but its initial behavior may remain minimal.

## 3. Companies and tenant model

An account owner can manage multiple companies. A company is an independent business entity and tenant inside Invumo.

Each company has independent:

- Customers
- Contacts
- Quotations
- Invoices
- Recurring invoice templates
- Transactions
- Bank accounts
- Document numbering
- Currency settings
- Tax settings
- Language settings
- Payment terms
- Quote validity
- Branding and logo
- Document settings
- Email defaults
- Audit history

Use one shared PostgreSQL database unless there is a strong architectural reason not to. Every company-owned object must be scoped correctly, and protection against cross-company data access is critical.

## 4. Company ownership and members

A company can have multiple members with these roles:

- Owner
- Admin
- Member

Design a clear permission matrix around these roles.

Company ownership must be transferable. A company should be movable from one account owner/account to another without rewriting or duplicating its business data.

During transfer:

- Existing company data remains intact.
- Existing company members remain attached by default.
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
- Address
- Country
- VAT/tax ID
- Registration number
- Email
- Phone
- Website
- Logo
- Multiple bank accounts

Additional sensible identity fields may be added when necessary, but avoid unnecessary complexity.

Company defaults include:

- Default currency
- Default document language
- Payment terms
- Terms & Conditions
- Default tax rate/settings
- Default quote validity
- Invoice notes/footer
- Quote notes/footer
- Document numbering settings
- Currency precision settings
- Email defaults
- Public-link defaults

## 7. Bank accounts

Bank accounts are part of company settings.

- A company can have multiple bank accounts.
- Design for optional currency association.
- Relevant bank information should be selectable for and displayable on invoices.

## 8. Customers

Support both company and individual customers.

Keep customer v1 information simple and include normal identity and contact information. A customer may have multiple contacts.

From the quote or invoice editor, the user must be able to create a new customer without abandoning the document. Open customer creation in a modal, preserve the in-progress document, and automatically select the newly created customer after a successful save.

Customer-level defaults:

- Currency
- Language when needed
- Payment terms
- Tax settings
- Billing email

Do not build separate billing, service, or delivery addresses in v1.

Customer records with historical documents should normally be archived. If dependent historical data has been removed, permanent deletion should be possible so users can ultimately delete their data.

## 9. Quotations

Quote lifecycle:

- Draft
- Sent
- Accepted
- Rejected
- Expired

Rules:

- Quotes remain editable after acceptance.
- No quote revision/versioning system in v1.
- A quote may generate multiple invoices.
- Track the quoted amount, amount already invoiced, and remaining amount.
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

## 10. Invoices

Invoice lifecycle:

- Draft
- Issued
- Partially Paid
- Paid
- Overdue
- Cancelled

Invoices may be created from quotations or independently. One quote may generate multiple invoices.

Issued invoices remain editable. Users may edit customer, lines, quantities, prices, discounts, tax, currency, dates, and document metadata. Significant changes must be captured in the audit history.

This flexibility is intentional. Do not enforce country-specific legal or accounting restrictions in v1.

## 11. Document numbering

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
- Warn about duplicate numbers where appropriate.
- Support configurable formats such as `INV-2026-0001` and `Q-2026-0001`.
- Avoid overengineering the numbering engine.

## 12. Quote and invoice lines

There is no product catalog in v1. Every line is entered directly into the document.

Line fields:

- Line number/order
- Description
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

## 13. Period calculation

Period units:

- None / N/A
- Month
- Year

Period quantity is stored separately. Examples include N/A, 1 month, 12 months, 1 year, and 3 years.

Do not automatically convert years to months. The item price may itself be defined for the user's selected period.

If period is N/A, its multiplier is 1.

Calculation:

```text
items subtotal = item price × quantity

if period exists:
    items total = items subtotal × period quantity
else:
    items total = items subtotal

discount value = items total × discount percentage
grand subtotal = items total − discount value
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
```

Avoid floating-point errors. Use appropriate fixed-precision decimal handling and define rounding behavior explicitly before implementation.

## 14. Discounts

- Support discount percentage per line.
- Calculate discount value automatically.
- Support an overall document discount only if it can be implemented without ambiguous calculation or tax ordering.
- If the overall discount materially complicates calculation, define the order explicitly before implementation or defer it.
- Never silently produce ambiguous totals.

## 15. Tax

For v1, use tax per line only. Do not implement invoice-wide tax.

- Each line may have its own tax rate.
- Company and customer settings may provide defaults.
- Users may override defaults on individual document lines.
- Prices are tax-exclusive.

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

## 16. Currency

- Each customer has a default currency.
- Documents inherit the customer currency by default.
- Users may override currency per quote or invoice.
- There is no foreign-exchange conversion or exchange-rate service in v1.
- Decimal precision is user-configurable per currency.
- Do not enforce ISO precision as mandatory behavior.
- Store monetary values safely and accurately.

Examples:

- EUR → 2 decimals
- USD → 2 decimals
- JPY → 0 decimals
- Any supported currency → user-selected precision

## 17. Payment terms and Terms & Conditions

Payment terms can have a company default, customer default, and document override.

The invoice due date derives automatically from the applicable terms but remains editable.

Terms & Conditions are separate customer-visible document content, not payment-term logic and not general notes.

- A company may define default Terms & Conditions.
- Each quote or invoice inherits the company default.
- The user may override the content per quote or invoice.
- Notes/footer, Terms & Conditions, and structured payment terms must remain distinct concepts.

## 18. Transactions and payments

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

- Amount
- Currency
- Date
- Payment method
- Reference
- Notes

Support refunds and negative payment adjustments where needed.

Do not build expenses, general accounting transactions, a chart of accounts, or a bookkeeping ledger. The Transactions page is the global company-level view of invoice payment transactions.

Invoice payment status should derive appropriately from payments. Prevent payments from unintentionally exceeding the outstanding balance. If refunds make the balance unpaid again, update the invoice state.

## 19. Recurring invoices

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

Recurrence options:

- Weekly
- Monthly
- Quarterly
- Yearly
- Custom interval

Support a start date, optional end date, and optional maximum occurrence count.

Keep scheduling infrastructure as simple as reasonably possible. Avoid queues and message brokers unless architecture analysis identifies a genuine need. Scheduled execution must be idempotent.

## 20. Localization

Both the application UI and generated documents support multiple languages.

Launch languages:

- English
- Romanian

Adding languages later should be straightforward.

Document language inherits an applicable company/account default and is overridable per quote or invoice.

Changing document language localizes system-generated terminology, date formatting, number formatting, and locale-specific presentation. User-written descriptions remain exactly as entered; do not translate user content automatically.

## 21. PDFs

Quotes and invoices must generate professional downloadable PDFs.

v1 includes one excellent document template with:

- Company logo and details
- Customer details
- Document metadata
- Lines and totals
- Relevant bank information
- Terms & Conditions
- Notes/footer

Design the PDF system so more templates can be added later without rewriting the document model. Do not build a PDF template editor in v1.

## 22. Email

Send transactional email through Zoho ZeptoMail. Prefer its API if the architecture assessment finds it cleaner and more reliable than SMTP.

Quote and invoice emails include:

- Sensible predefined multilingual subject
- Sensible predefined multilingual body
- Editable subject before sending
- Editable body before sending

Track Sent, Delivered, and Opened where ZeptoMail supports them. Authenticate webhooks and process provider events idempotently.

Do not build customer SMTP or a marketing-email system in v1.

## 23. Public quote and invoice pages

Both quotes and invoices support secure public links, such as:

- `invumo.com/q/<secure-token>`
- `invumo.com/i/<secure-token>`

Do not expose predictable database IDs.

Public links are:

- Sufficiently random and unpredictable
- Valid for 30 days by default
- Configurable by the user
- Revocable
- Regeneratable

Public invoice pages support viewing and PDF download without a customer account.

Public quote pages support viewing, PDF download, Accept, and Reject.

Accepting or rejecting requires the customer's name and email address. Record the decision timestamp and relevant audit metadata, with optional IP/user-agent metadata where appropriate.

Do not add electronic signatures in v1.

## 24. Quote acceptance

When a customer accepts or rejects through a public link:

- Update quote status accordingly.
- Record the event in the audit trail.
- Prevent duplicated or replayed actions from producing inconsistent records.
- Permit an internal user to change quote status manually when needed.

Flexibility should generally win over rigid workflows.

## 25. Dashboard

Keep the dashboard extremely simple. Show at least:

- Unpaid invoices
- Overdue invoices
- Paid this month
- Outstanding total
- Recent invoices

Do not build analytics or reporting dashboards in v1.

Dashboard calculations must respect company and currency boundaries. Do not add amounts in different currencies together without an explicit, mathematically valid basis; Invumo has no FX system.

## 26. Audit history

Maintain an audit trail for significant business operations, including:

- Invoice creation, issue, edits after issue, cancellation, deletion, and number changes
- Quote creation, edits, acceptance, and rejection
- Payment creation, deletion, and changes
- Public-link generation, revocation, and regeneration
- Company transfer
- Important settings and membership changes

Audit history should answer what happened, when, who caused it, and what object was affected. Retain enough before/after information to make important edits understandable.

Do not introduce a complex event-sourcing architecture unless independently justified.

## 27. Deletion and archiving

Customers and companies with historical dependencies should normally be archived. Permanent deletion is allowed after dependent data has been removed and deletion is valid.

Users must ultimately be able to delete their data. Do not impose artificial permanent-retention rules in v1 unless technically required. Use clear warnings before destructive actions.

## 28. UI and UX

Create the initial Invumo visual identity; there is no existing design system.

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

Avoid excessive dashboards, unnecessary cards, excessive modals, decorative complexity, and enterprise-style navigation overload.

Primary navigation should remain small. A possible starting point is:

- Dashboard
- Customers
- Quotes
- Invoices
- Recurring
- Transactions
- Settings

Analyze the UX and propose the simplest navigation. Users should create an invoice or quote with very few interactions.

## 29. Settings hierarchy

Clearly separate account/user settings from company settings.

Account/user settings include profile, account preferences, application language, and plan/entitlements.

Company settings include legal details, tax information, bank accounts, logo, currencies, precision, numbering, document defaults, language, payment terms, quote validity, email defaults, members, and public-link defaults.

A user switching between companies must always understand which company is active.

## 30. Security

Security is critical because Invumo is multi-tenant financial/business software.

Address at least:

- Tenant isolation
- Authentication
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

Never rely solely on client-side authorization. Every server-side business-data operation must verify company membership and permission.

## 31. Database

Use PostgreSQL from day one. Prefer a normalized, understandable relational schema and do not introduce database-per-tenant architecture without a compelling reason.

Likely concepts include:

- Users
- Accounts
- Plans/entitlements
- Companies
- Company members
- Company settings
- Bank accounts
- Customers
- Customer contacts
- Quotes and quote lines
- Invoices and invoice lines
- Transactions/payments
- Recurring invoice templates and lines
- Public document links
- Email delivery events
- Audit events

These names are conceptual, not mandatory. Analyze the domain and choose the cleanest schema.

## 32. Data integrity

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

Use transactions for business-critical operations where appropriate.

## 33. Testing

Create automated tests for critical calculations and workflows, especially:

- Invoice line and period calculations
- Discounts and taxes
- Document totals and rounding
- Partial payments
- Invoice status transitions
- Refunds
- Next-number suggestion
- Quote to multiple invoices
- Recurring invoice generation
- Tenant isolation
- Role authorization
- Public quote acceptance
- Public-link expiry and revocation

Implement browser-level tests for the main journey:

```text
Register
→ create company
→ add customer
→ create quote
→ send quote
→ accept quote
→ create invoice
→ issue invoice
→ record payment
→ invoice becomes paid
```

## 34. Development philosophy

Prefer:

- One application
- One PostgreSQL database
- Simple deployments
- Minimal infrastructure
- Strong data integrity
- Maintainable code
- Clear abstractions
- Good UX

Avoid by default:

- Microservices
- Redis
- RabbitMQ
- Kafka
- Elasticsearch
- Separate worker infrastructure
- Kubernetes
- Unnecessary SaaS dependencies
- Premature abstractions

If any of these become genuinely necessary, explain why before introducing them.

## 35. Implementation process

Do not generate the application as an unstructured code dump. Work incrementally.

First produce:

1. Requirements assessment
2. Architecture recommendation
3. Technology stack recommendation
4. Data model/schema
5. Permission and tenant model
6. Main routes and navigation
7. Exact domain calculation and rounding rules
8. Implementation phases and verification strategy

Then build in logical stages. A sensible initial order is:

### Phase 1 — Foundation

- Project structure
- Database
- Authentication
- Users and accounts
- Companies
- Memberships
- Settings
- Tenant isolation

### Phase 2 — Customers

- Customers
- Contacts
- Customer defaults

### Phase 3 — Quotes

- Quote CRUD
- Line calculations
- Numbering
- PDF
- Statuses

### Phase 4 — Invoices

- Invoice CRUD
- Quote-to-invoice workflow
- Calculations
- Numbering
- PDF
- Statuses

### Phase 5 — Payments

- Transactions
- Partial payments and refunds
- Invoice payment states
- Transactions screen

### Phase 6 — Public documents

- Secure links
- Expiry
- Revocation and regeneration
- Quote acceptance/rejection
- Invoice viewing

### Phase 7 — Email

- ZeptoMail integration
- Default templates
- Editing before sending
- Delivery/open tracking

### Phase 8 — Recurring invoices

- Templates
- Scheduling
- Automatic invoice generation
- Automatic issue and email

### Phase 9 — Dashboard and audit

- Dashboard
- Audit history
- Destructive-action handling

### Phase 10 — Polish

- English and Romanian
- UX refinement
- Responsive behavior
- Edge cases
- Tests
- Security review

Change the order only when a simpler or safer sequence is justified.

## 36. Definition of successful v1

A new user can:

1. Register.
2. Create a company.
3. Configure its basic information.
4. Add a customer.
5. Create a quotation.
6. Generate a professional PDF.
7. Email the quote.
8. Let the customer accept it online.
9. Generate one or multiple invoices from it.
10. Send an invoice.
11. Record one or multiple payments.
12. See the invoice become partially paid or paid automatically.
13. Find those payments under Transactions.
14. Create an invoice without a quote.
15. Create recurring invoice templates.
16. Have recurring invoices generated, issued, and emailed automatically.
17. Manage multiple completely independent companies.
18. Invite users with appropriate roles.
19. Transfer a company to another account owner.
20. Work in English or Romanian.

All of this should feel substantially simpler than traditional accounting software.

Invumo is quotation and invoicing software, not an accounting suite.
