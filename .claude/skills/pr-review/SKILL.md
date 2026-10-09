---
name: pr-review
description: Full review of Laravel pull request
context: fork
agent: Explore
allowed-tools: Bash(gh pr diff:*), Bash(gh pr view:*), Bash(gh api:*)
---

Analyse the current pull request:
- Full diff: !`gh pr diff`
Existing review comments:
!`gh api "repos/{owner}/{repo}/pulls/$(gh pr view --json number -q .number)/comments"`

Check: Laravel conventions (naming, controller/service structure),
regression risks, missing test coverage. Summarise in 5 points max.
