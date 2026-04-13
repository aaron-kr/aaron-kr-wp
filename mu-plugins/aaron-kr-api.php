<?php
/**
 * Plugin Name:  Aaron KR — Headless API Layer
 * Description:  Configures the WordPress REST API for the aaron.kr Next.js frontend.
 *               Handles CORS, registers post types, adds custom REST fields
 *               (reading time, clean excerpt, author card, etc.), and manages
 *               Jetpack Portfolio/Testimonials REST exposure.
 *               Installed as a must-use plugin so it's always active regardless
 *               of theme or plugin changes.
 * Version:      1.0.0
 * Author:       Aaron Snowberger
 *
 * INSTALLATION:
 *   Copy this file to: wp-content/mu-plugins/aaron-kr-api.php
 *   No activation needed — mu-plugins load automatically on every request.
 */

defined( 'ABSPATH' ) || exit;

// ════════════════════════════════════════════════════════════════════════════
// 1. CORS — allow the Next.js frontend to call the REST API
//    Adjust ALLOWED_ORIGINS if you add more frontends (staging, etc.)
// ════════════════════════════════════════════════════════════════════════════

define( 'AARON_KR_ALLOWED_ORIGINS', [
    'https://aaron.kr',
    'https://www.aaron.kr',
    'http://localhost:3000',   // local Next.js dev server
    'http://localhost:3001',   // alternate port
] );

add_action( 'rest_api_init', function () {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
    add_filter( 'rest_pre_serve_request', 'aaron_kr_cors_headers' );
}, 15 );

add_action( 'send_headers', 'aaron_kr_cors_headers' );

function aaron_kr_cors_headers( $value = null ) {
    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : '';

    if ( in_array( $origin, AARON_KR_ALLOWED_ORIGINS, true ) ) {
        header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
        header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
        header( 'Access-Control-Allow-Credentials: true' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
        header( 'Vary: Origin' );
    }

    // Handle preflight OPTIONS requests
    if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
        status_header( 204 );
        exit;
    }

    return $value;
}

// ════════════════════════════════════════════════════════════════════════════
// 2. POST TYPES — register custom types with full REST API support
// ════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'aaron_kr_register_post_types' );

function aaron_kr_register_post_types() {

    // ── Research Papers ──────────────────────────────────────────────────────
    register_post_type( 'research', [
        'label'         => 'Research',
        'labels'        => [
            'name'          => 'Research Papers',
            'singular_name' => 'Research Paper',
            'add_new_item'  => 'Add Research Paper',
            'edit_item'     => 'Edit Research Paper',
        ],
        'public'        => true,
        'show_in_rest'  => true,        // ← essential for REST API access
        'rest_base'     => 'research',  // → /wp-json/wp/v2/research
        'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail',
                             'custom-fields', 'revisions', 'author' ],
        'has_archive'   => false,       // headless — no WP archive page needed
        'rewrite'       => [ 'slug' => 'research' ],
        'menu_icon'     => 'dashicons-welcome-learn-more',
        'menu_position' => 5,
        'taxonomies'    => [ 'post_tag' ],
    ] );

    // ── Talks / Presentations ────────────────────────────────────────────────
    register_post_type( 'talk', [
        'label'         => 'Talks',
        'labels'        => [
            'name'          => 'Talks',
            'singular_name' => 'Talk',
            'add_new_item'  => 'Add Talk',
            'edit_item'     => 'Edit Talk',
        ],
        'public'        => true,
        'show_in_rest'  => true,
        'rest_base'     => 'talks',     // → /wp-json/wp/v2/talks
        'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail',
                             'custom-fields', 'revisions' ],
        'has_archive'   => false,
        'rewrite'       => [ 'slug' => 'talks' ],
        'menu_icon'     => 'dashicons-megaphone',
        'menu_position' => 6,
        'taxonomies'    => [ 'post_tag' ],
    ] );

    // ── Courses (supplements the GitHub Pages site with WP-managed metadata) ─
    register_post_type( 'course', [
        'label'         => 'Courses',
        'labels'        => [
            'name'          => 'Courses',
            'singular_name' => 'Course',
        ],
        'public'        => true,
        'show_in_rest'  => true,
        'rest_base'     => 'courses',   // → /wp-json/wp/v2/courses
        'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ],
        'has_archive'   => false,
        'rewrite'       => [ 'slug' => 'courses' ],
        'menu_icon'     => 'dashicons-book-alt',
        'menu_position' => 7,
    ] );
}

