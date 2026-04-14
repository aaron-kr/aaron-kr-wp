<?php
/**
 * Plugin Name:  Aaron KR — Headless API Layer
 * Description:  REST API configuration, custom post types, CORS, admin enhancements.
 * Version:      1.2.0
 * Author:       Aaron Snowberger
 */

defined( 'ABSPATH' ) || exit;

// ════════════════════════════════════════════════════════════════════════════
// 1. JETPACK — disable ONLY the custom post type module
//    Keeps Jetpack active for stats, CDN, security, etc.
//    Must run before Jetpack registers its modules.
//    This is why it's first in the file.
// ════════════════════════════════════════════════════════════════════════════

// Remove Jetpack's 'custom-content-types' module from the active modules list.
// This stops Jetpack registering jetpack-portfolio and jetpack-testimonial,
// which would overwrite our rewrite rules with the same /portfolio/ slug.
add_filter( 'jetpack_get_available_modules', function ( $modules ) {
    unset( $modules['custom-content-types'] );
    return $modules;
} );

// Belt-and-suspenders: also filter the stored active modules option
add_filter( 'option_jetpack_active_modules', function ( $modules ) {
    return array_values( array_diff(
        (array) $modules,
        [ 'custom-content-types' ]
    ) );
} );

// ════════════════════════════════════════════════════════════════════════════
// 2. ADMIN BAR (must live here, not in wp-config.php)
// ════════════════════════════════════════════════════════════════════════════

add_filter( 'show_admin_bar', '__return_false' );

// ════════════════════════════════════════════════════════════════════════════
// 2b. HEADLESS PREVIEW URLS
//     In local dev (WP_HOME = WP_SITEURL), rewrite "View Post" and preview
//     links to point at the Next.js dev server (localhost:3000) instead of
//     the WP domain. This lets you click View Post from WP admin and see the
//     Next.js-rendered version. 404s until that route exists in Next.js.
//
//     In production, WP_HOME is already aaron.kr so links go there naturally.
// ════════════════════════════════════════════════════════════════════════════

add_filter( 'preview_post_link', 'aaron_kr_headless_preview_link', 10, 2 );
add_filter( 'post_link',         'aaron_kr_headless_post_link', 10, 2 );
add_filter( 'post_type_link',    'aaron_kr_headless_post_link', 10, 2 );
add_filter( 'page_link',         'aaron_kr_headless_post_link', 10, 2 );

function aaron_kr_frontend_url(): string {
    // In production WP_HOME differs from WP_SITEURL — use WP_HOME.
    // In local dev they are the same — use localhost:3000.
    $home = untrailingslashit( get_option( 'home' ) );
    $site = untrailingslashit( get_option( 'siteurl' ) );
    return ( $home !== $site ) ? $home : 'http://localhost:3000';
}

function aaron_kr_headless_post_link( string $url, $post ): string {
    $wp_base = untrailingslashit( get_option( 'siteurl' ) );
    $fe_base = aaron_kr_frontend_url();
    return str_replace( $wp_base, $fe_base, $url );
}

function aaron_kr_headless_preview_link( string $url, $post ): string {
    // Preview URL: replace domain but keep query params (?preview=true etc.)
    // so WP still handles the preview render via index.php passthrough
    $wp_base = untrailingslashit( get_option( 'siteurl' ) );
    $fe_base = aaron_kr_frontend_url();
    return str_replace( $wp_base, $fe_base, $url );
}

// ════════════════════════════════════════════════════════════════════════════
// 3. CORS
// ════════════════════════════════════════════════════════════════════════════

define( 'AARON_KR_ALLOWED_ORIGINS', [
    'https://aaron.kr',
    'https://www.aaron.kr',
    'http://localhost:3000',
    'http://localhost:3001',
] );

add_action( 'rest_api_init', function () {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
    add_filter( 'rest_pre_serve_request', 'aaron_kr_cors_headers' );
}, 15 );
add_action( 'send_headers', 'aaron_kr_cors_headers' );

