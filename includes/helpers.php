<?php
defined( 'ABSPATH' ) || exit;

function pb_t() { global $wpdb; return $wpdb->prefix . 'pb_entri'; }

/* ------------------------------------------------------------------ field types */

function pb_field_types() {
	return [
		'heading'   => [ 'Tajuk Bahagian', 'fa-heading', false ],
		'paragraph' => [ 'Teks / Nota', 'fa-align-left', false ],
		'text'      => [ 'Teks Pendek', 'fa-font', true ],
		'textarea'  => [ 'Teks Panjang', 'fa-paragraph', true ],
		'ic'        => [ 'No. KP / MyKid', 'fa-id-card', true ],
		'tel'       => [ 'No. Telefon', 'fa-phone', true ],
		'email'     => [ 'E-mel', 'fa-envelope', true ],
		'number'    => [ 'Nombor', 'fa-hashtag', true ],
		'date'      => [ 'Tarikh', 'fa-calendar-day', true ],
		'select'    => [ 'Senarai Pilihan (dropdown)', 'fa-caret-square-down', true ],
		'radio'     => [ 'Pilih Satu (radio)', 'fa-dot-circle', true ],
		'checkbox'  => [ 'Pilih Beberapa (kotak semak)', 'fa-check-square', true ],
		'file'      => [ 'Muat Naik Fail', 'fa-paperclip', true ],
		'consent'   => [ 'Akuan / Persetujuan', 'fa-signature', true ],
	];
}
/** Does this type collect a value? */
function pb_is_input( $type ) { $t = pb_field_types(); return ! empty( $t[ $type ][2] ); }

function pb_statuses() {
	return [
		'baru'     => [ 'Baharu', 'primary' ],
		'sah'      => [ 'Disahkan', 'success' ],
		'menunggu' => [ 'Senarai Menunggu', 'warning' ],
		'hadir'    => [ 'Hadir', 'info' ],
		'batal'    => [ 'Batal', 'secondary' ],
	];
}
function pb_status_label( $s ) { $a = pb_statuses(); return $a[ $s ][0] ?? $s; }
function pb_status_badge( $s ) {
	$a = pb_statuses();
	return '<span class="pb-badge pb-badge-' . esc_attr( $a[ $s ][1] ?? 'secondary' ) . '">' . esc_html( $a[ $s ][0] ?? $s ) . '</span>';
}

/* ------------------------------------------------------------------ form config */

function pb_default_settings() {
	return [
		'tarikh_program' => '',
		'masa'           => '',
		'tempat'         => '',
		'yuran'          => '',
		'hubungi'        => '',
		'buka'           => '',   // Y-m-d\TH:i (site time) — empty = open now
		'tutup'          => '',   // Y-m-d\TH:i (site time) — empty = no closing date
		'kuota'          => 0,
		'penuh'          => 'tutup', // tutup | menunggu
		'tunjuk_baki'    => 1,
		'prefix'         => 'REG',
		'unik'           => '',   // field id that must be unique
		'mesej'          => 'Terima kasih. Pendaftaran anda telah diterima. AJK akan menghubungi anda untuk pengesahan.',
		'butang'         => 'Hantar Pendaftaran',
		'halaman'        => 0,    // page id that shows this form
		'slaid'          => 0,    // show as a slide in the front-page hero while open
		'slaid_teks'     => '',   // optional slide tagline (defaults to the page excerpt)
	];
}
function pb_settings( $id ) {
	$s = get_post_meta( $id, '_pb_settings', true );
	return wp_parse_args( is_array( $s ) ? $s : [], pb_default_settings() );
}
function pb_fields( $id ) {
	$f = json_decode( (string) get_post_meta( $id, '_pb_fields', true ), true );
	return is_array( $f ) ? $f : [];
}
function pb_input_fields( $id ) { return array_values( array_filter( pb_fields( $id ), fn( $f ) => pb_is_input( $f['type'] ?? '' ) ) ); }

