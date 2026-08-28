# Production Runtime Baseline

Status: Phase 1 operational baseline complete; public-launch operations verification remains open

Verified: 2026-08-27

This document records the production runtime that currently serves Invumo. It contains no credentials and does not replace the canonical [development tracker](../development/development-tracker.md).

## Temporary pre-launch operating model

Until public launch, while Invumo has no external users or customer business data, development occurs directly in this hosted production checkout. This is an approved temporary simplification. Source control and relevant automated checks still apply, but a repeatable application deployment process is not a Phase 1 requirement.

Rollback, off-server database/file backup and restore, uptime/error monitoring, and alert delivery are provided by the owner's external infrastructure and are not implemented or configured in this repository. Before public launch, Phase 12 must verify evidence that those safeguards are active and usable. Before real users make direct-production development unsafe, Invumo must add separate development and production environments and a repeatable release process.

Every production migration also requires a fresh local logical SQL backup immediately before migration. Run `php scripts/backup-production-database.php`; it reads the private Laravel schema connection without printing credentials and holds one exported repeatable-read PostgreSQL snapshot across the complete dump. Because the schema role cannot bypass forced RLS, the command exports global data once and each discovered forced-RLS tenant table once per Company under explicit tenant context, then verifies the emitted row count for every such table. It writes an atomically finalized `0600` SQL file beneath the `0700` `/home/invumo/backups` directory, verifies a non-empty PostgreSQL dump header, and reports its SHA-256 checksum. The directory is outside every Invumo repository and its contents must never be copied into Git, application storage, logs, or build artifacts. Abort the migration if backup creation or verification fails. Disposable isolated `_test` migrations do not require this production backup.

`POSTGRESQL_CLIENT_BIN_DIRECTORY` identifies the host-specific directory containing both `pg_dump` and `psql`; `POSTGRESQL_CLIENT_MAJOR_VERSION` pins the required major version. The Debian/Ubuntu production baseline defaults to `/usr/lib/postgresql/18/bin` and major version `18`, but other host layouts must override the directory rather than changing application code. Production readiness and backup startup execute both configured binaries with `--version` and fail closed unless they are executable PostgreSQL clients from the configured major release. CI consumes the same two environment-backed Laravel configuration values.

The generic backup core is covered against the isolated PostgreSQL test database. Automated checks prove one exported snapshot reaches every dump segment, Company creation during backup cannot enter only part of the recovery point, forced-RLS data is exported per Company and row-count checked, expired snapshots and mismatched counts remove partial output, modes/finalization are restrictive, and the resulting SQL restores global and tenant rows into a freshly rebuilt `_test` schema. The restore harness refuses any database name outside the `_test` boundary.

## Public application

