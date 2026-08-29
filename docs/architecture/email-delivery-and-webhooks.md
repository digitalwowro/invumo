# Email Delivery and Webhook Contract

Status: Approved architecture decision  
Approved: 2026-08-27

This document defines the Phase 9 transport, provider-authentication, retry, idempotency, event-ordering, privacy, and retention contract for Quote, Invoice, reminder, and payment-received email. It does not change the existing Laravel SMTP path for account verification, recovery, or Company invitations.

## 1. Transport boundary

- Document and reminder email uses Zoho ZeptoMail's HTTPS Send API.
- Foundational account email continues through the existing authenticated ZeptoMail SMTP transport.
- Laravel remains authoritative for resolved recipients, language, content, attachment mode, public-link eligibility, delivery state, and persisted history.
- One source-owned provider adapter owns ZeptoMail request/response mapping. Domain Actions and jobs never construct provider payloads directly.
- Provider configuration is environment-backed, validated by production readiness, and never exposed to browser props, logs, audit, queue metadata, or persisted provider payload history.
- External calls occur only after the owning database transaction commits. Tests use fakes and never contact ZeptoMail.

The Send API is selected because it returns structured outcomes and supports Invumo's stable `client_reference`, attachment, and tracking requirements without changing the established account-email path.

## 2. Resolved delivery boundary

Before the external call, the owning Action persists one immutable logical delivery containing the exact resolved:

- Company, document, event, language, and internal idempotency identity;
- `TO`, `CC`, and `BCC` recipients after validation and case-insensitive deduplication;
- subject, body, button label, signature, and public URL used for this attempt;
- secure-link-only or attached-PDF choice;
- immutable PDF artifact reference when attached; and
- provider-safe tracking settings.

The queued job carries only the logical delivery UUID, Company UUID, and non-sensitive machine idempotency metadata. It reloads the delivery under forced RLS, rechecks the exact public-link generation and document version, prepares any immutable PDF artifact, and creates a new provider-attempt row immediately before submission. The provider call begins only after that transaction closes.

Already-resolved delivery content and attachments do not change when the Company template, Customer, document, or current PDF changes later.

Only one logical delivery may be queued or retrying for a document at a time. Document edits and lifecycle/transaction mutations are blocked while that delivery is pending. Emergency public-link revocation remains available; a worker that sees the exact persisted link revoked, expired, or disabled rejects the delivery before provider submission.

Automated reminder deliveries use the same immutable delivery/attempt/provider boundary but remain distinguishable as `PAYMENT_REMINDER`. A queued reminder does not freeze Invoice lifecycle, due-date, or transaction mutations: those root Actions must be able to suppress or recalculate it. The worker therefore rechecks the reminder instance, lifecycle, outstanding balance, schedule, recipient set, and public-link state immediately before provider submission. Direct sends remain mutation-blocking while queued. Reminder failures are visible on the Invoice and in the Owner/Admin Company reminder-operations view; manual retry preserves the reminder occurrence and dispatcher identities, creates a fresh immutable delivery from current recipients/content/link eligibility, requires a duplicate-delivery warning, and audits only machine-safe identifiers/category metadata. Earlier delivery and attempt rows remain unchanged.

Payment-received delivery is a separate, explicit user action on one retained Payment. Recording, importing, or backfilling a Payment never queues email. The Action resolves the current Invoice recipients, `PAYMENT_RECEIVED` template, exact Payment amount/date, outstanding balance, public link, and PDF mode into a new immutable logical delivery, then reuses the same provider job and quotas. The delivery snapshots the Payment edit version that produced those values. A queued/retrying receipt blocks direct transaction mutation like another direct send, and both the worker and manual retry recheck that the Invoice is still Issued and the linked row is still the same Payment version immediately before provider submission. Accepted or `UNKNOWN` history produces a correction warning but does not freeze the financial row. Editing a Payment into another kind or deleting it explicitly detaches the nullable live transaction reference inside the transaction-owning Action; PostgreSQL independently rejects a referenced non-Payment. Editing amount or date leaves historical identity intact but makes the frozen version ineligible for submission or retry. The resolved receipt content, recipients, attempts, and document-level history remain unchanged, and a detached or version-stale receipt cannot be retried.

Direct composition remains editable, including one-off recipient addresses, but it is not an unbounded mail relay. One logical delivery accepts at most ten case-insensitively unique recipients. A shared named HTTP limiter bounds user and Account submission bursts, while the provider-neutral worker boundary atomically consumes weighted recipient budgets for the Company, owning Account, and shared provider identity immediately before creating a provider attempt. Initial sends, manual retries, automatic retries, and non-HTTP callers therefore converge on the same provider-submission guard. The six counters are read together and persisted in one bounded cache write while a lock whose lifetime exceeds the worker timeout is held. Genuine exhaustion returns a terminal quota outcome; lock contention or persistence failure raises into the approved queue retry path and never records false exhaustion. The worker also rechecks Company archive, Account suspension, and initiating-User suspension before any provider call. Quota excess and lost sender authority fail visibly without contacting ZeptoMail. Production readiness rejects absent, non-positive, or overly permissive quota configuration.