function aaron_kr_cors_headers( $value = null ) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ( in_array( $origin, AARON_KR_ALLOWED_ORIGINS, true ) ) {
        header( 'Access-Control-Allow-Origin: '    . esc_url_raw( $origin ) );
        header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
        header( 'Access-Control-Allow-Credentials: true' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
        header( 'Vary: Origin' );
    }
    if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        status_header( 204 ); exit;
    }
    return $value;
}

// ════════════════════════════════════════════════════════════════════════════
// 4. POST TYPES
// ════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'aaron_kr_register_post_types' );

function aaron_kr_register_post_types() {

    register_post_type( 'portfolio', [
        'labels'       => [
            'name'          => 'Portfolio',
            'singular_name' => 'Portfolio Item',
            'add_new_item'  => 'Add Portfolio Item',
            'edit_item'     => 'Edit Portfolio Item',
            'menu_name'     => 'Portfolio',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'rest_base'    => 'portfolio',
        'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
        'has_archive'  => false,
        'rewrite'      => [ 'slug' => 'portfolio', 'with_front' => false ],
        'menu_icon'    => 'dashicons-art',
        'menu_position'=> 5,
        'taxonomies'   => [ 'portfolio_type', 'post_tag' ],
    ] );

    register_taxonomy( 'portfolio_type', 'portfolio', [
        'label'        => 'Portfolio Types',
        'hierarchical' => true,
        'show_in_rest' => true,
        'rest_base'    => 'portfolio-types',
        'rewrite'      => [ 'slug' => 'portfolio-type', 'with_front' => false ],
    ] );

    register_post_type( 'testimonial', [
        'labels'       => [
            'name'          => 'Testimonials',
            'singular_name' => 'Testimonial',
            'add_new_item'  => 'Add Testimonial',
            'menu_name'     => 'Testimonials',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'rest_base'    => 'testimonials',
        'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ],
        'has_archive'  => false,
        'rewrite'      => [ 'slug' => 'testimonials', 'with_front' => false ],
        'menu_icon'    => 'dashicons-format-quote',
        'menu_position'=> 6,
    ] );

    register_post_type( 'research', [
        'labels'       => [
            'name'          => 'Research Papers',
            'singular_name' => 'Research Paper',
            'add_new_item'  => 'Add Research Paper',
            'menu_name'     => 'Research',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'rest_base'    => 'research',
        'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions', 'author' ],
        'has_archive'  => false,
        'rewrite'      => [ 'slug' => 'research', 'with_front' => false ],
        'menu_icon'    => 'dashicons-welcome-learn-more',
        'menu_position'=> 7,
        'taxonomies'   => [ 'research_area', 'post_tag' ],
    ] );

    register_taxonomy( 'research_area', 'research', [
        'label'        => 'Research Areas',
        'hierarchical' => true,
        'show_in_rest' => true,
        'rest_base'    => 'research-areas',
        'rewrite'      => [ 'slug' => 'research-area', 'with_front' => false ],
    ] );

    register_post_type( 'talk', [
        'labels'       => [
            'name'          => 'Talks',
            'singular_name' => 'Talk',
            'add_new_item'  => 'Add Talk',
            'menu_name'     => 'Talks',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'rest_base'    => 'talks',
        'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
        'has_archive'  => false,
        'rewrite'      => [ 'slug' => 'talks', 'with_front' => false ],
        'menu_icon'    => 'dashicons-megaphone',
        'menu_position'=> 8,
        'taxonomies'   => [ 'post_tag' ],
    ] );

    register_post_type( 'course', [
        'labels'       => [
            'name'          => 'Courses',
            'singular_name' => 'Course',
            'add_new_item'  => 'Add Course',
            'menu_name'     => 'Courses',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'rest_base'    => 'courses',
        'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ],
        'has_archive'  => false,
        'rewrite'      => [ 'slug' => 'courses', 'with_front' => false ],
        'menu_icon'    => 'dashicons-book-alt',
        'menu_position'=> 9,
    ] );
}

// ════════════════════════════════════════════════════════════════════════════
// 5. REWRITE RULE FLUSH
//    Runs once when version changes, and on theme switch.
// ════════════════════════════════════════════════════════════════════════════

add_action( 'init', function () {
    $ver = '1.3.0';
    if ( get_option( 'aaron_kr_version' ) !== $ver ) {
        update_option( 'aaron_kr_version', $ver );
        flush_rewrite_rules( true ); // true = hard flush, rebuilds .htaccess
    }
}, 99 );

add_action( 'switch_theme', fn() => flush_rewrite_rules( true ) );

// ════════════════════════════════════════════════════════════════════════════
// 6. REST API — QUERY ENHANCEMENTS
//    Merged from Aaron's WP Addons plugin
// ════════════════════════════════════════════════════════════════════════════

// Allow ?orderby=rand
foreach ( [ 'post', 'portfolio', 'testimonial', 'research', 'talk', 'course' ] as $_type ) {
    add_filter( "rest_{$_type}_collection_params", function ( $params ) {
        $params['orderby']['enum'][] = 'rand';
        return $params;
    } );
}
unset( $_type );

// Remove password-protected posts from REST
add_filter( 'rest_post_query', fn( $args ) => array_merge( $args, [ 'has_password' => false ] ) );

// Cache-Control headers
add_filter( 'rest_post_dispatch', function ( $response ) {
    if ( ! is_wp_error( $response ) ) {
        $response->header( 'Cache-Control', 'public, max-age=3600, stale-while-revalidate=7200' );
    }
    return $response;
} );

// Block unauthenticated writes
add_filter( 'rest_authentication_errors', function ( $result ) {
    if ( true === $result || is_wp_error( $result ) ) return $result;
    if ( ! is_user_logged_in() ) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ( ! in_array( $method, [ 'GET', 'OPTIONS', 'HEAD' ], true ) ) {
            return new WP_Error( 'rest_not_logged_in', 'Write access requires authentication.', [ 'status' => 401 ] );
        }
    }
    return $result;
} );

// ════════════════════════════════════════════════════════════════════════════
// 7. CUSTOM REST FIELDS
// ════════════════════════════════════════════════════════════════════════════

add_action( 'rest_api_init', 'aaron_kr_register_rest_fields' );

function aaron_kr_register_rest_fields() {
    $all = [ 'post', 'page', 'portfolio', 'testimonial', 'research', 'talk', 'course' ];

    // Reading time
    register_rest_field( $all, 'reading_time_minutes', [
        'get_callback' => fn( $p ) => (int) max( 1, ceil(
            str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $p['id'] ) ) ) / 200
        ) ),
        'schema' => [ 'type' => 'integer', 'context' => [ 'view' ] ],
    ] );

    // Plain excerpt
    register_rest_field( $all, 'excerpt_plain', [
        'get_callback' => function ( $p ) {
            $raw = get_post_field( 'post_excerpt', $p['id'] )
                ?: get_post_field( 'post_content', $p['id'] );
            return mb_strimwidth( wp_strip_all_tags( $raw ), 0, 160, '…' );
        },
        'schema' => [ 'type' => 'string', 'context' => [ 'view' ] ],
    ] );

    // Featured image (all sizes inline — no _embed needed)
    register_rest_field( $all, 'featured_image_urls', [
        'get_callback' => function ( $p ) {
            $id = get_post_thumbnail_id( $p['id'] );
            if ( ! $id ) return null;
            $get = fn( $size ) => ( $src = wp_get_attachment_image_src( $id, $size ) ) ? $src[0] : null;
            return [
                'full'   => $get( 'full' ),
                'large'  => $get( 'large' ),
                'medium' => $get( 'medium' ),
                'alt'    => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: '',
            ];
        },
        'schema' => [ 'type' => 'object', 'context' => [ 'view' ] ],
    ] );

    // Author card
    register_rest_field( $all, 'author_card', [
        'get_callback' => function ( $p ) {
            $uid = $p['author'] ?? get_post_field( 'post_author', $p['id'] );
            if ( ! $uid ) return null;
            return [
                'name'        => get_the_author_meta( 'display_name', $uid ),
                'slug'        => get_the_author_meta( 'user_nicename', $uid ),
                'description' => get_the_author_meta( 'description', $uid ),
                'url'         => get_the_author_meta( 'url', $uid ),
                'avatar'      => get_avatar_url( $uid, [ 'size' => 96 ] ),
            ];
        },
        'schema' => [ 'type' => 'object', 'context' => [ 'view' ] ],
    ] );

    // Category list
    register_rest_field( [ 'post', 'research', 'talk' ], 'category_list', [
        'get_callback' => function ( $p ) {
            $terms = get_the_terms( $p['id'], 'category' );
            if ( ! $terms || is_wp_error( $terms ) ) return [];
            return array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $terms );
        },
        'schema' => [ 'type' => 'array', 'context' => [ 'view' ] ],
    ] );

    // Tag list
    register_rest_field( $all, 'tag_list', [
        'get_callback' => function ( $p ) {
            $terms = get_the_terms( $p['id'], 'post_tag' );
            if ( ! $terms || is_wp_error( $terms ) ) return [];
            return array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $terms );
        },
        'schema' => [ 'type' => 'array', 'context' => [ 'view' ] ],
    ] );

    // ACF fields (from Aaron's WP Addons, merged here)
    foreach ( $all as $type ) {
        add_filter( "rest_prepare_{$type}", function ( $response, $post ) {
            if ( function_exists( 'get_fields' ) && $fields = get_fields( $post->ID ) ) {
                $response->data['acf'] = $fields;
            }
            return $response;
        }, 10, 2 );
    }

    // Yoast SEO meta
    register_rest_field( $all, 'seo', [
        'get_callback' => function ( $p ) {
            $id = $p['id'];
            return [
                'title'       => get_post_meta( $id, '_yoast_wpseo_title',    true ) ?: get_the_title( $id ),
                'description' => get_post_meta( $id, '_yoast_wpseo_metadesc', true ),
                'canonical'   => get_post_meta( $id, '_yoast_wpseo_canonical', true ) ?: get_permalink( $id ),
                'no_index'    => (bool) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true ),
                'og_image'    => get_post_meta( $id, '_yoast_wpseo_opengraph-image', true ),
            ];
        },
        'schema' => [ 'type' => 'object', 'context' => [ 'view' ] ],
    ] );

    // Post-type specific meta fields
    register_rest_field( 'research', 'research_meta', [
        'get_callback' => fn( $p ) => [
            'venue'     => get_post_meta( $p['id'], 'research_venue',     true ),
            'year'      => get_post_meta( $p['id'], 'research_year',      true ),
            'doi'       => get_post_meta( $p['id'], 'research_doi',       true ),
            'pdf_url'   => get_post_meta( $p['id'], 'research_pdf_url',   true ),
            'award'     => get_post_meta( $p['id'], 'research_award',     true ),
            'coauthors' => get_post_meta( $p['id'], 'research_coauthors', true ),
        ],
        'schema' => [ 'type' => 'object', 'context' => [ 'view' ] ],
    ] );

    register_rest_field( 'talk', 'talk_meta', [
        'get_callback' => fn( $p ) => [
            'event'      => get_post_meta( $p['id'], 'talk_event',      true ),
            'event_date' => get_post_meta( $p['id'], 'talk_event_date', true ),
            'location'   => get_post_meta( $p['id'], 'talk_location',   true ),
            'slides_url' => get_post_meta( $p['id'], 'talk_slides_url', true ),
            'video_url'  => get_post_meta( $p['id'], 'talk_video_url',  true ),
            'language'   => get_post_meta( $p['id'], 'talk_language',   true ),
        ],
        'schema' => [ 'type' => 'object', 'context' => [ 'view' ] ],
    ] );

    register_rest_field( 'testimonial', 'testimonial_meta', [
        'get_callback' => fn( $p ) => [
            'person_name'  => get_post_meta( $p['id'], 'testimonial_name',     true ),
            'person_title' => get_post_meta( $p['id'], 'testimonial_title',    true ),
            'person_org'   => get_post_meta( $p['id'], 'testimonial_org',      true ),
            'rating'       => get_post_meta( $p['id'], 'testimonial_rating',   true ),
            'language'     => get_post_meta( $p['id'], 'testimonial_language', true ),
            'context'      => get_post_meta( $p['id'], 'testimonial_context',  true ),
        ],
        'schema' => [ 'type' => 'object', 'context' => [ 'view' ] ],
    ] );

    register_rest_field( 'portfolio', 'portfolio_meta', [
        'get_callback' => fn( $p ) => [
            'client'      => get_post_meta( $p['id'], 'portfolio_client',      true ),
            'year'        => get_post_meta( $p['id'], 'portfolio_year',        true ),
            'tools'       => get_post_meta( $p['id'], 'portfolio_tools',       true ),
            'project_url' => get_post_meta( $p['id'], 'portfolio_project_url', true ),
        ],
        'schema' => [ 'type' => 'object', 'context' => [ 'view' ] ],
    ] );
}

