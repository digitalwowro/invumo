# Routes, Navigation, Operational Lists, and Editor Composition

Status: Approved architecture decision

Created: 2026-08-23

Last updated: 2026-08-24

Approved: 2026-08-23

This document closes the Phase 1 composition gate required before Invumo's custom application shell and feature navigation are built. It translates the approved product scope, permissions, tenant isolation, relational indexes, and design-system contract into one coherent interface structure. It does not add product scope or authorize later-phase business workflows ahead of the canonical development tracker.

## 1. Goals and constraints

The composition must:

- keep the active Company unmistakable;
- make Quote and Invoice creation reachable with very few interactions;
- preserve explicit Company context in every business-data route;
- expose only destinations and actions permitted for the current membership;
- use the same operational-list and editor patterns for equivalent work;
- support desktop work efficiently and remain usable on narrow screens;
- keep account settings separate from Company settings;
- avoid accounting-suite navigation, reports, purchasing, Purchase Order entities, inventory, and other excluded scope.

This document controls composition and route ownership. The design-system contract continues to control all visual presentation and component behavior.

## 2. Company context and route boundary

Every Company-owned application route uses the explicit prefix:

```text
/companies/{company}/...
```

`{company}` is the Company's UUIDv7 identifier. It is not an authorization credential. Laravel must resolve membership and the named ability before establishing the transaction-local PostgreSQL tenant context. The restricted runtime role and forced RLS remain independently authoritative.

Explicit Company routes are required because one User may work in multiple Companies and may keep more than one browser tab open. A session-only "current company" must not make an old tab silently operate on a newly selected Company.

The Company switcher navigates to the equivalent supported destination in the selected Company when safe. Otherwise it opens that Company's Dashboard. It never rewrites a form submission or unsaved editor in place; the shared unsaved-change guard runs first.

The root route redirects a guest to Sign in. An authenticated User is redirected to onboarding when they have no accessible Company, otherwise to their last accessible Company's Dashboard. A missing, removed, or unauthorized last Company falls back safely instead of revealing whether another Company exists.

## 3. Primary application navigation

### 3.1 Sidebar composition

The desktop sidebar contains, in order:

1. reserved Invumo product-mark/wordmark slot;
2. active-Company switcher;
3. compact **Create** menu;
4. primary Company navigation;
5. the Company-scoped **Settings** destination at the bottom when permitted;
6. account/user menu at the bottom.

The Create menu contains only the frequent actions **New invoice**, **New quote**, and **New customer**. It uses the same create actions as the corresponding list headers. Product/service and recurring-template creation remain on their list pages so the global menu does not become a catalogue of every possible action.

The narrow-screen navigation is a sheet containing the same Company switcher, destinations, authorization rules, labels, ordering, and Create menu. It is not a separate mobile information architecture.

### 3.2 Primary destinations

| Order | Destination  | Visible to           | Purpose                                                                                                  |
| ----- | ------------ | -------------------- | -------------------------------------------------------------------------------------------------------- |
| 1     | Dashboard    | Owner, Admin, Member | Operational summary and work requiring attention                                                         |
| 2     | Quotes       | Owner, Admin, Member | Quote list, creation, decisions, conversion, and delivery history                                        |
| 3     | Invoices     | Owner, Admin, Member | Invoice list, creation, financial state, and delivery history                                            |
| 4     | Transactions | Owner, Admin, Member | Company-wide Payment, Refund, and Adjustment history; mutation follows permission                        |
| 5     | Customers    | Owner, Admin, Member | Customers, contacts, delivery defaults, and customer history                                             |
| 6     | Recurring    | Owner, Admin, Member | Templates and occurrence history; Member automation actions remain restricted                            |
| 7     | Products     | Owner, Admin         | Manage the reusable Product & Service line-default catalogue; Members select entries only inside editors |
| 8     | Settings     | Owner, Admin         | Company configuration and membership administration                                                      |

