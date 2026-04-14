<?php
/**
 * Plugin Name: Aaron KR — Portfolio Patcher (aaron.kr)
 * Description: Reads the same JSON export file and patches existing portfolio
 *              posts with missing featured images, tags, and portfolio_type terms.
 *              Non-destructive: never overwrites data that already exists.
 *              INSTALL ON: aaron.kr / lab.aaron.kr   DELETE AFTER: patching done
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
    add_management_page(
        'Patch Portfolio Images & Tags',
        '🩹 Patch Portfolio',
        'manage_options',
        'aaron-kr-patcher',
        'aaron_kr_patcher_page'
    );
} );

function aaron_kr_patcher_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );

    @set_time_limit( 600 );
    @ini_set( 'memory_limit', '512M' );

    $result = null;

    if (
        isset( $_POST['aaron_kr_action'] ) &&
        wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'aaron_kr_patch' ) &&
        ! empty( $_FILES['patch_file']['tmp_name'] )
    ) {
        $tmp     = $_FILES['patch_file']['tmp_name'];
        $dry_run = ( $_POST['aaron_kr_action'] === 'dry_run' );

        $upload_dir = wp_upload_dir();
        $dest       = $upload_dir['basedir'] . '/aaron-kr-patch-tmp.json';

        if ( ! move_uploaded_file( $tmp, $dest ) ) {
            $result = [ 'error' => 'Could not move uploaded file. Check uploads directory permissions.' ];
        } else {
            $result = aaron_kr_stream_patch( $dest, $dry_run );
            @unlink( $dest );
        }
    }

    // ── Count current state for orientation ───────────────────────────────────
    $portfolio_total    = wp_count_posts( 'portfolio' )->publish ?? 0;
    $without_thumb      = aaron_kr_count_without_thumbnail();
    $without_tags       = aaron_kr_count_without_terms( 'post_tag' );
    $without_type_terms = aaron_kr_count_without_terms( 'portfolio_type' );
    ?>
    <div class="wrap">
        <h1>🩹 Patch Portfolio — Images &amp; Tags</h1>

        <p>Upload the same JSON export file. This tool finds each portfolio post
           by slug and patches <strong>only what is missing</strong> — it never
           overwrites a featured image or tags that already exist.</p>

        <div class="notice notice-warning inline" style="max-width:680px">
            <p><strong>Featured images are always overwritten</strong> from the export,
               regardless of whether WP thinks one is set. This fixes the
               "Could not retrieve featured image data" broken-reference problem.
               Tags and type terms are only <em>added</em> — existing terms are never removed.</p>
        </div>

        <h2 style="margin-top:1.5rem">Current status</h2>
        <table class="widefat" style="max-width:480px;margin-bottom:1.5rem">
            <tbody>
                <tr>
                    <td>Published portfolio posts</td>
                    <td><strong><?php echo (int) $portfolio_total; ?></strong></td>
                </tr>
                <tr style="background:#fff3cd">
                    <td>Without <em>any</em> featured image reference</td>
                    <td><strong><?php echo (int) $without_thumb; ?></strong>
                        <small style="color:#666">(broken refs not counted here)</small></td>
                </tr>
                <tr style="background:#fff3cd">
                    <td>Missing post tags</td>
                    <td><strong><?php echo (int) $without_tags; ?></strong></td>
                </tr>
                <tr style="background:#fff3cd">
                    <td>Missing portfolio type terms</td>
                    <td><strong><?php echo (int) $without_type_terms; ?></strong></td>
                </tr>
            </tbody>
        </table>

        <?php if ( isset( $result['error'] ) ) : ?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $result['error'] ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( $result && ! isset( $result['error'] ) ) :
            $dr = $result['dry_run'];
        ?>
            <div class="notice <?php echo $dr ? 'notice-info' : 'notice-success'; ?>">
                <p>
                    <strong><?php echo $dr ? 'Dry run complete.' : 'Patch complete.'; ?></strong>
                    &nbsp;
                    <?php echo (int) $result['patched']; ?> posts <?php echo $dr ? 'would be patched' : 'patched'; ?>,
                    <?php echo (int) $result['skipped']; ?> had nothing to update (skipped),
                    <?php echo (int) $result['not_found']; ?> not found on this site,
                    <?php echo (int) $result['image_ok']; ?> image<?php echo $result['image_ok'] !== 1 ? 's' : ''; ?> <?php echo $dr ? 'ready to overwrite' : 'overwritten'; ?>,
                    <?php echo (int) $result['image_fail']; ?> image <?php echo $result['image_fail'] === 1 ? 'issue' : 'issues'; ?>.
                </p>
            </div>

            <?php if ( ! empty( $result['log'] ) ) : ?>
                <details open style="margin:1.25rem 0">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        <?php echo $dr ? 'Preview' : 'Patch log'; ?>
                        (<?php echo count( $result['log'] ); ?> entries)
                        &nbsp;<small style="font-weight:400;color:#666">— click to collapse</small>
                    </summary>
                    <table class="widefat striped" style="margin-top:.75rem">
                        <thead><tr>
                            <th>Post</th>
                            <th>Image</th>
                            <th>Tags added</th>
                            <th>Type terms added</th>
                            <th>Status</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $result['log'] as $row ) : ?>
                            <tr>
                                <td>
                                    <?php if ( ! empty( $row['post_id'] ) ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $row['post_id'] ) ); ?>">
                                            <?php echo esc_html( $row['title'] ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html( $row['title'] ); ?>
                                    <?php endif; ?>
                                    <br><small style="color:#999"><code><?php echo esc_html( $row['slug'] ); ?></code></small>
                                </td>
                                <td style="font-size:12px"><?php echo esc_html( $row['image'] ); ?></td>
                                <td style="font-size:11px"><?php echo esc_html( implode( ', ', $row['tags_added'] ) ?: '—' ); ?></td>
                                <td style="font-size:11px"><?php echo esc_html( implode( ', ', $row['types_added'] ) ?: '—' ); ?></td>
                                <td style="font-size:12px"><?php echo esc_html( $row['status'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>

            <?php if ( $dr ) : ?>
                <div style="padding:1rem;background:#e7f5fe;border-left:4px solid #007cba;margin-top:.5rem">
                    <p>↑ Dry run only — nothing was changed. Re-upload the file
                       and click <strong>Run Real Patch</strong> to apply.</p>
                </div>
            <?php else : ?>
                <div style="padding:1rem;background:#fff3cd;border-left:4px solid #ffc107;margin-top:.5rem">
                    <p><strong>🗑 Delete this file when done:</strong>
                       <code>wp-content/mu-plugins/aaron-kr-patcher.php</code></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <hr style="margin:2rem 0">

        <form method="post" enctype="multipart/form-data" style="max-width:680px">
            <?php wp_nonce_field( 'aaron_kr_patch' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="patch_file">JSON export file</label></th>
                    <td>
                        <input type="file" name="patch_file" id="patch_file"
                               accept=".json,application/json" required>
                        <p class="description">The same export file from aaronsnowberger.com.</p>
                    </td>
                </tr>
            </table>

            <p class="submit" style="display:flex;gap:1rem;flex-wrap:wrap">
                <button type="submit" name="aaron_kr_action" value="dry_run"
                        class="button button-large">
                    🔍 Dry Run (preview only)
                </button>
                <button type="submit" name="aaron_kr_action" value="patch"
                        class="button button-primary button-large"
                        onclick="return confirm('Patch existing posts with missing images and tags?')">
                    🩹 Run Real Patch
                </button>
            </p>
        </form>
    </div>
    <?php
}

// ── Count helpers for the status table ───────────────────────────────────────
function aaron_kr_count_without_thumbnail(): int {
    global $wpdb;
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'portfolio'
           AND post_status IN ('publish','draft','private')
           AND ID NOT IN (
               SELECT post_id FROM {$wpdb->postmeta}
               WHERE meta_key = '_thumbnail_id'
           )"
    );
    return $total;
}

function aaron_kr_count_without_terms( string $taxonomy ): int {
    global $wpdb;
    $tt_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt
             WHERE tt.taxonomy = %s",
            $taxonomy
        )
    );
    if ( empty( $tt_ids ) ) return (int) ( wp_count_posts( 'portfolio' )->publish ?? 0 );

    $placeholders = implode( ',', array_fill( 0, count( $tt_ids ), '%d' ) );
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             WHERE p.post_type = 'portfolio'
               AND p.post_status IN ('publish','draft','private')
               AND p.ID NOT IN (
                   SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                   WHERE tr.term_taxonomy_id IN ($placeholders)
               )",
            ...$tt_ids
        )
    );
}

// ════════════════════════════════════════════════════════════════════════════
// STREAMING PATCHER
// Same state machine as the importer — reads one post at a time from the
// JSON file, looks up the existing WP post by slug, patches what's missing.
// ════════════════════════════════════════════════════════════════════════════

function aaron_kr_stream_patch( string $path, bool $dry_run ): array {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $patched    = 0;
    $skipped    = 0;
    $not_found  = 0;
    $image_ok   = 0;
    $image_fail = 0;
    $log        = [];

    $fp = fopen( $path, 'rb' );
    if ( ! $fp ) return [ 'error' => 'Cannot open file.' ];

    // State machine (identical structure to the importer)
    $state    = 'seeking';
    $head_buf = '';
    $buf      = '';
    $depth    = 0;
    $in_str   = false;
    $esc      = false;

    while ( ! feof( $fp ) ) {
        $chunk = fread( $fp, 65536 );
        $len   = strlen( $chunk );

        for ( $i = 0; $i < $len; $i++ ) {
            $c = $chunk[ $i ];

            if ( $state === 'seeking' ) {
                $head_buf .= $c;
                if ( strlen( $head_buf ) > 20 ) $head_buf = substr( $head_buf, -20 );
                if ( str_ends_with( $head_buf, '"posts":[' ) ) {
                    $state = 'between';
                    $head_buf = '';
                }
                continue;
            }

            if ( $state === 'between' ) {
                if ( $c === '{' ) {
                    $state  = 'in_post';
                    $buf    = '{';
                    $depth  = 1;
                    $in_str = false;
                    $esc    = false;
                }
                continue;
            }

            if ( $state === 'in_post' ) {
                $buf .= $c;

                if ( $esc ) { $esc = false; continue; }
                if ( $c === '\\' && $in_str ) { $esc = true; continue; }
                if ( $c === '"' ) { $in_str = ! $in_str; continue; }
                if ( $in_str ) continue;

                if ( $c === '{' ) {
                    $depth++;
                } elseif ( $c === '}' ) {
                    $depth--;
                    if ( $depth === 0 ) {
                        $post = json_decode( $buf, true );
                        $buf  = '';
                        $state = 'between';

                        if ( ! is_array( $post ) ) continue;

                        $row = aaron_kr_patch_one( $post, $dry_run );
                        $log[] = $row;

                        switch ( $row['status'] ) {
                            case 'patched':    $patched++;   break;
                            case 'complete':   $skipped++;   break;
                            case 'not found':  $not_found++; break;
                        }
                        if ( str_starts_with( $row['image'], '✓' ) ) $image_ok++;
                        elseif ( str_starts_with( $row['image'], '✗' ) ) $image_fail++;

                        unset( $post, $row );
                    }
                }
            }
        }
    }

    fclose( $fp );
    return compact( 'patched', 'skipped', 'not_found', 'image_ok', 'image_fail', 'log', 'dry_run' );
}

// ════════════════════════════════════════════════════════════════════════════
// PATCH ONE POST
// Finds the existing WP post by slug, then fills in only what is missing.
// ════════════════════════════════════════════════════════════════════════════

function aaron_kr_patch_one( array $item, bool $dry_run ): array {
    $slug        = sanitize_title( $item['post_name'] ?: $item['post_title'] );
    $title       = $item['post_title'] ?? '(untitled)';
    $target_type = $item['post_type']  ?? 'portfolio';

    // ── Find existing post by slug ─────────────────────────────────────────────
    // get_page_by_path works for any post type
    $post = get_page_by_path( $slug, OBJECT, $target_type );

    // Also try published posts if draft lookup missed
    if ( ! $post ) {
        $posts = get_posts( [
            'name'           => $slug,
            'post_type'      => $target_type,
            'post_status'    => 'any',
            'posts_per_page' => 1,
        ] );
        $post = $posts[0] ?? null;
    }

    if ( ! $post ) {
        return [
            'title'       => $title, 'slug' => $slug,
            'post_id'     => null,
            'image'       => '—', 'tags_added' => [], 'types_added' => [],
            'status'      => 'not found',
        ];
    }

    $pid = $post->ID;

    // ── Determine what needs patching ─────────────────────────────────────────
    // NOTE: We always overwrite the featured image regardless of whether WP
    // thinks one exists. WP can store a _thumbnail_id that points to a deleted
    // or broken attachment — has_post_thumbnail() returns true but the image
    // shows "Could not retrieve featured image data" in the editor. Always
    // re-uploading from the export source is the only reliable fix.
    $existing_tags  = wp_get_object_terms( $pid, 'post_tag',      [ 'fields' => 'names' ] );
    $existing_types = wp_get_object_terms( $pid, 'portfolio_type', [ 'fields' => 'names' ] );
    $existing_tags  = is_wp_error( $existing_tags )  ? [] : $existing_tags;
    $existing_types = is_wp_error( $existing_types ) ? [] : $existing_types;

    // Build the new tags from the export (jp_tags + std_tags, deduplicated)
    $export_tags  = array_unique( array_merge(
        array_column( $item['jp_tags']  ?? [], 'name' ),
        array_column( $item['std_tags'] ?? [], 'name' )
    ) );
    $export_types = array_column( $item['jp_type_terms'] ?? [], 'name' );

    // Only add tags/types not already on the post
    $tags_to_add  = array_values( array_diff( $export_tags,  $existing_tags ) );
    $types_to_add = array_values( array_diff( $export_types, $existing_types ) );

    // Check whether the export actually has an image for this post
    $b64         = $item['featured_image_base64'] ?? null;
    $has_export_image = ( ! empty( $b64['data'] ) && ! empty( $b64['mime'] ) )
                     || ! empty( $item['featured_image_url'] );

    // Nothing to do only when: no image in export AND no new terms to add
    if ( ! $has_export_image && empty( $tags_to_add ) && empty( $types_to_add ) ) {
        return [
            'title'       => $title, 'slug' => $slug,
            'post_id'     => $pid,
            'image'       => '— not in export', 'tags_added' => [], 'types_added' => [],
            'status'      => 'complete',
        ];
    }

    // ── Dry run: describe what would happen ───────────────────────────────────
    if ( $dry_run ) {
        return [
            'title'       => $title, 'slug' => $slug,
            'post_id'     => $pid,
            'image'       => $has_export_image ? 'would overwrite' : '— not in export',
            'tags_added'  => $tags_to_add,
            'types_added' => $types_to_add,
            'status'      => 'would patch',
        ];
    }

    // ── Apply patches ──────────────────────────────────────────────────────────

    // Featured image — always overwrite.
    // First: remove the broken _thumbnail_id reference so we start clean.
    // The old attachment record (if it exists) stays in the media library
    // but is no longer linked to this post.
    delete_post_meta( $pid, '_thumbnail_id' );

    $img_status = '— not in export';

    if ( ! empty( $b64['data'] ) && ! empty( $b64['mime'] ) ) {
        $img_status = aaron_kr_patcher_sideload_b64(
            $pid,
            $b64['data'],
            $b64['mime'],
            $item['featured_image_filename'] ?? 'image.jpg',
            $item['featured_image_alt']      ?? $title
        );
    } elseif ( ! empty( $item['featured_image_url'] ) ) {
        $img_status = aaron_kr_patcher_sideload_url(
            $pid,
            $item['featured_image_url'],
            $item['featured_image_alt'] ?? $title
        );
    }

    // Tags (append — preserves any tags already on the post)
    if ( $tags_to_add ) {
        wp_set_post_terms( $pid, $tags_to_add, 'post_tag', true );
    }

    // Portfolio type terms (append)
    if ( $types_to_add ) {
        foreach ( $types_to_add as $tn ) {
            if ( ! term_exists( $tn, 'portfolio_type' ) ) {
                wp_insert_term( $tn, 'portfolio_type' );
            }
        }
        wp_set_object_terms( $pid, $types_to_add, 'portfolio_type', true );
    }

    return [
        'title'       => $title, 'slug' => $slug,
        'post_id'     => $pid,
        'image'       => $img_status,
        'tags_added'  => $tags_to_add,
        'types_added' => $types_to_add,
        'status'      => 'patched',
    ];
}

// ════════════════════════════════════════════════════════════════════════════
// IMAGE SIDELOADERS (same as importer)
// ════════════════════════════════════════════════════════════════════════════

function aaron_kr_patcher_sideload_b64(
    int $pid, string $b64, string $mime, string $filename, string $alt
): string {
    $tmp = wp_tempnam( $filename );
    if ( file_put_contents( $tmp, base64_decode( $b64 ) ) === false ) {
        @unlink( $tmp );
        return '✗ could not write temp file';
    }
    $file   = [ 'name' => $filename, 'tmp_name' => $tmp, 'type' => $mime,
                'error' => UPLOAD_ERR_OK, 'size' => filesize( $tmp ) ];
    $att_id = media_handle_sideload( $file, $pid, $alt );
    @unlink( $tmp );

    if ( is_wp_error( $att_id ) ) return '✗ ' . $att_id->get_error_message();
    if ( $alt ) update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
    set_post_thumbnail( $pid, $att_id );
    return '✓ uploaded';
}

function aaron_kr_patcher_sideload_url( int $pid, string $url, string $alt ): string {
    $src = (string) preg_replace( '#^https?://i\d+\.wp\.com/#', 'https://', $url );
    foreach ( [ $src, $url ] as $try ) {
        $tmp = download_url( $try, 30 );
        if ( is_wp_error( $tmp ) ) continue;

        $file   = [ 'name' => basename( (string) parse_url( $try, PHP_URL_PATH ) ),
                    'tmp_name' => $tmp ];
        $att_id = media_handle_sideload( $file, $pid, $alt );
        @unlink( $tmp );

        if ( is_wp_error( $att_id ) ) continue;
        if ( $alt ) update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
        set_post_thumbnail( $pid, $att_id );
        return '✓ from URL';
    }
    return '✗ URL fetch failed';
}
