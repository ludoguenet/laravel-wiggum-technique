# API Contracts

- [Introduction](#introduction)
- [Validation & Authorization](#validation-and-authorization)
- [API Resources](#api-resources)
- [Status Codes & Error Format](#status-codes-and-error-format)
- [Pagination & Filtering](#pagination-and-filtering)
- [Versioning & Backward Compatibility](#versioning-and-backward-compatibility)
- [Server-Side Recalculation](#server-side-recalculation)
- [Idempotent Retries](#idempotent-retries)
- [Rate Limiting](#rate-limiting)

<a name="introduction"></a>

## Introduction

This document expands section 8 ("API contracts") of the engineering standards (the `engineering-standards` guideline in `CLAUDE.md`). An API contract is everything a client can observe: accepted input, authorization outcome, response shape, status codes, error format, pagination and limits. Each must be explicit in code and asserted by tests.

API routes live in `routes/api.php` (prefixed with `/api`). `bootstrap/app.php` already renders exceptions as JSON for `api/*` and `expectsJson()` requests via `shouldRenderJsonWhen()`.

Related documents:

- [03-laravel-backend.md](03-laravel-backend.md) — controllers, Actions, Form Requests.
- [04-database-and-concurrency.md](04-database-and-concurrency.md) — idempotency keys, atomic updates.
- [06-frontend-vue-react.md](06-frontend-vue-react.md) — consuming these contracts.
- [07-testing.md](07-testing.md) — feature test conventions.
- [08-security.md](08-security.md) — authorization and data exposure.

<a name="validation-and-authorization"></a>

## Validation & Authorization

<a name="validation-and-authorization-principle"></a>

### Principle

Every write endpoint receives a dedicated Form Request that authorizes the action (delegating to a Policy) and validates every accepted field. Controllers only consume `$request->validated()`.

<a name="validation-and-authorization-why-it-exists"></a>

### Why It Exists

`$request->all()` passed to `create()` lets a client set `is_admin`, `team_id` or `price_cents`. Authorization checked only in the frontend lets any user call `PATCH /api/orders/42` on someone else's order (IDOR).

<a name="validation-and-authorization-when-to-apply"></a>

### When To Apply

- Any endpoint accepting a body or query parameters that affect behavior.
- Any endpoint touching a resource that belongs to a user or team: authorize through the Policy, either in `authorize()` or with `Gate::authorize()` / the `can` middleware.

<a name="validation-and-authorization-when-not-to-apply"></a>

### When Not To Apply

- A read-only endpoint without input: route-level `can:view,order` middleware is enough, no Form Request needed.
- Do not duplicate the same Policy check in the Form Request *and* the controller; pick one location per endpoint.

<a name="validation-and-authorization-correct-example"></a>

### Correct Example

```php
class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('order'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipping_address' => ['sometimes', 'string', 'max:500'],
            'delivery_slot' => ['sometimes', Rule::enum(DeliverySlot::class)],
        ];
    }
}
```

A failed `authorize()` returns `403`; failed rules return `422` with the standard error shape.

<a name="validation-and-authorization-anti-pattern"></a>

### Anti-Pattern

```php
public function update(Request $request, Order $order): JsonResponse
{
    $order->update($request->all()); // no authorization, every column writable

    return response()->json($order);
}
```

<a name="validation-and-authorization-checklist"></a>

### Checklist

- [ ] Each write endpoint has a Form Request or an explicit `Gate::authorize()` call.
- [ ] Controllers use `validated()` or `safe()->only([...])`, never `all()`.
- [ ] Ownership is checked through a Policy, not a frontend-hidden button.
- [ ] Tests cover `403` for another user's resource and `422` for invalid input.
- [ ] Enum-like fields use `Rule::enum()` or `Rule::in()`.

<a name="api-resources"></a>

## API Resources

<a name="api-resources-principle"></a>

### Principle

Every JSON response representing a model goes through an API Resource that lists the exposed fields explicitly. Relations and counts are included only when loaded (`whenLoaded()`, `whenCounted()`), and role-dependent fields use conditional attributes (`when()`, `mergeWhen()`).

<a name="api-resources-why-it-exists"></a>

### Why It Exists

Returning a model directly serializes every non-hidden column: a migration adding `internal_notes` or `stripe_customer_id` silently leaks it. It also couples the API shape to the schema, so renaming a column breaks clients, and accessing unloaded relations in serialization causes N+1 queries.

<a name="api-resources-when-to-apply"></a>

### When To Apply

- All public or SPA-consumed JSON endpoints returning models or collections.
- Responses whose fields differ by viewer (owner vs. other member vs. admin).

<a name="api-resources-when-not-to-apply"></a>

### When Not To Apply

- Small non-model payloads (`{"tickets_left": 0}`) or action acknowledgements: `response()->json([...])` is clearer.
- Internal Artisan or queue payloads: they are not an HTTP contract.

<a name="api-resources-correct-example"></a>

### Correct Example

```php
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_cents' => $this->total_cents,
            'placed_at' => $this->created_at?->toIso8601String(),
            'lines' => OrderLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'internal_notes' => $this->when($request->user()?->isAdmin(), $this->internal_notes),
        ];
    }
}
```

```php
test('order resource exposes only the contract fields', function () {
    $order = Order::factory()->for($user = User::factory()->create(), 'customer')->create();

    $this->actingAs($user)->getJson(route('api.orders.show', $order))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $data) => $data
                ->whereType('id', 'integer')
                ->where('status', 'pending')
                ->hasAll(['total_cents', 'placed_at'])
                ->missing('internal_notes')
                ->missing('lines')
                ->etc()));
});
```

`missing()` turns "do not leak this field" into a regression test.

<a name="api-resources-anti-pattern"></a>

### Anti-Pattern

```php
return response()->json(Order::with('customer')->findOrFail($id)); // full model + full customer, incl. future columns
```

<a name="api-resources-checklist"></a>

### Checklist

- [ ] Each exposed field is listed explicitly in `toArray()`.
- [ ] Relations use `whenLoaded()`; the controller eager-loads what it intends to expose.
- [ ] Sensitive fields are guarded by `when()` with a server-side permission check.
- [ ] Dates are serialized in one documented format (ISO 8601).
- [ ] A test asserts the shape and `missing()` for at least one sensitive field.

<a name="status-codes-and-error-format"></a>

## Status Codes & Error Format

<a name="status-codes-and-error-format-principle"></a>

### Principle

Status codes carry meaning and errors share one shape. Use Laravel's default JSON error format (`{"message": "...", "errors": {...}}` for validation) and map domain exceptions to statuses centrally in `bootstrap/app.php` with `$exceptions->render()`.

<a name="status-codes-and-error-format-why-it-exists"></a>

### Why It Exists

`200 {"success": false}` breaks HTTP caches, retries and client error handling. Ad-hoc `try/catch` blocks in controllers produce different shapes per endpoint and sometimes leak SQL or stack traces.

<a name="status-codes-and-error-format-when-to-apply"></a>

### When To Apply

Use this mapping: `200` read/update, `201` created (with the resource), `204` delete without body, `401` unauthenticated, `403` forbidden, `404` not found, `409` conflict with current state (out of stock, duplicate in-flight request), `422` validation or business rule on input, `429` rate limited, `5xx` only for server faults.

<a name="status-codes-and-error-format-when-not-to-apply"></a>

### When Not To Apply

- Do not invent a custom envelope (`{"status": "ok", "data": ...}`) when clients already parse Laravel's default; changing it is a breaking change.
- `404` instead of `403` is legitimate when revealing existence is itself a leak (resources scoped to another tenant).

<a name="status-codes-and-error-format-correct-example"></a>

### Correct Example

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );

    $exceptions->render(function (OutOfStockException $e, Request $request): ?JsonResponse {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json(['message' => 'This product is out of stock.'], 409);
    });
})
```

```php
test('validation errors use the standard shape', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('api.orders.store'), [])
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['lines']])
        ->assertJsonValidationErrors(['lines']);
});
```

<a name="status-codes-and-error-format-anti-pattern"></a>

### Anti-Pattern

```php
try {
    $order = $placeOrder->handle($request->validated());
} catch (Throwable $e) {
    return response()->json(['success' => false, 'error' => $e->getMessage()], 200); // leaks internals, wrong status
}
```

<a name="status-codes-and-error-format-checklist"></a>

### Checklist

- [ ] Domain exceptions are mapped once in `bootstrap/app.php`, not per controller.
- [ ] No endpoint returns `200` for a failure.
- [ ] Exception messages shown to clients are written for clients, never `$e->getMessage()` from SQL/HTTP clients.
- [ ] `APP_DEBUG=false` in production so 500s contain no trace.
- [ ] Tests assert status *and* error structure for the main failure paths.

<a name="pagination-and-filtering"></a>

## Pagination & Filtering

<a name="pagination-and-filtering-principle"></a>

### Principle

Every list endpoint is paginated with a server-enforced maximum page size. Filters and sort keys are validated against an allow-list. Use `paginate()` when clients need totals and page numbers; use `cursorPaginate()` for large or append-heavy datasets and infinite scroll.

<a name="pagination-and-filtering-why-it-exists"></a>

### Why It Exists

An unpaginated `Order::all()` endpoint is fine at launch and an outage a year later. `?per_page=100000` is a free denial of service. `orderBy($request->sort)` with unvalidated input allows sorting by `password` or crashing on unknown columns.

<a name="pagination-and-filtering-when-to-apply"></a>

### When To Apply

- `paginate()`: admin tables, small/medium datasets, UIs that show "page 3 of 12". It runs an extra `COUNT(*)`.
- `cursorPaginate()`: feeds, logs, large tables, or rows inserted while the user paginates. No total, no jump to page N; requires ordering on a unique (or uniquely combined) column.

<a name="pagination-and-filtering-when-not-to-apply"></a>

### When Not To Apply

- Inherently bounded lists (a user's 3 addresses, enum options): return the collection.
- Exports: stream with `lazyById()` in a queued job instead of paging an HTTP endpoint.

<a name="pagination-and-filtering-correct-example"></a>

### Correct Example

```php
public function index(IndexOrderRequest $request): AnonymousResourceCollection
{
    $filters = $request->validated();

    $orders = $request->user()->orders()
        ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
        ->orderBy($filters['sort'] ?? 'created_at', 'desc')
        ->orderBy('id', 'desc')
        ->cursorPaginate(min((int) ($filters['per_page'] ?? 20), 100));

    return OrderResource::collection($orders);
}
```

`IndexOrderRequest` restricts `sort` with `Rule::in(['created_at', 'total_cents'])` and `per_page` with `integer|between:1,100`. The resource collection adds `links` and `meta` (including `next_cursor`).

<a name="pagination-and-filtering-anti-pattern"></a>

### Anti-Pattern

```php
return Order::where($request->query())->orderBy($request->sort)->paginate($request->per_page);
```

The client controls columns, operators and page size, and sees every user's orders.

<a name="pagination-and-filtering-checklist"></a>

### Checklist

- [ ] No list endpoint returns an unbounded collection.
- [ ] `per_page` has a hard maximum enforced server-side.
- [ ] Filter and sort keys are validated with an allow-list.
- [ ] Cursor pagination orders by a unique tie-breaker column.
- [ ] The base query is scoped to the authenticated user or tenant.
- [ ] A test asserts `data`, `links` and `meta` keys with `assertJsonStructure()`.

<a name="versioning-and-backward-compatibility"></a>

## Versioning & Backward Compatibility

<a name="versioning-and-backward-compatibility-principle"></a>

### Principle

Published contracts evolve additively. Adding optional input fields or new response fields is allowed; removing, renaming, retyping a field or tightening validation is a breaking change that requires a new version (`/api/v2/...`) or a coordinated deprecation.

<a name="versioning-and-backward-compatibility-why-it-exists"></a>

### Why It Exists

Mobile apps stay installed for months; third-party integrations are never redeployed in lockstep with you. Renaming `total` to `total_cents` "because it is clearer" breaks every client that has not shipped an update.

<a name="versioning-and-backward-compatibility-when-to-apply"></a>

### When To Apply

- Any API consumed by clients you do not deploy together with the backend (mobile, partners, public API).
- Deprecation: keep the old field, document the replacement, add a `Deprecation`/`Sunset` header, monitor usage, remove only in the next version.

<a name="versioning-and-backward-compatibility-when-not-to-apply"></a>

### When Not To Apply

- An SPA or Inertia frontend built and deployed atomically with the backend: change both in one PR, no versioning needed.
- Do not create `v2` for additive changes; versions multiply maintenance cost.

<a name="versioning-and-backward-compatibility-correct-example"></a>

### Correct Example

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'total' => $this->total_cents / 100, // deprecated: kept for v1 clients, remove in v2
        'total_cents' => $this->total_cents, // additive replacement
        'currency' => $this->currency,
    ];
}
```

<a name="versioning-and-backward-compatibility-anti-pattern"></a>

### Anti-Pattern

```php
'total_cents' => $this->total_cents, // 'total' removed in a patch release, no version bump, no notice
```

<a name="versioning-and-backward-compatibility-checklist"></a>

### Checklist

- [ ] The PR states whether the contract change is additive or breaking.
- [ ] Breaking changes ship under a new version prefix or after a communicated sunset date.
- [ ] Contract tests for the previous version still pass.
- [ ] New required input fields are introduced as optional first.
- [ ] Deprecated fields are documented with their replacement.

<a name="server-side-recalculation"></a>

## Server-Side Recalculation

<a name="server-side-recalculation-principle"></a>

### Principle

Clients send *intent* (product ids, quantities, coupon code); the server computes every sensitive value (prices, discounts, taxes, totals, permissions, ownership) from its own data.

<a name="server-side-recalculation-why-it-exists"></a>

### Why It Exists

Anything in a request body is attacker-controlled. Accepting `unit_price` or `total` from the cart payload lets anyone buy a laptop for one cent with a modified request.

<a name="server-side-recalculation-when-to-apply"></a>

### When To Apply

- Money, quantities with limits, discounts, loyalty points, scores.
- Ownership fields (`user_id`, `team_id`): take them from `$request->user()` or the route, never the body.

<a name="server-side-recalculation-when-not-to-apply"></a>

### When Not To Apply

- Client-displayed totals are fine as a UX preview; optionally accept an `expected_total_cents` and return `409` if it differs from the server value, so the user confirms a price change. The server value still wins.

<a name="server-side-recalculation-correct-example"></a>

### Correct Example

```php
public function handle(User $customer, array $lines): Order
{
    $products = Product::query()->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');

    $orderLines = collect($lines)->map(fn (array $line): array => [
        'product_id' => $line['product_id'],
        'quantity' => $line['quantity'],
        'unit_price_cents' => $products[$line['product_id']]->price_cents,
    ]);

    return DB::transaction(function () use ($customer, $orderLines): Order {
        $order = $customer->orders()->create([
            'total_cents' => $orderLines->sum(fn (array $line): int => $line['quantity'] * $line['unit_price_cents']),
        ]);

        $order->lines()->createMany($orderLines->all());

        return $order;
    });
}
```

<a name="server-side-recalculation-anti-pattern"></a>

### Anti-Pattern

```php
Order::create([
    'user_id' => $request->input('user_id'),
    'total_cents' => $request->input('total_cents'),
]);
```

<a name="server-side-recalculation-checklist"></a>

### Checklist

- [ ] No price, total, discount or ownership field appears in Form Request rules as writable input.
- [ ] Prices are read from the database at order time and snapshotted on the line.
- [ ] A test posts a tampered price and asserts the stored total is the server-computed one.
- [ ] Rounding rules (integer cents, tax rounding) are implemented once on the server.

<a name="idempotent-retries"></a>

## Idempotent Retries

<a name="idempotent-retries-principle"></a>

### Principle

`POST` endpoints that create important resources accept an `Idempotency-Key` header. A repeated key returns the original result instead of creating a second resource. The storage and locking mechanics are described in [04-database-and-concurrency.md](04-database-and-concurrency.md#idempotency).

<a name="idempotent-retries-why-it-exists"></a>

### Why It Exists

Networks fail after the server commits but before the client receives the response. A client that retries a non-idempotent `POST /payments` charges the customer twice.

<a name="idempotent-retries-when-to-apply"></a>

### When To Apply

- Payments, orders, transfers, invitations, anything with external side effects.
- Endpoints called by mobile apps or third parties with automatic retry policies.

<a name="idempotent-retries-when-not-to-apply"></a>

### When Not To Apply

- `GET`, `PUT`, `DELETE`: idempotent by HTTP semantics if implemented correctly.
- Low-stakes creates where a duplicate is harmless and the client can delete it (a draft note).

<a name="idempotent-retries-correct-example"></a>

### Correct Example

```php
test('repeating a payment request with the same key charges once', function () {
    $user = User::factory()->create();
    $headers = ['Idempotency-Key' => (string) Str::uuid()];

    $first = $this->actingAs($user)->postJson(route('api.payments.store'), ['order_id' => 1], $headers);
    $second = $this->actingAs($user)->postJson(route('api.payments.store'), ['order_id' => 1], $headers);

    $first->assertCreated();
    $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
    expect(Payment::count())->toBe(1);
});
```

Validate the header (`required|uuid`) and scope the stored key to the user so one client cannot replay another's key.

<a name="idempotent-retries-anti-pattern"></a>

### Anti-Pattern

```php
// Disable the submit button in the frontend and assume the POST will only arrive once.
```

Frontend guards prevent double clicks, not network retries, proxies or scripts.

<a name="idempotent-retries-checklist"></a>

### Checklist

- [ ] Critical `POST` endpoints require and validate `Idempotency-Key`.
- [ ] The key is persisted with a unique index, scoped per user.
- [ ] A replay returns the original status and body.
- [ ] A replay with the same key but a different payload returns `422` or `409`.
- [ ] A test proves a duplicate request creates one resource.

<a name="rate-limiting"></a>

## Rate Limiting

<a name="rate-limiting-principle"></a>

### Principle

Every API route group has a named rate limiter defined with `RateLimiter::for()` and applied with the `throttle:<name>` middleware. Sensitive endpoints (login, password reset, OTP, expensive AI or export calls) get stricter, dedicated limiters.

<a name="rate-limiting-why-it-exists"></a>

### Why It Exists

Without limits, a single client can brute-force codes, scrape every resource by id, or burn your AI-provider budget in minutes.

<a name="rate-limiting-when-to-apply"></a>

### When To Apply

- All authenticated API routes: key by user id, fall back to IP for guests.
- Authentication and verification endpoints: key by identifier *and* IP.
- Endpoints that call paid external services.

<a name="rate-limiting-when-not-to-apply"></a>

### When Not To Apply

- Health checks (`/up`) and incoming webhooks from verified providers (authenticate them by signature instead; throttling causes redelivery storms).
- Do not use per-IP limits alone for authenticated APIs: users behind one corporate NAT share an IP.

<a name="rate-limiting-correct-example"></a>

### Correct Example

```php
// AppServiceProvider::boot()
RateLimiter::for('api', function (Request $request): Limit {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('assistant', function (Request $request): array {
    return [
        Limit::perMinute(5)->by('user:'.$request->user()->id),
        Limit::perDay(100)->by('user:'.$request->user()->id),
    ];
});

// routes/api.php
Route::middleware(['auth', 'throttle:assistant'])->post('/assistant', AskAssistantController::class);
```

Exceeding a limit returns `429` with a `Retry-After` header; assert it with `assertTooManyRequests()`.

<a name="rate-limiting-anti-pattern"></a>

### Anti-Pattern

```php
Route::post('/login', LoginController::class)->middleware('throttle:1000,1'); // effectively unlimited, keyed by IP only
```

<a name="rate-limiting-checklist"></a>

### Checklist

- [ ] Every route in `routes/api.php` is covered by a named limiter.
- [ ] Limiters key by user id when authenticated.
- [ ] Authentication and costly endpoints have dedicated, stricter limits.
- [ ] Limits are documented for API consumers.
- [ ] A test asserts `429` after the limit is exceeded on at least one sensitive route.
