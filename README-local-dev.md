# Local Development Setup
## aaron.kr headless stack — Next.js ↔ WordPress

---

## The two pieces you run locally

```
┌─────────────────────────────────────────────────┐
│  Terminal A                Terminal B            │
│                                                  │
│  Local WordPress           Next.js dev server    │
│  http://localhost:10008    http://localhost:3000  │
│  (via LocalWP)             (npm run dev)         │
│                                                  │
│  REST API:                 Reads from:           │
│  localhost:10008/wp-json   WP_API_URL in .env    │
└─────────────────────────────────────────────────┘
```

Your links in the Next.js app all point to **external sites** (aaron.kr,
pailab.io, courses.aaron.kr) — those still go to the live internet. Only
the WP REST API calls go to your local WordPress. So local dev works fine
without touching DNS.

---

## Step 1 — Install LocalWP

**LocalWP** (by Flywheel) is the easiest local WordPress environment.
Free, runs on Mac/Windows/Linux, no Docker required.

1. Download: https://localwp.com/
2. Install and open it
3. Click **+ Create a new site**
4. Site name: `aaron-kr-lab`
5. Choose **Custom** environment if asked, pick:
   - PHP: 8.1 or 8.2
   - Web server: Nginx (matches Dreamhost VPS)
   - MySQL: 8.0
6. Set admin username/password (you'll need these)
7. Click **Add Site**

LocalWP gives you:
- WP admin at: `http://aaron-kr-lab.local/wp-admin`
- A **Live Link** button for temporary public URL (optional)

---

## Step 2 — Install the theme and plugin

In LocalWP, open your site's file manager (right-click → Open Site Shell,
or navigate to the path LocalWP shows you):

```
~/Local Sites/aaron-kr-lab/app/public/
```

Copy the files from this repo:

```bash
# From this repo root:

# 1. Copy the headless theme
cp -r aaron-kr-headless \
  ~/Local\ Sites/aaron-kr-lab/app/public/wp-content/themes/

# 2. Copy the must-use plugin
cp mu-plugins/aaron-kr-api.php \
  ~/Local\ Sites/aaron-kr-lab/app/public/wp-content/mu-plugins/
```

