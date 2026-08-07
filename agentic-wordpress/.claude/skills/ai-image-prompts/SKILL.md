---
description: Write AI image-generation prompts that match this theme's design language and the exact aspect ratio the target block needs, for the user to paste into their own image tool (Midjourney, DALL·E, Ideogram, Firefly, etc.) — or, if they supply their own API key, generate the image directly without the key ever touching git history or chat output. Use whenever a block needs an image and none exists yet (product photography, hero imagery, lifestyle shots, promo panels).
---

# AI image prompts for this theme

Default output of this skill is **text** — a prompt the user pastes into
whatever image tool they already use or have credits on. This boilerplate
doesn't hardcode a specific image-gen vendor, same reasoning as why it
doesn't hardcode an analytics or ESP vendor (see `CLAUDE.md`'s "Stack"
section) — the user's tool choice is theirs, not baked into the repo.

## Before writing the prompt

1. Confirm which block the image is for, and pull its aspect ratio from the
   `responsive-design` skill's table (`1/1` product tiles, `4/3`
   image-with-text/latest-posts, `4/5` shop-the-set, `3/4` faq-accordion,
   `16/10` cta-cards, `16/9` video-section). State the ratio explicitly in
   the prompt (most tools accept `--ar 4:5` / `--aspect 4:5` style flags —
   check the user's tool's syntax if unsure rather than guessing one).
2. Read the current `theme.json` palette and `styles.elements` — don't
   assume this is still the blush/terracotta Sleek demo; a clone may have
   already rebranded it (see `apply-brand-input`). Pull actual hex values
   and describe them by *appearance* in the prompt (image models respond to
   "warm terracotta and dusty pink tones" far better than a hex code).
3. Check whether the block even needs a **photo** at all.
   `hero-banner` specifically has a built-in fallback — when a slide has no
   `imageUrl`, `agentic_hero_banner_illustration()` in `agentic-blocks.php`
   renders a brand-neutral abstract shape illustration using the current
   palette tokens automatically, no image required. Point this out to the
   user as the zero-effort option before generating anything for a hero
   slide specifically.

## Prompt structure

Write one dense paragraph, not a bullet list (most image models weight
prose more reliably than fragments), covering in this order:

1. **Subject** — exactly what's in frame, matching the block's actual
   content (a named product category, a lifestyle moment, a portrait crop
   for `faq-accordion`'s 3:4 slot).
2. **Style** — photography vs. illustration, and which: for a beauty/DTC
   storefront in this theme's default direction, that's "clean studio
   product photography, soft diffused lighting, minimal styling" — adjust
   to whatever `apply-brand-input` established for a rebranded clone.
3. **Palette** — the 2–3 dominant colors pulled from `theme.json`, described
   by appearance, plus background tone (matches `blush`/`sand`/`subtle` for
   promo-panel shots, near-white/neutral for product-on-white shots meant to
   sit on `base`).
4. **Composition/crop** — explicit aspect ratio and any headroom needed
   (e.g. `image-with-text` often needs negative space on one side for the
   text column to sit beside it — say which side).
5. **Negative constraints** — no visible text/logos/watermarks baked into
   the image (badges, prices, and headings are rendered by the block itself
   on top of the image — see `product-badge` — so text *in* the photo
   just conflicts with it), no other brands' products in frame.

## Example (for `image-with-text`, 4:3, current Sleek demo palette)

> Clean studio product photography of a glass dropper serum bottle catching
> soft window light, warm terracotta and dusty blush tones in the
> background, minimal styling with a single stone pedestal prop, negative
> space on the left third of the frame for a text overlay, 4:3 aspect ratio,
> no text or logos in the image, no other visible brands.

## If the user has their own API key and wants a direct call

Only do this if they explicitly offer a key — never ask them to paste a raw
secret into chat as the default path.

- **Never put the key in a git-tracked file or in a chat message you
  produce.** If they paste it inline, use it for the one call and don't
  echo it back or write it into any file you create.
- Preferred channel: they set it as a shell environment variable
  (`export MYTOOL_API_KEY=...`, via `! export ...` in-session or their own
  shell profile) and you reference `$MYTOOL_API_KEY` by name in the `curl`/
  script command — never print the resolved value (avoid `set -x`, `curl -v`
  with auth headers visible, or `echo $MYTOOL_API_KEY`).
- If they'd rather it persist on disk between sessions: create a
  **gitignored** local file (e.g. `.env.local` at the repo root) holding
  `MYTOOL_API_KEY=...`, and check first that a pattern covering it is
  actually in `.gitignore` — add one (`.env.local` or `.env*.local`) before
  writing the file if it's missing, and confirm with the user before adding
  a secret to disk at all. This is the same "content or credential the
  agent doesn't own" boundary as the `wp-config.php` constants pattern for
  `AGENTIC_GA4_ID`/`AGENTIC_META_PIXEL_ID` in `CLAUDE.md` — the value is the
  owner's, never invented, never committed.
- Generated images still go through the `image-to-webp` skill before they're
  saved into `theme/agentic-theme/assets/images/`.
