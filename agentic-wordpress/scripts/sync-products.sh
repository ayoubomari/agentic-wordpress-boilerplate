#!/usr/bin/env bash
#
# Syncs the WooCommerce product catalog (products, categories, images)
# between this local wp-env and a remote VPS running docker-compose.yml,
# over SSH. Products are matched by SKU only — never by post ID, which is
# only meaningful within one database — so re-running this is safe and
# idempotent: existing SKUs are updated in place, new ones are created,
# nothing is ever duplicated. See scripts/lib/import-products.php for why
# WooCommerce's own CSV importer isn't used here.
#
# Usage:
#   ./scripts/sync-products.sh pull [user@host] [remote-repo-path]
#   ./scripts/sync-products.sh push [user@host] [remote-repo-path] --force
#
#   pull   Remote catalog -> local (default direction). Pulls real product
#          data down to develop against. Safe to run any time.
#   push   Local catalog -> remote. Overwrites matching SKUs on the target.
#          Only for an initial catalog migration before a store goes live —
#          never as routine sync against a live store, since it can stomp
#          real stock/price edits made directly on the live site after your
#          last pull. Requires --force for exactly that reason.
#
# user@host and remote-repo-path are optional — if omitted, they come from
# VPS_HOST / VPS_REMOTE_PATH in .env (copy .env.example to get started) or,
# in CI, from repository secrets set as env vars. See
# .github/workflows/sync-products.yml for the CI entry point, and
# scripts/lib/ssh-target.sh for exactly how the target and SSH key are
# resolved.
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

POSITIONAL=()
FORCE=0
for arg in "$@"; do
	case "$arg" in
	--force) FORCE=1 ;;
	*) POSITIONAL+=("$arg") ;;
	esac
done

DIRECTION="${POSITIONAL[0]:-}"
CLI_VPS_HOST="${POSITIONAL[1]:-}"
CLI_VPS_REMOTE_PATH="${POSITIONAL[2]:-}"

usage() {
	sed -n '3,28p' "$0" | sed 's/^# \{0,1\}//'
	exit 1
}

[ "$DIRECTION" = "pull" ] || [ "$DIRECTION" = "push" ] || usage

if [ "$DIRECTION" = "push" ] && [ "$FORCE" != "1" ]; then
	echo "Refusing to push local catalog data onto the target without --force." >&2
	echo "This overwrites matching SKUs on the target — see usage below." >&2
	echo >&2
	usage
fi

# shellcheck source=scripts/lib/ssh-target.sh
source "$REPO_ROOT/scripts/lib/ssh-target.sh"
HOST="$VPS_HOST"
REMOTE_PATH="$VPS_REMOTE_PATH"

REMOTE_WP="cd $REMOTE_PATH && docker compose run --rm -T wpcli"
LOCAL_WP="wp-env run cli wp"
# `wp eval` (unlike `eval-file`) takes bare PHP statements, not a full file —
# it errors on a leading `<?php` tag. The .php files under scripts/lib/ keep
# the tag anyway (every editor/linter expects it on a real PHP file); strip
# it right before embedding, on whichever side is actually reading the file.
STRIP_PHP_TAG="sed '1{/^<?php\$/d}'"

extract_csv_b64() {
	# Pulls the base64 payload out from between the export script's markers,
	# discarding any WP-CLI/Docker banner noise around it.
	sed -n '/===AGENTIC-CSV-START===/,/===AGENTIC-CSV-END===/p' | sed '1d;$d'
}

php_body() {
	sed '1{/^<?php$/d}' "$1"
}

if [ "$DIRECTION" = "pull" ]; then
	echo "→ Exporting product catalog from $HOST ..."
	CSV_B64="$(ssh "${SSH_OPTS[@]}" "$HOST" "$REMOTE_WP eval \"\$($STRIP_PHP_TAG scripts/lib/export-products.php)\"" | extract_csv_b64)"
	[ -n "$CSV_B64" ] || {
		echo "Export produced no data — check the SSH command above ran cleanly." >&2
		exit 1
	}
	echo "→ Importing into local wp-env ..."
	IMPORT_PHP="define('AGENTIC_SYNC_CSV_B64', '$CSV_B64');
$(php_body scripts/lib/import-products.php)"
	$LOCAL_WP eval "$IMPORT_PHP"
else
	echo "→ Exporting product catalog from local wp-env ..."
	CSV_B64="$($LOCAL_WP eval "$(php_body scripts/lib/export-products.php)" | extract_csv_b64)"
	[ -n "$CSV_B64" ] || {
		echo "Export produced no data — check wp-env is running." >&2
		exit 1
	}
	echo "→ Importing into $HOST ..."
	IMPORT_PHP="define('AGENTIC_SYNC_CSV_B64', '$CSV_B64');
$(php_body scripts/lib/import-products.php)"
	echo "$IMPORT_PHP" | ssh "${SSH_OPTS[@]}" "$HOST" "$REMOTE_WP eval \"\$(cat)\""
fi

echo "→ Done."
