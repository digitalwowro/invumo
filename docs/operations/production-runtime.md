# Production Runtime Baseline

Status: Operational Phase 1 foundation; public-launch operations verification remains open

Verified: 2026-08-23

This document records the production runtime that currently serves Invumo. It contains no credentials and does not replace the canonical [development tracker](../development/development-tracker.md).

## Temporary pre-launch operating model

Until public launch, while Invumo has no real users, development occurs directly in this hosted production checkout. This is an approved temporary simplification. Source control and relevant automated checks still apply, but a repeatable application deployment process is not a Phase 1 requirement.

Rollback, off-server database/file backup and restore, uptime/error monitoring, and alert delivery are provided by the owner's external infrastructure and are not implemented or configured in this repository. Before public launch, Phase 12 must verify evidence that those safeguards are active and usable. Before real users make direct-production development unsafe, Invumo must add separate development and production environments and a repeatable release process.

## Public application

- Canonical URL: `https://app.invumo.com`
- Application repository: `/home/invumo/invumo`
- Nginx document root: `/home/invumo/invumo/public`
- PHP runtime: PHP 8.5 FPM using the `invumo` pool
- Database: PostgreSQL 18
- Public health endpoint: `https://app.invumo.com/up`

Nginx, PHP-FPM, and PostgreSQL remain system-managed services. Laravel web requests execute through the unprivileged `invumo` PHP-FPM pool.

## Environment and database separation

The production `.env` is untracked, owned by `invumo`, and mode `0600`. `APP_ENV=production`, `APP_DEBUG=false`, the canonical HTTPS URL, encrypted database sessions, Secure/HttpOnly/SameSite cookies, database cache/queue drivers, and host-only cookie scope are configured. The cached Laravel configuration is also mode `0600` because it contains resolved secrets.

PostgreSQL uses two login roles:

- `invumo_schema` owns the database and is used only by controlled migrations;
- `invumo_runtime` is used by web requests and jobs.

Neither role is a superuser, may create databases or roles, or may bypass RLS. The runtime role cannot create schema objects or read/write the Laravel migration repository. Tenant-table RLS policies and restricted-role isolation tests remain part of the Phase 1 business-schema work.

The one-time [database bootstrap](../../scripts/bootstrap-production-database.sh) creates or normalizes these roles without deleting an existing database, generates independent secrets without printing them, writes them only to `.env`, runs migrations through `pgsql_schema`, revokes runtime migration-table access, and caches production configuration. It refuses to run again after its placeholders have been replaced.

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

The repeatable [service installer](../../scripts/install-production-services.sh) installs both user-owned definitions without sudo. The [runtime verifier](../../scripts/verify-production-runtime.sh) checks environment permissions, migrations, system services, queue supervision, scheduler installation, the public health endpoint, and the login redirect without revealing secrets.

## Test isolation on the hosted development server

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

Verified on 2026-08-23: the regional ZeptoMail SMTP endpoint, authenticated TLS submission, verified sender, and bounded timeout are active in cached production configuration; the test message was accepted and received; the queue worker remained active after restart; and the environment/configuration files remained mode `0600`. This proves the transport only. The verification, recovery, and invitation flows still require their Phase 1 implementation and tests.

## Verified behavior

The 2026-08-23 verification proved:

- `/up` returns HTTP 200;
- `/` returns HTTP 302 to the HTTPS login page;
- `/login` returns HTTP 200;
- session cookies are Secure, HttpOnly where applicable, SameSite=Lax, and host-only;
- schema/runtime database separation matches the approved privilege boundary;
- current migrations have run;
- the user queue service is enabled, active, and has not restarted unexpectedly;
- the cron scheduler executes and writes to the journal.

## Gates still open

This runtime baseline does **not** close the complete Phase 1 or public-launch acceptance gates. The following still require implementation or verified evidence:

- evidence before public launch that externally managed rollback, off-server database/file backup and restore, uptime/error monitoring, and alert delivery are active and usable;
- separate development/production environments and repeatable application releases before real users make direct-production development unsafe; this is deliberately deferred during the current no-user period;
- verification, recovery, and invitation mail flows, templates, queue behavior, and tests on the now-verified ZeptoMail transport;
- application/job idempotency and observability primitives;
- complete business migrations, forced RLS policies, and restricted-role isolation tests;
- later Phase 12 operational re-verification.
