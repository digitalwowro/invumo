# Invumo Application Architecture Baseline

Status: Approved architecture decision
Last updated: 2026-08-24

This document records the approved technology and application-architecture baseline. It does not track implementation progress or remaining deliverables; those are maintained only in the [Invumo Development Tracker](../development/development-tracker.md).

## Decision

Build Invumo as one modular Laravel application with a React/TypeScript interface connected through Inertia.

| Concern                         | Decision                                                                                                                           |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Backend and application runtime | Laravel 13 on PHP 8.5                                                                                                              |
| Web interface                   | React 19 with strict TypeScript                                                                                                    |
| Laravel/React integration       | Inertia 3                                                                                                                          |
| Project foundation              | Official Laravel React starter kit                                                                                                 |
| Authentication                  | Built-in Laravel Fortify sessions, email verification/recovery, rate limiting, and secure session management                       |
| Type-safe route integration     | Laravel Wayfinder                                                                                                                  |
| Database                        | PostgreSQL 18                                                                                                                      |
| Frontend build                  | Vite                                                                                                                               |
| Styling and components          | Tailwind CSS 4, source-owned shadcn/ui components, and the centralized [Invumo Design System Contract](../design/design-system.md) |
| Localization                    | Laravel language files as the only authored string source and `config/localization.php` as the sole supported-locale allowlist; resolved strings passed to React through Inertia props |
| Package management              | Composer and npm with committed lockfiles                                                                                          |
| Automated testing               | Pest 4, Vitest, and Pest Browser backed by Playwright                                                                              |
| Code quality                    | Laravel Pint, Larastan/PHPStan, strict TypeScript, ESLint, and Prettier                                                            |
| Continuous integration          | GitHub Actions                                                                                                                     |
| Agent development support       | Laravel Boost as a development-only dependency                                                                                     |
| Background work                 | Laravel database queue with one supervised PHP worker                                                                              |
| Scheduling                      | Laravel scheduler invoked once per minute by cron                                                                                  |
| Deployment shape                | One SaaS application deployment and one PostgreSQL database at `app.invumo.com`                                                    |

This choice optimizes total system complexity rather than language count. PHP and TypeScript remain in one repository and one deployable application; they do not create separate backend and frontend services.

## Application boundary

Invumo is a modular monolith. Its approved business modules, physical code layout, cross-module APIs, dependency direction, application-action transaction contract, frontend layers, test ownership, and maintenance rules are defined in the living [Invumo Codebase Map](codebase-map.md).

The internal [Platform Operations](platform-operations.md) back office is another bounded module and React/Inertia shell inside this same application. It uses the same authentication, localization, design system, database, and deployment; it does not introduce a second service, frontend, authentication system, or general tenant-data bypass. After Laravel's shared recent-password window and action throttling authorize the separate password-free mutation, full-action impersonation transitions the existing server-side session to a selected non-operator effective User, clears confirmation state across the identity boundary, preserves the original Platform Owner separately for audit/exit, blocks Platform Operations for the impersonated session, and then uses only the selected User's normal Company authorization and RLS paths.

Module boundaries organize code and tests but do not create network services or separately deployed applications. Create a module directory only when it has real implementation. Keep Laravel's framework entry points conventional where that is clearer, and do not introduce a module package, empty scaffolds, repositories around every model, event sourcing, or premature service interfaces.

Controllers remain thin; Form Requests validate input shape; Policies enforce permissions; purpose-specific Queries own reads; and one named root application Action owns each mutation workflow and its outer database transaction. Business state changes do not live in controllers, React pages, provider adapters, or queued-job handlers. Cross-module mutation goes through the owning module's Action rather than direct model updates.

## Laravel, Inertia, and React responsibilities

Laravel owns:

- Routing, authentication, sessions, CSRF protection, and authorization
- Tenant context and PostgreSQL Row-Level Security integration
- Validation and all authoritative business rules
- Database access, transactions, numbering, calculations, and state changes
- Queues, scheduling, email, webhooks, storage, localization, and PDF generation

React owns:

- Pages, layouts, tables, forms, modals, responsive navigation, and interaction state
- Quote, invoice, and recurring-template editor behavior
- Immediate non-authoritative previews of lines, discounts, taxes, and totals
- Loading, empty, error, success, and unsaved-change states