/** Sanitise a field list coming from the builder (or a template). */
function pb_sanitize_fields( $raw ) {
	$types = pb_field_types();
	$out   = [];
	$seen  = [];
	foreach ( (array) $raw as $f ) {
		if ( ! is_array( $f ) ) { continue; }
		$type = sanitize_key( $f['type'] ?? '' );
		if ( ! isset( $types[ $type ] ) ) { continue; }
		$id = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $f['id'] ?? '' ) ) );
		if ( ! $id || isset( $seen[ $id ] ) ) { $id = 'f_' . strtolower( wp_generate_password( 6, false, false ) ); }
		$seen[ $id ] = 1;
		$opts = [];
		if ( in_array( $type, [ 'select', 'radio', 'checkbox' ], true ) ) {
			$src = is_array( $f['options'] ?? null ) ? $f['options'] : preg_split( '/\r\n|\r|\n/', (string) ( $f['options'] ?? '' ) );
			foreach ( $src as $o ) { $o = mb_substr( trim( sanitize_text_field( (string) $o ) ), 0, 150 ); if ( '' !== $o && ! in_array( $o, $opts, true ) ) { $opts[] = $o; } }
			$opts = array_slice( $opts, 0, 60 );
		}
		$label = 'paragraph' === $type ? mb_substr( wp_kses( (string) ( $f['label'] ?? '' ), pb_kses_text() ), 0, 3000 ) : mb_substr( sanitize_text_field( (string) ( $f['label'] ?? '' ) ), 0, 'consent' === $type ? 1000 : 200 );
		$out[] = [
			'id'          => $id,
			'type'        => $type,
			'label'       => $label,
			'help'        => mb_substr( sanitize_text_field( (string) ( $f['help'] ?? '' ) ), 0, 300 ),
			'placeholder' => mb_substr( sanitize_text_field( (string) ( $f['placeholder'] ?? '' ) ), 0, 120 ),
			'required'    => ! empty( $f['required'] ) && pb_is_input( $type ) ? 1 : 0,
			'width'       => ( $f['width'] ?? '' ) === 'half' ? 'half' : 'full',
			'options'     => $opts,
			'min'         => mb_substr( sanitize_text_field( (string) ( $f['min'] ?? '' ) ), 0, 20 ),
			'max'         => mb_substr( sanitize_text_field( (string) ( $f['max'] ?? '' ) ), 0, 20 ),
			'list'        => ! empty( $f['list'] ) && pb_is_input( $type ) ? 1 : 0,
			'bila'        => preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $f['bila'] ?? '' ) ) ),
			'bila_nilai'  => mb_substr( sanitize_text_field( (string) ( $f['bila_nilai'] ?? '' ) ), 0, 150 ),
		];
		if ( count( $out ) >= 80 ) { break; }
	}
	return $out;
}
/**
 * Conditional field: shown / required only when field `bila` has value `bila_nilai`.
 * @param array $f   field
 * @param array $in  raw or clean values (field id => value)
 */
function pb_cond_met( $f, $in ) {
	$on = (string) ( $f['bila'] ?? '' );
	if ( '' === $on ) { return true; }
	$v = $in[ $on ] ?? '';
	$want = (string) ( $f['bila_nilai'] ?? '' );
	if ( is_array( $v ) ) { return in_array( $want, array_map( 'strval', $v ), true ); }
	return is_scalar( $v ) && (string) $v === $want;
}
function pb_kses_text() {
	return [ 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'br' => [], 'a' => [ 'href' => [], 'target' => [] ], 'ul' => [], 'ol' => [], 'li' => [], 'p' => [] ];
}

/** Open / closed / full state of a form. */
function pb_state( $id ) {
	$s   = pb_settings( $id );
	$now = current_time( 'timestamp' );
	if ( get_post_status( $id ) !== 'publish' ) { return 'draf'; }
	if ( $s['buka'] && strtotime( $s['buka'] ) > $now ) { return 'belum'; }
	if ( $s['tutup'] && strtotime( $s['tutup'] ) < $now ) { return 'tutup'; }
	if ( (int) $s['kuota'] > 0 && pb_count_active( $id ) >= (int) $s['kuota'] ) { return 'tutup' === $s['penuh'] ? 'penuh' : 'menunggu'; }
	return 'buka';
}
function pb_state_label( $st ) {
	return [ 'draf' => 'Draf', 'belum' => 'Belum dibuka', 'tutup' => 'Ditutup', 'penuh' => 'Penuh', 'menunggu' => 'Penuh — senarai menunggu', 'buka' => 'Dibuka' ][ $st ] ?? $st;
}

