---
description: Checks the code (Pint + PHPStan + tests) then creates a conventional commit
argument-hint: (optional) commit message
allowed-tools: Bash(vendor/bin/pint:*), Bash(vendor/bin/phpstan:*), Bash(php artisan test:*), Bash(git add:*), Bash(git commit:*), Bash(git diff:*)
disable-model-invocation: true
---

Diff of stages files:
!`git diff --cached`

Before committing, run these in order and fix issues if any:
1. vendor/bin/pint --dirty (formatting)
2. vendor/bin/phpstan analyse (static analysis)
3. php artisan test --parallel (tests)

If everything passes, create a commit with a message follwing the "Conventional Commits"
convention (feat/fix/refactor/chore...) based on the diff above. If $ARGUMENTS is provided, use it as the message.
If any step fails, DO NOT commit and explain what needs to be fixed.
