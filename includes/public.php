<?php
defined( 'ABSPATH' ) || exit;

$GLOBALS['pb_errors'] = [];
$GLOBALS['pb_values'] = [];

/** Register front-end assets. Safe to call early: block themes render shortcodes before wp_enqueue_scripts. */
function pb_register_public() {
	if ( wp_style_is( 'pb-public', 'registered' ) ) { return; }
	wp_register_style( 'pb-public', PB_URL . 'assets/pb-public.css', [], PB_VER );
	wp_add_inline_style( 'pb-public', pb_color_css() );
	wp_register_script( 'pb-public', PB_URL . 'assets/pb-public.js', [], PB_VER, true );
	wp_register_style( 'pb-fontawesome', PB_FA_URL, [], null );
}
add_action( 'wp_enqueue_scripts', 'pb_register_public' );

function pb_enqueue_public( $with_js = true ) {
	pb_register_public();
	wp_enqueue_style( 'pb-public' );
	if ( $with_js ) { wp_enqueue_script( 'pb-public' ); }
	// Font Awesome: decide once the theme's own styles are queued.
	if ( did_action( 'wp_enqueue_scripts' ) ) { pb_maybe_fa(); }
	elseif ( ! has_action( 'wp_enqueue_scripts', 'pb_maybe_fa' ) ) { add_action( 'wp_enqueue_scripts', 'pb_maybe_fa', 100 ); }
}

/* ================================================================= POST handling (PRG) */

add_action( 'template_redirect', function () {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['pb_borang'] ) ) { return; }
	$id = absint( is_scalar( $_POST['pb_borang'] ) ? $_POST['pb_borang'] : 0 );
	if ( ! $id || get_post_type( $id ) !== PB_CPT ) { return; }

	$err = &$GLOBALS['pb_errors'];
	if ( ! wp_verify_nonce( $_POST['pb_nonce'] ?? '', 'pb_hantar_' . $id ) ) { $err[ $id ] = [ 'Sesi borang telah tamat. Sila muat semula halaman dan cuba lagi.' ]; return; }
	if ( ! empty( $_POST['pb_web'] ) ) { $err[ $id ] = [ 'Penghantaran ditolak.' ]; return; } // honeypot

	[ $values, $errs ] = pb_collect( $id );
	$GLOBALS['pb_values'][ $id ] = $values; // re-fill the form on any error below
	if ( ( time() - (int) ( $_POST['pb_ts'] ?? 0 ) ) < 8 ) { $err[ $id ] = [ 'Borang dihantar terlalu cepat. Sila semak maklumat dan hantar semula.' ]; return; }

	$state = pb_state( $id );
	if ( ! in_array( $state, [ 'buka', 'menunggu' ], true ) ) { $err[ $id ] = [ 'Pendaftaran untuk borang ini ' . strtolower( pb_state_label( $state ) ) . '.' ]; return; }
	if ( $errs ) { $err[ $id ] = $errs; return; }
	if ( ! pb_throttle( 'submit', 10, HOUR_IN_SECONDS ) ) { $err[ $id ] = [ 'Terlalu banyak penghantaran dari rangkaian anda. Sila cuba sebentar lagi.' ]; return; }

	[ $files, $ferrs ] = pb_collect_files( $id );
	if ( $ferrs ) { $err[ $id ] = $ferrs; return; }

	$res = pb_insert_entry( $id, $values, $files, 'menunggu' === $state ? 'menunggu' : 'baru' );
	if ( is_wp_error( $res ) ) { foreach ( $files as $rel ) { pb_delete_private( $rel ); } $err[ $id ] = $res->get_error_messages(); return; }

	$tok = pb_flash_set( [ 'id' => $id, 'ref' => $res[1], 'menunggu' => 'menunggu' === $state ] );
	wp_safe_redirect( add_query_arg( 'pb_ok', $tok, strtok( (string) ( wp_get_referer() ?: get_permalink() ), '?#' ) ) . '#pb-' . $id );
	exit;
} );

/* ================================================================= shortcode */

