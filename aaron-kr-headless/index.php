<?php
/**
 * Aaron KR Headless Theme — index.php
 *
 * PRODUCTION (lab.aaron.kr):
 *   WP_SITEURL = https://lab.aaron.kr  → REST API, admin, uploads live here
 *   WP_HOME    = https://aaron.kr      → visitors redirected to Next.js
 *   Since HOME ≠ SITEURL: redirect non-system requests to aaron.kr ✓
 *
 * LOCAL DEV (aaronkr.local):
 *   WP_SITEURL = WP_HOME = http://aaronkr.local
 *   Since HOME = SITEURL: show a notice page instead of redirecting ✓
 *   WP draft/preview URLs (?preview=true, ?p=ID) still work in both modes ✓
 */

$site_url = untrailingslashit( get_option( 'siteurl' ) );
$home_url = untrailingslashit( get_option( 'home' ) );
$path     = $_SERVER['REQUEST_URI'] ?? '/';
$query    = $_SERVER['QUERY_STRING'] ?? '';

// ── Never intercept these — let WordPress handle them normally ───────────────
$is_system = (
    strpos( $path,  '/wp-admin'   ) !== false ||
    strpos( $path,  '/wp-login'   ) !== false ||
    strpos( $path,  '/wp-json'    ) !== false ||
    strpos( $path,  '/wp-cron'    ) !== false ||
    strpos( $path,  '/wp-content' ) !== false ||
    strpos( $path,  '/wp-includes') !== false
);

// ── WP draft/preview URLs — pass through so you can preview unpublished posts ─
// These use query params (?preview=true, ?p=123) not permalink slugs,
// so they work regardless of WP_HOME. We let them through in all environments.
$is_preview = (
    strpos( $query, 'preview=true'    ) !== false ||
    strpos( $query, 'preview_id='     ) !== false ||
    strpos( $query, 'preview_nonce='  ) !== false ||
    strpos( $path,  '?p='             ) !== false ||
    // Elementor / Gutenberg full-site preview
    strpos( $query, 'et_fb='          ) !== false ||
    strpos( $query, 'customize_changeset' ) !== false
);

if ( $is_system || $is_preview ) {
    // WordPress handles it — do nothing here
    return;
}

// ── PRODUCTION: redirect to Next.js frontend ─────────────────────────────────
if ( $home_url !== $site_url ) {
    // Preserve the path so /about → aaron.kr/about (for future Next.js routes)
    wp_redirect( $home_url . $path, 301 );
    exit;
}

// ── LOCAL DEV: HOME = SITEURL — show a dashboard notice (no redirect loop) ───
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aaron KR · Headless WordPress</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0c0f0e; color: #e6efec;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 1.5rem;
        }
        .card {
            max-width: 520px; width: 100%;
            background: #101614; border: 1px solid #1b2825;
            border-radius: 12px; padding: 2.5rem;
        }
        .label {
            font-size: .68rem; font-weight: 700; letter-spacing: .18em;
            text-transform: uppercase; color: #2dd4bf;
            display: flex; align-items: center; gap: .5rem; margin-bottom: .9rem;
        }
        .label::before { content: ''; width: 1.2rem; height: 1.5px; background: #2dd4bf; }
        h1 { font-size: 1.25rem; font-weight: 700; color: #e6efec; margin-bottom: .5rem; }
        p  { font-size: .86rem; color: #7ea89e; line-height: 1.75; margin-bottom: 1.2rem; }
        .links { display: flex; flex-direction: column; gap: .4rem; }
        a { color: #2dd4bf; text-decoration: none; font-size: .85rem; font-weight: 600; }
        a:hover { opacity: .75; }
        .divider { height: 1px; background: #1b2825; margin: 1.2rem 0; }
        .api-list { display: flex; flex-direction: column; gap: .3rem; }
        code { font-size: .78rem; color: #3e5852; background: #131a18;
               padding: 2px 7px; border-radius: 4px; }
        .preview-note {
            font-size: .78rem; color: #3e5852; margin-top: 1rem;
            border-top: 1px solid #1b2825; padding-top: 1rem;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="label">Local Development</div>
    <h1>Aaron KR · Headless WordPress</h1>
    <p>This WordPress serves content via the REST API.<br>
       The Next.js frontend is at
       <a href="http://localhost:3000" target="_blank">localhost:3000</a>.</p>

    <div class="links">
        <a href="/wp-admin">→ WordPress Admin</a>
        <a href="/wp-admin/edit.php">→ All Posts</a>
        <a href="/wp-admin/upload.php">→ Media Library</a>
    </div>

    <div class="divider"></div>

    <p style="margin-bottom:.6rem">REST API endpoints:</p>
    <div class="api-list">
        <?php
        $endpoints = [
            'posts'         => 'Blog posts',
            'portfolio'     => 'Portfolio items',
            'research'      => 'Research papers',
            'talks'         => 'Talks',
            'testimonials'  => 'Testimonials',
            'courses'       => 'Courses',
        ];
        foreach ( $endpoints as $slug => $label ) :
            $url = "/wp-json/wp/v2/{$slug}?per_page=3";
        ?>
        <a href="<?php echo esc_attr( $url ); ?>" target="_blank">
            <code>/wp-json/wp/v2/<?php echo esc_html( $slug ); ?></code>
            — <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <p class="preview-note">
        💡 To preview a draft post: open it in WP Admin and click
        <strong>Preview</strong> — preview URLs use <code>?preview=true</code>
        and load correctly on this domain.
    </p>
</div>
</body>
</html>
<?php exit; ?>
