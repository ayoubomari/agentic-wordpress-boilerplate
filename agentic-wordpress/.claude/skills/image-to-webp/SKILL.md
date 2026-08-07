---
description: Convert sourced/generated PNG or JPG images to WebP before adding them to the theme, at the right dimensions for the block that will use them. Use whenever a new image is about to be saved into theme/agentic-theme/assets/images/ or agentic-blocks assets — e.g. after downloading a stock photo, exporting from a design tool, or generating one with an AI image tool.
---

# Image → WebP conversion

Every image asset already in this theme is `.webp` — there are zero
`.png`/`.jpg` files in `theme/agentic-theme/assets/`
(`find . -iname '*.png' -o -iname '*.jpg'` returns nothing). Keep it that
way: a non-webp file landing in the repo is very likely what a failing
Lighthouse `modern-image-formats` audit is pointing at (see
`lighthouse-optimize`).

## Why this matters here specifically

- No plugin does this conversion for you — `agentic-blocks` render.php files
  read `imageUrl` attributes that point straight at static files under
  `assets/images/`, not WordPress media-library attachments (which is also
  why they carry no `srcset` — see `responsive-design`). Whatever file you
  save there ships to the browser byte-for-byte.
- `wp-env`'s `.htaccess` cache block (`setup-site.sh`) gives every image a
  1-year cache lifetime — a bloated source file stays slow for a year of
  visitors, not just first paint.

## Converting

ImageMagick's `convert` is available in this environment; use it unless
`cwebp` (from `libwebp`, usually sharper output at the same file size) is
installed:

```bash
# Preferred if available — check first: `which cwebp`
cwebp -q 82 input.png -o output.webp

# Fallback, always available here
convert input.png -strip -quality 82 output.webp
```

- `-quality 82` (or `-q 82`) is a reasonable default — visually
  lossless for product/lifestyle photography, meaningfully smaller than
  90+. Go to 90 only for a hero image where banding would be visible;
  drop to ~70 for small decorative/marquee images.
- `-strip` removes EXIF/color-profile metadata — pure byte savings, no
  visible effect on a sRGB web image.
- Never hand-write width/height in the filename or path; keep the same
  base name as elsewhere in the folder (`assets/images/<section>/<slug>.webp`,
  matching the pattern already used by `hero/`, `collections/`, `statement/`,
  `testimonials/`, `journal/`, `marquee/`, `bundle/`, `promo/`).

## Sizing before you convert

Resize to the dimensions the target block actually needs — don't ship a
4000px source image behind CSS that displays it at 400px. Check the block's
`style.css` for its `aspect-ratio` (see `responsive-design`'s table) and
size the source to roughly 2x the largest rendered box (for HiDPI screens),
not larger:

```bash
convert input.png -strip -resize 1600x1600^ -gravity center -extent 1600x1600 -quality 82 output.webp   # 1:1 square, e.g. product/collection tiles
```

Adjust the `-resize`/`-extent` values to the block's actual aspect ratio
(`^` + `-gravity center -extent` crops to fill rather than letterboxing —
matches the `object-fit:cover` behavior the CSS already applies, so what you
see in the cropped source is what renders).

## After converting

Delete the original `.png`/`.jpg` — don't leave both in the repo (dead
weight, and an easy way for a future edit to accidentally reference the
uncompressed one). Then reference the new `.webp` path from the block's
`imageUrl` attribute in the template file, same as every existing section.
