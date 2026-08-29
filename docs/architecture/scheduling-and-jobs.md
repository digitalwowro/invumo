# Scheduling, Recurrence, Reminders, and Downtime

Status: Approved architecture decision  
Last updated: 2026-08-29

This specification defines how company-local recurring invoices and reminders map to UTC execution, survive daylight-saving transitions and service downtime, and remain idempotent under retries or overlapping workers.

## Runtime shape

- Laravel's scheduler is invoked once per minute by cron.
- Due work is materialized in PostgreSQL and claimed transactionally.
- Laravel's PostgreSQL-backed database queue carries retryable jobs.
- One linger-backed `systemd --user` PHP queue worker owned by the unprivileged `invumo` account processes jobs initially.
- Redis and an external message broker are not required.

The scheduler dispatches work; it does not perform slow PDF or provider operations inside the scheduler lock.

## Shared tenant-job execution contract

Phase 1 provides the worker-side contract reused by later scheduling features:

- Every tenant business job carries a validated Company ID, stable machine idempotency key, and non-sensitive component label.
- Payloads are encrypted and duplicate dispatch is suppressed for up to seven days, or until earlier processing release, through a dedicated database uniqueness lock on the same `pgsql` connection as the queue and business mutation.
- Queue-row and lock insertion may occur inside the root business transaction when the work is already known; commit exposes the mutation and job together, while rollback removes both. Provider/network work never runs inside that transaction.
- One initial attempt and five retries use delays of 1 minute, 5 minutes, 15 minutes, 1 hour, and 6 hours. The queue visibility timeout is 120 seconds, above the worker timeout of 90 seconds.
- Jobs begin and end without tenant state, may enter only their declared Company's short transaction-local RLS context, and fail closed if they attempt to switch Company or leak a transaction/context into the next worker execution.
- Operational logs contain correlation ID, component, attempt counts, duration, outcome, and a bounded machine failure category only. They exclude Company/target/idempotency identifiers, payloads, recipient addresses, tokens, exception text, and provider content.
- Queue uniqueness prevents duplicate pending dispatch. Every business effect still needs its own delivery-time validity recheck and, where applicable, a database uniqueness/idempotency constraint. A provider acceptance followed by a worker crash is not made exactly-once by the queue; provider idempotency and event reconciliation remain part of each later integration design where supported.

Company-invitation delivery is the initial proof workflow: its encrypted job is queued atomically with invitation creation/resend, reloads and validates the token under forced RLS, closes the database transaction, and then submits mail. A rotated, accepted, revoked, or expired invitation is deliberately skipped.

Batch 9E owns the first `job_dispatches` implementation. The table contains only Company ID, opaque target ID, job type, due time, idempotency key, and claim state. A dedicated `NOLOGIN`, `NOBYPASSRLS` dispatcher role receives schema usage, `SELECT` on that table, and column-level `UPDATE` only for claim state and timestamps. Its membership in `invumo_runtime` has no admin or inheritance option and permits explicit `SET ROLE` only, so ordinary web/job queries keep Company-scoped RLS while the scheduler can claim cross-Company payload-free rows without gaining authority to rewrite dispatch identity or tenant ownership.

## Canonical time model

Each company stores:

- An IANA timezone
- One automation-local-time setting, default `09:00`

v1 uses this company time for recurring generation and reminder delivery. Per-template execution-time overrides are deferred.

The canonical schedule is a company-local calendar rule, not a chain of UTC durations. For every occurrence:

1. Calculate the next local calendar date from the recurrence or reminder rule.
2. Combine it with the company's automation-local time.
3. Resolve that local value through the company's current IANA timezone.
4. Store the resolved UTC timestamp as the queryable `next_run_at` or `scheduled_for_at` value.

Never calculate monthly, quarterly, or yearly recurrence by adding a fixed number of seconds to the prior UTC timestamp.

Occurrence/reminder records retain the local date, local time, timezone identifier, resolved UTC timestamp, idempotency key, status, attempts, outcome, and related generated invoice/email where applicable. Past records do not change when timezone rules or company settings change.

## Recurrence calendar rules

- The start date is the first eligible occurrence date.
- Weekly recurrence uses the start date's weekday.
- Monthly recurrence uses the start date's day of month.
- Quarterly recurrence advances three calendar months from the anchored start date.
- Yearly recurrence uses the anchored start month and day.
- Custom recurrence is a positive integer interval in days, weeks, months, or years.
- When an anchored day does not exist in a target month, use that month's last calendar day without changing the anchor. For example, a January 31 monthly schedule runs on February 28/29 and then March 31.
- A February 29 yearly schedule runs on February's last day in non-leap years and returns to February 29 in leap years.
- The optional end date is inclusive in the company-local calendar.
- Maximum occurrence count counts successfully created occurrence/invoice records; retries of the same occurrence do not increase it.

Do not derive a later monthly/yearly date from a previously clamped date; always calculate from the original anchor plus the logical occurrence ordinal.

