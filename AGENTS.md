<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

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

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

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

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

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

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

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

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

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

=== mrpunyapal/laravel-auditor/core rules ===

## Laravel Auditor

Laravel Auditor equips an **existing** AI coding agent with an evidence-based Laravel audit methodology. It does not scan the app by itself. Use it when the user asks to audit, review, or assess a Laravel application.

### Relationship to Boost

Laravel Auditor extends Laravel Boost. It does **not** replace Boost's general Laravel context. Continue using Boost's documentation search and general Laravel guidelines. Use Laravel Auditor when asked to audit, review, or assess an existing Laravel application.

### What Laravel Auditor provides

- A structured audit workflow: **Discover** project facts, **Scope** relevant domains, **Investigate** with evidence, **Verify** high-severity findings, and **Report** structured findings.
- Six audit domains: security, performance, architecture, database, testing, and Laravel conventions.
- Stable audit rules with rule IDs (e.g. `AUD-SEC-001`), severity, confidence, evidence requirements, and false-positive considerations.
- Context tools that expose deterministic Laravel facts: project info, routes, models, migrations, database schema, dependencies, configuration, authorization, jobs/events/schedules, tests, and subsystems.
- A finding schema: id, rule ID, title, domain, severity, confidence, status, summary, why-it-matters, evidence, affected resources, recommendation, remediation, verification notes, and optional `metadata.priority` (`p0`–`p3`).

### Installing

```bash
composer require --dev mrpunyapal/laravel-auditor
php artisan boost:install
```

When Boost is not installed, use the package's own installer instead:

```bash
php artisan auditor:install --agents=claude_code
```

Useful commands: `auditor:status`, `auditor:rules` (`--applicable`), `auditor:context`, `auditor:report`, `auditor:ci`, and `auditor:mcp`.

`composer audit` is **on by default**. Test-case listing is **off by default**. Do not treat `composer_audit.available: false` or an empty advisory list as “no vulnerabilities” unless the check actually ran.

### Audit skill

Use the `laravel-audit` skill when asked to audit or review a Laravel application. It contains the full workflow, evidence requirements, and severity/confidence guidance. Domain skills (`laravel-audit-security`, `laravel-audit-performance`, `laravel-audit-architecture`, `laravel-audit-database`, `laravel-audit-testing`, `laravel-audit-conventions`) go deeper once the scope is chosen. Use `laravel-audit-dsa` for a bounded subsystem / data-structure / ownership audit (`auditor:context subsystems`, P0–P3 ranking).

### Key rules

- Evidence first: every meaningful finding cites concrete file paths, lines, routes, or config keys.
- Prefer deterministic project facts over model guesses.
- Distinguish confirmed findings from hypotheses.
- Never claim exploitability without sufficient evidence.
- Read-only by default: never modify application code during an audit.
- Few high-quality findings over noisy volume.

=== mrpunyapal/laravel-auditor/dsa rules ===

## Laravel Auditor DSA

When asked for a DSA, data-structure, ownership, or subsystem audit, use the `laravel-audit-dsa` skill.

- Read-only. Do not implement fixes during the audit.
- Inventory subsystems first (`php artisan auditor:context subsystems`).
- One worker per ownership boundary. At most two findings per subsystem.
- Skip when the local code is already clear.
- Verify every finding yourself before it enters the report.
- Rank P0–P3. Keep P3 small. Set `metadata.priority` to `p0`–`p3` and `metadata.subsystem` to the inventory id.
- Prefer `AUD-DSA-*` rules when they fit. Otherwise use the six core domains.

Do not recommend a new type just to hide existing branching.

=== mrpunyapal/laravel-auditor/findings rules ===

## Laravel Auditor findings

Write structured findings, not a chat-only review. Match `schema/finding.schema.json`.

Required: `id`, `rule_id`, `title`, `domain`, `severity`, `confidence`, `summary`, `why_it_matters`.

Include whenever possible: `status` (`open` for new findings), `evidence`, `affected_resources`, `symbol`, `recommendation`, `remediation`, `verification_notes`, and `metadata.priority` (`p0`–`p3`).

Write JSON to `storage/auditor-findings.json` and render:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```

Preview the packaged example with `php artisan auditor:report --example`.

=== mrpunyapal/laravel-auditor/performance rules ===

## Laravel Auditor performance findings

Performance findings follow the full pipeline: Signal → Context → Behavior → Verification → Impact → Finding. A suspicious pattern is not automatically a performance bug.

### Before reporting

1. Check what else consumes the value. `$users = User::where(...)->get(); $count = $users->count();` with the collection rendered by the view is **correct code** — do not recommend `->count()`. Only materialize-then-reduce with no other consumer is a finding.
2. Verify semantic equivalence for this exact usage: accessors/casts, comparison strictness (Collection `where()` is loose, SQL is not), null handling, custom collection classes, primary/foreign keys for narrowed selects, model events on bulk writes.
3. Describe impact by mechanism — "avoids transferring every matching row into PHP" — never invented multipliers like "10x faster". Cite numbers only from real runtime evidence.
4. Add `metadata.impact` when evidence allows: `resource` (database/memory/network/IO), `mechanism` (what is wasted and what the fix avoids), `amplification` (loop counts, table growth, request frequency).

### Severity discipline

- `high`: query explosion or heavy amplification on hot paths (data-driven per-row queries, unbounded retrieval on public endpoints).
- `medium`: clear unnecessary database work, avoidable materialization, repeated queries, rendering-path queries with real traffic.
- `low`: small inefficiencies on modest data.
- Correct micro-optimizations on tiny datasets are not findings.

### Noise floor

Do not report: reused collections/results, replacements you cannot show equivalent, bounded small datasets, caching without a key/invalidation story, concurrency for dependent requests, or eager loading "just in case" (over-eager loading is itself waste).

Use `php artisan auditor:rules --domain=performance --applicable` to list the rules (`AUD-PER-*`, plus `AUD-LW-003`/`AUD-FIL-003`/`AUD-IN-003` when those packages are installed). The `laravel-audit-performance` skill contains the full methodology and checklist.

</laravel-boost-guidelines>