// ════════════════════════════════════════════════════════════════════════════
// 8. ADMIN — FEATURED IMAGE COLUMN + META BOXES
// ════════════════════════════════════════════════════════════════════════════

add_action( 'admin_head', function () {
    echo '<style>
        .column-featured_thumb { width: 58px !important; }
        .column-featured_thumb img { width:50px;height:50px;object-fit:cover;border-radius:4px;display:block; }
        .column-featured_thumb .no-thumb { width:50px;height:50px;background:#1b2825;border-radius:4px;
            display:flex;align-items:center;justify-content:center;font-size:20px;color:#3e5852; }
    </style>';
} );

$_thumb_types = [ 'post', 'page', 'portfolio', 'testimonial', 'research', 'talk', 'course' ];
foreach ( $_thumb_types as $_type ) {
    add_filter( "manage_{$_type}_posts_columns", function ( $cols ) {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[$k] = $v;
            if ( $k === 'cb' ) $new['featured_thumb'] = '🖼';
        }
        return $new;
    } );
    add_action( "manage_{$_type}_posts_custom_column", function ( $col, $id ) {
        if ( $col !== 'featured_thumb' ) return;
        echo has_post_thumbnail( $id )
            ? get_the_post_thumbnail( $id, [50, 50] )
            : '<div class="no-thumb">·</div>';
    }, 10, 2 );
}
unset( $_thumb_types, $_type );

