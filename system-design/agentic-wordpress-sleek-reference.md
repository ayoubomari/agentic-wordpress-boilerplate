# Reference Design System: Agentic WordPress Demo (Sleek-style Beauty/DTC Storefront)

> Saved as a reference example for `agentic-wordpress-boilerplate`. Shows what a
> complete, token-driven design system document looks like for a brand built on
> the boilerplate — a WordPress + WooCommerce beauty/DTC storefront in the style
> of Shopify's "Sleek" theme.

This document describes the visual language of one live storefront: warm
blush/terracotta palette, fully-rounded pill buttons, bold rounded-sans
headings, full-bleed hero carousels. Every value below is drawn directly from
the site's token set and component inventory — nothing here is invented. A
designer or developer who has never seen the site should be able to rebuild
its visual language from this document alone.

---

## 1. Foundations

### Color palette

No hardcoded colors are used anywhere in the codebase — every surface, text
color, border, and badge consumes one of these twelve tokens.

| Token | Hex | Swatch | Role |
|---|---|---|---|
| `base` | `#ffffff` | white | Page background |
| `contrast` | `#1a1a1a` | near-black | Primary text, button background |
| `subtle` | `#f8f1ee` | pale warm pink-grey | Soft section background |
| `muted` | `#6b6b6b` | mid grey | Secondary/caption text |
| `border` | `#ece0da` | pale warm taupe | Hairline borders, dividers |
| `accent` | `#c96f52` | terracotta | Terracotta accent |
| `sale` | `#c23b3a` | brick red | Sale badge/price |
| `blush` | `#f0d7d0` | dusty pink | Warm promo-panel surface |
| `sand` | `#ecd9c3` | warm beige | Warm promo-panel surface (alt) |
| `new-badge` | `#3f7d4e` | forest green | "New" product badge |
| `selling-fast` | `#6d5bd0` | violet | "Selling fast" product badge |
| `sky` | `#81aecf` | dusty blue | Accent surface |

**Palette structure**: `base`/`contrast` are the neutral extremes (page and
text/button-fill). `subtle`/`border` are quiet warm neutrals for section
backgrounds and dividers. `accent` is the single brand color used for links,
icons, and highlight moments. `sale` is reserved for price-drop communication
only. `blush`/`sand` are interchangeable warm surfaces for promo panels —
pick whichever contrasts better with the panel's photography. `new-badge`,
`selling-fast`, and `sky` are narrow-purpose status/accent colors, not general
decoration.

### Typography

One family carries the entire site — **DM Sans** (self-hosted variable font,
weights 100–1000, upright + italic). There is no separate display or serif
face.

- **Headings**: DM Sans, weight **800**, letter-spacing **-0.02em**,
  line-height **1.15** — this combination is what gives headings their
  bold-rounded-sans display character, without switching typefaces.
- **Body**: DM Sans, weight **400**, line-height **1.6**.

**Type specimen**

| Token | Base size | Fluid range | Weight | Typical use |
|---|---|---|---|---|
| Small | 0.875rem (14px) | fixed | 400 (500 in buttons/badges) | Captions, nav, buttons |
| Medium | 1rem (16px) | fixed | 400 | Body copy |
| Large | 1.25rem (20px) | fixed | 800 | H3 |
| X-Large | 1.75rem (28px) | 1.5rem–2.25rem (24px–36px) | 800 | H2 |
| XX-Large (Display) | 2.75rem (44px) | 2rem–3.75rem (32px–60px) | 800 | H1, hero headings |

The two largest steps are fluid (viewport-interpolated between the stated
min and max); the three smaller steps are fixed. Buttons and nav links use
the Small step at weight 500 with 0.02em letter-spacing — slightly heavier
and more open than body text, so short labels read clearly at small sizes.

### Spacing scale

Seven steps, each named by the role it plays rather than just its size —
components reference the role name, so "the gap between two homepage
sections" always resolves to the same value even if the number is retuned
later.

```
1 Tight    ▏0.5rem  (8px)   — inside compact clusters (badge to icon)
2 Small    ▎1rem    (16px)  — internal component padding
3 Base     ▍1.5rem  (24px)  — default gap between related elements
4 Medium   ▊2.5rem  (40px)  — gap between a block's sub-groups
5 Large    █4rem    (64px)  — gap between distinct components on a page
6 Section  ██5.5rem (88px)  — standard gap between homepage sections
7 Hero     ██7rem   (112px) — hero-scale breathing room
```

### Shape scale

Exactly three radii. Every component uses one of them — never a one-off
value.

- **Pill** — `999px` — buttons, badges, pill nav
- **Card** — `10px` — product cards, thumbnails
- **Panel** — `20px` — hero/promo full-bleed panels

### Shadows

Two elevation levels:

- `card` — `0 1px 2px rgba(0,0,0,0.06)` — resting elevation
- `lifted` — `0 6px 24px rgba(0,0,0,0.10)` — hover/elevated states

### Layout

A single content cap is used everywhere, including cart, checkout, and
account pages: **1400px** max-width, centered.

---

## 2. Components

