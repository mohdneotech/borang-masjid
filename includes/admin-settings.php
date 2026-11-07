<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=' . PB_CPT, 'Tetapan Borang', 'Tetapan', 'manage_options', 'pb-tetapan', 'pb_page_options' );
}, 30 );

add_action( 'admin_notices', function () {
	if ( ! get_transient( 'pb_welcome' ) || ! current_user_can( PB_CAP ) ) { return; }
	delete_transient( 'pb_welcome' );
	echo '<div class="notice notice-success is-dismissible"><p><strong>Borang Program Masjid diaktifkan.</strong> Mula dengan <a href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-tetapan' ) ) . '">Tetapan</a> (nama masjid &amp; warna), kemudian <a href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-baharu' ) ) . '">bina borang pertama</a>.</p></div>';
} );

add_filter( 'plugin_action_links_' . plugin_basename( PB_FILE ), function ( $l ) {
	array_unshift( $l, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-tetapan' ) ) . '">Tetapan</a>' );
	return $l;
} );

/** Is uploads/pb-private reachable from the web? (nginx ignores .htaccess). Cached 12 h. */
function pb_private_exposed() {
	$c = get_transient( 'pb_priv_check' );
	if ( false !== $c ) { return 'yes' === $c; }
	pb_private_dir();
	$res = wp_remote_get( trailingslashit( wp_upload_dir()['baseurl'] ) . 'pb-private/index.php', [ 'timeout' => 5, 'redirection' => 0, 'sslverify' => false ] );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$exp  = 200 === $code;
	set_transient( 'pb_priv_check', $exp ? 'yes' : 'no', 12 * HOUR_IN_SECONDS );
	return $exp;
}

function pb_page_options() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Tiada kebenaran.' ); }
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && check_admin_referer( 'pb_options' ) ) {
		$in = isset( $_POST['pbo'] ) && is_array( $_POST['pbo'] ) ? wp_unslash( $_POST['pbo'] ) : [];
		update_option( 'pb_options', [
			'nama_masjid' => mb_substr( sanitize_text_field( (string) ( $in['nama_masjid'] ?? '' ) ), 0, 150 ),
			'warna'       => sanitize_hex_color( (string) ( $in['warna'] ?? '' ) ) ?: '#527c3a',
			'warna2'      => sanitize_hex_color( (string) ( $in['warna2'] ?? '' ) ) ?: '#8dc642',
			'fontawesome' => in_array( $in['fontawesome'] ?? '', [ 'auto', 'cdn', 'off' ], true ) ? $in['fontawesome'] : 'auto',
			'ip'          => in_array( $in['ip'] ?? '', [ 'remote', 'cloudflare', 'xff' ], true ) ? $in['ip'] : 'remote',
			'padam_data'  => empty( $in['padam_data'] ) ? 0 : 1,
		], false );
		delete_transient( 'pb_priv_check' );
		echo '<div class="notice notice-success is-dismissible"><p>Tetapan disimpan.</p></div>';
	}
	$o   = pb_options();
	$sel = fn( $k, $opts ) => implode( '', array_map( fn( $v, $l ) => '<option value="' . esc_attr( $v ) . '"' . selected( $o[ $k ], $v, false ) . '>' . esc_html( $l ) . '</option>', array_keys( $opts ), $opts ) );

	echo '<div class="wrap pb-wrap"><h1><i class="fa-solid fa-sliders"></i> Tetapan Borang Program</h1>';
	if ( pb_private_exposed() ) {
		echo '<div class="notice notice-error"><p><strong>Amaran:</strong> folder fail muat naik <code>uploads/pb-private/</code> boleh dibuka dari internet. Pelayan nginx tidak membaca <code>.htaccess</code> — tambah peraturan ini dan muat semula nginx:</p><pre>location ^~ /wp-content/uploads/pb-private/ { deny all; return 403; }</pre></div>';
	}
	echo '<form method="post">' . wp_nonce_field( 'pb_options', '_wpnonce', true, false ) . '<table class="form-table" role="presentation">'
		. '<tr><th><label for="pbo-n">Nama masjid / surau</label></th><td><input id="pbo-n" class="regular-text" name="pbo[nama_masjid]" value="' . esc_attr( $o['nama_masjid'] ) . '" placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '"><p class="description">Digunakan dalam templat (cth. "Ahli kariah <em>Masjid …</em>?", tempat program) dan cetakan. Kosong = nama laman web.</p></td></tr>'
		. '<tr><th>Warna</th><td><label>Utama <input type="color" name="pbo[warna]" value="' . esc_attr( $o['warna'] ) . '"></label> &nbsp; <label>Aksen <input type="color" name="pbo[warna2]" value="' . esc_attr( $o['warna2'] ) . '"></label><p class="description">Butang, ikon dan bar kuota pada borang awam.</p></td></tr>'
		. '<tr><th><label for="pbo-fa">Ikon Font Awesome</label></th><td><select id="pbo-fa" name="pbo[fontawesome]">' . $sel( 'fontawesome', [ 'auto' => 'Automatik (muat jika tema belum ada)', 'cdn' => 'Sentiasa muat dari jsDelivr', 'off' => 'Jangan muat' ] ) . '</select></td></tr>'
		. '<tr><th><label for="pbo-ip">Sumber alamat IP</label></th><td><select id="pbo-ip" name="pbo[ip]">' . $sel( 'ip', [ 'remote' => 'Terus (REMOTE_ADDR) — lalai', 'cloudflare' => 'Cloudflare (CF-Connecting-IP)', 'xff' => 'Proksi lain (X-Forwarded-For)' ] ) . '</select><p class="description">Untuk had cubaan penghantaran. Pilih Cloudflare / proksi hanya jika pelayan memang di belakangnya.</p></td></tr>'
		. '<tr><th>Nyahpasang</th><td><label><input type="checkbox" name="pbo[padam_data]" value="1"' . checked( $o['padam_data'], 1, false ) . '> Padam semua borang, pendaftaran dan fail muat naik apabila plugin <strong>dipadam</strong></label><p class="description">Nyahaktif sahaja tidak memadam apa-apa.</p></td></tr>'
		. '</table><p><button class="button button-primary">Simpan tetapan</button></p></form></div>';
}
