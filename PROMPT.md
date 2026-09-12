# Spec: 3 Team Features

Re-read this file every pass. Work on exactly **one unchecked task** per pass, then check it off below — this checklist, not just a green test run, is what tells the loop whether to stop.

## Progress checklist

- [ ] Feature 1 — Rename team
- [ ] Feature 2 — Leave team
- [ ] Feature 3 — Cancel pending invitation

Check a box only after that feature's acceptance test passes and the full suite is green. Never check a box preemptively, and never uncheck one that's already done.

## Feature 1 — Rename team

- A team owner can update their team's `name`.
- A non-owner attempting to rename gets a 403 (`TeamPolicy@update` already exists and already enforces owner-only — reuse it, do not duplicate the check).
- Renaming does not change `owner_id`, members, or invitations.
- Validation: `name` required, string, same rules as team creation (`StoreTeamRequest`) — reuse or mirror, do not invent new rules.

**Acceptance test:** owner can rename → team persists new name. Non-owner attempts to rename → 403, name unchanged.

## Feature 2 — Leave team

- Any member who is **not** the owner can remove themselves from a team.
- The owner cannot leave their own team — attempting to do so fails with a `ValidationException::withMessages` error, same pattern as `TeamMemberController@destroy` uses for "owner cannot be removed."
- Leaving detaches the user from the team's `users` relation. It does not delete the team.

**Acceptance test:** non-owner member leaves → detached from team, redirected. Owner attempts to leave → validation error, still attached.

## Feature 3 — Cancel pending invitation

- A team owner can cancel an invitation they (or another inviter) sent on their team, but only while it is still `pending`.
- Non-owners get a 403.
- Cancelling an invitation that is not pending (already accepted/declined) fails with a `ValidationException::withMessages` error, same pattern as `InvitationController@accept`/`decline` use for "no longer pending."
- Cancelling deletes the `TeamInvitation` record (it does not just change status — cancelled invitations should not linger in the list).
- A cancelled invitation can no longer be accepted or declined (it no longer exists).

**Acceptance test:** owner cancels a pending invitation → record gone. Non-owner attempts to cancel → 403. Owner attempts to cancel a non-pending invitation → validation error.

---

## Conventions to follow (do not deviate)

- Authorization goes through `Gate::authorize(...)` in the controller, backed by a named ability on `TeamPolicy` or `TeamInvitationPolicy` — follow the existing `update`, `removeMember`, `inviteMember`, `respond` pattern. Add new abilities the same way (e.g. `leave`, `cancelInvitation`) rather than inlining checks.
- Domain-rule failures (owner can't leave, invitation not pending) use `ValidationException::withMessages(...)`, not custom exceptions or abort codes.
- Controllers redirect with `redirect()->route(...)` back to the relevant `teams.show` or `invitations.index` view, matching existing controllers.
- Multi-step writes that touch more than one row go through `DB::transaction(...)`, matching `TeamController@store` and `InvitationController@accept`.
- New routes live in `routes/web.php`, inside the existing `auth` middleware group, next to the related existing routes (team routes near team routes, invitation routes near invitation routes).
- New Pest feature tests live in `tests/Feature`, using existing factories (`Team::factory()`, `User::factory()`, `TeamInvitation::factory()`) — do not hand-build models.
- Match existing naming: `TeamController`, `TeamMemberController`, `TeamInvitationController`, `InvitationController` already exist — extend them rather than creating new controllers, unless an existing one has no sensible action name for the new route.

## Hard guardrails

- Do not modify any file under `database/migrations/` — the existing schema already supports all 3 features.
- Do not modify `composer.json`, `composer.lock`, `package.json`, or `package-lock.json`.
- Do not edit, weaken, or delete any existing test in `tests/Feature` or `tests/Unit`. Only add new tests.
- Do not touch files outside `app/`, `routes/web.php`, `tests/Feature`, and (only if strictly required) `resources/views/teams` or `resources/views/invitations`.
- Run `vendor/bin/pest --compact` after every change. A feature is not done until its acceptance test passes and the rest of the suite is still green.
- Commit your work for the task you just completed before finishing the pass, with a clear, scoped commit message. One task per commit — do not batch multiple features into one commit.
