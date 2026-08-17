# Resolves where "the VPS" is and how to reach it — sourced by
# scripts/deploy.sh and scripts/sync-products.sh so both agree on the same
# defaults instead of each hardcoding them.
#
# Local (human) use: values come from .env at the repo root (copy
# .env.example to .env and fill it in — .env is gitignored).
# CI use: .github/workflows/*.yml set the same variable names as env vars
# from repository Secrets directly, so there's no .env file in CI and this
# just falls through to the already-set environment.
#
# Not meant to be run directly — must be sourced with REPO_ROOT already set.

if [ -f "$REPO_ROOT/.env" ]; then
	set -a
	# shellcheck source=/dev/null
	source "$REPO_ROOT/.env"
	set +a
fi

VPS_HOST="${CLI_VPS_HOST:-${VPS_HOST:-}}"
VPS_REMOTE_PATH="${CLI_VPS_REMOTE_PATH:-${VPS_REMOTE_PATH:-~/agentic-wordpress}}"

if [ -z "$VPS_HOST" ]; then
	echo "VPS_HOST not set. Either pass a host as an argument, or set VPS_HOST" >&2
	echo "in .env (copy .env.example to .env first) or as a CI secret." >&2
	exit 1
fi

# ssh/scp option array: an explicit key if VPS_SSH_KEY points at a real
# file (typical for local/.env use), otherwise ssh's own default
# identity/agent resolution applies unchanged (typical for CI, where
# webfactory/ssh-agent has already loaded the key into an agent).
SSH_OPTS=()
if [ -n "${VPS_SSH_KEY:-}" ] && [ -f "$VPS_SSH_KEY" ]; then
	SSH_OPTS+=(-i "$VPS_SSH_KEY")
fi
