# Owner, Admin, and Member Permission Matrix

Status: Approved
Approved: 2026-08-22
Last updated: 2026-08-27

This document assigns every v1 Company action to the Owner, Admin, and Member roles. It translates the approved [master build brief](../product/master-build-brief.md), [domain rules](../product/domain-rules.md), [financial/document state contract](document-and-financial-state.md), [numbering contract](numbering-and-concurrency.md), [scheduling contract](scheduling-and-jobs.md), and [tenant-isolation contract](tenant-isolation.md) into one authorization contract for Laravel Policies, application actions, queue jobs, tests, and React UI visibility.

The state, tenant, calculation, snapshot, idempotency, and deletion rules in those approved documents always apply. A role permission never bypasses a state precondition or integrity guard.

Every role allocation in this matrix was explicitly approved by the owner on 2026-08-22. A later permission expansion or restriction requires its own explicit approval before this contract or the canonical tracker is changed.

## 1. Role model

- v1 has three fixed Company roles only: Owner, Admin, and Member. It has no custom roles, per-user permission toggles, or per-record ownership rules.
- Permissions belong to the active Company membership. The same User may have a different role in another Company.
- Every active Company member may see and work with ordinary Company data allowed to their role; records are not restricted to their creator.
- The singular Owner has every Company permission.
- Admin is the Company-management role. It has all operational and configuration permissions except authority over the owning Account, ownership transfer, permanent Company erasure, or the Owner membership.
- Member is the ordinary day-to-day role. It can manage Customers, routine document workflows, Payments/Refunds, Quote lifecycle corrections, and Invoice cancellation/reopening. It cannot change Company configuration, control unattended automation, create or mutate Adjustments, manage the product catalog, permanently delete business records, unlink Quote provenance, or make exceptional duplicate/issued-number decisions.
- Removing a membership or accepting a role change affects authorization immediately. Existing sessions must not retain the previous Company abilities.
- Platform Owner is a separate internal role governed by the [Platform Operations contract](platform-operations.md). It is never inferred from any Company role and does not grant Company membership or tenant-data access.

## 2. Matrix legend

| Value    | Meaning                                                                                                                            |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Yes      | The role may perform the action, subject to normal validation and state rules                                                      |
| Guarded  | The role may perform it only with the additional confirmation, reason, reauthentication, or state checks named in the Notes column |
| Self     | This is a personal User action rather than authority over other Company members                                                    |
| Use only | The role may view/select active values but cannot manage their source records                                                      |
| No       | The server denies the action and the UI does not offer it                                                                          |

## 2.1 Platform Operations matrix

This matrix is independent of the Company-role tables below.

| Action                                                       | Platform Owner | Company Owner/Admin/Member without platform role | Notes                                                                                                                                                                                                                     |
| ------------------------------------------------------------ | -------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Open Platform Operations                                     | Yes            | No                                               | Verified, unsuspended User and current operator record required                                                                                                                                                           |
| View approved User/Account/Company control-plane metadata    | Yes            | No                                               | Never includes tenant business content or an RLS bypass                                                                                                                                                                   |
| View current plan lifecycle and upcoming expirations         | Yes            | No                                               | Operational status/date tracking only; no billing/payment data exists in v1                                                                                                                                               |
| Change an Account's seeded Plan or lifecycle                 | Guarded        | No                                               | Recent password confirmation, explicit confirmation/reason, locks, validation, and platform audit                                                                                                                         |
| Suspend/reactivate a non-operator User and revoke sessions   | Guarded        | No                                               | Cannot target self or the last active Platform Owner                                                                                                                                                                      |
| Suspend/reactivate an Account                                | Guarded        | No                                               | Blocks Companies owned by that Account; does not affect unrelated Account ownership                                                                                                                                       |
| View platform audit                                          | Yes            | No                                               | Separate from Company audit                                                                                                                                                                                               |
| Grant/revoke Platform Owner through the web UI               | No             | No                                               | Protected confirmed application command only in v1; last active operator cannot be removed                                                                                                                                |
| Read tenant Customers/documents/Transactions without context | No             | No                                               | Full impersonation must first establish the selected User's authorized Company/RLS context                                                                                                                                |
| Fully impersonate a non-operator User                        | Guarded        | No                                               | Shared recent-password window plus action throttle; the mutation accepts no password; no reason/separate action confirmation/special timeout; target permissions/RLS and dual audit apply; Platform Operations is blocked |

