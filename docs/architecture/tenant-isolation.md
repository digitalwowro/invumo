# Tenant Isolation and PostgreSQL Row-Level Security

Status: Approved architecture decision  
Last updated: 2026-08-24

Invumo uses defense-in-depth tenant isolation: Laravel authorization and company scoping are the first layer; PostgreSQL Row-Level Security is a mandatory independent layer for tenant-owned business data.

RLS does not replace authentication, membership checks, Policies, validation, or ownership-transfer safeguards. It limits the impact of an application query that accidentally omits a company filter.

## Data classes

### Control-plane data

The application must access some records before selecting a tenant:

- Users and authentication/session/recovery records
- Accounts and plan entitlements
- Platform operators, Account plan lifecycle/suspension, and platform audit
- Companies
- Memberships and invitations
- Minimal scheduling-dispatch records

These tables use strict grants, application authorization, and targeted query paths. They must not contain copied customer/document financial payloads merely to avoid RLS.

The approved Platform Operations pages may query only this bounded control-plane metadata. Platform Owner is not an RLS-bypass role and cannot use an unscoped connection to inspect tenant business tables. Full-action impersonation is the approved support-access path after recent password confirmation and throttling: it leaves and blocks the platform control plane, establishes a selected non-operator User as the effective identity, and enters Company context only through that User's ordinary active membership and Account eligibility. RLS therefore exposes exactly what the selected User can access, never everything the Platform Owner might want to inspect.

### Tenant-owned business data

RLS applies to every company-owned business table, including the eventual equivalents of:

- Company settings, currencies, tax presets, numbering series/counters, and bank accounts
- Customers and contacts
- Products and services
- Quotes, invoices, recurring templates, and all their lines/snapshots
- Payments, refunds, and adjustments
- Reminder rules and instances
- Recurring occurrences
- Public-document links
- Email templates, attempts, and provider events
- Audit events
- Uploaded/generated asset metadata

Every tenant-owned row carries a non-null native UUID `company_id`, including child tables. Parent tables expose a unique key suitable for `(company_id, id)` UUID references, and composite foreign keys enforce that a child cannot reference a parent in another company. These types follow the approved [Domain Identifier Policy](identifier-policy.md).

## Database roles

Use separate PostgreSQL roles:

- A schema owner/migration role used only by controlled deployment and migration operations
- A restricted runtime role used by Laravel web requests and business jobs
- When needed, a narrowly granted scheduling dispatcher role limited to the minimal dispatch table

The runtime role:

- Does not own tenant tables
- Is not a superuser
- Does not have `BYPASSRLS`
- Receives only required table/sequence/function grants

Enable and force RLS on tenant-owned tables. A missing applicable context/policy is default-deny. Migrations must create the policies and grants as part of the same reviewed schema change as each tenant-owned table.

## Tenant policy

The equivalent policy on each tenant-owned table is:

```sql
USING (
    company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
)
WITH CHECK (
    company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
)
```

This restricts reads, updates, and deletes and prevents inserts or updates from writing another company's `company_id`.

The `::uuid` cast is intentional: company IDs and all domain foreign keys are PostgreSQL-native UUIDs, not text values inferred merely from this policy example.

## Request and job context

Tenant context is transaction-scoped so it cannot leak through a persistent database connection or long-lived queue worker.

For an authenticated request:

1. Resolve the selected company from control-plane data.
2. Verify membership and route permission in Laravel.
3. Begin the tenant database transaction.
4. Set the company with `set_config('app.current_company_id', <uuid>, true)`.
5. Execute all tenant queries and writes.
6. Commit or roll back, automatically clearing the local setting.

Every company-scoped queue job carries a trusted server-generated `company_id` and enters the same transaction-local context before loading tenant data. Job completion, failure, or exception must clear the context through transaction completion.

Do not use a connection-session tenant value that depends on manual cleanup. Do not let a client-provided company ID establish context until membership/permission has been verified.

Keep tenant transactions short. Do not wait for user input, external email/PDF providers, or streamed downloads while holding them open. Persist the business operation, commit, then perform retryable external work through the queue.

## Laravel enforcement

RLS is the backstop, not the only normal query style.

- Tenant-owned Eloquent models use a shared tenant-owned contract/trait for explicit company assignment and normal scoping.
- Controllers and actions obtain the active company from trusted tenant context rather than request fields.
- Policies evaluate membership and action permission even when the row belongs to the active company.
- Validation constrains referenced IDs to the current company.
- Application actions set `company_id`; database policies and composite foreign keys verify it.
- Generic `withoutGlobalScopes()` or unscoped tenant queries are prohibited in web controllers and ordinary jobs.

Company transfer changes account ownership and memberships without changing the company's ID or rewriting its business rows, so RLS identity remains stable.

## Public document bootstrap

Public requests begin without membership or a known company. Resolve them without opening a cross-tenant list/query surface:

1. Hash the presented token; never store or query by its plaintext value.
2. Begin a transaction and set the hash as transaction-local `app.public_link_hash`.
3. A dedicated SELECT-only RLS policy permits reading only the public-link row whose stored hash matches that setting and is otherwise eligible for lookup.
4. Take the returned company ID and establish normal company tenant context in the same transaction.
5. Load the linked quote/invoice through ordinary tenant RLS and apply expiry, revocation, lifecycle, rate-limit, and action rules.

The token-lookup policy does not grant general access to quote, invoice, customer, or company tables.

## Scheduler bootstrap

The global scheduler must discover due work without bypass access to tenant business tables. Use a minimal control-plane dispatch table containing only:

- Company ID
- Opaque target ID
- Job type
- Due UTC timestamp
- Idempotency key
- Claim/status metadata

A narrowly granted dispatcher claims due rows and queues jobs. Each job then enters the normal company RLS context before it can load the target template, invoice, reminder, customer, or email data.

Do not give the scheduler or queue worker a general RLS-bypass role.

## Testing and verification

Tenant-isolation integration tests must connect as the restricted production-equivalent runtime role, not as the schema owner or a superuser.

Required tests prove:

- Unset company context cannot read, insert, update, or delete tenant rows
- Company A cannot read, mutate, or delete Company B data through ORM, query-builder, or raw-query paths
- Company A cannot attach a child row to a Company B parent
- A client-supplied company ID cannot select context without membership
- Unscoped Eloquent queries remain constrained by RLS
- Queue jobs establish and clear context between sequential jobs for different companies
- Public-link lookup exposes only the row matching the supplied token hash
- The scheduling dispatcher cannot read tenant business data
- Migration privileges remain unavailable to the runtime role
- Ownership transfer preserves tenant identity and isolation
- Platform Operations cannot read tenant business rows without full-action impersonation entering a Company context authorized for the selected effective User
- Impersonation never widens the selected User's Company abilities or RLS visibility, cannot target an active Platform Owner, blocks all Platform Operations while active, cannot nest, and preserves original-operator attribution on audited mutations
- Account suspension denies access only to Companies owned by the suspended Account

Run these tests in continuous integration against PostgreSQL. SQLite is not an acceptable substitute for isolation, concurrency, numbering, or scheduling integration tests.

## Reference

- [PostgreSQL Row-Level Security](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)
