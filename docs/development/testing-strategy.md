# Testing Strategy

Status: Approved quality and execution contract  
Last updated: 2026-08-29

Invumo keeps strong coverage without treating every edit as a release candidate. Verification depth follows the change's actual risk, and the complete GitHub gate runs only when a phase closes.

## Local verification

During implementation, run the smallest test set that proves the changed behavior and its affected boundaries:

- PHP behavior: the directly affected Pest file or cohesive module subset;
- React behavior: the directly affected Vitest file;
- rendered UI: the directly affected Pest Browser journey and required viewport/locale;
- schema, RLS, authorization, locking, or exact-decimal behavior: the focused integration and adversarial tests for that boundary;
- shared components or cross-cutting foundations: every known consumer test that can be affected.

Use `--stop-on-failure` during iteration. A passing focused test is not permission to omit a known affected tenant, authorization, audit, localization, responsive, or concurrency boundary.

Typical commands are:

```bash
vendor/bin/pest tests/Feature/path/ChangedBehaviorTest.php --stop-on-failure
npm run test:unit -- resources/js/path/changed-behavior.test.tsx
vendor/bin/pest tests/Browser/ChangedJourneyTest.php --stop-on-failure
```

`composer ci:static`, `composer ci:check:core`, and `composer ci:check` remain available for broad or offline verification. They are not routine per-change commands.

## Batch closeout

An approved implementation batch closes after proportionate focused verification, durable-memory updates, and commits and pushes for both repositories. Do not create a production backup or apply production migrations at batch closeout.

Pushing a batch does not run GitHub Actions. Do not wait for or poll a GitHub run after an ordinary batch commit.

## Phase closeout

After the final batch and phase acceptance review:

1. complete any remaining focused acceptance evidence;
2. push the phase-closing commits and durable memory;
3. manually dispatch `.github/workflows/tests.yml` with the phase number;
4. require the consolidated `Phase quality gate` job to pass;
5. inspect failure artifacts, fix only the demonstrated problem, and rerun the phase gate when necessary;
6. record the successful run's exact commit SHA as the immutable release input;
7. quiesce HTTP business writes and scheduler dispatch, allow current HTTP/scheduler/queue work to drain for at most 120 seconds without a force-kill, and stop the worker only after it is idle;
8. create and verify one fresh production SQL backup under `/home/invumo/backups`;
9. stage the exact verified revision, rebuild its production caches, apply all pending phase migrations once, and restart the application/worker against that same revision;
10. run smoke checks against the restarted application and freshly rebuilt production configuration; and
11. resume traffic, scheduling, and queue processing only after every check passes.

A failed gate or backup blocks production migration. A drain timeout or inability to prove that work settled leaves the application quiesced for manual investigation; do not force-kill an in-flight provider job. If migration, restart, cache rebuild, or smoke verification fails after migration begins, remain quiesced. Do not automatically restore the backup, activate the previous revision against the changed schema, or resume traffic. Forward repair, a proven schema-compatible code reversion, and database restoration are separate explicit authorization paths.

The full local gate is not duplicated before the GitHub phase gate unless GitHub is unavailable or the change affects CI/test infrastructure itself and local reproduction is necessary.

## Full-gate topology

The manually dispatched workflow runs independent jobs concurrently:

- one static/frontend job for generation, formatting, design and structure guards, strict types, Vitest, production build, Pint, and PHPStan;
- four Pest Unit/Feature shards, each with its own PostgreSQL 18 service and restricted test roles;
- two Pest Browser shards, each with its own PostgreSQL 18 service and Chromium runtime;
- one consolidated terminal job that passes only when every parallel job succeeds.

Each shard is isolated at the runner and database level. Do not parallelize test processes against one shared PostgreSQL database: schema-refreshing, privilege, concurrency, and forced-RLS tests require isolation. Every substantive job has a bounded timeout, and browser comparison artifacts are retained on failure.

Shard membership may change as files are added or renamed. Isolation comes from each shard's independent runner and database, not from a pinned file assignment. Give a test a dedicated job only when its demonstrated isolation requirement exceeds that boundary.

## Performance work

The current PHP cost is dominated by migration-backed tests; 85 test files deliberately use `DatabaseMigrations` because committed cross-connection behavior, deferred constraints, RLS roles, and schema tests cannot be assumed safe under one shared transaction. External sharding reduces phase-gate wall time without weakening that isolation.

Any later switch to migrate-once/truncate or transaction-based isolation requires profiling plus explicit proof for cross-connection visibility, commit-time triggers, role/privilege tests, concurrency tests, and schema-mutating tests. It must not be introduced merely to improve a benchmark.

## Evidence

Tracker evidence names the focused tests used for each task or batch. Only a phase acceptance gate records the full manually dispatched GitHub run. The first run of the reworked workflow may expose workflow-infrastructure defects; those defects are fixed and the gate rerun before any production migration. Historical full-gate evidence remains valid; this policy changes future execution frequency, not prior verification.