## 3. Already-approved authorization boundaries

- Each Company has exactly one Owner. An invitation or ordinary role change may assign only Admin or Member; Owner changes only through the ownership-transfer workflow.
- Only Owner/Admin manage Products & Services and receive its management destination. Members receive the separate named ability to search and use active catalog entries inside permitted editors.
- Accepted is the ordinary Quote-to-Invoice path. Only Owner/Admin may confirm conversion from Draft, Sent, or Expired. Rejected must first be corrected to an allowed state.
- Backward number-counter realignment requires Owner/Admin permission, confirmation, reuse preview, and audit history.
- Public Quote decisions are authenticated by the valid public-link workflow, not by a Company role.
- Queue jobs and provider webhooks are system actors with narrow named actions. They do not receive Owner/Admin authority.
- PostgreSQL RLS isolates Companies; Laravel Policies and application actions enforce roles. Neither layer substitutes for the other.

## 4. Personal access and Company governance

| Action                                                         | Owner   | Admin   | Member | Notes                                                                                                                  |
| -------------------------------------------------------------- | ------- | ------- | ------ | ---------------------------------------------------------------------------------------------------------------------- |
| Edit own profile, application language, password, and sessions | Self    | Self    | Self   | Not delegated through a Company role                                                                                   |
| Switch among Companies where the User has active membership    | Yes     | Yes     | Yes    | The destination membership controls the new context                                                                    |
| View active Company identity and member directory              | Yes     | Yes     | Yes    | Does not expose secrets or Account controls                                                                            |
| View the owning Account's plan and entitlements                | Yes     | No      | No     | Exclusive among Company roles; Platform Owner lifecycle administration follows the separate matrix                     |
| Transfer Company ownership                                     | Guarded | No      | No     | Existing Admin/Member only; validate destination Account/Plan, reauthenticate, confirm former-Owner outcome, and audit |
| Permanently delete/erase the Company                           | Guarded | No      | No     | Reauthentication, highest-friction confirmation, dependency ordering, and audit required                               |
| Invite a User as Admin or Member                               | Yes     | Yes     | No     | Expires 7 days after issue/resend; revocable and single-use; cannot invite Owner                                       |
| Resend or revoke a pending invitation                          | Yes     | Yes     | No     | Audit significant actions                                                                                              |
| Change another non-Owner membership between Admin and Member   | Guarded | Guarded | No     | Confirmation and audit; Admin cannot affect Owner or change its own role through this action                           |
| Remove another non-Owner member                                | Guarded | Guarded | No     | Confirmation and audit; Admin cannot remove Owner or itself through this action                                        |
| Leave a Company                                                | Guarded | Self    | Self   | Owner must transfer ownership or erase the Company first                                                               |

## 5. Company settings and reusable configuration

| Action                                                                                          | Owner   | Admin   | Member   | Notes                                                                           |
| ----------------------------------------------------------------------------------------------- | ------- | ------- | -------- | ------------------------------------------------------------------------------- |
| View/select active Company defaults and presets in ordinary workflows                           | Yes     | Yes     | Use only | Members receive only values needed by their permitted screens                   |
| Edit legal identity, address, and registration                                                  | Yes     | Yes     | No       | Existing document snapshots remain unchanged                                    |
| Edit timezone or automation time                                                                | Guarded | Guarded | No       | Approved confirmation, audit, and pending-schedule recalculation rules apply    |
| Edit document defaults, language, payment terms, Quote validity, notes, and Terms & Conditions  | Yes     | Yes     | No       | Affects future/default resolution only unless explicitly reapplied              |
| Manage currencies, display style, and precision                                                 | Yes     | Yes     | No       | Existing document snapshots remain unchanged                                    |
| Manage tax presets and bank accounts                                                            | Yes     | Yes     | No       | Referenced records archive rather than rewriting snapshots                      |
| Manage numbering formats and reset policy                                                       | Yes     | Yes     | No       | Does not silently alter existing counters/documents                             |
| Realign a number counter, including moving it backwards                                         | Guarded | Guarded | No       | **Already approved:** lock, preview, duplicate/reuse warning, reason, and audit |
| Manage Company logo and primary brand color                                                     | Yes     | Yes     | No       | Upload validation remains a Phase 1 gate                                        |
| Manage Company email templates, reminder defaults, recipient defaults, and public-link defaults | Yes     | Yes     | No       | Per-document/per-send overrides are assigned separately below                   |

