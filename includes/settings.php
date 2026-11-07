<?php
defined( 'ABSPATH' ) || exit;

/* Site-wide options (Borang Program → Tetapan). Per-form settings live in post meta (_pb_settings). */

const PB_FA_URL = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css';
const PB_FA_SRI = 'sha384-PPIZEGYM1v8zp5Py7UjFb79S58UeqCL9pYVnVPURKEqvioPROaVAJKKLzvH2rDnI';

function pb_default_options() {
	return [
		'nama_masjid' => '',        // empty = site title
		'warna'       => '#527c3a', // primary
		'warna2'      => '#8dc642', // accent
		'fontawesome' => 'auto',    // auto | cdn | off
		'ip'          => 'remote',  // remote | cloudflare | xff
		'padam_data'  => 0,         // remove tables + files when the plugin is deleted
	];
}
function pb_options() { return wp_parse_args( (array) get_option( 'pb_options', [] ), pb_default_options() ); }
function pb_opt( $k ) { $o = pb_options(); return $o[ $k ] ?? null; }
function pb_masjid() { return pb_opt( 'nama_masjid' ) ?: get_bloginfo( 'name' ); }

/** Colour variables for every .pb-ui block (front end + admin manual-entry forms). */
function pb_color_css() {
	$c  = sanitize_hex_color( pb_opt( 'warna' ) ) ?: '#527c3a';
	$c2 = sanitize_hex_color( pb_opt( 'warna2' ) ) ?: '#8dc642';
	return '.pb-ui,.pb-slide{--pb-c:' . $c . ';--pb-leaf:' . $c2 . ';}';
}

/** Does the theme (or another plugin) already load Font Awesome? */
function pb_theme_has_fa() {
	$st = wp_styles();
	foreach ( array_merge( $st->queue, $st->done ) as $h ) {
		if ( 'pb-fontawesome' === $h || empty( $st->registered[ $h ] ) ) { continue; }
		if ( preg_match( '/font-?awesome/i', $h . ' ' . (string) $st->registered[ $h ]->src ) ) { return true; }
	}
	return false;
}
function pb_maybe_fa() {
	$fa = pb_opt( 'fontawesome' );
	if ( 'cdn' === $fa || ( 'auto' === $fa && ! pb_theme_has_fa() ) ) { wp_enqueue_style( 'pb-fontawesome' ); }
}
add_filter( 'style_loader_tag', function ( $tag, $handle ) {
	return 'pb-fontawesome' === $handle ? str_replace( ' href=', ' integrity="' . PB_FA_SRI . '" crossorigin="anonymous" href=', $tag ) : $tag;
}, 10, 2 );