Members do not receive a dead or disabled Products & Services navigation item. Catalogue selection remains available in document editors. Owner-only settings/actions are omitted for Admin where required, while the normal Company-scoped Settings destination remains visible.

The account/user menu owns Profile, Security, Preferences/Application language, accessible Companies, Plan/entitlements where permitted, and Sign out. A verified Platform Owner additionally receives one “Platform operations” destination. These are not mixed into Company Settings, and platform navigation never appears in the Company sidebar.

There is no v1 primary navigation entry for Purchase Orders, Vendors, Credit Notes, Expenses, Accounting, Reports, Contracts, Inventory, or Audit for Members. Owner/Admin reach the full Company audit trail from Company Settings/Operations rather than adding another top-level destination.

## 4. Canonical internal route map

Route names use normal Laravel resource vocabulary. Exact controller/action class names may follow module conventions, but a feature must not create an alternative URL for the same workspace.

### 4.1 Identity, onboarding, and account routes

| Method      | Route                                                                      | Responsibility                                                            |
| ----------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| GET         | `/`                                                                        | Safe guest/authenticated redirect only                                    |
| GET/POST    | Fortify identity routes                                                    | Registration, verification, sign-in/out, password reset, and confirmation |
| GET         | `/companies`                                                               | Accessible Company chooser/management entry                               |
| GET/POST    | `/companies/create`                                                        | Create the first or another Company                                       |
| GET         | `/invitations/{token}`                                                    | Rate-limited invitation review without Company enumeration                |
| POST        | `/invitations/{token}/accept`                                             | Accept after authentication, verification, and invited-email matching     |
| GET/POST    | `/companies/{company}/settings/members`                                   | Authorized member directory and invitation creation                      |
| PATCH/DELETE | `/companies/{company}/settings/members/{membership}`                      | Guarded non-Owner role change or removal                                  |
| DELETE      | `/companies/{company}/settings/members/current`                            | Guarded Admin/Member self-leave                                            |
| PATCH       | `/companies/{company}/settings/ownership`                                  | Reauthenticated Owner transfer to an existing Admin/Member                 |
| POST/DELETE | `/companies/{company}/settings/members/invitations/{invitation}/*`        | Resend or revoke a Company-bound pending invitation                       |
| GET/PATCH   | `/settings/profile`                                                      | User identity                                                             |
| GET/PUT     | `/settings/security`                                                     | Password and secure-session controls in v1 scope                          |
| GET/PATCH   | `/settings/preferences`                                                  | Application language and account-level preferences                        |
| GET         | `/settings/plan`                                                         | Account Owner view of current plan/entitlements when implemented           |

### 4.1.1 Platform Operations routes

These routes use a distinct platform shell, current operator revalidation, and the guards in the approved [Platform Operations contract](platform-operations.md).

| Method | Route                                  | Responsibility                                                                      |
| ------ | -------------------------------------- | ----------------------------------------------------------------------------------- |
| GET    | `/platform`                            | Platform overview                                                                   |
| GET    | `/platform/users`                      | Searchable User control-plane list                                                   |
| GET    | `/platform/accounts`                   | Searchable Account and current plan-lifecycle list                                   |
| GET    | `/platform/companies`                  | Searchable Company ownership/membership-count list                                   |
| GET    | `/platform/plan-lifecycle`             | Active/trial/past-due/cancel-at-end/expired/upcoming-expiry operational views        |
| GET    | `/platform/audit`                      | Reverse-chronological append-only platform audit                                     |
| POST   | `/platform/users/{user}/impersonation` | Start full-action impersonation as the selected User without extra ceremony           |
| DELETE | `/platform/impersonation`              | Exit impersonation and restore the still-authorized original Platform Owner           |
| POST   | `/platform/users/{user}/suspension`    | Guarded User suspension                                                              |
| DELETE | `/platform/users/{user}/suspension`    | Guarded User reactivation                                                            |
| POST   | `/platform/accounts/{account}/suspension` | Guarded Account suspension                                                         |
| DELETE | `/platform/accounts/{account}/suspension` | Guarded Account reactivation                                                       |
| PATCH  | `/platform/accounts/{account}/plan`    | Guarded seeded-Plan and lifecycle update                                             |