Inertia is the bridge. Laravel routes and controllers return React page names plus explicitly selected props. Inertia performs navigation and form submissions without a full page reload. The web application does not require a separate REST API, client-side router, authentication system, deployment, or server-state cache.

Laravel remains authoritative. React may calculate a preview, but Laravel recalculates and validates all monetary results before persistence.

Use React/Inertia for the authenticated application and customer-facing public pages. Use Blade only for the minimal Inertia root, transactional-email markup, and dedicated PDF templates. Do not mix Livewire into the interactive application.

## Project bootstrap profile

Create the application from Laravel's official React starter kit and retain its Inertia 3, React 19, strict TypeScript, Tailwind CSS 4, source-owned shadcn/ui, Vite, and Wayfinder foundation.

Use the starter kit's built-in Fortify authentication. Keep registration, email verification, sign-in/out, password reset, password confirmation, secure session management, and rate limiting. Foundational verification and recovery notifications live in `Identity`, use Laravel-authored locale copy, queue only after commit with encrypted payloads and bounded retries, and recheck link validity at delivery rather than only at dispatch. Do not select WorkOS AuthKit and do not enable the starter kit's Teams domain: Invumo owns its Account, Company, Membership, invitation, company-switching, and ownership-transfer model. TOTP two-factor authentication and recovery codes are explicitly deferred from v1.

Use Composer for PHP and npm for browser dependencies. Commit both lockfiles. Do not introduce Bun, pnpm, Yarn, or a second package manager without a demonstrated build or operational need.

Laravel is the only authored translation source. Store Invumo translations under `lang/en` and `lang/ro`; use the same Laravel localization system for validation, system messages, transactional email, PDFs, and strings resolved for React. Do not install `react-i18next`, maintain client-authored catalogs, or add a catalog-generation build step.

Pass only a small, namespaced common translation bag through `HandleInertiaRequests::share()` and pass page-specific bags as ordinary Inertia page props, preferably through reusable typed translation-bag classes rather than repeated controller arrays. Never send the complete catalog with every response. React reads resolved strings through a small typed accessor/interpolation helper. For rare client-live plurals, Laravel supplies the required variants and React selects them with `Intl.PluralRules`; do not create an independent client translation map. Changing the application language may perform an Inertia request.

English and Romanian exist from the first implemented phase; user-entered content is never automatically translated. Automated tests verify locale-key parity, required page/common bags, placeholder behavior, and representative Romanian plural selection.

Use Laravel Boost as a development-only Composer dependency. Its installed-version documentation, agent guidelines, and skills support vibe coding, but it is not application runtime infrastructure and must not override Invumo's approved architecture, product rules, or repository instructions. Keep Invumo-specific durable agent rules in source control.

## State and dependency rules

- Start with normal React state and `useReducer` for complex editors.
- Do not add Redux or another global state library without a demonstrated need.
- Treat Inertia props as server-provided page data; keep draft interaction state local to the editor.
- Keep sensitive or unnecessary fields out of Inertia props because all props reach the browser.
- Keep ordinary User/Company props free of platform authority and control-plane data; expose only a bounded Platform Operations destination/ability to a currently authorized operator.
- Prefer source-owned shadcn/ui components over a runtime UI-framework dependency.
- Keep raw internal colour values in the single approved semantic-token definition. Feature/page code consumes token-backed shared components and must not create page-specific visual treatments or hard-coded colours.
- Treat pages as composition and data-binding boundaries: source-owned shadcn primitives feed shared Invumo application/domain components, and those components own appearance, state presentation, responsive behavior, and accessibility.
- Keep PHP application services independent of Inertia so future interfaces can reuse them.
- Coordinate the initial data required by a page in Laravel and send one deliberate Inertia prop payload; do not create client-side request fan-out for data already known at navigation time.
- Use direct React/component imports rather than barrel files, and preserve the `ui → app → domain → features → pages/layouts` dependency direction defined by the codebase map.

## Engineering changes and external integration safety

The following rules apply across PHP, React, queued work, tests, operations, and documentation:

1. **External side effects are explicit.** A provider or external-system write may occur only from an explicit user action or an approved scheduled/system workflow. Rendering, reads, polling, previews, static checks, tests, and browser automation must not send email, mutate provider state, create external records, or change production data unless the owner explicitly authorizes that exact write-capable verification workflow.
2. **Providers remain behind narrow adapters.** Provider-specific authentication, requests, responses, identifiers, statuses, errors, and retry details stay inside the relevant integration adapter. Core actions and domain code consume Invumo-owned commands and normalized concepts rather than ZeptoMail or another vendor's labels. Add an adapter at a real external boundary; do not create interfaces around ordinary internal Laravel code merely for uniformity.
3. **Environment variables are an application interface.** Every new or changed variable defines its purpose, requirement/default, consumer, validation, and operational effect. Update Laravel configuration, validation, the root `.env.example`, operations documentation, and relevant tests together. Application/domain code reads `config()` values; direct `env()` access remains inside Laravel configuration files. Origins, endpoints, ports, credentials, and provider settings are never hard-coded in application code.
4. **Logs are allowlisted and privacy-safe.** Logs and diagnostics never contain credentials, tokens, cookies, customer document contents, email bodies, recipient lists, full provider payloads, or sensitive identifiers. Store only the minimum structured, redacted metadata required to operate and diagnose the system; approved audit and provider-event retention rules remain authoritative.
   Audit before/after data follows the same principle through explicit action-specific field allowlists. Callers own the semantic safety of selected values; the shared guard is only a secondary check for secret-shaped keys and unmistakable private-key or complete Authorization credential formats, not a promise to detect arbitrary secrets hidden in ordinary prose.
5. **Material changes receive an impact review.** Before implementing a change to schema, stored data, permissions, an external boundary, or public behavior, identify migration, backward-compatibility, and rollout consequences. Complete the affected contract/types, validation, server action, UI behavior, tests, operations, and documentation as one coherent change rather than leaving layers inconsistent.
6. **Edit authority is not publication authority.** Authorization to inspect or modify files does not authorize committing, pushing, merging, deploying, releasing, sending communications, creating external records, or mutating production data. Each applicable external action requires its own explicit request.
7. **Integration evidence is labelled accurately.** Integration notes distinguish facts guaranteed by current primary provider documentation, Invumo implementation choices, behavior observed in a real test, and conclusions inferred from tests or experiments. Observations and inferences must not be presented as provider guarantees.

The Phase 1 implementation enforces these cross-cutting boundaries rather than leaving them as conventions. A dedicated production-configuration command rejects unsafe configuration by key without including secret values and is part of the pre-traffic runtime verification gate. The same contract participates in the public health diagnosis alongside verification of the restricted PostgreSQL runtime identity and absence of inherited tenant context. It is deliberately not executed from service-provider boot, so health and repair/inspection commands remain available when configuration is unsafe. Every web response receives a server-generated UUID correlation header, and application operational events use one namespaced, allowlisted logger that accepts only bounded machine metadata. The module guard rejects direct application logging outside that boundary. Laravel-authored English/Romanian validation catalogues and one shared localized Inertia error response keep validation failures and 403/404/500/503 pages consistent without exposing internal exception messages.

## Data and integrity foundations

PostgreSQL is authoritative. Use exact decimal arithmetic and never binary floating point for money. Important multi-record operations use explicit database transactions, constraints, and idempotency keys.

All domain entities use native PostgreSQL UUIDv7 primary keys, and all domain foreign keys, including `company_id`, use the native `uuid` type. Framework infrastructure tables and identity-free pure joins retain the narrow exceptions defined in the identifier policy.

The following approved specifications are part of this baseline:

- [Tenant isolation and Row-Level Security](tenant-isolation.md)
- [Calculation, decimal precision, and rounding](calculation-and-rounding.md)
- [Domain identifier policy](identifier-policy.md)
- [Document numbering and concurrency](numbering-and-concurrency.md)
- [Scheduling, recurrence, reminders, and downtime](scheduling-and-jobs.md)
- [Quote, Invoice, and financial state](document-and-financial-state.md)
- [Owner, Admin, and Member permissions](role-permission-matrix.md)

The later relational-model document may refine table and column names but must preserve these behaviors.

## Background work

A queue is justified because PDF creation, ZeptoMail requests, webhooks, reminders, and recurring generation must be retryable and must not hold open browser requests.

Use Laravel's PostgreSQL-backed database queue and one `systemd --user`-supervised PHP worker owned by the unprivileged `invumo` operating-system account. Enabled systemd lingering starts and supervises that user service across logout and server restart without a root-owned application unit. Invoke Laravel's scheduler once per minute through the `invumo` user's crontab, with overlap protection and output sent to the system journal. This is part of the same application and server, not separate worker infrastructure.

