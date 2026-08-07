---
description: How this theme handles responsiveness — breakpoints actually used per block, fluid typography via theme.json, aspect-ratio/object-fit image containers instead of srcset, and how to verify with Playwright at multiple viewports. Use when building/editing a block or template that needs to work across mobile/tablet/desktop, or when a Playwright/Lighthouse check surfaces a layout problem at a given width.
---

# Responsive design in this theme

There is no rigid global breakpoint scale — each block picks the breakpoint
its own content actually needs, not a shared set of tokens. Match that
pattern rather than inventing a new global system.

## Breakpoints actually in use (grep `@media` across `agentic-blocks/blocks/*/style.css`)

| Range | Used for |
|---|---|
| `max-width: 460–480px` | small-phone tweaks (`collection-list`, `photo-statement`) |
| `max-width: 600–700px` | phone/tablet stack point (`photo-marquee`, `photo-statement`, journal cards) |
| `max-width: 780–900px` | the most common "go to 1/2 columns" breakpoint (`collection-list`, `featured-collection`) — note WordPress's own admin-bar breakpoint is 782px, so this range isn't arbitrary, it lines up with core |
| `max-width: 960–1024px` | tablet-to-desktop column count changes (`photo-statement`, journal) |
| `min-width: 700–1100px` | a few blocks build mobile-first and add columns going up (`testimonials`) instead of max-width going down — check the block's existing CSS direction before adding a new query, don't mix both directions in one block |

When adding a new block, start from whichever of these matches an existing
block with similar content (a card grid → match `collection-list`'s 780px
break; a two-column promo → match `image-with-text`) rather than picking a
new number.

## Typography scales itself — you don't write breakpoints for it

`theme.json`'s `typography.fluid: true` plus each font size's `fluid.min`/
`fluid.max` (see `x-large`/`xx-large` in `theme.json`) means headings resize
continuously with viewport width via `clamp()`. Never add a media query just
to shrink a heading — that's already handled, and a manual override would
fight the token.

## Images: aspect-ratio + object-fit, not srcset

Block images are static files referenced by an `imageUrl` attribute, not
WordPress media-library attachments — so there's no automatic `srcset`
responsive-image generation here (see `image-to-webp`). Instead, every
image container gets a fixed CSS `aspect-ratio` and `object-fit: cover`, so
the same file scales fluidly at any width without WordPress needing to
generate multiple sizes. Reuse these existing ratios rather than picking a
new one per block:

| Ratio | Used by |
|---|---|
| `1 / 1` | product cards (`featured-collection`, WooCommerce), `collection-list` tiles |
| `4 / 3` | `image-with-text`, `latest-posts`, `product-subcategories`, journal cards |
| `4 / 5` | `shop-the-set` bundle hero (portrait) |
| `3 / 4` | `faq-accordion` portrait image |
| `16 / 10` | `cta-cards` |
| `16 / 9` | `video-section` |
| n/a (viewport-height based: `32vh`/`52vh`/`74vh`/`90vh` via `--height-small/medium/large`) | `hero-banner` — full-bleed CSS `background-image` with `background-size:cover`, not an `<img>` |

If you're sourcing/generating an image for one of these blocks, crop it to
the matching ratio *before* saving it (see `image-to-webp`'s `-extent`
example) rather than relying on `object-fit` to hide a mismatched source —
`object-fit:cover` will crop, but starting from the right ratio avoids
losing the subject off-frame.

## Layout width

One shared cap everywhere — `contentSize`/`wideSize`, both `1400px` in
`theme.json` — including WooCommerce cart/checkout/account (which ships its
own hardcoded 1000px cap, overridden back in `assets/css/woocommerce.css`).
Never add a per-block `max-width` override; if a section looks too
wide/narrow, that's a signal to check whether it's using `align="wide"` /
`align="full"` correctly, not to hardcode a width.

## Verifying

Use the `playwright-verify` skill's viewport-resize steps (375×812 / 768×1024
/ 1440×900) whenever a change touches columns, grid counts, or a block's own
`@media` rules. A color/copy/token-only change doesn't need this — reserve it
for actual layout changes.