Platform Owner grant/revoke has no web route in v1. Non-operators receive no platform route/action props. Platform pages use the same shared page, operational-table, status, form, confirmation, accessibility, responsive, and localization components as the Company application without a second visual system. During impersonation, every application shell renders the shared persistent identity banner and exit action; this state is server-owned and may not be inferred or dismissed only in React.

### 4.2 Company shell and operational resources

| Method | Route                                                           | Page/action                                                  |
| ------ | --------------------------------------------------------------- | ------------------------------------------------------------ |
| GET    | `/companies/{company}`                                          | Redirect to Company Dashboard                                |
| GET    | `/companies/{company}/dashboard`                                | Dashboard                                                    |
| GET    | `/companies/{company}/customers`                                | Customer operational list                                    |
| GET    | `/companies/{company}/customers/create`                         | Standalone complete Customer form                            |
| POST   | `/companies/{company}/customers`                                | Shared standalone/inline create action                       |
| GET    | `/companies/{company}/customers/{customer}`                     | Customer workspace                                           |
| PATCH  | `/companies/{company}/customers/{customer}`                     | Update Customer aggregate                                    |
| POST   | `/companies/{company}/customers/{customer}/archive`             | Archive with dependency rules                                |
| POST   | `/companies/{company}/customers/{customer}/restore`             | Restore archived Customer                                    |
| DELETE | `/companies/{company}/customers/{customer}`                     | Owner/Admin-only dependency-guarded permanent deletion       |
| GET    | `/companies/{company}/products`                                 | Product & Service operational list                           |
| GET    | `/companies/{company}/products/create`                          | Standalone catalogue form                                    |
| POST   | `/companies/{company}/products`                                 | Shared standalone/inline create action                       |
| GET    | `/companies/{company}/products/{product}`                       | Product/Service workspace                                    |
| PATCH  | `/companies/{company}/products/{product}`                       | Update catalogue entry                                       |
| POST   | `/companies/{company}/products/{product}/archive`               | Archive entry                                                |
| POST   | `/companies/{company}/products/{product}/restore`               | Restore entry                                                |
| DELETE | `/companies/{company}/products/{product}`                       | Owner/Admin-only dependency-guarded permanent deletion       |
| GET    | `/companies/{company}/quotes`                                   | Quote operational list                                       |
| POST   | `/companies/{company}/quotes`                                   | Persist a numbered Draft and redirect to its workspace       |
| GET    | `/companies/{company}/quotes/{quote}`                           | Quote document workspace/editor                              |
| PATCH  | `/companies/{company}/quotes/{quote}`                           | Version-checked aggregate save                               |
| DELETE | `/companies/{company}/quotes/{quote}`                           | Guarded permanent deletion                                   |
| POST   | `/companies/{company}/quotes/{quote}/invoices`                  | Guarded Quote-to-Invoice conversion                          |
| POST   | `/companies/{company}/quotes/{quote}/invoices/{invoice}/unlink` | Owner/Admin-only eligible Draft unlink action                |
| GET    | `/companies/{company}/invoices`                                 | Invoice operational list                                     |
| POST   | `/companies/{company}/invoices`                                 | Persist a numbered Draft and redirect to its workspace       |
| GET    | `/companies/{company}/invoices/{invoice}`                       | Invoice document workspace/editor                            |
| PATCH  | `/companies/{company}/invoices/{invoice}`                       | Version-checked aggregate save                               |
| DELETE | `/companies/{company}/invoices/{invoice}`                       | Strongly confirmed, guarded permanent deletion               |
| POST   | `/companies/{company}/invoices/{invoice}/cancel`                | Guarded cancellation                                         |
| POST   | `/companies/{company}/invoices/{invoice}/reopen`                | Guarded reopen                                               |
| GET    | `/companies/{company}/recurring`                                | Recurring-template operational list                          |
| GET    | `/companies/{company}/recurring/create`                         | Minimal creation form requiring the internal template name   |
| POST   | `/companies/{company}/recurring`                                | Persist a valid Draft template and redirect to its workspace |
| GET    | `/companies/{company}/recurring/{template}`                     | Recurring-template workspace/editor                          |
| PATCH  | `/companies/{company}/recurring/{template}`                     | Version-checked future-template save                         |
| POST   | `/companies/{company}/recurring/{template}/activate`            | Owner/Admin activation                                       |
| POST   | `/companies/{company}/recurring/{template}/pause`               | Owner/Admin pause                                            |
| POST   | `/companies/{company}/recurring/{template}/resume`              | Owner/Admin resume                                           |
| POST   | `/companies/{company}/recurring/{template}/complete`            | Owner/Admin completion                                       |
| POST   | `/companies/{company}/recurring/{template}/duplicate`           | Duplicate retained/Completed template into a Draft           |
| GET    | `/companies/{company}/transactions`                             | Company transaction operational list                         |

