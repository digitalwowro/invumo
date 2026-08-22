# Invumo

Invumo is a streamlined, multi-tenant quotation and invoicing SaaS.

> Anything that does not help the user create, send, manage, or get paid for an invoice probably should not exist.

This repository contains the formal product documentation, application code, tests, and deployment assets. Application implementation has not started yet; the project is currently in architecture and planning.

## Documentation

- [Documentation index](docs/README.md)
- [Master build brief](docs/product/master-build-brief.md)
- [Core domain rules](docs/product/domain-rules.md)

Durable cross-session context for agents is stored separately in [`digitalwowro/invumo-agents`](https://github.com/digitalwowro/invumo-agents).

## Current phase

The next deliverable is an architecture package covering requirements risks, stack recommendation, data model, tenant and permission model, routes/navigation, exact calculation rules, implementation phases, and verification strategy.

PostgreSQL is required from day one. No other application stack has been approved yet.