## 3. Provider identity and duplicate control

- Every logical delivery has one stable Invumo delivery UUID and idempotency key enforced by the database.
- Every provider submission attempt has its own immutable attempt identity.
- Send `client_reference` as that exact attempt identity; do not place Company, Customer, document number, email address, public token, or business content in it.
- Queue uniqueness reduces duplicate pending work but is not treated as provider exactly-once delivery.
- ZeptoMail does not document a Send API idempotency key. Invumo therefore never claims exactly-once provider submission after an ambiguous network outcome.

Known provider acceptance marks the attempt accepted/sent. A known provider rejection records the mapped failure without pretending the message was accepted.

## 4. Retry and ambiguous outcomes

The shared retry schedule remains one initial attempt followed by five retries after 1 minute, 5 minutes, 15 minutes, 1 hour, and 6 hours.

- Retry only failures known to have occurred before provider acceptance, including a structured retryable rejection or a connection failure proven to occur before request transmission.
- Validation, recipient, template, revoked-access, attachment, authentication, and other permanent failures do not retry automatically.
- If a timeout, connection loss, malformed response, or worker interruption occurs after transmission may have begun and acceptance cannot be proven, mark the attempt `UNKNOWN`.
- `UNKNOWN` attempts are never resent automatically. The UI explains that delivery may have occurred.
- An authorized manual retry rechecks current eligibility and creates a new immutable provider attempt with a new `client_reference`, after explicit duplicate-delivery warning. It does not rewrite the unknown attempt.
- Provider acceptance followed by a local persistence failure is reconciled from authenticated provider events when possible and otherwise remains visibly unknown for operator review.
- A worker interruption after a provider-attempt row becomes `PENDING` is treated as ambiguous and becomes `UNKNOWN`; an interruption before an attempt exists is a known internal failure and is not represented as provider rejection.

Operational logs retain only correlation ID, component, duration, attempt number, provider HTTP class, and a bounded failure category. They exclude recipients, content, URLs, tokens, provider bodies, provider identifiers, and exception text.

Attached PDFs are limited to 11 MiB of raw bytes. This leaves deterministic room for base64 expansion, MIME/request overhead, and resolved message content beneath ZeptoMail's documented 15 MB total-message limit.

## 5. Tracking contract

The owner explicitly selected provider tracking for delivered, bounced, opened, and clicked events.

- Enable ZeptoMail open and click tracking for document and reminder deliveries.
- The send composer and history UI state clearly that open/click signals are provider-reported and may be incomplete or affected by mail-client privacy features.
- Click tracking permits ZeptoMail to rewrite tracked links. Secure public URLs are therefore disclosed to the selected delivery provider only as required for the approved email service; they remain absent from Invumo audit, logs, queue metadata, webhook history, and analytics.
- v1 stores no recipient IP address, geolocation, browser, device, User-Agent, or raw provider payload.

The normalized provider event vocabulary is:

- `DELIVERED`
- `SOFT_BOUNCED`
- `HARD_BOUNCED`
- `OPENED`
- `CLICKED`
- `FEEDBACK_LOOP`

Provider-specific values are mapped at the webhook boundary and do not leak into domain enums.

## 6. Webhook authentication and containment

The live ZeptoMail Agent UI supports one static authorization header per webhook but no longer exposes the Authentication Key required by its published `producer-signature` HMAC instructions. The owner therefore approved the provider-supported static-header boundary before Batch 9D closeout:

1. Configure the pinned header name `X-Invumo-Webhook-Key` and a distinct environment-backed random value of 32–512 characters in the ZeptoMail Agent. The value is not the Send API key.
2. Require the exact header before processing every `POST` and compare its SHA-256 digest with the configured secret in constant time.
3. Accept an unauthenticated empty `GET` or `HEAD` only as a side-effect-free reachability probe and reject non-empty unauthenticated verification requests. An empty `POST` remains an authenticated side-effect-free probe. Authenticated event requests accept ZeptoMail's observed bounded `application/json` body and its documented bounded form body containing exactly one `data` field; both decode into the same strict event object.
4. Parse and validate event JSON only after authentication. Authenticated Agent events without an Invumo UUID `client_reference` are acknowledged and ignored, so provider tests and unrelated Agent traffic cannot create tenant effects or retry indefinitely.
5. Resolve only a mapped internal attempt through its non-sensitive `client_reference`; never accept a Company or document identity as authority from the payload. The runtime role receives one exact-reference bootstrap `SELECT` policy; normal forced RLS remains in force and the mapped Company context is revalidated before the event write.

The static header is protected in transit by HTTPS but supplies no signed per-request timestamp or payload MAC. Invumo therefore does not claim the superseded five-minute producer-signature freshness property. Replayed authentic events remain effect-idempotent through the globally unique provider event identifier, and delayed legitimate events remain acceptable. A provider-reported occurrence more than five minutes in the future is still rejected as malformed, but that event time is not treated as request authentication.

