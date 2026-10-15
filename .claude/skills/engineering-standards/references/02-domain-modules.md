# Domain Modules

- [Introduction](#introduction)
- [Functional Modules](#functional-modules)
- [Modules Are Not Bounded Contexts](#bounded-contexts)
- [Public Surface and Internals](#public-surface)
- [Contracts and Provider Adapters](#provider-adapters)
- [Binding Through Configuration](#configuration-binding)
- [Failing Closed](#failing-closed)
- [Contract Tests and Fakes](#contract-tests)

<a name="introduction"></a>

## Introduction

This document expands section 4 ("Domain modules") and section 5 ("Interchangeable implementations") of the engineering standards (the `engineering-standards` guideline in `CLAUDE.md`). It explains when a feature should become a module, how to lay one out, how to hide provider-specific details behind a contract, and how to test the result.

Every topic below builds part of one worked example: a **CAPTCHA module** that can verify tokens with Cloudflare Turnstile or hCaptcha. Configuration picks the driver, a validation rule exposes it to Form Requests, it fails closed on timeouts, and the same Pest contract tests run against every driver. Here is the complete layout:

```text
app/Modules/Captcha/
├── CaptchaServiceProvider.php      # public: registered in bootstrap/providers.php
├── CaptchaResult.php               # public: value returned by the contract
├── CaptchaOutcome.php              # public: Passed / Rejected / Unavailable
├── Contracts/Captcha.php           # public: the consumer-facing contract
├── Rules/ValidCaptcha.php          # public: what Form Requests use
├── Testing/FakeCaptcha.php         # public (tests only): deterministic fake
└── Drivers/                        # internal: provider-specific adapters
    ├── TurnstileCaptcha.php
    └── HCaptcha.php
config/captcha.php
tests/Feature/Modules/Captcha/CaptchaContractTest.php
tests/Feature/Modules/Captcha/ValidCaptchaTest.php
```

Related documents: [Architecture](01-architecture.md) for layers and dependency direction, [Laravel Backend](03-laravel-backend.md) for Form Requests and config, [Security](08-security.md) for fail-safe verification, and [Testing](07-testing.md).

<a name="functional-modules"></a>

## Functional Modules

<a name="functional-modules-principle"></a>

### Principle

A module groups everything that belongs to one functional capability under `app/Modules/<Name>`, with the namespace `App\Modules\<Name>`: its contract, implementations, actions, rules, provider and config. Laravel's default type-based layout (`app/Models`, `app/Http/Controllers`, ...) remains the right place for code that has not earned a module.

<a name="functional-modules-why-it-exists"></a>

### Why It Exists

A provider integration spread across `app/Services/TurnstileService.php`, `app/Rules/Captcha.php`, `app/Support/CaptchaHelper.php` and `AppServiceProvider` has no visible boundary. Nobody can tell which files belong together, which ones other code may call, or what to delete when the provider is replaced. Grouping by function makes ownership, the public surface and dead code visible.

**Why `app/Modules` and not `app/Domain`:** "Domain" suggests Domain-Driven Design (aggregates, bounded contexts, ubiquitous language), which this project does not apply by default (see [Modules Are Not Bounded Contexts](#bounded-contexts)). `app/Modules` is neutral. It sits under the existing `App\` PSR-4 root, so `composer.json` does not change, and it does not collide with Laravel's generated folders.

<a name="functional-modules-when-to-apply"></a>

### When To Apply

Create a module when **two or more** of these are true:

- **Cohesion:** five or more classes change together for the same reason (contract, drivers, rule, provider, config).
- **Variability:** there are interchangeable implementations selected by configuration.
- **Integration:** the code wraps an external system whose failures need defined behavior.
- **Reuse:** several unrelated features consume it (CAPTCHA protects login, registration, contact and password reset).
- **Ownership:** a person or team is accountable for it as a unit.

<a name="functional-modules-when-not-to-apply"></a>

### When Not To Apply

- CRUD features made of a model, a controller, a Form Request and a policy. Laravel's default folders fit them perfectly, and this covers most of the current app (`Team`, `Book`, `Invoice`).
- A single class integration with no alternative driver. Put it in `app/Support` or a service class and promote it later if it grows.
- Do not split Eloquent models into modules by default. Models are shared across features, and `app/Models` keeps relationships easy to discover. A module may own a model only when nothing outside the module queries it directly.
- Do not move existing code into modules just for symmetry. Migrate a feature when you are already changing it substantially.

<a name="functional-modules-correct-example"></a>

### Correct Example

```php
// bootstrap/providers.php
use App\Modules\Captcha\CaptchaServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CaptchaServiceProvider::class,
];
```

The module registers itself through its own service provider. Nothing outside `app/Modules/Captcha` and `config/captcha.php` needs to know which drivers exist.

<a name="functional-modules-anti-pattern"></a>

### Anti-Pattern

```text
app/Modules/
├── Teams/Http/Controllers/TeamController.php
├── Teams/Models/Team.php
├── Teams/Policies/TeamPolicy.php
└── Teams/Requests/StoreTeamRequest.php
```

This moves a plain CRUD feature into a module with no variability, no integration and no extra cohesion. It recreates Laravel's default folders one level deeper, breaks `make:*` generator defaults and policy auto-discovery, and gives no boundary in return.

<a name="functional-modules-checklist"></a>

### Checklist

- [ ] The pull request names at least two module criteria (cohesion, variability, integration, reuse, ownership).
- [ ] The module lives in `app/Modules/<Name>` with namespace `App\Modules\<Name>`.
- [ ] The module has its own service provider registered in `bootstrap/providers.php`.
- [ ] Its config, if any, is in `config/<name>.php`.
- [ ] Its tests are grouped under `tests/Feature/Modules/<Name>` (or `tests/Unit/Modules/<Name>`).

<a name="bounded-contexts"></a>

## Modules Are Not Bounded Contexts

<a name="bounded-contexts-principle"></a>

### Principle

A module is a code-organization boundary. A DDD bounded context is a model boundary, where the same term can mean different things and data is owned and translated at the edges. Do not apply DDD tactical patterns (aggregates, value objects everywhere, domain events, anti-corruption layers) unless the business complexity of that area really needs them.

<a name="bounded-contexts-why-it-exists"></a>

### Why It Exists

Teams that treat every folder as a bounded context end up duplicating models (`Billing\Customer`, `Support\Customer`, `Teams\Member` all mapping `users`), writing translators between them, and wrapping Eloquent in aggregates. For a CRUD-heavy Laravel app that is pure overhead. It slows changes down and introduces mapping bugs, all without a real language conflict to justify it.

<a name="bounded-contexts-when-to-apply"></a>

### When To Apply

Treat an area as a bounded context, with its own models and explicit translation, only when:

- The same word has different, conflicting rules in two areas (for example "order" in fulfillment vs. accounting) and one shared model keeps causing bugs.
- The area's invariants are complex enough that a rich model with guarded state transitions prevents real defects.
- The area is a realistic extraction candidate (see [Modular Monolith Over Microservices](01-architecture.md#modular-monolith)).

<a name="bounded-contexts-when-not-to-apply"></a>

### When Not To Apply

- Technical modules like CAPTCHA, payments adapters or notification channels. They have no business language of their own. They are capabilities.
- Areas where one Eloquent model serves everyone without conflict.
- When "DDD" is proposed as a style rather than as a fix for a demonstrated modeling problem.

<a name="bounded-contexts-correct-example"></a>

### Correct Example

```php
namespace App\Modules\Captcha;

final readonly class CaptchaResult
{
    public function __construct(
        public CaptchaOutcome $outcome,
        public ?string $reason = null,
    ) {}

    public function passed(): bool
    {
        return $this->outcome === CaptchaOutcome::Passed;
    }
}
```

The CAPTCHA module is a capability module. It returns one small immutable result type and has no aggregates, repositories or domain events, because there is no business model to protect.

<a name="bounded-contexts-anti-pattern"></a>

### Anti-Pattern

```php
namespace App\Modules\Captcha\Domain\Aggregates;

final class CaptchaChallengeAggregate
{
    private array $recordedEvents = [];

    public function verify(CaptchaToken $token, IpAddress $ip, CaptchaVerifierPort $port): void
    {
        $this->recordedEvents[] = new CaptchaChallengeVerified(/* ... */);
    }
}
```

This applies DDD vocabulary to a stateless HTTP check. It adds ports, an aggregate, value objects and domain events with no invariant to protect. The result is harder to read than an `if` statement.

<a name="bounded-contexts-checklist"></a>

### Checklist

- [ ] The module has no duplicated Eloquent models for the same table without a documented language conflict.
- [ ] DDD tactical patterns appear only in areas with demonstrated complex invariants.
- [ ] Capability modules (CAPTCHA, payments, notifications) expose plain contracts and value objects.
- [ ] Any bounded-context decision is recorded with the conflict that motivated it.

<a name="public-surface"></a>

## Public Surface and Internals

<a name="public-surface-principle"></a>

### Principle

Each module exposes a small, intentional public surface. For CAPTCHA that is the `Captcha` contract, `CaptchaResult`, `CaptchaOutcome`, the `ValidCaptcha` rule, the service provider, and `FakeCaptcha` for tests. Everything else, in particular `Drivers/*`, is internal and must not be referenced from outside the module.

<a name="public-surface-why-it-exists"></a>

### Why It Exists

If a Form Request does `new TurnstileCaptcha(...)` directly, switching to hCaptcha means editing every form. That also bypasses the configured timeout and failure handling. A narrow public surface lets the module change its internals (add a driver, change an endpoint, add retries) without touching consumers.

<a name="public-surface-when-to-apply"></a>

### When To Apply

- Every module. Decide what is public before writing consumers.
- Whenever a second feature starts using the module. That is when accidental coupling to internals usually happens.

<a name="public-surface-when-not-to-apply"></a>

### When Not To Apply

- PHP has no package-private visibility, and enforcing "internal" through `@internal` tags alone is weak. Do not build elaborate facades or proxy classes to hide internals. Use the folder convention plus an architecture test.
- Tests may instantiate internal drivers directly. Contract tests must do so.
- Do not wrap the module's single public contract in a second "API" facade class. The contract is the API.

<a name="public-surface-correct-example"></a>

### Correct Example

```php
namespace App\Modules\Captcha\Rules;

use App\Modules\Captcha\CaptchaOutcome;
use App\Modules\Captcha\Contracts\Captcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class ValidCaptcha implements ValidationRule
{
    public function __construct(private ?string $ip) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Please complete the CAPTCHA.');

            return;
        }

        $result = app(Captcha::class)->verify($value, $this->ip);

        match ($result->outcome) {
            CaptchaOutcome::Passed => null,
            CaptchaOutcome::Rejected => $fail('The CAPTCHA could not be verified. Please try again.'),
            CaptchaOutcome::Unavailable => $this->failClosed($fail, $result->reason),
        };
    }

    private function failClosed(Closure $fail, ?string $reason): void
    {
        Log::warning('captcha.unavailable', ['reason' => $reason]);

        $fail('Verification is temporarily unavailable. Please try again in a moment.');
    }
}
```

```php
// app/Http/Requests/Auth/RegisterRequest.php (consumer)
public function rules(): array
{
    return [
        'email' => ['required', 'email', 'max:255', 'unique:users'],
        'cf-turnstile-response' => ['required', 'string', new ValidCaptcha($this->ip())],
    ];
}
```

Consumers only know `ValidCaptcha`. The rule depends only on the contract. The token is never logged, and an unavailable provider is a validation failure, not a pass.

<a name="public-surface-anti-pattern"></a>

### Anti-Pattern

```php
use App\Modules\Captcha\Drivers\TurnstileCaptcha;

public function rules(): array
{
    return [
        'token' => ['required', function ($attribute, $value, $fail) {
            if (! (new TurnstileCaptcha(env('TURNSTILE_SECRET'), 30))->verify($value, null)->passed()) {
                $fail('Invalid captcha.');
            }
        }],
    ];
}
```

This hard-codes one driver and reads `env()` outside config, which breaks under `config:cache`. It picks its own 30-second timeout, so a slow provider ties up PHP workers, and it duplicates logic every other form will copy.

<a name="public-surface-checklist"></a>

### Checklist

- [ ] The module's public classes are listed in the module's provider docblock or this document.
- [ ] No code outside the module imports `App\Modules\<Name>\Drivers`.
- [ ] An `arch()` test enforces that rule (see [Architecture](01-architecture.md#cross-module)).
- [ ] Consumers use the validation rule or the contract, never a concrete driver.
- [ ] Secrets and tokens are never logged by public or internal classes.

<a name="provider-adapters"></a>

## Contracts and Provider Adapters

<a name="provider-adapters-principle"></a>

### Principle

Define the contract from the consumer's point of view: what it needs to know, not what a vendor returns. Each provider gets one adapter that implements the contract and keeps everything provider-specific inside it: endpoint, parameter names, response format, error codes and timeouts.

<a name="provider-adapters-why-it-exists"></a>

### Why It Exists

Turnstile and hCaptcha both return `{"success": bool, "error-codes": [...]}` today, but their endpoints, optional parameters and error codes differ. If consumers read `error-codes` directly, the vendor's format becomes part of your application. A consumer-shaped contract (`verify(token, ip): CaptchaResult`) with a three-state outcome lets the caller handle "rejected" and "provider down" differently. A boolean cannot express that difference, and a boolean is exactly how silent fail-open bugs happen.

<a name="provider-adapters-when-to-apply"></a>

### When To Apply

- Two or more providers are supported now, or a switch is planned with a date.
- The provider is network-bound and consumers need a deterministic fake.
- The provider's failure modes (timeout, 5xx, malformed body) need a uniform treatment.

<a name="provider-adapters-when-not-to-apply"></a>

### When Not To Apply

- One provider and no realistic second one. Write a single client class and test it with `Http::fake()`. Introduce the contract when the second provider shows up, which is a mechanical refactor.
- Do not mirror the vendor's full API in the contract. Expose only what consumers use. `verify()` is enough. There is no need for `getSiteKey()`, `getStats()` or other methods "for completeness."

<a name="provider-adapters-correct-example"></a>

### Correct Example

```php
namespace App\Modules\Captcha\Contracts;

use App\Modules\Captcha\CaptchaResult;

interface Captcha
{
    /**
     * Verify a client token. Never throws for provider failures: returns an Unavailable result instead.
     */
    public function verify(string $token, ?string $ip): CaptchaResult;
}
```

```php
namespace App\Modules\Captcha;

enum CaptchaOutcome: string
{
    case Passed = 'passed';
    case Rejected = 'rejected';
    case Unavailable = 'unavailable';
}
```

```php
namespace App\Modules\Captcha\Drivers;

use App\Modules\Captcha\CaptchaOutcome;
use App\Modules\Captcha\CaptchaResult;
use App\Modules\Captcha\Contracts\Captcha;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileCaptcha implements Captcha
{
    public const string ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private string $secret, private float $timeoutSeconds) {}

    public function verify(string $token, ?string $ip): CaptchaResult
    {
        try {
            $response = Http::asForm()
                ->connectTimeout($this->timeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->post(self::ENDPOINT, array_filter([
                    'secret' => $this->secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException) {
            return new CaptchaResult(CaptchaOutcome::Unavailable, 'turnstile: connection failed or timed out');
        }

        $success = $response->json('success');

        return match (true) {
            ! $response->successful(), ! is_bool($success) => new CaptchaResult(CaptchaOutcome::Unavailable, "turnstile: unexpected response ({$response->status()})"),
            $success => new CaptchaResult(CaptchaOutcome::Passed),
            default => new CaptchaResult(CaptchaOutcome::Rejected, implode(',', (array) $response->json('error-codes', []))),
        };
    }
}
```

`HCaptcha` is identical apart from `ENDPOINT = 'https://api.hcaptcha.com/siteverify'` and the `hcaptcha:` reason prefix. Both handle connection errors, non-2xx responses and malformed bodies as `Unavailable`. Only an explicit `"success": true` is a pass.

<a name="provider-adapters-anti-pattern"></a>

### Anti-Pattern

```php
interface Captcha
{
    public function verify(string $token): bool;
}

class TurnstileCaptcha implements Captcha
{
    public function verify(string $token): bool
    {
        $response = Http::post(self::ENDPOINT, ['secret' => $this->secret, 'response' => $token]);

        return $response->json('success') ?? true;   // "don't block users if Cloudflare is down"
    }
}
```

The boolean contract cannot tell "rejected" from "unavailable." The `?? true` silently turns every outage, or any attacker-induced malformed response, into a pass. There is no timeout beyond the client default, so a hung provider holds a PHP worker for up to 30 seconds per request.

<a name="provider-adapters-checklist"></a>

### Checklist

- [ ] The contract method signature is consumer-shaped and returns a result type, not a vendor array or a bare `bool`.
- [ ] The contract documents its failure behavior in the docblock.
- [ ] Each adapter sets `connectTimeout()` and `timeout()` explicitly, from config.
- [ ] Each adapter maps connection errors, non-2xx and malformed bodies to `Unavailable`.
- [ ] Only an explicit, strictly typed success value produces `Passed`.
- [ ] Vendor-specific endpoints and field names do not appear outside the adapter.

<a name="configuration-binding"></a>

## Binding Through Configuration

<a name="configuration-binding-principle"></a>

### Principle

The active implementation is chosen by a config value (`captcha.driver`) and bound to the contract in the module's service provider. `env()` is read only in `config/captcha.php`. An unknown driver or missing secret is a boot-time error, never a silent fallback.

<a name="configuration-binding-why-it-exists"></a>

### Why It Exists

Choosing the implementation with `if (app()->environment('local'))` scattered through the code makes production behavior impossible to reason about. Reading `env()` outside config returns `null` once `php artisan config:cache` has run in production. A missing secret that becomes an empty string makes every verification fail with a vague provider error, or worse, it gets "handled" with a pass-through. One binding point driven by config makes the selection explicit and testable.

<a name="configuration-binding-when-to-apply"></a>

### When To Apply

- Every contract with more than one implementation.
- Any implementation that needs secrets, URLs or timeouts.

<a name="configuration-binding-when-not-to-apply"></a>

### When Not To Apply

- A single implementation with no constructor configuration. The container auto-resolves concrete classes, so no binding is needed.
- Do not add a `null` or `log` driver that always passes "for local development." Use real test keys from the provider (both Turnstile and hCaptcha publish always-pass test keys) or `FakeCaptcha` in tests.
- The `#[Bind]` container attribute (Laravel 12+) binds an interface to one concrete class per environment. It cannot read a config value, so it does not fit config-selected drivers. Use the service provider.

<a name="configuration-binding-correct-example"></a>

### Correct Example

```php
// config/captcha.php
return [
    'driver' => env('CAPTCHA_DRIVER', 'turnstile'),

    'timeout' => (float) env('CAPTCHA_TIMEOUT', 3),

    'drivers' => [
        'turnstile' => ['secret' => env('TURNSTILE_SECRET_KEY')],
        'hcaptcha' => ['secret' => env('HCAPTCHA_SECRET_KEY')],
    ],
];
```

```php
namespace App\Modules\Captcha;

use App\Modules\Captcha\Contracts\Captcha;
use App\Modules\Captcha\Drivers\HCaptcha;
use App\Modules\Captcha\Drivers\TurnstileCaptcha;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class CaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Captcha::class, function (): Captcha {
            $driver = (string) config('captcha.driver');
            $secret = config("captcha.drivers.{$driver}.secret");
            $timeout = (float) config('captcha.timeout');

            if (! is_string($secret) || $secret === '') {
                throw new InvalidArgumentException("Missing secret for CAPTCHA driver [{$driver}].");
            }

            return match ($driver) {
                'turnstile' => new TurnstileCaptcha($secret, $timeout),
                'hcaptcha' => new HCaptcha($secret, $timeout),
                default => throw new InvalidArgumentException("Unsupported CAPTCHA driver [{$driver}]."),
            };
        });
    }
}
```

A singleton is safe because the drivers are stateless. The binding is resolved lazily, so a misconfiguration fails on the first protected request and in the contract tests, not silently.

<a name="configuration-binding-anti-pattern"></a>

### Anti-Pattern

```php
$this->app->bind(Captcha::class, function () {
    return match (env('CAPTCHA_DRIVER')) {
        'hcaptcha' => new HCaptcha(env('HCAPTCHA_SECRET_KEY') ?? '', 30),
        default => new class implements Captcha {
            public function verify(string $token, ?string $ip): CaptchaResult
            {
                return new CaptchaResult(CaptchaOutcome::Passed);
            }
        },
    };
});
```

`env()` returns `null` under cached config, so the `default` branch is taken and **every CAPTCHA passes in production**. The empty-string secret hides misconfiguration, and the 30-second timeout ties up workers.

<a name="configuration-binding-checklist"></a>

### Checklist

- [ ] `config/<module>.php` holds driver, timeouts and secrets. `env()` appears nowhere else.
- [ ] The module provider binds the contract with `match` on the configured driver.
- [ ] Unknown drivers and missing secrets throw `InvalidArgumentException`.
- [ ] No always-pass implementation is reachable from configuration.
- [ ] New env keys are added to `.env.example` with empty values, never real secrets.
- [ ] The app still boots with `php artisan config:cache`.

<a name="failing-closed"></a>

## Failing Closed

<a name="failing-closed-principle"></a>

### Principle

When a security-critical verification cannot be completed because of a timeout, a 5xx, a malformed response or a missing configuration, the request is **denied** with a retryable message, and the event is logged without sensitive data. A verification never passes because the verifier was unavailable.

<a name="failing-closed-why-it-exists"></a>

### Why It Exists

Attackers can cause or wait for verifier failures: they can flood the provider, block its domain at the network level, or send oversized tokens that cause errors. A fail-open CAPTCHA becomes "no CAPTCHA" exactly when bots are most active. Failing closed costs some legitimate users a retry during an outage. That trade-off is explicit and visible in logs and alerts.

<a name="failing-closed-when-to-apply"></a>

### When To Apply

- CAPTCHA, webhook signature checks, payment confirmations, fraud and risk scores, licence checks, and any verification that guards a state change.
- Any adapter whose result is used as an authorization or anti-abuse signal.

<a name="failing-closed-when-not-to-apply"></a>

### When Not To Apply

- Non-security enrichments: geo-IP lookups for display, avatar fetches, analytics. Degrade gracefully and render without them.
- When the business explicitly accepts fail-open for availability (for example a risk score that only adds a review flag). Write the decision down, make it a visible config switch that defaults to closed, log every fail-open event, and alert on its rate. Never make it an implicit `?? true`.

<a name="failing-closed-correct-example"></a>

### Correct Example

```php
// In TurnstileCaptcha::verify() (see Contracts and Provider Adapters):
} catch (ConnectionException) {
    return new CaptchaResult(CaptchaOutcome::Unavailable, 'turnstile: connection failed or timed out');
}

// In ValidCaptcha::validate():
CaptchaOutcome::Unavailable => $this->failClosed($fail, $result->reason),
```

Timeouts (`ConnectionException`) and bad responses become `Unavailable`. The rule turns that into a validation error with a retryable message and a `captcha.unavailable` log entry that contains no token.

<a name="failing-closed-anti-pattern"></a>

### Anti-Pattern

```php
try {
    $result = app(Captcha::class)->verify($value, $this->ip);
} catch (\Throwable $e) {
    report($e);

    return;   // provider is down; let the user through
}
```

The early `return` without calling `$fail()` makes the field valid. Every outage, and every input that makes the adapter throw, bypasses the CAPTCHA.

<a name="failing-closed-checklist"></a>

### Checklist

- [ ] Every code path that cannot confirm success ends in a denial (`$fail()`, `403`, `422`), never an early `return`.
- [ ] Adapter timeouts are short (2–5 seconds) and set explicitly.
- [ ] The user-facing message for `Unavailable` is retryable and does not reveal provider details.
- [ ] Unavailability is logged under a stable key (`captcha.unavailable`) without tokens or secrets.
- [ ] A test proves that a connection failure produces a validation error.

<a name="contract-tests"></a>

## Contract Tests and Fakes

<a name="contract-tests-principle"></a>

### Principle

One Pest test file defines the contract's behavior and runs it against **every** real implementation through a dataset, using `Http::fake()` so that no network is involved. Consumers of the contract are tested with `FakeCaptcha`, a deterministic in-memory implementation that the module ships for tests.

<a name="contract-tests-why-it-exists"></a>

### Why It Exists

Interchangeable implementations drift: one driver treats a 500 as a rejection, another as a pass. A shared contract test makes "interchangeable" a verified property instead of an assumption. A shipped fake keeps feature tests fast and deterministic, and stops each test file from inventing its own mock with slightly different semantics.

<a name="contract-tests-when-to-apply"></a>

### When To Apply

- Every contract with two or more implementations: run the contract tests per driver.
- Every consumer of a network-bound contract: use the fake.

<a name="contract-tests-when-not-to-apply"></a>

### When Not To Apply

- Do not hit the real provider in the default suite. Live smoke tests, if any, belong in a separate, opt-in group run with real test keys.
- Do not use the fake to test the adapters themselves. Adapters are tested with `Http::fake()` so that their HTTP mapping is actually exercised.
- Do not ship the fake as a configurable driver. It is bound only from tests with `$this->app->instance()`.

<a name="contract-tests-correct-example"></a>

### Correct Example

```php
// tests/Feature/Modules/Captcha/CaptchaContractTest.php
use App\Modules\Captcha\CaptchaOutcome;
use App\Modules\Captcha\Drivers\HCaptcha;
use App\Modules\Captcha\Drivers\TurnstileCaptcha;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

dataset('captcha drivers', [
    'turnstile' => [TurnstileCaptcha::class],
    'hcaptcha' => [HCaptcha::class],
]);

it('passes only on an explicit success', function (string $driver) {
    Http::fake([$driver::ENDPOINT => Http::response(['success' => true])]);

    expect((new $driver('test-secret', 3.0))->verify('token', '203.0.113.7')->outcome)
        ->toBe(CaptchaOutcome::Passed);

    Http::assertSent(fn (Request $request) => $request['secret'] === 'test-secret'
        && $request['response'] === 'token'
        && $request['remoteip'] === '203.0.113.7');
})->with('captcha drivers');

it('rejects a token the provider refuses', function (string $driver) {
    Http::fake([$driver::ENDPOINT => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect((new $driver('test-secret', 3.0))->verify('bad', null)->outcome)->toBe(CaptchaOutcome::Rejected);
})->with('captcha drivers');

it('fails closed when the provider cannot be verified', function (string $driver, string $failure) {
    Http::fake([$driver::ENDPOINT => match ($failure) {
        'timeout' => Http::failedConnection(),
        'server error' => Http::response(['success' => true], 503),
        'malformed body' => Http::response('<html>oops</html>', 200),
        'non-boolean success' => Http::response(['success' => 'yes'], 200),
    }]);

    expect((new $driver('test-secret', 3.0))->verify('token', null)->outcome)->toBe(CaptchaOutcome::Unavailable);
})->with('captcha drivers')->with(['timeout', 'server error', 'malformed body', 'non-boolean success']);
```

`Http::failedConnection()` simulates a connection failure or timeout. Combining datasets runs every failure case against every driver. Adding a third driver means adding one dataset line.

```php
namespace App\Modules\Captcha\Testing;

use App\Modules\Captcha\CaptchaOutcome;
use App\Modules\Captcha\CaptchaResult;
use App\Modules\Captcha\Contracts\Captcha;

class FakeCaptcha implements Captcha
{
    public function __construct(private CaptchaOutcome $outcome = CaptchaOutcome::Passed) {}

    public function verify(string $token, ?string $ip): CaptchaResult
    {
        return new CaptchaResult($this->outcome, 'fake');
    }
}
```

```php
// tests/Feature/Modules/Captcha/ValidCaptchaTest.php
it('blocks registration when the captcha provider is unavailable', function () {
    $this->app->instance(Captcha::class, new FakeCaptcha(CaptchaOutcome::Unavailable));

    $this->post('/register', [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'cf-turnstile-response' => 'token',
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});
```

The consumer test proves the fail-closed behavior end to end without HTTP stubs, because the adapters' HTTP mapping is already covered by the contract tests.

<a name="contract-tests-anti-pattern"></a>

### Anti-Pattern

```php
it('verifies captcha', function () {
    $this->mock(TurnstileCaptcha::class)
        ->shouldReceive('verify')->andReturn(new CaptchaResult(CaptchaOutcome::Passed));

    $this->post('/register', [/* ... */])->assertRedirect();
});
```

This mocks the concrete driver, which consumers do not even resolve, so the test may pass while hitting the real binding. It only covers the happy path, and it does not test `HCaptcha` at all. Nothing proves that the drivers behave the same on timeouts.

<a name="contract-tests-checklist"></a>

### Checklist

- [ ] One contract test file runs against every driver through a Pest dataset.
- [ ] The contract tests cover pass, reject, connection failure, 5xx, malformed body and wrong-typed success.
- [ ] `Http::preventStrayRequests()` guarantees no real network calls.
- [ ] Request payloads are asserted with `Http::assertSent()` (secret, token, IP).
- [ ] Consumer tests bind `FakeCaptcha` with `$this->app->instance()`, and at least one covers `Unavailable`.
- [ ] The fake is not selectable through `config/captcha.php`.
