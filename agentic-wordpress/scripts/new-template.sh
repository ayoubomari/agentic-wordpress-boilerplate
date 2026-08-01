#!/usr/bin/env bash
#
# Scaffold a new block template, pre-wired with the header and footer parts.
#
# Usage:
#   ./scripts/new-template.sh <template-name>
#
# Examples:
#   ./scripts/new-template.sh page-lookbook     # a specific page slug
#   ./scripts/new-template.sh taxonomy-brand    # a taxonomy archive
#
# Naming follows the WordPress template hierarchy, so the file name decides
# when WordPress uses it. Common names:
#   front-page, home, index, single, page, archive, search, 404
#   single-product, archive-product, taxonomy-product_cat
#   page-<slug>  (e.g. page-cart, page-checkout)
set -euo pipefail

cd "$(dirname "$0")/.."

NAME="${1:-}"
if [ -z "$NAME" ]; then
  echo "Usage: ./scripts/new-template.sh <template-name>" >&2
  exit 1
fi

if ! printf '%s' "$NAME" | grep -qE '^[a-z0-9][a-z0-9_-]*$'; then
  echo "✖ Template name must be lowercase (letters, digits, - and _), got: $NAME" >&2
  exit 1
fi

FILE="theme/agentic-theme/templates/$NAME.html"
if [ -e "$FILE" ]; then
  echo "✖ $FILE already exists." >&2
  exit 1
fi

cat > "$FILE" <<'EOF'
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

  <!-- TODO: replace with the sections this page needs.
       Available custom sections (see agentic-blocks/blocks/):
         wp:agentic/hero-banner
         wp:agentic/featured-collection
         wp:agentic/image-with-text
         wp:agentic/multicolumn
         wp:agentic/testimonials
         wp:agentic/faq-accordion
         wp:agentic/newsletter-signup
  -->

  <!-- wp:post-title {"level":1} /-->
  <!-- wp:post-content {"layout":{"type":"constrained"}} /-->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
EOF

echo "✔ Created $FILE"
echo
echo "Next:"
echo "  1. Edit the file — every template must keep the header and footer parts."
echo "  2. Confirm WordPress resolves it to the theme:"
echo "     wp-env run cli wp eval '\$t=get_block_template(\"agentic-theme//$NAME\"); echo \$t ? \$t->source : \"MISSING\";'"
echo "  3. Load the page, screenshot it, then ./scripts/lighthouse-check.sh <path>"
