# Invumo Application Architecture Baseline

Status: Approved architecture decision
Last updated: 2026-08-23

This document records the approved technology and application-architecture baseline. It does not track implementation progress or remaining deliverables; those are maintained only in the [Invumo Development Tracker](../development/development-tracker.md).

## Decision

Build Invumo as one modular Laravel application with a React/TypeScript interface connected through Inertia.

| Concern | Decision |
| --- | --- |
| Backend and application runtime | Laravel 13 on PHP 8.5 |
| Web interface | React 19 with strict TypeScript |
| Laravel/React integration | Inertia 3 |
| Project foundation | Official Laravel React starter kit |
| Authentication | Built-in Laravel Fortify sessions, email verification/recovery, rate limiting, and secure session management |
| Type-safe route integration | Laravel Wayfinder |
| Database | PostgreSQL 18 |
| Frontend build | Vite |
| Styling and components | Tailwind CSS 4, source-owned shadcn/ui components, and the centralized [Invumo Design System Contract](../design/design-system.md) |
| Localization | Laravel `lang/en` and `lang/ro` files as the only authored source; resolved strings passed to React through Inertia props |
| Package management | Composer and npm with committed lockfiles |
| Automated testing | Pest 4, Vitest, and Pest Browser backed by Playwright |
| Code quality | Laravel Pint, Larastan/PHPStan, strict TypeScript, ESLint, and Prettier |
| Continuous integration | GitHub Actions |
| Agent development support | Laravel Boost as a development-only dependency |
| Background work | Laravel database queue with one supervised PHP worker |
| Scheduling | Laravel scheduler invoked once per minute by cron |
| Deployment shape | One application deployment and one PostgreSQL database |

This choice optimizes total system complexity rather than language count. PHP and TypeScript remain in one repository and one deployable application; they do not create separate backend and frontend services.

## Application boundary

Invumo is a modular monolith. Initial modules follow the product boundaries:

- Identity, accounts, authentication, and entitlements
- Companies, memberships, invitations, and ownership transfer
- Company configuration
- Customers and contacts
- Products & Services
- Shared documents and calculations
- Quotes
- Invoices
- Payments, refunds, and adjustments
- Public documents
- Email and reminders
- Recurring invoices
- Audit and operational history

Module boundaries organize code and tests but do not create network services or separately deployed applications.

Use conventional Laravel structure with pragmatic application actions/services for important use cases. Controllers remain thin; Form Requests validate input; Policies enforce permissions; application actions own workflows and transaction boundaries; Eloquent models represent persistence. Do not introduce an academic domain framework, repositories around every model, event sourcing, or premature service interfaces.

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

Use the starter kit's built-in Fortify authentication. Keep registration, email verification, sign-in/out, password reset, password confirmation, secure session management, and rate limiting. Do not select WorkOS AuthKit and do not enable the starter kit's Teams domain: Invumo owns its Account, Company, Membership, invitation, company-switching, and ownership-transfer model. TOTP two-factor authentication and recovery codes are explicitly deferred from v1.

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
- Prefer source-owned shadcn/ui components over a runtime UI-framework dependency.
- Keep raw internal colour values in the single approved semantic-token definition. Feature/page code consumes token-backed shared components and must not create page-specific visual treatments or hard-coded colours.
- Treat pages as composition and data-binding boundaries: source-owned shadcn primitives feed shared Invumo application/domain components, and those components own appearance, state presentation, responsive behavior, and accessibility.
- Keep PHP application services independent of Inertia so future interfaces can reuse them.

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

Do not add Redis, RabbitMQ, Kafka, Horizon, or a separate message-broker service initially. Reassess only when observed throughput, latency, or operational needs exceed the database queue.

## PDFs and files

Generate v1 PDFs from dedicated Blade templates using a pure-PHP renderer behind an internal PDF-renderer boundary. Before committing to the renderer, prove Romanian diacritics, embedded fonts, long tables, line wrapping, multi-page page breaks, logos, and the approved brand color.

If that proof fails, replace only the renderer with a headless-browser implementation; do not move invoice rendering or calculations into a Node service.

Use Laravel's filesystem abstraction. Local production storage is acceptable initially only when uploaded and generated files are covered by the externally managed off-server backup and restore process. This preserves a later move to S3-compatible storage without changing domain code.

## Development and deployment

The initial hosted footprint is one production environment on the owner's infrastructure. Until public launch, while the application has no real users, development may occur directly in the hosted production checkout. This is an explicitly temporary operating mode, not the intended post-launch workflow. Changes still remain source-controlled and must pass the relevant automated checks before they are treated as complete.

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
- Database constraints and migrations reviewed as product behavior
- GitHub Actions checks that run tests, analysis, formatting/linting checks, and the production asset build before deployment
- Design-contract guards that reject raw colours and major component-layer bypasses in feature/page code, plus a development/test component gallery and representative visual-regression coverage for the shared system

Tests must use the restricted PostgreSQL runtime role where RLS behavior matters. Generated code is not accepted solely because it renders or passes a happy-path browser check.

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
