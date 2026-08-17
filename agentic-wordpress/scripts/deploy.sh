#!/usr/bin/env bash
#
# Deploys code (theme, blocks, scripts, theme.json) to the VPS: git pull,
# then rebuild the block-editor JS bundles (agentic-blocks/build/ is
# gitignored — see CLAUDE.md's "Build step for block JS" — so a rebuild is
# required every deploy, not optional). PHP/template files need no restart:
# docker-compose.yml bind-mounts theme/agentic-theme and agentic-blocks
# straight from the checkout, so a `git pull` alone makes them live.
#
# This intentionally does NOT run scripts/setup-site.sh — that script
# hardcodes `wp-env run cli` (dev-only) and, even once made backend-
# agnostic, its content-seeding steps (sample products/posts) must never
# run against a live store. See "Deployment & environment sync" in
# CLAUDE.md.
#
# Usage: ./scripts/deploy.sh [user@host] [remote-repo-path]
# Same optional-target resolution as scripts/sync-products.sh — see
# scripts/lib/ssh-target.sh and .env.example.
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

CLI_VPS_HOST="${1:-}"
CLI_VPS_REMOTE_PATH="${2:-}"

# shellcheck source=scripts/lib/ssh-target.sh
source "$REPO_ROOT/scripts/lib/ssh-target.sh"
HOST="$VPS_HOST"
REMOTE_PATH="$VPS_REMOTE_PATH"

echo "→ Pulling latest code on $HOST ..."
ssh "${SSH_OPTS[@]}" "$HOST" "cd $REMOTE_PATH && git pull --ff-only"

echo "→ Rebuilding block-editor JS bundles ..."
ssh "${SSH_OPTS[@]}" "$HOST" "cd $REMOTE_PATH/agentic-blocks && docker run --rm -v \"\$(pwd)\":/app -w /app node:20 sh -c 'npm install && npm run build'"

echo "→ Done. PHP/template changes are already live via the bind mount;"
echo "  only the JS bundle rebuild above needed an explicit step."
