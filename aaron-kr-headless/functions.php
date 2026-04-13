<?php
/**
 * Aaron KR Headless — functions.php
 *
 * This file is intentionally thin. All REST API configuration,
 * post type registration, and custom fields live in the must-use
 * plugin (mu-plugins/aaron-kr-api.php) so they load regardless
 * of which theme is active.
 *
 * Only theme-specific hooks that genuinely belong to the theme go here.
 */

defined( 'ABSPATH' ) || exit;

// ── Remove all front-end cruft (nobody visits this WP URL) ──────────────────
add_action( 'init', function () {
    // No emoji scripts
    remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles',     'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles',  'print_emoji_styles' );

    // No RSD / Windows Live Writer links in <head>
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );

    // No generator tag (security hygiene)
    remove_action( 'wp_head', 'wp_generator' );

    // No shortlink
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
} );

// ── Strip oEmbed discovery links (nobody embedding from lab.aaron.kr) ────────
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );

// ── No feeds on the headless domain ─────────────────────────────────────────
add_action( 'do_feed',      'aaron_kr_no_feed', 1 );
add_action( 'do_feed_rdf',  'aaron_kr_no_feed', 1 );
add_action( 'do_feed_rss',  'aaron_kr_no_feed', 1 );
add_action( 'do_feed_rss2', 'aaron_kr_no_feed', 1 );
add_action( 'do_feed_atom', 'aaron_kr_no_feed', 1 );

function aaron_kr_no_feed() {
    wp_redirect( 'https://aaron.kr', 301 );
    exit;
}

// ── Disable xmlrpc (security, not needed for REST-only setup) ────────────────
add_filter( 'xmlrpc_enabled', '__return_false' );