- Canonical URL: `https://app.invumo.com`
- Application repository: [`digitalwowro/invumo`](https://github.com/digitalwowro/invumo)
- Application checkout: `/home/invumo/invumo`
- Nginx document root: `/home/invumo/invumo/public`
- PHP runtime: PHP 8.5 FPM using the `invumo` pool
- Database: PostgreSQL 18
- Public health endpoint: `https://app.invumo.com/up`

Nginx, PHP-FPM, and PostgreSQL remain system-managed services. Laravel web requests execute through the unprivileged `invumo` PHP-FPM pool.

Phase 8 public Quote/Invoice routes carry bearer credentials in their path. Before the public-link schema is migrated or any credential is issued, the Nginx access-log format must replace the token segment on `/q/{token}` and `/i/{token}` requests with a fixed redacted marker, including Quote `/decision` and both `/pdf` variants. Validate the configuration, reload Nginx, send a request containing a synthetic token, and prove that token is absent from the resulting access log. This production configuration change remains a separate explicit authorization boundary from an approved application batch.

Public-link ciphertext supports Laravel's current application key plus `APP_PREVIOUS_KEYS`. Rotate keys in this order: install the new current key with the prior key in `APP_PREVIOUS_KEYS`, rebuild the private configuration cache and restart application workers, run `php artisan public-document-links:reencrypt`, verify every retained ciphertext decrypts under the new current key, and only then remove the retired key in a later controlled configuration change. The command iterates Companies through restricted tenant context and locks documents and link rows in stable UUID order; it never prints a token, hash, ciphertext, or URL.

Verified on 2026-08-27 for Batch 8B: the Invumo server uses its own redacted access-log format, overriding the inherited full-request format. A synthetic Quote PDF request with a token-bearing Invoice referrer logged only `/q/[redacted]/pdf` and `[redacted-public-document-url]`; searching the access log for either probe token returned no result. Backup `invumo-20260827T172433Z-pre-migration.sql` was finalized outside Git at 280,099 bytes with SHA-256 `755934c5f4b9c84d56de8b4b0ddcabe160c2e48dfd23bb00924909a7018eafd1` before migration `2026_08_27_040000_create_public_document_links` ran as production batch 17. The table is forced-RLS with its ordinary Company policy and exact-hash bootstrap policy; default/unknown-hash runtime visibility is zero. The existing Company has the enabled/30-day future-document defaults, its two existing documents remain disabled, and no link credential was generated during rollout. Unknown valid-shaped tokens return the same no-store, no-referrer, noindex, framed-denied 404 through both the origin and public host. Production readiness passes and every migration is current.

Verified on 2026-08-27 for Batch 8C: backup `invumo-20260827T185313Z-pre-migration.sql` was finalized outside Git at 289,504 bytes, mode `0600`, with SHA-256 `dbd6f1fc52c164507fa76ad3c3b19948efc3aceff530fb1fd2c23212daf17c77` before migration `2026_08_27_050000_create_quote_public_decisions` ran as production batch 18. `quote_public_decisions` has forced RLS, one Company policy, 16 validated constraints, its immutable-update trigger, and runtime SELECT/INSERT/UPDATE grants; UPDATE exists only to support stable row locking and every actual update is rejected by the trigger. Both production connections see zero decision rows without Company context. All migrations are current and the production runtime verifier passes.

Verified on 2026-08-27 for Batch 9B and the public-decision erasure correction: backup `invumo-20260827T205354Z-pre-migration.sql` was finalized outside Git at 311,942 bytes, mode `0600`, with SHA-256 `245d205693e24840269e0f740d66e2eac8e3767fa487a5d0d5af760887d6831f` before migrations `2026_08_27_060000_add_customer_erasure_to_quote_public_decisions` and `2026_08_27_061000_create_company_email_templates` ran together as production batch 19. Both tenant tables have forced RLS, one Company policy, 17 validated constraints, and zero default-context visibility through either production connection. Public decisions retain the narrow INSERT/SELECT/UPDATE grant set and one-way identity-redaction trigger; Company email templates have the expected CRUD grants. The one Company has zero public decisions and zero template overrides, so no live identity required backfill and Laravel-authored system defaults remain authoritative. All migrations are current and the production runtime verifier passes. No provider call or email was sent.

Verified on 2026-08-28 for Batch 9C: production migration status already recorded `2026_08_28_010000_create_direct_document_deliveries` in batch 20 before the closeout migration command, so that command correctly reported nothing pending. Backup `invumo-20260828T062745Z-pre-migration.sql` was finalized outside Git at 348,809 bytes, mode `0600`, with SHA-256 `36750fd76d29a7f0a3e67cbdb5e586cba4195837ce2bb1e4184e65b3f607fb58`; because the schema was already present, this is a post-schema backup, while `invumo-20260827T205354Z-pre-migration.sql` remains the latest recovery point predating the delivery schema. The four delivery tables have forced RLS, one Company policy each, validated constraints, their expected restricted runtime grants, and zero visibility without tenant context through either production connection. All contain zero rows. Production configuration pins the EU Send API endpoint, accepts only a bare Send API key, and resolves the verified sender address without disclosing credentials. The owner-authorized live adapter test was accepted by ZeptoMail. The queue worker restarted cleanly with zero restarts, `/up` returned HTTP 200, all migrations are current, and the production runtime verifier passes.

Verified on 2026-08-28 for Batch 9D: the first migration attempt after private backup `invumo-20260828T162656Z-pre-migration.sql` detected that the already-applied Batch 9C attempt table predated its redaction column and rolled back atomically; the provider-event table and delivery milestone columns remained absent. The forward repair was then covered by the complete quality gate. Fresh backup `invumo-20260828T164028Z-pre-migration.sql` was finalized outside Git at 346,632 bytes, mode `0600`, with SHA-256 `169d264b3fb1282254711019a8fa3c7eb7cab55d2e2ff47a10155efd6c888b8e` before migrations `2026_08_28_015000_align_email_delivery_attempt_redaction` and `2026_08_28_020000_create_email_provider_events` ran as batch 21. The attempt redaction column and nullable client reference now match the canonical schema. `email_provider_events` has forced RLS, one Company policy, 13 validated constraints, one redaction-only trigger, and runtime INSERT/SELECT/UPDATE only; its exact-reference attempt bootstrap and the delivery milestone guard are installed. Both production connections see zero provider events without Company context, and tenant totals remain zero deliveries, attempts, and events. All migrations, production configuration, `/up`, and the runtime verifier pass. The configured static webhook secret returns HTTP 200 on an authenticated empty probe and no authentication returns 401 without exposing the value.

## Environment and database separation

The production `.env` is untracked, owned by `invumo`, and mode `0600`. `APP_ENV=production`, `APP_DEBUG=false`, the canonical HTTPS URL, encrypted database sessions, Secure/HttpOnly/SameSite cookies, database cache/queue drivers, and host-only cookie scope are configured. `DB_QUEUE_CONNECTION` and `DB_TENANT_JOB_LOCK_CONNECTION` resolve to the restricted `pgsql` runtime connection, and `DB_QUEUE_RETRY_AFTER=120` remains greater than the worker's 90-second timeout. These are atomicity and duplicate-execution safeguards, not tuning suggestions. The cached Laravel configuration is also mode `0600` because it contains resolved secrets.

PostgreSQL uses two login roles:

- `invumo_schema` owns the database and is used only by controlled migrations;
- `invumo_runtime` is used by web requests and jobs.

Neither role is a superuser, may create databases or roles, or may bypass RLS. The runtime role cannot create schema objects or read/write the Laravel migration repository. The Phase 1 business-schema foundation now enforces the reusable forced-RLS/restricted-grant contract and isolated PostgreSQL tests; each later feature migration must apply it to its own tenant tables.

Production-like migrations now require `invumo_runtime` before executing any conditional grant path. A missing role aborts with a named configuration error instead of allowing a successful but incomplete migration. Local and isolated testing may omit split roles deliberately; when the role exists, the same grants and denials are applied and verified.

The one-time [database bootstrap](../../scripts/bootstrap-production-database.sh) creates or normalizes these roles without deleting an existing database, generates independent secrets without printing them, writes them only to `.env`, runs migrations through `pgsql_schema`, revokes runtime migration-table access, and caches production configuration. It refuses to run again after its placeholders have been replaced.

Verified on 2026-08-25: all current migrations through `2026_08_25_210000_create_customers` are applied. During the Batch 3A closeout, a destructive migration command combined a `testing` CLI environment override with cached production database targets and rebuilt the pre-launch schema. The owner explicitly chose account re-bootstrap instead of external-backup restoration because no external users or customer business data existed. Two verified personal Accounts were recreated from the owner-supplied credentials: one protected Platform Owner and one ordinary User. After the app returned to service, the ordinary User recreated one valid Company through the application. Batch closeout observed no Customer rows. Credential, role, migration, forced-RLS, configuration, queue, and runtime-health checks passed before normal work resumed.

Verified on 2026-08-26: backup `invumo-20260826T064023Z-pre-migration.sql` was finalized outside Git at 146,362 bytes with SHA-256 `cb740672d6595fa07ec58c6fa5ce70c946e17ecbee1397a60c0a6ae7ffc1c36f` before migration `2026_08_26_020000_guard_product_prices_on_currency_precision_change` ran as production batch 5. All migrations are current, zero Product/Service rows violate their configured currency precision, and the deferred currency trigger watches both activation and precision changes. A second post-migration verification run exercised the final tenant-aware backup implementation successfully; it is evidence of the command, not the pre-migration recovery point for this migration.

Verified on 2026-08-26: backup `invumo-20260826T082916Z-pre-migration.sql` was finalized outside Git at 147,899 bytes with SHA-256 `c77f847a082971a6c0c17766dab7d66f9efa179b64fe42b8532ff906921d5dc7` before the Batch 5B Quote aggregate migrations ran as production batch 6. `number_counters`, `documents`, `quotes`, `document_lines`, and `document_number_events` each have forced RLS and one tenant policy; expected runtime grants, three key uniqueness constraints, and four subtype/calculation integrity triggers are present. The one existing Company has zero rows in the new tables, all migrations are current, and the production runtime verifier passes.

Verified on 2026-08-26: both environment-configured PostgreSQL client binaries report the required major version before readiness and backup work. Backup `invumo-20260826T110701Z-pre-migration.sql` was finalized outside Git at 191,003 bytes with SHA-256 `96ef59d3c51e082f134730af41a574827397f2c3c0e00cceb6680b07f12a4e45` before Batch 5C migration `2026_08_26_032000_create_document_snapshots` ran as production batch 7. All six document snapshot/default/delivery tables have forced RLS, one tenant policy, validated constraints, expected runtime CRUD grants, and default-deny zero visibility without Company context. All migrations are current and the production runtime verifier passes.

Verified on 2026-08-26: backup `invumo-20260826T122104Z-pre-migration.sql` was finalized outside Git at 229,141 bytes with SHA-256 `b07ee69e68379add2279717f1473410e869199184caf446dd0d6eca73bf44e61` before Batch 5D migration `2026_08_26_040000_complete_quote_operational_fields` ran as production batch 8. Immediate verification found that forced RLS had intentionally hidden Company settings from the migration's cross-tenant backfill, leaving both existing Quotes with null validity while all schema checks, triggers, indexes, and runtime grants were healthy. The corrected per-Company backfill passed a two-tenant forced-RLS regression, then backup `invumo-20260826T123112Z-pre-migration.sql` was finalized at 235,171 bytes with SHA-256 `3b5bd06e107d06560d0e1f006c498c4bbc2631084850c7228e85d50955d7e5b9` before migration `2026_08_26_041000_backfill_quote_validity_with_tenant_context` ran as batch 9. Both existing Quotes now have Company-default validity, with zero missing/mismatched validity rows and zero null generated sort values. All migrations, safe cached configuration, services, queue/scheduler, and public runtime health pass.

Migration-history clarification: `2026_08_26_040000_complete_quote_operational_fields` now contains only the Quote operational schema change; its inert cross-tenant `UPDATE` was removed so it cannot be mistaken for the authoritative data migration during a rebuild or squash. `2026_08_26_041000_backfill_quote_validity_with_tenant_context` is the sole validity backfill and enters one transaction-local forced-RLS context per Company in stable UUID order. Its `down()` is intentionally empty and the migration is intentionally irreversible because later legitimate Quote edits make the originally backfilled values indistinguishable from user-authored values; rollback of the later schema migration removes the columns when an isolated full rollback is required.

The Phase 5 renderer runtime is Dompdf 3.1.6 behind Invumo's internal contract. It reads the dedicated Blade/CSS template and pinned source-owned Atkinson Hyperlegible Next/Mono files from the repository, performs no remote resource fetch, and keeps renderer cache/temp data beneath private application storage. Its local-file chroot contains only `resources/fonts/atkinson-hyperlegible` and `storage/framework/cache/dompdf`; the application root, environment, logs, database files, private uploads, source tree, and other storage are not readable through Dompdf local URLs. Current authenticated PDF downloads are generated read-only and are not retained as delivery artifacts.

Verified on 2026-08-26: backup `invumo-20260826T132944Z-pre-migration.sql` was finalized outside Git at 222,619 bytes with mode `0600` and SHA-256 `c01c3be554764f9c6e8d22153c9c2e9a0eef71e25d8cb66242ff0fe1dc2fe79b` before Batch 5E migration `2026_08_26_050000_snapshot_document_currency_display_style` ran as production batch 10. The one existing document Company snapshot received a valid display style; zero invalid values remain. The column is non-null, its `CODE`/`SYMBOL` constraint is validated, the table retains forced RLS, and both schema/runtime connections see zero rows without tenant context. All migrations are current and the production runtime verifier passes.

Verified on 2026-08-26: backup `invumo-20260826T143918Z-pre-migration.sql` was finalized outside Git at 231,075 bytes with mode `0600` and SHA-256 `2f4dda4a929898e2ff58f7faab14949f5eae18378ea7e4f326b16b7f85a7897c` before Batch 6A migration `2026_08_26_060000_create_invoice_draft_aggregate` ran as production batch 11. The `invoices` table has forced RLS, one tenant policy, 14 validated constraints, two custom integrity triggers, and the expected runtime CRUD grants. The single production Company has zero Invoice subtype/base rows, and the runtime role sees zero Invoice rows without tenant context. All migrations are current and the production runtime verifier passes.

Verified on 2026-08-26: backup `invumo-20260826T180230Z-pre-migration.sql` was finalized outside Git at 238,892 bytes with mode `0600` and SHA-256 `afa1c6a559acaf654794b97e8c1e7b24560b5f6e23ee9cf83bfa39d70e7eaa2d` before Batch 6B migration `2026_08_26_061000_enable_invoice_issued_lifecycle` ran as production batch 12. The validated lifecycle constraint permits only Draft/Issued, and five deferred triggers run the restricted issuability function across subtype/base/line/required-snapshot writes. The runtime role may execute that function, sees zero Invoice rows without Company context, and the single production Company has zero Invoice rows. All migrations are current, production configuration is safe, and the runtime verifier passes.

Verified on 2026-08-26: backup `invumo-20260826T202144Z-pre-migration.sql` was finalized outside Git at 246,169 bytes with mode `0600` and SHA-256 `966c7232f107169691ff2a771001105d0b678f69e978977815e93752eef13959` before Batch 6C migration `2026_08_26_062000_create_quote_invoice_provenance` ran as production batch 13. `quote_invoice_links` has forced RLS, one tenant policy, 14 validated constraints, expected runtime CRUD grants, and zero visibility through either production connection without Company context. The one existing Quote received the correct Customer-or-Company Invoice payment-term resolution and zero backfill mismatches remain; no provenance links existed at rollout. All migrations are current, production configuration is safe, and the runtime verifier passes.

Verified on 2026-08-27: backup `invumo-20260826T222018Z-pre-migration.sql` was finalized outside Git at 260,426 bytes with mode `0600` and SHA-256 `2112445826f9eda278befea545a383feffa14b5d1cce3a847f785fdf346b8f1e` before the logo-retention correction and Batch 7A transaction migrations ran as production batch 14. `invoice_transactions` has forced RLS, one tenant policy, 28 validated constraints, expected runtime CRUD grants, and zero visibility through either production connection without Company context. All three ledger triggers and all three logo-reference triggers are installed. The one production Company has zero transaction rows and zero deleted assets referenced by a live Company setting or retained document snapshot. All migrations are current, production configuration is safe, and the runtime verifier passes.

Verified on 2026-08-27: backup `invumo-20260827T063018Z-pre-migration.sql` was finalized outside Git at 280,176 bytes with mode `0600` and SHA-256 `7e86909cb8193da4c34441904550fa43d3b4172b3c6cad5188e8b1b87812c0d3` before Batch 7B migration `2026_08_27_020000_enable_invoice_cancellation` ran as production batch 15. The validated lifecycle constraint permits Draft/Issued/Cancelled; the three retained ledger triggers require zero net paid for cancellation, freeze financial rows while Cancelled, and allow reopening only to Issued. The runtime role retains execute privilege on the ledger function. The one production Company has zero Invoices and transactions, zero invalid Cancelled balances, and zero runtime Invoice visibility without Company context. All migrations are current, production configuration is safe, and the runtime verifier passes.

Verified on 2026-08-27 after Batch 7C: no schema migration was added because the existing restrictive `invoice_transactions` foreign key, current document cascades, and retained audit/number-event tables already implement the required database boundary. Production migration status remains current through batch 15 and the production runtime verifier passes. No production migration ran, so no pre-migration backup was required or created for this batch.

Verified on 2026-08-27: backup `invumo-20260827T094817Z-pre-migration.sql` was finalized outside Git at 276,473 bytes with mode `0600` and SHA-256 `31cbfef90a2e973bd3c4924b55d3060928c4ca14ed1032027e258d9cb032efd5` before Batch 7D migration `2026_08_27_030000_index_company_transaction_list` ran as production batch 16. The Company-wide `(company_id, transaction_date, id)` cursor index is present. The one production Company has zero transaction rows, the runtime role sees zero transaction rows without tenant context, every migration is current, and the production runtime verifier passes.

Destructive Artisan database commands now fail closed unless Laravel is in the `testing` environment and both `pgsql` and `pgsql_schema` target database names end in `_test`. This remains enforced even when a caller supplies a CLI environment override, so cached production connection targets cannot become eligible for `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, or `db:wipe`.

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

Automated tests must never use the live `invumo` database. Both Laravel PostgreSQL connections are forced to `invumo_test` by `phpunit.xml`, and the base Laravel test case aborts before database-reset traits run unless the application environment is `testing` and every PostgreSQL connection name ends in `_test`. Independently, Laravel prohibits every destructive database command unless that same environment-and-database-name contract is satisfied. These guards also block direct test or destructive Artisan invocations while production configuration remains cached, including invocations that supply `--env=testing`.

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

Direct Quote/Invoice delivery uses the separate ZeptoMail EU HTTPS Send API boundary. Production requires `ZEPTOMAIL_SEND_API_TOKEN`, pins `ZEPTOMAIL_API_ENDPOINT` to `https://api.zeptomail.eu/v1.1/email`, and bounds connect/total timeouts with `ZEPTOMAIL_API_CONNECT_TIMEOUT` and `ZEPTOMAIL_API_TIMEOUT`. Store only the bare Send API key in `ZEPTOMAIL_SEND_API_TOKEN`; do not include the `Zoho-enczapikey` authorization scheme. The token must be generated inside the intended ZeptoMail Agent and therefore already scopes outbound requests to that Agent; the Agent Alias remains provider-side setup metadata and is not sent or persisted by Invumo. `MAIL_FROM_ADDRESS` is the exact provider-verified Domain/Sender Address used in the Send API `from.address` field. The token stays only in the private environment/configuration cache and must not be copied into documentation, logs, audit, queue metadata, or persisted delivery history. `php artisan invumo:production-configuration --no-interaction` verifies the configured boundary without disclosing its value.

The inbound endpoint is `https://app.invumo.com/webhooks/zeptomail`. In the same ZeptoMail Agent, enter that URL, set the Authorization header key to exactly `X-Invumo-Webhook-Key`, and set its value to a random 32–512 character secret stored identically as `ZEPTOMAIL_WEBHOOK_SECRET`. Select Delivered, Soft bounce, Hard bounce, Feedback loop, and any separately offered Open/Click notifications. The secret is distinct from the Send API token. The endpoint accepts authenticated empty reachability probes and otherwise requires the strict form-encoded ZeptoMail event body. The live Agent UI no longer exposes the Authentication Key required by ZeptoMail's published producer-signature instructions, so Invumo uses the provider-supported static header over HTTPS plus rate limiting and provider-event idempotency; it does not claim a signed request timestamp. Never paste the key or a webhook body into logs, documentation, audit, or a task.

Shared-sender abuse controls are environment-backed through `DOCUMENT_DELIVERY_MAX_RECIPIENTS`, the Company/Account hourly and daily recipient limits, and the platform hourly/daily recipient limits shown in `.env.example`. Defaults are deliberately conservative. Production readiness permits lower values but rejects zero, negative, or values above the reviewed safety ceilings. All initial and retry provider submissions consume the same weighted budgets; changing these ceilings requires a deliverability-capacity review rather than only an application restart.

Immutable email PDF artifacts use `DOCUMENT_ARTIFACTS_DISK`, defaulting to the private, non-served `document_artifacts_local` disk rooted at `storage/app/document-artifacts`. The application process and queue worker require write access. The directory must be included in the externally managed off-server file backup and restore scope before direct attachment delivery is enabled. The application limits raw PDF artifacts to 11 MiB so base64 and message overhead remain beneath the provider's 15 MB total-message limit.

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
