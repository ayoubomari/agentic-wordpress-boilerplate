---
description: Visually verify a page/template/block renders as intended using the Playwright MCP tools against the local wp-env site (http://localhost:8888) — screenshots only, never used to build or edit content. Use after finishing a block/template change, but only once the user has confirmed they want this check run (see "Ask first" below); also use when the user explicitly says "screenshot this", "show me how it looks", or "verify visually".
---

# Playwright visual verification

This is the boilerplate's only sanctioned use of browser automation — read
`CLAUDE.md`'s CORE RULE first: Playwright/wp-admin are for **looking**, never
for building. If you find yourself about to click, type, or drag inside the
block editor to construct something, stop — write the template/block file
instead.

## Ask first

Do not run this automatically after every block/template edit. Once the code
change is done, ask the user whether they want a visual check before running
it — e.g. "Built the X block. Want me to screenshot it and/or run a
Lighthouse check before we move on?" Proceed without asking only when the
user's own request already implied it ("build X and show me a screenshot").

## Preconditions

- `wp-env start` must already be running (site at `http://localhost:8888`).
- If blocks were just added/edited: `wp-env run cli wp plugin activate
  agentic-blocks` (only if not already active — activating an already-active
  plugin is a harmless no-op, but skip it if you know it's active).

## Steps

1. `mcp__playwright__browser_navigate` to the exact page the change affects —
   e.g. `http://localhost:8888/` for a `front-page.html` edit,
   `http://localhost:8888/shop/` for `archive-product.html`, a specific
   `/product-slug/` for `single-product.html`.
2. `mcp__playwright__browser_take_screenshot` (or `browser_snapshot` for a
   structural/accessibility read instead of pixels — cheaper when you just
   need to confirm text/links are present, not layout).
3. Compare what's rendered against the attribute JSON you actually wrote —
   heading text, image, CTA, badge, background color token. A screenshot that
   merely shows "not a PHP error page" is not a pass; check the specific
   thing you changed is actually there and looks right.
4. For a responsive check (see the `responsive-design` skill for background),
   `mcp__playwright__browser_resize` through a small set of viewports and
   re-screenshot at each:
   - `375x812` — mobile
   - `768x1024` — tablet
   - `1440x900` — desktop
   Only do this when the change touches layout/spacing/columns, not for a
   pure copy or color-token edit.

## After a failed check

Fix by editing the source file (template/block/`theme.json`) and re-run —
never by adjusting anything through wp-admin or the block editor UI, even
temporarily "just to see."
