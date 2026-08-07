# agentic-wordpress-boilerplate

A code-first WordPress + WooCommerce boilerplate designed to be built and
maintained by an AI coding agent (Claude Code) the way a Shopify theme is
built with the Shopify CLI: every page, section, and design token is a file
in the repo, not a click in wp-admin.

## The goal

Agencies and freelancers rebuilding the same WooCommerce store scaffolding
for every new client waste time re-solving problems WordPress has already
solved badly by default (page builders, hardcoded PHP templates, settings
scattered across wp-admin screens with no history). This repo is a single
cloneable starting point that:

- gives an AI agent an unambiguous, file-based way to build an ecommerce
  storefront — no browser automation standing in for real development
- ships WooCommerce, SEO, email deliverability, and backups already wired
  up and free, so a clone starts from "working store," not "blank install"
- keeps every design decision (color, type, spacing, corner radius) as a
  small number of tokens in one file, so restyling an entire clone for a
  new brand is a token edit, not a find-and-replace across templates
- separates the reusable boilerplate from any one client's brand material,
  so the boilerplate itself stays generic and publishable

## Layout

```
agentic-wordpress/    the boilerplate itself — theme, blocks, scripts, docs
system-design/        brand-input folder + a worked reference example
```

- **`agentic-wordpress/`** is the actual product: a WordPress block theme
  (`theme/agentic-theme/`), a custom block/section library
  (`agentic-blocks/`), setup and verification scripts, and `CLAUDE.md` — the
  operating rules an AI agent follows when developing inside it. See
  `agentic-wordpress/README.md` for the technical architecture.
- **`system-design/`** holds the brand inputs a store owner or designer
  drops in (logos, fonts, copy, style references) for the agent to translate
  into `theme.json` and block markup, plus a reference design-system
  document generated from the demo store below — useful as a worked example
  of what "done" looks like before styling a real client. See
  `system-design/README.md`.

## What's been built so far

The boilerplate has been developed against a worked example: a beauty/DTC
storefront styled after Shopify's **Sleek** theme (warm blush/terracotta
palette, pill buttons and badges, bold rounded-sans headings, full-bleed
hero carousels), used to prove out the block library and token system end
to end rather than in the abstract. In build order:

1. **Foundation** — WooCommerce, Yoast SEO, WP Mail SMTP, and UpdraftPlus
   wired up via a code-first setup script; global design tokens
   (`theme.json`) and the first phase of the block/section library.
2. **Sleek restyle** — self-hosted variable font (DM Sans), the three-token
   shape scale (pill/card/panel radii), search-drawer and cta-cards blocks,
   hover/interaction polish across the library.
3. **Homepage buildout** — hero carousel, tabbed featured-collection grid,
   photo-statement, collection-list, shop-the-set bundle block,
   photo-marquee, restyled footer/newsletter chrome, payment-badge icons.
4. **Content depth** — product-subcategories block, photo-based
   testimonials, real newsletter form-entry storage, restyled Journal
   (blog) templates, an About Us page.
5. **Polish pass** — site-wide scroll-reveal animation, with the resulting
   LCP/performance regressions fixed so the Lighthouse gate stays green.

Current state: 22 attribute-driven blocks, full template coverage across
every WooCommerce and content page type, and a `system-design/` reference
document describing the demo store's design system in reusable form.

## Getting started

```bash
cd agentic-wordpress
cat README.md   # technical setup + architecture
```
or jump straight to `agentic-wordpress/CLAUDE.md` for the full agent
operating rules.
