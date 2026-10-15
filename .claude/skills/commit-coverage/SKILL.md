---
name: commit-coverage
description: Generates a test coverage report, checks the code (Pint + PHPStan), then creates a conventional commit
argument-hint: (optional) commit message
allowed-tools: Bash(vendor/bin/pint:*), Bash(vendor/bin/phpstan:*), Bash(php artisan test:*), Bash(php ${CLAUDE_SKILL_DIR}/scripts/coverage.php:*), Bash(git add:*), Bash(git commit:*), Bash(git diff:*)
disable-model-invocation: true
---

# Commit with coverage report

Diff of staged files:
!`git diff --cached`

Before committing, run in order:
1. vendor/bin/pint --dirty
2. vendor/bin/phpstan analyse
3. php artisan test --coverage --min=80 --coverage-clover=coverage.xml
4. Run `php ${CLAUDE_SKILL_DIR}/scripts/coverage.php coverage.xml` to generate a readable coverage summary per modified file (see reference.md for the expected output format)
If everything passes, create a conventional commit based on the diff above. if $ARGUMENTS is provided, use it as the message.
If any step fails, clearly explain which one and why, without commiting.
For the coverage report format details, see [reference.md](reference.md)
