<?php
/**
 * Plugin Name: Aaron KR — Portfolio Exporter (aaronsnowberger.com)
 * Description: Exports Jetpack Portfolio and Testimonials as a self-contained
 *              JSON file. Remaps post types to the new custom CPT names, carries
 *              taxonomy terms, all post meta, and encodes featured images as
 *              base64 so they survive the transfer without URL dependency.
 *              INSTALL ON: aaronsnowberger.com (the SOURCE site)
 *              DELETE AFTER: export is complete
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
    add_management_page(
        'Export Portfolio to aaron.kr',
        '📦 Export to aaron.kr',
        'manage_options',
        'aaron-kr-exporter',
        'aaron_kr_exporter_page'
    );
} );

function aaron_kr_exporter_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );

    // ── Trigger download ───────────────────────────────────────────────────────
    if (
        isset( $_POST['aaron_kr_do_export'] ) &&
        wp_verify_nonce( $_POST['_wpnonce'], 'aaron_kr_export' )
    ) {
        $type     = sanitize_key( $_POST['export_type'] ?? 'portfolio' );
        $embed    = ! empty( $_POST['embed_images'] );
        $data     = aaron_kr_build_export( $type, $embed );
        $filename = "aaron-kr-{$type}-export-" . date( 'Y-m-d' ) . '.json';

        header( 'Content-Type: application/json; charset=utf-8' );
        header( "Content-Disposition: attachment; filename=\"{$filename}\"" );
        header( 'Cache-Control: no-cache' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    // ── Preview counts ─────────────────────────────────────────────────────────
    $counts = [];
    foreach ( [ 'jetpack-portfolio' => 'portfolio', 'jetpack-testimonial' => 'testimonial' ] as $jp => $new ) {
        $c = wp_count_posts( $jp );
        $counts[ $jp ] = [
            'new_type' => $new,
            'publish'  => $c->publish  ?? 0,
            'draft'    => $c->draft    ?? 0,
            'private'  => $c->private  ?? 0,
        ];
    }
    ?>
    <div class="wrap">
        <h1>📦 Export Portfolio → aaron.kr</h1>
        <p>Exports Jetpack Portfolio or Testimonials as a self-contained JSON file
           ready to import on your aaron.kr WordPress installation. Post types are
           remapped to your new custom CPTs. All taxonomy terms and post meta are
           included. Optionally embeds featured images as base64.</p>

        <h2>Available content</h2>
        <table class="widefat" style="max-width:600px;margin-bottom:1.5rem">
            <thead><tr><th>Source type</th><th>→ New type</th><th>Published</th><th>Drafts</th></tr></thead>
            <tbody>
            <?php foreach ( $counts as $jp => [ 'new_type' => $nt, 'publish' => $pub, 'draft' => $dr ] ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $jp ); ?></code></td>
                    <td><code><?php echo esc_html( $nt ); ?></code></td>
                    <td><?php echo (int) $pub; ?></td>
                    <td><?php echo (int) $dr; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post">
            <?php wp_nonce_field( 'aaron_kr_export' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="export_type">Export which type?</label></th>
                    <td>
                        <select name="export_type" id="export_type">
                            <option value="portfolio">Jetpack Portfolio → portfolio</option>
                            <option value="testimonial">Jetpack Testimonials → testimonial</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="embed_images">Embed featured images?</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="embed_images" id="embed_images" value="1" checked>
                            Yes — encode images as base64 in the JSON
                            <br><small style="color:#666">Recommended. Makes the JSON self-contained.
                            File may be large (5–50MB depending on image count).
                            Uncheck only if you know images are already on the target server.</small>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="aaron_kr_do_export" value="1"
                    class="button button-primary button-large">
                    ⬇ Generate &amp; Download JSON
                </button>
            </p>
        </form>

        <hr>
        <h2>After exporting</h2>
        <ol>
            <li>Download the JSON file from this page.</li>
            <li>On <strong>aaron.kr</strong> (or your local WP): install <code>aaron-kr-importer.php</code> as a mu-plugin.</li>
            <li>Go to <strong>Tools → Import from aaron.kr JSON</strong> on the target site.</li>
            <li>Upload and run the import.</li>
            <li>Delete both this exporter and the importer when done.</li>
        </ol>
    </div>
    <?php
}

// ── Build the export array ────────────────────────────────────────────────────
function aaron_kr_build_export( string $type, bool $embed_images ): array {
    $jp_type = "jetpack-{$type}"; // jetpack-portfolio or jetpack-testimonial

    // Jetpack taxonomy names
    $jp_tag_tax  = "jetpack-{$type}-tag";  // jetpack-portfolio-tag
    $jp_type_tax = "jetpack-{$type}-type"; // jetpack-portfolio-type (portfolio only)

    $posts = get_posts( [
        'post_type'      => $jp_type,
        'posts_per_page' => -1,
        'post_status'    => [ 'publish', 'draft', 'private', 'pending' ],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );

    $export = [
        'export_version'  => '1.0',
        'export_date'     => date( 'c' ),
        'source_site'     => get_bloginfo( 'url' ),
        'source_type'     => $jp_type,
        'target_type'     => $type,
        'post_count'      => count( $posts ),
        'posts'           => [],
    ];

    foreach ( $posts as $post ) {
        $pid = $post->ID;

        // ── Taxonomy terms ────────────────────────────────────────────────────
        $tags  = wp_get_object_terms( $pid, $jp_tag_tax );
        $types = wp_get_object_terms( $pid, $jp_type_tax );
        // Also grab standard post_tag and category in case some were already there
        $std_tags = wp_get_object_terms( $pid, 'post_tag' );
        $cats     = wp_get_object_terms( $pid, 'category' );

        $term_map = function ( $terms ) {
            if ( is_wp_error( $terms ) ) return [];
            return array_map( fn( $t ) => [
                'name' => $t->name,
                'slug' => $t->slug,
            ], $terms );
        };

        // ── Post meta ─────────────────────────────────────────────────────────
        $all_meta = get_post_meta( $pid );
        $clean_meta = [];
        // Skip internal WP meta and thumbnail ID (handled separately)
        $skip = [ '_thumbnail_id', '_edit_lock', '_edit_last', '_wp_old_slug',
                  '_oembed_', '_encloseme', '_pingme' ];
        foreach ( $all_meta as $key => $values ) {
            $skip_this = false;
            foreach ( $skip as $prefix ) {
                if ( strpos( $key, $prefix ) === 0 ) { $skip_this = true; break; }
            }
            if ( ! $skip_this ) {
                $clean_meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
            }
        }

        // ── Featured image ────────────────────────────────────────────────────
        $thumb_id  = get_post_thumbnail_id( $pid );
        $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : null;
        $thumb_data = null;

        if ( $thumb_id && $embed_images ) {
            $thumb_data = aaron_kr_encode_image( $thumb_id );
        }

        // ── ACF fields ────────────────────────────────────────────────────────
        $acf = function_exists( 'get_fields' ) ? get_fields( $pid ) : null;

        $export['posts'][] = [
            // Core post data
            'post_title'    => $post->post_title,
            'post_content'  => $post->post_content,
            'post_excerpt'  => $post->post_excerpt,
            'post_status'   => $post->post_status,
            'post_date'     => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
            'post_name'     => $post->post_name,  // slug
            'post_type'     => $type,              // REMAPPED to new type
            'post_password' => $post->post_password,
            'menu_order'    => $post->menu_order,

            // Taxonomy terms
            'jp_tags'       => $term_map( $tags ),   // jetpack-portfolio-tag → post_tag
            'jp_type_terms' => $term_map( $types ),  // jetpack-portfolio-type → portfolio_type
            'std_tags'      => $term_map( $std_tags ),
            'categories'    => $term_map( $cats ),

            // Meta
            'post_meta'     => $clean_meta,
            'acf'           => $acf,

            // Featured image
            'featured_image_url'      => $thumb_url,
            'featured_image_filename' => $thumb_url ? basename( parse_url( $thumb_url, PHP_URL_PATH ) ) : null,
            'featured_image_alt'      => $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '',
            'featured_image_base64'   => $thumb_data,  // null if embed_images=false
        ];
    }

    return $export;
}

// ── Encode a WP attachment as base64 ─────────────────────────────────────────
function aaron_kr_encode_image( int $attachment_id ): ?array {
    // Try the local file path first (fastest)
    $file_path = get_attached_file( $attachment_id );

    if ( $file_path && file_exists( $file_path ) ) {
        $mime     = mime_content_type( $file_path ) ?: 'image/jpeg';
        $data     = base64_encode( file_get_contents( $file_path ) );
        return [ 'mime' => $mime, 'data' => $data, 'source' => 'local' ];
    }

    // File not local (e.g. Jetpack CDN, remote server) — fetch via HTTP
    $url = wp_get_attachment_image_url( $attachment_id, 'full' );
    if ( ! $url ) return null;

    // Also try i0.wp.com CDN variant that Jetpack uses
    $response = wp_remote_get( $url, [ 'timeout' => 30 ] );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        // Try the direct source URL without CDN
        $source_url = preg_replace( '#^https://i\d+\.wp\.com/#', 'https://', $url );
        $response   = wp_remote_get( $source_url, [ 'timeout' => 30 ] );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [ 'mime' => null, 'data' => null, 'url' => $url, 'source' => 'fetch_failed' ];
        }
        $url = $source_url;
    }

    $body = wp_remote_retrieve_body( $response );
    $mime = wp_remote_retrieve_header( $response, 'content-type' ) ?: 'image/jpeg';
    $mime = explode( ';', $mime )[0]; // strip charset if present
    return [ 'mime' => $mime, 'data' => base64_encode( $body ), 'source' => 'remote', 'url' => $url ];
}
