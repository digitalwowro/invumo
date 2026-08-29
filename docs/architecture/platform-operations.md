# Platform Operations

Status: Approved v1 architecture and product contract
Last updated: 2026-08-24

Platform Operations is Invumo's internal SaaS back office. It is separate from every tenant Company's Owner, Admin, and Member permissions.

This contract adds the operational surface required to administer the hosted platform, including explicit full-action User impersonation for support, without turning v1 into a billing system or granting a general tenant-data/RLS bypass.

## 1. Identity and authorization boundary

- A normal verified Invumo User may separately hold the `Platform Owner` role through a `platform_operators` record.
- Platform authority never comes from a Company membership, Account ownership, an email-domain rule, a client-provided flag, or a Company role.
- v1 has one platform role only: `Platform Owner`. Additional platform roles require an explicit later permission design.
- Granting or revoking Platform Owner is not available through the web UI in v1. A protected application command performs it by exact User identity, requires explicit confirmation, prevents removal of the last active Platform Owner, and records a platform audit event.
- Platform routes require authentication, verified email, current operator revalidation, and CSRF protection for mutations. Every sensitive Platform mutation, including impersonation start, uses Laravel's shared recent-password confirmation window through `RequirePassword`; no guarded business-action endpoint accepts or verifies a password directly. The mutation routes remain throttled to 10 attempts per minute. Impersonation does not require a support reason or a separate action-specific confirmation after reauthentication.
- Every platform mutation re-reads current authority and locks its target control-plane records inside its transaction. Platform Owner grant/revoke additionally uses one transaction-scoped PostgreSQL advisory lock so concurrent command runs remain serialized without granting UPDATE on the protected operator table. React receives named platform abilities and never infers authority from role strings.
- TOTP remains deferred from v1. The impersonation flow does not remove recent-password, verified-email, current-operator, CSRF, throttling, audit-attribution, or last-operator safeguards from their applicable boundaries.

Platform authority and Company authority are independent. A Platform Owner may also be a Company Owner/Admin/Member, but neither role implies the other.

After the approved business migrations exist in the target environment and the User has registered and verified their email, the protected command is:

```bash
php artisan invumo:platform-owner grant owner@example.com --reason="Initial authorized Platform Owner"
```

Use `revoke` in place of `grant` for removal. The command always asks for interactive confirmation; it never creates a User, bypasses verification/suspension, or permits the last active Platform Owner to be removed. Creating the first production operator still requires the separate explicit production authorization tracked for Phase 1.

## 2. Interface boundary

Platform Operations lives under `/platform` in the same Laravel/Inertia application and deployment. It uses a distinct platform shell so the operator always knows whether they are administering Invumo or working inside a Company.

The ordinary Company sidebar never contains platform navigation. An authenticated Platform Owner receives a restrained “Platform operations” destination in the account menu. Non-operators receive no platform props or navigation and cannot access platform routes directly.

v1 pages are:

1. **Overview** — counts and recent control-plane activity.
2. **Users** — searchable User identity, verification/suspension state, registration/last-login dates, owned Account plan state, and accessible-Company count.
3. **Accounts** — searchable owner, current plan/lifecycle, suspension state, owned-Company count, and lifecycle dates.
4. **Companies** — searchable control-plane Company name, owning Account/User, member count, archive state, and creation date.
5. **Plan lifecycle** — filterable active, trialing, past-due, cancel-at-end, expired, and upcoming-expiry views.
6. **Platform audit** — append-only history of platform grants and every platform mutation, plus privacy-minimal erasure proof and aggregate private-file cleanup status.

These are operational lists, not product analytics or financial reports. Use stable indexed ordering and cursor pagination for growing lists.

## 3. Data visibility

Platform Operations may read only the control-plane fields needed by the pages above:

- User identity and authentication state;
- Account ownership, plan/lifecycle, and suspension state;
- Company selector identity, ownership, membership counts, and archive state;
- Platform operator identity;
- Platform audit events.

The Platform Operations pages themselves do not expose Customers, contacts, Products, Quotes, Invoices, recurring-template contents, Transactions, bank/tax settings, document/public-link payloads, tenant audit payloads, email bodies/recipients, PDFs, or uploaded files.

Platform Operations does not use a general RLS bypass. Tenant business data becomes available to a Platform Owner only after explicitly entering the approved impersonation flow, and then only through the selected User's ordinary Company memberships, permissions, application authorization, and RLS context.

## 4. Platform reauthentication contract

Laravel/Fortify is the single authority for Platform password reauthentication:

