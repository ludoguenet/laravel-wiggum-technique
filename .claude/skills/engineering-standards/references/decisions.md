# Architecture Decision Records

- [Introduction](#introduction)
- [When To Write A Decision Record](#when-to-write-a-decision-record)
- [Writing A Decision Record](#writing-a-decision-record)
- [Lifecycle](#lifecycle)

<a name="introduction"></a>

## Introduction

An Architecture Decision Record (ADR) captures one consequential technical decision: the context, the options considered, the choice and its consequences. ADRs explain *why* the code looks the way it does long after the pull request discussion is forgotten.

The [engineering principles](00-principles.md) describe defaults. An ADR records a deliberate choice, including any deliberate deviation from those defaults.

<a name="when-to-write-a-decision-record"></a>

## When To Write A Decision Record

Write an ADR when a decision is expensive to reverse or will surprise a future reader, for example:

- Introducing a new module boundary, architectural layer, contract or external provider.
- Choosing a database strategy for consistency (locking, idempotency, queueing) on a critical flow.
- Versioning or breaking a public API contract.
- Adding a significant dependency or infrastructure component (cache, queue backend, search engine).
- Deviating from a rule in these engineering standards for a specific, justified reason.

Do not write an ADR for choices that are easily reversed or already dictated by the standards.

<a name="writing-a-decision-record"></a>

## Writing A Decision Record

Copy [the template](decision-template.md) into the project's `docs/decisions/` directory (create it if missing), named with the next number and a short kebab-case title, for example `docs/decisions/0001-captcha-provider-contract.md`.

Keep it to one page. Link the pull request that implements it, and link the ADR from the code or document it affects when the decision would otherwise be surprising.

<a name="lifecycle"></a>

## Lifecycle

| Status | Meaning |
| --- | --- |
| `Proposed` | Under discussion in a pull request. |
| `Accepted` | Merged. The code follows it. |
| `Superseded by NNNN` | Replaced by a later decision. Keep the file. Never rewrite history. |
| `Deprecated` | No longer relevant, for example because the feature was removed. |

> [!NOTE]
> Accepted ADRs are immutable. To change a decision, write a new ADR that supersedes the old one and update the old one's status line only.