Payment, Refund, and Adjustment mutations are nested under the Invoice aggregate because every ledger change locks and recalculates that Invoice. They may have stable transaction identifiers for edit/audit links, but no mutation bypasses the Invoice workflow:

```text
POST   /companies/{company}/invoices/{invoice}/payments
PATCH  /companies/{company}/invoices/{invoice}/payments/{payment}
DELETE /companies/{company}/invoices/{invoice}/payments/{payment}
POST   /companies/{company}/invoices/{invoice}/refunds
PATCH  /companies/{company}/invoices/{invoice}/refunds/{refund}
DELETE /companies/{company}/invoices/{invoice}/refunds/{refund}
POST   /companies/{company}/invoices/{invoice}/adjustments
PATCH  /companies/{company}/invoices/{invoice}/adjustments/{adjustment}
DELETE /companies/{company}/invoices/{invoice}/adjustments/{adjustment}
```

The permission matrix controls each mutation. Members may manage Payments and Refunds but not Adjustments.

### 4.3 Company settings routes

`/companies/{company}/settings` redirects to the first permitted section. Sections use one `SettingsShell`:

- Company profile and legal/address details;
- Defaults and localization;
- Taxes;
- Bank accounts;
- Appearance for outward documents;
- Numbering and document defaults;
- Reminders;
- Email templates/defaults;
- Members and invitations;
- Operations/audit/data lifecycle for permitted roles.

Settings use bounded offset pagination only where a list is naturally small. They do not reuse operational keyset pagination merely for visual consistency.

### 4.4 Deferred route contracts

Public Quote/Invoice, PDF, email-provider webhook, upload-serving, and provider-callback routes are not fixed here. Public document pages will remain on the `app.invumo.com` SaaS host rather than the separate `invumo.com` marketing website, but their exact path and bootstrap contract remains blocked by the named token, renderer, upload, or integration gate. The internal route map reserves no insecure public-ID shortcut.

## 5. Operational-list contract

Customers, Products & Services, Quotes, Invoices, Recurring, and Transactions are configurations of one `OperationalTable` system.

### 5.1 Shared query behavior

- Search, filters, and sort are represented in the URL query string so refresh, back/forward, and copied internal links preserve the view.
- Search is Company-scoped, trimmed, length-bounded, debounced in React, and submitted through an Inertia partial reload.
- The server validates every filter and sort allowlist; unknown values fall back safely.
- Growing business lists use keyset/cursor pagination with stable UUID tie-breakers. They expose Previous/Next navigation rather than pretending cursor results have reliable numbered pages.
- Bounded settings lists may use offset pagination.
- Changing search, filter, sort, or page size resets the cursor.
- Empty database and zero search-result states use distinct shared Empty configurations.
- Loading preserves table geometry; recoverable partial errors retain the last safe data and expose Retry.

### 5.2 Shared row behavior