Tenant business jobs extend one shared encrypted, queue-unique contract. Each carries a validated Company ID, a stable machine idempotency key, and a non-sensitive component label; holds its duplicate-dispatch lock for up to seven days unless processing releases it earlier; uses one initial attempt plus retries after 1 minute, 5 minutes, 15 minutes, 1 hour, and 6 hours; and emits only allowlisted started/succeeded/skipped/retrying/failed operational metadata. The execution boundary starts and ends with no active tenant transaction, binds every short RLS context entered by the job to the job's Company, rejects attempts to switch Company, and verifies cleanup before the worker accepts later work. Provider calls occur only after the short tenant transaction has closed. Queue uniqueness is dispatch suppression, not a substitute for business-state rechecks and database effect constraints. A provider accepting a request immediately before a worker crash remains an integration-level ambiguity; later delivery integrations must use provider idempotency/event reconciliation where available rather than claiming the queue alone provides exactly-once external effects.

The database queue and the dedicated tenant-job lock store use the same restricted `pgsql` connection as the business transaction. When work is already known during a mutation, inserting its queue row and uniqueness lock inside that transaction makes all three changes visible together at commit and removes all three on rollback. The queue visibility timeout is 120 seconds and must remain greater than the worker's 90-second execution timeout. Future cross-Company recurrence/reminder discovery continues to use the separately specified payload-free `job_dispatches` outbox and narrow dispatcher role; Phase 1 does not create either prematurely.

Do not add Redis, RabbitMQ, Kafka, Horizon, or a separate message-broker service initially. Reassess only when observed throughput, latency, or operational needs exceed the database queue.

## PDFs and files

Generate v1 PDFs from dedicated Blade templates using a pure-PHP renderer behind an internal PDF-renderer boundary. Before committing to the renderer, prove Romanian diacritics, embedded fonts, long tables, line wrapping, multi-page page breaks, logos, and the approved brand color.

If that proof fails, replace only the renderer with a headless-browser implementation; do not move invoice rendering or calculations into a Node service.

Use Laravel's filesystem abstraction. Local production storage is acceptable initially only when uploaded and generated files are covered by the externally managed off-server backup and restore process. This preserves a later move to S3-compatible storage without changing domain code. Company-logo validation, private serving, replacement, cleanup, and the local-to-S3 transition follow the approved [`uploads-and-storage.md`](uploads-and-storage.md) contract.

## Development and deployment

The initial hosted footprint is one production environment on the owner's infrastructure at `https://app.invumo.com`. Until public launch, while the application has no real users, development may occur directly in the hosted production checkout. This is an explicitly temporary operating mode, not the intended post-launch workflow. Changes still remain source-controlled and must pass the relevant automated checks before they are treated as complete.

The public marketing website will use `https://invumo.com` and is outside this Laravel application's route tree, codebase, deployment, and product scope. Its approved implementation boundary is the private [`digitalwowro/invumo-web`](https://github.com/digitalwowro/invumo-web) repository and `/home/invumo/invumo-web` working directory. The repository and directory exist; the empty local directory may be connected to the remote when marketing-site implementation begins. The SaaS application remains in the `invumo` repository and `/home/invumo/invumo` checkout.

Initial production processes:

```text
Nginx
PHP-FPM
Laravel application
PostgreSQL
One Laravel queue worker
One cron-triggered Laravel scheduler
```

The worker is a linger-backed `invumo` user service, and the scheduler belongs to the `invumo` user's crontab. Neither application process runs as root. Root remains limited to operating-system, Nginx, PHP-FPM, and PostgreSQL administration.

Node is used locally and during deployment to build the React/TypeScript/CSS assets. No Node web server remains running in production. Inertia server-side rendering is disabled.

Docker, Kubernetes, a separate frontend deployment, and a separate web API are not part of v1. Repeatable application deployment automation is deliberately deferred during the temporary pre-launch, no-user direct-production period. Environment secrets remain outside Git, database changes remain migration-driven, and the application retains its health endpoint and supervised worker/scheduler even during this period.

