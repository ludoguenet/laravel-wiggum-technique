#!/usr/bin/env bash
set -e

i=0

while :; do
  i=$((i + 1))
  echo "=== Ralph pass $i ==="

  claude -p "$(cat <<'EOF'
Follow these steps in order, for exactly one task, this pass:

1. Read PROMPT.md.
2. Find the next incomplete task (an unchecked "- [ ]" item in the Progress checklist).
3. Implement it, including its acceptance test.
4. Run `vendor/bin/pest --compact` to confirm the implementation works and nothing else broke.
5. Commit your changes with a clear, scoped commit message.
6. Update PROMPT.md: check off the task you just completed.
7. If every task in PROMPT.md is now checked off, say so clearly in your final message.
EOF
)" --dangerously-skip-permissions

  if ! grep -q '^- \[ \]' PROMPT.md; then
    echo "All tasks checked off after pass $i — stopping."
    break
  fi
done

echo "Tests green after $i pass(es) — stopping."
