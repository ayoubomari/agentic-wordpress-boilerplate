#!/usr/bin/env bash
#
# Usage: ./scripts/lighthouse-check.sh [path] [min-score] [categories]
#   ./scripts/lighthouse-check.sh /
#   ./scripts/lighthouse-check.sh /shop/ 95
#   ./scripts/lighthouse-check.sh /cart/ 90 performance,accessibility,best-practices
#
# Exits non-zero if any category scores below the threshold (default 90), so
# it can gate a verification pass instead of just printing numbers.
#
# NOTE on cart / checkout / my-account: those pages are intentionally
# noindex, so their SEO score is legitimately low ("Page is blocked from
# indexing"). That is correct behaviour for a cart, not a bug — audit them
# without the seo category rather than trying to make them indexable.
set -euo pipefail

PATH_ARG="${1:-/}"
MIN_SCORE="${2:-90}"
CATEGORIES="${3:-performance,accessibility,seo,best-practices}"
URL="http://localhost:8888${PATH_ARG}"
OUT="lighthouse-report.json"
# Pinned so a verification run is reproducible and does not silently change
# scoring between sessions.
LH_VERSION="12.8.2"

# Any HTTP response means the server is up. Deliberately not `curl -f`: a 404
# page is a real template that needs auditing too, and -f would reject it.
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL" || echo 000)"
if [ "$HTTP_CODE" = "000" ]; then
  echo "✖ $URL is not reachable — is 'wp-env start' running?" >&2
  exit 2
fi

CHROME_BIN="${CHROME_PATH:-$(command -v google-chrome || command -v google-chrome-stable || command -v chromium || true)}"
if [ -z "$CHROME_BIN" ]; then
  echo "✖ No Chrome/Chromium found. Install google-chrome or set CHROME_PATH." >&2
  exit 2
fi
export CHROME_PATH="$CHROME_BIN"

echo "→ Auditing $URL (threshold: $MIN_SCORE, HTTP $HTTP_CODE)"

# Lighthouse refuses to audit a non-2xx page unless told otherwise. The 404
# template is a real template and needs auditing like any other.
EXTRA_FLAGS=()
case "$HTTP_CODE" in
  2*) ;;
  *) EXTRA_FLAGS+=( --ignore-status-code ) ;;
esac

npx --yes "lighthouse@${LH_VERSION}" "$URL" \
  --output=json \
  --output-path="$OUT" \
  --chrome-flags="--headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage" \
  --only-categories="$CATEGORIES" \
  "${EXTRA_FLAGS[@]}" \
  --quiet

node -e '
const min = Number(process.argv[1]);
const r = require("./lighthouse-report.json");
let failed = [];
console.log("--- Scores (" + r.finalUrl + ") ---");
for (const [key, cat] of Object.entries(r.categories)) {
  const score = Math.round(cat.score * 100);
  const ok = score >= min;
  if (!ok) failed.push(key + " " + score);
  console.log((ok ? "  " : "✖ ") + key.padEnd(15) + score);
}
if (failed.length) {
  console.error("\n✖ Below " + min + ": " + failed.join(", "));
  console.error("  Inspect failing audits with:");
  console.error("    node -e \x27const r=require(\"./lighthouse-report.json\");for(const a of Object.values(r.audits))if(a.score!==null&&a.score<0.9)console.log(a.score,a.id,\"-\",a.title)\x27");
  process.exit(1);
}
console.log("\n✔ All categories >= " + min);
' "$MIN_SCORE"
