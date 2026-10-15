# Database & Concurrency

- [Introduction](#introduction)
- [Database Constraints](#database-constraints)
- [Transactions](#transactions)
- [Race Conditions & Atomic Updates](#race-conditions-and-atomic-updates)
- [Pessimistic Locking](#pessimistic-locking)
- [Deadlocks](#deadlocks)
- [Idempotency](#idempotency)
- [Zero-Downtime Migrations](#zero-downtime-migrations)
- [Measuring Query Performance](#measuring-query-performance)

<a name="introduction"></a>
## Introduction

This document expands section 7 ("Database integrity and concurrency") of the [engineering standards](../../.ai/guidelines/engineering-standards.md). It covers how invariants are protected when several requests, workers or deployments touch the same rows at the same time.

The guiding rule: application code expresses intent, the database enforces truth. Validation and `if` statements are a convenience for users; constraints, atomic statements and locks are what keep data correct under concurrency.

Related documents:

- [01-architecture.md](01-architecture.md) — where transactional orchestration lives (Actions, services).
- [03-laravel-backend.md](03-laravel-backend.md) — Eloquent, jobs and events conventions.
- [05-api-contracts.md](05-api-contracts.md) — idempotent retries at the HTTP layer.
- [07-testing.md](07-testing.md) — testing failure paths and concurrency.
- [09-performance.md](09-performance.md) — indexes, N+1 and caching.
- [10-ci-cd-observability.md](10-ci-cd-observability.md) — migration rollout and monitoring.

<a name="database-constraints"></a>
## Database Constraints

<a name="database-constraints-principle"></a>
### Principle

Every invariant that must hold for *all* writers (web requests, queue workers, Artisan commands, manual SQL, future services) is declared in the schema: foreign keys, unique indexes, `NOT NULL` and, where the database supports them, check constraints. Form Request validation duplicates these rules for user-friendly messages; it never replaces them.

<a name="database-constraints-why-it-exists"></a>
### Why It Exists

A `unique` validation rule runs a `SELECT` and then the `INSERT` happens later. Two simultaneous registrations with the same email both pass validation and both insert. Without a unique index you now have two accounts, and every `->first()` lookup silently picks one. The same applies to orphaned rows after a parent is deleted, or negative stock written by a buggy job that bypasses the controller.

<a name="database-constraints-when-to-apply"></a>
### When To Apply

- A column must be unique per scope (email, `(team_id, slug)`, external payment id, idempotency key).
- A row references another row: always `foreignId(...)->constrained()` with an explicit `cascadeOnDelete()`, `restrictOnDelete()` or `nullOnDelete()` decision.
- A numeric value has a hard business bound (stock `>= 0`, amount `> 0`, `ends_at > starts_at`).
- A column is required for the row to be meaningful: leave it `NOT NULL` (the default) instead of adding `->nullable()` "just in case".

<a name="database-constraints-when-not-to-apply"></a>
### When Not To Apply

- Rules that depend on mutable configuration or other tables' aggregates ("max 5 members on the free plan"): enforce them in the application with a lock (see [Pessimistic Locking](#pessimistic-locking)).
- Polymorphic relations (`morphs()`): a foreign key cannot target several tables; validate the type with a morph map instead.
- Soft-deleted rows that must free a unique value: a plain unique index blocks re-use. Use a partial index on PostgreSQL, or a generated column on MySQL, and document the choice.
- Cross-database or cross-service references: there is no FK across connections; reconcile with a scheduled check instead.

<a name="database-constraints-correct-example"></a>
### Correct Example

The Schema builder has no fluent check-constraint method, so check constraints are added with a raw statement after the table exists (MySQL 8.0.16+ and PostgreSQL enforce them; SQLite only supports them at `CREATE TABLE` time).

```php
public function up(): void
{
    Schema::create('order_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->constrained()->restrictOnDelete();
        $table->unsignedInteger('quantity');
        $table->unsignedBigInteger('unit_price_cents');
        $table->timestamps();

        $table->unique(['order_id', 'product_id']);
    });

    if (DB::getDriverName() !== 'sqlite') {
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_quantity_positive CHECK (quantity > 0)');
    }
}
```

The unique pair makes "add the same product twice" impossible regardless of which code path writes the line.

<a name="database-constraints-anti-pattern"></a>
### Anti-Pattern

```php
$table->unsignedBigInteger('order_id')->nullable();
$table->unsignedBigInteger('product_id')->nullable();

// In the controller:
if (OrderLine::where('order_id', $order->id)->where('product_id', $productId)->exists()) {
    abort(422);
}
```

No foreign keys, nullable references and an application-only uniqueness check: orphans and duplicates appear as soon as two requests interleave or a job writes directly.

<a name="database-constraints-checklist"></a>
### Checklist

- [ ] Every `*_id` column uses `foreignId()->constrained()` with an explicit on-delete behavior.
- [ ] Every business-unique value has a unique index, scoped with a composite index where needed.
- [ ] `->nullable()` is only present where "unknown" is a legitimate state.
- [ ] Hard numeric bounds use unsigned types or a check constraint.
- [ ] Code catching `UniqueConstraintViolationException` exists where duplicates are an expected outcome.
- [ ] Validation rules mirror, not replace, the schema constraints.

<a name="transactions"></a>
## Transactions

<a name="transactions-principle"></a>
### Principle

Writes that must succeed or fail together run inside `DB::transaction()`. The transaction contains only database work; network calls, mail, and queued work happen outside or after commit.

<a name="transactions-why-it-exists"></a>
### Why It Exists

Without a transaction, an exception between "create order" and "create order lines" leaves a half-written order that customers can see and pay for. With an over-wide transaction, a 10-second payment-gateway timeout holds row locks for 10 seconds, blocking every other request on those rows, and a job dispatched inside the closure may run *before* commit and find no row (or run although the transaction rolled back).

<a name="transactions-when-to-apply"></a>
### When To Apply

- Two or more writes form one business operation (order + lines + stock movement).
- A read must be consistent with the write that follows it (required for `lockForUpdate()`, which is meaningless outside a transaction).
- Jobs, events, notifications or broadcasts depend on the written data: dispatch them with `afterCommit()` or rely on `DB::afterCommit()`.

<a name="transactions-when-not-to-apply"></a>
### When Not To Apply

- A single `INSERT`/`UPDATE` statement: it is already atomic.
- Long batch processing over thousands of rows: commit per chunk (`chunkById()`), otherwise you hold locks and grow undo logs for minutes.
- Around HTTP calls to third parties: call the provider first (or after commit), and persist the result in a short transaction. If the call must happen "between" writes, store a pending state, commit, call, then commit the outcome.

<a name="transactions-correct-example"></a>
### Correct Example

```php
public function handle(Cart $cart, PaymentGateway $gateway): Order
{
    $charge = $gateway->charge($cart->customer, $cart->totalCents()); // network call, no locks held

    $order = DB::transaction(function () use ($cart, $charge): Order {
        $order = $cart->customer->orders()->create([
            'total_cents' => $cart->totalCents(),
            'charge_id' => $charge->id,
        ]);

        $order->lines()->createMany($cart->linesForOrder());

        return $order;
    }, attempts: 3);

    SendOrderConfirmation::dispatch($order)->afterCommit();

    return $order;
}
```

`attempts: 3` only retries on concurrency errors (deadlocks, serialization failures); other exceptions roll back and are rethrown immediately.

<a name="transactions-anti-pattern"></a>
### Anti-Pattern

```php
DB::transaction(function () use ($cart, $gateway) {
    $order = Order::create([...]);
    $gateway->charge($cart->customer, $cart->totalCents()); // locks held during network I/O
    Mail::to($cart->customer)->send(new OrderPlaced($order)); // sent even if commit fails
    SendInvoice::dispatch($order); // worker may run before commit
});
```

If the commit fails after the charge, the customer is billed for an order that does not exist, and the email already left.

<a name="transactions-checklist"></a>
### Checklist

- [ ] Multi-write operations are wrapped in a single `DB::transaction()`.
- [ ] No HTTP client, mail, filesystem or external SDK call runs inside the closure.
- [ ] Jobs and notifications dispatched from transactional code use `->afterCommit()`, or events implement `ShouldDispatchAfterCommit`.
- [ ] `attempts` is set (2–3) when the transaction locks rows that other requests also lock.
- [ ] The closure is safe to re-run (no side effects outside the database) when `attempts > 1`.
- [ ] A test asserts nothing is persisted when the second write throws.

<a name="race-conditions-and-atomic-updates"></a>
## Race Conditions & Atomic Updates

<a name="race-conditions-and-atomic-updates-principle"></a>
### Principle

A "check then act" sequence in PHP (`if ($stock > 0) { $stock--; save(); }`) is never safe under concurrency. Fold the check into the write itself with a guarded `UPDATE ... WHERE` and inspect the affected-row count, or lock the row first (next section).

<a name="race-conditions-and-atomic-updates-why-it-exists"></a>
### Why It Exists

Two buyers load a product with `stock = 1`. Both pass `if ($product->stock > 0)`, both decrement, and you have sold two units of one item. A transaction alone does not help: under the default `REPEATABLE READ`/`READ COMMITTED` isolation, both transactions read `1` without blocking each other.

<a name="race-conditions-and-atomic-updates-when-to-apply"></a>
### When To Apply

- Counters and quotas: stock, seats, tickets, credits, rate counters.
- State transitions that must happen once: `pending -> paid`, `draft -> published`.
- Any decision whose input can be changed by another request between your read and your write.

<a name="race-conditions-and-atomic-updates-when-not-to-apply"></a>
### When Not To Apply

- The decision needs data from several rows or tables that a single `WHERE` cannot express: use [pessimistic locking](#pessimistic-locking).
- Last-write-wins is acceptable by design (a user editing their own display name).
- Data written by a single process only (an import command running alone, guarded by `withoutOverlapping()`).

<a name="race-conditions-and-atomic-updates-correct-example"></a>
### Correct Example

```php
public function reserve(Product $product, int $quantity): void
{
    $affected = Product::query()
        ->whereKey($product->id)
        ->where('stock', '>=', $quantity)
        ->decrement('stock', $quantity);

    if ($affected === 0) {
        throw new OutOfStockException($product);
    }
}

public function markPaid(Order $order): bool
{
    return Order::query()
        ->whereKey($order->id)
        ->where('status', OrderStatus::Pending)
        ->update(['status' => OrderStatus::Paid, 'paid_at' => now()]) === 1;
}
```

The database evaluates the guard and the write as one statement; only one caller can see `1` affected row.

<a name="race-conditions-and-atomic-updates-anti-pattern"></a>
### Anti-Pattern

```php
$product = Product::find($id);

if ($product->stock >= $quantity) {
    $product->stock -= $quantity; // computed from a stale value
    $product->save();             // writes an absolute value, overwriting concurrent changes
}
```

Besides overselling, `save()` writes `stock = <old - qty>`, so a concurrent restock is silently lost. Note that the model-level `$product->decrement('stock')` is atomic (`stock = stock - 1`) but still has no guard.

<a name="race-conditions-and-atomic-updates-checklist"></a>
### Checklist

- [ ] No business decision relies on a value read earlier in the request without a guard or lock.
- [ ] Counters use `increment()`/`decrement()`/`incrementEach()`, never read-modify-`save()`.
- [ ] Guarded updates check the returned affected-row count.
- [ ] State transitions include the expected current state in the `WHERE` clause.
- [ ] A check constraint (`stock >= 0`) backs the guard as a last line of defense.
- [ ] A test reproduces the stale-read scenario and asserts the second caller is rejected.

<a name="pessimistic-locking"></a>
## Pessimistic Locking

<a name="pessimistic-locking-principle"></a>
### Principle

When a decision needs to read a row (or several) and then write based on what was read, lock the rows with `lockForUpdate()` inside a transaction. Use `sharedLock()` when you only need the rows not to change while you read them. Always acquire locks in a deterministic order.

<a name="pessimistic-locking-why-it-exists"></a>
### Why It Exists

Some invariants cannot fit in one `WHERE`: "transfer 50 from wallet A to wallet B if A's balance and daily limit allow it", "add a member unless the team's plan limit is reached". Without a lock, two transfers read the same balance and both succeed. `SELECT ... FOR UPDATE` makes the second transaction wait until the first commits, then read the fresh value.

<a name="pessimistic-locking-when-to-apply"></a>
### When To Apply

- Read-decide-write across several columns or rows (balances, limits, aggregates).
- Refreshing a model already loaded earlier in the request: Laravel 13 provides `$model->refreshForUpdate()`, which reloads the attributes with `FOR UPDATE`.
- `sharedLock()` (`FOR SHARE` / `LOCK IN SHARE MODE`) when you read a parent row and must prevent it from changing or being deleted until you commit (e.g. inserting children of an order that must stay `open`).

<a name="pessimistic-locking-when-not-to-apply"></a>
### When Not To Apply

- A single guarded `UPDATE` expresses the rule: it is cheaper and cannot deadlock on read ordering.
- Hot rows under heavy write load (a global counter): locks serialize every request; prefer atomic increments or sharded counters.
- SQLite: `lockForUpdate()` is a no-op there (the whole database is locked on write); do not treat a passing SQLite test as proof of lock correctness.
- Work outside a transaction: the lock is released immediately, so it protects nothing.

<a name="pessimistic-locking-correct-example"></a>
### Correct Example

```php
public function transfer(Wallet $from, Wallet $to, int $amountCents): void
{
    DB::transaction(function () use ($from, $to, $amountCents): void {
        $wallets = Wallet::query()
            ->whereKey([$from->id, $to->id])
            ->orderBy('id') // consistent lock order prevents deadlocks
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $source = $wallets[$from->id];

        if ($source->balance_cents < $amountCents) {
            throw new InsufficientFundsException($source);
        }

        $source->decrement('balance_cents', $amountCents);
        $wallets[$to->id]->increment('balance_cents', $amountCents);
    }, attempts: 3);
}
```

For a single model already in memory, `$tour->refreshForUpdate()` as the first statement in the transaction is the idiomatic equivalent.

<a name="pessimistic-locking-anti-pattern"></a>
### Anti-Pattern

```php
$wallet = Wallet::lockForUpdate()->find($id); // no surrounding transaction: lock released instantly

DB::transaction(function () use ($wallet, $amount) {
    if ($wallet->balance_cents >= $amount) { // stale value read before the transaction
        $wallet->decrement('balance_cents', $amount);
    }
});
```

The lock is taken in autocommit mode and the decision uses the pre-transaction value, so two concurrent withdrawals both pass.

<a name="pessimistic-locking-checklist"></a>
### Checklist

- [ ] Every `lockForUpdate()`/`sharedLock()`/`refreshForUpdate()` call is inside `DB::transaction()`.
- [ ] The locked read is the first read of that row inside the transaction.
- [ ] Multiple rows are locked in one query with `orderBy` on the primary key.
- [ ] The lock query uses an indexed column, so it locks rows, not ranges or the whole table.
- [ ] No network call happens while the lock is held.
- [ ] Concurrency behavior is tested against MySQL/PostgreSQL, not only SQLite.

<a name="deadlocks"></a>
## Deadlocks

<a name="deadlocks-principle"></a>
### Principle

Deadlocks are an expected, recoverable event in any system that locks rows. Reduce their frequency with consistent lock ordering and short transactions, and handle the remainder with automatic retries via the `attempts` argument of `DB::transaction()`.

<a name="deadlocks-why-it-exists"></a>
### Why It Exists

Request 1 locks wallet 7 then wallet 9; request 2 locks wallet 9 then wallet 7. Each waits for the other, the database kills one transaction and the user gets a 500 error. Unhandled, these surface as random production errors that never reproduce locally.

<a name="deadlocks-when-to-apply"></a>
### When To Apply

- Transactions that lock more than one row, or rows in more than one table.
- Transactions that update rows also updated by queue workers or scheduled commands.
- MySQL InnoDB with non-unique or missing indexes on the locking query: gap locks widen the locked range and multiply deadlocks.

<a name="deadlocks-when-not-to-apply"></a>
### When Not To Apply

- Single-statement updates on one row by primary key: they cannot deadlock with each other.
- Do not wrap *every* transaction in retries "for safety": a retried closure must be free of side effects, and retrying hides genuine lock-ordering bugs that should be fixed.

<a name="deadlocks-correct-example"></a>
### Correct Example

```php
DB::transaction(function () use ($order): void {
    $productIds = $order->lines->pluck('product_id')->sort()->values();

    $products = Product::query()
        ->whereKey($productIds)
        ->orderBy('id')
        ->lockForUpdate()
        ->get();

    foreach ($products as $product) {
        $product->decrement('stock', $order->quantityFor($product));
    }
}, attempts: 3);
```

Sorted lock acquisition removes the classic cycle; `attempts: 3` absorbs the rare remaining deadlock.

<a name="deadlocks-anti-pattern"></a>
### Anti-Pattern

```php
DB::transaction(function () use ($order) {
    DB::transaction(function () use ($order) { // nested: attempts is useless here
        foreach ($order->lines as $line) {   // lock order follows user input
            Product::lockForUpdate()->find($line->product_id)->decrement('stock', $line->quantity);
        }
    }, attempts: 5);
});
```

Laravel only retries at the outermost level: a deadlock inside a nested transaction is rethrown as a `DeadlockException`. Lock order follows the cart order, so two carts with the same products in a different order deadlock.

<a name="deadlocks-checklist"></a>
### Checklist

- [ ] Rows are always locked in ascending primary-key order, tables in a documented order.
- [ ] `attempts` is set on the outermost transaction, not a nested one.
- [ ] The retried closure has no external side effects.
- [ ] Locking queries filter on indexed columns to avoid gap/range locks.
- [ ] Deadlock exceptions are monitored; a rising rate triggers a lock-order review.

<a name="idempotency"></a>
## Idempotency

<a name="idempotency-principle"></a>
### Principle

An operation that may be retried (client retries, double-clicks, webhook redelivery, queue retries) produces the same outcome when executed twice. The guarantee comes from a unique constraint on a business or client-supplied key, optionally fronted by an atomic lock (`Cache::lock()`) or a unique job (`ShouldBeUnique`).

<a name="idempotency-why-it-exists"></a>
### Why It Exists

Mobile clients retry on timeout even though the first request succeeded. Stripe redelivers webhooks. A queue worker crashes after charging but before acknowledging the job. Without idempotency each retry creates another order, charge or email.

<a name="idempotency-when-to-apply"></a>
### When To Apply

- `POST` endpoints that create money-related or externally visible resources (orders, payments, invitations).
- Webhook handlers: key on the provider's event id.
- Jobs with external side effects: key on the business id (`charge:{order_id}`).
- Jobs that should not be queued twice concurrently: `ShouldBeUnique` with `uniqueId()` and `#[UniqueFor(seconds)]` or `uniqueFor()`.

<a name="idempotency-when-not-to-apply"></a>
### When Not To Apply

- Naturally idempotent operations: `PUT` of a full resource, `DELETE`, guarded state transitions (`pending -> paid` only once).
- Reads.
- Do not rely on `Cache::lock()` alone for correctness: locks expire and cache stores can be flushed. It reduces duplicate work; the unique index guarantees correctness.

<a name="idempotency-correct-example"></a>
### Correct Example

```php
public function store(StorePaymentRequest $request, ChargeCustomer $chargeCustomer): JsonResponse
{
    $key = $request->header('Idempotency-Key');

    $payment = Cache::lock("payments:{$key}", 10)->block(5, function () use ($request, $key, $chargeCustomer): Payment {
        return Payment::query()->where('idempotency_key', $key)->first()
            ?? $chargeCustomer->handle($request->user(), $request->validated(), $key);
    });

    return PaymentResource::make($payment)->response()->setStatusCode(201);
}
```

`payments.idempotency_key` carries a unique index, so even if the lock expires the second insert fails with `UniqueConstraintViolationException` instead of charging twice. `block()` throws `LockTimeoutException` if the lock cannot be acquired; map it to a `409`.

<a name="idempotency-anti-pattern"></a>
### Anti-Pattern

```php
if (! Payment::where('order_id', $orderId)->exists()) { // check-then-act again
    $gateway->charge(...);
    Payment::create(['order_id' => $orderId]);
}
```

Two retries arriving together both see "no payment", both charge. Without a unique index there is no backstop, and without passing an idempotency key to the gateway, the provider cannot deduplicate either.

<a name="idempotency-checklist"></a>
### Checklist

- [ ] Each retryable create operation has a unique column for its idempotency or business key.
- [ ] The same key is forwarded to payment providers that support idempotency keys.
- [ ] Webhook handlers store the provider event id with a unique index before acting.
- [ ] Jobs with side effects implement `ShouldBeUnique` or check a persisted marker.
- [ ] Locks have both an expiry and a bounded wait (`block()`), with the timeout mapped to an HTTP status.
- [ ] A test sends the same request twice and asserts one resource and one side effect.

<a name="zero-downtime-migrations"></a>
## Zero-Downtime Migrations

<a name="zero-downtime-migrations-principle"></a>
### Principle

During a deploy, old and new code run against the same schema. Every migration must be compatible with both. Breaking changes follow expand / migrate / contract across separate deploys: add the new structure, backfill and dual-write, switch reads, then remove the old structure.

<a name="zero-downtime-migrations-why-it-exists"></a>
### Why It Exists

Renaming `name` to `full_name` in one deploy makes every old PHP worker (still serving requests or processing queued jobs) fail with "unknown column" until it restarts. Adding a `NOT NULL` column without a default rejects inserts from old code. Adding an index on a 50-million-row table can lock writes for minutes on some engines.

<a name="zero-downtime-migrations-when-to-apply"></a>
### When To Apply

- Renaming or dropping columns or tables that running code reads.
- Adding required columns to tables with existing rows.
- Changing a column's type or meaning.
- Adding indexes or constraints to large, write-heavy tables.

<a name="zero-downtime-migrations-when-not-to-apply"></a>
### When Not To Apply

- New tables and new nullable columns: they are already additive.
- Pre-production apps or features behind a maintenance window agreed with stakeholders.
- Small tables (a few thousand rows) where an index build is instantaneous; measure on a production-sized copy if unsure.

<a name="zero-downtime-migrations-correct-example"></a>
### Correct Example

```php
// Deploy 1 — expand: nullable column, code writes both columns.
Schema::table('customers', function (Blueprint $table) {
    $table->string('full_name')->nullable()->after('name');
});

// Deploy 1 — backfill in chunks (command or queued job, not inside the migration for big tables).
Customer::query()->whereNull('full_name')->chunkById(1000, function (Collection $customers): void {
    foreach ($customers as $customer) {
        $customer->updateQuietly(['full_name' => $customer->name]);
    }
});

// Deploy 2 — code reads full_name only. Deploy 3 — contract.
Schema::table('customers', function (Blueprint $table) {
    $table->dropColumn('name');
});
```

On PostgreSQL, create large indexes with `CREATE INDEX CONCURRENTLY` via `DB::statement()` in a migration that sets `public $withinTransaction = false;`, because that statement cannot run inside a transaction. On MySQL 8, verify that the operation is online (`ALGORITHM=INPLACE, LOCK=NONE`) before running it on a large table.

<a name="zero-downtime-migrations-anti-pattern"></a>
### Anti-Pattern

```php
Schema::table('customers', function (Blueprint $table) {
    $table->renameColumn('name', 'full_name');
    $table->string('vat_number'); // NOT NULL, no default, on a table with rows
});
```

Old code breaks the moment the migration runs; the `NOT NULL` column fails on existing rows or blocks old inserts; rollback means data loss.

<a name="zero-downtime-migrations-checklist"></a>
### Checklist

- [ ] The migration works with the currently deployed code *and* the new code.
- [ ] New columns on existing tables are nullable or have a default.
- [ ] Renames and drops are split across at least two deploys.
- [ ] Backfills run in chunks (`chunkById()`/`lazyById()`), outside the deploy-blocking migration for large tables.
- [ ] Index and constraint creation on large tables is checked for locking behavior on the target engine.
- [ ] `down()` is either correct and tested, or explicitly documented as irreversible.

<a name="measuring-query-performance"></a>
## Measuring Query Performance

<a name="measuring-query-performance-principle"></a>
### Principle

Query performance decisions (adding an index, rewriting a query, denormalizing) are based on measurements: `EXPLAIN` plans, query counts and timings on realistic data volumes, never on intuition.

<a name="measuring-query-performance-why-it-exists"></a>
### Why It Exists

Indexes added "just in case" slow down every write and are often not used by the planner. Conversely, a query that is instant with 100 local rows does a full scan on 5 million production rows. Only the plan tells you which case you are in.

<a name="measuring-query-performance-when-to-apply"></a>
### When To Apply

- Before adding or removing an index.
- When a slow-query log, APM trace or Pulse/Telescope entry points at a query.
- For new queries on tables expected to grow large (orders, events, logs).
- For any query that runs under `lockForUpdate()`: a missing index turns a row lock into a range or table lock.

<a name="measuring-query-performance-when-not-to-apply"></a>
### When Not To Apply

- Small, bounded tables (settings, countries, roles): a full scan is fine.
- Admin-only or one-off reports with no latency requirement.
- Plans from SQLite or an empty local database do not represent production; do not optimize based on them.

<a name="measuring-query-performance-correct-example"></a>
### Correct Example

```php
Order::query()
    ->where('customer_id', $customerId)
    ->where('status', OrderStatus::Pending)
    ->latest()
    ->explain()
    ->dd();
```

Look for full scans (`type: ALL` on MySQL, `Seq Scan` on PostgreSQL), high estimated rows and filesorts. If confirmed, add a composite index matching the filter and sort order, e.g. `$table->index(['customer_id', 'status', 'created_at']);`, then re-run `EXPLAIN` to prove it is used. Use `EXPLAIN ANALYZE` in a database console for actual timings.

<a name="measuring-query-performance-anti-pattern"></a>
### Anti-Pattern

```php
Schema::table('orders', function (Blueprint $table) {
    $table->index('status');
    $table->index('created_at');
    $table->index('customer_id');
    $table->index('total_cents');
});
```

Four single-column indexes added without a plan: the planner typically uses only one, writes pay for all four, and the real query still filesorts.

<a name="measuring-query-performance-checklist"></a>
### Checklist

- [ ] Every new index is justified by an `EXPLAIN` of a real query.
- [ ] Composite index column order matches equality filters first, then range/sort columns.
- [ ] Plans were checked against a production-sized dataset or production replica.
- [ ] The plan was re-checked after the change and the improvement recorded in the PR.
- [ ] Unused indexes are identified (e.g. `pg_stat_user_indexes`, `sys.schema_unused_indexes`) before adding more.
