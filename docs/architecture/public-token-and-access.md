# Public Token and Access Contract

Status: Approved architecture decision  
Approved: 2026-08-27

This document defines the approved Phase 8 public-token security, persistence, tenant-bootstrap, rate-limit, and lifecycle contract. It refines the approved public-document behavior before routes, schema, or token issuance are implemented.

## 1. Scope

Phase 8 adds bearer-link access to the current Quote and Invoice representations already used by authenticated screens and PDFs. It does not add customer accounts, electronic signatures, a historical document-version screen, payment collection, or a separate public API.

The public page always renders the current persisted document and freshly generated current PDF. Previously delivered email content and attachments remain immutable delivery artifacts under Phase 9.

## 2. Token credential

- Generate exactly 32 random bytes with PHP's cryptographically secure `random_bytes()` source.
- Encode them as unpadded base64url, producing an exact 43-character token containing only `A-Z`, `a-z`, `0-9`, `_`, and `-`.
- Compute the lookup credential as lowercase hexadecimal SHA-256, producing an exact 64-character hash.
- Validate the exact token grammar before hashing. Invalid, missing, unknown, expired, revoked, disabled, wrong-kind, archived-Company, or suspended-Account credentials return the same localized unavailable response.
- Keep the token independent from UUIDv7 identifiers and human document numbers.
- Enforce global hash uniqueness. A collision aborts issuance and retries with a new random token.

SHA-256 is appropriate for lookup because the source token already has 256 bits of CSPRNG entropy; password hashing would add cost without useful protection against exhaustive search.

## 3. Recoverable authorized copy

Hash-only persistence cannot support copying an existing link or reusing the same valid link in a later email. Store two separate representations:

- `token_hash` for indexed public lookup; and
- authenticated Laravel-encrypted `token_ciphertext` so an authorized internal workflow can reconstruct the existing URL.

The ciphertext is hidden from model serialization and is decrypted only by the named internal link Query or the later delivery Action. The plaintext token, ciphertext, hash, and complete URL are excluded from audit, logs, exceptions, queue metadata, analytics, and provider payload history. Application-key rotation must re-encrypt retained ciphertext before retiring the previous key.

This is a deliberate refinement of the earlier shorthand that described the stored representation only as hashed. The database never stores plaintext.

## 4. Routes and response containment

Use the approved SaaS host with type-specific routes:

- `/q/{token}` and `/q/{token}/pdf`
- `/i/{token}` and `/i/{token}/pdf`

The route prefix must match the linked document kind. Tokens never appear in query strings or hidden form fields. Public decision forms use the already-resolved link context rather than accepting a second token field.

Every public response uses `Cache-Control: private, no-store`, `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow, noarchive`, `X-Content-Type-Options: nosniff`, and a public-page Content Security Policy with `frame-ancestors 'none'`. Pages load no third-party resources.

The current host access log records full request paths. Before any token route becomes live, the web-server/access-proxy configuration must suppress or structurally redact the token segment for these routes. This is a separate production-configuration authorization boundary. Application logs and exception reporting must also redact route tokens.

## 5. Relational model

`public_document_links` is a tenant-owned forced-RLS table containing:

- UUIDv7 `id` and `company_id`
- same-Company `document_id`
- positive integer `generation`
- exact 64-character lowercase `token_hash`
- bounded authenticated `token_ciphertext`
- `expires_at`
- nullable `revoked_at`, `revoked_by_user_id`, and revocation kind `EXPLICIT` or `REGENERATED`
- nullable `created_by_user_id`
- ordinary creation/update timestamps

Required database boundaries:

- global unique index on `token_hash`
- unique `(company_id, document_id, generation)`
- partial unique `(company_id, document_id)` where `revoked_at IS NULL`, so at most one current generation exists even after natural expiry
- same-Company restrictive document foreign key during ordinary writes; the guarded document-deletion Action explicitly removes link rows inside its aggregate transaction
- checks for token-hash grammar, positive generation, expiry after creation, and complete revocation metadata
- indexes on `(company_id, document_id, generation)` and the active-document lookup path

