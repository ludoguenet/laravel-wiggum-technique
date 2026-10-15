# Laravel Backend

- [Introduction](#introduction)
- [Form Requests](#form-requests)
- [Policies and Authorization](#policies)
- [API Resources](#api-resources)
- [Mass Assignment](#mass-assignment)
- [Actions and Jobs](#actions-and-jobs)
- [Enums, Types and Static Analysis](#typed-code)
- [Configuration, Secrets and Exceptions](#config-and-exceptions)

<a name="introduction"></a>
## Introduction

This document expands section 6 ("Laravel and PHP") of the [engineering standards](../../.ai/guidelines/engineering-standards.md). It covers the framework building blocks this project uses and the conventions of the installed versions: Laravel 13, PHP 8.5, Pest 5, Larastan 3 and Pint. Where to put these pieces is covered in [Architecture](01-architecture.md). Transactions and locks are covered in [Database and Concurrency](04-database-and-concurrency.md), response contracts in [API Contracts](05-api-contracts.md), and threat-specific guidance in [Security](08-security.md).

Project conventions that apply below: models declare writable fields with the `#[Fillable([...])]` attribute (see `app/Models/*.php`), casts use the `casts()` method, query scopes use `#[Scope]`, controllers authorize with `Gate::authorize()`, and files do not declare `strict_types`.

<a name="form-requests"></a>
## Form Requests

<a name="form-requests-principle"></a>
### Principle

All external input that changes state is validated by a Form Request class. Downstream code consumes only `$request->validated()` or `$request->safe()`.

<a name="form-requests-why-it-exists"></a>
### Why It Exists

Inline `$request->validate()` calls get copied and drift apart between `store` and `update`, and `$request->input()` after validation still reads fields that were never validated. A Form Request gives validation one tested home and makes the set of accepted fields explicit.

<a name="form-requests-when-to-apply"></a>
### When To Apply

- Every `POST`, `PUT`, `PATCH` and `DELETE` endpoint that accepts a body.
- `GET` endpoints with filters, sorting or pagination parameters that reach a query.

<a name="form-requests-when-not-to-apply"></a>
### When Not To Apply

- Endpoints with no input beyond route-model-bound parameters (`destroy(Team $team)`).
- Validating data that does not come from HTTP, such as an AI tool's arguments or a command's options. Use `Validator::make()` there, or typed parameters on the action.
- Do not put business rules that depend on locked database state (for example "invoice not already refunded") in the Form Request. Validation runs before the transaction, so the check can race. Enforce those rules in the action.

<a name="form-requests-correct-example"></a>
### Correct Example

```php
class StoreTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inviteMember', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::enum(TeamRole::class)],
        ];
    }
}
```

Authorization and validation run before the controller method. `Rule::enum()` restricts values to the backed enum's cases.

<a name="form-requests-anti-pattern"></a>
### Anti-Pattern

```php
public function update(Request $request, Team $team)
{
    $request->validate(['name' => 'required']);

    $team->update($request->input());
}
```

`$request->input()` includes every submitted field, including `owner_id`, which then reaches `update()`. The missing `max`/`string` rules let arrays and huge payloads through.

<a name="form-requests-checklist"></a>
### Checklist

- [ ] The request class is generated with `php artisan make:request` and named `<Verb><Model>Request`.
- [ ] `rules()` has an array-shape PHPDoc and uses array syntax with `Rule::` objects.
- [ ] Every string has a `max`, every ID has `exists` (scoped to the tenant or owner when relevant), and enums use `Rule::enum()`.
- [ ] Controllers read only `validated()` or `safe()->only([...])`.
- [ ] Feature tests cover at least one invalid payload per rule group (`assertSessionHasErrors` / `assertUnprocessable`).

<a name="policies"></a>
## Policies and Authorization

<a name="policies-principle"></a>
### Principle

Authorization of a model action lives in that model's Policy and is enforced on the server at the entry point with `Gate::authorize()`, the Form Request's `authorize()`, or `can` middleware. UI checks (`@can`, `Gate::allows()` for button visibility) are only a convenience on top.

<a name="policies-why-it-exists"></a>
### Why It Exists

Inline checks like `$invoice->user_id === auth()->id()` get forgotten in the third copy, as the vulnerable `LookupAnyInvoice` AI tool in this repository shows. A Policy is one named rule (`InvoicePolicy::refund`) that every entry point (controller, command, AI tool) calls, so the rule can be tested once.

<a name="policies-when-to-apply"></a>
### When To Apply

- Every action on a record that not every authenticated user may perform.
- Non-HTTP entry points acting on behalf of a user: call `Gate::forUser($user)->authorize(...)`.

<a name="policies-when-not-to-apply"></a>
### When Not To Apply

- Actions that are not about a model (for example "view the admin dashboard"). Define a Gate in `AppServiceProvider::boot()` instead of an artificial policy.
- System jobs running without a user, such as a nightly reconciliation. They are authorized by being scheduled, not by a policy. Do not fake a user to satisfy one.
- Filtering lists. A policy authorizes one record. Use a scope like `Document::visibleTo($user)` that mirrors the policy, and test that both agree.

<a name="policies-correct-example"></a>
### Correct Example

```php
class InvoicePolicy
{
    public function refund(User $user, Invoice $invoice): bool
    {
        return $invoice->user()->is($user) && ! $invoice->isRefunded();
    }
}

// Controller
Gate::authorize('refund', $invoice);

// Test
it('forbids refunding another customer\'s invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('invoices.refund', $invoice))
        ->assertForbidden();

    expect($invoice->fresh()->refunded_at)->toBeNull();
});
```

The policy is auto-discovered by naming convention (`App\Policies\InvoicePolicy` for `App\Models\Invoice`). The test checks both the status code and that nothing changed.

<a name="policies-anti-pattern"></a>
### Anti-Pattern

```php
public function refund(Invoice $invoice)
{
    if (! auth()->user()->is_admin && $invoice->user_id != auth()->id()) {
        return response()->json(['error' => 'nope'], 200);
    }
    // ...
}
```

The rule is buried in a controller and cannot be reused. The loose `!=` comparison is fragile, and returning `200` on denial hides the failure from clients and tests.

<a name="policies-checklist"></a>
### Checklist

- [ ] Every sensitive route or tool calls a Policy ability before acting.
- [ ] Policy methods have typed parameters and a `bool` (or `Response`) return type.
- [ ] Denials produce `403` (or `404` when existence itself is sensitive), never `200`.
- [ ] Each ability has an allowed and a forbidden test, the forbidden one asserting no side effect.
- [ ] List scopes that mirror a policy are tested against it.

<a name="api-resources"></a>
## API Resources

<a name="api-resources-principle"></a>
### Principle

JSON responses are shaped by an API Resource that lists every exposed field explicitly. Models are never returned directly from API endpoints.

<a name="api-resources-why-it-exists"></a>
### Why It Exists

`return $invoice;` serializes every attribute not marked `#[Hidden]`. When someone later adds an `iban` or `internal_notes` column, it ships to clients without anyone noticing. Resources make the response contract explicit, reviewable and stable when columns are renamed.

<a name="api-resources-when-to-apply"></a>
### When To Apply

- Every JSON endpoint under `routes/api.php` and every JSON response consumed by a frontend.

<a name="api-resources-when-not-to-apply"></a>
### When Not To Apply

- Responses that are not model representations, such as `{"status": "queued"}` or health checks. A plain `response()->json()` is fine.
- Inertia or Blade pages that pass explicit arrays. The rule there is the same (expose only intended fields), but a Resource is optional.

<a name="api-resources-correct-example"></a>
### Correct Example

```php
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'amount_cents' => $this->amount_cents,
            'iban_last4' => substr((string) $this->iban, -4),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'customer' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}

return InvoiceResource::collection($request->user()->invoices()->with('user')->paginate());
```

Fields are explicit, sensitive data is masked, and relations appear only when eager loaded, which avoids N+1 queries in collections.

<a name="api-resources-anti-pattern"></a>
### Anti-Pattern

```php
return response()->json(Invoice::with('user')->get());
```

This exposes the full IBAN, billing address and every user column not hidden. The result is unpaginated, and the response shape changes whenever a migration adds a column.

<a name="api-resources-checklist"></a>
### Checklist

- [ ] Each JSON endpoint returns a Resource or Resource collection.
- [ ] Sensitive fields are omitted or masked in `toArray()`.
- [ ] Relations use `whenLoaded()`, and the controller eager loads what it exposes.
- [ ] Collections are paginated.
- [ ] Feature tests assert the exact JSON structure (`assertExactJsonStructure` / `assertJsonPath`).

<a name="mass-assignment"></a>
## Mass Assignment

<a name="mass-assignment-principle"></a>
### Principle

Every model declares its mass-assignable attributes with the `#[Fillable([...])]` attribute, following the project convention, and only validated data is mass-assigned. Ownership and privileged columns (`user_id`, `owner_id`, `is_admin`, `status`) are set explicitly from server state, not from input.

<a name="mass-assignment-why-it-exists"></a>
### Why It Exists

`Book::create($request->all())` lets a client set any fillable column. If `is_admin` or `user_id` is fillable, privilege escalation or record hijacking is one extra form field away. `#[Fillable]` limits the columns. Using `validated()` limits the input. Both are needed.

<a name="mass-assignment-when-to-apply"></a>
### When To Apply

- Every model, and every `create`, `update`, `fill` and `firstOrCreate` call that takes an array.

<a name="mass-assignment-when-not-to-apply"></a>
### When Not To Apply

- Seeders and factories may use `Model::unguarded()` or `forceFill()`, because their input is trusted.
- Do not make a column fillable just so one internal action can set it. Assign it directly (`$invoice->refunded_at = now();`) or use `forceFill()` inside the action.

<a name="mass-assignment-correct-example"></a>
### Correct Example

```php
#[Fillable(['subject', 'body'])]
class Ticket extends Model {}

// Controller: ownership comes from the authenticated user, not the payload.
$ticket = $request->user()->tickets()->create($request->validated());

// AppServiceProvider::boot()
Model::shouldBeStrict(! $this->app->isProduction());
```

Creating through the relationship sets `user_id` from server state. Strict mode makes silently discarded attributes and lazy loading throw outside production.

<a name="mass-assignment-anti-pattern"></a>
### Anti-Pattern

```php
#[Fillable(['user_id', 'subject', 'body'])]
class Ticket extends Model {}

Ticket::create($request->all());
```

A client can post `user_id=42` and file a ticket as someone else. `all()` bypasses whatever the Form Request validated.

<a name="mass-assignment-checklist"></a>
### Checklist

- [ ] Every model uses `#[Fillable]`. None uses `#[Unguarded]` or an empty `#[Guarded([])]`.
- [ ] Ownership and privilege columns are not fillable, or are never fed from input.
- [ ] Mass assignment uses only `validated()` or `safe()` data.
- [ ] Sensitive attributes are listed in `#[Hidden]`.
- [ ] A test proves that a forged ownership field is ignored.

<a name="actions-and-jobs"></a>
## Actions and Jobs

<a name="actions-and-jobs-principle"></a>
### Principle

Synchronous use cases live in actions (`app/Actions/<Area>`, one `handle()` method, see [Architecture](01-architecture.md#actions-vs-services)). Slow, retryable or external work runs in queued Jobs that are idempotent, declare their retry policy, and are dispatched after the database commit.

<a name="actions-and-jobs-why-it-exists"></a>
### Why It Exists

A synchronous call to a slow provider ties up a PHP worker and fails the user's request when the provider fails. A job dispatched inside a transaction can run before the commit and see missing rows. A job without idempotency charges or refunds twice when the queue retries it.

<a name="actions-and-jobs-when-to-apply"></a>
### When To Apply

- Use a job for network calls whose result the user does not need immediately: gateways, emails, webhooks, exports, AI calls.
- Use a job for work that must be retried on transient failure.

<a name="actions-and-jobs-when-not-to-apply"></a>
### When Not To Apply

- Do not queue work whose result the response depends on, such as a CAPTCHA check or a price quote. Run it synchronously with a short timeout.
- Do not queue trivial in-process work like writing one row. The queue adds latency and failure modes.
- Do not chain jobs for steps that must be atomic. Put them in one transaction in an action.

<a name="actions-and-jobs-correct-example"></a>
### Correct Example

```php
#[Tries(5)]
#[Backoff(10, 60, 300)]
#[Timeout(30)]
class SendRefundToGateway implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function uniqueId(): string
    {
        return (string) $this->invoice->id;
    }

    public function handle(PaymentGateway $payments): void
    {
        $payments->refund($this->invoice->number, $this->invoice->amount_cents, idempotencyKey: "refund-{$this->invoice->id}");
    }
}

SendRefundToGateway::dispatch($invoice)->afterCommit();
```

The queue attributes (`Illuminate\Queue\Attributes\Tries`, `Backoff`, `Timeout`) declare the retry policy. `ShouldBeUnique` and the provider idempotency key make retries safe, and `afterCommit()` guarantees the job sees committed data.

<a name="actions-and-jobs-anti-pattern"></a>
### Anti-Pattern

```php
DB::transaction(function () use ($invoice) {
    $invoice->update(['refunded_at' => now()]);
    Http::post($gatewayUrl, ['invoice' => $invoice->number]);   // network call inside the transaction
    SendRefundEmail::dispatch($invoice);                         // may run before commit
});
```

The row lock is held while the network call runs. A rollback after the HTTP call leaves the money refunded but the invoice not marked. The email job can run on uncommitted state.

<a name="actions-and-jobs-checklist"></a>
### Checklist

- [ ] Jobs declare `#[Tries]`, `#[Backoff]` and `#[Timeout]`, or the equivalent properties.
- [ ] Jobs with external side effects are idempotent (unique ID, idempotency key or state check).
- [ ] Jobs dispatched inside transactions use `afterCommit()` or implement `ShouldQueueAfterCommit`.
- [ ] No `Http::` calls run inside `DB::transaction()`.
- [ ] Tests use `Queue::fake()` plus `Queue::assertPushed()` for dispatch, and call `handle()` directly for behavior.

<a name="typed-code"></a>
## Enums, Types and Static Analysis

<a name="typed-code-principle"></a>
### Principle

Finite sets of values are backed enums with TitleCase cases, cast on the model. All methods declare parameter and return types, arrays carry array-shape PHPDoc, and every change passes Pint and Larastan.

<a name="typed-code-why-it-exists"></a>
### Why It Exists

`'status' => 'pendng'` is a valid string and a silent bug. `TeamInvitationStatus::Pendng` fails at parse time and in static analysis. Types and Larastan catch null access, wrong arguments and dead branches before tests do. Pint removes style discussions from reviews.

<a name="typed-code-when-to-apply"></a>
### When To Apply

- Any column or parameter with a closed set of values (status, role, driver).
- Every new or modified method and file.

<a name="typed-code-when-not-to-apply"></a>
### When Not To Apply

- Open-ended or user-defined sets, such as tags or categories managed in an admin UI, belong in a table, not an enum.
- Larastan is currently configured at `level: 1` in `phpstan.neon`. Raise the level in a dedicated change with a baseline (`--generate-baseline`), not as a side effect of a feature pull request.

<a name="typed-code-correct-example"></a>
### Correct Example

```php
enum TeamInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}

protected function casts(): array
{
    return ['status' => TeamInvitationStatus::class];
}
```

```shell
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan test --compact
```

The cast gives typed reads and writes on the model. The three commands are the minimum gate before a change is considered done.

<a name="typed-code-anti-pattern"></a>
### Anti-Pattern

```php
public function setStatus($invitation, $status)
{
    if (in_array($status, ['pending', 'accepted', 'declined'])) {
        $invitation->status = $status;
    }
}
```

The method is untyped, it duplicates the enum's values, it ignores invalid input silently, and `in_array` without `strict: true` uses loose comparison.

<a name="typed-code-checklist"></a>
### Checklist

- [ ] Closed value sets are backed enums cast in `casts()`.
- [ ] All parameters and returns are typed, and arrays have shape PHPDoc.
- [ ] Pint has been run on the changed files.
- [ ] Larastan passes at the configured level with no new baseline entries.

<a name="config-and-exceptions"></a>
## Configuration, Secrets and Exceptions

<a name="config-and-exceptions-principle"></a>
### Principle

`env()` is called only inside `config/*.php`. Code reads `config('...')`. Secrets live in the environment, never in the repository or in logs. Business failures are named exceptions mapped to HTTP responses in `bootstrap/app.php`, and internal details never reach clients.

<a name="config-and-exceptions-why-it-exists"></a>
### Why It Exists

After `php artisan config:cache`, `env()` outside config returns `null`. That is how the "always pass" CAPTCHA fallback in [Domain Modules](02-domain-modules.md#configuration-binding) happens. Unhandled exceptions with `APP_DEBUG=true` leak SQL, file paths and credentials. A generic `500` for a business conflict gives clients nothing they can act on.

<a name="config-and-exceptions-when-to-apply"></a>
### When To Apply

- Any value that varies by environment or is secret.
- Any expected business failure a client should react to (conflict, quota exceeded, already processed).

<a name="config-and-exceptions-when-not-to-apply"></a>
### When Not To Apply

- Constants that never vary by environment, such as a provider's fixed endpoint URL, can be class constants.
- Do not create an exception class for every validation case. Use `ValidationException::withMessages()` for field-level errors, as `TeamInvitationController::destroy()` does.
- Do not catch `Throwable` to return a friendly message. Let the handler report it.

<a name="config-and-exceptions-correct-example"></a>
### Correct Example

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );

    $exceptions->dontReport(InvoiceAlreadyRefunded::class);

    $exceptions->render(fn (InvoiceAlreadyRefunded $e, Request $request) => $request->expectsJson()
        ? response()->json(['message' => 'This invoice has already been refunded.'], 409)
        : back()->withErrors(['invoice' => 'This invoice has already been refunded.']));
})
```

The existing `shouldRenderJsonWhen()` stays. The named exception maps to a stable `409` with a safe message, and it is not reported because it is an expected outcome.

<a name="config-and-exceptions-anti-pattern"></a>
### Anti-Pattern

```php
try {
    $issueRefund->handle($invoice);
} catch (\Exception $e) {
    return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 500);
}
```

This leaks internals (SQL, paths, possibly secrets in arguments) to the client. It also swallows the exception so it is never reported, and it uses `500` for what may be a `409`.

<a name="config-and-exceptions-checklist"></a>
### Checklist

- [ ] `grep -rn "env(" app/ routes/` returns nothing.
- [ ] New env keys are in `.env.example` without real values.
- [ ] `APP_DEBUG=false` in production.
- [ ] Expected business failures are named exceptions with a `render` mapping and a stable status code.
- [ ] Logs and error responses never contain tokens, passwords, IBANs or stack traces for clients.