- a successful password confirmation writes the server-owned `auth.password_confirmed_at` session timestamp;
- `RequirePassword` guards every sensitive Platform mutation and accepts that confirmation only for Laravel's configured `auth.password_timeout` window;
- a guarded business-action request never contains, validates, logs, or receives the operator's password; only Fortify's dedicated confirmation endpoint receives it;
- the confirmation window may be reused for other guarded Platform mutations until it expires, matching Laravel's other sensitive-action behavior;
- the Platform impersonation control checks the same Fortify confirmation status before submitting its mutation. If the window is stale, one localized dialog confirms the password through a Platform-scoped Fortify endpoint throttled to 10 attempts per minute, then submits the separately guarded impersonation action automatically. This preserves the single-interaction UI without turning the action endpoint into a password-verification oracle;
- a direct stale-window request to any guarded mutation remains fail-closed through `RequirePassword`;
- entering impersonation clears the original operator's confirmation timestamp before the selected User becomes effective, and exiting clears any timestamp established by the effective User before restoring the operator. A password-confirmation window never crosses an identity boundary.

This is the sole Platform web reauthentication contract. Platform features may not add inline `current_password` fields or private confirmation-window implementations. Support reasons and action-specific confirmation dialogs remain separate controls and are required only where their individual product rules say so.

## 5. Full-action User impersonation

A Platform Owner may start impersonating a selected existing non-operator User directly from Platform Operations. This is full-action impersonation inside the selected User's ordinary application surface: the resulting application session behaves as that User, not as a read-only support preview.

- Starting impersonation revalidates that the initiating session still belongs to a verified, unsuspended current Platform Owner, requires the shared recent-password window, and is throttled to 10 action attempts per minute. When reauthentication is stale, the localized dialog names the selected User, establishes that window through the separately throttled Fortify confirmation endpoint, and then starts impersonation without returning to the Users list for a second click.
- It requires no support reason, separate action-specific confirmation after reauthentication, impersonation-specific duration, or restriction on actions ordinarily available to the selected User.
- The selected User becomes the effective authenticated identity. Navigation, Companies, records, abilities, validation, authorization, suspension behavior, and PostgreSQL RLS resolve exactly as they would for that User.
- The Platform Owner receives no extra Company ability and no RLS bypass while impersonating. If the selected User cannot access or perform something, neither can the impersonated session.
- Every action available to the selected User remains available, including mutations and real external effects such as document email, reminder, payment, refund, membership, settings, and deletion workflows where that User's normal role permits them.
- There is no impersonation-specific timeout. The session lasts until explicit exit, logout, or the ordinary application session lifetime ends.
- A persistent, unmistakable shared-shell banner identifies the selected User and offers an immediate exit action. Pages may not hide or restyle this indicator.
- An active Platform Owner cannot be selected as the target. The target check occurs inside the same advisory-lock-protected transaction used for operator mutations, so a concurrent grant cannot race the decision.
- Platform Operations is unavailable for the entire impersonated session, even if an operator record appears later. This independently prevents platform-role grants/revocations and every other platform mutation from running under an impersonated identity.
- Nested impersonation is prohibited. An already impersonating session cannot start another impersonation.
- Any existing User who is not an active Platform Owner may be selected. An unverified User remains inside the ordinary verification flow with the banner and exit available. A suspended User remains suspended and is confined to a dedicated identity-warning screen whose only application action is exiting impersonation; support entry never weakens the suspension boundary.
- Exiting restores the original User only if that User still has a verified, unsuspended current Platform Owner record, then returns directly to the Platform Users screen without passing through Company-home routing. Otherwise the session ends safely at sign-in.
- Start and explicit end are recorded in platform audit. Every normally audited mutation performed during impersonation records the selected User as the effective actor and the original Platform Owner as the impersonator. Outward customer-facing behavior remains attributable to the selected User. Company audit history exposes only the neutral fact that an event was performed through Invumo support access; it never returns or searches the Platform Owner's name or other identity. Named operator attribution remains restricted to Platform audit and internal support governance.

The server-side database session retains the original Platform Owner identifier and impersonation start timestamp; these values are never accepted from browser input. Session regeneration occurs when starting and ending impersonation so neither identity transition reuses an older session identifier.

## 6. Current plan lifecycle

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

## 7. Suspension and session behavior

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

## 8. Platform audit

`platform_audit_events` is a separate append-only control-plane log because Company audit requires a Company tenant context.