## Daylight-saving behavior

- If the selected local time does not exist during a forward transition, shift it forward by the size of the DST gap.
- If the selected local time occurs twice during a backward transition, select the first occurrence.
- A stable occurrence key and database uniqueness constraint ensure that an ambiguous local time executes once.
- Calculate the following occurrence again from its local calendar rule so a one-time DST adjustment does not create permanent drift.

Changing a company's timezone or automation time requires confirmation, is audited, and recalculates future pending executions only. Completed, sent, skipped, superseded, or failed historical records retain their original scheduling context.

## Claiming due work

A minimal scheduling-dispatch record contains company ID, opaque target ID, job type, due UTC timestamp, idempotency key, and processing status; it contains no customer or financial payload.

The scheduler claims at most 50 due dispatch records in stable due-time/UUID order using one transaction and `FOR UPDATE SKIP LOCKED`, inserts the corresponding database-queue jobs, marks the dispatches queued with a claim identity, and commits those changes together before workers perform slow work. A two-minute scheduler-overlap lease reduces redundant local invocations without making correctness depend on the lease; stale locks clear quickly and database claiming remains safe across hosts. The restricted role is reset before queue insertion. Each queued job uses the shared tenant-job contract and enters only its declared Company's short tenant RLS context before reading business data. A scheduler rollback removes both the claim and queue insertion; a completed or terminally failed worker closes the claim state.

Business effects use database uniqueness constraints in addition to queue uniqueness:

- Recurring occurrences are unique by `(template_id, occurrence_key)`.
- Reminder instances are unique by their invoice/rule occurrence identity.
- Email attempts use a stable delivery idempotency key.

Create the occurrence and its generated invoice in one transaction. Dispatch PDF/email jobs only after commit. A retry finds and continues the existing occurrence/invoice instead of creating a replacement.

## Recurring invoice calculation

The recurrence rule stores a start date, interval, optional end date, optional maximum count, a successful-occurrence count, and an occurrence cursor. The count advances only when the occurrence and Invoice commit, is not derived from retained occurrence-row count, and is never decremented by later Invoice deletion. Each occurrence key is stable and derived from the template plus its logical occurrence identity, not from the queue job ID.

For an eligible occurrence:

1. Lock the Company configuration, template, and stable payload-free dispatch identity in the approved order.
2. Recheck that the template is Active, the dispatch matches its current logical ordinal, and the occurrence remains within its end/count limits.
3. Resolve every inherited Customer field from the current Customer, then Company fallback, while retaining explicit template/line overrides.
4. Create exactly one invoice using the normal invoice-number allocator and snapshot those resolved values.
5. Use the scheduled company-local occurrence date as the invoice issue date.
6. Derive the due date from that issue date and the resolved current or explicitly overridden payment terms.
7. Recalculate lines using the resolved currency precision without FX conversion, then issue the invoice and materialize its reminder schedule.
8. If automatic email is enabled and inherited currency differs from the template's last-confirmed delivery currency, set the currency-review latch and suppress automatic delivery. While latched, every later occurrence remains issue-only. The first eligible occurrence establishes the initial baseline; an explicit template currency override bypasses this comparison.
9. Record the generated invoice on the occurrence.
10. Queue PDF generation and, only when automatic email remains eligible, email after commit.
11. Calculate the next local occurrence from the recurrence rule.

Batch 10D implements steps 1–7, 9, and 11. One worker pass uses the bounded `invumo.recurring.max_catch_up_occurrences` setting, which defaults to ten and is hard-capped at 100. Successful occurrence, issued Invoice, reminder materialization, template cursor/count, dispatch completion, and privacy-safe audit commit together. A permanent business failure rolls the transaction back completely and stores only bounded operational metadata on the dispatch/template; it does not advance the ordinal. The template remains Active but stalled until Owner/Admin corrects the source and retries the same dispatch identity. Batch 10E owns the automatic-delivery and currency-latch steps.

An email failure retries delivery against the same invoice. It never creates another invoice for that occurrence.

An authorized permanent deletion of an eligible generated Invoice removes that Invoice and its occurrence plus any pending occurrence-dispatch state in the same transaction. It does not rewind the template cursor, logical ordinal, or successful-occurrence count. Queue work addresses the persisted occurrence identity; if that row has been deliberately deleted, stale work exits without reconstructing the historical occurrence key. The next distinct eligible occurrence generates normally. Cancelling a generated Invoice preserves its occurrence and has no effect on later scheduled occurrences.

A provider-accepted manual send of a reviewed currency-change Invoice clears the template's review latch and stores that Invoice currency as the new confirmed delivery baseline. Clearing is recorded only after provider acceptance, does not retroactively send other issue-only occurrences, and is idempotent under request retries. Jobs recheck the latch immediately before provider delivery so an overlapping run cannot bypass it.

## Recurring downtime and pause behavior

Service downtime and an intentional template pause are different:

