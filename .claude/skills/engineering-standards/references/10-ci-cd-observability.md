# CI/CD & Observability

- [Introduction](#introduction)
- [Automated Quality Gates](#quality-gates)
- [The CI Pipeline](#ci-pipeline)
- [Deployments](#deployments)
- [Migration Safety & Rollback](#migration-safety)
- [Structured Logging](#structured-logging)
- [Exception Reporting](#exception-reporting)
- [Health Checks, Metrics & Alerts](#health-checks)
- [Backups & Restore Drills](#backups)

<a name="introduction"></a>

## Introduction

This document expands section 13 ("Performance and operations") and section 14 ("Definition of done") of the engineering standards (the `engineering-standards` guideline in `CLAUDE.md`). It covers how a change is verified before merge, how it reaches production, and how we know it is working once it is there.

Scope: CI quality gates, deployment and migration procedure, logging, exception reporting, health checks, alerting, and backups. Query and rendering performance live in [Performance](09-performance.md). Test design lives in [Testing](07-testing.md). Database constraints and locking live in [Database & Concurrency](04-database-and-concurrency.md). Secret handling lives in [Security](08-security.md).

Related documents: [01-architecture.md](01-architecture.md), [02-domain-modules.md](02-domain-modules.md), [03-laravel-backend.md](03-laravel-backend.md), [04-database-and-concurrency.md](04-database-and-concurrency.md), [05-api-contracts.md](05-api-contracts.md), [06-frontend-vue-react.md](06-frontend-vue-react.md), [07-testing.md](07-testing.md), [08-security.md](08-security.md), [09-performance.md](09-performance.md), [11-ai-assisted-development.md](11-ai-assisted-development.md).

> [!NOTE]
> Project facts at the time of writing: Laravel 13, PHP 8.5, Pest 5 (Paratest installed, so `--parallel` works), Larastan 3 at `level: 1` in `phpstan.neon`, Pint, Vite 8. Tests run on SQLite (`phpunit.xml`). There is no `.github/workflows` directory yet; the pipeline below is the reference to adopt.

<a name="quality-gates"></a>

## Automated Quality Gates

<a name="quality-gates-principle"></a>

### Principle

Every change passes the same machine-checked gates before merge: formatting (Pint), static analysis (Larastan), tests (Pest), dependency advisories (`composer audit`), and a production asset build (`npm run build`). A red gate blocks the merge; nobody "fixes it in the next PR".

<a name="quality-gates-why-it-exists"></a>

### Why It Exists

Without gates, the checks depend on someone remembering to run them. Typical failures: a call to an undefined method that only a rarely-hit path reaches and that Larastan would have flagged; a known-vulnerable package version merged because nobody ran `composer audit`; a deploy that fails with "Unable to locate file in Vite manifest" because the asset build was never tried.

<a name="quality-gates-when-to-apply"></a>

### When To Apply

- On every pull request and on every push to `main`.
- Locally, before you ask for review. Run the narrowest checks first (one test file, `--dirty` Pint), then the full set before pushing.
- When an AI agent produced the change. Agents must report which gates they actually ran (see [AI-assisted development](11-ai-assisted-development.md)).

<a name="quality-gates-when-not-to-apply"></a>

### When Not To Apply

- Do not raise the Larastan level and fix 400 errors inside a feature PR. Raise it in a dedicated PR, using a baseline (`vendor/bin/phpstan analyse --generate-baseline`) if you need to.
- `composer audit` can report an advisory with no fix available that does not affect us, for example a vulnerable code path we never call. Record it in `config.audit.ignore` in `composer.json`, with a reason and a review date. Do not delete the gate.
- Docs-only changes may skip the PHP jobs through path filters. They never skip them on `main`.

<a name="quality-gates-correct-example"></a>

### Correct Example

```shell
vendor/bin/pint --test                     # CI: fail on style drift, change nothing
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php artisan test --parallel --compact
composer audit --no-dev --locked
npm ci && npm run build
```

Locally, agents use `vendor/bin/pint --dirty --format agent`, which fixes the files instead of failing. CI uses `--test` because CI must never change code it is verifying.

<a name="quality-gates-anti-pattern"></a>

### Anti-Pattern

```yaml
- run: vendor/bin/phpstan analyse || true
- run: php artisan test --filter=Smoke
```

`|| true` turns the gate into decoration: the job is green whatever the result. Running only a filtered subset in CI hides regressions in every other file.

<a name="quality-gates-checklist"></a>

### Checklist

- [ ] Pint, Larastan, Pest, `composer audit` and `npm run build` all run in CI and all fail the build on error.
- [ ] No gate is wrapped in `|| true`, `continue-on-error: true` or an equivalent.
- [ ] The Larastan level only goes up, and each increase lands in its own PR.
- [ ] Every ignored audit advisory has a reason and a review date.
- [ ] Branch protection on `main` requires the CI checks to pass.
- [ ] PR descriptions say which gates were run locally and which were skipped.

<a name="ci-pipeline"></a>

## The CI Pipeline

<a name="ci-pipeline-principle"></a>

### Principle

CI runs on the same PHP major/minor and the same database engine family as production. It caches dependencies by lockfile hash and runs independent gates as parallel jobs, so feedback is fast without being lenient.

<a name="ci-pipeline-why-it-exists"></a>

### Why It Exists

If CI runs on a different PHP version or database than production, tests can pass in CI and still fail in production. SQLite accepts things that MySQL or PostgreSQL reject: foreign key behaviour differs, `ALTER TABLE` support is limited, and type coercion is looser. A slow pipeline creates pressure to merge without waiting.

<a name="ci-pipeline-when-to-apply"></a>

### When To Apply

- Any repository that will be deployed.
- Add a database service container as soon as production uses MySQL or PostgreSQL. SQLite in CI stays acceptable only while production also runs SQLite.

<a name="ci-pipeline-when-not-to-apply"></a>

### When Not To Apply

- A throwaway demo branch may run a single job, but it still runs every gate.
- Do not build a matrix across PHP versions for an application. A matrix is for libraries that support several versions. An application supports exactly the version it deploys on.

<a name="ci-pipeline-correct-example"></a>

### Correct Example

```yaml
# .github/workflows/ci.yml (reference, not yet committed)
name: CI

on:
  pull_request:
  push:
    branches: [main]

concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true

jobs:
  php:
    runs-on: ubuntu-latest
    # Uncomment when production runs MySQL; set DB_* env on the test step.
    # services:
    #   mysql:
    #     image: mysql:8.4
    #     env:
    #       MYSQL_DATABASE: testing
    #       MYSQL_ROOT_PASSWORD: password
    #     ports: ['3306:3306']
    #     options: --health-cmd="mysqladmin ping" --health-interval=10s --health-retries=5
    steps:
      - uses: actions/checkout@v5

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: mbstring, pdo_sqlite, intl
          coverage: none

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: ~/.composer/cache/files
          key: composer-${{ hashFiles('composer.lock') }}

      - run: composer install --no-interaction --prefer-dist --no-progress

      - run: cp .env.example .env && php artisan key:generate

      - name: Code style
        run: vendor/bin/pint --test

      - name: Static analysis
        run: vendor/bin/phpstan analyse --memory-limit=1G --no-progress

      - name: Tests
        run: php artisan test --parallel --compact

      - name: Dependency advisories
        run: composer audit --locked

  assets:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: actions/setup-node@v5
        with:
          node-version: 22
          cache: npm
      - run: npm ci
      - run: npm run build
```

Pin each action to a current major version and let Dependabot bump it. `concurrency` cancels superseded runs on the same branch.

<a name="ci-pipeline-anti-pattern"></a>

### Anti-Pattern

```yaml
- uses: shivammathur/setup-php@v2
  with:
    php-version: latest
- run: composer update
- run: npm install
```

`latest` and `composer update` make the build non-reproducible: CI tests packages that are not in `composer.lock` and a PHP version production may not run. `npm install` can rewrite the lockfile. Use `npm ci`.

<a name="ci-pipeline-checklist"></a>

### Checklist

- [ ] The PHP version in CI matches production (`8.5`).
- [ ] Dependencies are installed from lockfiles (`composer install`, `npm ci`), never updated.
- [ ] Caches are keyed on `composer.lock` / `package-lock.json` hashes.
- [ ] The database engine in CI matches production once production is not SQLite.
- [ ] No production secrets are available to `pull_request` workflows.
- [ ] Superseded runs are cancelled through `concurrency`.

<a name="deployments"></a>

## Deployments

<a name="deployments-principle"></a>

### Principle

A deployment is a scripted, repeatable sequence that runs in the same order every time: install locked dependencies without dev packages, build assets, migrate with `--force`, warm caches with `php artisan optimize`, then restart long-running processes. Each step fails loudly, and a failed step stops the deploy.

<a name="deployments-why-it-exists"></a>

### Why It Exists

Manual deploys skip steps. The common incidents:

- Queue workers keep running old code because nobody ran `queue:restart`. They then process jobs serialized by the new code.
- `config:cache` was run while config files call `env()` outside `config/`, and those calls return `null` in production.
- Two servers run `migrate` at the same time.

<a name="deployments-when-to-apply"></a>

### When To Apply

- Every production and staging deploy, whether the host is Laravel Cloud, Forge or your own servers.
- Use maintenance mode (`php artisan down`) only when a change truly cannot run alongside live traffic. One example is a destructive migration that cannot be split into expand/contract steps (see [Migration Safety](#migration-safety)).

<a name="deployments-when-not-to-apply"></a>

### When Not To Apply

- On Laravel Cloud, the platform builds the release and gracefully reloads services. Do not add a manual `reload` or `queue:restart` there; put only build and deploy commands in its configuration.
- Do not put `php artisan down` in every deploy "just in case". Zero-downtime release switching (atomic symlink, or the platform's equivalent) is the default. `down` is a deliberate exception.
- Local and CI environments do not run `optimize`. Cached config hides `.env` changes and confuses debugging.

<a name="deployments-correct-example"></a>

### Correct Example

```shell
set -euo pipefail

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build

php artisan migrate --force --isolated   # --isolated: only one server migrates
php artisan optimize                     # config, events, routes, views

# Atomically switch the "current" symlink to this release here.

php artisan reload                       # or: php artisan queue:restart
```

`reload` terminates long-running services such as queue workers, so a process monitor like Supervisor must restart them. `queue:restart` signals workers through the cache, so the cache store must be shared between servers.

When downtime is unavoidable:

```shell
php artisan down --retry=60 --with-secret
# ...run the unsafe step...
php artisan up
```

<a name="deployments-anti-pattern"></a>

### Anti-Pattern

```shell
git pull
composer install
php artisan migrate
php artisan config:cache
```

Pulling into the live directory serves half-updated code while the pull runs. This version also installs dev dependencies. `migrate` without `--force` stops at the production confirmation prompt, or someone adds `--force` on every server and they race each other. Workers are never restarted.

<a name="deployments-checklist"></a>

### Checklist

- [ ] The deploy script uses `set -e` (or the platform's equivalent) and stops on the first failure.
- [ ] Production installs use `--no-dev` and `--optimize-autoloader`.
- [ ] Migrations run once per release with `--force --isolated`.
- [ ] `php artisan optimize` runs after the code and the `.env` are in place.
- [ ] Queue workers, plus Octane or Reverb if present, are restarted after the switch.
- [ ] `env()` is only called inside `config/` files.
- [ ] Any use of `php artisan down` is justified in the PR description.

<a name="migration-safety"></a>

## Migration Safety & Rollback

<a name="migration-safety-principle"></a>

### Principle

Every migration must be compatible with both the code currently running and the code being deployed. Breaking schema changes follow **expand / migrate data / contract** across separate releases. Once a migration has run in production, the default response to a problem is a **forward fix**, not `migrate:rollback`.

<a name="migration-safety-why-it-exists"></a>

### Why It Exists

During a zero-downtime deploy, the old code serves requests after the new schema exists. Workers may also process old jobs. Renaming or dropping a column in one step therefore produces SQL errors for the whole length of the deploy. A `down()` method that drops a column destroys the data written since the deploy, so a rollback can lose more than the bug did.

<a name="migration-safety-when-to-apply"></a>

### When To Apply

- Renaming or dropping columns or tables, changing types, adding `NOT NULL` to existing columns, or adding unique constraints to populated tables.
- Backfills on large tables. Run them as a chunked job or command, not inside the schema migration.
- Any migration that takes locks on a large or hot table. Test it on a production-sized copy first, and use `migrate --pretend` to review the SQL.

<a name="migration-safety-when-not-to-apply"></a>

### When Not To Apply

- Purely additive changes (a new table, or a new nullable column) are safe in one step.
- Before the first production release, or on a table that has no production rows yet, you may edit the schema in one step. Expand/contract protects live data and live code; when neither exists, it is ceremony.
- Rollback is acceptable when the migration has not yet written or dropped user data, for example a failed deploy caught on staging, or an index that was added and must be removed.

<a name="migration-safety-correct-example"></a>

### Correct Example

Renaming `tickets.body` to `tickets.message` across three releases:

```php
// Release 1 (expand): add the new column; code writes both, reads old.
Schema::table('tickets', function (Blueprint $table): void {
    $table->text('message')->nullable()->after('body');
});

// Release 2: backfill in chunks (command or job), code reads new column.
Ticket::query()->whereNull('message')->chunkById(500, function ($tickets): void {
    foreach ($tickets as $ticket) {
        $ticket->updateQuietly(['message' => $ticket->body]);
    }
});

// Release 3 (contract): once nothing reads `body`, drop it.
Schema::table('tickets', function (Blueprint $table): void {
    $table->dropColumn('body');
});
```

Each release can be deployed and reverted on its own without losing data.

<a name="migration-safety-anti-pattern"></a>

### Anti-Pattern

```php
public function up(): void
{
    Schema::table('tickets', function (Blueprint $table): void {
        $table->renameColumn('body', 'message');
    });
}
```

Old code that is still serving requests during the deploy queries `body` and fails. If the team then "rolls back" by renaming the column again, jobs serialized with the new shape fail instead.

<a name="migration-safety-checklist"></a>

### Checklist

- [ ] The migration works with both the old and the new code running at the same time.
- [ ] Renames, drops and type changes are split into expand and contract releases.
- [ ] Large backfills run outside the migration, chunked with `chunkById`.
- [ ] The SQL was reviewed with `php artisan migrate --pretend` for risky changes.
- [ ] The PR states the rollback plan: a forward fix, or why `migrate:rollback` is safe.
- [ ] Destructive steps run only after a verified backup (see [Backups](#backups)).

<a name="structured-logging"></a>

## Structured Logging

<a name="structured-logging-principle"></a>

### Principle

Logs are structured events with a short constant message and the variable data in the context array. Request-wide identifiers such as trace ID, user ID or tenant go through the `Context` facade (`Illuminate\Support\Facades\Context`). That data is attached to every log entry and carried into queued jobs automatically. Secrets never reach a log.

<a name="structured-logging-why-it-exists"></a>

### Why It Exists

If messages interpolate their data (`"Refund 42 failed for user 7"`), you cannot group or filter them. Without a shared trace ID, nobody can follow one request into the job it dispatched. Logging a full request or a model's `toArray()` puts passwords, API tokens and personal data into a system with weaker access control than the database.

<a name="structured-logging-when-to-apply"></a>

### When To Apply

- At business events worth auditing (a refund issued, an invoice sent), at integration boundaries (an external API failure), and in error paths you handle without rethrowing.
- Add `Context` in a global middleware for request-scoped identifiers, and in commands or scheduled tasks for a run ID.

<a name="structured-logging-when-not-to-apply"></a>

### When Not To Apply

- Do not log the happy path of every method. Volume costs money and buries signal.
- Do not log an exception and then rethrow it. The exception handler already reports it, so you get a duplicate entry. Add context to the exception instead (see [Exception Reporting](#exception-reporting)).
- Values that must travel to jobs but must not be logged belong in `Context::addHidden()`.

<a name="structured-logging-correct-example"></a>

### Correct Example

```php
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class AssignTraceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Context::add('trace_id', $request->header('X-Request-Id') ?? Str::uuid()->toString());
        Context::add('user_id', $request->user()?->id);

        return $next($request);
    }
}

// Elsewhere: constant message, data in context, no secrets.
Log::warning('Refund rejected by provider.', [
    'invoice_id' => $invoice->id,
    'provider_status' => $response->status(),
]);
```

Register the middleware with `$middleware->append(AssignTraceContext::class)` in `bootstrap/app.php`. In production, use a `LOG_STACK` that ships logs to a central store, such as `daily` plus your log driver.

<a name="structured-logging-anti-pattern"></a>

### Anti-Pattern

```php
Log::info("Login attempt for {$request->email}", $request->all());
Log::debug('Calling provider', ['headers' => $client->headers()]);
```

`$request->all()` contains the password. The headers contain the bearer token. Interpolating the email into the message turns every entry into a unique string that cannot be aggregated.

<a name="structured-logging-checklist"></a>

### Checklist

- [ ] Log messages are constant strings and the variable data goes in the context array.
- [ ] A trace ID is added through `Context` and appears on request logs and queued job logs.
- [ ] No `$request->all()`, raw headers, tokens, passwords or full model dumps are logged.
- [ ] Sensitive values that jobs need use `Context::addHidden()`.
- [ ] Production `LOG_LEVEL` is `info` or `warning`, not `debug`.
- [ ] Log retention is defined (for example `LOG_DAILY_DAYS`, or the central store's policy).

<a name="exception-reporting"></a>

## Exception Reporting

<a name="exception-reporting-principle"></a>

### Principle

Unhandled exceptions go to one central reporter, configured in `withExceptions()` in `bootstrap/app.php`, with useful context and controlled noise. Exceptions that are expected (validation, 404, authorization) are not reported. Domain exceptions carry their own `context()`.

<a name="exception-reporting-why-it-exists"></a>

### Why It Exists

Without context, an alert says "RuntimeException" and nothing else. Without throttling, a third-party outage floods the tracker and hides the real regression. Swallowing an exception with an empty `catch` turns a visible failure into silent data corruption.

<a name="exception-reporting-when-to-apply"></a>

### When To Apply

- Configure global context, throttling and `dontReport` once, in `bootstrap/app.php`.
- Add a `context(): array` method to a domain exception whenever its identifiers are needed for diagnosis.
- Use `report($e)` when you handle an exception and continue, but still need to know it happened.

<a name="exception-reporting-when-not-to-apply"></a>

### When Not To Apply

- Do not call `report()` for an outcome the user is expected to hit, such as a declined card or a duplicate submission. Return the outcome; if you need a trend, track it as a metric.
- Do not sample (`Lottery`) exceptions that indicate data integrity problems. Sample only high-volume, low-value noise.

<a name="exception-reporting-correct-example"></a>

### Correct Example

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Context;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->context(fn (): array => [
        'trace_id' => Context::get('trace_id'),
    ]);

    $exceptions->dontReportDuplicates();

    $exceptions->throttle(fn (Throwable $e) => match (true) {
        $e instanceof PaymentProviderUnavailable => Limit::perMinute(30),
        default => Limit::none(),
    });

    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

Context is attached to every report. Provider outages are rate-limited per exception class. Real regressions still reach the tracker.

<a name="exception-reporting-anti-pattern"></a>

### Anti-Pattern

```php
try {
    $this->refunds->issue($invoice);
} catch (\Throwable $e) {
    return back()->with('status', 'Refund queued.');
}
```

The failure is invisible: nothing is reported, and the user is told the refund succeeded when it failed. At minimum, `report($e)` and tell the user the truth.

<a name="exception-reporting-checklist"></a>

### Checklist

- [ ] There are no empty `catch` blocks, and every caught `Throwable` is rethrown, reported, or converted into an explicit outcome.
- [ ] Global exception context includes the trace ID.
- [ ] Domain exceptions expose `context()` with identifiers and never secrets.
- [ ] Noisy third-party failures are throttled with `Limit` or `Lottery`, and integrity failures are not.
- [ ] `APP_DEBUG=false` in production, so stack traces never reach clients.
- [ ] An external tracker or log store receives the reports. Options include Laravel Nightwatch, Sentry and Flare.

<a name="health-checks"></a>

## Health Checks, Metrics & Alerts

<a name="health-checks-principle"></a>

### Principle

Expose a cheap health endpoint, measure the few signals that reflect user pain (error rate, latency, queue backlog, failed jobs, scheduler heartbeat), and alert on **symptoms** that someone must act on, never on raw causes such as CPU.

<a name="health-checks-why-it-exists"></a>

### Why It Exists

The default `/up` route only proves the framework boots. It stays green while the database is unreachable or the queue is ten thousand jobs behind. When alerts fire on noise ("CPU 80%"), people learn to ignore them, and the real outage is found by a customer.

<a name="health-checks-when-to-apply"></a>

### When To Apply

- Use the `/up` route (already configured with `health: '/up'` in `bootstrap/app.php`) for load balancers and uptime monitors.
- Add `DiagnosingHealth` checks for hard dependencies without which the app cannot serve any request, typically the database.
- Schedule `queue:monitor` as soon as the app uses a real queue (`QUEUE_CONNECTION=database` today).

<a name="health-checks-when-not-to-apply"></a>

### When Not To Apply

- Do not check third-party APIs in `/up`. A load balancer would take every server out of rotation because a payment provider is slow. Monitor those dependencies separately.
- Do not build a dashboard that nobody reads. Every alert must have an owner and a runbook line; if it has neither, delete it.
- Pulse, Nightwatch or a third-party APM are options to adopt when the logs no longer answer "what is slow?". They are not prerequisites.

<a name="health-checks-correct-example"></a>

### Correct Example

```php
// AppServiceProvider::boot()
Event::listen(function (DiagnosingHealth $event): void {
    DB::connection()->getPdo(); // throws, so /up returns 500
});

Event::listen(function (QueueBusy $event): void {
    Log::critical('Queue backlog above threshold.', [
        'connection' => $event->connectionName,
        'queue' => $event->queue,
        'size' => $event->size,
    ]);
});
```

```php
// routes/console.php
Schedule::command('queue:monitor database:default --max=100')->everyMinute();
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Alert on the following, each with an owner:

- `/up` non-200 for 2 minutes or more.
- 5xx rate above the baseline.
- p95 latency above the SLO.
- `QueueBusy` logged.
- Failed-jobs count rising.
- Scheduler heartbeat missing.

<a name="health-checks-anti-pattern"></a>

### Anti-Pattern

```php
Route::get('/health', function () {
    Http::get('https://api.stripe.com');
    return ['db' => DB::table('invoices')->count(), 'ok' => true];
});
```

This health route calls a third party, so an outage outside our control fails our health check. It counts a whole table on every probe, and it is public and leaks data volume.

<a name="health-checks-checklist"></a>

### Checklist

- [ ] `/up` is monitored externally and includes the database check through `DiagnosingHealth`.
- [ ] Health checks never call third-party services or run expensive queries.
- [ ] `queue:monitor` is scheduled, and `QueueBusy` produces an alert.
- [ ] Failed jobs are visible (`queue:failed`), pruned, and alerted when they grow.
- [ ] The scheduler has a heartbeat, so a dead cron is detected.
- [ ] Every alert has an owner and a one-line runbook, and none fire on causes alone.

<a name="backups"></a>

## Backups & Restore Drills

<a name="backups-principle"></a>

### Principle

A backup counts only after it has been restored. Persistent data (database, uploaded files) is backed up automatically, encrypted, stored off-site with a defined retention, and **restored on a schedule** into a non-production environment to prove it works.

<a name="backups-why-it-exists"></a>

### Why It Exists

Teams discover during an incident that the dump was empty, excluded a schema, was encrypted with a lost key, or takes nine hours to restore. A destructive migration or a bad `DELETE` without a recent verified backup means permanent data loss.

<a name="backups-when-to-apply"></a>

### When To Apply

- Any environment holding data that cannot be regenerated: production always, staging when it holds client test data.
- Before every destructive migration (contract step, data rewrite), check the age of the latest backup.
- Define an RPO (maximum acceptable data loss) and an RTO (maximum restore time) with the business. These two numbers drive the backup frequency.

<a name="backups-when-not-to-apply"></a>

### When Not To Apply

- Caches, compiled views, queues of idempotent jobs and other regenerable data do not need backups.
- A managed database with point-in-time recovery (Laravel Cloud, RDS) replaces your own dump scripts. It does not replace restore drills, and it does not cover uploaded files on separate storage.

<a name="backups-correct-example"></a>

### Correct Example

```shell
# Quarterly restore drill (staging), recorded in the ops log
LATEST=$(backup-tool latest --env=production)
backup-tool restore "$LATEST" --target=staging-db

php artisan migrate:status --database=restore_check
php artisan tinker --execute 'echo App\Models\Invoice::max("created_at");'
# Record: backup age, restore duration (vs RTO), data freshness (vs RPO).
```

The drill measures the two numbers that matter: how much data the backup would lose, and how long it takes to get it back.

<a name="backups-anti-pattern"></a>

### Anti-Pattern

```shell
# crontab on the app server
0 3 * * * mysqldump app > /var/backups/app.sql
```

The dump is stored on the same server it protects and is overwritten every night, so there is no history. It is unencrypted and has never been restored. Its failure would go unnoticed because nothing checks it.

<a name="backups-checklist"></a>

### Checklist

- [ ] Database and file storage are both backed up automatically and stored off-site.
- [ ] Backups are encrypted, and the key is stored separately from the backups.
- [ ] RPO and RTO are written down and the backup frequency meets them.
- [ ] A restore drill ran in the last quarter, and its duration and data freshness were recorded.
- [ ] A backup failure raises an alert.
- [ ] Destructive migrations are preceded by a check of the latest backup's age.