add_shortcode( 'borang', 'pb_shortcode' );
function pb_shortcode( $atts ) {
	$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'borang' );
	$id   = absint( $atts['id'] );
	if ( ! $id || get_post_type( $id ) !== PB_CPT ) { return current_user_can( PB_CAP ) ? '<p class="alert alert-warning">Borang tidak ditemui (id ' . esc_html( $atts['id'] ) . ').</p>' : ''; }
	$post = get_post( $id );
	if ( 'publish' !== $post->post_status && ! current_user_can( PB_CAP ) ) { return ''; }

	pb_enqueue_public();
	if ( ! headers_sent() ) { nocache_headers(); }

	$s     = pb_settings( $id );
	$state = pb_state( $id );
	$out   = '<div class="pb-ui" id="pb-' . $id . '">';

	// programme info card
	$rows = [];
	if ( $s['tarikh_program'] ) { $rows[] = [ 'fa-calendar-day', 'Tarikh', pb_fmt_date( $s['tarikh_program'] ) ]; }
	if ( $s['masa'] ) { $rows[] = [ 'fa-clock', 'Masa', $s['masa'] ]; }
	if ( $s['tempat'] ) { $rows[] = [ 'fa-location-dot', 'Tempat', $s['tempat'] ]; }
	if ( $s['yuran'] ) { $rows[] = [ 'fa-tag', 'Yuran', $s['yuran'] ]; }
	if ( $s['tutup'] ) { $rows[] = [ 'fa-hourglass-end', 'Tarikh tutup', pb_fmt_datetime( $s['tutup'] ) ]; }
	if ( $s['hubungi'] ) { $rows[] = [ 'fa-phone', 'Pertanyaan', $s['hubungi'] ]; }
	if ( $rows ) {
		$out .= '<div class="pb-info card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4"><div class="row g-3">';
		foreach ( $rows as $r ) {
			$out .= '<div class="col-sm-6"><div class="d-flex gap-3"><span class="pb-ic"><i class="fa-solid ' . esc_attr( $r[0] ) . '"></i></span><div><div class="small text-secondary">' . esc_html( $r[1] ) . '</div><div class="fw-semibold">' . esc_html( $r[2] ) . '</div></div></div></div>';
		}
		$out .= '</div>';
		if ( (int) $s['kuota'] > 0 && $s['tunjuk_baki'] && 'draf' !== $state ) {
			$used = pb_count_active( $id );
			$left = max( 0, (int) $s['kuota'] - $used );
			$pct  = min( 100, round( $used / max( 1, (int) $s['kuota'] ) * 100 ) );
			$out .= '<div class="pb-quota mt-4"><div class="d-flex justify-content-between small mb-1"><span><i class="fa-solid fa-users me-1 text-mp"></i>Tempat berbaki</span><strong>' . ( $left ? esc_html( $left . ' / ' . $s['kuota'] ) : 'Penuh' ) . '</strong></div>'
				. '<div class="progress" role="progressbar" aria-label="Tempat terisi" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100" style="height:8px"><div class="progress-bar bg-mp" style="width:' . $pct . '%"></div></div></div>';
		}
		$out .= '</div></div>';
	}

	// intro / terms (the form's main content)
	if ( trim( $post->post_content ) ) { $out .= '<div class="pb-intro mb-4">' . wpautop( wp_kses_post( $post->post_content ) ) . '</div>'; }

	// success
	$ok = pb_flash_get();
	if ( is_array( $ok ) && (int) ( $ok['id'] ?? 0 ) === $id ) {
		$out .= '<div class="pb-success card border-0 shadow-sm rounded-4 mb-4" role="status"><div class="card-body p-4 text-center">'
			. '<div class="pb-success-ic mb-3"><i class="fa-solid ' . ( $ok['menunggu'] ? 'fa-hourglass-half' : 'fa-circle-check' ) . '"></i></div>'
			. '<h3 class="h4">' . ( $ok['menunggu'] ? 'Dimasukkan ke senarai menunggu' : 'Pendaftaran diterima' ) . '</h3>'
			. '<p class="mb-2">No. Rujukan anda:</p><p class="pb-ref">' . esc_html( $ok['ref'] ) . '</p>'
			. '<p class="mb-3">' . esc_html( $ok['menunggu'] ? 'Kuota telah penuh. AJK akan menghubungi anda jika ada kekosongan.' : $s['mesej'] ) . '</p>'
			. '<p class="small text-secondary mb-3">Sila simpan atau tangkap layar nombor rujukan ini.</p>'
			. '<a class="btn btn-outline-secondary btn-sm" href="' . esc_url( strtok( (string) get_permalink(), '?' ) . '#pb-' . $id ) . '"><i class="fa-solid fa-plus me-1"></i>Daftar peserta lain</a>'
			. '</div></div></div>';
		return $out;
	}

	if ( ! in_array( $state, [ 'buka', 'menunggu' ], true ) && ! ( 'draf' === $state && current_user_can( PB_CAP ) ) ) {
		$msg = [
			'belum' => 'Pendaftaran akan dibuka pada ' . pb_fmt_datetime( $s['buka'] ) . '.',
			'tutup' => 'Pendaftaran telah ditutup. Terima kasih atas sambutan anda.',
			'penuh' => 'Pendaftaran telah penuh. Terima kasih atas sambutan anda.',
			'draf'  => 'Borang ini belum diterbitkan.',
		][ $state ] ?? 'Pendaftaran ditutup.';
		return $out . '<div class="alert alert-secondary rounded-4 d-flex gap-2 align-items-center"><i class="fa-solid fa-circle-info"></i><span>' . esc_html( $msg ) . '</span></div></div>';
	}
	if ( 'draf' === $state ) { $out .= '<div class="alert alert-warning small"><i class="fa-solid fa-eye me-1"></i>Pratonton AJK — borang ini masih draf dan belum boleh diisi oleh orang awam.</div>'; }
	if ( 'menunggu' === $state ) { $out .= '<div class="alert alert-warning rounded-4"><i class="fa-solid fa-hourglass-half me-2"></i>Kuota telah penuh. Pendaftaran baharu akan dimasukkan ke <strong>senarai menunggu</strong>.</div>'; }

	$errs = $GLOBALS['pb_errors'][ $id ] ?? [];
	if ( $errs ) {
		$out .= '<div class="alert alert-danger rounded-4" role="alert" tabindex="-1" id="pb-err-' . $id . '"><strong><i class="fa-solid fa-triangle-exclamation me-1"></i>Sila semak semula:</strong><ul class="mb-0 mt-2">';
		foreach ( $errs as $e ) { $out .= '<li>' . esc_html( $e ) . '</li>'; }
		$out .= '</ul></div>';
	}

	$has_file = (bool) array_filter( pb_fields( $id ), fn( $f ) => 'file' === $f['type'] );
	$out .= '<form class="pb-form card border-0 shadow-sm rounded-4" method="post" action="' . esc_url( strtok( (string) get_permalink(), '?' ) . '#pb-' . $id ) . '"' . ( $has_file ? ' enctype="multipart/form-data"' : '' ) . ' novalidate data-pb-form>'
		. '<div class="card-body p-4 p-lg-5">'
		. wp_nonce_field( 'pb_hantar_' . $id, 'pb_nonce', false, false )
		. '<input type="hidden" name="pb_borang" value="' . $id . '"><input type="hidden" name="pb_ts" value="' . time() . '">'
		. '<div class="pb-hp" aria-hidden="true"><label>Laman web<input type="text" name="pb_web" tabindex="-1" autocomplete="off"></label></div>'
		. '<p class="small text-secondary mb-4"><span class="text-danger">*</span> Wajib diisi</p>'
		. pb_render_fields( $id, $GLOBALS['pb_values'][ $id ] ?? [] )
		. '<div class="mt-4 pt-3 border-top d-flex flex-wrap gap-3 align-items-center">'
		. '<button type="submit" class="btn btn-mp btn-lg px-4"><i class="fa-solid fa-paper-plane me-2"></i>' . esc_html( $s['butang'] ) . '</button>'
		. '<span class="small text-secondary"><i class="fa-solid fa-lock me-1"></i>Maklumat anda hanya dilihat oleh AJK masjid.</span>'
		. '</div></div></form></div>';
	return $out;
}
