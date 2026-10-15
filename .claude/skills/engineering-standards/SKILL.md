---
name: engineering-standards
description: Detailed rationale, examples, anti-patterns and checklists behind the engineering standards guideline. Use before designing or reviewing work in an area the standards cover (architecture, domain modules, Laravel backend, database and concurrency, API contracts, Vue/React frontend, testing, security, performance, CI/CD and observability, AI-assisted development) or when recording an architecture decision.
---

# Engineering Standards

The `engineering-standards` guideline in `CLAUDE.md` states *what* to do. These references explain *why*, *when*, *when not*, and show what good and bad look like. Read only the reference that matches the task.

## References

| Area | Reference |
| --- | --- |
| Principles and index | [references/00-principles.md](references/00-principles.md) |
| Architecture, layers, modular monolith | [references/01-architecture.md](references/01-architecture.md) |
| Domain modules, contracts, interchangeable providers | [references/02-domain-modules.md](references/02-domain-modules.md) |
| Form Requests, Policies, Resources, Actions, Jobs | [references/03-laravel-backend.md](references/03-laravel-backend.md) |
| Constraints, transactions, locks, idempotency, migrations | [references/04-database-and-concurrency.md](references/04-database-and-concurrency.md) |
| Validation, response shapes, errors, pagination, versioning | [references/05-api-contracts.md](references/05-api-contracts.md) |
| Vue and React state, async states, UI testing | [references/06-frontend-vue-react.md](references/06-frontend-vue-react.md) |
| Test strategy, Pest, fakes, contract tests | [references/07-testing.md](references/07-testing.md) |
| Authorization, sensitive data, vulnerabilities | [references/08-security.md](references/08-security.md) |
| Measurement, queries, caching, queues, bundle cost | [references/09-performance.md](references/09-performance.md) |
| Quality gates, deployments, logging, rollback | [references/10-ci-cd-observability.md](references/10-ci-cd-observability.md) |
| Coding agents and AI features | [references/11-ai-assisted-development.md](references/11-ai-assisted-development.md) |
| Architecture Decision Records | [references/decisions.md](references/decisions.md) |

## Recording a decision

Consequential decisions live in the project, not in this skill. Copy [references/decision-template.md](references/decision-template.md) to `docs/decisions/NNNN-short-title.md` (create the directory if missing) and follow [references/decisions.md](references/decisions.md).
