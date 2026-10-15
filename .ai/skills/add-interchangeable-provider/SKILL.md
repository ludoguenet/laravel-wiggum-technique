---
name: add-interchangeable-provider
description: Add or replace an interchangeable implementation in a Laravel module, such as a CAPTCHA, payment, storage, notification or external API provider. Use when a feature must support multiple implementations behind a stable contract.
---

# Add an interchangeable provider

## Objective

Implement a new provider without coupling consumers to its concrete implementation or changing unrelated business behavior.

## Reference

Read `docs/engineering/02-domain-modules.md` first. It contains the worked CAPTCHA example (contract, drivers, configuration binding, fail-closed behavior and contract tests) that this procedure follows.

## Procedure

### 1. Inspect the existing architecture

- Identify the module and its public contract.
- Find existing implementations and their configuration.
- Inspect service providers, container bindings, consumers and tests.
- Determine whether the existing contract is sufficient.
- Do not create a second abstraction if an appropriate one already exists.

### 2. Define the contract

- Model the behavior required by consumers.
- Keep the contract small and implementation-independent.
- Use explicit input and output types where appropriate.
- Define expected behavior for invalid input, timeouts and provider failures.
- Avoid leaking provider-specific objects into the public interface.

### 3. Implement the provider

- Keep HTTP requests, authentication, payload mapping and response parsing inside the provider.
- Use Laravel's HTTP client or the project's established integration layer.
- Apply appropriate timeouts.
- Handle transport errors and invalid responses explicitly.
- Never expose private credentials.
- Do not silently treat a failed security verification as success.

### 4. Wire the implementation

- Add validated configuration.
- Bind the contract to the selected implementation through Laravel's container.
- Follow existing configuration conventions.
- Fail clearly for unsupported drivers.
- Ensure development and test implementations cannot be enabled accidentally in production.

### 5. Integrate with consumers

- Inject the contract instead of instantiating the concrete provider.
- Keep provider selection out of controllers and business rules.
- Preserve existing consumer behavior.
- Update frontend or API integration only when required by the contract.

### 6. Test the result

- Test the provider's response mapping and error behavior.
- Test contract behavior against all relevant implementations.
- Use fakes for external HTTP requests.
- Test invalid configuration and provider failure.
- Verify that existing consumers remain independent of the new provider.
- Test security-sensitive failure paths.

### 7. Review the design

Before completion, verify:

- The abstraction solves a real requirement.
- No unnecessary factory or additional layer was introduced.
- Dependencies point in the intended direction.
- Secrets are not exposed.
- No insecure fallback exists.
- Tests, static analysis and relevant checks pass.

## Final report

Summarize:

- Files changed.
- Contract and provider behavior.
- Configuration required.
- Tests and checks executed.
- Failure behavior and remaining risks.