- A permitted row is one keyboard-focusable navigation target.
- The identifying value remains ink-coloured; a trailing chevron provides the persistent navigation affordance.
- The overflow action stops row navigation and contains only authorized, state-valid secondary actions.
- Permission-hidden actions are omitted. State-blocked actions are disabled only when an accessible explanation is available.
- Narrow screens retain identity, status, date, and money columns through horizontal scrolling and minimum widths; they do not silently discard financial values.
- Numeric, date, identifier, and amount cells use the shared data typography and value components.
- Bulk selection is added only for a real approved bulk workflow. v1 pages must not show inert checkboxes or invent bulk mutations.

### 5.3 Resource configurations

| List                | Search includes                                                         | Core filters                                                            | Default stable sort         |
| ------------------- | ----------------------------------------------------------------------- | ----------------------------------------------------------------------- | --------------------------- |
| Customers           | name, company name, external reference, email, registration identifiers | active/archived, Country where useful                                   | recently updated            |
| Products & Services | name, internal code/SKU, description                                    | active/archived                                                         | name then UUID              |
| Quotes              | number, Customer, customer reference / PO number                        | lifecycle, issue/validity dates, expired                                | issue date descending       |
| Invoices            | number, Customer, customer reference / PO number                        | lifecycle, payment state, overdue, issue/due dates                      | issue date descending       |
| Recurring           | internal name, Customer, customer reference / PO number                 | Draft/Active/Paused/Completed, next-run range, currency-review required | next occurrence then UUID   |
| Transactions        | Invoice number, Customer, transaction reference where present           | Payment/Refund/Adjustment as permitted, date, currency                  | transaction date descending |

Amounts in different currencies are never added into one list total. Any summary is split by currency or omitted.

## 6. Shared workspace and editor composition

### 6.1 Resource workspaces

An operational row opens one canonical resource workspace; Invumo does not maintain competing view and edit pages for the same current record.

- Customer workspace: Overview, contacts/delivery/defaults, documents, and permitted activity.
- Product/Service workspace: catalogue defaults, use/dependency summary, and permitted activity.
- Quote workspace: Document, Delivery/decision history, related Invoices, and permitted activity.
- Invoice workspace: Document, Transactions, Delivery/reminders, and permitted activity.
- Recurring workspace: Template, Schedule/occurrences, Delivery/reminders, and permitted activity.

The tabs/sections are configurations of shared workspace components. A record with only one meaningful region does not show decorative tabs. The primary creation/editing path always opens on Document or Template.

### 6.2 `DocumentEditorShell`

Quote, Invoice, and recurring-template editing use one shell with typed slots rather than three separate page designs:

1. shared page header with number/internal name, compressed status, unsaved state, and authorized primary/overflow actions;
2. blocking validation/conflict summary when required;
3. Customer and document identity section;
4. dates/validity or recurring schedule slot;
5. currency, language, payment/validity terms, tax/default inheritance controls;
6. customer reference / PO-number field;
7. shared `DocumentLinesEditor`;
8. server/preview totals and reconciliation region;
9. Terms & Conditions and notes;
10. bank, recipients, delivery, reminder, and automation sections where applicable;
11. shared save/action region and unsaved-navigation guard.

Quote-only validity/decision fields, Invoice-only ledger/payment state, and recurring-only internal name/schedule/inheritance/automatic-email controls enter through documented slots. Field order and control treatment do not fork by document type.

On wide screens, the editable content is the flexible main column and the totals/action summary may remain visible in a restrained sticky side region. On narrow screens, the same regions become one column in logical reading order; no field disappears, horizontal line-item scrolling remains available, and primary actions keep a 44px target.

### 6.3 Draft creation and saving