Do not persist IP address, User-Agent, plaintext token, complete URL, or a view counter on this table.

## 6. Company and document defaults

Add two Owner/Admin-managed Company defaults:

- `public_links_enabled_by_default`, default `true`
- `default_public_link_validity_days`, default `30`, allowed range `1..3650`

The enabled default is copied to new Quote and Invoice delivery settings, but it does not itself create a credential or expose a document. A link exists only after an authorized user explicitly creates/shares it or an explicit send workflow requires it. Existing documents remain disabled during the migration; the new Company default affects later documents only.

There is no non-expiring public link in v1. Expiry is an absolute timestamp calculated from generation time and never slides after use.

## 7. Link lifecycle

Every mutation is a named root Action under the document-owning module and uses the global configuration-before-aggregate lock order:

1. lock Company settings;
2. lock the Document/subtype aggregate;
3. lock all link generations in UUID order;
4. recheck ability, lifecycle, and current link state;
5. write link state and privacy-bounded audit together.

Creating or re-enabling access creates a new generation. Explicit revocation sets the document access latch to false and revokes the current generation. Re-enabling never unrevokes an old row.

Regeneration requires public access to remain enabled, revokes the current generation as `REGENERATED`, and creates the next generation atomically. The old token fails immediately after commit. Naturally expired rows are not described as explicitly revoked; replacement revokes the old generation as `REGENERATED` before creating the new one.

Direct email may create or confirm a valid link through its explicit send Action. A reminder may replace a naturally expired generation only while the document access latch remains enabled. It never recreates explicitly revoked/disabled access.

## 8. Document-state behavior

An authorized Company member may deliberately expose the current representation of any retained Quote or Invoice lifecycle, including Draft and Cancelled. Lifecycle is always displayed.

Viewing/downloading and Quote decisions are separate capabilities:

- valid links may view/download the current document;
- only a stored `SENT`, non-expired Quote may be accepted or rejected;
- Expired Quotes remain viewable but decision controls are unavailable;
- Cancelled Invoices remain viewable/downloadable and expose no financial action.

The presence of any public-link generation is permanent evidence that a Quote-derived Invoice was shared. `UnlinkQuoteInvoice` must therefore check link history rather than the current `public_access_enabled` boolean. Revocation or expiry never restores the never-shared unlink condition.

Permanent document deletion locks and removes every link generation, records the existing minimal audit tombstone/high-risk exposure fact without token material, and prevents later token bootstrap. Link history does not independently block an otherwise approved deletion.

## 9. Public RLS bootstrap

Public requests never accept a Company identifier and never use a general RLS bypass.

1. Validate and hash the presented token before database lookup.
2. Begin one short restricted-runtime transaction.
3. Set only the 64-character hash as transaction-local `app.public_link_hash`.
4. Use one purpose-specific raw/query-builder lookup that bypasses only Laravel's tenant global scope. A dedicated `FOR SELECT TO invumo_runtime` policy exposes at most the row whose unique hash matches, is unrevoked, and is unexpired.
5. Derive `company_id` and `document_id` only from that returned row.
6. Establish the normal transaction-local Company context inside the same outer transaction through a dedicated public-link context boundary.
7. Load the document and snapshots through ordinary forced RLS, then recheck document-kind, access latch, Company/Account availability, lifecycle, and request purpose.
8. Clear both application context objects in `finally`; transaction end clears both database settings.

The bootstrap policy grants no INSERT, UPDATE, DELETE, document, Customer, Company-settings, transaction, membership, or audit visibility. No matching row means default deny. Public-link mutations use ordinary authenticated Company context, never the bootstrap policy.

## 10. Rate limits

Use Laravel's database-backed named rate limiters with multiple simultaneous limits:

