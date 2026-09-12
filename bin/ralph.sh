#!/usr/bin/env bash
set -e

i=0

while :; do
  i=$((i + 1))
  echo "=== Ralph pass $i ==="

  claude -p "$(cat PROMPT.md)" --dangerously-skip-permissions

  vendor/bin/pest --compact && break
done

echo "Tests green after $i pass(es) — stopping."
