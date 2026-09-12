# Video Script: Ralph Wiggum Technique (10 min)

**Goal:** show the "dumb loop" technique live — a bash `for` loop hammering Claude Code against a fixed spec until tests pass — shipping 3 small features on this Laravel app.

**App:** teams/invitations demo. Existing: create/list/view teams, invite, accept/decline, remove member.

**Features to add on camera:**
1. **Rename team** — owner-only, `PUT /teams/{team}`.
2. **Leave team** — member removes self, `DELETE /teams/{team}/leave`.
3. **Cancel pending invitation** — owner-only, `DELETE /teams/{team}/invitations/{invitation}`.

Each is small, has an obvious pass/fail test, and touches route + controller + policy + test — enough surface to prove the loop actually works without eating the whole runtime.

---

## Timeline

| Time | Beat | On screen |
|---|---|---|
| 0:00–0:45 | Hook | "What if I never touched the keyboard after this line?" — show the one-liner loop full screen. |
| 0:45–1:45 | What is Ralph Wiggum | Quick def: dumb loop + fixed spec + test as judge. Named after the Simpsons kid — persistent, not clever. |
| 1:45–2:30 | The spec file | Open `PROMPT.md`, show the 3 features listed as acceptance criteria, not instructions. |
| 2:30–3:15 | The loop | Reveal the bash `for` loop (below). Explain each line in one breath. |
| 3:15–3:30 | Baseline | Run `php artisan test --compact` once — green, clean starting point. |
| 3:30–4:00 | Launch | Kick off the loop. Terminal takes over screen. |
| 4:00–7:30 | Fast-forward | Sped-up footage of iterations: edit → test run → red → edit → green. Cut in 3 quick zooms: route added, policy check added, test file created. |
| 7:30–8:15 | Loop exits | Tests green 2 iterations in a row → loop breaks. Show final iteration count on screen. |
| 8:15–9:15 | Review | `git diff` walkthrough — 3 features, no scope creep, no touched migrations/deps. Run full suite once more. |
| 9:15–9:45 | Guardrails callout | Flash bullets: no new deps, no schema edits, tests never weakened. |
| 9:45–10:00 | Close | "The loop was dumb. The spec and the tests weren't." CTA. |

---

## The loop (show full screen at 2:30)

```bash
#!/usr/bin/env bash
set -e

MAX_ITERATIONS=15

for i in $(seq 1 "$MAX_ITERATIONS"); do
  echo "=== Ralph pass $i/$MAX_ITERATIONS ==="

  claude -p "$(cat PROMPT.md)" --dangerously-skip-permissions

  if vendor/bin/pest --compact; then
    echo "Tests green on pass $i — stopping."
    break
  fi
done
```

Talking points while it's on screen:
- No branching logic, no orchestration — just "run agent, run tests, stop when green."
- `PROMPT.md` never changes between passes; the agent re-reads it and the current diff every time.
- The loop doesn't know or care *what* changed — Pest is the judge, not the agent's opinion of itself.

---

## PROMPT.md contents (show at 1:45, keep on screen small in corner during the run)

- One sentence per feature, written as an acceptance criterion, not a step:
  - "A team owner can rename their team; non-owners get a 403; a renamed team keeps its members."
  - "Any team member can leave a team they don't own; the owner cannot leave their own team; leaving removes them from the members list."
  - "A team owner can cancel a pending invitation; the invitation record is removed; accepting a cancelled invitation fails."
- One line of guardrails baked into the prompt itself: "Do not modify migrations, dependencies, or existing tests. Follow existing controller/policy conventions in this app."

---

## B-roll / cutaway shot list

- Terminal split: loop output left, `git status --short` growing on the right.
- One quick diff zoom per feature: the new route line, the policy method, the new Pest test.
- A deliberate "red" moment left in the edit (iteration fails, loop just tries again) — this is the whole point, don't cut it out.
- Final `git log --oneline` or diff stat as the "receipt" shot before the close.

## On-screen text callouts (lower thirds)

- "Spec doesn't change. Loop doesn't think. Tests decide."
- "Guardrails: no new deps · no migrations · no weakened tests"
- "Iteration N → tests green → stop"

## Cut if running long

- Trim to 2 features (drop "cancel invitation") and extend fast-forward section instead — keeps the loop's iteration count visibly higher without adding runtime.