Rollback, database/file backup and restore, uptime/error monitoring, and alert delivery are managed externally to the application repository. Invumo documentation records this ownership boundary and the release gate verifies that the external safeguards are active; it does not duplicate the owner's infrastructure tooling.

Before real users depend on the service, introduce separate development and production environments and a repeatable release process. Reassess whether a staging environment is justified at that boundary rather than adding it automatically.

## Quality guardrails for agent-written code

Vibe coding increases the value of automated boundaries. The baseline requires:

- Laravel Pint formatting and Larastan/PHPStan static analysis
- Strict TypeScript checking, ESLint, and Prettier
- Pest 4 for PHP unit, feature, domain, authorization, restricted-role RLS, database, and integration tests
- Vitest for isolated TypeScript calculation and React behavior tests
- Pest Browser, using Playwright, for critical full-browser customer and administrative journeys
- Byte-level visual references owned by the pinned GitHub Ubuntu runner, with comparison artifacts retained on failure; non-canonical environments still run the browser behavior, accessibility, JavaScript, typography-selection, and responsive-state assertions
- Hash-bound baseline-review evidence that names the protected screens, causal code change, intended visual differences, and inspected runner artifact; CI rejects a snapshot update without matching evidence
- Database constraints and migrations reviewed as product behavior
- GitHub Actions checks that run tests, analysis, formatting/linting checks, and the production asset build before deployment
- Design-contract guards that reject raw colours and major component-layer bypasses in feature/page code, plus a development/test component gallery and representative visual-regression coverage for the shared system
- A source-file size guard that warns above the 300-line soft limit and fails above the 500-line hard limit
- A module-boundary guard that rejects prohibited backend cross-module/concrete-integration imports and inverted frontend layer dependencies

Tests must use the restricted PostgreSQL runtime role where RLS behavior matters. Generated code is not accepted solely because it renders or passes a happy-path browser check.

### Source-file size and responsibility contract

The limits count physical lines and apply to handwritten/source-owned PHP, TypeScript, React, JavaScript, tests, and stylesheets—including source-owned shadcn/ui primitives. A file with 301–500 lines produces a visible warning and requires a refactor review; more than 500 lines fails CI and is prohibited for new or modified source. The target remains at or below 300 lines whenever a coherent separation is available.

Generated code, lockfiles, third-party dependencies, compiled assets, documentation, and authored translation catalogs are excluded. An unusually cohesive migration or configuration file may exceed the hard limit only after the owner explicitly approves an exact-path exception with a recorded reason. Convenience, framework origin, or the cost of splitting is not sufficient justification.

Files should have one clear responsibility. Pages compose shared components rather than accumulating complete screens in one file; controllers remain thin while application actions own workflows and transaction boundaries; React editors split along meaningful interface/behavior regions; and tests split by behavior or scenario. The guard supplements architectural review—it does not make a fragmented collection of arbitrary small files desirable.

## Future mobile application

Do not build a mobile API, PWA, offline synchronization, push notifications, or a mobile repository in v1. Keep web controllers thin and business operations in reusable PHP application actions.

If a mobile application is approved later, add versioned Laravel JSON endpoints and Laravel Sanctum authentication around the same application services. React/TypeScript knowledge transfers to a possible React Native or Expo application, but web components are not assumed to be directly reusable.

## Deliberate initial exclusions

- Livewire
- Next.js or a separately deployed React application
- WorkOS AuthKit and the Laravel starter-kit Teams domain
- A REST or GraphQL API for the web interface
- Inertia server-side rendering
- Microservices
- Docker and Kubernetes
- Redis and external message brokers
- WebSockets unless a later feature proves a need
- Elasticsearch or a separate search cluster
- Event sourcing
- Separate tenant databases
- Native mobile or PWA functionality

## References

- [Laravel 13 release and support policy](https://laravel.com/docs/13.x/releases)
- [Laravel React starter kit](https://laravel.com/docs/13.x/starter-kits)
- [Laravel Boost](https://laravel.com/docs/13.x/boost)
- [Inertia request model](https://inertiajs.com/docs/v3/core-concepts/how-it-works)
- [Pest browser testing](https://pestphp.com/docs/browser-testing)
- [Laravel queues](https://laravel.com/docs/13.x/queues)
- [Laravel scheduler](https://laravel.com/docs/13.x/scheduling)
- [PostgreSQL support policy](https://www.postgresql.org/support/versioning/)
