# Engineering Principles

- [Introduction](#introduction)
- [How To Use These Documents](#how-to-use-these-documents)
- [Correctness Before Speed](#correctness-before-speed)
- [The Smallest Correct Design](#the-smallest-correct-design)
- [Follow Existing Conventions](#follow-existing-conventions)
- [Never Silently Change Behavior](#never-silently-change-behavior)
- [Verified, Honest Reporting](#verified-honest-reporting)
- [Definition Of Done](#definition-of-done)

<a name="introduction"></a>

## Introduction

These documents are the detailed companion to the summary standards in the `engineering-standards` guideline, which Laravel Boost compiles into `CLAUDE.md` from the `ludoguenet/laravel-standards` package. The summary tells humans and AI agents *what* to do; these documents explain *why*, *when*, *when not*, and show what good and bad look like.

| Document | Scope |
| --- | --- |
| [Architecture](01-architecture.md) | Layers, responsibilities, dependencies, modular monolith, simplicity vs abstraction. |
| [Domain Modules](02-domain-modules.md) | Functional modules, contracts, providers, dependency injection, the CAPTCHA example. |
| [Laravel Backend](03-laravel-backend.md) | Form Requests, Policies, Resources, Actions, Jobs, configuration, typing. |
| [Database & Concurrency](04-database-and-concurrency.md) | Constraints, transactions, locks, deadlocks, idempotency, migrations. |
| [API Contracts](05-api-contracts.md) | Validation, response shapes, errors, pagination, versioning. |
| [Frontend (Vue & React)](06-frontend-vue-react.md) | State ownership, async states, server validation, testing UI behavior. |
| [Testing](07-testing.md) | Strategy, Pest, fakes, contract tests, regressions. |
| [Security](08-security.md) | Authorization, validation, sensitive data, vulnerabilities, safe errors. |
| [Performance](09-performance.md) | Measurement, queries, caching, queues, bundle cost. |
| [CI/CD & Observability](10-ci-cd-observability.md) | Quality gates, pipelines, deployments, logs, alerts, rollback. |
| [AI-Assisted Development](11-ai-assisted-development.md) | Working with coding agents and building AI features safely. |
| [Decisions](decisions.md) | Architecture Decision Records (ADRs). |

<a name="how-to-use-these-documents"></a>

## How To Use These Documents

Every topic follows the same seven-part structure so it can be read quickly and applied consistently:

1. **Principle** — the rule, stated normatively.
2. **Why It Exists** — the concrete failure it prevents.
3. **When To Apply** — the situations where the rule pays for itself.
4. **When Not To Apply** — legitimate exceptions. A rule without exceptions becomes dogma.
5. **Correct Example** — what good looks like in this codebase.
6. **Anti-Pattern** — what to recognize and refuse in review.
7. **Checklist** — verifiable items for authors and reviewers.

When a rule does not fit your situation, follow the "When Not To Apply" guidance. If no listed exception covers your case and the decision has lasting consequences, record it as an [Architecture Decision Record](decisions.md) rather than deviating silently.

> [!NOTE]
> The summary standards take precedence over any other agent guideline. When these documents and the summary disagree, fix the document that is wrong instead of picking one.

<a name="correctness-before-speed"></a>

## Correctness Before Speed

<a name="correctness-before-speed-principle"></a>

### Principle

Optimize for long-term clarity and correctness, not for the fastest implementation. A change is only finished when it is understood, tested and verified.

<a name="correctness-before-speed-why-it-exists"></a>

### Why It Exists

Fast, unreviewed changes move cost into the future: data corrupted by a race condition, an authorization hole found by a customer, or a refactor nobody dares to touch because nothing tests it. Those failures cost far more than the time saved.

<a name="correctness-before-speed-when-to-apply"></a>

### When To Apply

Always, and with extra care for money, permissions, personal data, irreversible operations (sending email, charging cards, deleting data) and anything concurrent.

<a name="correctness-before-speed-when-not-to-apply"></a>

### When Not To Apply

Throwaway spikes and prototypes may skip tests and polish, provided they are clearly labelled, never merged to `main` as-is, and rewritten properly once the approach is validated. Even a spike must not touch production data.

<a name="correctness-before-speed-correct-example"></a>

### Correct Example

```php
public function store(StoreRefundRequest $request, Invoice $invoice, RefundInvoice $refundInvoice): RedirectResponse
{
    $this->authorize('refund', $invoice);

    $refundInvoice->handle($invoice, $request->validated('reason'));

    return to_route('invoices.show', $invoice);
}
```

Authorization, validation and the business operation are explicit and each one is testable on its own.

<a name="correctness-before-speed-anti-pattern"></a>

### Anti-Pattern

```php
public function store(Request $request, Invoice $invoice): RedirectResponse
{
    $invoice->update(['status' => 'refunded'] + $request->all());

    return back();
}
```

There is no authorization and no validation, writable fields are open to mass assignment, and the business rule ("only paid invoices can be refunded") is missing. It works in the demo but fails in production.

<a name="correctness-before-speed-checklist"></a>

### Checklist

- [ ] The business requirement and acceptance criteria are written down (issue, PR description or test names).
- [ ] Security, data-integrity and concurrency risks were considered explicitly.
- [ ] Automated tests cover the behavior and its important failure paths.
- [ ] The final diff was reviewed line by line by its author before requesting review.

<a name="the-smallest-correct-design"></a>

## The Smallest Correct Design

<a name="the-smallest-correct-design-principle"></a>

### Principle

Choose the smallest design that correctly addresses the current requirement. Add abstractions when a concrete need appears, not because one might appear someday.

<a name="the-smallest-correct-design-why-it-exists"></a>

### Why It Exists

Every interface, repository, event and layer is code that must be read, tested and kept consistent. Speculative abstractions usually guess the wrong axis of change, so you pay for them twice: once to build them and again to work around them.

<a name="the-smallest-correct-design-when-to-apply"></a>

### When To Apply

When designing any new feature, and when reviewing a pull request that introduces a new class, layer or package.

<a name="the-smallest-correct-design-when-not-to-apply"></a>

### When Not To Apply

"Smallest" never means "incorrect". If the requirement involves interchangeable providers (payment, CAPTCHA, storage), concurrency or a public API contract, the correct design needs a contract, a lock or a versioned resource — see [Domain Modules](02-domain-modules.md) and [Database & Concurrency](04-database-and-concurrency.md).

<a name="the-smallest-correct-design-correct-example"></a>

### Correct Example

```php
class PublishBook
{
    public function handle(Book $book): void
    {
        $book->update(['published_at' => now()]);
    }
}
```

There is one implementation and one consumer, so a plain class resolved by the container is enough.

<a name="the-smallest-correct-design-anti-pattern"></a>

### Anti-Pattern

```php
interface BookPublisherInterface { public function publish(BookDTO $book): PublishResultDTO; }
interface BookRepositoryInterface { public function save(BookDTO $book): void; }

class EloquentBookRepository implements BookRepositoryInterface { /* wraps $book->save() */ }
class DefaultBookPublisher implements BookPublisherInterface { /* calls the repository */ }
```

Four types to set one timestamp. Nothing swaps the implementations, and the repository hides Eloquent features (scopes, eager loading) the team already knows.

<a name="the-smallest-correct-design-checklist"></a>

### Checklist

- [ ] Each new class, interface or layer has a concrete, current justification.
- [ ] No interface exists with a single implementation unless a test double or a module boundary needs it.
- [ ] The design solves today's requirement and does not invent future ones.
- [ ] Correctness concerns (authorization, transactions, locks) were not removed for the sake of simplicity.

<a name="follow-existing-conventions"></a>

## Follow Existing Conventions

<a name="follow-existing-conventions-principle"></a>

### Principle

Follow the conventions already used in the codebase and the versions of Laravel and PHP actually installed. Improve a convention deliberately and consistently, never one file at a time.

<a name="follow-existing-conventions-why-it-exists"></a>

### Why It Exists

Inconsistent code is harder to review: every reader has to work out which of three styles applies here. Code written for the wrong framework version compiles in a reviewer's head and fails at runtime.

<a name="follow-existing-conventions-when-to-apply"></a>

### When To Apply

Before creating or editing a file, look at its siblings (`app/Models`, `app/Policies`, `tests/Feature`) and check installed versions with `composer show --direct` and `package.json`.

<a name="follow-existing-conventions-when-not-to-apply"></a>

### When Not To Apply

When an existing convention is itself a defect (missing authorization, `env()` outside config files, unguarded models). Fix it in a focused change, record the new convention if it is significant, and do not copy the defect into new code.

<a name="follow-existing-conventions-correct-example"></a>

### Correct Example

```shell
composer show laravel/framework
php artisan make:policy InvoicePolicy --model=Invoice --no-interaction
vendor/bin/pint --dirty
```

Generators produce files that match the framework's conventions, and Pint enforces the project's style.

<a name="follow-existing-conventions-anti-pattern"></a>

### Anti-Pattern

Hand-writing a controller in a new `app/Services/Http` folder with a different naming scheme, or copying a Laravel 10 tutorial into a Laravel 13 application without checking that the APIs still exist.

<a name="follow-existing-conventions-checklist"></a>

### Checklist

- [ ] Sibling files were inspected before creating a new file.
- [ ] Artisan `make:` commands were used where they exist.
- [ ] APIs used match the installed package versions.
- [ ] No new base folder was created without approval.
- [ ] Pint was run on modified PHP files.

<a name="never-silently-change-behavior"></a>

## Never Silently Change Behavior

<a name="never-silently-change-behavior-principle"></a>

### Principle

Never silently change business behavior, public API contracts or data semantics. Every behavior change is intentional, visible in the pull request description and covered by a test.

<a name="never-silently-change-behavior-why-it-exists"></a>

### Why It Exists

Consumers rely on behavior that is not written down: a mobile app parsing a field, a finance export expecting a status, a job assuming an order of operations. A "small cleanup" that changes any of these breaks them without warning.

<a name="never-silently-change-behavior-when-to-apply"></a>

### When To Apply

Refactorings, dependency upgrades, changes to API Resources, enum values, validation rules, default values, migrations and scheduled jobs.

<a name="never-silently-change-behavior-when-not-to-apply"></a>

### When Not To Apply

Fixing a confirmed bug is a behavior change by definition. Do it, but say so explicitly and add a regression test that fails before the fix.

<a name="never-silently-change-behavior-correct-example"></a>

### Correct Example

```markdown
## Behavior changes
- `GET /api/v1/invoices` now excludes soft-deleted invoices (previously included). Bug #142.
- Regression test: `it('does not list deleted invoices')`.

## No changes
- Response shape of `InvoiceResource` is unchanged.
```

The pull request states what changed, why, and how it is verified.

<a name="never-silently-change-behavior-anti-pattern"></a>

### Anti-Pattern

A refactor titled "tidy invoice controller" that also renames the JSON key `total_cents` to `total` and changes the default sort order. Nothing in the description mentions it, and no test asserts the old shape.

<a name="never-silently-change-behavior-checklist"></a>

### Checklist

- [ ] The pull request lists every intended behavior change, or states that there are none.
- [ ] Public contracts (routes, JSON shapes, events, enum values) are unchanged or deliberately versioned.
- [ ] A test pins the new behavior.
- [ ] Refactors and behavior changes are kept in separate commits where practical.

<a name="verified-honest-reporting"></a>

## Verified, Honest Reporting

<a name="verified-honest-reporting-principle"></a>

### Principle

Report only the checks that were actually executed, with their real results. Disclose unverified assumptions, skipped checks and remaining risks.

<a name="verified-honest-reporting-why-it-exists"></a>

### Why It Exists

"Tests pass" written by someone who did not run them, whether a human or an AI agent, is worse than no claim: reviewers lower their guard and the defect ships with an alibi.

<a name="verified-honest-reporting-when-to-apply"></a>

### When To Apply

Every pull request description, every agent summary, every deployment note.

<a name="verified-honest-reporting-when-not-to-apply"></a>

### When Not To Apply

There is no exception. When a check cannot run (missing service, no credentials), say so instead of omitting it.

<a name="verified-honest-reporting-correct-example"></a>

### Correct Example

```markdown
## Verification
- `php artisan test --compact tests/Feature/RefundInvoiceTest.php` → 6 passed
- `vendor/bin/pint --dirty` → 2 files fixed
- Larastan: not run (no phpstan config yet)

## Risks
- Stripe refund API not exercised against the sandbox; covered by `Http::fake()` only.
```

<a name="verified-honest-reporting-anti-pattern"></a>

### Anti-Pattern

```markdown
Implemented refunds. Everything works and all tests pass. 🚀
```

The report names no command and no result, and it does not mention that the external API was never called.

<a name="verified-honest-reporting-checklist"></a>

### Checklist

- [ ] Each claimed check names the command and its result.
- [ ] Skipped checks are listed with the reason.
- [ ] Assumptions that could not be verified are stated.
- [ ] Known limitations and risks are stated.

<a name="definition-of-done"></a>

## Definition Of Done

<a name="definition-of-done-principle"></a>

### Principle

A change is done only when requirements are met, risks are considered, tests are added, quality checks have run, side effects are reviewed, documentation is updated and remaining risks are stated.

<a name="definition-of-done-why-it-exists"></a>

### Why It Exists

Without a shared definition, "done" means "it worked on my machine". A short, explicit list makes review predictable and lets agents and humans be held to the same bar.

<a name="definition-of-done-when-to-apply"></a>

### When To Apply

Before requesting review and before merging.

<a name="definition-of-done-when-not-to-apply"></a>

### When Not To Apply

Draft pull requests opened to discuss an approach may be incomplete, but they must be marked as drafts and must not be merged.

<a name="definition-of-done-correct-example"></a>

### Correct Example

```markdown
- [x] Acceptance criteria from #142 satisfied
- [x] Authorization: only invoice owners can refund (policy + test)
- [x] Concurrency: double refund prevented by unique constraint + test
- [x] `php artisan test --compact` → 214 passed
- [x] Pint clean
- [x] API contract documentation updated with the new error code
- [ ] Risk: refunds above €10k still need manual approval (follow-up #150)
```

<a name="definition-of-done-anti-pattern"></a>

### Anti-Pattern

Merging because CI is green while the new endpoint has no authorization test and the migration was never run against a copy of production data.

<a name="definition-of-done-checklist"></a>

### Checklist

- [ ] Requirements and business rules satisfied.
- [ ] Security and data-integrity implications considered.
- [ ] Tests added or updated, and run.
- [ ] Quality checks run, or skipped checks disclosed.
- [ ] Diff reviewed for unintended side effects.
- [ ] Relevant documentation updated.
- [ ] Remaining limitations and risks stated.
