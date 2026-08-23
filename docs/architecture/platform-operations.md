# Platform Operations

Status: Approved v1 architecture and product contract
Last updated: 2026-08-23

Platform Operations is Invumo's internal SaaS back office. It is separate from every tenant Company's Owner, Admin, and Member permissions.

This contract adds the minimum operational surface required to understand and administer the hosted platform without turning v1 into a billing system or granting routine access to tenant business data.

## 1. Identity and authorization boundary

- A normal verified Invumo User may separately hold the `Platform Owner` role through a `platform_operators` record.
- Platform authority never comes from a Company membership, Account ownership, an email-domain rule, a client-provided flag, or a Company role.
- v1 has one platform role only: `Platform Owner`. Additional platform roles require an explicit later permission design.
- Granting or revoking Platform Owner is not available through the web UI in v1. A protected application command performs it by exact User identity, requires explicit confirmation, prevents removal of the last active Platform Owner, and records a platform audit event.
- Platform routes require authentication, verified email, current operator revalidation, CSRF protection for mutations, recent password confirmation for sensitive actions, and dedicated rate limits.
- Every platform mutation re-reads and locks the operator plus target control-plane record inside its transaction. React receives named platform abilities and never infers authority from role strings.
- TOTP remains deferred from v1. This does not weaken the verified-email, recent-password, session-revocation, audit, or last-operator safeguards.

Platform authority and Company authority are independent. A Platform Owner may also be a Company Owner/Admin/Member, but neither role implies the other.

## 2. Interface boundary

Platform Operations lives under `/platform` in the same Laravel/Inertia application and deployment. It uses a distinct platform shell so the operator always knows whether they are administering Invumo or working inside a Company.

The ordinary Company sidebar never contains platform navigation. An authenticated Platform Owner receives a restrained “Platform operations” destination in the account menu. Non-operators receive no platform props or navigation and cannot access platform routes directly.

v1 pages are:

1. **Overview** — counts and recent control-plane activity.
2. **Users** — searchable User identity, verification/suspension state, registration/last-login dates, owned Account plan state, and accessible-Company count.
3. **Accounts** — searchable owner, current plan/lifecycle, suspension state, owned-Company count, and lifecycle dates.
4. **Companies** — searchable control-plane Company name, owning Account/User, member count, archive state, and creation date.
5. **Plan lifecycle** — filterable active, trialing, past-due, cancel-at-end, expired, and upcoming-expiry views.
6. **Platform audit** — append-only history of platform grants and every platform mutation.

These are operational lists, not product analytics or financial reports. Use stable indexed ordering and cursor pagination for growing lists.

## 3. Data visibility

Platform Operations may read only the control-plane fields needed by the pages above:

- User identity and authentication state;
- Account ownership, plan/lifecycle, and suspension state;
- Company selector identity, ownership, membership counts, and archive state;
- Platform operator identity;
- Platform audit events.

It does not expose Customers, contacts, Products, Quotes, Invoices, recurring-template contents, Transactions, bank/tax settings, document/public-link payloads, tenant audit payloads, email bodies/recipients, PDFs, or uploaded files.

Platform Operations does not use a general RLS bypass. A future support workflow that needs tenant business data requires a separately approved, time-bounded, reasoned, and audited break-glass design. v1 has no impersonation or “log in as User” feature.

## 4. Current plan lifecycle

Each Account keeps one current plan assignment. The existing `plan_id` remains authoritative for entitlements. v1 adds provider-independent operational lifecycle fields to the Account rather than introducing a second subscription/billing aggregate:

- `plan_status`: `TRIALING`, `ACTIVE`, `PAST_DUE`, `CANCELED`, or `EXPIRED`;
- `plan_started_at`;
- nullable `trial_ends_at`;
- nullable `access_ends_at`;
- `cancel_at_period_end`;
- nullable `ended_at`.

Free is a Plan, not another status. A normal Free Account is `ACTIVE` with no access end. Pro and Enterprise remain seeded plans and can initially be assigned manually.

The Platform Owner may change the current Plan and lifecycle fields with confirmation, a required reason, validation, row locking, and platform audit. The plan must be active. Dates use UTC timestamps and obey:

- trial end and access end cannot precede plan start;
- cancel-at-period-end requires an access end;
- canceled/expired states require an end timestamp;
- trialing requires a future trial end when saved;
- an ended lifecycle cannot be presented as active without an explicit reactivation/update action.

