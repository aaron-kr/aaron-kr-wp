<?php
/**
 * Plugin Name: Aaron KR — Portfolio Exporter (aaronsnowberger.com)
 * Description: Exports Jetpack Portfolio/Testimonials as a downloadable JSON file.
 *              Hooks into admin_init so download headers fire before WordPress
 *              outputs any HTML — the only reliable way to force a file download
 *              from a WP admin page.
 *              INSTALL ON: aaronsnowberger.com   DELETE AFTER: export done
 * Version: 1.2.0
 */

defined( 'ABSPATH' ) || exit;

// ════════════════════════════════════════════════════════════════════════════
// DOWNLOAD HANDLER — must run in admin_init, before any HTML is output.
// This is the critical fix: if we wait until the page callback, WordPress
// has already sent the admin HTML headers and ob_end_clean() can't undo that.
// ════════════════════════════════════════════════════════════════════════════

add_action( 'admin_init', function () {
    // Only act on our specific form submission
    if ( empty( $_POST['aaron_kr_do_export'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'aaron_kr_export' ) ) {
        wp_die( 'Security check failed.' );
    }

    $type  = sanitize_key( $_POST['export_type'] ?? 'portfolio' );
    $embed = ! empty( $_POST['embed_images'] );

    // Raise limits for this request
    @set_time_limit( 600 );
    @ini_set( 'memory_limit', '512M' );

    // Kill any output buffers WordPress or plugins may have opened
    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }

    $filename = 'aaron-kr-' . $type . '-export-' . date( 'Y-m-d' ) . '.json';

    // These headers must fire before any output
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'X-Accel-Buffering: no' ); // tell nginx not to buffer
    header( 'Transfer-Encoding: chunked' );

    aaron_kr_stream_json( $type, $embed );
    exit; // never let WordPress render the page
} );

// ════════════════════════════════════════════════════════════════════════════
// ADMIN PAGE — just the form UI, no download logic here
// ════════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', function () {
    add_management_page(
        'Export Portfolio to aaron.kr',
        '📦 Export to aaron.kr',
        'manage_options',
        'aaron-kr-exporter',
        'aaron_kr_exporter_ui'
    );
} );

function aaron_kr_exporter_ui(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );

    $counts = [];
    foreach ( [
        'jetpack-portfolio'   => 'portfolio',
        'jetpack-testimonial' => 'testimonial',
    ] as $jp => $new ) {
        $c = wp_count_posts( $jp );
        $counts[ $jp ] = [
            'new_type' => $new,
            'publish'  => (int) ( $c->publish ?? 0 ),
            'draft'    => (int) ( $c->draft   ?? 0 ),
        ];
    }
    ?>
    <div class="wrap">
        <h1>📦 Export Portfolio → aaron.kr</h1>

        <div class="notice notice-info" style="max-width:680px">
            <p>
                <strong>Read-only.</strong> This tool never modifies any content —
                it only reads your database and media files.<br>
                Images are encoded one at a time as the file streams to your browser,
                so even 136 posts with featured images won't cause a memory error.
            </p>
        </div>

        <h2 style="margin-top:1.5rem">Available content</h2>
        <table class="widefat" style="max-width:560px;margin-bottom:1.5rem">
            <thead>
                <tr><th>Source type</th><th>→ Target type</th><th>Published</th><th>Drafts</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $counts as $jp => $info ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $jp ); ?></code></td>
                    <td><code><?php echo esc_html( $info['new_type'] ); ?></code></td>
                    <td><?php echo $info['publish']; ?></td>
                    <td><?php echo $info['draft']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post">
            <?php wp_nonce_field( 'aaron_kr_export' ); ?>

            <table class="form-table" style="max-width:680px">
                <tr>
                    <th scope="row"><label for="export_type">Export type</label></th>
                    <td>
                        <select name="export_type" id="export_type" class="regular-text">
                            <option value="portfolio">
                                Jetpack Portfolio → portfolio
                                (<?php echo $counts['jetpack-portfolio']['publish']; ?> posts)
                            </option>
                            <option value="testimonial">
                                Jetpack Testimonials → testimonial
                                (<?php echo $counts['jetpack-testimonial']['publish']; ?> posts)
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="embed_images">Featured images</label></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="embed_images" value="1" checked>
                                Embed as base64 <em>(recommended — self-contained, ~50–200MB for 136 posts)</em>
                            </label><br><br>
                            <label>
                                <input type="radio" name="embed_images" value="0">
                                URLs only <em>(smaller file, importer fetches images during import)</em>
                            </label>
                        </fieldset>
                        <p class="description" style="margin-top:.5rem">
                            Base64 mode streams images one at a time — safe for large libraries.
                            "URLs only" is faster to generate but the importer needs internet
                            access to the source server at import time.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="aaron_kr_do_export" value="⬇ Download JSON"
                       class="button button-primary button-large">
            </p>
        </form>

        <hr>
        <h2>After downloading</h2>
        <ol style="max-width:600px;line-height:1.8">
            <li>On <strong>aaron.kr / aaronkr.local</strong>: copy
                <code>aaron-kr-importer.php</code> to <code>wp-content/mu-plugins/</code></li>
            <li>Go to <strong>Tools → Import from aaron.kr JSON</strong></li>
            <li>Upload the file — run <strong>Dry Run</strong> first to preview</li>
            <li>Run the real import, verify the results table</li>
            <li><strong>Delete both files</strong> (exporter + importer) when done</li>
        </ol>
    </div>
    <?php
}

// ════════════════════════════════════════════════════════════════════════════
// STREAMING JSON — writes directly to PHP output, one post at a time
// ════════════════════════════════════════════════════════════════════════════

