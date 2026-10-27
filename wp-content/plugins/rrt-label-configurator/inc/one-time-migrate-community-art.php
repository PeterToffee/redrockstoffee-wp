<?php
/**
 * ONE-TIME MIGRATION — REMOVE AFTER USE.
 *
 * Pete, 2026-10-27: "we don't have the images/community art in the library
 * on live, we had it on local" — the 6 images donated during this
 * session's local testing only ever became Media Library rows + uploaded
 * files on LOCAL's own database; the file-based deploy pipeline has no
 * way to see or move that (it only ever moves committed plugin files,
 * never WordPress database content or user uploads — same root cause as
 * the /community-standards/ page and the co-branding shortcode earlier
 * this session).
 *
 * This bridges that one gap, once: it imports the same 6 already-vetted
 * files (exported from local, uploaded here to a temp folder over SFTP)
 * into LIVE's own Media Library, tagged exactly like a real donation
 * (rrt_label_donate_to_community_library() in rrt-label-configurator.php)
 * so rrt_label_community_art() picks them up the same way.
 *
 * Gated to a logged-in administrator + a nonce (admin_init runs for every
 * admin page load, so both checks matter — this must never be reachable
 * by a plain visitor). Self-disables via the rrt_community_art_migrated
 * option so it can't double-import if the link is clicked twice.
 *
 * DELETE THIS FILE (and its require_once in rrt-label-configurator.php)
 * once Pete confirms the images show up in the live ART LIBRARY picker.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'rrt_community_art_migrated' ) ) {
		return;
	}
	$url = wp_nonce_url( admin_url( 'index.php?rrt_migrate_community_art=1' ), 'rrt_migrate_community_art' );
	echo '<div class="notice notice-warning"><p><strong>RRT Label Configurator:</strong> one-time import of 6 donated Community Art images is ready. <a href="' . esc_url( $url ) . '" class="button button-primary">Import them now</a></p></div>';
} );

add_action( 'admin_init', function () {
	if ( ! isset( $_GET['rrt_migrate_community_art'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'rrt_migrate_community_art' );

	if ( get_option( 'rrt_community_art_migrated' ) ) {
		wp_die( 'Already imported — nothing to do. <a href="' . esc_url( admin_url() ) . '">Back to dashboard</a>' );
	}

	$items = array(
		array( 'file' => 'community-art-1-scaled.jpg', 'title' => 'community-art-1', 'donor_name' => 'Red Rocks Toffee Company', 'donor_email' => '' ),
		array( 'file' => 'community-art-2-scaled.jpg', 'title' => 'community-art-2', 'donor_name' => 'Red Rocks Toffee Company', 'donor_email' => '' ),
		array( 'file' => 'community-art-3.jpg',         'title' => 'community-art-3', 'donor_name' => 'Red Rocks Toffee Company', 'donor_email' => '' ),
		array( 'file' => 'community-art-4-scaled.jpg', 'title' => 'community-art-4', 'donor_name' => 'Red Rocks Toffee Company', 'donor_email' => '' ),
		array( 'file' => 'community-art-5-scaled.jpg', 'title' => 'community-art-5', 'donor_name' => 'Red Rocks Toffee Company', 'donor_email' => '' ),
		array( 'file' => 'community-art-6-scaled.jpg', 'title' => 'community-art-6', 'donor_name' => 'Red Rocks Toffee Company', 'donor_email' => '' ),
	);

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload_dir = wp_upload_dir();
	$src_dir    = trailingslashit( $upload_dir['basedir'] ) . 'rrt-migration-tmp';

	$done = array();
	$skipped = array();
	foreach ( $items as $item ) {
		$src = trailingslashit( $src_dir ) . $item['file'];
		if ( ! file_exists( $src ) ) {
			$skipped[] = $item['file'] . ' (not found in ' . $src_dir . ')';
			continue;
		}

		$info = getimagesize( $src );
		$ext  = isset( $info['mime'] ) ? array_search( $info['mime'], array( 'png' => 'image/png', 'jpg' => 'image/jpeg' ), true ) : false;
		if ( ! $ext ) {
			$skipped[] = $item['file'] . ' (not a recognizable image)';
			continue;
		}

		$dest_dir = trailingslashit( $upload_dir['path'] );
		$dest     = $dest_dir . wp_unique_filename( $dest_dir, basename( $item['file'] ) );
		if ( ! copy( $src, $dest ) ) {
			$skipped[] = $item['file'] . ' (copy into uploads failed)';
			continue;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $info['mime'],
				'post_title'     => $item['title'],
				'post_status'    => 'inherit',
			),
			$dest
		);
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			$skipped[] = $item['file'] . ' (wp_insert_attachment failed)';
			continue;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $dest ) );
		update_post_meta( $attachment_id, '_rrt_community_art', '1' );
		update_post_meta( $attachment_id, '_rrt_donor_name', $item['donor_name'] );
		update_post_meta( $attachment_id, '_rrt_donor_email', $item['donor_email'] );

		$done[] = $item['file'] . ' -> attachment #' . $attachment_id;
	}

	update_option( 'rrt_community_art_migrated', 1 );

	$msg = '<h1>Community Art import</h1><p><strong>Imported:</strong></p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $done ) ) . '</li></ul>';
	if ( $skipped ) {
		$msg .= '<p><strong>Skipped:</strong></p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $skipped ) ) . '</li></ul>';
	}
	$msg .= '<p><a href="' . esc_url( admin_url() ) . '">Back to dashboard</a></p>';
	wp_die( $msg, 'Community Art import', array( 'response' => 200 ) );
} );