// ── Ensure Jetpack Portfolio & Testimonials are in the REST API ─────────────
// Jetpack registers these early, so we patch them after init.
add_action( 'init', 'aaron_kr_jetpack_rest', 20 );

function aaron_kr_jetpack_rest() {
    global $wp_post_types;

    // Portfolio → /wp-json/wp/v2/jetpack-portfolio
    if ( isset( $wp_post_types['jetpack-portfolio'] ) ) {
        $wp_post_types['jetpack-portfolio']->show_in_rest = true;
        $wp_post_types['jetpack-portfolio']->rest_base    = 'jetpack-portfolio';
    }

    // Testimonials → /wp-json/wp/v2/jetpack-testimonial
    if ( isset( $wp_post_types['jetpack-testimonial'] ) ) {
        $wp_post_types['jetpack-testimonial']->show_in_rest = true;
        $wp_post_types['jetpack-testimonial']->rest_base    = 'jetpack-testimonial';
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 3. TAXONOMIES — add custom taxonomies and ensure all are in REST
// ════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'aaron_kr_register_taxonomies' );

function aaron_kr_register_taxonomies() {

    // Research areas (hierarchical, like categories)
    register_taxonomy( 'research_area', [ 'research' ], [
        'label'        => 'Research Areas',
        'hierarchical' => true,
        'show_in_rest' => true,
        'rest_base'    => 'research-areas',
        'rewrite'      => [ 'slug' => 'research-area' ],
    ] );

    // Skills / tech tags (flat, shared across post types)
    register_taxonomy( 'skill', [ 'research', 'talk', 'course', 'post' ], [
        'label'        => 'Skills & Technologies',
        'hierarchical' => false,
        'show_in_rest' => true,
        'rest_base'    => 'skills',
        'rewrite'      => [ 'slug' => 'skill' ],
    ] );
}

// ════════════════════════════════════════════════════════════════════════════
// 4. CUSTOM REST FIELDS
//    These extend the REST API response with data the Next.js frontend needs
//    but that WordPress doesn't expose by default.
// ════════════════════════════════════════════════════════════════════════════

add_action( 'rest_api_init', 'aaron_kr_register_rest_fields' );

function aaron_kr_register_rest_fields() {
    $all_types = [ 'post', 'page', 'research', 'talk', 'course',
                   'jetpack-portfolio', 'jetpack-testimonial' ];

    // ── Reading time ─────────────────────────────────────────────────────────
    // Returns integer minutes. Frontend displays "5 min read".
    register_rest_field( $all_types, 'reading_time_minutes', [
        'get_callback' => function ( $post_arr ) {
            $content    = get_post_field( 'post_content', $post_arr['id'] );
            $word_count = str_word_count( wp_strip_all_tags( $content ) );
            return (int) max( 1, ceil( $word_count / 200 ) ); // 200 wpm
        },
        'schema' => [
            'description' => 'Estimated reading time in minutes.',
            'type'        => 'integer',
            'context'     => [ 'view' ],
        ],
    ] );

    // ── Plain excerpt ────────────────────────────────────────────────────────
    // WP's default excerpt.rendered contains <p> tags. This gives a clean
    // plain-text version, already truncated to 160 chars (good for meta too).
    register_rest_field( $all_types, 'excerpt_plain', [
        'get_callback' => function ( $post_arr ) {
            $raw = get_post_field( 'post_excerpt', $post_arr['id'] );
            if ( ! $raw ) {
                $raw = get_post_field( 'post_content', $post_arr['id'] );
            }
            $plain = wp_strip_all_tags( $raw );
            return mb_strimwidth( $plain, 0, 160, '…' );
        },
        'schema' => [
            'description' => 'Plain-text excerpt, max 160 chars.',
            'type'        => 'string',
            'context'     => [ 'view' ],
        ],
    ] );

    // ── Author card ──────────────────────────────────────────────────────────
    // Returns everything the frontend needs without a second API call.
    register_rest_field( $all_types, 'author_card', [
        'get_callback' => function ( $post_arr ) {
            $user_id = $post_arr['author'] ?? get_post_field( 'post_author', $post_arr['id'] );
            if ( ! $user_id ) return null;
            return [
                'name'        => get_the_author_meta( 'display_name', $user_id ),
                'slug'        => get_the_author_meta( 'user_nicename', $user_id ),
                'description' => get_the_author_meta( 'description', $user_id ),
                'url'         => get_the_author_meta( 'url', $user_id ),
                'avatar'      => get_avatar_url( $user_id, [ 'size' => 96 ] ),
                'twitter'     => get_the_author_meta( 'twitter', $user_id ),
            ];
        },
        'schema' => [
            'description' => 'Author card data.',
            'type'        => 'object',
            'context'     => [ 'view' ],
        ],
    ] );

    // ── Featured image (all sizes, no _embed required) ───────────────────────
    // Using _embed works but adds latency for the media lookup on WP's side.
    // This field inlines just the URLs the frontend actually uses.
    register_rest_field( $all_types, 'featured_image_urls', [
        'get_callback' => function ( $post_arr ) {
            $id = get_post_thumbnail_id( $post_arr['id'] );
            if ( ! $id ) return null;
            $full   = wp_get_attachment_image_src( $id, 'full' );
            $large  = wp_get_attachment_image_src( $id, 'large' );
            $medium = wp_get_attachment_image_src( $id, 'medium' );
            $alt    = get_post_meta( $id, '_wp_attachment_image_alt', true );
            return [
                'full'   => $full   ? $full[0]   : null,
                'large'  => $large  ? $large[0]  : null,
                'medium' => $medium ? $medium[0] : null,
                'alt'    => $alt ?: '',
            ];
        },
        'schema' => [
            'description' => 'Featured image URLs at multiple sizes.',
            'type'        => 'object',
            'context'     => [ 'view' ],
        ],
    ] );

    // ── Categories (flat array of names + slugs, no second API call) ─────────
    register_rest_field( [ 'post', 'research', 'talk' ], 'category_list', [
        'get_callback' => function ( $post_arr ) {
            $terms = get_the_terms( $post_arr['id'], 'category' );
            if ( ! $terms || is_wp_error( $terms ) ) return [];
            return array_map( fn( $t ) => [
                'id'   => $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
            ], $terms );
        },
        'schema' => [
            'description' => 'Categories as name/slug pairs.',
            'type'        => 'array',
            'context'     => [ 'view' ],
        ],
    ] );

    // ── Tags ─────────────────────────────────────────────────────────────────
    register_rest_field( $all_types, 'tag_list', [
        'get_callback' => function ( $post_arr ) {
            $terms = get_the_terms( $post_arr['id'], 'post_tag' );
            if ( ! $terms || is_wp_error( $terms ) ) return [];
            return array_map( fn( $t ) => [
                'id'   => $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
            ], $terms );
        },
        'schema' => [
            'description' => 'Tags as name/slug pairs.',
            'type'        => 'array',
            'context'     => [ 'view' ],
        ],
    ] );

    // ── Research-specific fields ─────────────────────────────────────────────
    // Stored as post meta. Edit in WP Admin → Research → (post) → Custom Fields,
    // or use ACF / Meta Box plugin for a nicer UI.
    register_rest_field( 'research', 'research_meta', [
        'get_callback' => function ( $post_arr ) {
            $id = $post_arr['id'];
            return [
                'venue'     => get_post_meta( $id, 'research_venue',     true ), // journal/conf name
                'year'      => get_post_meta( $id, 'research_year',      true ),
                'doi'       => get_post_meta( $id, 'research_doi',       true ),
                'pdf_url'   => get_post_meta( $id, 'research_pdf_url',   true ),
                'award'     => get_post_meta( $id, 'research_award',     true ), // e.g. "Best Thesis"
                'coauthors' => get_post_meta( $id, 'research_coauthors', true ),
            ];
        },
        'schema' => [
            'description' => 'Research-specific metadata.',
            'type'        => 'object',
            'context'     => [ 'view' ],
        ],
    ] );

    // ── Talk-specific fields ─────────────────────────────────────────────────
    register_rest_field( 'talk', 'talk_meta', [
        'get_callback' => function ( $post_arr ) {
            $id = $post_arr['id'];
            return [
                'event'       => get_post_meta( $id, 'talk_event',       true ),
                'event_date'  => get_post_meta( $id, 'talk_event_date',  true ),
                'location'    => get_post_meta( $id, 'talk_location',    true ),
                'slides_url'  => get_post_meta( $id, 'talk_slides_url',  true ),
                'video_url'   => get_post_meta( $id, 'talk_video_url',   true ),
                'language'    => get_post_meta( $id, 'talk_language',    true ), // EN / KO
            ];
        },
        'schema' => [
            'description' => 'Talk-specific metadata.',
            'type'        => 'object',
            'context'     => [ 'view' ],
        ],
    ] );
}

// ════════════════════════════════════════════════════════════════════════════
// 5. REST API — quality-of-life improvements
// ════════════════════════════════════════════════════════════════════════════

// ── Remove password-protected posts from REST responses ─────────────────────
add_filter( 'rest_post_query', function ( $args ) {
    $args['has_password'] = false;
    return $args;
} );

// ── Add ?lang= filter support (for future bilingual post setup) ─────────────
// If you later use Polylang or WPML, this hook is where you'd add
// language filtering to REST queries.

// ── Cache-Control headers on REST responses ──────────────────────────────────
// Tells Vercel's edge cache (and ISR) how long to consider responses fresh.
// Next.js's `next: { revalidate: 3600 }` fetch option handles this on the
// JS side, but setting headers here provides a second layer.
add_filter( 'rest_post_dispatch', function ( $response ) {
    if ( ! is_wp_error( $response ) ) {
        $response->header( 'Cache-Control', 'public, max-age=3600, stale-while-revalidate=7200' );
    }
    return $response;
} );

// ── Disable REST API for unauthenticated users on non-GET requests ────────────
// Your Next.js app only reads (GET) — this blocks any write attempts.
add_filter( 'rest_authentication_errors', function ( $result ) {
    if ( true === $result || is_wp_error( $result ) ) {
        return $result;
    }
    if ( ! is_user_logged_in() ) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ( ! in_array( $method, [ 'GET', 'OPTIONS', 'HEAD' ], true ) ) {
            return new WP_Error(
                'rest_not_logged_in',
                'Write access requires authentication.',
                [ 'status' => 401 ]
            );
        }
    }
    return $result;
} );

// ════════════════════════════════════════════════════════════════════════════
// 6. ADMIN ENHANCEMENTS
//    Better editing experience for the custom post types
// ════════════════════════════════════════════════════════════════════════════

// ── Show reading time in the post list column ────────────────────────────────
add_filter( 'manage_posts_columns', 'aaron_kr_add_reading_time_column' );
add_action( 'manage_posts_custom_column', 'aaron_kr_reading_time_column_value', 10, 2 );

function aaron_kr_add_reading_time_column( $columns ) {
    $columns['reading_time'] = '⏱ Read';
    return $columns;
}

function aaron_kr_reading_time_column_value( $column, $post_id ) {
    if ( 'reading_time' === $column ) {
        $content    = get_post_field( 'post_content', $post_id );
        $word_count = str_word_count( wp_strip_all_tags( $content ) );
        $minutes    = max( 1, ceil( $word_count / 200 ) );
        echo esc_html( $minutes ) . ' min';
    }
}

// ── Custom meta boxes for research and talk fields ───────────────────────────
add_action( 'add_meta_boxes', 'aaron_kr_add_meta_boxes' );

function aaron_kr_add_meta_boxes() {
    add_meta_box(
        'aaron_kr_research_meta',
        'Research Details',
        'aaron_kr_research_meta_box',
        'research',
        'side',
        'high'
    );
    add_meta_box(
        'aaron_kr_talk_meta',
        'Talk Details',
        'aaron_kr_talk_meta_box',
        'talk',
        'side',
        'high'
    );
}

function aaron_kr_research_meta_box( $post ) {
    wp_nonce_field( 'aaron_kr_save_meta', 'aaron_kr_meta_nonce' );
    $fields = [
        'research_venue'     => 'Venue / Journal',
        'research_year'      => 'Year',
        'research_doi'       => 'DOI',
        'research_pdf_url'   => 'PDF URL',
        'research_award'     => 'Award (if any)',
        'research_coauthors' => 'Co-authors',
    ];
    aaron_kr_render_meta_fields( $post->ID, $fields );
}

function aaron_kr_talk_meta_box( $post ) {
    wp_nonce_field( 'aaron_kr_save_meta', 'aaron_kr_meta_nonce' );
    $fields = [
        'talk_event'      => 'Event Name',
        'talk_event_date' => 'Event Date (YYYY-MM-DD)',
        'talk_location'   => 'Location',
        'talk_slides_url' => 'Slides URL',
        'talk_video_url'  => 'Video URL',
        'talk_language'   => 'Language (EN / KO)',
    ];
    aaron_kr_render_meta_fields( $post->ID, $fields );
}

function aaron_kr_render_meta_fields( $post_id, $fields ) {
    echo '<table style="width:100%;border-collapse:collapse">';
    foreach ( $fields as $key => $label ) {
        $value = get_post_meta( $post_id, $key, true );
        printf(
            '<tr><td style="padding:4px 0"><label for="%1$s" style="font-weight:600;font-size:12px">%2$s</label></td></tr>
             <tr><td style="padding-bottom:8px"><input type="text" id="%1$s" name="%1$s" value="%3$s" style="width:100%%;box-sizing:border-box"/></td></tr>',
            esc_attr( $key ),
            esc_html( $label ),
            esc_attr( $value )
        );
    }
    echo '</table>';
}

// ── Save meta box data ────────────────────────────────────────────────────────
add_action( 'save_post', 'aaron_kr_save_meta_box_data' );

function aaron_kr_save_meta_box_data( $post_id ) {
    if (
        ! isset( $_POST['aaron_kr_meta_nonce'] ) ||
        ! wp_verify_nonce( $_POST['aaron_kr_meta_nonce'], 'aaron_kr_save_meta' ) ||
        defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
        ! current_user_can( 'edit_post', $post_id )
    ) {
        return;
    }

    $all_meta_keys = [
        'research_venue', 'research_year', 'research_doi',
        'research_pdf_url', 'research_award', 'research_coauthors',
        'talk_event', 'talk_event_date', 'talk_location',
        'talk_slides_url', 'talk_video_url', 'talk_language',
    ];

    foreach ( $all_meta_keys as $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 7. PERFORMANCE
// ════════════════════════════════════════════════════════════════════════════

// ── Limit REST API post response fields to what Next.js actually uses ─────────
// This cuts response size ~60% by dropping fields like ping_status, template,
// meta (raw WP internal meta), etc. Only applies to GET requests.
// If you need a field that's missing, add it to the $needed array.
add_filter( 'rest_request_after_callbacks', function( $response, $handler, $request ) {
    if ( 'GET' !== $request->get_method() ) {
        return $response;
    }

    $needed = [
        'id', 'date', 'modified', 'slug', 'link', 'type', 'status',
        'title', 'content', 'excerpt', 'featured_media',
        'categories', 'tags', 'author',
        // Custom fields added by this plugin:
        'reading_time_minutes', 'excerpt_plain', 'author_card',
        'featured_image_urls', 'category_list', 'tag_list',
        'research_meta', 'talk_meta',
        // Jetpack portfolio fields:
        'jetpack_portfolio_tag', 'jetpack_portfolio_type',
        '_links', '_embedded',
    ];

    if ( is_a( $response, 'WP_REST_Response' ) ) {
        $data = $response->get_data();
        if ( is_array( $data ) && isset( $data['id'] ) ) {
            // Single post — filter keys
            $response->set_data( array_intersect_key( $data, array_flip( $needed ) ) );
        }
        // Collection (array of posts) — filter each item
        if ( is_array( $data ) && isset( $data[0]['id'] ) ) {
            $response->set_data( array_map(
                fn( $item ) => array_intersect_key( $item, array_flip( $needed ) ),
                $data
            ) );
        }
    }

    return $response;
}, 10, 3 );
