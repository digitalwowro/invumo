# Domain Identifier Policy

Status: Approved architecture decision
Last updated: 2026-08-22

Invumo uses PostgreSQL-native UUIDv7 identifiers consistently across its business domain. This is an explicit schema rule, not an implication of the tenant-isolation examples.

## Domain primary keys

Every domain entity uses a native PostgreSQL `uuid` primary key generated as UUIDv7. This includes the eventual equivalents of:

- Users, accounts, companies, memberships, and invitations
- Company settings and reusable configuration records
- Customers and contacts
- Products and services
- Quotes, invoices, recurring templates, and their lines and snapshots
- Payments, refunds, and adjustments
- Reminder rules, reminder instances, and recurring occurrences
- Public-link records, business email records, audit events, and business asset metadata

Do not use auto-incrementing integer primary keys or store UUIDs in `varchar(36)` columns for domain entities.

Laravel generates identifiers before insertion through Eloquent's `HasUuids` support, which generates UUIDv7 by default. Use one shared domain-model convention so generation and model configuration are not repeated inconsistently. PostgreSQL 18 also supports UUIDv7, but application-generated identifiers are the canonical path.

## Foreign keys and tenancy

Every domain foreign key uses the native PostgreSQL `uuid` type. In particular:

- `company_id` is always a native UUID.
- Every tenant-owned row carries a non-null UUID `company_id`.
- Same-company composite foreign keys use UUID columns such as `(company_id, parent_id)`.
- Parent tables expose the corresponding unique `(company_id, id)` key required by those references.

The `::uuid` cast in the Row-Level Security tenant policy is therefore deliberate and schema-wide.

## Explicit exceptions

Framework infrastructure tables may retain the identifiers expected by Laravel or the installed driver. This includes migrations, cache, sessions, and Laravel queue internals. Do not customize those tables solely to create superficial identifier uniformity.

A pure join table with no identity, lifecycle, attributes, or independent audit meaning may use a composite primary or unique key instead of an artificial UUID. A relationship that is itself a business record, such as a company membership, receives its own UUID.

External provider identifiers remain provider-defined strings and are never reused as Invumo primary keys.

## Security and public identifiers

UUIDs are identifiers, not authorization credentials. Authorization always comes from authentication, membership, Policies, tenant scoping, and PostgreSQL RLS.

UUIDv7 values are time-ordered and must not be treated as secret or fully opaque. Public quote and invoice access continues to use separate cryptographically random tokens whose plaintext is shown only to the intended recipient and whose stored representation is hashed.

Human-facing quote and invoice numbers remain separate company-controlled business identifiers. UUID adoption does not alter numbering, duplicate warnings, manual overrides, or counter locking.

## Verification

Schema and model tests must prove:

- Domain primary and foreign-key columns use PostgreSQL `uuid`, not text or integers
- New domain models generate valid UUIDv7 identifiers before insertion
- `company_id` and parent identifiers cannot form a cross-company relationship
- UUID presence never bypasses normal authorization or RLS
- Public tokens and provider identifiers remain separate from domain primary keys
- Framework-table exceptions remain isolated from domain-model conventions

## References

- [Laravel UUID and ULID keys](https://laravel.com/docs/13.x/eloquent#uuid-and-ulid-keys)
- [PostgreSQL UUID type](https://www.postgresql.org/docs/current/datatype-uuid.html)
- [PostgreSQL UUID functions](https://www.postgresql.org/docs/current/functions-uuid.html)
