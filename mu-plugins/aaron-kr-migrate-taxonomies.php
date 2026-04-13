<?php
/**
 * Plugin Name: Aaron KR — Jetpack Taxonomy Migration (ONE-TIME USE)
 * Description: Migrates jetpack-portfolio-tag and jetpack-portfolio-type terms
 *              to post_tag and portfolio_type on portfolio posts.
 *              DELETE THIS FILE after running.
 *
 * INSTALL:  wp-content/mu-plugins/aaron-kr-migrate-taxonomies.php
 * RUN:      Visit /wp-admin/ → Tools → Migrate Jetpack Taxonomies
 * DELETE:   Remove this file immediately after migration completes.
 */

defined( 'ABSPATH' ) || exit;

// Only register the admin page — no code runs until you click the button
add_action( 'admin_menu', function () {
    add_management_page(
        'Migrate Jetpack Taxonomies',
        '⚡ Migrate Jetpack Taxes',
        'manage_options',
        'aaron-kr-migrate-taxes',
        'aaron_kr_migration_page'
    );
} );

function aaron_kr_migration_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }

    $ran    = false;
    $result = [];

    // ── Run migration when button is clicked ──────────────────────────────────
    if (
        isset( $_POST['aaron_kr_run_migration'] ) &&
        wp_verify_nonce( $_POST['_wpnonce'], 'aaron_kr_migrate' )
    ) {
        $ran    = true;
        $result = aaron_kr_run_taxonomy_migration();
    }

    // ── Preview: what's waiting to be migrated ────────────────────────────────
    $preview = aaron_kr_migration_preview();
    ?>
    <div class="wrap">
        <h1>⚡ Migrate Jetpack Portfolio Taxonomies</h1>
        <p>This tool copies taxonomy terms from the old Jetpack-specific taxonomies
           (<code>jetpack-portfolio-tag</code>, <code>jetpack-portfolio-type</code>)
           to your new custom ones (<code>post_tag</code>, <code>portfolio_type</code>)
           on all <code>portfolio</code> posts. It is <strong>additive</strong> —
           existing tags are never removed.</p>

        <?php if ( $ran ) : ?>
            <div class="notice notice-success">
                <p><strong>Migration complete.</strong>
                   <?php echo esc_html( $result['posts_processed'] ); ?> posts processed.</p>
            </div>
            <h2>Results</h2>
            <table class="widefat" style="max-width:780px">
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>JP Tags → post_tag</th>
                        <th>JP Types → portfolio_type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $result['log'] as $row ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>">
                            <?php echo esc_html( $row['title'] ); ?></a><br>
                            <small>ID <?php echo (int) $row['id']; ?></small></td>
                        <td><?php echo esc_html( implode( ', ', $row['tags_added'] ) ?: '—' ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $row['types_added'] ) ?: '—' ); ?></td>
                        <td><?php echo $row['changed'] ? '✅ Updated' : '⏭ No change'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:1.5rem;padding:1rem;background:#fff3cd;border-left:4px solid #ffc107">
                <strong>🗑 Delete this file</strong> now:
                <code>wp-content/mu-plugins/aaron-kr-migrate-taxonomies.php</code>
            </p>
        <?php else : ?>
            <h2>What will be migrated</h2>
            <?php if ( empty( $preview['posts'] ) ) : ?>
                <p>No portfolio posts found with Jetpack taxonomy terms. Nothing to migrate.</p>
            <?php else : ?>
                <table class="widefat" style="max-width:780px">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>jetpack-portfolio-tag (→ post_tag)</th>
                            <th>jetpack-portfolio-type (→ portfolio_type)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $preview['posts'] as $row ) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>">
                                <?php echo esc_html( $row['title'] ); ?></a></td>
                            <td><?php echo esc_html( implode( ', ', $row['jp_tags'] ) ?: '—' ); ?></td>
                            <td><?php echo esc_html( implode( ', ', $row['jp_types'] ) ?: '—' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:1rem">
                    <strong><?php echo count( $preview['posts'] ); ?></strong> posts have terms to migrate.
                    <strong><?php echo $preview['already_tagged']; ?></strong> already have tags on <code>post_tag</code>
                    (those will be kept — migration is additive).
                </p>

                <form method="post" style="margin-top:1.5rem">
                    <?php wp_nonce_field( 'aaron_kr_migrate' ); ?>
                    <input type="hidden" name="aaron_kr_run_migration" value="1">
                    <button type="submit" class="button button-primary button-hero"
                        onclick="return confirm('Run migration? This is safe to run multiple times — terms already assigned are skipped.')">
                        ▶ Run Migration
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ── Preview: read only, no writes ─────────────────────────────────────────────
function aaron_kr_migration_preview(): array {
    $posts = get_posts( [
        'post_type'      => 'portfolio',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ] );

    $rows           = [];
    $already_tagged = 0;

    foreach ( $posts as $id ) {
        $jp_tags  = wp_get_object_terms( $id, 'jetpack-portfolio-tag',  [ 'fields' => 'names' ] );
        $jp_types = wp_get_object_terms( $id, 'jetpack-portfolio-type', [ 'fields' => 'names' ] );
        $cur_tags = wp_get_object_terms( $id, 'post_tag', [ 'fields' => 'names' ] );

        if ( ! empty( $jp_tags ) || ! empty( $jp_types ) ) {
            $rows[] = [
                'id'       => $id,
                'title'    => get_the_title( $id ),
                'jp_tags'  => is_wp_error( $jp_tags )  ? [] : $jp_tags,
                'jp_types' => is_wp_error( $jp_types ) ? [] : $jp_types,
            ];
        }

        if ( ! empty( $cur_tags ) && ! is_wp_error( $cur_tags ) ) {
            $already_tagged++;
        }
    }

    return [ 'posts' => $rows, 'already_tagged' => $already_tagged ];
}

// ── Migration: writes terms, returns detailed log ─────────────────────────────
function aaron_kr_run_taxonomy_migration(): array {
    $posts = get_posts( [
        'post_type'      => 'portfolio',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ] );

    $log = [];

    foreach ( $posts as $id ) {
        $jp_tags  = wp_get_object_terms( $id, 'jetpack-portfolio-tag',  [ 'fields' => 'names' ] );
        $jp_types = wp_get_object_terms( $id, 'jetpack-portfolio-type', [ 'fields' => 'names' ] );

        $tags_added  = [];
        $types_added = [];

        // ── jetpack-portfolio-tag → post_tag ──────────────────────────────────
        if ( ! empty( $jp_tags ) && ! is_wp_error( $jp_tags ) ) {
            // wp_set_post_terms with append=true preserves existing tags
            $result = wp_set_post_terms( $id, $jp_tags, 'post_tag', true );
            if ( ! is_wp_error( $result ) ) {
                $tags_added = $jp_tags;
            }
        }

        // ── jetpack-portfolio-type → portfolio_type ───────────────────────────
        if ( ! empty( $jp_types ) && ! is_wp_error( $jp_types ) ) {
            // Ensure terms exist in portfolio_type taxonomy first
            foreach ( $jp_types as $term_name ) {
                if ( ! term_exists( $term_name, 'portfolio_type' ) ) {
                    wp_insert_term( $term_name, 'portfolio_type' );
                }
            }
            $result = wp_set_object_terms( $id, $jp_types, 'portfolio_type', true );
            if ( ! is_wp_error( $result ) ) {
                $types_added = $jp_types;
            }
        }

        $log[] = [
            'id'          => $id,
            'title'       => get_the_title( $id ),
            'tags_added'  => $tags_added,
            'types_added' => $types_added,
            'changed'     => ! empty( $tags_added ) || ! empty( $types_added ),
        ];
    }

    return [
        'posts_processed' => count( $posts ),
        'log'             => $log,
    ];
}
