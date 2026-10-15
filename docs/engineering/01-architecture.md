# Architecture

- [Introduction](#introduction)
- [Layers and Responsibilities](#layers)
- [Thin Controllers](#thin-controllers)
- [Actions Versus Services](#actions-vs-services)
- [Dependency Direction and Contracts](#dependency-direction)
- [Simplicity Before Abstraction](#simplicity)
- [Modular Monolith Over Microservices](#modular-monolith)
- [Avoiding Circular and Cross-Module Dependencies](#cross-module)

<a name="introduction"></a>
## Introduction

This document expands section 3 ("Architecture") of the [engineering standards](../../.ai/guidelines/engineering-standards.md). It describes how code is split between layers, where business logic lives, which way dependencies point, and how much structure a feature should get before it is worth adding more.

The application is a Laravel 13 monolith on PHP 8.5. The default `app/` layout (`Http/Controllers`, `Http/Requests`, `Models`, `Policies`, `Providers`, `Enums`, `Support`, `Ai`, `Console`) is the baseline. New folders such as `app/Actions` or `app/Modules/<Name>` are added when a feature needs them, not ahead of time.

Related documents:

- [Domain Modules](02-domain-modules.md): when and how to group a feature into `app/Modules/<Name>`, with a full CAPTCHA example.
- [Laravel Backend](03-laravel-backend.md): Form Requests, Policies, API Resources, Jobs and other framework building blocks.
- [Database and Concurrency](04-database-and-concurrency.md): transactions, locks and idempotency.
- [Testing](07-testing.md): how each layer is tested.

<a name="layers"></a>
## Layers and Responsibilities

<a name="layers-principle"></a>
### Principle

Every piece of code belongs to one of four concerns: HTTP (input, authorization and output), application orchestration (running one use case), domain rules (business invariants), and infrastructure (database, queues, external APIs). Keep these concerns apart once a feature is complex enough that mixing them hides the business rule.

<a name="layers-why-it-exists"></a>
### Why It Exists

When a controller validates input, checks ownership, computes a refund, writes three tables and calls a payment API all in one method, nothing else can reuse that logic. An Artisan command, a queued job or an AI tool that needs the same refund ends up copying the method and leaving out a check, often the authorization or the "already refunded" guard. Separate layers give each rule one home.

Here is how the layers map to Laravel building blocks:

| Concern | Laravel building blocks | Owns |
| --- | --- | --- |
| HTTP | Routes, controllers, Form Requests, API Resources, middleware | Input shape, status codes, response shape |
| Authorization | Policies, `Gate` | Who may do what to which record |
| Application orchestration | Actions (`app/Actions`), Jobs, Artisan commands | The steps of one use case, transactions |
| Domain rules | Models (methods, casts), enums, value objects, module services | Invariants like "a refunded invoice cannot be refunded again" |
| Infrastructure | Eloquent queries, `Http` client, `Cache`, queue drivers, provider adapters | How data is stored and fetched |

<a name="layers-when-to-apply"></a>
### When To Apply

- The same use case can start from more than one place: a controller, a command, a job, or an AI tool in `app/Ai/Tools`.
- The operation writes more than one record or must succeed or fail as a whole.
- The operation calls an external system whose failure needs a defined behavior.
- A business rule is complex enough that it needs its own unit tests.

<a name="layers-when-not-to-apply"></a>
### When Not To Apply

- Plain CRUD where the "business rule" is just validation. A controller with a Form Request, `Model::create($request->validated())` and a Resource is already layered enough.
- Read-only pages that load a model, authorize it and render it.
- Prototypes and throwaway demo routes. Mark them as such, and add the layers when they become real features.

Splitting a 5-line method into a controller, an action, a service and a repository adds files without adding clarity.

<a name="layers-correct-example"></a>
### Correct Example

```php
// routes/web.php
Route::post('/invoices/{invoice}/refund', [InvoiceRefundController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('invoices.refund');

// app/Http/Controllers/InvoiceRefundController.php
class InvoiceRefundController extends Controller
{
    public function store(Invoice $invoice, IssueInvoiceRefund $issueRefund): RedirectResponse
    {
        Gate::authorize('refund', $invoice);           // Authorization (InvoicePolicy)

        $issueRefund->handle($invoice);                 // Application orchestration

        return to_route('invoices.show', $invoice);     // HTTP response
    }
}
```

The controller does only HTTP work and authorization. The refund rules and the transaction live in `IssueInvoiceRefund` (see [Actions Versus Services](#actions-vs-services)).

<a name="layers-anti-pattern"></a>
### Anti-Pattern

```php
public function refund(Request $request, $id)
{
    $invoice = Invoice::find($id);

    if ($invoice->refunded_at === null) {
        Http::post(config('services.payments.url').'/refunds', ['invoice' => $invoice->number]);
        $invoice->update(['refunded_at' => now()]);
    }

    return response()->json($invoice);
}
```

This method has no authorization, so any user can refund any invoice. It has no lock, so two concurrent requests can both refund. It makes a network call with no timeout and no failure handling. It returns the raw model, which includes the IBAN. None of it can be reused by a command or job without copying.

<a name="layers-checklist"></a>
### Checklist

- [ ] Each sensitive operation has exactly one server-side authorization point (Policy or Gate).
- [ ] Business invariants live in one place, not duplicated across controllers, commands and tools.
- [ ] External calls go through an adapter or client class that sets timeouts and defines failure handling.
- [ ] Responses go through an API Resource or a view, never a raw model with hidden fields exposed.
- [ ] Plain CRUD was not split into extra layers without a reason.

<a name="thin-controllers"></a>
## Thin Controllers

<a name="thin-controllers-principle"></a>
### Principle

A controller translates an HTTP request into a call to application code and turns the result into an HTTP response. It validates through a Form Request, authorizes through a Policy, delegates the use case, and formats the output. It does not hold business rules.

<a name="thin-controllers-why-it-exists"></a>
### Why It Exists

Fat controllers cause three concrete problems. Their logic can only be tested through HTTP. Their rules are copied when another entry point needs them: this project already has AI tools (`app/Ai/Tools/RefundInvoice.php`) and console commands that act on the same models. And reviewers cannot see an authorization gap that is buried in the middle of 80 lines of mixed concerns.

<a name="thin-controllers-when-to-apply"></a>
### When To Apply

- Any controller method that changes state beyond a single validated `create`/`update`.
- Any controller method longer than roughly 20 lines or with more than one branch on business state.
- Any logic that a second entry point (command, job, AI tool, webhook) needs now.

<a name="thin-controllers-when-not-to-apply"></a>
### When Not To Apply

- Simple resource controllers. `store()` that validates, creates and redirects is fine inline:

```php
public function store(StoreTeamRequest $request): RedirectResponse
{
    $team = $request->user()->ownedTeams()->create($request->validated());

    return to_route('teams.show', $team);
}
```

- Query composition for one specific page (filters, eager loading, pagination). Unless the same query is reused, it can stay in the controller or move to a model scope. It does not need an action.

<a name="thin-controllers-correct-example"></a>
### Correct Example

```php
class TeamInvitationController extends Controller
{
    public function store(StoreTeamInvitationRequest $request, Team $team, InviteTeamMember $inviteMember): RedirectResponse
    {
        Gate::authorize('inviteMember', $team);

        $inviteMember->handle(
            team: $team,
            inviter: $request->user(),
            inviteeId: $request->integer('user_id'),
        );

        return to_route('teams.show', $team);
    }
}
```

Validation is in the Form Request and authorization is in the Policy. Rules like "no duplicate pending invitation" and "notify the invitee" live in the action, where a test can call them directly.

<a name="thin-controllers-anti-pattern"></a>
### Anti-Pattern

```php
public function store(Request $request, Team $team): RedirectResponse
{
    $request->validate(['user_id' => 'required|exists:users,id']);

    if ($team->owner_id !== auth()->id()) {
        abort(403);
    }

    if (TeamInvitation::where('team_id', $team->id)->where('invitee_id', $request->user_id)->exists()) {
        return back()->withErrors(['user_id' => 'Already invited.']);
    }

    $invitation = TeamInvitation::create($request->all() + ['team_id' => $team->id]);
    Mail::to(User::find($request->user_id))->send(new InvitationMail($invitation));

    return to_route('teams.show', $team);
}
```

Authorization is done inline instead of in `TeamPolicy`, so it drifts from the policy. `$request->all()` mass-assigns untrusted input. The duplicate check is a check-then-insert race with no unique constraint behind it. The mail is sent synchronously inside the request.

<a name="thin-controllers-checklist"></a>
### Checklist

- [ ] Input comes from a Form Request (`$request->validated()`, `$request->safe()`), never `$request->all()`.
- [ ] Authorization uses `Gate::authorize()` or the Form Request's `authorize()` with a Policy, not inline `if` checks on IDs.
- [ ] State-changing logic used by more than one entry point lives in an action.
- [ ] The controller method has no `DB::transaction()` and no `Http::` calls. Those belong to the action or an adapter.
- [ ] The response is a redirect, a view or an API Resource.

<a name="actions-vs-services"></a>
## Actions Versus Services

<a name="actions-vs-services-principle"></a>
### Principle

An **action** is a plain class in `app/Actions/<Area>` with one public `handle()` method that runs one use case, for example `IssueInvoiceRefund` or `InviteTeamMember`. A **service** is a class that groups related operations around one capability or integration, for example a payment gateway client or a pricing calculator. Default to actions for use cases. Use services for reusable capabilities that several actions share.

<a name="actions-vs-services-why-it-exists"></a>
### Why It Exists

Unscoped `InvoiceService` classes grow into 1,500-line god objects that need ten constructor dependencies to call one method. They mix unrelated use cases, and their tests need heavy mocking. One action per use case keeps dependencies explicit and lets each one be tested on its own. Services still exist, but for real shared capabilities, not as a dumping ground.

<a name="actions-vs-services-when-to-apply"></a>
### When To Apply

Use an **action** when:

- The use case changes state and has invariants, a transaction or side effects (jobs, notifications).
- Two or more entry points run the same use case.

Use a **service** when:

- Several actions need the same capability (talking to Stripe, computing taxes).
- The capability has internal state or configuration (credentials, base URL, timeout) that is resolved once from the container.

<a name="actions-vs-services-when-not-to-apply"></a>
### When Not To Apply

- Do not create an action for a single `Model::create($validated)` call. The controller is fine.
- Do not create an action that only forwards to another action or service. That adds indirection with no responsibility.
- Do not put HTTP concerns in an action. It must not take a `Request`, return a `Response` or call `abort()`. Pass typed values instead.
- Do not make actions "smart" about their caller. If two entry points need different behavior, write two actions or pass an explicit parameter.

<a name="actions-vs-services-correct-example"></a>
### Correct Example

```php
namespace App\Actions\Billing;

use App\Exceptions\InvoiceAlreadyRefunded;
use App\Jobs\SendRefundToGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class IssueInvoiceRefund
{
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->isRefunded()) {
                throw InvoiceAlreadyRefunded::for($locked);
            }

            $locked->update(['refunded_at' => now()]);

            SendRefundToGateway::dispatch($locked)->afterCommit();

            return $locked;
        });
    }
}
```

The action owns the invariant (no double refund, enforced with a row lock) and the transaction boundary. The network call to the gateway runs in a job after commit, so it stays outside the transaction (see [Database and Concurrency](04-database-and-concurrency.md)).

<a name="actions-vs-services-anti-pattern"></a>
### Anti-Pattern

```php
class InvoiceService
{
    public function __construct(
        private PaymentGateway $payments,
        private Mailer $mailer,
        private PdfRenderer $pdf,
        private TaxCalculator $taxes,
        private AuditLogger $audit,
    ) {}

    public function refund(Request $request, int $id) { /* ... */ }
    public function download(int $id) { /* ... */ }
    public function recalculate(int $id) { /* ... */ }
    public function sendReminder(int $id) { /* ... */ }
}
```

Every caller depends on five collaborators even when it uses one of them. `refund()` takes a `Request`, so commands and jobs cannot call it. The class has no single reason to change, and its test setup has to mock everything.

<a name="actions-vs-services-checklist"></a>
### Checklist

- [ ] Each action has one public method (`handle()`) and a name that is a verb phrase for the use case.
- [ ] Actions are resolved from the container (method or constructor injection), not created with `new`, so their dependencies can be swapped in tests.
- [ ] Actions accept typed values or models, never `Request`, and never call `abort()` or return responses.
- [ ] Business failures are reported with named exceptions (`InvoiceAlreadyRefunded`) that the HTTP layer maps to status codes.
- [ ] Services wrap one capability and have fewer than about five dependencies.
- [ ] Each action has a feature or unit test that calls `handle()` directly.

<a name="dependency-direction"></a>
## Dependency Direction and Contracts

<a name="dependency-direction-principle"></a>
### Principle

Dependencies point from volatile code to stable code: HTTP depends on application code, application code depends on domain code and contracts, and infrastructure implements those contracts. Domain and application code never import controllers, requests or concrete third-party SDKs. When consumers must stay independent of interchangeable implementations, they depend on an interface, and the service container binds the implementation.

<a name="dependency-direction-why-it-exists"></a>
### Why It Exists

If an action calls a specific vendor's SDK directly, replacing the vendor or testing the action means editing every caller. If a model imports an HTTP Request, it cannot be used from a queue worker. Getting the direction right keeps the cost of change local: replacing a CAPTCHA provider touches one adapter and one config value, not every form.

<a name="dependency-direction-when-to-apply"></a>
### When To Apply

- There are two or more real implementations today (Turnstile and hCaptcha, Stripe and Mollie), or a test double is needed because the real one calls the network.
- The selection depends on configuration or environment.
- A module exposes behavior to other modules and wants to hide how it works.

<a name="dependency-direction-when-not-to-apply"></a>
### When Not To Apply

- One implementation and no realistic second one. Inject the concrete class. The container auto-resolves it, and tests can still swap it with `$this->app->instance()` or `$this->mock()`.
- Framework services that already have contracts and fakes. `Http::fake()`, `Queue::fake()`, `Mail::fake()` and `Storage::fake()` already make code testable, so do not wrap them in your own interface.
- Eloquent models. They are the domain objects in a Laravel app. Do not hide them behind an interface.

<a name="dependency-direction-correct-example"></a>
### Correct Example

```php
// app/Modules/Payments/Contracts/PaymentGateway.php
interface PaymentGateway
{
    public function refund(string $reference, int $amountCents): RefundResult;
}

// app/Modules/Payments/PaymentsServiceProvider.php
public function register(): void
{
    $this->app->singleton(PaymentGateway::class, fn (): PaymentGateway => match (config('payments.driver')) {
        'stripe' => new StripeGateway(config('payments.stripe.secret'), timeout: 5),
        'mollie' => new MollieGateway(config('payments.mollie.key'), timeout: 5),
        default => throw new InvalidArgumentException('Unsupported payment driver.'),
    });
}

// app/Jobs/SendRefundToGateway.php
public function handle(PaymentGateway $payments): void
{
    $payments->refund($this->invoice->number, $this->invoice->amount_cents);
}
```

The job depends only on the contract. The provider is the single place that knows which concrete class exists, and an unknown driver fails loudly instead of falling back to something.

<a name="dependency-direction-anti-pattern"></a>
### Anti-Pattern

```php
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        return [
            'number' => $this->number,
            'refund_status' => $stripe->refunds->retrieve($this->refund_id)->status,
        ];
    }
}
```

The presentation layer depends on a vendor SDK. Serializing a collection makes one network call per row. `env()` returns `null` once config is cached. Tests cannot run without the network.

<a name="dependency-direction-checklist"></a>
### Checklist

- [ ] Actions, jobs and models never import `Illuminate\Http\Request` or controllers.
- [ ] Vendor SDKs are used only inside one adapter class per vendor.
- [ ] Interfaces exist only where there is more than one implementation, or where a network-bound test double is needed.
- [ ] Implementations are bound in a service provider from config, and unknown drivers throw.
- [ ] `env()` is called only in `config/*.php`.
- [ ] An architecture test enforces the most important direction rules (see [Avoiding Circular and Cross-Module Dependencies](#cross-module)).

<a name="simplicity"></a>
## Simplicity Before Abstraction

<a name="simplicity-principle"></a>
### Principle

Add a repository, interface, factory, event bus or extra layer only when you can name the concrete problem it solves today. Otherwise use what Laravel already provides: Eloquent, the container, events, queues and the framework fakes.

<a name="simplicity-why-it-exists"></a>
### Why It Exists

Speculative abstraction is not free. Every `UserRepositoryInterface` that wraps `User::find()` adds a file, an indirection and a binding. It also hides useful Eloquent features (scopes, eager loading, `lockForUpdate()`) behind a lowest-common-denominator API. Teams then add `findWithRelations()`, `findForUpdate()` and so on until the repository is just Eloquent with a different name. Events used for in-process control flow hide the order of execution and make a failure in a listener look like it came from elsewhere.

<a name="simplicity-when-to-apply"></a>
### When To Apply

Here is a concrete justification for each abstraction:

| Abstraction | Justified when |
| --- | --- |
| Interface / contract | Two or more implementations exist now, or the real one is network-bound and needs a deterministic fake. |
| Repository | The data source is **not** Eloquent (external API, search index), or one complex query is reused in many places and a scope cannot express it. |
| Factory class | Building the object needs runtime decisions that the container cannot express. |
| Domain event + listeners | Several independent reactions happen, owned by different modules, and the emitter must not know about them (for example `InvoiceRefunded` triggers a notification and an analytics export). |
| Extra layer / module | The feature's cohesion, size or variability meets the threshold in [Domain Modules](02-domain-modules.md). |

<a name="simplicity-when-not-to-apply"></a>
### When Not To Apply

- "We might switch databases someday." Eloquent and the query builder already abstract the driver.
- "It is more testable." With `RefreshDatabase` and model factories, real queries are tested directly and give better coverage than a mocked repository.
- "Events decouple things." An event with exactly one listener that the emitter depends on for correctness is a method call with extra steps. Call the action directly.
- "It's Clean Architecture." Patterns follow from problems, not the other way around.

<a name="simplicity-correct-example"></a>
### Correct Example

```php
// A reusable query lives in a model scope, not a repository.
#[Scope]
protected function overdue(Builder $query): void
{
    $query->whereNull('paid_at')->where('due_at', '<', now());
}

// Usage keeps the full Eloquent API available.
$invoices = Invoice::query()->overdue()->with('user')->lockForUpdate()->get();
```

The `#[Scope]` attribute is the convention already used in `app/Models/Document.php`. It names the rule once and keeps eager loading and locking available.

<a name="simplicity-anti-pattern"></a>
### Anti-Pattern

```php
interface InvoiceRepositoryInterface
{
    public function find(int $id): ?Invoice;
    public function save(Invoice $invoice): void;
}

class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function find(int $id): ?Invoice { return Invoice::find($id); }
    public function save(Invoice $invoice): void { $invoice->save(); }
}
```

There is one implementation, it adds no behavior, and it removes access to `lockForUpdate()` and eager loading. Callers still get Eloquent models back, so the "abstraction" leaks anyway.

<a name="simplicity-checklist"></a>
### Checklist

- [ ] Every new interface has two or more implementations, or a documented network-bound test need.
- [ ] No repository wraps Eloquent one-to-one.
- [ ] Reusable query rules are model scopes (`#[Scope]`) or query objects with a clear name.
- [ ] Events are used only when multiple independent reactions exist. Correctness-critical steps are direct calls inside the action.
- [ ] The pull request description states the problem that justifies any new layer.

<a name="modular-monolith"></a>
## Modular Monolith Over Microservices

<a name="modular-monolith-principle"></a>
### Principle

Build one deployable Laravel application with clear internal modules. Extract a separate service only when there is a measured, independent need for scaling, deployment, technology or ownership that the monolith cannot meet.

<a name="modular-monolith-why-it-exists"></a>
### Why It Exists

Microservices turn function calls into network calls, and every network call can time out, fail halfway or arrive twice. A refund that was one `DB::transaction()` becomes a distributed saga with compensations, retries and eventual consistency. That cost only pays off when teams or workloads really are independent. Without that need, a modular monolith gives most of the organizational benefit for none of the distributed-systems cost.

<a name="modular-monolith-when-to-apply"></a>
### When To Apply

Stay in the monolith (the default) when:

- One team owns the codebase, or teams can coordinate deployments.
- Workloads share the database and need transactional consistency.
- Scaling needs are met by horizontal web workers plus queue workers. Laravel queues already separate heavy background work.

Consider extracting a service only when **at least one** of these is measured:

- One workload needs very different scaling or hardware (for example GPU inference, video transcoding).
- One component must deploy on a different cadence or has a different compliance boundary (for example card data under PCI scope).
- A separate team fully owns it, including its data, and the module boundary has already been stable inside the monolith.

<a name="modular-monolith-when-not-to-apply"></a>
### When Not To Apply

- Do not split for "clean separation." Use modules and architecture tests for that.
- Do not split because one module is large. Large is not the same as independent.
- Do not split before the boundary has proven stable as an internal module. A bad in-process boundary is cheap to move, but a bad network boundary is expensive.

<a name="modular-monolith-correct-example"></a>
### Correct Example

```text
app/
├── Actions/Billing/IssueInvoiceRefund.php
├── Http/Controllers/InvoiceRefundController.php
├── Models/Invoice.php
└── Modules/
    ├── Captcha/        # contract + drivers + rule + provider
    └── Payments/       # PaymentGateway contract + Stripe/Mollie adapters
```

```shell
php artisan queue:work --queue=payments   # heavy work scales as separate worker processes
```

Modules are folders with a small public surface inside one deployable application. Heavy background work scales through queue workers, not separate services.

<a name="modular-monolith-anti-pattern"></a>
### Anti-Pattern

```php
// "Invoice service" called over HTTP from the same team's main app.
$response = Http::post('http://invoices.internal/api/invoices/'.$id.'/refund');

if ($response->failed()) {
    // Was the refund applied before the timeout? Nobody knows.
}
```

A transactional operation now has an unknown outcome on timeout. It needs idempotency keys, retries and reconciliation, and nothing about the workload required any of that.

<a name="modular-monolith-checklist"></a>
### Checklist

- [ ] New features land as folders or modules inside the existing application.
- [ ] Any extraction proposal cites a measured scaling, deployment, compliance or ownership need.
- [ ] The module boundary existed and was enforced by architecture tests before extraction.
- [ ] Background workloads use dedicated queues and workers before anyone considers a separate service.

<a name="cross-module"></a>
## Avoiding Circular and Cross-Module Dependencies

<a name="cross-module-principle"></a>
### Principle

A module may use another module only through that module's public surface (contracts, actions, data objects, events it publishes). It must never use another module's internal classes, and two modules must never depend on each other. If two modules both need something, move it to a third, lower-level place or invert the dependency with a contract or event.

<a name="cross-module-why-it-exists"></a>
### Why It Exists

Cycles make modules impossible to change, test or extract on their own. If `Billing` calls `Teams\Internal\SeatCounter` and `Teams` calls `Billing\Internal\PlanLimits`, a change to either can break both, and neither can be loaded or tested alone. Cross-module access to internals also bypasses invariants the owning module enforces, such as authorization or locking.

<a name="cross-module-when-to-apply"></a>
### When To Apply

- As soon as `app/Modules/<Name>` exists (see [Domain Modules](02-domain-modules.md)).
- For layer direction even without modules: `App\Models`, `App\Actions` and `App\Jobs` must not depend on `App\Http`.

<a name="cross-module-when-not-to-apply"></a>
### When Not To Apply

- Shared framework-level code (`App\Models\User`, `App\Enums`, `App\Support`) is meant to be used everywhere. Treat it as the stable base layer, and keep it free of module-specific logic so it does not create hidden cycles.
- Tests may reach module internals to test them. The rule applies to production code.
- Do not invent a contract or event for a one-way dependency that is already acyclic. A plain call to a public action is fine.

<a name="cross-module-correct-example"></a>
### Correct Example

```php
// tests/Feature/ArchitectureTest.php
arch('application code does not depend on the HTTP layer')
    ->expect(['App\Models', 'App\Actions', 'App\Jobs', 'App\Modules'])
    ->not->toUse('App\Http');

arch('billing uses captcha only through its public surface')
    ->expect('App\Modules\Billing')
    ->not->toUse('App\Modules\Captcha\Drivers');

arch('modules do not depend on each other cyclically')
    ->expect('App\Modules\Captcha')
    ->not->toUse('App\Modules\Billing');
```

Pest architecture tests (`arch()`, `toUse()`, `not->toUse()`) turn the boundary rules into failing tests instead of review comments.

<a name="cross-module-anti-pattern"></a>
### Anti-Pattern

```php
namespace App\Modules\Teams;

use App\Modules\Billing\Internal\PlanLimits;   // internal class of another module

class AddTeamMember
{
    public function handle(Team $team, User $user): void
    {
        if ((new PlanLimits)->seatsFor($team) <= $team->users()->count()) { /* ... */ }
    }
}

// ...while Billing\Internal\PlanLimits itself imports App\Modules\Teams\TeamSeatCounter.
```

`Teams` depends on a `Billing` internal, and `Billing` depends back on `Teams`. Neither module can change on its own. The fix is a `Billing\Contracts\SeatQuota` contract that `Teams` consumes, with `Billing` reading seat counts through the public `Team` model rather than a `Teams` internal.

<a name="cross-module-checklist"></a>
### Checklist

- [ ] Every cross-module import targets a public class (contract, action, data object, event).
- [ ] No pair of modules imports from each other.
- [ ] `App\Models`, `App\Actions`, `App\Jobs` and `App\Modules` do not use `App\Http`.
- [ ] Architecture tests encode the boundaries and run in `composer test`.
- [ ] A new cross-module dependency is called out in the pull request description.
