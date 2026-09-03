# Testing Strategy

Status: Approved quality and execution contract  
Last updated: 2026-08-29

Invumo keeps strong coverage without treating every edit as a release candidate. Verification depth follows the change's actual risk, and the complete GitHub gate runs only when a phase closes.

## Local verification

During implementation, run the smallest test set that proves the changed behavior and its affected boundaries:

- PHP behavior: the directly affected Pest file or cohesive module subset;
- React behavior: the directly affected Vitest file;
- rendered UI: the directly affected Pest Browser journey and required viewport/locale, always launched through the supervised Composer wrapper;
- schema, RLS, authorization, locking, or exact-decimal behavior: the focused integration and adversarial tests for that boundary;
- shared components or cross-cutting foundations: every known consumer test that can be affected.

Use `--stop-on-failure` during iteration. A passing focused test is not permission to omit a known affected tenant, authorization, audit, localization, responsive, or concurrency boundary.

Manual reference comparisons use the single canonical [`design-qa.md`](design-qa.md) log. Every cited source and rendered implementation artifact must be committed under `design-qa-evidence/`, registered with its SHA-256 and review metadata in `tests/Browser/design-qa-reviews.json`, and pass `npm run design-qa:check`. Machine-local attachment and ignored browser-output paths are not durable evidence. This reference-comparison record is separate from, and never substitutes for, pinned-runner canonical visual snapshot review.

## Cross-phase invariant regression review

Every feature batch has a named seam-review responsibility in addition to its local tests:

1. For every new foreign key, reference, or reachable state, find earlier cleanup, deletion, archive, eligibility, fallback, and resolution code that enumerates references or depends on that state being absent.
2. For every new controller, command, job, retry, scheduled path, or other entry point to an existing operation, enumerate the existing entry points and verify that the authoritative guard is shared or exercised consistently by each path.
3. Update the owning invariant boundary and add focused behavior/integration tests in the same batch. If the affected behavior belongs to a completed phase, reopen that owning task for the correction rather than deferring it to Phase 11 or 12.

At phase closeout, compare the complete phase diff with the prior successful phase-gate SHA. Review schema references, state/constraint additions, and new operation entry points against their earlier consumers. Record the impacted boundaries and focused evidence, or an explicit finding that none were introduced, in the tracker before dispatching the manual phase gate. The full suite confirms tests; it does not replace this semantic seam review.

For recurring automation, the review specifically follows generation into ordinary Invoice issue/reminder/deletion behavior and automatic email into ordinary public-link, composition, sender, quota, retry, provider-attempt, and completion behavior. The same eligibility Query must drive retry presentation and the locked pre-provider decision; a passing generation test alone is insufficient.

Typical commands are:

```bash
vendor/bin/pest tests/Feature/path/ChangedBehaviorTest.php --stop-on-failure
npm run test:unit -- resources/js/path/changed-behavior.test.tsx
composer test:browser -- tests/Browser/ChangedJourneyTest.php --stop-on-failure
```

The browser wrapper closes standard input so Pest cannot wait invisibly for an interactive failure prompt, prints a heartbeat every 15 seconds, applies PHPUnit's 120-second default limit to each Browser test, applies a ten-minute hard suite timeout, and terminates the complete Pest/Playwright process group after success, failure, timeout, or interruption. A missing or stale locator therefore fails the active test instead of consuming the whole suite timeout. `BROWSER_TEST_CASE_TIMEOUT_SECONDS` may raise the per-test limit only for a reviewed journey with a demonstrated longer bound. Interactive `--debug` runs are excluded from automated verification and require an explicit `BROWSER_TEST_INTERACTIVE=true` headed-terminal session.

`composer ci:static`, `composer ci:check:core`, and `composer ci:check` remain available for broad or offline verification. They are not routine per-change commands.

## Batch closeout

An approved implementation batch closes after proportionate focused verification, durable-memory updates, and commits and pushes for both repositories. Do not create a production backup or apply production migrations at batch closeout.

Pushing a batch does not run GitHub Actions. Do not wait for or poll a GitHub run after an ordinary batch commit.

## Phase closeout

After the final batch and phase acceptance review:

1. complete any remaining focused acceptance evidence and the cross-phase invariant regression review;
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

Tracker evidence names the focused tests and cross-phase seam review used for each task or batch. Only a phase acceptance gate records the full manually dispatched GitHub run. The first run of the reworked workflow may expose workflow-infrastructure defects; those defects are fixed and the gate rerun before any production migration. Historical full-gate evidence remains valid; this policy changes future execution frequency, not prior verification.
