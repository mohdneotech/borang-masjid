<?php
/**
 * Plugin Name:       Borang Program Masjid
 * Plugin URI:        https://github.com/mohdneotech/borang-masjid
 * Description:       Pembina borang pendaftaran program masjid / surau (cth. Khatan Perdana, kursus, sukarelawan). AJK bina borang sendiri dengan templat, tetapkan kuota, tarikh tutup dan semakan umur, terima pendaftaran dalam talian, urus status, cetak senarai kehadiran dan eksport CSV.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mohd Nordin Hussain
 * Author URI:        https://mohdnordin.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       borang-masjid
 */

defined( 'ABSPATH' ) || exit;

// Another copy (e.g. the older site-specific "Perepat Borang" build) is already loaded — stand down instead of fataling.
if ( defined( 'PB_VER' ) ) {
	register_activation_hook( __FILE__, function () {
		wp_die( 'Sila nyahaktifkan plugin lama (Perepat Borang) dahulu, kemudian aktifkan plugin ini. Semua borang dan pendaftaran akan dikekalkan.', 'Plugin lama masih aktif', [ 'back_link' => true ] );
	} );
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>Borang Program Masjid:</strong> satu lagi plugin borang (fungsi <code>pb_*</code>) sedang aktif. Nyahaktifkan plugin lama dahulu — semua borang dan pendaftaran akan dikekalkan.</p></div>';
	} );
	return;
}

define( 'PB_VER', '1.0.0' );
define( 'PB_DB_VER', '1' );
define( 'PB_FILE', __FILE__ );
define( 'PB_DIR', plugin_dir_path( __FILE__ ) );
define( 'PB_URL', plugin_dir_url( __FILE__ ) );
define( 'PB_CAP', 'pb_urus' );
define( 'PB_CPT', 'pb_borang' );
define( 'PB_REPO', 'mohdneotech/borang-masjid' );
define( 'PB_GENERIC', true ); // public build: theme-independent CSS, settings screen

require_once PB_DIR . 'includes/helpers.php';
require_once PB_DIR . 'includes/settings.php';
require_once PB_DIR . 'includes/templates.php';
require_once PB_DIR . 'includes/render.php';
require_once PB_DIR . 'includes/public.php';
require_once PB_DIR . 'includes/hero.php';
require_once PB_DIR . 'includes/class-github-updater.php';
if ( is_admin() ) {
	require_once PB_DIR . 'includes/admin-builder.php';
	require_once PB_DIR . 'includes/admin-entries.php';
	require_once PB_DIR . 'includes/admin-settings.php';
}
if ( is_admin() || wp_doing_cron() ) {
	new PB_GitHub_Updater( __FILE__, PB_REPO, PB_VER );
}

register_activation_hook( __FILE__, 'pb_activate' );
function pb_activate() {
	pb_install_schema();
	pb_private_dir();
	pb_grant_caps();
	pb_register_cpt();
	if ( false === get_option( 'pb_options' ) ) { add_option( 'pb_options', pb_default_options(), '', false ); }
	flush_rewrite_rules();
	set_transient( 'pb_welcome', 1, HOUR_IN_SECONDS );
}

function pb_grant_caps() {
	foreach ( (array) apply_filters( 'pb_roles', [ 'administrator', 'editor', 'pk_ajk' ] ) as $r ) {
		if ( $role = get_role( $r ) ) { $role->add_cap( PB_CAP ); }
	}
}

function pb_install_schema() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . pb_t() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		borang_id bigint(20) unsigned NOT NULL,
		no_rujukan varchar(30) NOT NULL DEFAULT '',
		kunci varchar(100) NOT NULL DEFAULT '',
		data longtext NOT NULL,
		fail longtext NULL,
		status varchar(20) NOT NULL DEFAULT 'baru',
		catatan text NULL,
		sumber varchar(10) NOT NULL DEFAULT 'online',
		ip_hash varchar(64) NOT NULL DEFAULT '',
		dicipta datetime NOT NULL,
		dikemaskini datetime NULL,
		oleh bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY borang_status (borang_id,status),
		KEY borang_kunci (borang_id,kunci)
	) $c;" );
	update_option( 'pb_db_ver', PB_DB_VER, false );
}

add_action( 'plugins_loaded', function () {
	if ( get_option( 'pb_db_ver' ) !== PB_DB_VER ) { pb_install_schema(); pb_grant_caps(); }
} );

add_action( 'init', 'pb_register_cpt' );
function pb_register_cpt() {
	register_post_type( PB_CPT, [
		'labels'          => [
			'name'               => 'Borang Program',
			'singular_name'      => 'Borang',
			'menu_name'          => 'Borang Program',
			'add_new'            => '+ Bina Borang Baharu',
			'add_new_item'       => 'Bina Borang Baharu',
			'edit_item'          => 'Sunting Borang',
			'all_items'          => 'Semua Borang',
			'search_items'       => 'Cari borang',
			'not_found'          => 'Tiada borang lagi. Klik "+ Bina Borang Baharu".',
			'not_found_in_trash' => 'Tiada borang dalam tong sampah.',
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => false,
		'menu_position'   => 26,
		'menu_icon'       => 'dashicons-feedback',
		'supports'        => [ 'title', 'editor', 'revisions' ],
		'capability_type' => 'pb_borang',
		'map_meta_cap'    => false,
		'capabilities'    => array_fill_keys( [ 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'edit_published_posts', 'create_posts', 'delete_private_posts', 'edit_private_posts' ], PB_CAP ),
	] );
}

/* Deleting a form permanently removes its registrations and their private files. */
add_action( 'before_delete_post', function ( $id ) {
	if ( get_post_type( $id ) !== PB_CPT ) { return; }
	global $wpdb;
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT fail FROM ' . pb_t() . ' WHERE borang_id=%d', $id ) ) as $f ) {
		foreach ( (array) json_decode( (string) $f, true ) as $rel ) { pb_delete_private( $rel ); }
	}
	$wpdb->delete( pb_t(), [ 'borang_id' => (int) $id ] );
} );
