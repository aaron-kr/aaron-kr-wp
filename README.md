# aaron-kr-wp

Headless WordPress configuration for [aaron.kr](https://aaron.kr) — the REST API backend serving content to the Next.js frontend.

WordPress lives at `lab.aaron.kr` and is never visited directly by the public. All requests are served by the Next.js frontend at `aaron.kr`; this installation exposes content exclusively via the WP REST API.

---

## Repository contents

```
aaron-kr-headless/              Headless WordPress theme (activate this)
  style.css                     Theme identification header
  index.php                     Redirects visitors → aaron.kr; local dev notice page
  functions.php                 Strips front-end cruft, disables xmlrpc/feeds

mu-plugins/
  aaron-kr-api.php              Must-use plugin: all REST API configuration
  aaron-kr-migrate-taxonomies.php  One-time Jetpack taxonomy migration (delete after use)

aaron-kr-wp-config-additions.php  Lines to add to wp-config.php
```

---

## What the mu-plugin does

`aaron-kr-api.php` is the core of this setup. It loads automatically on every request regardless of active theme or plugin state.

**Jetpack conflict resolution** — disables Jetpack's `custom-content-types` module so it doesn't register `jetpack-portfolio`/`jetpack-testimonial` with conflicting rewrite slugs. Jetpack remains active for stats, CDN, and security.

**Custom post types** registered with full REST API support:

| Post Type | REST Endpoint | Purpose |
|---|---|---|
| `portfolio` | `/wp-json/wp/v2/portfolio` | Design / visual work (replaces Jetpack Portfolio) |
| `testimonial` | `/wp-json/wp/v2/testimonials` | Testimonials (replaces Jetpack Testimonials) |
| `research` | `/wp-json/wp/v2/research` | Academic papers and datasets |
| `talk` | `/wp-json/wp/v2/talks` | Conference talks and presentations |
| `course` | `/wp-json/wp/v2/courses` | Course metadata |

**Custom REST fields** added to all post types (no `_embed` required):
- `reading_time_minutes` — integer, calculated at 200wpm
- `excerpt_plain` — HTML-stripped, 160-char excerpt
- `featured_image_urls` — `{full, large, medium, alt}` inline
- `author_card` — full author info in one field
- `category_list` / `tag_list` — flat name+slug arrays
- `acf` — all ACF fields (if Advanced Custom Fields is active)
- `seo` — Yoast title, description, canonical, og_image
- `research_meta`, `talk_meta`, `testimonial_meta`, `portfolio_meta` — type-specific fields

**Admin enhancements:**
- Featured image thumbnail column in all post-type list views
- Reading time column on posts list
- Custom meta boxes for research, talk, testimonial, and portfolio fields
- Automatic rewrite rule flush on version change

**CORS** configured for `aaron.kr`, `www.aaron.kr`, `localhost:3000`, `localhost:3001`.

---

## Installation

### On Dreamhost VPS (production)

```bash
# SSH into your VPS
ssh user@your-vps.dreamhost.com

# Copy theme
cp -r aaron-kr-headless /path/to/wp-content/themes/

# Copy mu-plugin
cp mu-plugins/aaron-kr-api.php /path/to/wp-content/mu-plugins/
```

Then in WP Admin:
1. **Appearance → Themes** → Activate "Aaron KR Headless"
2. **Settings → Permalinks** → Save Changes (flushes rewrite rules)

Add to `wp-config.php` (before `/* That's all, stop editing! */`):

```php
define( 'WP_SITEURL', 'https://lab.aaron.kr' );
define( 'WP_HOME',    'https://aaron.kr' );      // Next.js frontend
define( 'DISALLOW_FILE_EDIT',         true  );
define( 'AUTOMATIC_UPDATER_DISABLED', true  );
define( 'WP_MEMORY_LIMIT',            '256M' );
```

### LocalWP (local development)

1. Create a new site in LocalWP (PHP 8.1+, Nginx)
2. Copy `aaron-kr-headless/` to `wp-content/themes/` and activate
3. Copy `mu-plugins/aaron-kr-api.php` to `wp-content/mu-plugins/`
4. Add to `wp-config.php`:

```php
define( 'WP_SITEURL', 'http://aaronkr.local' );
define( 'WP_HOME',    'http://aaronkr.local' );  // Same as SITEURL for local dev
define( 'DISALLOW_FILE_EDIT',         true  );
define( 'AUTOMATIC_UPDATER_DISABLED', true  );
define( 'WP_MEMORY_LIMIT',            '256M' );
```

5. In Next.js project: set `WP_API_URL=http://aaronkr.local/wp-json/wp/v2` in `.env.local`

---

## Jetpack taxonomy migration

If migrating from Jetpack Portfolio, run the one-time migration script to transfer `jetpack-portfolio-tag` and `jetpack-portfolio-type` terms to `post_tag` and `portfolio_type`:

1. Copy `mu-plugins/aaron-kr-migrate-taxonomies.php` to `wp-content/mu-plugins/`
2. In WP Admin: **Tools → Migrate Jetpack Taxonomies**
3. Review the preview table, then click **Run Migration**
4. **Delete the file immediately after** — it's a one-time tool

---

## REST API reference

```bash
# All post types available
curl https://lab.aaron.kr/wp-json/wp/v2/types

# Portfolio items (design work)
curl "https://lab.aaron.kr/wp-json/wp/v2/portfolio?per_page=4&_fields=id,title,featured_image_urls,portfolio_meta"

# Blog posts with custom fields
curl "https://lab.aaron.kr/wp-json/wp/v2/posts?per_page=8&_fields=id,title,date,excerpt_plain,reading_time_minutes,featured_image_urls,category_list"

# Research papers
curl "https://lab.aaron.kr/wp-json/wp/v2/research?_fields=id,title,research_meta"
```

---

## Local development workflow

```
Terminal A: LocalWP running at http://aaronkr.local
Terminal B: cd ~/aaron-kr && npm run dev  →  http://localhost:3000
```

Visiting `http://aaronkr.local` in a browser shows a local dev notice page with links to WP Admin and all REST endpoints. It does not redirect to localhost:3000 — that would cause an infinite redirect loop. Next.js finds WordPress via `WP_API_URL` in `.env.local`.

WP draft/preview URLs (`?preview=true`) pass through the theme's `index.php` and work normally for previewing unpublished content.

---

## Plugin audit (what's needed vs. redundant headless)

**Keep:**
Advanced Custom Fields, JWT Authentication for WP-API, Yoast SEO, Redirection, WP Super Cache, Akismet, Sensei LMS (if active), Site Kit by Google, WordPress Importer (until migration done), TablePress.

**Safe to deactivate:**
Aaron's WP Addons (merged into mu-plugin), all Gutenberg block plugins (CoBlocks, Genesis Blocks, Getwid, Spectra, Ultimate Blocks — front-end rendering, irrelevant headless), Font Awesome, Korea SNS, WP External Links, Easy Menu Icons / Menu Icons / Menu Image, Admin Color Schemer / Slate Admin Theme, Jetpack Boost, Post Type Switcher (after migration done).

---

## File structure advice — one repo vs. two

Keep this repo as a single repository. The theme and mu-plugin are tightly coupled — they implement two halves of the same concept (headless WP). Separating them adds overhead with no benefit since neither works without the other.

The Next.js frontend lives in a separate repository (`aaron-kr`) and is deployed independently to Vercel.
