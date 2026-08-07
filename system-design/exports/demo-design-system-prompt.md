# Prompt: generate a design system doc from the Agentic WordPress demo

> Paste everything below into Claude (or another design-writing tool). It is
> self-contained — no repo access needed. Output is meant to be saved back
> into `system-design/` as a **reference example**, so future clones of
> `agentic-wordpress-boilerplate` show what "done" looks like before anyone
> writes brand-specific code.

---

## Prompt

You are a design systems writer. Below is the complete token set and
component inventory of a live ecommerce storefront (WordPress + WooCommerce,
built as a beauty/DTC brand in the style of Shopify's "Sleek" theme — warm
blush/terracotta palette, fully-rounded pill buttons, bold rounded-sans
headings, full-bleed hero carousels). Turn it into a clear, well-organized
**design system document** with four sections: Foundations, Components,
Patterns, and Page Templates. Use swatches (described in words/hex, since you
can't render images), a type specimen table, and a spacing scale diagram
described in prose or ASCII. Write it so a designer or developer who has
never seen the site could rebuild its visual language from your document
alone. Do not invent anything not listed below — if something is ambiguous,
say so rather than guessing.

### 1. Foundations

**Color palette** (every token, no hardcoded colors used anywhere in the
codebase — everything consumes these):

| Token | Hex | Role |
|---|---|---|
| base | #ffffff | Page background |
| contrast | #1a1a1a | Primary text, button background |
| subtle | #f8f1ee | Soft section background |
| muted | #6b6b6b | Secondary/caption text |
| border | #ece0da | Hairline borders, dividers |
| accent | #c96f52 | Terracotta accent |
| sale | #c23b3a | Sale badge/price |
| blush | #f0d7d0 | Warm promo-panel surface |
| sand | #ecd9c3 | Warm promo-panel surface (alt) |
| new-badge | #3f7d4e | "New" product badge |
| selling-fast | #6d5bd0 | "Selling fast" product badge |
| sky | #81aecf | Accent surface |

**Typography**: one family used for both body and headings — **DM Sans**
(self-hosted variable font, weights 100–1000, upright + italic). Headings
are the same family set to weight 800 with -0.02em letter-spacing and 1.15
line-height (bold-rounded-sans display look). Body copy: weight 400, 1.6
line-height.

Fluid type scale:
| Token | Base size | Fluid range | Use |
|---|---|---|---|
| small | 0.875rem | — | Captions, nav, buttons |
| medium | 1rem | — | Body |
| large | 1.25rem | — | H3 |
| x-large | 1.75rem | 1.5rem–2.25rem | H2 |
| xx-large (Display) | 2.75rem | 2rem–3.75rem | H1 / hero headings |

**Spacing scale** (7 steps, all named by role not just size):
1 Tight (0.5rem) · 2 Small (1rem) · 3 Base (1.5rem) · 4 Medium (2.5rem) ·
5 Large (4rem) · 6 Section (5.5rem, the standard gap between homepage
sections) · 7 Hero (7rem).

**Shape scale** — exactly three radii, every component uses one of them,
never a one-off value:
- Pill (999px) — buttons, badges, pill nav
- Card (10px) — product cards, thumbnails
- Panel (20px) — hero/promo full-bleed panels

**Shadows**: `card` (0 1px 2px, 6% black) for resting elevation, `lifted`
(0 6px 24px, 10% black) for hover/elevated states.

**Layout**: single content cap used everywhere including cart/checkout/
account — 1400px.

### 2. Components

- **Button**: contrast-colored pill, 1px contrast border, white text,
  small-size type, 500 weight, 0.02em letter-spacing, inverts to
  white-background/contrast-text/contrast-border on hover.
- **Product badges**: up to two shown at once — a status badge (priority:
  sold-out > sale > selling-fast) plus an independent "New" badge. Sale
  shows a computed `-X%` rather than the word "Sale."
- **Cards**: 10px radius, `card` shadow at rest.
- **Promo panels**: 20px radius, full-bleed or inset image, background is
  always `blush` or `sand`.
- **Nav**: small-size type, no underline on links.
- **Forms**: newsletter signup — email field + pill submit button.

### 3. Patterns (attribute-driven sections — configured via block attributes,
   never by nesting/editing content blocks by hand)

- **Hero banner**: full-bleed peek carousel, 2+ slides required for
  carousel chrome (single slide = static, no JS). Each slide: optional
  eyebrow (small text above heading), heading, optional subheading,
  background-color token, CTA, image or a generated abstract shape
  illustration when no image is given.
- **Featured collection**: product grid with optional tabs (label +
  category + on-sale + max-price filter) or a plain price-filtered row.
- **Photo statement**: inline text + small circular/rounded product photos
  woven into a heading-sized sentence.
- **Collection list**: category tile grid with image + title + link.
- **Shop the set**: bundle hero — lifestyle photo with numbered hotspots
  linking to individual products in the set.
- **Photo marquee**: continuously scrolling strip of image + short label
  pairs (brand statement moments — "Cruelty-Free," "Bestseller Alert").
- **Image with text**: colored promo panel, image left/right, eyebrow +
  heading + body + CTA; two side by side make a "dual promo split."
- **Testimonials**: grid or carousel layout; items can be plain quotes or
  large photo-testimonial cards with a linked product chip
  (photo/name/price/image) and platform attribution (Google/Trustpilot).
- **FAQ accordion**: either a plain centered list, or (with eyebrow/intro
  heading/text/image/CTA set) a 2-column layout — portrait image one side,
  accordion the other, first item open.
- **Multicolumn**: columns or "pills" variant (compact checkmark trust-
  badge row).
- **Rich text**: CTA link styled as button or underline.
- **Logo list, video section, countdown banner, cta-cards,
  product-subcategories**: supporting/promo sections, same token language.
- **Announcement bar**: single static message, or 2+ messages as a
  continuously scrolling marquee.
- **Search drawer**: header slide-out panel — real search form, curated
  popular-search links, real popular products (not invented analytics).
- **Payment badges**: footer row of accepted card-network icons.
- **Motion**: scroll-reveal animation applied site-wide on section entry;
  carousels (hero + testimonials) loop seamlessly via pre-corrected clone
  slides so there's no first-paint layout shift.

### 4. Page templates (all share header + footer template parts, never a
   WooCommerce PHP fallback)

Homepage (composed in this exact order): announcement bar → header → hero
carousel → featured collection (tabbed) → photo statement → collection
list → shop-the-set → photo marquee → image-with-text promo → testimonials
→ footer.

Also covered: Journal (blog list) and post template, generic page + a
full-width page variant, product archive/category/tag, product detail,
cart, checkout, my-account, order confirmation, search, 404, About page.

### Deliverable

Produce the finished design-system document now, formatted in Markdown with
clear headers, tables for tokens, and a short "how to extend this" closing
note (e.g. "add a new badge color as a palette token, never a literal hex").
