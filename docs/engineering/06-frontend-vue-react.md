# Frontend: Vue and React

- [Introduction](#introduction)
- [Single Source of Truth](#single-source-of-truth)
- [Derive, Don't Store](#derive-dont-store)
- [Server State vs UI State](#server-state)
- [Request Lifecycle and Stale Responses](#request-lifecycle)
- [Server Validation Is Authoritative](#server-validation)
- [Effects and Watchers Are a Last Resort](#effects-and-watchers)
- [Testing User-Visible Behavior](#testing-user-visible-behavior)

<a name="introduction"></a>
## Introduction

This document expands section 9 ("Vue and React") of `.ai/guidelines/engineering-standards.md`. It is framework-agnostic: the project has **no Vue or React installed yet** (Vite 8 + Tailwind 4 only). Examples show Vue 3 (Composition API, `<script setup>`) and React (hooks) side by side. When a frontend framework is adopted, follow only the one actually installed.

Two transport options are assumed:

- **Inertia** (`inertiajs/inertia-laravel`, v3 docs available through Boost `search-docs`): the controller returns page props; the server owns the data and the page.
- **Plain JSON API**: controllers return Eloquent API Resources, consumed with `fetch`. See [05-api-contracts.md](05-api-contracts.md).

Related: [01-architecture.md](01-architecture.md), [03-laravel-backend.md](03-laravel-backend.md), [05-api-contracts.md](05-api-contracts.md), [07-testing.md](07-testing.md), [08-security.md](08-security.md), [11-ai-assisted-development.md](11-ai-assisted-development.md).

> [!NOTE]
> Adding `vue`, `react`, `@inertiajs/*`, `vitest` or `@testing-library/*` changes the project's dependencies and requires approval first (see `CLAUDE.md`, "Do not change the application's dependencies without approval").

<a name="single-source-of-truth"></a>
## Single Source of Truth

<a name="single-source-of-truth-principle"></a>
### Principle

Every piece of state has exactly one owner. Everything else reads from that owner or receives it as props.

<a name="single-source-of-truth-why-it-exists"></a>
### Why It Exists

Two copies of the same value drift: a cart badge says 3 while the cart page says 2, because one copy was updated and the other was not. Bugs of this kind are intermittent and depend on navigation order, which makes them expensive to reproduce.

<a name="single-source-of-truth-when-to-apply"></a>
### When To Apply

- Any value displayed in more than one component.
- Any value that the server also stores (it is the owner; see [Server State vs UI State](#server-state)).
- Selection, filters and pagination that should survive a reload: put them in the URL query string, which is then the owner.

<a name="single-source-of-truth-when-not-to-apply"></a>
### When Not To Apply

- A deliberate draft: a form holds a local copy of a record *on purpose* so the user can edit and cancel. The copy is the draft's owner until submit.
- Optimistic updates, where a temporary local value is shown and reconciled with the server response.

<a name="single-source-of-truth-correct-example"></a>
### Correct Example

The parent owns `selectedId`; children receive it and emit changes.

```vue
<script setup>
import { ref } from 'vue'
const props = defineProps({ invoices: Array })
const selectedId = ref(null)
</script>

<template>
  <InvoiceList :invoices="props.invoices" :selected-id="selectedId" @select="selectedId = $event" />
  <InvoiceDetail :invoice="props.invoices.find(i => i.id === selectedId)" />
</template>
```

```jsx
function InvoicesPage({ invoices }) {
  const [selectedId, setSelectedId] = useState(null)
  const selected = invoices.find((invoice) => invoice.id === selectedId)

  return (
    <>
      <InvoiceList invoices={invoices} selectedId={selectedId} onSelect={setSelectedId} />
      <InvoiceDetail invoice={selected} />
    </>
  )
}
```

Store the **id**, not a copy of the selected object, so an updated `invoices` prop is reflected immediately.

<a name="single-source-of-truth-anti-pattern"></a>
### Anti-Pattern

```jsx
const [selectedInvoice, setSelectedInvoice] = useState(null)
// later: invoices prop is reloaded with the paid status,
// but selectedInvoice still holds the old object -> detail shows "unpaid"
```

The copied object is a second source of truth that never receives updates.

<a name="single-source-of-truth-checklist"></a>
### Checklist

- [ ] Each piece of state has one identifiable owner (component, URL, or server).
- [ ] Selections store identifiers, not copies of objects.
- [ ] Shareable view state (filters, page, tab) lives in the query string.
- [ ] Any intentional copy (draft, optimistic value) is named as such (`draft`, `optimistic…`).
- [ ] No two components write the same value independently.

<a name="derive-dont-store"></a>
## Derive, Don't Store

<a name="derive-dont-store-principle"></a>
### Principle

If a value can be computed from existing state or props, compute it during render (`computed` in Vue, plain expression or `useMemo` in React). Never store it in its own state variable.

<a name="derive-dont-store-why-it-exists"></a>
### Why It Exists

Stored derived values must be manually updated on every change of their inputs. One missed path (a deletion, a reload) yields a stale total, a wrong "empty" message, or a button enabled when it should not be.

<a name="derive-dont-store-when-to-apply"></a>
### When To Apply

- Totals, counts, filtered or sorted lists, booleans like `isEmpty`, `canSubmit`, `hasUnpaid`.
- Formatting (currency, dates) of server values.

<a name="derive-dont-store-when-not-to-apply"></a>
### When Not To Apply

- The value is genuinely expensive and measured as such: use `useMemo` / `computed` (cached derivation), still not separate state.
- Business-critical values (prices, tax, discounts) must be **recalculated server-side**; a client-derived total is for display only.
- A snapshot that must *not* follow its inputs (e.g. "price when the page was opened") is real state, not derived.

<a name="derive-dont-store-correct-example"></a>
### Correct Example

```vue
<script setup>
import { computed } from 'vue'
const props = defineProps({ invoices: Array })
const unpaid = computed(() => props.invoices.filter(i => !i.paid_at))
const unpaidTotal = computed(() => unpaid.value.reduce((sum, i) => sum + i.amount_cents, 0))
</script>
```

```jsx
function UnpaidSummary({ invoices }) {
  const unpaid = invoices.filter((invoice) => !invoice.paid_at)
  const unpaidTotal = unpaid.reduce((sum, invoice) => sum + invoice.amount_cents, 0)

  return <p>{unpaid.length} unpaid, {unpaidTotal / 100} EUR</p>
}
```

In React, plain computation is the default; add `useMemo` only after profiling shows a cost.

<a name="derive-dont-store-anti-pattern"></a>
### Anti-Pattern

```jsx
const [unpaidTotal, setUnpaidTotal] = useState(0)
useEffect(() => {
  setUnpaidTotal(invoices.filter((i) => !i.paid_at).reduce((s, i) => s + i.amount_cents, 0))
}, [invoices])
```

An extra render with a stale value, an extra state variable, and an effect that exists only to copy data.

<a name="derive-dont-store-checklist"></a>
### Checklist

- [ ] No `ref`/`useState` holds a value computable from other state or props.
- [ ] Vue derivations use `computed`, not `watch` + assignment.
- [ ] React derivations are render-time expressions; `useMemo` is justified by a measurement.
- [ ] Monetary values shown to the user are recomputed by the server before any write.
- [ ] Snapshots that must not follow inputs are documented as such.

<a name="server-state"></a>
## Server State vs UI State

<a name="server-state-principle"></a>
### Principle

Server-owned business data (invoices, users, permissions) belongs to the server and reaches components through Inertia props or a request cache. UI state (open modal, active tab, input draft) lives locally in the component that needs it. Do not mirror server data into a global store.

<a name="server-state-why-it-exists"></a>
### Why It Exists

A Pinia/Redux/Context copy of server data must be invalidated by hand after every mutation, by every user, in every tab. When forgotten, users act on outdated data (refunding an already-refunded invoice) and the client store becomes a second, unauthorized "database".

<a name="server-state-when-to-apply"></a>
### When To Apply

- **Inertia**: treat page props as read-only server state; refresh with `router.reload({ only: ['invoices'] })` (partial reload) after a mutation, or let the redirect after `form.post()` deliver fresh props.
- **JSON API**: use a request cache keyed by URL/params (e.g. a query library, after approval) or component-local state fetched per page.

<a name="server-state-when-not-to-apply"></a>
### When Not To Apply

- Truly global UI state: theme, toasts, an authenticated-user summary shared by Inertia via `HandleInertiaRequests::share()`. A small store or context is fine here.
- Offline-first or real-time collaborative apps have different consistency models; document that decision in an ADR first.

<a name="server-state-correct-example"></a>
### Correct Example

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
defineProps({ invoices: Array })        // server state: read-only
const isConfirmOpen = ref(false)         // UI state: local

function refund(invoice) {
  router.post(`/invoices/${invoice.id}/refund`, {}, {
    onSuccess: () => { isConfirmOpen.value = false },
  })
}
</script>
```

The redirect after the POST re-renders the page with fresh `invoices` props; nothing is patched by hand.

<a name="server-state-anti-pattern"></a>
### Anti-Pattern

```js
// stores/invoices.js
export const useInvoiceStore = defineStore('invoices', {
  state: () => ({ invoices: [] }),
  actions: {
    markRefunded(id) { this.invoices.find(i => i.id === id).refunded = true },
  },
})
```

The client decides that an invoice is refunded without the server confirming it; the store and database diverge on failure or concurrent edits.

<a name="server-state-checklist"></a>
### Checklist

- [ ] Server data enters components only via props or a request cache.
- [ ] Props are never mutated; changes go through a request.
- [ ] After a mutation, data is refreshed from the server (redirect, partial reload, or cache invalidation).
- [ ] Global stores contain UI-only state, each entry justified.
- [ ] Shared Inertia props expose only intended fields (no full `User` model).

<a name="request-lifecycle"></a>
## Request Lifecycle and Stale Responses

<a name="request-lifecycle-principle"></a>
### Principle

Every asynchronous read models five states explicitly: **loading, success, empty, error, cancelled/stale**. Only the response to the latest request may update the UI.

<a name="request-lifecycle-why-it-exists"></a>
### Why It Exists

A search box sends "inv" then "inv-10". If the first response arrives last, the list shows results for "inv" under the "inv-10" query. Without an error state the screen stays blank forever; without an empty state users cannot tell "nothing found" from "still loading".

<a name="request-lifecycle-when-to-apply"></a>
### When To Apply

- Search-as-you-type, filters, pagination, tab switches: anything that can fire a new request before the previous one resolves.
- Components that can unmount while a request is pending.

<a name="request-lifecycle-when-not-to-apply"></a>
### When Not To Apply

- Inertia visits: the router already cancels the previous visit and exposes `processing`; do not rebuild that machinery.
- A single fetch on mount with no changing inputs needs loading/error/empty, but cancellation is optional.

<a name="request-lifecycle-correct-example"></a>
### Correct Example

```jsx
function InvoiceSearch({ query }) {
  const [state, setState] = useState({ status: 'loading', invoices: [] })

  useEffect(() => {
    const controller = new AbortController()
    setState({ status: 'loading', invoices: [] })

    fetch(`/api/invoices?q=${encodeURIComponent(query)}`, { signal: controller.signal })
      .then((response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        return response.json()
      })
      .then(({ data }) => setState({ status: data.length ? 'success' : 'empty', invoices: data }))
      .catch((error) => {
        if (error.name !== 'AbortError') setState({ status: 'error', invoices: [] })
      })

    return () => controller.abort()
  }, [query])

  // render one branch per status
}
```

In Vue, the same logic lives in `watch(() => props.query, (query, _old, onCleanup) => { ... }, { immediate: true })`, calling `onCleanup(() => controller.abort())`.

Aborting the previous request on input change guarantees the latest response wins; `AbortError` is not shown as an error. Here the watcher is legitimate: it synchronizes with an external system (the network).

<a name="request-lifecycle-anti-pattern"></a>
### Anti-Pattern

```js
const [invoices, setInvoices] = useState([])
useEffect(() => {
  fetch(`/api/invoices?q=${query}`).then((r) => r.json()).then(({ data }) => setInvoices(data))
}, [query])
```

No cancellation (out-of-order responses win), no `response.ok` check (a 500 body is treated as data), no loading/empty/error distinction, and the query is not URL-encoded.

<a name="request-lifecycle-checklist"></a>
### Checklist

- [ ] Loading, success, empty and error each have a distinct rendering.
- [ ] Each request triggered by changing input is aborted on change or unmount (`AbortController`).
- [ ] `AbortError` is ignored, not displayed.
- [ ] Non-2xx responses are treated as errors (`response.ok`).
- [ ] Error messages are generic to users; details are not leaked from the server.
- [ ] Inertia visits rely on `processing` / built-in cancellation rather than custom code.

<a name="server-validation"></a>
## Server Validation Is Authoritative

<a name="server-validation-principle"></a>
### Principle

The Laravel Form Request is the validation contract. Client-side checks are a UX convenience; the UI must display the server's 422 errors field by field.

<a name="server-validation-why-it-exists"></a>
### Why It Exists

Client validation is bypassed by any HTTP client. Rules that depend on the database (unique email, invoice still refundable) cannot be checked on the client at all. A UI that ignores 422 responses leaves users with a silent, non-working button.

<a name="server-validation-when-to-apply"></a>
### When To Apply

- Every form that writes data.
- With Inertia, use `useForm`: `form.errors` is populated automatically when Laravel throws a `ValidationException`, and `form.processing` prevents double submits.
- With a JSON API, map the `errors` object of the 422 response to fields.

<a name="server-validation-when-not-to-apply"></a>
### When Not To Apply

- Instant feedback on obvious format errors (required, max length) may be duplicated client-side, as long as the server rule remains and wins.
- Duplicating complex rules client-side "just in case": prefer Laravel Precognition (after approval) over hand-copied rules that drift.

<a name="server-validation-correct-example"></a>
### Correct Example

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
const form = useForm({ email: '', message: '' })
</script>

<template>
  <form @submit.prevent="form.post('/tickets', { onSuccess: () => form.reset() })">
    <input v-model="form.email" type="email" />
    <p v-if="form.errors.email">{{ form.errors.email }}</p>
    <textarea v-model="form.message" />
    <p v-if="form.errors.message">{{ form.errors.message }}</p>
    <button :disabled="form.processing">Send</button>
  </form>
</template>
```

```jsx
import { useForm } from '@inertiajs/react'

function TicketForm() {
  const { data, setData, post, processing, errors, reset } = useForm({ email: '', message: '' })

  function submit(event) {
    event.preventDefault()
    post('/tickets', { onSuccess: () => reset() })
  }

  return (
    <form onSubmit={submit}>
      <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
      {errors.email && <p>{errors.email}</p>}
      <button disabled={processing}>Send</button>
    </form>
  )
}
```

<a name="server-validation-anti-pattern"></a>
### Anti-Pattern

```js
if (!email.includes('@')) { error.value = 'Invalid email'; return }
await fetch('/api/tickets', { method: 'POST', body: JSON.stringify({ email, message }) })
router.visit('/tickets') // navigates even if the server answered 422
```

The client rule is treated as sufficient and the server's verdict is ignored.

<a name="server-validation-checklist"></a>
### Checklist

- [ ] Every write endpoint has a Form Request (or `validate()`) independent of the client.
- [ ] Each field renders its server error message.
- [ ] Submit is disabled while `processing` to avoid duplicate submissions.
- [ ] Success handling runs only on a successful response (`onSuccess` / `response.ok`).
- [ ] A feature test covers the 422 case on the server.

<a name="effects-and-watchers"></a>
## Effects and Watchers Are a Last Resort

<a name="effects-and-watchers-principle"></a>
### Principle

Use `useEffect` / `watch` only to synchronize with something outside the framework (network, timers, DOM APIs, storage). Never use them to keep two pieces of state in sync; derive instead, or update both in the event handler.

<a name="effects-and-watchers-why-it-exists"></a>
### Why It Exists

State-syncing effects cause extra renders, flashes of wrong values, and chains of effects that trigger each other (sometimes infinitely). They also hide the cause of a change: reading the code no longer tells you *why* a value changed.

<a name="effects-and-watchers-when-to-apply"></a>
### When To Apply

- Subscriptions (Echo channels, `ResizeObserver`), fetching on input change, focusing an element, writing to `localStorage`.

<a name="effects-and-watchers-when-not-to-apply"></a>
### When Not To Apply

- Reacting to a user action: do it in the event handler.
- Resetting state when a prop changes: in React, use a `key` on the component; in Vue, derive or reset in the handler that changes the prop.
- Global state: avoid adding a store when lifting state to the nearest common parent suffices.

<a name="effects-and-watchers-correct-example"></a>
### Correct Example

```jsx
function InvoiceFilter({ onChange }) {
  const [status, setStatus] = useState('all')

  function handleChange(event) {
    setStatus(event.target.value)
    onChange(event.target.value) // side effect caused by the event, in the event handler
  }

  return <select value={status} onChange={handleChange}>{/* options */}</select>
}
```

<a name="effects-and-watchers-anti-pattern"></a>
### Anti-Pattern

```vue
<script setup>
const fullName = ref('')
watch([firstName, lastName], ([first, last]) => { fullName.value = `${first} ${last}` })
watch(status, (value) => emit('change', value))
</script>
```

The first watcher should be a `computed`; the second hides a user action behind a reactive side effect and also fires when `status` is changed programmatically.

<a name="effects-and-watchers-checklist"></a>
### Checklist

- [ ] Every `useEffect` / `watch` names the external system it synchronizes with.
- [ ] No effect only calls `setState` / assigns a ref from other state.
- [ ] Actions triggered by user events run in event handlers.
- [ ] Every effect with a subscription or request has a cleanup.
- [ ] Each global store entry is justified by more than one distant consumer.

<a name="testing-user-visible-behavior"></a>
## Testing User-Visible Behavior

<a name="testing-user-visible-behavior-principle"></a>
### Principle

Test what the user sees and does (text, roles, labels, clicks), not component internals. The recommended stack is Vitest + Testing Library (`@testing-library/vue` or `@testing-library/react`). **None is installed yet; adding them requires approval.**

<a name="testing-user-visible-behavior-why-it-exists"></a>
### Why It Exists

Tests asserting internal state (`wrapper.vm.isOpen`, hook return values) break on every refactor and still pass when the button is invisible. Behavior tests survive refactors and catch real regressions: a missing error message, a double submit.

<a name="testing-user-visible-behavior-when-to-apply"></a>
### When To Apply

- Forms (validation messages, disabled submit), conditional rendering of loading/empty/error states, important interactions (filters, confirmations).
- Regression tests for fixed frontend bugs.

<a name="testing-user-visible-behavior-when-not-to-apply"></a>
### When Not To Apply

- Authorization and validation rules: test them server-side in Pest feature tests ([07-testing.md](07-testing.md)); the frontend test only checks that errors are displayed.
- Purely presentational components without logic.
- Full journeys across pages: prefer Pest browser tests if that plugin is approved.

<a name="testing-user-visible-behavior-correct-example"></a>
### Correct Example

```js
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { test, expect, vi } from 'vitest'

test('shows the empty state when no invoice matches', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ data: [] })))

  render(<InvoiceSearch query="INV-9999" />)

  expect(await screen.findByText('No invoice found')).toBeInTheDocument()
})
```

Queries by visible text and roles, mocks only the network boundary.

<a name="testing-user-visible-behavior-anti-pattern"></a>
### Anti-Pattern

```js
const wrapper = mount(InvoiceSearch, { props: { query: 'x' } })
wrapper.vm.status = 'empty'
expect(wrapper.vm.status).toBe('empty')
```

Sets and reads internal state; proves nothing about what is rendered.

<a name="testing-user-visible-behavior-checklist"></a>
### Checklist

- [ ] Test tooling was approved before being added to `package.json`.
- [ ] Queries use roles, labels or visible text (`getByRole`, `findByText`).
- [ ] Only the network/Inertia boundary is mocked.
- [ ] Loading, empty, error and 422 display paths are covered for important screens.
- [ ] Interactions use `userEvent`, not direct state manipulation.
- [ ] Server-side rules are covered by Pest, not duplicated in frontend tests.