## 6. Customers and Products & Services

| Action                                                              | Owner   | Admin   | Member   | Notes                                                       |
| ------------------------------------------------------------------- | ------- | ------- | -------- | ----------------------------------------------------------- |
| Search/view Customers and contacts                                  | Yes     | Yes     | Yes      | Company-scoped only                                         |
| Create/edit Customers, contacts, defaults, and delivery preferences | Yes     | Yes     | Yes      | Includes the inline Customer modal                          |
| Archive or restore a Customer                                       | Yes     | Yes     | Yes      | Reversible operational action; snapshots remain unchanged   |
| Permanently delete a Customer                                       | Guarded | Guarded | No       | Only after blocking dependencies are removed                |
| Search/select active Products & Services                            | Yes     | Yes     | Use only | **Already approved:** selection copies an editable snapshot |
| Create/edit/archive/restore Products & Services                     | Yes     | Yes     | No       | Includes saving a new inline catalog entry                  |
| Permanently delete an unreferenced Product/Service                  | Guarded | Guarded | No       | Referenced entries use archive; snapshots remain unchanged  |
| Enter a manual document line without a catalog entry                | Yes     | Yes     | Yes      | Member does not need product-management permission          |

## 7. Quotes

| Action                                                                      | Owner   | Admin   | Member  | Notes                                                                      |
| --------------------------------------------------------------------------- | ------- | ------- | ------- | -------------------------------------------------------------------------- |
| Search/view/create a Quote                                                  | Yes     | Yes     | Yes     | New creates an idempotent persisted Draft                                  |
| Edit a Quote in any lifecycle state                                         | Yes     | Yes     | Yes     | State does not reset automatically; stale saves are rejected               |
| Send/resend a Quote and edit per-send content/recipients                    | Yes     | Yes     | Yes     | Accepted/Rejected resend uses the approved warning                         |
| Create, revoke, regenerate, or re-enable a Quote public link                | Yes     | Yes     | Yes     | Public-token rules remain a Phase 8 implementation gate                    |
| Correct stored lifecycle among Draft/Sent/Accepted/Rejected                 | Guarded | Guarded | Guarded | Confirmation, required reason, and audit; Expired remains derived          |
| Convert an Accepted Quote to a Draft Invoice                                | Yes     | Yes     | Yes     | Normal commercial path                                                     |
| Convert a Draft, Sent, or Expired Quote to a Draft Invoice                  | Guarded | Guarded | No      | **Already approved** intentional override; never convert Rejected directly |
| Unlink an eligible unused Quote-derived Draft Invoice                       | Guarded | Guarded | No      | Approved state/provenance checks, confirmation, and audit                  |
| Edit a Draft Quote number to a non-duplicate value                          | Yes     | Yes     | Yes     | Counter remains unchanged                                                  |
| Confirm a duplicate Quote number or renumber a Sent/Accepted/Rejected Quote | Guarded | Guarded | No      | Warning, reason, and audit; no silent counter change                       |
| Permanently delete a Quote with no linked Invoice                           | Guarded | Guarded | No      | Lifecycle does not block; exposed/decided history strengthens confirmation |

Customer Accept/Reject is outside this membership matrix and follows the approved public-link identity, eligibility, rate-limit, idempotency, and audit rules.

## 8. Invoices