- **Button** — contrast-colored pill, 1px contrast border, white text, Small
  type, weight 500, 0.02em letter-spacing. Hover inverts to white background
  / contrast text / contrast border.
- **Product badges** — up to two shown at once: a status badge (priority
  order sold-out > sale > selling-fast) plus an independent "New" badge, so a
  product can show both "New" and a status badge simultaneously. The sale
  badge always shows a computed `-X%` rather than the word "Sale."
- **Cards** — 10px (Card) radius, `card` shadow at rest.
- **Promo panels** — 20px (Panel) radius, full-bleed or inset image;
  background is always `blush` or `sand`, never any other token.
- **Nav** — Small type, no underline on links.
- **Forms** — newsletter signup: an email field plus a pill submit button.

---

## 3. Patterns

Patterns are attribute-driven homepage/landing sections — configured through
block attributes, never by nesting or hand-editing content blocks. Each one
below is a distinct block type.

- **Hero banner** — full-bleed peek carousel. Two or more slides trigger
  carousel chrome (arrows, dots, drag); a single slide renders static with no
  JS at all. Each slide independently sets: optional eyebrow (Small text
  above the heading), heading, optional subheading, a background-color
  token, a CTA, and either a photo or a generated abstract shape
  illustration when no image is supplied.
- **Featured collection** — a product grid, either with tabs (each tab
  defined by label + category + on-sale flag + max-price filter) or as a
  single plain price-filtered row.
- **Photo statement** — inline text with small circular/rounded product
  photos woven directly into a heading-sized sentence.
- **Collection list** — a grid of category tiles (image + title + link).
- **Shop the set** — a bundle hero: one lifestyle photo with numbered
  hotspots, each linking to an individual product in the set.
- **Photo marquee** — a continuously scrolling strip of image + short-label
  pairs, used for brand-statement moments (e.g. "Cruelty-Free," "Bestseller
  Alert").
- **Image with text** — a colored promo panel (image left or right) with
  eyebrow + heading + body + CTA. Two placed side by side form a "dual promo
  split."
- **Testimonials** — grid or carousel. Items are either plain quotes or
  large photo-testimonial cards that include a linked product chip
  (photo/name/price/link) and platform attribution (Google/Trustpilot).
- **FAQ accordion** — either a plain centered list, or, when an
  eyebrow/intro-heading/text/image/CTA set is provided, a 2-column layout
  with a portrait image on one side and the accordion on the other, first
  item open by default.
- **Multicolumn** — plain columns, or a "pills" variant: a compact row of
  checkmark trust badges.
- **Rich text** — freeform text block whose CTA link can be styled as either
  a button or an underlined link.
- **Logo list** — row of partner/press logos.
- **Video section** — supporting video block.
- **Countdown banner** — timed promotional banner.
- **CTA cards** — promotional card grid.
- **Product subcategories** — subcategory tile row.
- **Announcement bar** — a single static message, or, with 2+ messages, a
  continuously scrolling marquee.
- **Search drawer** — header slide-out panel containing a real search form,
  curated "popular searches" links, and real popular products (never
  invented/mocked analytics).
- **Payment badges** — footer row of accepted card-network icons.
- **Motion** — scroll-reveal animation is applied site-wide as sections enter
  the viewport. Carousels (hero and testimonials) loop seamlessly via
  pre-corrected clone slides, so there is no first-paint layout shift.

All of the above reuse only the tokens and components from Sections 1–2 —
none introduces a new color, radius, or type style.

---

## 4. Page templates

Every template shares the same header and footer template parts. There is
no WooCommerce PHP fallback template anywhere in the site.

**Homepage** — composed in this exact order:

1. Announcement bar
2. Header
3. Hero carousel
4. Featured collection (tabbed)
5. Photo statement
6. Collection list
7. Shop the set
8. Photo marquee
9. Image-with-text promo
10. Testimonials
11. Footer

**Other templates** (structure not itemized in the source material — see
note below): Journal (blog list) and single post template, generic page
template, full-width page variant, product archive/category/tag, product
detail, cart, checkout, my-account, order confirmation, search, 404, About
page.

> **Ambiguity flag**: the source material names these templates but does not
> specify their internal section order or component composition the way it
> does for the homepage. Don't guess a layout for them — pull the actual
> block composition from the boilerplate before documenting it here.

---

## How to extend this

- **New color** → add it as a palette token in Section 1, never as a literal
  hex in a component or pattern. If it's a badge color, name it for its
  status (e.g. `low-stock`), not its hue.
- **New spacing value** → only if no existing step fits; name it by role
  (what it separates), not just size, and slot it into the 7-step scale
  rather than adding an ad-hoc value beside it.
- **New radius** → don't. The three-radius rule (Pill/Card/Panel) is
  deliberately closed; a new component should map to one of the three, not
  add a fourth.
- **New pattern** → define its full attribute set up front (what's
  configurable vs. fixed) before building it, the way each pattern above
  lists its exact knobs. Reuse existing components inside it rather than
  styling new markup from scratch.
- **New page template** → reuse the shared header/footer parts; never add a
  PHP fallback template outside the pattern system.
