# Engineering Standards

These standards take precedence over any other guideline in this file when they conflict.

Detailed rationale, examples, anti-patterns and checklists for each section live in `docs/engineering/` (start with `00-principles.md`). Read the relevant document before working in its area. Record consequential decisions in `docs/engineering/decisions/`.

## 1. Mission

Build software that is correct, secure, maintainable, testable and operationally reliable.

Optimize for long-term clarity and correctness, not merely for the fastest implementation.

AI-generated code is not trusted by default. Understand, review and validate every change before considering it complete.

## 2. Mandatory workflow

Before modifying code:

1. Inspect the relevant existing code, project structure, dependencies and tests.
2. Identify the business requirement, constraints and acceptance criteria.
3. Follow existing conventions unless there is a clear reason to improve them.
4. Identify security, data-integrity, concurrency and backward-compatibility risks.
5. Choose the smallest design that correctly addresses the requirement.

During implementation:

1. Keep changes focused and cohesive.
2. Respect module boundaries and dependency direction.
3. Reuse existing abstractions when appropriate.
4. Avoid unnecessary abstractions, generic frameworks and speculative extensibility.
5. Never silently change business behavior or public API contracts.

Before completion:

1. Add or update meaningful automated tests.
2. Run the relevant tests, static analysis, linters and build when available.
3. Review the final diff for correctness, security and unintended changes.
4. Report the checks actually executed and their results.
5. Explicitly disclose unverified assumptions, skipped checks and remaining risks.

Never claim that a test, build, migration or deployment succeeded unless it was actually verified.

## 3. Architecture

- Organize code around cohesive responsibilities and, where useful, business or functional domains.
- Keep HTTP concerns, application orchestration, domain rules and infrastructure concerns distinct when the complexity justifies those boundaries.
- Controllers should remain thin and should not become containers for all business logic.
- Use Actions, services, policies, requests, resources and jobs when they clarify real responsibilities.
- Define explicit contracts when consumers must remain independent of interchangeable implementations.
- Use dependency injection and the Laravel container to wire implementations.
- Keep dependencies directed toward stable contracts where appropriate.
- Avoid circular dependencies and uncontrolled cross-module access.
- Do not introduce a repository, factory, interface, event bus or additional architectural layer without a concrete justification.
- Prefer a well-structured modular monolith over premature microservices.

## 4. Domain modules

A functional module may contain its contracts, implementations, application actions, integrations, validation adapters, configuration and tests.

Keep related code together. Expose a small, intentional public interface and keep implementation details internal where practical.

Before creating a module, determine whether its cohesion, complexity, variability or reuse justifies a separate boundary.

Do not confuse a functional module with a strict Domain-Driven Design bounded context. Apply DDD patterns only when the business complexity benefits from them.

## 5. Interchangeable implementations

When multiple providers or implementations must be interchangeable:

1. Define the consumer-facing contract.
2. Implement each provider behind that contract.
3. Bind the selected implementation through configuration and dependency injection.
4. Keep provider-specific details inside the provider or its adapter.
5. Define behavior for timeouts, invalid responses and provider failures.
6. Test the contract against each implementation where practical.
7. Use fakes or test doubles for deterministic automated tests.
8. Never introduce an insecure fallback silently.

Examples include CAPTCHA providers, payment gateways, storage backends, notification channels and external API clients.

Do not create interchangeable abstractions merely because alternatives might exist someday.

## 6. Laravel and PHP

- Follow the conventions of the Laravel and PHP versions actually installed in the project.
- Use explicit types, clear names and narrowly defined responsibilities.
- Validate external input and authorize sensitive operations server-side.
- Use Form Requests, Policies, API Resources, Actions and Jobs when they improve clarity.
- Never mass-assign untrusted input indiscriminately.
- Use transactions for database operations that must succeed or fail together.
- Understand Eloquent queries, eager loading, indexes, locks and database constraints.
- Keep secrets in appropriate server-side configuration.
- Do not expose internal exceptions, SQL details or credentials to clients.

## 7. Database integrity and concurrency

- Treat database constraints as an essential layer of data integrity.
- Use foreign keys, unique constraints, suitable checks and indexes where appropriate.
- Do not rely exclusively on application-level checks for invariants vulnerable to concurrent requests.
- Analyze race conditions, duplicate submissions and retry behavior for critical operations.
- Use transactions, atomic updates, locks or idempotency mechanisms according to the actual consistency requirements.
- Keep external network calls outside database transactions when practical.
- Plan schema changes for deployment compatibility and data preservation.
- Verify query performance using actual measurements rather than assumptions.

## 8. API contracts

- Define request validation, authorization, response shapes, status codes and error formats explicitly.
- Expose only intended fields.
- Keep API responses stable unless a deliberate contract change is required.
- Recalculate sensitive values server-side.
- Handle pagination, filtering, authentication and retries consistently.
- Consider backward compatibility when changing public interfaces.

## 9. Vue and React

- Follow the framework actually used by the project.
- Keep a clear source of truth for application state.
- Avoid duplicated derived state.
- Separate UI state from server-owned business state.
- Handle loading, success, empty, error and cancellation or stale-response scenarios.
- Validate data on the server regardless of frontend validation.
- Avoid unnecessary global state, effects, watchers and abstractions.
- Test important user-visible behavior.

## 10. Security

- Enforce authorization on the server for every sensitive operation.
- Prevent unauthorized access to another user's resources.
- Validate inputs and constrain writable fields.
- Consider SQL injection, XSS, CSRF, SSRF, unsafe file uploads, rate limiting and secret exposure where relevant.
- Use least-privilege access for application accounts and deployment credentials.
- Never log passwords, tokens or unnecessary sensitive data.
- Treat external responses and user-provided content as untrusted.
- Fail safely when a security-critical verification cannot be completed.

## 11. Testing

Test behavior and business invariants, not merely implementation details.

Prioritize:

- Critical business rules.
- Authorization and validation.
- Failure paths and transaction integrity.
- Concurrency and idempotency for sensitive operations.
- API contracts and integration boundaries.
- Important frontend interactions.
- Regression tests for fixed bugs.

Use unit, integration, feature and end-to-end tests at appropriate boundaries.

Never weaken or remove a test solely to make an implementation pass. Investigate whether the test or implementation is wrong.

## 12. Quality and maintainability

- Prefer readable, explicit code over cleverness.
- Refactor incrementally and preserve behavior with tests.
- Avoid premature abstraction, unnecessary indirection and speculative generalization.
- Remove dead code when safely within scope.
- Keep changes focused and reviewable.
- Document non-obvious business rules and consequential technical decisions.

## 13. Performance and operations

- Measure before optimizing.
- Inspect database queries, application latency, resource use and frontend bundle costs.
- Introduce caching, queues or additional infrastructure only when justified.
- Provide useful logging and error diagnostics without leaking sensitive data.
- Make deployment, migrations, background jobs and rollback behavior explicit.
- Consider backups and restoration for applications with persistent data.

## 14. Definition of done

A change is complete only when:

- Its requirements and relevant business rules are satisfied.
- Security and data-integrity implications have been considered.
- Appropriate tests have been added or updated.
- Available quality checks have been run, or skipped checks have been disclosed.
- The change has been reviewed for unintended side effects.
- Relevant documentation has been updated.
- Remaining limitations and risks are explicitly stated.