function aaron_kr_stream_json( string $type, bool $embed ): void {
    $jp_type     = "jetpack-{$type}";
    $jp_tag_tax  = "jetpack-{$type}-tag";
    $jp_type_tax = "jetpack-{$type}-type";

    // Fetch IDs only — keeps this query lean
    $ids = get_posts( [
        'post_type'      => $jp_type,
        'posts_per_page' => -1,
        'post_status'    => [ 'publish', 'draft', 'private', 'pending' ],
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );

    // ── JSON envelope (no closing bracket yet) ────────────────────────────────
    echo '{';
    echo '"export_version":"1.2",';
    echo '"export_date":' . wp_json_encode( date( 'c' ) ) . ',';
    echo '"source_site":' . wp_json_encode( get_bloginfo( 'url' ) ) . ',';
    echo '"source_type":' . wp_json_encode( $jp_type ) . ',';
    echo '"target_type":' . wp_json_encode( $type ) . ',';
    echo '"post_count":' . count( $ids ) . ',';
    echo '"posts":[';

    $first = true;
    foreach ( $ids as $pid ) {
        $post = get_post( $pid );
        if ( ! $post ) continue;

        if ( ! $first ) echo ',';
        $first = false;

        // ── Terms ─────────────────────────────────────────────────────────────
        $flat = function ( $tax ) use ( $pid ): array {
            $terms = wp_get_object_terms( $pid, $tax );
            if ( is_wp_error( $terms ) || empty( $terms ) ) return [];
            return array_map( fn( $t ) => [ 'name' => $t->name, 'slug' => $t->slug ], $terms );
        };

        // ── Post meta ─────────────────────────────────────────────────────────
        $skip = [ '_thumbnail_id', '_edit_lock', '_edit_last', '_wp_old_slug',
                  '_oembed_', '_encloseme', '_pingme', '_wp_attachment_', 'jetpack_' ];
        $meta = [];
        foreach ( get_post_meta( $pid ) as $k => $v ) {
            foreach ( $skip as $p ) {
                if ( strpos( $k, $p ) === 0 ) continue 2;
            }
            $meta[ $k ] = count( $v ) === 1 ? $v[0] : $v;
        }

        // ── Featured image ─────────────────────────────────────────────────────
        $tid      = get_post_thumbnail_id( $pid ) ?: null;
        $turl     = $tid ? wp_get_attachment_image_url( $tid, 'full' ) : null;
        $tfile    = $turl ? basename( (string) parse_url( $turl, PHP_URL_PATH ) ) : null;
        $talt     = $tid  ? (string) get_post_meta( $tid, '_wp_attachment_image_alt', true ) : '';
        $tb64     = null;

        if ( $tid && $embed ) {
            $encoded = aaron_kr_encode_one_image( $tid );
            $tb64    = $encoded ?? [ 'data' => null, 'mime' => null,
                                     'url' => $turl, 'source' => 'encode_failed' ];
        }

        // ── Stream this post ──────────────────────────────────────────────────
        echo wp_json_encode( [
            'post_title'              => $post->post_title,
            'post_content'            => $post->post_content,
            'post_excerpt'            => $post->post_excerpt,
            'post_status'             => $post->post_status,
            'post_date'               => $post->post_date,
            'post_date_gmt'           => $post->post_date_gmt,
            'post_name'               => $post->post_name,
            'post_type'               => $type,         // ← remapped
            'menu_order'              => (int) $post->menu_order,
            'jp_tags'                 => $flat( $jp_tag_tax ),
            'jp_type_terms'           => $flat( $jp_type_tax ),
            'std_tags'                => $flat( 'post_tag' ),
            'categories'              => $flat( 'category' ),
            'post_meta'               => $meta,
            'acf'                     => function_exists( 'get_fields' )
                                            ? ( get_fields( $pid ) ?: null )
                                            : null,
            'featured_image_url'      => $turl,
            'featured_image_filename' => $tfile,
            'featured_image_alt'      => $talt,
            'featured_image_base64'   => $tb64,
        ], JSON_UNESCAPED_UNICODE );

        // Free the image data immediately — do not accumulate in memory
        unset( $tb64, $encoded, $meta );

        // Push bytes to the browser so the download progresses
        flush();
    }

    echo ']}';
}

// ── Encode one attachment ────────────────────────────────────────────────────
function aaron_kr_encode_one_image( int $id ): ?array {
    // Local file path is fastest — no HTTP, no extra memory copy
    $path = get_attached_file( $id );
    if ( $path && file_exists( $path ) ) {
        $mime = (string) ( mime_content_type( $path ) ?: 'image/jpeg' );
        $raw  = file_get_contents( $path );
        if ( $raw !== false ) {
            return [ 'mime' => $mime, 'data' => base64_encode( $raw ), 'source' => 'local' ];
        }
    }

    // Remote fetch (Jetpack CDN or offloaded media)
    $url = wp_get_attachment_image_url( $id, 'full' );
    if ( ! $url ) return null;

    // Strip Jetpack CDN prefix: i0.wp.com/domain.com/path → https://domain.com/path
    $src = (string) preg_replace( '#^https?://i\d+\.wp\.com/#', 'https://', $url );

    foreach ( [ $src, $url ] as $try ) {
        $resp = wp_remote_get( $try, [ 'timeout' => 30, 'sslverify' => false ] );
        if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
            $body = wp_remote_retrieve_body( $resp );
            $mime = explode( ';', wp_remote_retrieve_header( $resp, 'content-type' ) )[0];
            return [ 'mime' => trim( $mime ) ?: 'image/jpeg',
                     'data' => base64_encode( $body ),
                     'source' => 'remote', 'url' => $try ];
        }
    }

    return [ 'data' => null, 'mime' => null, 'url' => $url, 'source' => 'fetch_failed' ];
}