/** Registrations counted against the quota (not cancelled, not wait-listed). */
function pb_count_active( $id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . pb_t() . " WHERE borang_id=%d AND status NOT IN ('batal','menunggu')", $id ) );
}
function pb_count_all( $id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . pb_t() . ' WHERE borang_id=%d', $id ) );
}

/* ------------------------------------------------------------------ formatting */

function pb_fmt_date( $d, $with_day = true ) {
	$t = $d ? strtotime( $d ) : false;
	if ( ! $t ) { return ''; }
	$hari  = [ 'Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu' ];
	$bulan = [ 1 => 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember' ];
	return ( $with_day ? $hari[ (int) gmdate( 'w', $t ) ] . ', ' : '' ) . (int) gmdate( 'j', $t ) . ' ' . $bulan[ (int) gmdate( 'n', $t ) ] . ' ' . gmdate( 'Y', $t );
}
function pb_fmt_datetime( $d ) {
	$t = $d ? strtotime( $d ) : false;
	if ( ! $t ) { return ''; }
	$h = (int) gmdate( 'G', $t ); $m = gmdate( 'i', $t );
	$sfx = $h < 12 ? 'pagi' : ( $h < 14 ? 'tengah hari' : ( $h < 19 ? 'petang' : 'malam' ) );
	$h12 = $h % 12 ?: 12;
	return pb_fmt_date( $d ) . ', ' . $h12 . '.' . $m . ' ' . $sfx;
}
function pb_short( $s, $n ) { $s = wp_strip_all_tags( (string) $s ); return mb_strlen( $s ) > $n ? rtrim( mb_substr( $s, 0, $n - 1 ) ) . '…' : $s; }
function pb_digits( $s ) { return preg_replace( '/\D+/', '', (string) $s ); }
function pb_fmt_ic( $v ) { $d = pb_digits( $v ); return 12 === strlen( $d ) ? substr( $d, 0, 6 ) . '-' . substr( $d, 6, 2 ) . '-' . substr( $d, 8 ) : $d; }

/** Value of a stored entry field as display text. */
function pb_display_value( $field, $val ) {
	if ( is_array( $val ) ) { return implode( ', ', $val ); }
	switch ( $field['type'] ) {
		case 'ic':      return pb_fmt_ic( $val );
		case 'date':    return $val ? pb_fmt_date( $val, false ) : '';
		case 'consent': return $val ? 'Ya, bersetuju' : 'Tidak';
		default:        return (string) $val;
	}
}

/* ------------------------------------------------------------------ request helpers */

/** Client IP for throttling. Proxy headers are only trusted when chosen in Tetapan. */
function pb_client_ip() {
	$remote = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$src    = function_exists( 'pb_opt' ) ? pb_opt( 'ip' ) : 'remote';
	if ( 'cloudflare' === $src && filter_var( (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '' ), FILTER_VALIDATE_IP ) ) { return (string) $_SERVER['HTTP_CF_CONNECTING_IP']; }
	if ( 'xff' === $src && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$first = trim( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
		if ( filter_var( $first, FILTER_VALIDATE_IP ) ) { return $first; }
	}
	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
}
function pb_throttle( $bucket, $max, $window ) {
	$k = 'pb_t_' . $bucket . '_' . md5( pb_client_ip() );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) { return false; }
	set_transient( $k, $n + 1, $window );
	return true;
}
function pb_gs( $k, $default = '' ) { return isset( $_GET[ $k ] ) && is_scalar( $_GET[ $k ] ) ? (string) wp_unslash( $_GET[ $k ] ) : $default; }

function pb_flash_set( $data ) {
	$t = strtolower( wp_generate_password( 16, false, false ) );
	set_transient( 'pb_flash_' . $t, [ 'd' => $data, 'u' => get_current_user_id() ], 15 * MINUTE_IN_SECONDS );
	return $t;
}
function pb_flash_get( $param = 'pb_ok' ) {
	$t = isset( $_GET[ $param ] ) && is_string( $_GET[ $param ] ) ? strtolower( $_GET[ $param ] ) : '';
	if ( ! preg_match( '/^[a-z0-9]{16}$/', $t ) ) { return null; }
	$f = get_transient( 'pb_flash_' . $t );
	if ( ! is_array( $f ) || (int) $f['u'] !== get_current_user_id() ) { return null; }
	return $f['d'];
}

/** CSV cell guard against spreadsheet formula injection. */
function pb_csv_cell( $v ) {
	$v = (string) $v;
	return ( '' !== $v && in_array( $v[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) ? "'" . $v : $v;
}

/* ------------------------------------------------------------------ private uploads */

function pb_private_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'pb-private';
	if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
	if ( ! file_exists( "$dir/.htaccess" ) ) { @file_put_contents( "$dir/.htaccess", "Require all denied\nDeny from all\n" ); }
	if ( ! file_exists( "$dir/index.php" ) ) { @file_put_contents( "$dir/index.php", "<?php // silence\n" ); }
	return $dir;
}

/**
 * Validate & store an uploaded file outside the public media library.
 * @return string|WP_Error|null relative path, error, or null if no file.
 */
function pb_store_upload( $key ) {
	if ( empty( $_FILES['pb_f']['name'][ $key ] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES['pb_f']['error'][ $key ] ) { return null; }
	$f = [];
	foreach ( [ 'name', 'tmp_name', 'error', 'size' ] as $p ) { $f[ $p ] = $_FILES['pb_f'][ $p ][ $key ] ?? ''; }
	if ( is_array( $f['name'] ) ) { return new WP_Error( 'upload', 'Fail tidak sah.' ); }
	if ( UPLOAD_ERR_OK !== (int) $f['error'] ) { return new WP_Error( 'upload', 'Muat naik fail gagal. Sila cuba lagi.' ); }
	if ( $f['size'] > 10 * MB_IN_BYTES ) { return new WP_Error( 'upload', 'Saiz fail melebihi 10 MB.' ); }
	$allowed = [ 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf' ];
	$chk     = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $allowed );
	if ( empty( $chk['ext'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) { return new WP_Error( 'upload', 'Jenis fail tidak dibenarkan. Sila muat naik gambar (JPG/PNG) atau PDF.' ); }
	$head = (string) @file_get_contents( $f['tmp_name'], false, null, 0, 1024 );
	if ( 'pdf' === $chk['ext'] ) {
		if ( 0 !== strpos( ltrim( $head ), '%PDF-' ) ) { return new WP_Error( 'upload', 'Fail PDF tidak sah.' ); }
	} elseif ( false === @getimagesize( $f['tmp_name'] ) ) {
		return new WP_Error( 'upload', 'Fail gambar tidak sah.' );
	}
	if ( preg_match( '/<\?php|<script|<html|<svg/i', $head ) ) { return new WP_Error( 'upload', 'Fail tidak dibenarkan.' ); }
	$sub = gmdate( 'Y/m' );
	$dir = pb_private_dir() . '/' . $sub;
	wp_mkdir_p( $dir );
	$name = gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 10, false ) . '.' . $chk['ext'];
	if ( ! @move_uploaded_file( $f['tmp_name'], "$dir/$name" ) ) { return new WP_Error( 'upload', 'Fail tidak dapat disimpan.' ); }
	@chmod( "$dir/$name", 0640 );
	return "$sub/$name";
}
function pb_private_path( $rel ) {
	if ( ! is_string( $rel ) || ! preg_match( '#^\d{4}/\d{2}/[A-Za-z0-9\-]+\.(jpe?g|png|webp|pdf)$#', $rel ) ) { return null; }
	$base = realpath( pb_private_dir() );
	$path = realpath( $base . '/' . $rel );
	return ( $path && 0 === strpos( $path, $base . DIRECTORY_SEPARATOR ) && is_file( $path ) ) ? $path : null;
}
function pb_delete_private( $rel ) { if ( $p = pb_private_path( $rel ) ) { @unlink( $p ); } }

/* Staff-only, nonce-protected streaming of private uploads. */
add_action( 'admin_post_pb_fail', function () {
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_fail' );
	$path = pb_private_path( pb_gs( 'f' ) );
	if ( ! $path ) { wp_die( 'Fail tidak ditemui.', 404 ); }
	$ft = wp_check_filetype( $path );
	nocache_headers();
	header( 'Content-Type: ' . ( $ft['type'] ?: 'application/octet-stream' ) );
	header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cross-Origin-Resource-Policy: same-origin' );
	if ( 'application/pdf' !== $ft['type'] ) { header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox" ); }
	readfile( $path );
	exit;
} );
function pb_file_url( $rel ) { return wp_nonce_url( admin_url( 'admin-post.php?action=pb_fail&f=' . rawurlencode( $rel ) ), 'pb_fail' ); }
