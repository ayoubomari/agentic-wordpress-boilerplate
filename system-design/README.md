# System Design — brand inputs for the agent

This folder is the **input side** of the boilerplate. Drop brand and design
material here, and Claude Code (working inside `agentic-wordpress/`) reads it
as the source of truth when styling a store.

It deliberately sits **outside** the `agentic-wordpress/` repo, because the
repo is meant to be published as a clean, brand-neutral baseline. Your brand
PDFs, licensed fonts, and product copy should not ship inside it.

## What goes where

| Folder | Put this here |
|---|---|
| `identity/` | Brand guideline PDFs, logo files (SVG/PNG), wordmarks, icon sets |
| `fonts/` | Font files (`.woff2` preferred) **plus their licence files** |
| `styles/` | Colour specs, type scales, spacing rules, swatches, reference CSS |
| `copy/` | Real text — taglines, about copy, product descriptions, legal pages |
| `references/` | Screenshots of layouts to match (e.g. Shopify Dawn), inspiration |
| `exports/` | Anything the agent generates from the above (extracted palettes, etc.) |

## How the agent uses it

Before any styling or branding work, the agent should:

1. Check whether this folder exists and list what is in it.
2. Read `identity/` and `styles/` to derive colours, typography, and spacing.
3. Translate those into **code** — `theme/agentic-theme/theme.json` for design
   tokens, `parts/*.html` for layout, `agentic-blocks/blocks/*` for sections.
4. Copy any font files it actually uses into
   `theme/agentic-theme/assets/fonts/` and register them in `theme.json`
   (a WordPress theme can only serve fonts from inside the theme).

It must **never** hand-edit design through wp-admin — see `CLAUDE.md`.

## Fonts — read this before adding any

Only add fonts you have the right to redistribute. If a font is licensed
per-domain or per-seat, keep the file here and note the licence, but do not
commit it into the published boilerplate. Prefer:

- Self-hosted open-source fonts (Google Fonts, Fontshare) — safe to ship
- System font stacks — zero network cost, the current default

## Current status

Empty. Until real brand material is added, the theme uses a neutral,
Dawn-like default: system font stack, near-black on white, single accent
colour. Adding files here is what makes a clone stop looking like the
baseline and start looking like a specific brand.