| Surface | Per source-IP fingerprint | Per token hash |
| --- | ---: | ---: |
| HTML view | 60/minute | 120/minute |
| PDF generation/download | 10/minute | 10/minute |
| Accept/Reject submission | 10/minute | 5/minute |

Keys contain a keyed one-way fingerprint of the normalized source IP and the token SHA-256 hash, never their plaintext values. Invalid token guesses still consume the IP limit. Quote and Invoice routes share the corresponding public surface bucket. Rate-limited responses include `Retry-After`, reveal no token/link state, create no business audit event, and log only a bounded outcome code.

These limits constrain resource abuse; token unpredictability remains the primary guessing defense.

## 11. Side effects and retained metadata

Successful public GET/PDF reads remain side-effect free. Do not persist `last_used_at`, view counters, IP addresses, or User-Agent strings. This intentionally removes the earlier proposed `last-used metadata` field because it has no approved product consumer and would make rendering mutate retained state.

Phase 8C public Accept/Reject persists the required supplied name/email in the decision-owned live record under the Customer-data erasure boundary. Append-only audit records only public actor type, decision, target, timestamp, and non-sensitive lifecycle facts; it never copies the name, email, IP, User-Agent, token, hash, or URL.

## 12. Concurrency and idempotency

- Generation, revocation, and regeneration serialize on the Document and complete link set.
- The partial unique index independently prevents two current generations.
- Public resolution uses one database transaction and one token generation; regeneration racing a read yields either the complete old committed state or the complete new committed state.
- Accept/Reject locks the link and Quote aggregate, rechecks expiry/revocation/Sent/commercial validity, commits the decision and audit together, treats replay of the same decision as idempotent, and rejects an opposite later decision.
- No public GET, PDF, or rejected lookup creates queue work, email, artifacts, audit rows, or provider calls.

## 13. Required verification

Phase 8 implementation must prove:

- token entropy, exact encoding/hash grammar, ciphertext recoverability, redaction, and key-rotation behavior
- plaintext token/hash/URL absence from database plaintext fields, audit, logs, exceptions, queue payloads, and generated page props not explicitly used for authorized copy
- one-current-generation, immediate revocation/regeneration, absolute expiry, default bounds, and no sliding lifetime
- public bootstrap sees exactly one eligible link and cannot read any tenant row before derived Company context is established
- unset, malformed, expired, revoked, disabled, wrong-kind, cross-Company, archived-Company, and suspended-Account paths fail closed without an existence oracle
- Laravel authorization and forced RLS independently protect internal link management
- route/IP/token rate limits and PDF resource limits
- EN/RO desktop/mobile public views, PDF download, accessibility, no page overflow, no third-party requests, response headers, and side-effect-free rendering
- Quote decision lifecycle, idempotency, replay, concurrency, required identity, privacy-safe audit, and tenant isolation in Batch 8C
- production access-log redaction is verified before public routes are enabled

## 14. Approval record

The owner explicitly approved this combined choice on 2026-08-27:

1. 256-bit base64url tokens, SHA-256 lookup hashes, and encrypted recoverable token copies.
2. Type-specific token-in-path routes, with mandatory application/proxy access-log redaction before rollout.
3. New-Company public-link default enabled, 30-day default validity, `1..3650` configurable days, and no non-expiring option.
4. The exact layered rate limits in section 10.
5. Side-effect-free reads with no persisted `last_used_at`, IP address, User-Agent, or view counter.

This approval completes the Phase 8A gate. Batch 8B may implement the contract without reopening these choices.

## References

- [PHP `random_bytes`](https://www.php.net/manual/en/function.random-bytes.php)
- [PostgreSQL row security policies](https://www.postgresql.org/docs/18/ddl-rowsecurity.html)
- [PostgreSQL transaction-local `set_config`](https://www.postgresql.org/docs/18/functions-admin.html)
- [Laravel rate limiting](https://laravel.com/docs/13.x/rate-limiting)
- [OWASP session-token entropy guidance](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