// Reading time column on posts
add_filter( 'manage_posts_columns', fn( $c ) => array_merge( $c, [ 'reading_time' => '⏱' ] ) );
add_action( 'manage_posts_custom_column', function ( $col, $id ) {
    if ( $col !== 'reading_time' ) return;
    $words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) );
    echo esc_html( max( 1, ceil( $words / 200 ) ) ) . ' min';
}, 10, 2 );

// Meta boxes
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'aaron_kr_research',    'Research Details',    'aaron_kr_research_mb',    'research',    'side', 'high' );
    add_meta_box( 'aaron_kr_talk',        'Talk Details',        'aaron_kr_talk_mb',        'talk',        'side', 'high' );
    add_meta_box( 'aaron_kr_testimonial', 'Testimonial Details', 'aaron_kr_testimonial_mb', 'testimonial', 'side', 'high' );
    add_meta_box( 'aaron_kr_portfolio',   'Portfolio Details',   'aaron_kr_portfolio_mb',   'portfolio',   'side', 'high' );
} );

function aaron_kr_render_fields( int $post_id, array $fields ): void {
    wp_nonce_field( 'aaron_kr_save_meta', 'aaron_kr_meta_nonce' );
    echo '<table style="width:100%">';
    foreach ( $fields as $key => $label ) {
        $val = esc_attr( get_post_meta( $post_id, $key, true ) );
        echo "<tr><td style='padding:2px 0'><label style='font-weight:600;font-size:11px'>$label</label></td></tr>
              <tr><td style='padding-bottom:6px'><input type='text' name='$key' value='$val' style='width:100%;box-sizing:border-box'/></td></tr>";
    }
    echo '</table>';
}