- After service downtime, process every occurrence that became due while the template remained Active.
- Process missed occurrences oldest first.
- Process at most the configured bounded number of occurrences for one template in one scheduler pass, default ten and hard maximum 100, then continue in later passes until caught up.
- Preserve each scheduled local issue date and due-date calculation.
- Apply the template's automatic-email setting to each recovered occurrence.
- Stable occurrence keys and uniqueness constraints prevent duplicates across recovery runs.
- Record that execution occurred late and expose scheduled time, actual time, and outcome.

Pausing prevents future occurrences from becoming eligible. Resuming starts with the next eligible occurrence after resume and does not backfill the paused interval unless the user explicitly requests and confirms a backfill.

## Reminder execution and downtime

Owner/Admin manage ordered Company defaults; every new Invoice copies those rules into its own editable snapshot, and every Company role allowed to manage the Invoice may override that snapshot. Invoice issue materializes enabled reminder instances using the Invoice's stored due date, snapshotted rules, Company timezone, and automation-local time. Zero-total Invoices materialize no reminder work.

Immediately before sending, recheck lifecycle, outstanding balance, due date, recipient, public-link state, and whether a later attempt already succeeded.

- Paid or Cancelled invoices suppress unsent reminders.
- Partially Paid invoices remain eligible while their outstanding balance is positive.
- A delayed before-due reminder may send only while the company-local due date has not passed; afterward it is marked `Skipped: stale`.
- A delayed after-due reminder may send while the invoice remains overdue and outstanding.
- If multiple after-due reminders accumulated during downtime, send only the newest eligible instance and mark the older due instances `Superseded` so recovery cannot flood the customer.
- Changing the due date recalculates only pending reminder instances; sent/suppressed historical records remain unchanged.
- Changing Company timezone or automation time recalculates only unfinished instances while retaining every terminal row's original local/UTC context.
- Cancellation and full payment suppress unfinished reminders inside the same lifecycle/ledger transaction. Reopening never replays sent work: past before-due work becomes stale and at most the newest eligible after-due rule receives one recovery instance at the next Company automation time.
- Reminder delivery may replace a naturally expired public-link generation but cannot re-enable explicitly disabled public access.
- Every send records the scheduled time, actual attempt time, resolved recipients, outcome, and delivery record.

## Retry policy

Make one initial attempt. Retry transient database, network, and provider failures up to five times after:

1. 1 minute
2. 5 minutes
3. 15 minutes
4. 1 hour
5. 6 hours

If the final retry fails, mark the execution failed, expose it in the operational UI, log/alert it, and allow an authorized manual retry using the same idempotency key.

Permanent generation failures do not retry indefinitely. Examples include an inactive or invalid template or business data that no longer satisfies Invoice generation. Record a visible reason and require corrective action. Delivery-only failures such as unavailable recipients, disabled public access, or a currency-review latch do not roll back the generated Issued Invoice; they record an allowlisted issue-only suppression reason and leave it available for manual delivery.

An Active template whose current occurrence is permanently failed remains deliberately stopped rather than skipping an Invoice. Owner/Admin see a Company-wide attention badge in primary navigation; it links to a recurring-list filter containing only failed Active templates. Members may still see row outcomes where otherwise authorized but do not receive the Company-wide operations aggregate or retry authority.

## Observability

Record at least:

- Logical occurrence/reminder identity
- Company and target identifiers
- Scheduled local and UTC timestamps
- Actual start/completion timestamps
- Attempt count and retry schedule
- Success, skipped, superseded, or failed outcome
- Generated invoice, PDF, email, and provider delivery identifiers where applicable
- Confirmed recurring delivery currency, currency-review latch state, suppression reason, and confirming manual-send identifier where applicable
- Structured failure category and safe error summary

Operators must be able to distinguish no due work, intentional suppression, transient retry, permanent failure, and scheduler/worker downtime.

## Required tests

- Weekly, monthly, quarterly, yearly, and custom interval calendar calculations
- Leap years and end-of-month rules defined by the recurrence specification
- Spring-forward nonexistent local times
- Fall-back repeated local times executing once
- Timezone and automation-time changes affecting future work only
- Overlapping scheduler processes claiming each dispatch once
- Queue retry after invoice creation reusing the same invoice
- Database rollback before occurrence completion
- Recovery of multiple missed recurring occurrences in order and in bounded batches
- Pause/resume without implicit backfill
- First automatic-email occurrence establishing its currency baseline
- Inherited currency change generating/issuing once while suppressing email and latching later occurrences
- Provider-accepted manual send clearing the currency-review latch without retroactively sending suppressed Invoices
- Failed/retried manual send leaving the currency-review latch intact
- Overlapping generation/delivery rechecking the currency-review latch before provider submission
- Explicit template currency override bypassing the inherited-currency gate
- Reminder suppression on Paid or Cancelled invoices
- Before-due reminders becoming stale during downtime
- Multiple missed after-due reminders collapsing to the newest eligible send
- Manual retry retaining the same idempotency key
- Tenant context being set and cleared for every worker job
