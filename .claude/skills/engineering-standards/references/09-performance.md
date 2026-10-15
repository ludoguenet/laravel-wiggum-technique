# Performance

- [Introduction](#introduction)
- [Measure Before Optimizing](#measure-first)
- [N+1 Queries & Eager Loading](#eager-loading)
- [Select Only What You Use](#selecting-columns)
- [Database Work vs Collection Work](#database-vs-collection)
- [Large Datasets: Chunk, Lazy, Cursor](#large-datasets)
- [Caching With Explicit Keys & Invalidation](#caching)
- [Queues for Slow, Independent Work](#queues)
- [Frontend Bundle Cost](#frontend-bundle)

<a name="introduction"></a>

## Introduction

This document expands section 13 ("Performance and operations") and the "verify query performance using actual measurements" rule in section 7 of the engineering standards (the `engineering-standards` guideline in `CLAUDE.md`). It covers request-path performance: database queries, memory, caching, queues and frontend assets.

Describe impact by **mechanism**: "loads one row instead of every matching row", "replaces one query per invoice with two queries in total". Never use invented multipliers such as "10x faster". Quote numbers only when they come from a real measurement, and say where the measurement came from.

Related documents: [01-architecture.md](01-architecture.md), [02-domain-modules.md](02-domain-modules.md), [03-laravel-backend.md](03-laravel-backend.md), [04-database-and-concurrency.md](04-database-and-concurrency.md) (indexes, locks), [05-api-contracts.md](05-api-contracts.md) (pagination), [06-frontend-vue-react.md](06-frontend-vue-react.md), [07-testing.md](07-testing.md), [08-security.md](08-security.md), [10-ci-cd-observability.md](10-ci-cd-observability.md) (production metrics), [11-ai-assisted-development.md](11-ai-assisted-development.md).

<a name="measure-first"></a>

## Measure Before Optimizing

<a name="measure-first-principle"></a>

### Principle

Find the slow path with data before you change code: query count and duration, response time, memory, and bundle size. Then optimize the thing the data points at, and measure again to confirm the change helped.

<a name="measure-first-why-it-exists"></a>

### Why It Exists

Guessed optimizations add complexity in the wrong place. A typical case: a cache on a 2 ms query, while the actual 800 ms comes from an HTTP call in a view composer. The cache adds an invalidation bug and the page is not faster.

<a name="measure-first-when-to-apply"></a>

### When To Apply

- Before any change justified by "performance".
- When production metrics show a regression: p95 latency, slow-query log, queue wait time.
- When reviewing a new endpoint that lists data. Check its query count with a realistic dataset, not with three seeded rows.

<a name="measure-first-when-not-to-apply"></a>

### When Not To Apply

- Obvious correctness-level fixes do not need a benchmark first. A query inside a loop over user-controlled data, or a missing index on a foreign key you filter by, should simply be fixed.
- Do not install a profiling stack for a prototype. `DB::listen` and `EXPLAIN` are enough until you have real traffic.

<a name="measure-first-correct-example"></a>

### Correct Example

```php
// AppServiceProvider::boot(), local and staging only
if (! app()->isProduction()) {
    DB::listen(function (QueryExecuted $query): void {
        Log::debug('query', ['sql' => $query->sql, 'ms' => $query->time]);
    });
}

DB::whenQueryingForLongerThan(500, function (Connection $connection, QueryExecuted $event): void {
    Log::warning('Request exceeded cumulative query budget.', [
        'connection' => $connection->getName(),
        'last_sql' => $event->sql,
    ]);
});
```

```php
Invoice::query()->whereNull('refunded_at')->where('user_id', $userId)->explain()->dd();
```

Tools you can adopt when logs are no longer enough: Laravel Debugbar or Telescope (local), Laravel Pulse (self-hosted dashboard), Laravel Nightwatch or an APM (hosted).

<a name="measure-first-anti-pattern"></a>

### Anti-Pattern

```php
// "This page feels slow, let's cache everything."
return Cache::remember('dashboard', 3600, fn () => view('dashboard', $data)->render());
```

There was no measurement, so nobody knows what was slow. The rendered HTML now serves one user's data to every user, and stays stale for an hour.

<a name="measure-first-checklist"></a>

### Checklist

- [ ] The slow path is identified with a measurement: query log, `EXPLAIN`, profiler or production metric.
- [ ] Measurements use a realistic data volume.
- [ ] The before and after numbers, and their source, are in the PR description.
- [ ] The impact is described by mechanism, with no invented multipliers.
- [ ] `DB::listen` logging is never enabled in production.

<a name="eager-loading"></a>

## N+1 Queries & Eager Loading

<a name="eager-loading-principle"></a>

### Principle

Load the relationships you will access in a loop with `with()`, `load()` or `withCount()`. Make lazy loading fail loudly outside production with `Model::preventLazyLoading()`, or with `Model::shouldBeStrict()`, which also prevents silently discarded and missing attributes.

<a name="eager-loading-why-it-exists"></a>

### Why It Exists

Accessing `$invoice->user` inside a loop over N invoices issues one query per invoice, so N + 1 queries in total. The number of queries grows with the data, so a page that is fine with 20 rows becomes slow at 2,000. Nobody notices in development, because seeded data is small.

<a name="eager-loading-when-to-apply"></a>

### When To Apply

- Index pages, API collections, exports and notifications: anywhere you iterate models and touch a relationship.
- Use `withCount()` or `withExists()` when you only need a number or a boolean, not the related rows.

<a name="eager-loading-when-not-to-apply"></a>

### When Not To Apply

- Do not eager load "just in case". Loading `with(['user', 'tickets', 'tickets.user'])` for a view that shows invoice numbers transfers rows nobody reads. Over-eager loading is waste too.
- On a single-model page (`show`), lazy loading one relationship costs one query either way. Strict mode still forces you to write `load()`, which keeps the code explicit.
- `Model::automaticallyEagerLoadRelationships()` exists, but it hides which relationships a page depends on. Prefer explicit `with()` unless the team adopts it deliberately.

<a name="eager-loading-correct-example"></a>

### Correct Example

```php
// AppServiceProvider::boot()
Model::shouldBeStrict(! app()->isProduction());
```

```php
$invoices = Invoice::query()
    ->with('user:id,name')
    ->latest()
    ->paginate(25);

$users = User::query()->withCount('tickets')->paginate(25);
```

Two queries load the invoices with their users, whatever the page size. `tickets_count` is computed in SQL, so no ticket rows are loaded.

<a name="eager-loading-anti-pattern"></a>

### Anti-Pattern

```php
@foreach (Invoice::all() as $invoice)
    {{ $invoice->user->name }} ({{ $invoice->user->tickets->count() }} tickets)
@endforeach
```

This view runs a query in the template, loads every invoice without pagination, runs one query per invoice for `user`, and runs one more query per invoice that loads every ticket row just to count them.

<a name="eager-loading-checklist"></a>

### Checklist

- [ ] `Model::shouldBeStrict()` (or `preventLazyLoading()`) is enabled outside production.
- [ ] Every relationship accessed in a loop is eager loaded.
- [ ] Counts and existence checks use `withCount()` / `withExists()`.
- [ ] Eager loads match what the view or resource actually uses.
- [ ] A test or query log confirms the query count does not grow with the number of rows.

<a name="selecting-columns"></a>

## Select Only What You Use

<a name="selecting-columns-principle"></a>

### Principle

On hot or wide queries, select the columns the consumer needs, including the primary and foreign keys that relationships require. On ordinary queries, `select *` is fine.

<a name="selecting-columns-why-it-exists"></a>

### Why It Exists

Wide rows (text columns, JSON, or sensitive fields such as `iban` and `billing_address` on invoices) cost transfer and memory on every row. Fetching them also means sensitive data sits in memory and can end up in a serialized response or a log by accident.

<a name="selecting-columns-when-to-apply"></a>

### When To Apply

- Listing endpoints, exports and dropdown or autocomplete queries on tables with wide or sensitive columns.
- Eager-loaded relationships used for one field: `with('user:id,name')`.

<a name="selecting-columns-when-not-to-apply"></a>

### When Not To Apply

- Do not narrow selects on small tables or single-row fetches. The saving is negligible, and a later `$model->save()` or accessor may need the missing attribute. With `shouldBeStrict()`, the missing attribute throws.
- Do not narrow a select you cannot prove equivalent. Accessors, casts, appends and policies may read columns the view does not show directly.

<a name="selecting-columns-correct-example"></a>

### Correct Example

```php
$options = Invoice::query()
    ->where('user_id', $user->id)
    ->orderByDesc('id')
    ->limit(50)
    ->get(['id', 'number', 'amount_cents']);
```

The dropdown needs three columns. `iban` and `billing_address` never leave the database.

<a name="selecting-columns-anti-pattern"></a>

### Anti-Pattern

```php
$invoices = Invoice::query()->select('number')->with('user')->get();
```

`user_id` is missing, so the `user` relationship cannot be matched and every `$invoice->user` comes back `null`. Narrowing changed behavior, so it is not an optimization.

<a name="selecting-columns-checklist"></a>

### Checklist

- [ ] Hot or wide listing queries select explicit columns.
- [ ] Keys required by relationships (`id`, foreign keys) are included.
- [ ] Accessors, appends and policies used downstream still have their columns.
- [ ] Sensitive columns are not fetched for views that do not need them.

<a name="database-vs-collection"></a>

## Database Work vs Collection Work

<a name="database-vs-collection-principle"></a>

### Principle

When you only need an aggregate (count, existence, sum), ask the database for it: `count()`, `exists()` and `sum()` on the query. When you already need the rows, reuse the fetched collection instead of querying again.

<a name="database-vs-collection-why-it-exists"></a>

### Why It Exists

`->get()->count()` transfers every matching row into PHP and builds a model for each one, only to produce a single integer. As the table grows, so do memory use and response time. The opposite mistake, running a second `count()` query next to a collection you already have, wastes a round trip.

<a name="database-vs-collection-when-to-apply"></a>

### When To Apply

- Use `exists()` for guards ("has the user any unpaid invoice?") and `count()` or `sum()` for badges and totals that are not rendered alongside the rows.

<a name="database-vs-collection-when-not-to-apply"></a>

### When Not To Apply

- When the same rows are also rendered, `$invoices->count()` on the fetched collection is **correct**. It costs no query. Do not "fix" it into a second SQL query.
- Collection `where()` uses loose comparison, while SQL `WHERE` follows the database's typing and collation. Before moving a filter from PHP to SQL or back, check that the results are equivalent, including casts, null handling and case sensitivity.

<a name="database-vs-collection-correct-example"></a>

### Correct Example

```php
$hasOpenInvoice = $user->invoices()->whereNull('refunded_at')->exists();

$totalCents = $user->invoices()->whereNull('refunded_at')->sum('amount_cents');

// Rows are rendered anyway: reuse them.
$invoices = $user->invoices()->latest()->limit(20)->get();
return view('invoices.index', ['invoices' => $invoices, 'shown' => $invoices->count()]);
```

`exists()` stops at the first matching row, `sum()` returns a single scalar, and the last count reuses data already in memory.

<a name="database-vs-collection-anti-pattern"></a>

### Anti-Pattern

```php
if ($user->invoices->where('refunded_at', null)->count() > 0) {
    $total = $user->invoices->sum('amount_cents');
}
```

This loads every invoice the user ever had, filters them in PHP, and sums them in PHP. The database could answer both questions without returning any rows.

<a name="database-vs-collection-checklist"></a>

### Checklist

- [ ] Aggregates that are not shown with the rows use query `count()` / `exists()` / `sum()`.
- [ ] Collections that are already fetched are reused, not queried again.
- [ ] Moving a filter between PHP and SQL was checked for equivalence (casts, nulls, comparison).
- [ ] Relationship properties (`$user->invoices`) are not loaded just to aggregate.

<a name="large-datasets"></a>

## Large Datasets: Chunk, Lazy, Cursor

<a name="large-datasets-principle"></a>

### Principle

Never `get()` an unbounded result set. Paginate anything a user sees. Process batch data with `chunkById()` / `lazyById()`, which keep memory bounded and stay correct while rows are updated. Use `cursor()` only for read-only streaming that does not use eager loading.

<a name="large-datasets-why-it-exists"></a>

### Why It Exists

`Invoice::all()` in an export or backfill works until the table outgrows `memory_limit`, and then the job dies halfway. Offset-based `chunk()` skips rows when the loop updates the column it filters on, because each update shifts the offsets.

<a name="large-datasets-when-to-apply"></a>

### When To Apply

- Commands, jobs, exports, backfills and scheduled clean-ups that touch a table that can grow without limit.
- Use `chunkById()` / `lazyById()` whenever the loop modifies rows matched by the query.

<a name="large-datasets-when-not-to-apply"></a>

### When Not To Apply

- Bounded, small sets, such as a user's 12 settings or the 50 most recent items, are fine with `get()`. Chunking them only adds noise.
- If a single `UPDATE ... WHERE` does the job (`Invoice::query()->where(...)->update([...])`), do not loop at all. Be aware that a mass update skips model events and observers. Check that nothing depends on them first.
- `cursor()` keeps one query open and cannot eager load relationships, so use `lazyById()` when you need them.

<a name="large-datasets-correct-example"></a>

### Correct Example

```php
Invoice::query()
    ->whereNull('refunded_at')
    ->where('created_at', '<', now()->subYears(7))
    ->lazyById(500)
    ->each(function (Invoice $invoice): void {
        $this->archiver->archive($invoice);
    });
```

Memory stays bounded to about 500 models at a time. Paging by ID means rows modified during the loop are neither skipped nor processed twice.

<a name="large-datasets-anti-pattern"></a>

### Anti-Pattern

```php
Invoice::query()->whereNull('refunded_at')->chunk(500, function ($invoices): void {
    $invoices->each->update(['refunded_at' => now()]);
});
```

Each chunk removes its rows from the `whereNull` result, so the next offset skips the following 500 rows. About half the invoices are never processed, and nothing reports an error.

<a name="large-datasets-checklist"></a>

### Checklist

- [ ] No `all()` / `get()` on tables that can grow without limit.
- [ ] User-facing lists are paginated (`paginate`, `cursorPaginate`).
- [ ] Loops that modify their own filter columns use `chunkById` / `lazyById`.
- [ ] Mass `update()` / `delete()` is used only where skipping model events is acceptable.
- [ ] Long batch processes run in a job or command, not in a web request.

<a name="caching"></a>

## Caching With Explicit Keys & Invalidation

<a name="caching-principle"></a>

### Principle

Cache a value only when measurement shows it is expensive and read more often than it changes. Every cached value needs three things decided up front: a key that includes everything the value depends on, a TTL, and an invalidation path. Use `Cache::remember()` by default. Use `Cache::flexible()` (stale-while-revalidate) when brief staleness is acceptable and recomputation is slow.

<a name="caching-why-it-exists"></a>

### Why It Exists

A key that omits a dimension (user, locale, tenant) serves one user's data to another, which is both a security incident and a correctness bug. A cache with no invalidation shows stale totals after a refund. When a popular key expires, every concurrent request recomputes it at the same time.

<a name="caching-when-to-apply"></a>

### When To Apply

- Expensive aggregates or external API responses that are read far more often than they change, and where staleness has a defined acceptable bound.
- Use `Cache::flexible()` for dashboards and counters where serving a value a few seconds stale is fine while it refreshes in the background.

<a name="caching-when-not-to-apply"></a>

### When Not To Apply

- Do not cache to hide an N+1 or a missing index. Fix the query. The cache just moves the slowness to every cache miss.
- Do not cache cheap queries (an indexed primary-key lookup), data that must be exact at write time (balances, stock, authorization decisions), or values with no clear invalidation trigger.
- Laravel 13 sets `serializable_classes` to `false` by default. Cache arrays or scalars rather than arbitrary objects, unless the class is allow-listed.

<a name="caching-correct-example"></a>

### Correct Example

```php
public function openTotalFor(User $user): int
{
    return Cache::flexible("users:{$user->id}:invoices:open-total", [60, 300], fn (): int =>
        (int) $user->invoices()->whereNull('refunded_at')->sum('amount_cents'),
    );
}

// Invalidate where the underlying data changes.
public function refund(Invoice $invoice): void
{
    $invoice->update(['refunded_at' => now()]);

    Cache::forget("users:{$invoice->user_id}:invoices:open-total");
}
```

The value is fresh for 60 s, may be served stale for up to 300 s while it refreshes after the response, and is dropped as soon as a refund changes it.

<a name="caching-anti-pattern"></a>

### Anti-Pattern

```php
$total = Cache::rememberForever('open-total', fn () => auth()->user()->invoices()->sum('amount_cents'));
```

The key is missing the user, so the first user's total is shown to everyone. The value never expires and is never invalidated.

<a name="caching-checklist"></a>

### Checklist

- [ ] A measurement showed the computation is worth caching.
- [ ] The key includes every dimension the value depends on (user, tenant, locale, filters).
- [ ] The TTL is explicit, and the acceptable staleness is stated.
- [ ] Every write path that changes the value invalidates it, and a test covers this.
- [ ] Cached payloads are arrays or scalars, or allow-listed classes.
- [ ] Nothing security-relevant (permissions, balances) is served from cache.

<a name="queues"></a>

## Queues for Slow, Independent Work

<a name="queues-principle"></a>

### Principle

Move work to a queued job when the response does not depend on its result: emails, webhooks to third parties, PDF generation, AI calls whose output is delivered later. Keep work in the request when the user needs the result to continue.

<a name="queues-why-it-exists"></a>

### Why It Exists

A synchronous call to a slow or failing third party ties up a PHP worker for the whole call, and turns the provider's outage into our 500. Queues give us retries and backoff, and keep the request time independent of the third party.

<a name="queues-when-to-apply"></a>

### When To Apply

- Side effects that can complete after the response, with a defined retry policy.
- Work that may take longer than an HTTP timeout, or that needs rate limiting against a provider.
- Very short post-response work that does not need retries can use `defer()` instead of a full job.

<a name="queues-when-not-to-apply"></a>

### When Not To Apply

- Do not queue work whose result the response returns, such as calculating a price, validating a coupon, or authorizing a payment. Queuing it forces polling or optimistic UI for no gain.
- Do not queue to "speed up" a fast operation. Serialization, the queue round trip and worker scheduling add latency and new failure modes.
- Do not dispatch inside a database transaction without `afterCommit()`. The worker may run before the commit and read data that does not exist yet.

<a name="queues-correct-example"></a>

### Correct Example

```php
DB::transaction(function () use ($invoice): void {
    $invoice->update(['refunded_at' => now()]);

    SendRefundConfirmation::dispatch($invoice)->afterCommit();
});

return to_route('invoices.show', $invoice)->with('status', 'Refund recorded.');
```

The refund is committed before the response is sent. The email goes out after the commit, with retries, and does not affect response time.

<a name="queues-anti-pattern"></a>

### Anti-Pattern

```php
ComputeQuote::dispatch($cart);
$quote = Cache::get("quote:{$cart->id}"); // usually null
return response()->json($quote);
```

The response needs the quote, so it is a dependent request. Dispatching the work creates a race the request usually loses. Compute it inline.

<a name="queues-checklist"></a>

### Checklist

- [ ] Only work the response does not depend on is queued.
- [ ] Jobs dispatched inside transactions use `afterCommit()`.
- [ ] Jobs are idempotent and define tries, backoff and timeout.
- [ ] Queue backlog and failed jobs are monitored (see [CI/CD & Observability](10-ci-cd-observability.md)).
- [ ] Tests use `Queue::fake()` to assert dispatch, and test the job's `handle()` separately.

<a name="frontend-bundle"></a>

## Frontend Bundle Cost

<a name="frontend-bundle-principle"></a>

### Principle

Ship the JavaScript and CSS a page needs, and nothing else. Split heavy, rarely used code into dynamic `import()` chunks. Check the size of the production build (`npm run build` output) whenever you add a dependency.

<a name="frontend-bundle-why-it-exists"></a>

### Why It Exists

Every kilobyte of JavaScript is downloaded, parsed and executed before the page becomes interactive. On mid-range phones, parsing and execution often cost more than the download. One charting or editor library imported into the main entry makes every page pay for a feature only one page uses.

<a name="frontend-bundle-when-to-apply"></a>

### When To Apply

- When you add a dependency, compare the Vite build output before and after.
- For heavy features used on a single page or behind an interaction (charts, rich-text editors, PDF viewers), use a dynamic `import()`.
- After code splitting, use `Vite::prefetch()` when the likely next chunks should be warmed in the background.

<a name="frontend-bundle-when-not-to-apply"></a>

### When Not To Apply

- Do not split tiny modules. Each chunk is another request, and fragmenting a 5 kB bundle makes loading slower, not faster.
- Today `resources/js/app.js` is essentially empty and the UI is Blade with Tailwind. Do not add bundle tooling before there is JavaScript to optimize.

<a name="frontend-bundle-correct-example"></a>

### Correct Example

```js
// resources/js/app.js
const chartEl = document.querySelector('[data-revenue-chart]');

if (chartEl) {
    import('./charts/revenue.js').then(({ renderRevenueChart }) => {
        renderRevenueChart(chartEl);
    });
}
```

Vite emits `revenue.js` and its chart library as a separate chunk, which only pages that contain the chart download.

<a name="frontend-bundle-anti-pattern"></a>

### Anti-Pattern

```js
// resources/js/app.js
import Chart from 'chart.js/auto';
import * as _ from 'lodash';

window.Chart = Chart;
window._ = _;
```

Every page downloads and parses a full chart library and all of lodash, even when it uses neither. Putting them on `window` also defeats tree shaking.

<a name="frontend-bundle-checklist"></a>

### Checklist

- [ ] `npm run build` output was compared before and after adding a dependency.
- [ ] Heavy, page-specific libraries load through dynamic `import()`.
- [ ] No library is attached to `window` just for convenience.
- [ ] Imports are named (`import { debounce } from ...`) so unused code can be tree-shaken.
- [ ] Chunks are not split below a size where the extra request outweighs the saving.
