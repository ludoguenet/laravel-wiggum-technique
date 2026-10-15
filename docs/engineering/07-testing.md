# Testing

- [Introduction](#introduction)
- [Test Strategy](#test-strategy)
- [Feature Tests With Factories](#feature-tests-with-factories)
- [Authorization and Validation Tests](#authorization-and-validation-tests)
- [Failure Paths and Transactions](#failure-paths-and-transactions)
- [Concurrency and Idempotency](#concurrency-and-idempotency)
- [Faking External Boundaries](#faking-external-boundaries)
- [Frontend Tests](#frontend-tests)
- [Regression and Architecture Tests](#regression-and-architecture-tests)

<a name="introduction"></a>
## Introduction

This document explains how we design, write, and run automated tests. It expands section 11 of `.ai/guidelines/engineering-standards.md` and stays consistent with the `testing-best-practices` skill (`.claude/skills/testing-best-practices`). When this document and an established project convention disagree, the convention wins until the team decides otherwise.

The suite uses Pest 5 with `pestphp/pest-plugin-laravel`. `tests/Pest.php` binds `Tests\TestCase` and `RefreshDatabase` to every file in `tests/Feature`, and `phpunit.xml` runs the suite against an in-memory SQLite database. Examples use the project's real models (`User`, `Invoice`, `Order`, `Team`) and factory states (`User::factory()->admin()`, `Invoice::factory()->refunded()`). Routes, actions, and contracts such as `PlaceOrder`, `PaymentGateway`, or `invoices.refund` are illustrative.

Related documents:

- [Architecture](01-architecture.md) — where actions, policies, and contracts live.
- [Security](08-security.md) — the boundaries these tests must prove.
- [AI-Assisted Development](11-ai-assisted-development.md) — how generated code and generated tests are reviewed.
- The other documents in this folder, `01-architecture.md` through `11-ai-assisted-development.md`.

<a name="test-strategy"></a>
## Test Strategy

<a name="test-strategy-principle"></a>
### Principle

Test observable behavior and business invariants at the lowest boundary that can detect the defect. Choose the layer by boundary, not by habit:

| Layer | Boundary exercised | Default location |
| --- | --- | --- |
| Unit | Pure PHP logic with no framework, database, or I/O | `tests/Unit` |
| Feature | An HTTP request, command, job, or action through the container and a real test database | `tests/Feature` |
| Integration | A real external system (sandbox API, real Redis) behind a contract | `tests/Feature`, grouped `integration` |
| Browser / end-to-end | Real browser, JavaScript, and backend together | `tests/Browser` (not installed) |

<a name="test-strategy-why-it-exists"></a>
### Why It Exists

A suite that asserts implementation details breaks on every refactor and is eventually ignored. A suite that only covers the happy path passes while another customer's invoice leaks. Choosing the boundary deliberately gives fast tests that still fail for the right reason.

<a name="test-strategy-when-to-apply"></a>
### When To Apply

- Every behavior or logic change: identify each decision (branch, validation, calculation, authorization) and cover it.
- Behavior reachable through a request: write a feature test first.
- Logic that does not touch the framework (a price calculator, a parser): a unit test is enough.

<a name="test-strategy-when-not-to-apply"></a>
### When Not To Apply

- Pure copy, styling, or layout-only changes do not need new tests.
- Do not test framework behavior (that `required` rejects an empty string). Test the project's use of it.
- Real-browser tests require `pestphp/pest-plugin-browser` and Playwright, neither of which is installed. Adding them is a dependency change that needs approval; until then, cover the behavior with feature tests.

<a name="test-strategy-correct-example"></a>
### Correct Example

Run the narrowest set that covers the change, then ask for the full suite before merging:

```bash
php artisan test --compact tests/Feature/HelpdeskAssistantTest.php
php artisan test --compact --filter='refuses to refund another customer'
vendor/bin/pest --dirty            # only tests touched by uncommitted changes
php artisan test --compact         # full suite, before completion
```

The file path and `--filter` keep the feedback loop short; the full run proves nothing else broke.

<a name="test-strategy-anti-pattern"></a>
### Anti-Pattern

```php
test('refund calls the repository', function (): void {
    $repository = Mockery::mock(InvoiceRepository::class);
    $repository->shouldReceive('markRefunded')->once();

    (new RefundInvoiceAction($repository))->handle(Invoice::factory()->make());
});
```

This asserts that a method was called, not that the invoice is refunded. It fails after a harmless refactor and passes when `markRefunded` is broken.

<a name="test-strategy-checklist"></a>
### Checklist

- [ ] Every changed decision (branch, validation, calculation, authorization) has a test.
- [ ] Each test asserts an outcome (response, persisted state, dispatched message), not a call sequence.
- [ ] Unit tests contain no database, HTTP, or container access.
- [ ] No browser test or new test dependency was added without approval.
- [ ] The narrow tests were run and passed; the full suite was run or its omission was disclosed.

<a name="feature-tests-with-factories"></a>
## Feature Tests With Factories

<a name="feature-tests-with-factories-principle"></a>
### Principle

Build test data with model factories and their states, owned by the test that uses it. Act through the real entry point and assert both the response and the persisted state.

<a name="feature-tests-with-factories-why-it-exists"></a>
### Why It Exists

Hand-written `Model::create([...])` arrays drift from the schema and hide which attributes matter. Shared fixtures make tests depend on each other's data. Asserting only the status code lets a `200` response hide a write that never happened.

<a name="feature-tests-with-factories-when-to-apply"></a>
### When To Apply

- Any test that needs a model: use `Model::factory()`, relationships with `for()` / `has()`, and named states such as `admin()` or `refunded()`.
- Set explicitly only the attributes the test asserts on, so the reader sees what matters.
- When a state is needed in several files, add it to the factory instead of repeating attributes.

<a name="feature-tests-with-factories-when-not-to-apply"></a>
### When Not To Apply

- A unit test of a pure value object needs no factory; construct it directly.
- Use `make()` instead of `create()` when the code under test never queries the database.
- Do not add a factory state for a combination used by a single test; inline attributes are clearer there.

<a name="feature-tests-with-factories-correct-example"></a>
### Correct Example

```php
use App\Models\Invoice;
use App\Models\User;

test('a customer can refund their own invoice', function (): void {
    $customer = User::factory()->create();
    $invoice = Invoice::factory()->for($customer)->create(['amount_cents' => 4_500]);

    $response = $this->actingAs($customer)->post(route('invoices.refund', $invoice));

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($invoice->fresh()->isRefunded())->toBeTrue();
});
```

The factory states the ownership explicitly, and the test asserts the persisted outcome, not just the redirect.

<a name="feature-tests-with-factories-anti-pattern"></a>
### Anti-Pattern

```php
beforeEach(function (): void {
    $this->seed(HelpdeskSeeder::class);
});

test('refund works', function (): void {
    $this->actingAs(User::first())->post('/invoices/1/refund')->assertOk();
});
```

The test depends on seeder contents and auto-increment IDs, uses a hard-coded URL, and never checks that the invoice was refunded.

<a name="feature-tests-with-factories-checklist"></a>
### Checklist

- [ ] Models come from factories; existing states are reused before adding attributes by hand.
- [ ] Each test creates the data it needs; no reliance on seeders or IDs.
- [ ] URLs use `route()`.
- [ ] Assertions cover both the response and the database state.
- [ ] `fresh()` is used before asserting on a model the request modified.

<a name="authorization-and-validation-tests"></a>
## Authorization and Validation Tests

<a name="authorization-and-validation-tests-principle"></a>
### Principle

Every protected endpoint is tested for: a guest, an outsider (another user or tenant), an insufficient role, invalid input, and the valid case. Assert the full permission matrix against the policy, and keep one HTTP test per refused case to prove the endpoint actually calls it.

<a name="authorization-and-validation-tests-why-it-exists"></a>
### Why It Exists

Missing authorization does not break the feature; the owner's happy path still passes. Only a test that acts as another user detects an IDOR. Likewise, a validation rule removed by mistake is invisible until bad data reaches the database.

<a name="authorization-and-validation-tests-when-to-apply"></a>
### When To Apply

- Any route, action, job, or AI tool that reads or writes user-owned data.
- Any Form Request rule that encodes a business contract (allowed values, ranges, formats).
- Use a Pest dataset when several invalid inputs share the same setup and assertions.

<a name="authorization-and-validation-tests-when-not-to-apply"></a>
### When Not To Apply

- Public, read-only endpoints with no user data need no outsider test.
- Do not duplicate a full rule matrix in both a rule-class test and the endpoint test; keep the matrix in the rule test and one case at the endpoint.
- Do not assert that a rules array contains `'required'`; send invalid input and assert the error.

<a name="authorization-and-validation-tests-correct-example"></a>
### Correct Example

```php
test('an outsider cannot refund another customer\'s invoice', function (): void {
    $invoice = Invoice::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson(route('invoices.refund', $invoice))
        ->assertNotFound();

    expect($invoice->fresh()->isRefunded())->toBeFalse();
});

test('refund requests reject invalid input', function (array $payload, string $field): void {
    $invoice = Invoice::factory()->create();

    $this->actingAs($invoice->user)
        ->postJson(route('invoices.refund', $invoice), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing reason' => [[], 'reason'],
    'reason too long' => [['reason' => str_repeat('a', 501)], 'reason'],
    'unknown channel' => [['reason' => 'Damaged', 'channel' => 'cash'], 'channel'],
]);
```

The outsider test asserts `404` (the record's existence is not confirmed) and that no state changed. The dataset names each invalid case so a failure reads as a sentence.

<a name="authorization-and-validation-tests-anti-pattern"></a>
### Anti-Pattern

```php
test('owner can refund', function (): void {
    $invoice = Invoice::factory()->create();

    $this->actingAs($invoice->user)
        ->postJson(route('invoices.refund', $invoice), ['reason' => 'Damaged'])
        ->assertOk();
});
```

Only the owner is tested. Deleting the policy check leaves the suite green.

<a name="authorization-and-validation-tests-checklist"></a>
### Checklist

- [ ] Guest, outsider, and insufficient-role cases exist for each protected endpoint.
- [ ] Cross-tenant tests assert the intended status (`404` when existence must not leak) and unchanged state.
- [ ] The policy's full matrix is tested directly (`$user->can('refund', $invoice)`), like `tests/Feature/TeamAuthorizationTest.php`.
- [ ] Invalid inputs use a named dataset and assert the specific field (and message when it is part of the UX).
- [ ] An unexpected key in the payload (`is_admin`, `user_id`) is tested and ignored or rejected.

<a name="failure-paths-and-transactions"></a>
## Failure Paths and Transactions

<a name="failure-paths-and-transactions-principle"></a>
### Principle

For every operation that writes more than once, test that a failure midway leaves no partial state, and that the failure is reported and rendered safely.

<a name="failure-paths-and-transactions-why-it-exists"></a>
### Why It Exists

A missing `DB::transaction()` produces orders without stock movements, or stock decremented for orders that do not exist. The happy path never reveals it; only a forced failure does.

<a name="failure-paths-and-transactions-when-to-apply"></a>
### When To Apply

- Actions that perform several writes that must succeed or fail together.
- Code paths that catch exceptions: assert what is reported (`Exceptions::fake()`) and what the client sees.
- Provider failures: timeouts, `5xx`, malformed payloads (see [Faking External Boundaries](#faking-external-boundaries)).

<a name="failure-paths-and-transactions-when-not-to-apply"></a>
### When Not To Apply

- A single `create()` or `update()` is already atomic; no rollback test is needed.
- Do not test that the database engine rolls back; test that your code wraps the right writes.
- Prefer forcing failure with real data (a constraint violation) over mocking Eloquent.

<a name="failure-paths-and-transactions-correct-example"></a>
### Correct Example

```php
use Illuminate\Database\UniqueConstraintViolationException;

test('a failed order leaves the stock untouched', function (): void {
    $customer = User::factory()->create();
    $tour = Tour::factory()->create(['tickets_left' => 5]);
    Order::factory()->create(['reference' => 'ORD-1']);

    expect(fn () => app(PlaceOrder::class)->handle($customer, $tour, reference: 'ORD-1'))
        ->toThrow(UniqueConstraintViolationException::class);

    expect($tour->fresh()->tickets_left)->toBe(5)
        ->and(Order::count())->toBe(1);
});
```

The duplicate reference makes the second write fail for real; the assertions prove the first write was rolled back.

<a name="failure-paths-and-transactions-anti-pattern"></a>
### Anti-Pattern

```php
test('order fails gracefully', function (): void {
    $this->withoutExceptionHandling();

    expect(fn () => app(PlaceOrder::class)->handle(...))->toThrow(Exception::class);
});
```

Catching any `Exception` passes on unrelated errors, `withoutExceptionHandling()` changes the behavior under test, and nothing checks the data left behind.

<a name="failure-paths-and-transactions-checklist"></a>
### Checklist

- [ ] Each multi-write operation has a test that forces a mid-operation failure.
- [ ] The test asserts every table the operation touches is unchanged.
- [ ] The expected exception class is specific.
- [ ] Reported exceptions are asserted with `Exceptions::fake()` and `Exceptions::assertReported()`.
- [ ] HTTP failure responses contain no stack trace, SQL, or internal message.

<a name="concurrency-and-idempotency"></a>
## Concurrency and Idempotency

<a name="concurrency-and-idempotency-principle"></a>
### Principle

Test the guarantee that protects the invariant, not the race itself: the unique constraint, the conditional update, the idempotency key, or the lock. A single-process test cannot interleave two requests reliably, so verify the mechanism and the second-request outcome.

<a name="concurrency-and-idempotency-why-it-exists"></a>
### Why It Exists

Double-clicks, retries, and webhooks delivered twice create duplicate refunds and oversold stock. Application-level `exists()` checks pass every sequential test and still fail under real concurrency.

<a name="concurrency-and-idempotency-when-to-apply"></a>
### When To Apply

- Payments, refunds, stock, seats, invitations, and any operation a client may retry.
- Webhook and queue handlers, which are delivered at least once.
- Any invariant that a database constraint or lock is supposed to hold.

<a name="concurrency-and-idempotency-when-not-to-apply"></a>
### When Not To Apply

- Idempotent-by-nature operations (setting a name to a value) need no duplicate test.
- Do not try to reproduce a true race with `sleep()` or parallel processes in the default suite; it is slow and flaky. The in-memory SQLite test database also does not reproduce MySQL/PostgreSQL row locking; validate `lockForUpdate()` behavior with a dedicated integration check against the production engine when it is critical.

<a name="concurrency-and-idempotency-correct-example"></a>
### Correct Example

```php
test('the same refund request processed twice refunds once', function (): void {
    $invoice = Invoice::factory()->create();
    $headers = ['Idempotency-Key' => 'b7a1c2d4-refund'];

    $first = $this->actingAs($invoice->user)->postJson(route('invoices.refund', $invoice), [], $headers);
    $second = $this->actingAs($invoice->user)->postJson(route('invoices.refund', $invoice), [], $headers);

    $first->assertCreated();
    $second->assertCreated()->assertJson($first->json());
    expect(Refund::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('the database rejects a second refund row for the same invoice', function (): void {
    $invoice = Invoice::factory()->create();
    Refund::factory()->for($invoice)->create();

    expect(fn () => Refund::factory()->for($invoice)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});
```

The first test proves the replay contract; the second proves the constraint that still holds if two requests slip past the application check.

<a name="concurrency-and-idempotency-anti-pattern"></a>
### Anti-Pattern

```php
test('cannot refund twice', function (): void {
    $invoice = Invoice::factory()->refunded()->create();

    $this->actingAs($invoice->user)->postJson(route('invoices.refund', $invoice))->assertForbidden();
});
```

This only tests the sequential `isRefunded()` check. Two concurrent requests both see `refunded_at = null`, and nothing in the suite proves a constraint or atomic update stops the second one.

<a name="concurrency-and-idempotency-checklist"></a>
### Checklist

- [ ] The invariant is backed by a constraint, atomic update, lock, or idempotency key, and that mechanism has a test.
- [ ] A replayed request with the same key returns the original result and creates no new row.
- [ ] A replayed request with a different payload but the same key is rejected.
- [ ] Queue jobs and webhook handlers are tested by running the handler twice.
- [ ] Engine-specific locking is verified outside SQLite when it carries business risk, or the gap is disclosed.

<a name="faking-external-boundaries"></a>
## Faking External Boundaries

<a name="faking-external-boundaries-principle"></a>
### Principle

Use framework fakes for every external boundary (HTTP, queue, events, mail, notifications, storage, time), fake the exact endpoints each test uses, and forbid stray requests. When a contract has several implementations, run one shared contract test against each of them.

<a name="faking-external-boundaries-why-it-exists"></a>
### Why It Exists

Unfaked HTTP calls make tests slow, flaky, and capable of charging a real card. A bare `Http::fake()` accepts any URL and hides a request to the wrong host. Without contract tests, the in-memory fake drifts from the real adapter and tests pass against behavior production never has.

<a name="faking-external-boundaries-when-to-apply"></a>
### When To Apply

- Any test that reaches code using `Http`, `Queue`, `Bus`, `Event`, `Mail`, `Notification`, or `Storage`.
- Provider failure modes: `Http::failedConnection()`, `5xx`, and invalid JSON.
- Interchangeable implementations (payment gateways, CAPTCHA providers, AI providers) bound through the container.

<a name="faking-external-boundaries-when-not-to-apply"></a>
### When Not To Apply

- Do not fake the database or the query builder; use the real test database.
- Do not fake events globally when a factory relies on model events; create records first, or pass explicit event classes to `Event::fake([...])`.
- Integration tests against a real sandbox belong in a group excluded from the default run (`--exclude-group=integration`), not in every CI job.

<a name="faking-external-boundaries-correct-example"></a>
### Correct Example

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('a declined charge notifies the customer and stores nothing', function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.payments.test/v1/charges' => Http::response(['error' => 'card_declined'], 402)]);
    Notification::fake();
    $customer = User::factory()->create();

    $this->actingAs($customer)->postJson(route('orders.store'), ['product' => 'tour-42'])
        ->assertStatus(402);

    Http::assertSentCount(1);
    Notification::assertSentTo($customer, PaymentDeclined::class);
    expect(Order::count())->toBe(0);
});
```

A contract test runs the same expectations against every implementation:

```php
dataset('payment gateways', [
    'in-memory fake' => fn (): PaymentGateway => new InMemoryPaymentGateway,
    'stripe sandbox' => fn (): PaymentGateway => app(StripePaymentGateway::class),
]);

test('a gateway rejects a zero amount', function (PaymentGateway $gateway): void {
    expect(fn () => $gateway->charge(amountCents: 0, token: 'tok_visa'))
        ->toThrow(InvalidChargeException::class);
})->with('payment gateways')->group('integration');
```

<a name="faking-external-boundaries-anti-pattern"></a>
### Anti-Pattern

```php
beforeEach(fn () => Http::fake());

test('order is placed', function (): void {
    Queue::fake();
    // ...
    Queue::assertPushed(SendReceipt::class);
});
```

A file-level `Http::fake()` with no URL returns `200` for anything, including a typo in the host. A `Queue::fake()` without class names also silences jobs the test never asserts on.

<a name="faking-external-boundaries-checklist"></a>
### Checklist

- [ ] `Http::preventStrayRequests()` is active, and each fake targets an exact URL pattern.
- [ ] Fakes are created inside the test that needs them, not in a file-level `beforeEach()`.
- [ ] Each fake has an assertion (`assertSent`, `assertPushed`, `assertNothingSent`, ...), including payload data when it is behavior.
- [ ] Timeouts, connection failures, and error responses have tests.
- [ ] Every implementation of a contract runs the shared contract test.
- [ ] Time-dependent tests use `freezeTime()` or `travelTo()`.

<a name="frontend-tests"></a>
## Frontend Tests

<a name="frontend-tests-principle"></a>
### Principle

Test important user-visible behavior of components the way a user perceives it: by role, label, and text, through Vitest and Testing Library. Keep business rules on the server and test them there.

<a name="frontend-tests-why-it-exists"></a>
### Why It Exists

A component that never renders its error state, or submits twice on a double-click, passes every PHP test. Tests that query CSS classes or internal state break on every redesign without catching real regressions.

<a name="frontend-tests-when-to-apply"></a>
### When To Apply

- Components with loading, empty, error, and stale-response states.
- Forms with client-side behavior: disabled submit while pending, field errors from a `422` response.
- Only after Vue or React is adopted. Neither is installed today; adding `vitest`, `@testing-library/vue` or `@testing-library/react`, and `jsdom` requires approval.

<a name="frontend-tests-when-not-to-apply"></a>
### When Not To Apply

- Blade-only pages are covered by feature tests (`assertSee`, `assertSeeText`).
- Do not unit-test Tailwind classes or static markup.
- Do not re-test server validation rules in the component; test that server errors are displayed.

<a name="frontend-tests-correct-example"></a>
### Correct Example

React shown; Vue uses the same queries through `@testing-library/vue`.

```ts
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, it, vi } from 'vitest';
import RefundButton from './RefundButton';

it('disables the button while the refund is pending and shows server errors', async () => {
    const submit = vi.fn().mockRejectedValue({ message: 'Invoice already refunded.' });
    render(<RefundButton onRefund={submit} />);

    await userEvent.click(screen.getByRole('button', { name: 'Refund' }));

    expect(submit).toHaveBeenCalledTimes(1);
    expect((await screen.findByRole('alert')).textContent).toContain('Invoice already refunded.');
});
```

The test interacts through accessible roles and asserts what the user sees.

<a name="frontend-tests-anti-pattern"></a>
### Anti-Pattern

```ts
it('sets state', () => {
    const wrapper = mount(RefundButton);
    wrapper.vm.isPending = true;
    expect(wrapper.find('.btn-disabled').exists()).toBe(true);
});
```

It mutates internal state directly and asserts a CSS class; it neither proves the user flow nor survives a refactor.

<a name="frontend-tests-checklist"></a>
### Checklist

- [ ] Elements are found by role, label, or text, not by class or test-only IDs when an accessible query exists.
- [ ] Loading, error, and empty states are covered for data-fetching components.
- [ ] Network calls are faked at the module or fetch boundary; no real backend.
- [ ] Double submission and stale responses are tested where the component allows them.
- [ ] No frontend test dependency was added without approval.

<a name="regression-and-architecture-tests"></a>
## Regression and Architecture Tests

<a name="regression-and-architecture-tests-principle"></a>
### Principle

Every fixed bug starts with a test that fails for the reason the bug exists, then passes after the fix. Every structural convention worth enforcing is written as a Pest `arch()` test. A failing test is never weakened, skipped, or deleted to make an implementation pass.

<a name="regression-and-architecture-tests-why-it-exists"></a>
### Why It Exists

Bugs fixed without a test return in the next refactor. A test edited to match broken output turns the suite into a recording of current behavior. Conventions such as "no debug calls in `app/`" erode one file at a time unless something fails.

<a name="regression-and-architecture-tests-when-to-apply"></a>
### When To Apply

- Any bug fix: reproduce it in a test first, watch it fail, then fix.
- When a test fails after a change: decide whether the test or the code is wrong, and say which in the review.
- Conventions that apply to a whole directory: forbidden functions, required base classes, layer dependencies.

<a name="regression-and-architecture-tests-when-not-to-apply"></a>
### When Not To Apply

- A test may change when the requirement itself changes; document the new rule in the same commit.
- Do not adopt `arch()->preset()->laravel()` or `strict()` wholesale on an existing codebase without running them first; disable specific rules with `->ignoring()` deliberately rather than rewriting the app to satisfy a preset.
- Do not write `arch()` tests for rules that a single feature test already proves.

<a name="regression-and-architecture-tests-correct-example"></a>
### Correct Example

```php
// tests/Feature/HelpdeskAssistantTest.php — written before the fix
test('the lookup tool does not reveal another customer\'s IBAN', function (): void {
    $result = (new LookupInvoice($this->mallory))->handle(new Request(['number' => 'INV-1042']));

    expect($result)->not->toContain('BE68 5390 0754 7034');
});

// tests/Unit/ArchTest.php
arch()->preset()->php();
arch()->preset()->security();

arch('controllers do not query through the DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');
```

The `php` preset forbids debug calls such as `dd`, `dump`, and `var_dump`; the `security` preset forbids functions such as `eval`, `exec`, `md5`, `rand`, and `unserialize`. Pest 5 also ships `laravel`, `strict`, and `relaxed` presets.

<a name="regression-and-architecture-tests-anti-pattern"></a>
### Anti-Pattern

```php
test('refund total is computed', function (): void {
    // was: ->toBe(4500) — updated because the test kept failing
    expect(RefundCalculator::for($invoice))->toBe(4499);
})->skip('flaky, revisit later');
```

The expected value was changed to match the output and then the test was skipped. Both hide a defect; investigate whether the calculation or the expectation is wrong.

<a name="regression-and-architecture-tests-checklist"></a>
### Checklist

- [ ] Each bug fix includes a test that failed before the fix.
- [ ] No assertion was loosened, removed, or skipped without a stated requirement change.
- [ ] Tests are not deleted without approval.
- [ ] Arch rules are run against the current codebase before being committed, and exceptions use `->ignoring()` with a reason.
- [ ] The regression test name describes the behavior, not the ticket number.