function aaron_kr_research_mb( $p ): void {
    aaron_kr_render_fields( $p->ID, [ 'research_venue'=>'Venue / Journal','research_year'=>'Year',
        'research_doi'=>'DOI','research_pdf_url'=>'PDF URL','research_award'=>'Award','research_coauthors'=>'Co-authors' ] );
}
function aaron_kr_talk_mb( $p ): void {
    aaron_kr_render_fields( $p->ID, [ 'talk_event'=>'Event Name','talk_event_date'=>'Date (YYYY-MM-DD)',
        'talk_location'=>'Location','talk_slides_url'=>'Slides URL','talk_video_url'=>'Video URL','talk_language'=>'Language (EN/KO)' ] );
}
function aaron_kr_testimonial_mb( $p ): void {
    aaron_kr_render_fields( $p->ID, [ 'testimonial_name'=>'Person Name','testimonial_title'=>'Their Title',
        'testimonial_org'=>'Organization','testimonial_rating'=>'Rating (1–5)','testimonial_language'=>'Language (EN/KO)','testimonial_context'=>'Context' ] );
}
function aaron_kr_portfolio_mb( $p ): void {
    aaron_kr_render_fields( $p->ID, [ 'portfolio_client'=>'Client','portfolio_year'=>'Year',
        'portfolio_tools'=>'Tools Used','portfolio_project_url'=>'Project URL' ] );
}

