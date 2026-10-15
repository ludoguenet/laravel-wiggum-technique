<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== ludoguenet/laravel-standards/enforce-tests rules ===

# Test Enforcement

- Pure copy, styling, and layout-only changes do not require new or updated tests.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== ludoguenet/laravel-standards/engineering-standards rules ===

# Engineering Standards

These standards take precedence over any other guideline in this file when they conflict.

Detailed rationale, examples, anti-patterns and checklists for each section live in the `engineering-standards` skill (start with `references/00-principles.md`). Activate it and read the relevant reference before working in its area. Record consequential decisions in `docs/decisions/` using the skill's `references/decision-template.md`.

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

</laravel-boost-guidelines>
