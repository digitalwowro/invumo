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

The project has approved its Laravel/Inertia/React/PostgreSQL application baseline together with tenant isolation, UUIDv7 identifiers, decimal precision and rounding, document numbering, and scheduling rules. The next deliverables complete the requirements risks, relational data and snapshot boundaries, permission model, routes and editor composition, document/payment states, integrations, operations, and verification strategy around those constraints.

The relational schema must preserve the approved calculation and identifier policies rather than rediscovering them during migration design.
