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

1. **Foundation** — WooCommerce, The SEO Framework, WP Mail SMTP, and
   UpdraftPlus wired up via a code-first setup script; global design tokens
   (`theme.json`) and the first phase of the block/section library. (Yoast
   SEO shipped first and was swapped for The SEO Framework in a later phase
   — see `agentic-wordpress/ROADMAP.md` for why.)
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
6. **Fork-readiness** — swapped Yoast SEO for The SEO Framework (see above);
   added AI-crawler `robots.txt` rules, a dynamic `/llms.txt`, and a Google
   Merchant Center `/product-feed.xml` for AI shopping surfaces; an opt-in
   read-only WooCommerce MCP server; a Contact page with a real
   contact-form block; and an MIT license.
7. **Deploy & sync tooling** — `scripts/deploy.sh` (code -> VPS) and
   `scripts/sync-products.sh` (product catalog pull/push over SSH, matched
   by SKU), plus the GitHub Actions workflows that wrap them. See
   "Deploying and syncing to a VPS" below.

Current state: 23 attribute-driven blocks, full template coverage across
every WooCommerce and content page type, and a `system-design/` reference
document describing the demo store's design system in reusable form.

## Getting started

```bash
cd agentic-wordpress
cat README.md   # technical setup + architecture
```
or jump straight to `agentic-wordpress/CLAUDE.md` for the full agent
operating rules.

## Deploying and syncing to a VPS

`wp-env` (the environment above) is dev-only. A running clone on a VPS uses
`agentic-wordpress/docker-compose.yml` instead — official WordPress/MySQL
Docker images, not wp-env's dev-flavored setup. Getting code and data
between your local wp-env and that VPS is three separate problems with
different rules; see "Deployment & environment sync" in `CLAUDE.md` for the
full policy. In short:

| | What moves | How | Status |
|---|---|---|---|
| **Code** (theme, blocks, scripts) | local → VPS, one-way | `git push` + `./scripts/deploy.sh` (git pull + rebuild JS bundles on the VPS) | Script done. CI wiring written but parked — see below. |
| **Product catalog** (products, categories, images) | VPS → local, routine | `./scripts/sync-products.sh pull` | Done, tested locally (see `scripts/lib/import-products.php`'s header for why it doesn't use WooCommerce's own CSV importer). Meant to be run on your own machine, not CI. |
| **Product catalog**, initial migration only | local → VPS, one-time, pre-launch | `./scripts/sync-products.sh push --force` | Script done. CI wiring written but parked — see below. |
| Orders, customers, live wp-admin edits | — | — | **No sync exists or should exist.** Once a store is live, its own database is the source of truth; UpdraftPlus (already configured) covers disaster-recovery backup of this, which is a different concern. |

### One-time setup
```bash
cd agentic-wordpress
cp .env.example .env   # fill in VPS_HOST, VPS_REMOTE_PATH, VPS_SSH_KEY
```
`.env` is gitignored — it's for running these scripts from your own
machine.

### CI (GitHub Actions) — written, deliberately not live yet
`.github/workflows-disabled/deploy.yml` and `sync-products.yml` wrap the
two scripts above for CI. They live at **this repo's root** (`.github/`
must sit next to this README, not inside `agentic-wordpress/`, since
that's the only place GitHub Actions looks for workflows at all) but sit
in `workflows-disabled/`, not `workflows/`, so they're inert until moved —
their `run:` steps already `cd` into `agentic-wordpress/` internally
(via each job's `working-directory` default) to reach `scripts/deploy.sh`
etc., since the actual product is one level below this file:
```bash
mkdir -p .github/workflows
mv .github/workflows-disabled/*.yml .github/workflows/
git add .github/workflows .github/workflows-disabled
git commit -m "Enable deploy/sync CI"
```
Do that once there's an actual VPS/domain to deploy to and these
**repository secrets** are set (Settings → Secrets and variables →
Actions) — until then, moving them in early would just produce a failing
run (or a noisy one, for `deploy.yml`'s push-to-`main` trigger) on every
commit:

- `VPS_SSH_KEY` — the private key's full content (not a path), for a user
  on the VPS with `git pull` + `docker` access.
- `VPS_HOST` — e.g. `deploy@store.example.com`.
- `VPS_REMOTE_PATH` — optional, defaults to `~/agentic-wordpress`.

Once enabled, both workflows still fail fast with an explicit
"secrets are not configured yet" message rather than doing nothing
silently if either secret above is missing.