Then in WP Admin (http://aaron-kr-lab.local/wp-admin):
- **Appearance → Themes** → Activate "Aaron KR Headless"
- The mu-plugin is already active (no activation needed)

---

## Step 3 — Configure wp-config.php

Open:
```
~/Local Sites/aaron-kr-lab/app/public/wp-config.php
```

Add the contents of `aaron-kr-wp-config-additions.php` — BUT change
the URLs for local use:

```php
// LOCAL overrides (use these instead of the production values):
define( 'WP_SITEURL', 'http://aaron-kr-lab.local' );
define( 'WP_HOME',    'http://aaron-kr-lab.local' );  // or keep as aaron.kr — doesn't matter locally
```

---

## Step 4 — Install Jetpack (or just the modules you need)

For local dev you don't need the full Jetpack. The mu-plugin handles
Portfolio and Testimonial REST exposure, so you just need Jetpack active:

1. WP Admin → Plugins → Add New → search "Jetpack"
2. Install & Activate
3. Jetpack → Settings → Writing → enable **Portfolio** and **Testimonials**

**Alternative** (if you don't want Jetpack locally): the mu-plugin's
`aaron_kr_jetpack_rest()` function only runs if Jetpack's post types
exist. If Jetpack isn't installed, it silently does nothing — no errors.

---

## Step 5 — Point Next.js at local WordPress

Edit your Next.js `.env.local`:

```bash
# Local development
WP_API_URL=http://aaron-kr-lab.local/wp-json/wp/v2

# Production (comment out when developing locally)
# WP_API_URL=https://lab.aaron.kr/wp-json/wp/v2
```

CORS is pre-configured in the mu-plugin to allow `http://localhost:3000`.

---

## Step 6 — Run both servers

```bash
# Terminal A — already running via LocalWP UI

# Terminal B
cd ~/aaron-kr          # your Next.js project
npm run dev
# → http://localhost:3000
```

Open `http://localhost:3000` — your Next.js app now fetches real WP
content from local WordPress.

---

## Step 7 — Migrate content from live WP

To get your existing posts/content into the local WP:

### Option A — WP Export/Import (easiest)
1. On **live** aaron.kr: WP Admin → Tools → Export → All Content → Download
2. On **local** LocalWP: WP Admin → Tools → Import → WordPress → Upload the .xml
3. Check "Download and import file attachments" (gets your media too)

### Option B — Database dump (faster for large sites)
```bash
# On Dreamhost VPS (SSH in):
mysqldump -u DB_USER -p DB_NAME > aaron-kr-backup.sql

# Copy to local machine:
scp user@your-vps.dreamhost.com:~/aaron-kr-backup.sql .

# Import into LocalWP's MySQL (LocalWP provides a DB GUI — click "Database"):
# Or via CLI with LocalWP's shell:
mysql -u root -p aaron_kr_lab < aaron-kr-backup.sql
```

---

## Verify everything works

```bash
# Test the REST API directly:
curl "http://aaron-kr-lab.local/wp-json/wp/v2/posts?per_page=3&_fields=id,title,reading_time_minutes,excerpt_plain" | python3 -m json.tool

# Should return posts with your custom fields:
# {
#   "id": 123,
#   "title": { "rendered": "Your Post Title" },
#   "reading_time_minutes": 4,
#   "excerpt_plain": "Clean plain text excerpt..."
# }

# Test Jetpack Portfolio:
curl "http://aaron-kr-lab.local/wp-json/wp/v2/jetpack-portfolio?per_page=4" | python3 -m json.tool

# Test your custom Research post type:
curl "http://aaron-kr-lab.local/wp-json/wp/v2/research?per_page=5" | python3 -m json.tool
```

---

## Deploying to lab.aaron.kr

When ready to go live:

1. **SSH into your Dreamhost VPS** and copy the theme + mu-plugin:
   ```bash
   # Theme:
   scp -r aaron-kr-headless user@vps:/path/to/wp-content/themes/

   # MU-Plugin:
   scp mu-plugins/aaron-kr-api.php user@vps:/path/to/wp-content/mu-plugins/
   ```

2. **In WP Admin** on the live server:
   - Activate the theme
   - (mu-plugin is auto-active)

3. **Add the wp-config.php additions** (with production URLs):
   ```php
   define( 'WP_SITEURL', 'https://lab.aaron.kr' );
   define( 'WP_HOME',    'https://aaron.kr' );
   ```

4. **Update Vercel env vars**:
   - In Vercel dashboard → Project → Settings → Environment Variables
   - `WP_API_URL` = `https://lab.aaron.kr/wp-json/wp/v2`

5. **Trigger a Vercel redeploy** (or wait for ISR to refresh in 1 hour)

---

## Useful WP Admin URLs (once live)

| Purpose | URL |
|---|---|
| WP Admin | https://lab.aaron.kr/wp-admin |
| REST API root | https://lab.aaron.kr/wp-json |
| All posts | https://lab.aaron.kr/wp-json/wp/v2/posts |
| Research papers | https://lab.aaron.kr/wp-json/wp/v2/research |
| Talks | https://lab.aaron.kr/wp-json/wp/v2/talks |
| Portfolio | https://lab.aaron.kr/wp-json/wp/v2/jetpack-portfolio |
| Available types | https://lab.aaron.kr/wp-json/wp/v2/types |

---

## What links point where in local dev

| Link type | Where it goes | OK in dev? |
|---|---|---|
| WP REST API calls | `localhost:10008` | ✅ via .env.local |
| Nav links (Research, Teaching, etc.) | `#anchor` scroll | ✅ works locally |
| External links (LinkedIn, GitHub, etc.) | Live internet | ✅ always live |
| "View all courses →" | `courses.aaron.kr` | ✅ goes to live site |
| "PAI Lab →" | `pailab.io` | ✅ goes to live site |
| Media/uploads | `lab.aaron.kr/wp-content/uploads/` | ⚠️ goes to live WP |

The last point means **featured images** in local dev will load from the
live server — which is fine. If you want truly offline dev, export the
media library and import it into LocalWP.