add_action( 'save_post', function ( $id ) {
    if ( ! isset( $_POST['aaron_kr_meta_nonce'] ) ||
         ! wp_verify_nonce( $_POST['aaron_kr_meta_nonce'], 'aaron_kr_save_meta' ) ||
         ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
         ! current_user_can( 'edit_post', $id ) ) return;

    foreach ( [
        'research_venue','research_year','research_doi','research_pdf_url','research_award','research_coauthors',
        'talk_event','talk_event_date','talk_location','talk_slides_url','talk_video_url','talk_language',
        'testimonial_name','testimonial_title','testimonial_org','testimonial_rating','testimonial_language','testimonial_context',
        'portfolio_client','portfolio_year','portfolio_tools','portfolio_project_url',
        'naver_blog_url', 'korean_post_url',
    ] as $key ) {
        if ( isset( $_POST[$key] ) ) update_post_meta( $id, $key, sanitize_text_field( $_POST[$key] ) );
    }
} );

// ════════════════════════════════════════════════════════════════════════════
// 9. NAVER BLOG / KOREAN CROSS-POST — meta on all post types
//    Stored as post meta. Exposed via REST. Shown in meta box on every post.
// ════════════════════════════════════════════════════════════════════════════

add_action( 'rest_api_init', function () {
    $all = [ 'post', 'page', 'portfolio', 'testimonial', 'research', 'talk', 'course' ];

    foreach ( $all as $type ) {
        register_rest_field( $type, 'naver_blog_url', [
            'get_callback' => fn( $p ) => get_post_meta( $p['id'], 'naver_blog_url', true ) ?: null,
            'schema'       => [ 'type' => [ 'string', 'null' ], 'context' => [ 'view' ] ],
        ] );
        register_rest_field( $type, 'korean_post_url', [
            'get_callback' => fn( $p ) => get_post_meta( $p['id'], 'korean_post_url', true ) ?: null,
            'schema'       => [ 'type' => [ 'string', 'null' ], 'context' => [ 'view' ] ],
        ] );
    }
} );

