# Production Runtime Baseline

Status: Phase 1 operational baseline complete; public-launch operations verification remains open

Verified: 2026-08-24

This document records the production runtime that currently serves Invumo. It contains no credentials and does not replace the canonical [development tracker](../development/development-tracker.md).

## Temporary pre-launch operating model

Until public launch, while Invumo has no external users or customer business data, development occurs directly in this hosted production checkout. This is an approved temporary simplification. Source control and relevant automated checks still apply, but a repeatable application deployment process is not a Phase 1 requirement.

Rollback, off-server database/file backup and restore, uptime/error monitoring, and alert delivery are provided by the owner's external infrastructure and are not implemented or configured in this repository. Before public launch, Phase 12 must verify evidence that those safeguards are active and usable. Before real users make direct-production development unsafe, Invumo must add separate development and production environments and a repeatable release process.

## Public application

- Canonical URL: `https://app.invumo.com`
- Application repository: [`digitalwowro/invumo`](https://github.com/digitalwowro/invumo)
- Application checkout: `/home/invumo/invumo`
- Nginx document root: `/home/invumo/invumo/public`
- PHP runtime: PHP 8.5 FPM using the `invumo` pool
- Database: PostgreSQL 18
- Public health endpoint: `https://app.invumo.com/up`

Nginx, PHP-FPM, and PostgreSQL remain system-managed services. Laravel web requests execute through the unprivileged `invumo` PHP-FPM pool.

## Environment and database separation

The production `.env` is untracked, owned by `invumo`, and mode `0600`. `APP_ENV=production`, `APP_DEBUG=false`, the canonical HTTPS URL, encrypted database sessions, Secure/HttpOnly/SameSite cookies, database cache/queue drivers, and host-only cookie scope are configured. `DB_QUEUE_CONNECTION` and `DB_TENANT_JOB_LOCK_CONNECTION` resolve to the restricted `pgsql` runtime connection, and `DB_QUEUE_RETRY_AFTER=120` remains greater than the worker's 90-second timeout. These are atomicity and duplicate-execution safeguards, not tuning suggestions. The cached Laravel configuration is also mode `0600` because it contains resolved secrets.

PostgreSQL uses two login roles:

- `invumo_schema` owns the database and is used only by controlled migrations;
- `invumo_runtime` is used by web requests and jobs.

Neither role is a superuser, may create databases or roles, or may bypass RLS. The runtime role cannot create schema objects or read/write the Laravel migration repository. The Phase 1 business-schema foundation now enforces the reusable forced-RLS/restricted-grant contract and isolated PostgreSQL tests; each later feature migration must apply it to its own tenant tables.

Production-like migrations now require `invumo_runtime` before executing any conditional grant path. A missing role aborts with a named configuration error instead of allowing a successful but incomplete migration. Local and isolated testing may omit split roles deliberately; when the role exists, the same grants and denials are applied and verified.

The one-time [database bootstrap](../../scripts/bootstrap-production-database.sh) creates or normalizes these roles without deleting an existing database, generates independent secrets without printing them, writes them only to `.env`, runs migrations through `pgsql_schema`, revokes runtime migration-table access, and caches production configuration. It refuses to run again after its placeholders have been replaced.

Verified on 2026-08-24: all Phase 1 migrations through `2026_08_24_123000_create_company_assets` ran in production through the `pgsql_schema` connection as the `invumo` operating-system user. Post-migration readiness, private configuration/view caches, the user-owned queue worker, restricted runtime access, and the complete runtime verifier all passed. Later feature migrations remain just-in-time work owned by their phases.

## Queue worker

The database-backed Laravel worker is a user-level systemd service:

- source definition: [`ops/systemd/invumo-queue.service`](../../ops/systemd/invumo-queue.service);
- installed definition: `/home/invumo/.config/systemd/user/invumo-queue.service`;
- operating-system identity: `invumo`;
- boot persistence: systemd lingering for `invumo`;
- logs: system journal under `invumo-queue`.

Routine commands run as `invumo`, never root:

```bash
systemctl --user status invumo-queue
systemctl --user restart invumo-queue
journalctl --user --unit=invumo-queue
```

## Scheduler

The Laravel scheduler is invoked once per minute from the `invumo` user's crontab using the tracked [`ops/cron/invumo-scheduler`](../../ops/cron/invumo-scheduler) entry. `flock` prevents overlapping scheduler invocations, the command runs with a private umask, and output is sent to the journal with the `invumo-scheduler` identifier.

```bash
crontab -l
journalctl --identifier=invumo-scheduler
```

The tracked [service installer](../../scripts/install-production-services.sh) installs both user-owned definitions without sudo. The [runtime verifier](../../scripts/verify-production-runtime.sh) checks environment permissions, the production-configuration contract, migrations, system services, queue supervision, scheduler installation, the public health endpoint, and the login redirect without revealing secrets.

Run `php artisan invumo:production-configuration --no-interaction` after a production configuration/release change and before admitting traffic, or run the complete runtime verifier that invokes it. The command validates HTTPS/non-debug operation, production environment identity, split PostgreSQL credentials, encrypted secure database sessions, database queue/cache, matching business/queue/tenant-job-lock connections, a visibility timeout above the worker timeout, authenticated SMTP, and English/Romanian localization. Failure names only unsafe configuration keys and returns a non-zero exit code; it never prints configured values.

The assertion is intentionally absent from service-provider boot. This keeps Artisan inspection/repair commands and the health route bootable when configuration is unsafe. `/up` runs the same production contract and additionally opens the runtime database connection, verifies that PostgreSQL reports `invumo_runtime`, and fails if any tenant context was inherited. A successful browser request renders Laravel's bounded `Application up` health page, while JSON requests receive the bounded `up` status and failures receive only the bounded `down` surface; operators use the command for the cause. Ordinary web responses carry a server-generated `X-Request-ID`; operational logs accept only bounded machine labels, outcomes, counts/timings, and that correlation identifier. Customer values, record identifiers, free-text reasons, recipients, payloads, tokens, credentials, and exception messages are not accepted as operational-log context.

## Test isolation in the hosted production checkout

Automated tests must never use the live `invumo` database. Both Laravel PostgreSQL connections are forced to `invumo_test` by `phpunit.xml`, and the base Laravel test case aborts before database-reset traits run unless the application environment is `testing` and every PostgreSQL connection name ends in `_test`. This guard also blocks a direct `php artisan test` invocation while production configuration remains cached.

The one-time root-administered [test-database bootstrap](../../scripts/bootstrap-test-database.sh) creates the physically separate `invumo_test` database under the existing schema/runtime roles without reading, changing, copying, or dropping production data. Application tests still run as `invumo`, not root.

Verified on 2026-08-23: the complete CI command ran against `invumo_test` with 34 passing Laravel tests and 139 assertions, the production database counts were unchanged before and after, production configuration was re-cached with mode `0600`, and the public runtime remained healthy.

## System email through ZeptoMail

Foundational account email uses ZeptoMail's authenticated SMTP service on port 587 with automatic STARTTLS, a ten-second connection timeout, and a verified sender. The password remains only in the private production environment and its mode-`0600` cached configuration; it must never be committed, copied into documentation, or pasted into a task.

Run the interactive [ZeptoMail configurator](../../scripts/configure-zeptomail.sh) as the `invumo` user:

```bash
/home/invumo/invumo/scripts/configure-zeptomail.sh
```

The configurator hides the password while it is entered, validates the non-secret inputs, updates the environment atomically, rebuilds the private configuration cache, sends one synchronous test message, and restarts the user-owned queue worker. If any step fails, it automatically restores the previous mail configuration. The later document-email/webhook design remains a separate Phase 9 gate.

Verified on 2026-08-23: the regional ZeptoMail SMTP endpoint, authenticated TLS submission, verified sender, and bounded timeout are active in cached production configuration; the test message was accepted and received; the queue worker remained active after restart; and the environment/configuration files remained mode `0600`. This proves the transport. Verification and recovery flows use Laravel-authored English/Romanian copy, encrypted after-commit queue payloads, bounded retries, and delivery-time validity rechecks. Company-invitation delivery now uses the shared tenant-job contract: its encrypted database-queue row and uniqueness lock are inserted atomically with invitation creation/resend, the worker performs its validity check under short forced RLS, and mail submission starts after that transaction closes. Isolated tests exercise these boundaries without sending real email. The invitation schema is now deployed, but no live invitation or invitation email was created during the Phase 1 production closeout.

The owner also confirmed on 2026-08-23 that ZeptoMail domain authentication and DMARC were added and provider-verified. Later the same day, Google and Cloudflare public DNS resolvers both returned `_dmarc.invumo.com` as `v=DMARC1; p=none; adkim=r; aspf=r`, closing the propagation recheck.

## Private Company assets

Company-logo storage is configured through `COMPANY_ASSETS_DISK` and defaults to `company_assets_local`, whose root is `storage/app/company-assets`. This disk is private, has no public symbolic link or temporary-serving route, and requires the PHP `fileinfo` extension for content detection. Production upload validation must use bounded container/metadata inspection, native bulk traversal for JPEG entropy scans, and the shared structural-element cap; it must not fully decode untrusted raster pixels into GD memory or walk compressed payload bytes one at a time in PHP. Exact PNG/JPEG/WebP container termination rejects trailing bytes deliberately. GD remains a development/test dependency for generated fixtures, not a production upload-validation dependency. Stored-file SHA-256 verification is streamed so it does not retain a second complete copy. The production-readiness command rejects a missing, public, or framework-served Company-asset disk.

The current 32 GiB host's Invumo PHP-FPM safety envelope is a resolved `memory_limit` of exactly `128M` per request and `pm.max_children = 50` for the `invumo` pool. `memory_limit` must never be unlimited in FPM. These values cap the Invumo pool's PHP-managed heap budget at 6.25 GiB before native-library overhead, leaving capacity for PostgreSQL, Nginx, the queue worker, the operating system, and other hosted pools. Do not raise either value without measuring representative resident memory and recalculating the combined host budget; lowering `pm.max_children` is safe when traffic permits. HTTP uploads execute in PHP-FPM, so the single Laravel queue worker is not an upload-concurrency control.

The Company-asset metadata migration is deployed, but no Company-asset file, upload route, or public-serving route exists yet. Before the Phase 2 logo workflow is enabled in production, verify that the unprivileged application processes can write the private directory and confirm that the externally managed off-server file backup and restore scope includes it; the effective FPM `memory_limit` and `pm.max_children` must remain inside the envelope above. A later S3-compatible move follows the verified-copy transition in the [upload/storage contract](../architecture/uploads-and-storage.md), not a domain or UI rewrite.

## Platform Operations bootstrap

Verified on 2026-08-24: after explicit owner authorization, one existing-app-created and verified User with its personal Account received the initial Platform Owner grant through the protected application Action. No migration, seeder, fixture, web grant route, identifying credential, or tenant-data bypass created the authority. Production login succeeded, and all temporary authenticated smoke-test sessions were revoked afterward.

The live Platform overview, Users, and Accounts pages returned HTTP 200 at 1440×1000 and 390×844 viewports with no browser console warning/error or failed request. Anonymous `/platform` access returned to `/login`, and the sole active Platform Owner was not offered self-impersonation. The isolated Platform suite passed 32 tests with 321 assertions, including full-action impersonation of an ordinary User, exact target authorization/RLS, password-confirmed/throttled entry, operator-target rejection, blocked Platform Operations during impersonation, safe exit, and dual audit attribution.

## Verified behavior

The latest 2026-08-24 verification proved:

- `/up` returns HTTP 200;
- `/` returns HTTP 302 to the HTTPS login page;
- `/login` returns HTTP 200;
- session cookies are Secure, HttpOnly where applicable, SameSite=Lax, and host-only;
- schema/runtime database separation matches the approved privilege boundary;
- current migrations have run;
- the user queue service is enabled, active, and has not restarted unexpectedly;
- the cron scheduler executes and writes to the journal;
- the initial Platform Owner boundary is live and the public application retains no smoke-test session.

## Gates still open after Phase 1

Phase 1 is complete. The following later-phase or public-launch requirements remain open:

- evidence before public launch that externally managed rollback, off-server database/file backup and restore, uptime/error monitoring, and alert delivery are active and usable;
- separate development/production environments and repeatable application releases before real users make direct-production development unsafe; this is deliberately deferred during the current no-user period;
- later feature-owned business migrations, each using the completed forced-RLS/restricted-grant schema contract;
- later Phase 12 operational re-verification.
