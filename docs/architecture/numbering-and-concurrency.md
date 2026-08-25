# Document Numbering and Concurrency

Status: Approved architecture decision  
Last updated: 2026-08-25

This specification defines automatic quote and invoice numbering. It preserves the product's flexible manual-numbering rules while ensuring that two concurrent automatic creations cannot receive the same suggestion.

## Supported v1 configuration

Each company has separate Quote and Invoice number series. A series supports:

- Company-editable literal text around the approved tokens, up to 120 characters with no control characters or unrecognized braces
- Exactly one `{NUMBER}` token; it is mandatory and may appear only once
- An optional `{YEAR}` token that may appear at most once and resolves automatically to the current four-digit Company-local year
- Numeric zero-padding from 1 through 12 digits, configured separately from the pattern and defaulting to 4
- Reset policy: never or company-local calendar year

The default Quote pattern is `Q-{YEAR}-{NUMBER}` and the default Invoice pattern is `I-{YEAR}-{NUMBER}`. These are starting values, not enforced formats: for example, a Company may choose `INV-{NUMBER}`. The presence of `{YEAR}` does not implicitly reset the numeric sequence. Reset behavior is an explicit setting and defaults to never. v1 does not provide arbitrary expressions, multiple counters in one format, month/day tokens or resets, or a numbering rules engine.

The settings preview resolves `{YEAR}` on the server from the configured IANA Company timezone; the browser may reproduce that server-provided preview context but never supplies an authoritative year or falls back to UTC/browser time. Assigned document numbers remain persisted values and do not mutate when the calendar year changes.

The numeric period is `ALL` for a non-resetting series or the four-digit company-local year for an annual series. A new Draft starts with the current company-local date as its initial issue date, and that date determines the period. Changing or clearing the issue date later never silently renumbers the document; the user may explicitly request a new number from the appropriate period.

## Persistent state

The relational model must include the equivalent of:

- A number-series record containing `company_id`, document type, format, padding, and reset policy
- A number-counter record containing series, period key, and next numeric value
- A unique constraint on `(series_id, period_key)`
- A positive-value constraint on the next numeric value
- Document fields that retain the rendered number and whether it was automatically assigned or manually entered
- An idempotent document-creation key so a retried browser request returns the same Draft instead of consuming another number

Table and column names may change in the relational-model review, but these state boundaries may not.

Changing a series retires its active configuration and creates a new active configuration version. Existing documents and later counters retain their original series relationship; settings changes never rewrite historical configuration. Raw editable patterns are excluded from append-only audit payloads because literal text is Company-authored free-form content. Audit records retain the document type, stable changed-field names, padding, and reset policy.

Document numbers intentionally do not have an unconditional unique constraint because an authorized user may confirm a duplicate. Add a non-unique lookup index covering company, document type, and rendered number so duplicate detection is reliable and fast.

## Automatic allocation

Clicking New Quote or New Invoice creates and persists a Draft immediately. The number displayed in its editor is already assigned; it is not an unreserved browser-only preview.

Automatic allocation runs in one PostgreSQL transaction:

1. Validate the company, permission, document type, initial issue date, and idempotent creation key.
2. Resolve the series and period key.
3. Ensure the period counter exists using an atomic insert protected by the unique constraint.
4. Load the counter using `SELECT ... FOR UPDATE`.
5. Render the candidate from the locked `next_value`.
6. If the exact rendered number is already used by a non-deleted document because of a manual override, advance and render again until an unused automatic candidate is found.
7. Insert the Draft and its audit event.
8. Advance the counter to one more than the allocated numeric value.
9. Commit.

Concurrent allocators for the same company, document type, and period wait on the same counter row. Allocators for different companies, document types, or periods do not block one another.

Do not use a PostgreSQL advisory lock for normal numbering. The counter row is persisted business state and a row lock is easier to inspect, repair, constrain, and test.

If the transaction fails, both Draft creation and counter advancement roll back. Transaction-level deadlock or serialization failures are retried a small bounded number of times using the same creation key.

## Manual numbers and renumbering

Users may enter arbitrary manual document numbers, including values that do not match the configured format.

- Saving a number that duplicates a non-deleted document in the same company and document type requires a visible warning and explicit confirmation.
- A manual number or renumbering action does not change the automatic counter by default.
- When a manual value matches the configured format, the UI may offer an explicit `Continue future numbering from this value` action.
- Counter adjustment locks the same counter row and is audited.
- Moving a counter backwards requires explicit Owner/Admin confirmation, including a preview of the next resulting number and any duplicate/reuse risk.
- A manual override must never change a different company's, document type's, or period's counter.
- Renumbering an issued document is a significant audited edit.

The approved example is therefore explicit rather than inferred: after deleting `2` and renaming `3` to `2`, an authorized user may confirm that future numbering should continue from `3`. Without that confirmation, the existing counter is unchanged.

## Deletion, gaps, and reuse

- An abandoned Draft may consume a number.
- Deleting a document never rewinds a counter automatically.
- Renumbering never releases or rewinds a counter automatically.
- Normal automatic allocation is monotonically forward within its counter period.
- Reuse after deletion or renumbering happens only through an explicit counter realignment or manual number entry, with warnings and audit history.
- Switching settings or entering a new annual period creates a new counter; it never silently rewrites existing documents.

## Required tests

Automated integration tests must cover:

- Many simultaneous Draft creations in one company/type/period produce distinct automatic numbers
- Quote and Invoice counters remain independent
- Company counters remain independent
- Annual-boundary races create one counter row and distinct numbers
- A repeated creation request with the same idempotency key returns the same Draft
- Transaction failure rolls back the document and counter change together
- A manual high number is skipped by later automatic allocation when necessary
- Manual duplicates require explicit confirmation but remain possible
- Renumbering does not alter a counter without the explicit continuation action
- Backward realignment requires permission, warning, and audit history
- Deletion and abandoned Drafts do not silently cause reuse

## Reference

- [PostgreSQL row-level locks](https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-ROWS)
