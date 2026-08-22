# Invumo Application Architecture Baseline

Status: Approved architecture decision  
Last updated: 2026-08-22

This document records the approved technology and application-architecture baseline. It does not replace the remaining architecture package: the complete relational model, permission matrix, route map, document/payment-state specification, integration design, and operations/recovery plan still require review before broad implementation.

## Decision

Build Invumo as one modular Laravel application with a React/TypeScript interface connected through Inertia.

| Concern | Approved choice |
| --- | --- |
| Backend and application runtime | Laravel 13 on PHP 8.5 |
| Web interface | React 19 with strict TypeScript |
| Laravel/React integration | Inertia 3 |
| Database | PostgreSQL 18 |
| Frontend build | Vite |
| Styling and components | Tailwind CSS 4 and source-owned shadcn/ui components |
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

## State and dependency rules

- Start with normal React state and `useReducer` for complex editors.
- Do not add Redux or another global state library without a demonstrated need.
- Treat Inertia props as server-provided page data; keep draft interaction state local to the editor.
- Keep sensitive or unnecessary fields out of Inertia props because all props reach the browser.
- Prefer source-owned shadcn/ui components over a runtime UI-framework dependency.
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

The later relational-model document may refine table and column names but must preserve these behaviors.

## Background work

A queue is justified because PDF creation, ZeptoMail requests, webhooks, reminders, and recurring generation must be retryable and must not hold open browser requests.

Use Laravel's PostgreSQL-backed database queue and one `systemd`-supervised PHP worker initially. Use Laravel's scheduler through one cron entry. This is part of the same application and server, not separate worker infrastructure.

Do not add Redis, RabbitMQ, Kafka, Horizon, or a separate message-broker service initially. Reassess only when observed throughput, latency, or operational needs exceed the database queue.

## PDFs and files

Generate v1 PDFs from dedicated Blade templates using a pure-PHP renderer behind an internal PDF-renderer boundary. Before committing to the renderer, prove Romanian diacritics, embedded fonts, long tables, line wrapping, multi-page page breaks, logos, and the approved brand color.

If that proof fails, replace only the renderer with a headless-browser implementation; do not move invoice rendering or calculations into a Node service.

Use Laravel's filesystem abstraction. Local production storage is acceptable initially only when uploaded and generated files are included in automated off-server backups. This preserves a later move to S3-compatible storage without changing domain code.

## Development and deployment

The initial hosted footprint is one production environment on the owner's infrastructure. Development still occurs locally; source code is never edited directly on the production server.

Initial production processes:

```text
Nginx
PHP-FPM
Laravel application
PostgreSQL
One Laravel queue worker
One cron-triggered Laravel scheduler
```

Node is used locally and during deployment to build the React/TypeScript/CSS assets. No Node web server remains running in production. Inertia server-side rendering is disabled.

Docker, Kubernetes, a separate frontend deployment, and a separate web API are not part of v1. Deployment must nevertheless be scripted and repeatable, with environment secrets outside Git, database migrations, health checks, automated off-server backups, and a tested rollback path.

Only one hosted environment is needed before launch. A separate hosted development/staging environment is added when real users make direct production deployment unsafe.

## Quality guardrails for agent-written code

Vibe coding increases the value of automated boundaries. The baseline requires:

- PHP formatting and static analysis
- Strict TypeScript checking and frontend linting
- Database constraints and migrations reviewed as product behavior
- Backend feature tests for domain rules and tenant isolation
- Focused frontend tests for complex editor behavior
- Browser tests for critical customer journeys
- A continuous-integration check that runs tests, analysis, and the production asset build before deployment

Tests must use the restricted PostgreSQL runtime role where RLS behavior matters. Generated code is not accepted solely because it renders or passes a happy-path browser check.

## Future mobile application

Do not build a mobile API, PWA, offline synchronization, push notifications, or a mobile repository in v1. Keep web controllers thin and business operations in reusable PHP application actions.

If a mobile application is approved later, add versioned Laravel JSON endpoints and Laravel Sanctum authentication around the same application services. React/TypeScript knowledge transfers to a possible React Native or Expo application, but web components are not assumed to be directly reusable.

## Deliberate initial exclusions

- Livewire
- Next.js or a separately deployed React application
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
- [Inertia request model](https://inertiajs.com/docs/v3/core-concepts/how-it-works)
- [Laravel queues](https://laravel.com/docs/13.x/queues)
- [Laravel scheduler](https://laravel.com/docs/13.x/scheduling)
- [PostgreSQL support policy](https://www.postgresql.org/support/versioning/)
