# CLAUDE.md — aaron-kr-wp

This file gives Claude context about this repository. Read it before making any changes.

## What this repo is

Headless WordPress backend for aaron.kr. WordPress runs at `lab.aaron.kr` and serves content exclusively via the REST API. No public visitors ever see a WordPress-rendered page. The Next.js frontend (`aaron-kr` repo, deployed to Vercel) fetches content here and renders everything.

## Critical architecture rules

**Never add front-end rendering logic** to the theme. `index.php` does one job: redirect production visitors to aaron.kr, show a local dev notice when `WP_HOME === WP_SITEURL`. If you add PHP template files (single.php, archive.php, etc.) they will never be used.

**Never call WP functions in wp-config.php.** WP core isn't loaded yet when wp-config.php runs. `add_filter()`, `add_action()`, `get_option()` — all undefined at that point. Hooks belong in the mu-plugin.

**The mu-plugin must disable Jetpack's custom-content-types module at the top of the file**, before any other code. This is not optional — Jetpack (a regular plugin) loads *after* the mu-plugin and would overwrite our rewrite rules with its own `jetpack-portfolio` slug, causing 404s on all portfolio posts. The two filters at the top of `aaron-kr-api.php` are load-order critical.

**`WP_HOME` must NOT be set to `localhost:3000` in local dev.** Set both `WP_SITEURL` and `WP_HOME` to `http://aaronkr.local`. If `WP_HOME` points to the Next.js dev server, every WP admin "View Post" link goes to a Next.js route that doesn't exist yet → 404. Next.js finds WordPress via `WP_API_URL` in its own `.env.local`, not via `WP_HOME`.

## File map

```
aaron-kr-headless/style.css        Theme header — identifies theme to WP, no CSS
aaron-kr-headless/index.php        Visitor redirect / local dev notice (see above)
aaron-kr-headless/functions.php    Strips front-end noise; disables xmlrpc, feeds
mu-plugins/aaron-kr-api.php        EVERYTHING else: CORS, CPTs, REST fields, admin UI
aaron-kr-wp-config-additions.php   Reference snippet — not executed, paste into wp-config.php
```

## Custom post types and their REST slugs

```
portfolio    → /wp-json/wp/v2/portfolio      (taxonomy: portfolio_type)
testimonial  → /wp-json/wp/v2/testimonials
research     → /wp-json/wp/v2/research       (taxonomy: research_area)
talk         → /wp-json/wp/v2/talks
course       → /wp-json/wp/v2/courses
```

Standard `post` and `page` are also extended with all custom fields.

## Custom REST fields (on all post types)

| Field | Type | Notes |
|---|---|---|
| `reading_time_minutes` | integer | 200wpm calculation |
| `excerpt_plain` | string | HTML-stripped, 160 char max |
| `featured_image_urls` | object | `{full, large, medium, alt}` — no `_embed` needed |
| `author_card` | object | `{name, slug, description, url, avatar}` |
| `category_list` | array | `[{id, name, slug}]` |
| `tag_list` | array | `[{id, name, slug}]` |
| `acf` | object | All ACF fields (if ACF plugin active) |
| `seo` | object | `{title, description, canonical, no_index, og_image}` from Yoast |
| `research_meta` | object | venue, year, doi, pdf_url, award, coauthors |
| `talk_meta` | object | event, event_date, location, slides_url, video_url, language |
| `testimonial_meta` | object | person_name, person_title, person_org, rating, language, context |
| `portfolio_meta` | object | client, year, tools, project_url |

## How rewrite flush works

The mu-plugin stores `aaron_kr_version` in the options table. When the version string changes, it calls `flush_rewrite_rules(true)` once and updates the option. This means: after uploading a new version of the mu-plugin, the rules flush automatically on the next page load. Manual Settings → Permalinks save is only needed if something goes wrong.

## CORS allowed origins

`https://aaron.kr`, `https://www.aaron.kr`, `http://localhost:3000`, `http://localhost:3001`

To add a staging domain: add it to the `AARON_KR_ALLOWED_ORIGINS` constant near the top of the mu-plugin.

## Meta fields storage

Post-type-specific meta is stored as plain post meta (`wp_postmeta`). Key names:
- Research: `research_venue`, `research_year`, `research_doi`, `research_pdf_url`, `research_award`, `research_coauthors`
- Talk: `talk_event`, `talk_event_date`, `talk_location`, `talk_slides_url`, `talk_video_url`, `talk_language`
- Testimonial: `testimonial_name`, `testimonial_title`, `testimonial_org`, `testimonial_rating`, `testimonial_language`, `testimonial_context`
- Portfolio: `portfolio_client`, `portfolio_year`, `portfolio_tools`, `portfolio_project_url`

ACF can also manage these fields if field groups are configured — the `acf` REST field will expose them automatically.

## Adding a new post type

1. Add a `register_post_type()` call inside `aaron_kr_register_post_types()`
2. Add it to the `$all` array at the top of `aaron_kr_register_rest_fields()`
3. Add a `register_rest_field()` call for any type-specific meta
4. Add a meta box function and register it in the `add_meta_boxes` action
5. Update the version string in the auto-flush check to trigger a rewrite rule regeneration
6. Add the new slug to the `$_thumb_types` array for the featured image column

## What NOT to do

- Don't add rendering templates — this is headless
- Don't call WP functions in wp-config.php
- Don't set WP_HOME to localhost:3000
- Don't move the Jetpack conflict filters out of position 1 in the file
- Don't use `flush_rewrite_rules()` on every request — it's expensive; use the version-stamp pattern
- Don't commit wp-config.php or any file with database credentials
