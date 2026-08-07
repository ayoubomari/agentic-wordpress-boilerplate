---
description: Turn brand input — a written prompt from the user, a reference screenshot/design they paste in, and/or the ../system-design/ folder's contents — into actual theme.json tokens and block attribute edits. Use before any styling/branding work, and whenever the user describes a look-and-feel in words or shares a reference image rather than exact hex/px values.
---

# Applying brand input to code

This is the concrete "how" behind `CLAUDE.md`'s "Brand inputs" section.
Everything here ends the same way: an edit to `theme.json` and/or a block's
attributes in a template file — never a wp-admin setting, never a one-off
inline style.

## Gather every source before touching code

1. **`../system-design/` folder** (sibling to this repo). Read, in order:
   - `identity/` — logos, wordmarks, brand guideline PDFs. Pull exact brand
     colors and any explicit typography rules from here first; treat it as
     the highest-authority source when it conflicts with anything else.
   - `styles/` — colour specs, type scales, spacing rules, swatches. Direct
     token values usually live here.
   - `references/` — screenshots of layouts to match. Use these for
     *composition/layout* cues (hero style, card shape, badge placement),
     not literal pixel-copying.
   - `copy/` — real text. Use it verbatim in blocks instead of placeholder
     copy once it exists.
   - `exports/` — anything a previous agent session already derived (e.g.
     `system-design/exports/demo-design-system-prompt.md` /
     `agentic-wordpress-sleek-reference.md` in this repo) — read these
     before re-deriving the same tokens from scratch.
   If the folder is empty or missing, say so and keep the current tokens
   rather than inventing a new direction (per `CLAUDE.md`).
2. **A written prompt from the user** — a described mood/vibe ("warm,
   minimal, spa-like", "match Shopify's Sleek theme"). Translate adjectives
   into concrete decisions before writing any code: which of the 12
   `theme.json` color slots does "warm" map to (`accent`? `blush`/`sand`
   panel surfaces?), does "minimal" mean less `blockGap`, fewer simultaneous
   badge colors, a lighter font weight on `heading`?
3. **A reference screenshot the user pastes in.** Read it for the same
   design-system dimensions this boilerplate already tracks — don't
   eyeball a whole new layout system. Specifically extract:
   - **Color** — which of `base/contrast/subtle/muted/border/accent/sale/
     blush/sand/new-badge/selling-fast/sky` roles do the screenshot's colors
     map to. Don't invent a 13th palette slot for a color that's clearly
     playing an existing role (e.g. a second promo-panel color → `sand`).
   - **Shape** — is it sharp corners, small radius, or full pills? Maps
     directly to `--agentic-radius-pill/card/panel` in `theme.json`.
   - **Type** — weight and tracking of headings (compare against the
     current 800-weight/-0.02em DM Sans setup), serif vs. sans.
   - **Density** — generous whitespace vs. tight — maps to which
     `spacing.spacingSizes` step (`Tight`…`Hero`) a section should use.

## Writing the change

- Token-level differences (color, font, radius, spacing) → edit
  `theme.json` **once**, globally. Never override a color/radius/font
  per-block or per-template — if you find yourself writing a literal hex or
  `border-radius: 14px` inside a block's `style.css` to match the brand,
  that's a sign the token itself needs to change instead.
- Fonts you actually adopt must be copied into
  `theme/agentic-theme/assets/fonts/` and declared via `fontFace` entries,
  same pattern as the existing DM Sans setup — WordPress can only serve
  fonts from inside the theme.
- Layout/composition differences (a reference screenshot's hero style, tab
  layout, card arrangement) → block **attributes** in the relevant template
  file (`front-page.html`, etc.), using the block's existing
  attribute-driven options first (see each block's entry in `CLAUDE.md`'s
  "Section library") before reaching for a new attribute or a new block.
- Real photography/imagery the brand supplies → run it through the
  `image-to-webp` skill before saving it into `assets/images/`.

## Verify

Once tokens/attributes are updated, offer the `playwright-verify` skill (a
screenshot of the affected page/template) rather than assuming the change
looks right from the diff alone — color and shape changes in particular are
easy to get subtly wrong (e.g. an accent color that reads differently at
badge-size vs. panel-size).