The endpoint is rate limited, has a bounded request size, returns a uniform response after authenticated processing, and never establishes general Company or RLS-bypass access. The system actor may append normalized events and update the mapped delivery projection only.

## 7. Idempotency and event ordering

- `webhook_request_id` is the provider-event idempotency key and is unique within the provider integration.
- Store each accepted normalized provider event once. Duplicate delivery creates no second row, audit event, or customer-visible effect.
- Milestone timestamps retain the earliest authenticated occurrence of that milestone.
- Out-of-order events never move the delivery projection backwards or replace a more informative failure with an older event.
- An authenticated `OPENED` or `CLICKED` event does not fabricate a missing `DELIVERED` event. The UI may show the provider-reported milestone independently.
- Soft and hard bounces remain distinct. A later authenticated delivery/open/click event may add its own milestone without deleting the earlier normalized event history.
- Unknown event types are ignored with a bounded machine outcome and no raw-payload retention.

No webhook can send email, regenerate a public link, mutate a document, change recipients/content, or invoke an Owner/Admin action.

## 8. Persistence and erasure

While the source document exists, Invumo retains the immutable delivery content, resolved recipients, attachment artifact, normalized provider events, and the minimum provider references needed for reconciliation and support.

Permanent document deletion must extend the document-owning destructive Action to:

- erase subject, body, button label, signature, recipient names/addresses, public URL, attachment bytes/reference, provider message/reference identifiers, and any provider diagnostic text;
- set the delivery's erasure timestamp; and
- retain only the internal attempt identity, event kind, normalized lifecycle timestamps, retry count, and bounded non-sensitive failure category needed to prove that an operational event occurred.

Provider events are never a second store for recipient identity or message content. No raw webhook payload is persisted. Company erasure removes the complete tenant-owned delivery history under the existing Company-erasure boundary.

The normalized event row contains only provider name/event identifier, event type, and occurred/received timestamps. The delivery projection stores the earliest timestamp for each normalized milestone. Document erasure nulls the provider identity and idempotency identifier while retaining only the event type and timestamps.

## 9. Authorization and audit

- Owner/Admin manage Company email templates, reminder defaults, and Company-wide failure operations.
- Owner/Admin/Member may compose and send a Quote or Invoice when they hold the corresponding document ability.
- Owner/Admin/Member may explicitly send or resend a payment-received message for an Issued Invoice Payment when they hold the Invoice management ability; no role may enable implicit sending on Payment creation.
- Owner/Admin/Member may view document-local delivery history.
- Owner/Admin may retry automated reminder failures; direct-send retries follow the document send permission.
- Jobs and webhooks are narrow system actors and never inherit a Company role.

Append-only audit records user intent and safe state transitions only. It may retain event type, language, attachment mode, recipient counts, attempt outcome, and bounded failure category. It never retains recipient addresses/names, subject/body/signature, public URL/token/hash, provider secret, provider request/response body, provider identifier, IP address, device, location, or User-Agent.

## 10. Required verification

Phase 9 implementation must prove:

- API credentials and webhook secrets remain environment-backed and non-disclosing;
- resolved delivery content is immutable and external calls begin only after commit;
- known rejection, retryable pre-send failure, ambiguous post-transmission failure, manual retry, and provider acceptance are distinct states;
- ambiguous outcomes do not auto-resend;
- webhook secrets, verification probes, malformed inputs, duplicate IDs, unknown events, and cross-attempt mappings fail safely;
- duplicate/out-of-order webhooks do not duplicate effects or regress projections;
- click/open tracking is displayed as provider-reported and webhook privacy fields are discarded;
- forced RLS and Laravel authorization independently isolate every delivery, recipient, artifact, reminder, and event row;
- document deletion performs the approved delivery-content erasure while retaining only non-sensitive operational facts;
- Payment creation/backfill has no email side effect, while explicit receipt sends are idempotent, bind content to an immutable Payment edit version, reject version drift before submission/retry, and remain immutable when the Payment is later corrected or deleted;
- tests never call ZeptoMail or send real email; and
- the complete EN/RO responsive send, history, failure, and retry workflows pass the repository quality gate.

## 11. Approval record

The owner explicitly approved these choices on 2026-08-27:

1. ZeptoMail REST API for document/reminder delivery while account email stays on SMTP.
2. Delivered, bounced, opened, and click tracking.
3. Ambiguous transmission outcomes become visible `UNKNOWN` attempts and require a warned manual retry rather than automatic resend.
4. Privacy-minimal authenticated webhooks with no raw payload, IP, geolocation, browser, or device retention.
5. Document deletion erases delivery content, recipients, artifacts, and provider identity while retaining only non-sensitive operational facts.

This approval completes Batch 9A. Batch 9B may implement Company templates and side-effect-free preview without provider writes. Batches 9C and 9D must implement the outbound and inbound halves of this contract separately.

## References

- [ZeptoMail Send API](https://www.zoho.com/zeptomail/help/api/email-sending.html)
- [ZeptoMail webhooks](https://www.zoho.com/zeptomail/help/webhooks.html)
- [ZeptoMail SMTP headers](https://www.zoho.com/zeptomail/help/smtp-home.html)