- **New quote** and **New invoice** submit POST actions that allocate and persist a Draft exactly once, then redirect to its canonical workspace. GET requests never allocate numbers or mutate data.
- A recurring-template create form first collects its required internal name, then persists the Draft and opens its workspace; Invumo never invents a customer-visible or internal name merely to allocate an empty row.
- The editor keeps interaction state in a local reducer. Laravel receives one aggregate command, recalculates authoritatively, and returns persisted string decimals and the next edit version.
- Every save carries the aggregate edit version. A stale save is rejected into the shared reload/review flow rather than silently replacing newer work.
- Navigating away, switching Company, closing the shared inline modal, or following a row action respects the shared unsaved-change guard.
- State transitions such as Issue, Send, Accept/Reject correction, Cancel/Reopen, or recurring activation are explicit named actions, not hidden side effects of an ordinary save except where the approved state contract explicitly requires Draft issue before send.

### 6.4 Lines and inline creation

`DocumentLinesEditor` is shared by all three editors. It owns manual lines, searchable active Product/Service selection, snapshot copying, complete post-selection editing, add/remove/reorder, quantity, unit, period, description, unit price, discount, tax, and preview totals.

Line reorder sends the complete intended order with the current aggregate version. The server applies the approved PostgreSQL-safe atomic reorder mechanism; the browser never relies on sequential position writes.

Customer and Product/Service inline creation use the same actions and form components as standalone creation:

- the parent editor remains mounted and retains all local values;
- the dialog body scrolls vertically while its title and actions remain reachable;
- validation errors keep both editor and modal values;
- a successful create returns the minimum new option data, closes the dialog, and selects the new record;
- Product/Service inline creation is absent for Members because catalogue management is Owner/Admin-only;
- selecting a Customer or Product/Service never creates a live link that can silently rewrite an existing document line/snapshot.

## 7. Authorization and visibility

Navigation and UI affordances are projections of server-provided named abilities. They improve clarity but never replace Policies/actions/RLS.

- The server omits unauthorized navigation and actions from the page contract.
- React does not infer role behavior from role-name strings scattered through components.
- A shared typed ability bag controls navigation, action menus, editor controls, and explanatory disabled states.
- A direct request to a hidden route still receives the appropriate server denial.
- The Company switcher lists only accessible Companies and never exposes membership in another Account.
- Member cancellation that requires an Adjustment remains blocked with the approved Owner/Admin escalation explanation.

## 8. Accessibility and responsive verification

Before this gate is considered implemented, component/browser coverage must verify:

- keyboard opening/closing and focus restoration for sidebar sheets, Company/Create menus, overflow menus, and inline dialogs;
- visible skip link and landmark structure;
- one `h1` per page and consistent section hierarchy;
- row navigation and overflow actions without nested-interactive conflicts;
- status text and icons/dots communicate state without colour alone;
- English and Romanian labels fit representative sidebar, table, status, and editor widths;
- table scrolling preserves access to money/status/action columns;
- inline forms retain state across server validation responses;
- stale editor, unsaved navigation, loading, empty, no-results, error, and retry states;
- 44px narrow-screen action targets and no inaccessible fixed/sticky region.

## 9. Explicit exclusions

This composition does not approve:

- a Purchase Order entity or purchasing navigation;
- a second view-only document page beside the canonical workspace;
- client-side routing or a separate API;
- global search across all modules;
- inert bulk selection;
- Company branding in application chrome;
- different Customer/Product forms for standalone and inline use;
- different line editors for Quote, Invoice, and recurring templates;
- numbered pagination on cursor-paginated operational lists;
- public routes before the public-token gate.

## 10. Approval record

The owner approved this document on 2026-08-23 with the following explicit confirmations:

- Company context remains explicit in Company-owned URLs.
- The sidebar order is Dashboard, Quotes, Invoices, Transactions, Customers, Recurring, Products, Settings.
- The global Create menu contains New invoice, New quote, and New customer.
- Members do not see the Products destination but may select catalogue entries in document editors.
- Each record has one canonical workspace rather than separate view and edit pages.
- Growing operational lists use cursor-based Previous/Next navigation; bounded settings lists may use numbered pages.

This approval authorizes implementation of the custom application shell, authorized navigation, common operational-list composition, and shared editor-shell composition. It does not authorize later-phase business workflows ahead of the canonical development tracker.