// Meta box: shown on every post/CPT edit screen
add_action( 'add_meta_boxes', function () {
    $all = [ 'post', 'page', 'portfolio', 'testimonial', 'research', 'talk', 'course' ];
    add_meta_box(
        'aaron_kr_external_links',
        'External / Cross-post Links',
        function ( $post ) {
            wp_nonce_field( 'aaron_kr_save_meta', 'aaron_kr_meta_nonce' );
            $naver   = esc_attr( get_post_meta( $post->ID, 'naver_blog_url', true ) );
            $ko_url  = esc_attr( get_post_meta( $post->ID, 'korean_post_url', true ) );
            echo '<table style="width:100%">
                <tr><td style="padding:2px 0"><label style="font-weight:600;font-size:11px">Naver Blog URL (한국어로 읽기)</label></td></tr>
                <tr><td style="padding-bottom:6px"><input type="url" name="naver_blog_url" value="' . $naver . '" style="width:100%;box-sizing:border-box" placeholder="https://m.blog.naver.com/..." /></td></tr>
                <tr><td style="padding:2px 0"><label style="font-weight:600;font-size:11px">Korean Post URL (alternate link, if not Naver)</label></td></tr>
                <tr><td style="padding-bottom:6px"><input type="url" name="korean_post_url" value="' . $ko_url . '" style="width:100%;box-sizing:border-box" placeholder="https://..." /></td></tr>
            </table>';
        },
        $all, 'side', 'default'
    );
} );

// ════════════════════════════════════════════════════════════════════════════
// 10. CATEGORY FEATURED IMAGE
//     Adds an image URL field to the category admin screen so editors can
//     set a hero/card image per category. Exposed via REST under meta.
// ════════════════════════════════════════════════════════════════════════════

add_action( 'init', function () {
    register_term_meta( 'category', 'category_image_url', [
        'show_in_rest'  => true,
        'type'          => 'string',
        'description'   => 'Featured image URL for this category',
        'single'        => true,
        'auth_callback' => fn() => current_user_can( 'edit_posts' ),
    ] );
} );

// Add field to "Add Category" form
add_action( 'category_add_form_fields', function () {
    echo '<div class="form-field">
        <label for="category_image_url">Featured Image URL</label>
        <input type="url" name="category_image_url" id="category_image_url" value="" />
        <p>Paste a full image URL (from files.aaron.kr or any host).</p>
    </div>';
} );

// Add field to "Edit Category" form
add_action( 'category_edit_form_fields', function ( $term ) {
    $val = esc_attr( get_term_meta( $term->term_id, 'category_image_url', true ) );
    echo '<tr class="form-field">
        <th><label for="category_image_url">Featured Image URL</label></th>
        <td>
            <input type="url" name="category_image_url" id="category_image_url" value="' . $val . '" style="width:100%" />
            <p class="description">Paste a full image URL (from files.aaron.kr or any host).</p>
        </td>
    </tr>';
} );

// Save the field on both create and update
add_action( 'created_category', 'aaron_kr_save_category_image_url' );
add_action( 'edited_category',  'aaron_kr_save_category_image_url' );

function aaron_kr_save_category_image_url( int $term_id ): void {
    if ( isset( $_POST['category_image_url'] ) ) {
        update_term_meta( $term_id, 'category_image_url', esc_url_raw( $_POST['category_image_url'] ) );
    }
}

// Expose description + meta.category_image_url in REST response for categories
// (description is already in REST; meta is exposed by register_term_meta with show_in_rest)
add_filter( 'rest_prepare_category', function ( $response ) {
    // Ensure 'count' and 'description' are always present
    $data = $response->get_data();
    if ( ! isset( $data['description'] ) ) $data['description'] = '';
    $response->set_data( $data );
    return $response;
} );
