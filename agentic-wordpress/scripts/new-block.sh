#!/usr/bin/env bash
#
# Scaffold a new section block.
#
# Usage:
#   ./scripts/new-block.sh <slug> ["Human Title"] [--with-js]
#
# Examples:
#   ./scripts/new-block.sh logo-list
#   ./scripts/new-block.sh video-hero "Video Hero" --with-js
#
# Creates agentic-blocks/blocks/<slug>/ with block.json, render.php, style.css
# (and index.js when --with-js is passed). The plugin auto-registers anything
# with a block.json, so there is nothing else to wire up.
set -euo pipefail

cd "$(dirname "$0")/.."

SLUG="${1:-}"
if [ -z "$SLUG" ]; then
  echo "Usage: ./scripts/new-block.sh <slug> [\"Human Title\"] [--with-js]" >&2
  exit 1
fi

if ! printf '%s' "$SLUG" | grep -qE '^[a-z][a-z0-9-]*$'; then
  echo "✖ Slug must be lowercase kebab-case (e.g. logo-list), got: $SLUG" >&2
  exit 1
fi

TITLE="${2:-}"
case "${2:-}" in --with-js) TITLE="" ;; esac
if [ -z "$TITLE" ]; then
  # logo-list → Logo List
  TITLE="$(printf '%s' "$SLUG" | tr '-' ' ' | awk '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) substr($i,2)}1')"
fi

WITH_JS=0
for arg in "$@"; do
  [ "$arg" = "--with-js" ] && WITH_JS=1
done

DIR="agentic-blocks/blocks/$SLUG"
if [ -e "$DIR" ]; then
  echo "✖ $DIR already exists." >&2
  exit 1
fi
mkdir -p "$DIR"

EDITOR_SCRIPT=""
if [ "$WITH_JS" = "1" ]; then
  # Must point at the BUILT file — see CLAUDE.md. Pointing at the source
  # silently registers the block with no editor script.
  EDITOR_SCRIPT="
  \"editorScript\": \"file:../../build/$SLUG/index.js\","
fi

cat > "$DIR/block.json" <<EOF
{
  "\$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "agentic/$SLUG",
  "title": "$TITLE",
  "category": "agentic",
  "icon": "layout",
  "description": "TODO: describe what this section does.",
  "attributes": {
    "heading": { "type": "string", "default": "$TITLE" },
    "text": { "type": "string", "default": "" }
  },
  "supports": {
    "align": ["wide", "full"],
    "spacing": { "margin": true, "padding": true }
  },
  "render": "file:./render.php",$EDITOR_SCRIPT
  "style": "file:./style.css"
}
EOF

cat > "$DIR/render.php" <<EOF
<?php
/**
 * $TITLE section.
 *
 * @var array \$attributes
 */
\$heading = \$attributes['heading'] ?? '';
\$text    = \$attributes['text'] ?? '';
?>
<section <?php echo agentic_section_classes( '$SLUG' ); ?>>
	<?php if ( \$heading ) : ?>
		<h2 class="agentic-${SLUG}__heading"><?php echo esc_html( \$heading ); ?></h2>
	<?php endif; ?>

	<?php if ( \$text ) : ?>
		<p class="agentic-${SLUG}__text"><?php echo esc_html( \$text ); ?></p>
	<?php endif; ?>
</section>
EOF

cat > "$DIR/style.css" <<EOF
.agentic-${SLUG}__heading {
	margin: 0 0 var(--wp--preset--spacing--40);
	font-size: var(--wp--preset--font-size--x-large);
}

.agentic-${SLUG}__text {
	margin: 0;
	color: var(--wp--preset--color--muted);
}
EOF

if [ "$WITH_JS" = "1" ]; then
cat > "$DIR/index.js" <<EOF
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const { heading } = attributes;
		const blockProps = useBlockProps( { className: 'agentic-$SLUG' } );

		return (
			<div { ...blockProps }>
				<RichText
					tagName="h2"
					value={ heading }
					onChange={ ( v ) => setAttributes( { heading: v } ) }
					placeholder="Heading…"
				/>
			</div>
		);
	},
	save: () => null, // dynamic block — render.php handles output
} );
EOF
fi

echo "✔ Created $DIR"
echo
echo "Next:"
if [ "$WITH_JS" = "1" ]; then
  echo "  1. cd agentic-blocks && npm run build"
  echo "  2. Verify the editor script registered:"
  echo "     wp-env run cli wp eval 'print_r(WP_Block_Type_Registry::get_instance()->get_registered(\"agentic/$SLUG\")->editor_script_handles);'"
  echo "  3. Add it to a template, then screenshot + ./scripts/lighthouse-check.sh"
else
  echo "  1. Verify it renders:"
  echo "     wp-env run cli wp eval 'echo do_blocks(\"<!-- wp:agentic/$SLUG /-->\");'"
  echo "  2. Add it to a template, then screenshot + ./scripts/lighthouse-check.sh"
fi