| Action                                                                     | Owner   | Admin   | Member  | Notes                                                                                        |
| -------------------------------------------------------------------------- | ------- | ------- | ------- | -------------------------------------------------------------------------------------------- |
| Search/view/create an independent Draft Invoice                            | Yes     | Yes     | Yes     | Company/customer/default rules apply                                                         |
| Edit a Draft Invoice                                                       | Yes     | Yes     | Yes     | Stale saves are rejected                                                                     |
| Issue or send a Draft Invoice                                              | Yes     | Yes     | Yes     | Issue commits before external delivery                                                       |
| Edit an Issued Invoice                                                     | Yes     | Yes     | Yes     | Complete-ledger, currency, audit, PDF, and reminder rules apply                              |
| Send/resend an Issued Invoice and edit per-send content/recipients         | Yes     | Yes     | Yes     | Delivery safety gates are rechecked immediately before send                                  |
| Create, revoke, regenerate, or re-enable an Invoice public link            | Yes     | Yes     | Yes     | Invoice page remains view/download only                                                      |
| Override Invoice-specific reminder rules                                   | Yes     | Yes     | Yes     | Treated as a document edit; only pending instances are recalculated                          |
| Cancel an eligible Issued Invoice                                          | Guarded | Guarded | Guarded | Net paid must be zero; confirmation, reason, reminder suppression, and audit                 |
| Reopen a Cancelled Invoice                                                 | Guarded | Guarded | Guarded | Returns to Issued under the approved transaction/public/reminder behavior                    |
| Edit a Draft Invoice number to a non-duplicate value                       | Yes     | Yes     | Yes     | Counter remains unchanged                                                                    |
| Confirm a duplicate Invoice number or renumber an Issued/Cancelled Invoice | Guarded | Guarded | No      | Warning, reason, and audit; no silent counter change                                         |
| Permanently delete a transaction-free Invoice                              | Guarded | Guarded | No      | Highest-friction confirmation if ever issued, sent, or shared; transaction rows always block |

The UI treats deletion of an already issued, sent, or publicly shared transaction-free Invoice as the strongest irreversible document action: the operator must type the exact Invoice number and separately acknowledge that deletion is irreversible. Draft deletion uses the ordinary destructive confirmation. An active Quote provenance link always blocks Invoice deletion; a linked Draft must first pass the distinct Owner/Admin-only confirmed, reason-bearing unlink workflow, and a non-Draft linked Invoice cannot be unlinked or deleted. The Phase 7 UI tests both server enforcement and the strong interaction.

## 9. Payments, Refunds, and Adjustments

| Action                                                | Owner   | Admin   | Member  | Notes                                                                                 |
| ----------------------------------------------------- | ------- | ------- | ------- | ------------------------------------------------------------------------------------- |
| Search/view the Company-wide Invoice transaction list | Yes     | Yes     | Yes     | Requires both the named transaction-list ability and Invoice visibility               |
| View Invoice-local transactions and balances          | Yes     | Yes     | Yes     | Governed by the underlying Invoice visibility boundary                                |
| Record a Payment                                      | Yes     | Yes     | Yes     | Issued positive-total Invoice only; complete-ledger validation applies                |
| Send the optional payment-received email              | Yes     | Yes     | Yes     | Never automatic for backfilled payments                                               |
| Record a Refund                                       | Guarded | Guarded | Guarded | Actual refundable cash and net-paid bounds apply                                      |
| Record a positive/negative Adjustment                 | Guarded | Guarded | No      | Required reason and audit; never creates refundable cash                              |
| Edit or delete an existing Payment or Refund          | Guarded | Guarded | Guarded | Warning, full aggregate revalidation, and audit; delivered receipts remain historical |
| Edit or delete an existing Adjustment                 | Guarded | Guarded | No      | Adjustment creation and all later mutation remain entirely Owner/Admin-only           |

Invumo records financial facts but does not move money. These permissions do not authorize a bank/card refund outside Invumo.

A Member may therefore reach a valid Invoice state where cancellation still requires an Adjustment that the Member cannot perform. Show a clear **Owner/Admin action required** state and preserve the blocked cancellation until an authorized user resolves the adjusted balance. Do not disguise the escalation as a validation failure or suggest exceeding refundable cash.

## 10. Recurring templates and automation

| Action                                                               | Owner   | Admin   | Member | Notes                                                                                 |
| -------------------------------------------------------------------- | ------- | ------- | ------ | ------------------------------------------------------------------------------------- |
| Search/view recurring templates and occurrence history               | Yes     | Yes     | Yes    | Internal template name remains non-customer-visible                                   |
| Create/edit a Draft recurring template                               | Yes     | Yes     | Yes    | Member may prepare but not activate unattended automation                             |
| Duplicate a Completed template into a new Draft                      | Yes     | Yes     | Yes    | Completed remains terminal                                                            |
| Activate a Draft template                                            | Guarded | Guarded | No     | Validate schedule, content, recipients, reminders, and email setting                  |
| Pause/resume or complete an Active template                          | Guarded | Guarded | No     | No implicit pause-period backfill                                                     |
| Edit schedule/customer/currency/lines/delivery on an Active template | Guarded | Guarded | No     | Confirmation; affects future unmaterialized occurrences only                          |
| Enable/disable automatic email on a template                         | Guarded | Guarded | No     | Currency-review latch remains authoritative                                           |
| Archive/delete a recurring template where dependencies permit        | Guarded | Guarded | No     | Preserve completed/failed occurrence history as required by schema                    |
| Retry a failed occurrence or automation action                       | Guarded | Guarded | No     | Same idempotency identity; eligibility rechecked                                      |
| Manually send an already-generated Invoice                           | Yes     | Yes     | Yes    | Uses normal Invoice send permission; provider acceptance may clear the currency latch |

