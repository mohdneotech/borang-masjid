<?php
/**
 * Runs when the plugin is DELETED from wp-admin (not on deactivate).
 * Forms and registrations are only removed if "Padam semua …" was ticked in Borang Program → Tetapan.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$o = get_option( 'pb_options' );

delete_site_transient( 'pb_gh_release' );
delete_transient( 'pb_priv_check' );

if ( empty( $o['padam_data'] ) ) {
	return; // keep everything — reinstalling the plugin picks up where it left off
}

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pb_entri' ); // phpcs:ignore WordPress.DB.PreparedSQL
foreach ( get_posts( [ 'post_type' => 'pb_borang', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( [ 'pb_options', 'pb_db_ver' ] as $k ) { delete_option( $k ); }
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_pb\_%' OR option_name LIKE '\_transient\_timeout\_pb\_%'" );

// Private uploads folder
$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'pb-private';
if ( is_dir( $dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
	@rmdir( $dir );
}

foreach ( wp_roles()->role_objects as $role ) { $role->remove_cap( 'pb_urus' ); }
