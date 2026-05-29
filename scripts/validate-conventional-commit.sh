#!/usr/bin/env bash

set -euo pipefail

header="${1:-}"

if [[ -z "$header" ]]; then
    echo "No commit message header provided."
    exit 1
fi

# Allow git-generated merge and revert commits.
if [[ "$header" =~ ^Merge\  ]] || [[ "$header" =~ ^Revert\ \" ]]; then
    exit 0
fi

pattern='^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([a-z0-9._/-]+\))?(!)?: .+'

if [[ ! "$header" =~ $pattern ]]; then
    cat <<'EOF'
Invalid commit message.

Expected format:
  type(scope): short description

Examples:
  feat(billing): add invoice due date reminders
  fix(ci): correct release tag detection
  feat(api)!: remove legacy token endpoint

Allowed types:
  feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert

See CONTRIBUTING.md for full guidance.
EOF
    exit 1
fi
