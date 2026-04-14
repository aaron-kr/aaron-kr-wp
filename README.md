# aaron-kr-wp

Headless WordPress configuration for [aaron.kr](https://aaron.kr) — the REST API backend serving content to the Next.js frontend.

WordPress lives at `notes.aaron.kr` and is never visited directly by the public. All requests are served by the Next.js frontend at `aaron.kr`; this installation exposes content exclusively via the WP REST API.

---

## Repository contents

```
aaron-kr-headless/              Headless WordPress theme (activate this)
  style.css                     Theme identification header
  index.php                     Redirects visitors → aaron.kr; local dev notice page
  functions.php                 Strips front-end cruft, disables xmlrpc/feeds

mu-plugins/
  aaron-kr-api.php              Must-use plugin (v1.4.0): all REST API configuration
  aaron-kr-migrate-taxonomies.php  One-time Jetpack taxonomy migration (delete after use)

aaron-kr-wp-config-additions.php  Lines to add to wp-config.php (reference only — not executed)
```

---

## What the mu-plugin does

`aaron-kr-api.php` (v1.4.0) is the core of this setup. It loads automatically on every request regardless of active theme or plugin state.

**Jetpack conflict resolution** — disables Jetpack's `custom-content-types` module so it doesn't register `jetpack-portfolio`/`jetpack-testimonial` with conflicting rewrite slugs. Jetpack remains active for stats, CDN, and security.

**Custom post types** registered with full REST API support:

| Post Type | REST Endpoint | Purpose |
|---|---|---|
| `portfolio` | `/wp-json/wp/v2/portfolio` | Design / visual work |
| `testimonial` | `/wp-json/wp/v2/testimonials` | Testimonials |
| `research` | `/wp-json/wp/v2/research` | Academic papers and datasets |
| `talk` | `/wp-json/wp/v2/talks` | Conference talks and presentations |
| `course` | `/wp-json/wp/v2/courses` | Course metadata |

**Custom REST fields** added to all post types (no `_embed` required):

| Field | Notes |
|---|---|
| `reading_time_minutes` | Integer, calculated at 200wpm |
| `excerpt_plain` | HTML-stripped, 160-char excerpt |
| `featured_image_urls` | `{full, large, medium_large, medium, alt}` inline — frontend prefers largest available below `full` |
| `author_card` | `{name, slug, description, url, avatar}` — avatar prefers custom URL from user profile, falls back to Gravatar |
| `category_list` / `tag_list` | Flat name+slug arrays |
| `acf` | All ACF fields (if Advanced Custom Fields is active) |
| `seo` | Yoast title, description, canonical, og_image |
| `research_meta`, `talk_meta`, `testimonial_meta`, `portfolio_meta` | Type-specific fields |
| `naver_blog_url`, `korean_post_url` | Korean cross-post links |

**Admin enhancements:**
- Featured image thumbnail column in all post-type list views
- Reading time column on posts list
- Custom meta boxes for research, talk, testimonial, and portfolio fields
- **Custom Avatar URL** field on every user's Profile page (Users → Profile → "Custom Avatar")
- **Featured Image URL** field on category add/edit screens
- Automatic rewrite rule flush on version change

**CORS** configured for `aaron.kr`, `www.aaron.kr`, `localhost:3000`, `localhost:3001`.

---

## Setting your author avatar

WP → Users → (your account) → scroll to **"Custom Avatar (Headless Frontend)"** → paste an image URL (e.g. from `files.aaron.kr`). Leave blank to fall back to Gravatar.

The URL appears as `author_card.avatar` in the REST response and is used in the post sidebar and post meta byline on the frontend.

---

## Category featured images

WP → Posts → Categories → (edit category) → **"Featured Image URL"**. Paste any image URL. This feeds the "Beyond the Research" section on the homepage and appears as `meta.category_image_url` in the REST categories endpoint.

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
define( 'WP_SITEURL', 'https://notes.aaron.kr' );
define( 'WP_HOME',    'https://notes.aaron.kr' );
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
define( 'WP_HOME',    'http://aaronkr.local' );   // NOT localhost:3000
define( 'DISALLOW_FILE_EDIT',         true  );
define( 'AUTOMATIC_UPDATER_DISABLED', true  );
define( 'WP_MEMORY_LIMIT',            '256M' );
```

5. In Next.js project: set `WP_API_URL=http://aaronkr.local/wp-json/wp/v2` in `.env.local`

---

## Jetpack taxonomy migration

If migrating from Jetpack Portfolio, run the one-time migration script to transfer `jetpack-portfolio-tag` and `jetpack-portfolio-type` terms:

1. Copy `mu-plugins/aaron-kr-migrate-taxonomies.php` to `wp-content/mu-plugins/`
2. In WP Admin: **Tools → Migrate Jetpack Taxonomies**
3. Review the preview table, then click **Run Migration**
4. **Delete the file immediately after** — it's a one-time tool

---

## REST API reference

```bash
# Portfolio items
curl "https://notes.aaron.kr/wp-json/wp/v2/portfolio?per_page=4&_fields=id,title,featured_image_urls,portfolio_meta"

# Blog posts with custom fields
curl "https://notes.aaron.kr/wp-json/wp/v2/posts?per_page=8&_fields=id,title,date,excerpt_plain,reading_time_minutes,featured_image_urls,category_list"

# Research papers
curl "https://notes.aaron.kr/wp-json/wp/v2/research?_fields=id,title,research_meta"

# All categories (includes meta.category_image_url)
curl "https://notes.aaron.kr/wp-json/wp/v2/categories?per_page=100&_fields=id,name,slug,count,meta"
```

---

## Local development workflow

```
Terminal A: LocalWP running at http://aaronkr.local
Terminal B: cd ~/aaron-kr && npm run dev  →  http://localhost:3000
```

Visiting `http://aaronkr.local` shows a local dev notice page. Next.js finds WordPress via `WP_API_URL` in `.env.local`.

**Imported posts not showing?** Go to WP Admin → Posts → select all → Quick Edit → set Status to **Published**. ISR caches the previous (empty) response for up to 1 hour — restart the Next.js dev server to clear the in-memory cache immediately.

---

## Plugin audit (what's needed vs. redundant headless)

**Keep:**
Advanced Custom Fields, Yoast SEO, Redirection, WP Super Cache, Akismet, Site Kit by Google, TablePress, WordPress Importer (until migration done).

**Safe to deactivate:**
All Gutenberg block plugins (CoBlocks, Genesis Blocks, Getwid, Spectra, Ultimate Blocks — front-end rendering only), Font Awesome, Korea SNS, WP External Links, Menu Icon plugins, Admin Color Schemer, Jetpack Boost, Post Type Switcher (after migration done).