Each event records a UUIDv7 identifier, nullable actor User for bootstrap/system cases, nullable original impersonator User when the effective User is acting through impersonation, action, target type/UUID, required reason where applicable, allowlisted before/after fields, occurrence time, and an idempotency/correlation reference when needed.

Platform audit follows the same payload-safety contract as Company audit: action-specific allowlists, no copied requests/models/provider payloads, no credentials/tokens, and no tenant business content. Runtime code may insert and authorized Platform Owners may read; ordinary Users cannot read it, and application runtime code cannot update or delete it. The same protected page exposes the separate `data_erasure_events` proof stream with aggregate Company-file cleanup counts. It never returns a retained disk, storage key, location fingerprint, or deleted tenant payload.

Required actions include platform-owner grant/revoke, impersonation start/end, User suspension/reactivation, Account suspension/reactivation, session revocation, and plan/lifecycle changes. Company audit events add a nullable original-impersonator User reference so an impersonated mutation never erases the real operator identity.

## 9. PostgreSQL shape and indexes

Control-plane tables use strict runtime grants and Laravel authorization, not tenant RLS:

- `platform_operators`: UUIDv7 `id`, unique/indexed `user_id`, checked role, timestamps;
- `platform_audit_events`: UUIDv7 `id`, nullable/indexed actor, nullable/indexed original impersonator, action, target type/UUID, reason, allowlisted `jsonb` before/after, occurred timestamp, optional unique idempotency key;
- `data_erasure_events`: privacy-minimal UUIDv7 proof, nullable actor, action, subject type/UUID, and occurred timestamp;
- `company_erasure_files`: retained erasure-event relationship, private storage coordinate and non-secret location fingerprint only while cleanup is pending, attempt/failure state, and immutable completion evidence;
- `users`: nullable `suspended_at` and `last_login_at`;
- `accounts`: the lifecycle and nullable `suspended_at` fields defined above.

Indexes must support operator lookup, User email/created/suspension lists, Account plan-status/end/suspension lists, Company owner/name/creation lists, and reverse-chronological platform audit queries. Foreign-key columns are indexed. Text statuses use named check constraints.

## 10. Required tests

Tests must prove:

- Company Owner/Admin/Member roles never grant platform access;
- an operator record for an unverified, missing, or suspended User does not grant access;
- ordinary Users receive no platform navigation/props and direct access is denied;
- platform reads expose only approved control-plane fields and cannot query tenant business rows without Company context;
- guarded mutations reject a stale password-confirmation window, accept the shared Fortify window within the configured timeout, and never accept a password in the mutation payload;
- the Platform confirmation endpoint is CSRF protected and rate-limited, incorrect credentials do not establish the window, and a successful dialog confirmation immediately submits the separately guarded impersonation action;
- impersonation respects its 10-per-minute action throttle, starts only from a current Platform Owner, rejects an active Platform Owner target, clears confirmation state across both identity transitions, regenerates the session, exposes exactly the target User's Companies/abilities/RLS rows, and grants no operator-derived tenant ability;
- impersonated sessions can perform every action normally permitted to the selected User, including external effects, while denied actions remain denied;
- impersonated sessions cannot access Platform Operations even if the effective User gains platform authority after entry;
- impersonation requires no support reason, separate action-specific confirmation, or special timeout, shows the shared persistent banner, exits safely, and cannot nest;
- platform start/end events and every normally audited impersonated mutation preserve both original Platform Owner and effective User identities;
- every mutation revalidates current authority, requires its guards, locks the target, and writes one allowlisted platform audit event;
- plan lifecycle constraints and 7-/30-day expiry lists are correct at exact timestamp boundaries;
- User suspension invalidates sessions and blocks authentication;
- Account suspension blocks only Companies owned by that Account and preserves access to unrelated active Accounts;
- self-suspension and removal of the last active Platform Owner are impossible;
- no platform mutation can alter the singular Company Owner invariant or bypass ownership transfer;
- EN/RO UI, responsive layout, accessibility, and shared design-system rules remain intact.

## 11. Implementation sequence

Platform Operations remains inside Phase 1 but follows its prerequisites:

1. complete control-plane identity/schema and authentication/session foundations;
2. complete Company membership governance and ownership transfer;
3. add platform operator, Account lifecycle/suspension, and platform audit schema;
4. implement platform authorization and bootstrap command;
5. implement guarded Actions/Queries and the shared-component back office;
6. implement full-action impersonation, dual-identity audit attribution, and the persistent shared-shell exit banner;
7. create the first Platform Owner only through an explicitly authorized production action;
8. verify platform/tenant/impersonation boundaries before Phase 1 sign-off.