Generated Invoices use the ordinary Invoice cancellation and permanent-deletion permissions and guards. Deleting one also deletes its occurrence without rewinding the template schedule or recreating that period; cancelling one retains the occurrence. Neither action prevents later distinct scheduled occurrences.

Batch 10A resolves the Draft slice through `view_recurring_templates`, `manage_recurring_drafts`, and `delete_recurring_templates`. `manage_recurring_automation` remains a distinct Owner/Admin-only ability for the later activation, scheduling, and execution batches; Draft access never implies unattended-automation authority.

## 11. Email, reminders, public access, and operational history

| Action                                                 | Owner   | Admin   | Member | Notes                                                                      |
| ------------------------------------------------------ | ------- | ------- | ------ | -------------------------------------------------------------------------- |
| View document delivery/status/reminder history         | Yes     | Yes     | Yes    | Ordinary document-local operational history, not the full audit log        |
| Retry a failed direct Quote/Invoice email              | Yes     | Yes     | Yes    | Same authorization as sending the document; create a new immutable attempt |
| Retry a failed automated reminder                      | Guarded | Guarded | No     | Recheck balance, lifecycle, due date, recipient, link, and idempotency     |
| View Company-wide automation failures/operations       | Yes     | Yes     | No     | Includes recurring/reminder failures requiring intervention                |
| View the full Company audit trail                      | Yes     | Yes     | No     | Member still sees ordinary document status/delivery history                |
| Erase retained public-decision identity for a Customer | Guarded | Guarded | No     | Irreversibly nulls decision name/email while retaining the Quote and event |
| Delete or rewrite audit history                        | No      | No      | No     | Only approved retention/erasure workflows may remove records               |

## 12. System and public actors

- A scheduler/queue worker may execute only its named system action after establishing the Company RLS context and rechecking current eligibility. It cannot invoke an arbitrary Owner/Admin operation.
- A provider webhook may update only the delivery/payment-provider record identified through its authenticated, idempotent mapping.
- A public-link visitor may view only the linked current document while the link is valid. A Quote visitor may Accept/Reject only under the approved public-state rule.
- Public actors never receive membership-list, settings, Customer, product, transaction, recurring-template, or Company-audit access.
- When a User starts an asynchronous action, record that initiating User where applicable; later job execution remains a System actor.

## 13. Enforcement and tests

- Define named abilities once and use them from Laravel Policies/application actions and React visibility helpers. React visibility is convenience only; the server always authorizes again.
- Authorize membership and ability before setting transaction-local tenant context. RLS still fails closed when context is missing or cross-Company.
- Controllers, queued jobs, public endpoints, bulk actions, and exports/imports added later must call the same named application action rather than reimplementing permission checks.
- A policy denial must not reveal whether a record exists in another Company.
- Tests cover every matrix row for Owner, Admin, and Member; cross-Company access; removed/changed memberships; direct URL/action calls hidden by the UI; background/public actor boundaries; and all Guarded state/confirmation paths.
- No future role or permission expansion is inferred from a new screen. It requires an explicit matrix update and owner approval.

## 14. Approval and downstream use

The owner approved the fixed shared-Company role model; the Admin governance/configuration boundary; non-Owner membership administration; Member Customer/document/catalog-use permissions; Member Payment/Refund permissions; Owner/Admin-only Adjustment control; the Draft-versus-Active recurring split; the document exception/deletion boundaries; and communications, operations, and audit visibility on 2026-08-22.

The approved relational schema/snapshot-boundary specification encodes the singular Owner and membership constraints, while Laravel Policies and application actions implement every named ability here.