“Expiring” is a derived operational view from a trial/access end within the selected 7- or 30-day window. Plan expiry or past-due state never deletes data, silently changes plan, or automatically suspends access in v1. Explicit plan reassignment or Account suspension controls those outcomes. A future billing provider must map its events into these Invumo-owned concepts through a separately approved adapter and idempotent workflow.

v1 does not include self-service checkout, prices, payment collection, platform invoices, tax handling for Invumo fees, provider webhooks, automated renewals/dunning, or a Plan-creation/editor interface.

## 5. Suspension and session behavior

### User suspension

- A Platform Owner may suspend/reactivate a non-operator User with recent password confirmation, explicit confirmation, and a required reason.
- Suspension invalidates that User's active sessions and blocks new authentication while preserving the User, Account, memberships, and business history.
- A Platform Owner cannot suspend itself or the last active Platform Owner.

### Account suspension

- A Platform Owner may suspend/reactivate an Account with the same guarded controls.
- Account suspension blocks access to every Company owned by that Account for all members, but does not remove memberships or alter tenant data.
- A User who also belongs to Companies owned by other active Accounts may continue using those Companies.
- Suspension does not delete data, change the assigned Plan, consume invitations, or masquerade as a billing status.

Normal request entry, Company selection, direct Company URLs, invitation acceptance, jobs, and public mutation paths must recheck the relevant User/Account suspension boundary server-side. Read-only public documents are not silently revoked by Account suspension; changing that behavior requires an explicit later decision.

## 6. Platform audit

`platform_audit_events` is a separate append-only control-plane log because Company audit requires a Company tenant context.

Each event records a UUIDv7 identifier, nullable actor User for bootstrap/system cases, action, target type/UUID, required reason where applicable, allowlisted before/after fields, occurrence time, and an idempotency/correlation reference when needed.

Platform audit follows the same payload-safety contract as Company audit: action-specific allowlists, no copied requests/models/provider payloads, no credentials/tokens, and no tenant business content. Runtime code may insert and authorized Platform Owners may read; ordinary Users cannot read it, and application runtime code cannot update or delete it.

Required actions include platform-owner grant/revoke, User suspension/reactivation, Account suspension/reactivation, session revocation, and plan/lifecycle changes.

## 7. PostgreSQL shape and indexes

Control-plane tables use strict runtime grants and Laravel authorization, not tenant RLS:

- `platform_operators`: UUIDv7 `id`, unique/indexed `user_id`, checked role, timestamps;
- `platform_audit_events`: UUIDv7 `id`, nullable/indexed actor, action, target type/UUID, reason, allowlisted `jsonb` before/after, occurred timestamp, optional unique idempotency key;
- `users`: nullable `suspended_at` and `last_login_at`;
- `accounts`: the lifecycle and nullable `suspended_at` fields defined above.

Indexes must support operator lookup, User email/created/suspension lists, Account plan-status/end/suspension lists, Company owner/name/creation lists, and reverse-chronological platform audit queries. Foreign-key columns are indexed. Text statuses use named check constraints.

## 8. Required tests

Tests must prove:

- Company Owner/Admin/Member roles never grant platform access;
- an operator record for an unverified, missing, or suspended User does not grant access;
- ordinary Users receive no platform navigation/props and direct access is denied;
- platform reads expose only approved control-plane fields and cannot query tenant business rows without Company context;
- every mutation revalidates current authority, requires its guards, locks the target, and writes one allowlisted platform audit event;
- plan lifecycle constraints and 7-/30-day expiry lists are correct at exact timestamp boundaries;
- User suspension invalidates sessions and blocks authentication;
- Account suspension blocks only Companies owned by that Account and preserves access to unrelated active Accounts;
- self-suspension and removal of the last active Platform Owner are impossible;
- no platform mutation can alter the singular Company Owner invariant or bypass ownership transfer;
- EN/RO UI, responsive layout, accessibility, and shared design-system rules remain intact.

## 9. Implementation sequence

Platform Operations remains inside Phase 1 but follows its prerequisites:

1. complete control-plane identity/schema and authentication/session foundations;
2. complete Company membership governance and ownership transfer;
3. add platform operator, Account lifecycle/suspension, and platform audit schema;
4. implement platform authorization and bootstrap command;
5. implement guarded Actions/Queries and the shared-component back office;
6. create the first Platform Owner only through an explicitly authorized production action;
7. verify platform/tenant boundaries before Phase 1 sign-off.
