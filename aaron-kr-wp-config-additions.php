<?php
/**
 * aaron-kr-wp-config-additions.php
 *
 * Paste these into your wp-config.php BEFORE the line:
 *   /* That's all, stop editing! Happy publishing. */
 *
 * KEY RULE: Never call add_filter() / add_action() / any WP function here.
 * wp-config.php loads before WordPress core — those functions don't exist yet.
 * Hooks belong in functions.php or the mu-plugin.
 */

// ════════════════════════════════════════════════════════════════
// LOCAL DEV  — paste this block into your LocalWP wp-config.php
// ════════════════════════════════════════════════════════════════
//
// Keep WP_HOME = WP_SITEURL (your local WP URL).
// Do NOT set WP_HOME to http://localhost:3000.
//
// WHY: Next.js reads the REST API via WP_API_URL in .env.local.
// It doesn't care what WP_HOME is. But WP admin "View Post" links
// use WP_HOME — if that's localhost:3000, they send you to a
// Next.js route that doesn't exist yet → 404.
//
define( 'WP_SITEURL', 'http://aaronkr.local' );
define( 'WP_HOME',    'http://aaronkr.local' );

// ════════════════════════════════════════════════════════════════
// PRODUCTION — Dreamhost VPS at lab.aaron.kr
// ════════════════════════════════════════════════════════════════
//
// define( 'WP_SITEURL', 'https://lab.aaron.kr' );
// define( 'WP_HOME',    'https://aaron.kr' );

// ════════════════════════════════════════════════════════════════
// SHARED (both environments)
// ════════════════════════════════════════════════════════════════

define( 'DISALLOW_FILE_EDIT',        true  );
define( 'AUTOMATIC_UPDATER_DISABLED', true  );
define( 'WP_MEMORY_LIMIT',           '256M' );

// add_filter( 'show_admin_bar', '__return_false' ); ← in mu-plugin, not here
