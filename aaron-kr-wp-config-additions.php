<?php
/**
 * aaron-kr-wp-config-additions.php
 *
 * These lines go into your EXISTING wp-config.php on lab.aaron.kr
 * (and on your local WP install).
 *
 * Add them BEFORE the line that says:
 *   /* That's all, stop editing! Happy publishing. */
 */

// ── Headless site URL ────────────────────────────────────────────────────────
// WP_SITEURL  = where WordPress is installed (admin, REST API, uploads)
// WP_HOME     = the "public" URL — set to the Next.js frontend
//
// This means:
//   - wp-admin lives at:        https://lab.aaron.kr/wp-admin
//   - REST API lives at:        https://lab.aaron.kr/wp-json/wp/v2/...
//   - Uploads live at:          https://lab.aaron.kr/wp-content/uploads/...
//   - Front-end (Next.js) at:   https://aaron.kr
//
// For LOCAL development, override these in your local wp-config.php:
//   define( 'WP_SITEURL', 'http://localhost:8080' );
//   define( 'WP_HOME',    'http://localhost:8080' );

define( 'WP_SITEURL', 'https://lab.aaron.kr' );
define( 'WP_HOME',    'https://aaron.kr' );      // ← Next.js frontend URL

// ── Disable the WordPress theme/plugin editor in admin (security) ────────────
define( 'DISALLOW_FILE_EDIT', true );

// ── Disable automatic plugin/theme updates (you control deploys) ─────────────
define( 'AUTOMATIC_UPDATER_DISABLED', true );

// ── Set a generous memory limit for REST API responses ───────────────────────
define( 'WP_MEMORY_LIMIT', '256M' );

// ── REST API namespace shortcut (optional, for quick cURL testing) ────────────
// curl https://lab.aaron.kr/wp-json/wp/v2/posts?per_page=3&_fields=id,title,slug

// ── Disable the "admin bar" on the frontend (nobody visits the WP frontend) ──
add_filter( 'show_admin_bar', '__return_false' );
