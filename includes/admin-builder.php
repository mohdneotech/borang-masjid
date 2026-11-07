<?php
defined( 'ABSPATH' ) || exit;

/* ================================================================= menu */

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=' . PB_CPT, 'Bina Borang Baharu', '+ Bina Borang Baharu', PB_CAP, 'pb-baharu', 'pb_page_new' );
	add_submenu_page( 'edit.php?post_type=' . PB_CPT, 'Senarai Pendaftaran', 'Senarai Pendaftaran', PB_CAP, 'pb-entri', 'pb_page_entries' );
	add_submenu_page( 'edit.php?post_type=' . PB_CPT, 'Panduan', 'Panduan', PB_CAP, 'pb-panduan', 'pb_page_help' );
	remove_submenu_page( 'edit.php?post_type=' . PB_CPT, 'post-new.php?post_type=' . PB_CPT );
}, 20 );

// "Add New" always goes through the template picker
add_action( 'load-post-new.php', function () {
	if ( ( $_GET['post_type'] ?? '' ) === PB_CPT ) { wp_safe_redirect( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-baharu' ) ); exit; }
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$scr = get_current_screen();
	if ( ! $scr || PB_CPT !== $scr->post_type ) { return; }
	if ( 'off' !== pb_opt( 'fontawesome' ) ) { wp_enqueue_style( 'pb-fontawesome', PB_FA_URL, [], null ); }
	wp_enqueue_style( 'pb-admin', PB_URL . 'assets/pb-admin.css', [], PB_VER );
	if ( 'post' === $scr->base ) {
		wp_enqueue_script( 'pb-builder', PB_URL . 'assets/pb-builder.js', [ 'jquery', 'jquery-ui-sortable' ], PB_VER, true );
		$types = [];
		foreach ( pb_field_types() as $k => $t ) { $types[ $k ] = [ 'label' => $t[0], 'icon' => $t[1], 'input' => $t[2] ]; }
		wp_localize_script( 'pb-builder', 'PB_BUILDER', [ 'types' => $types ] );
	}
} );

/* ================================================================= new form: template picker */

function pb_page_new() {
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.' ); }
	echo '<div class="wrap pb-wrap"><h1><i class="fa-solid fa-wand-magic-sparkles"></i> Bina Borang Baharu</h1>'
		. '<p class="pb-lead">Pilih templat untuk bermula. Semua medan, teks dan tetapan boleh diubah selepas ini.</p>'
		. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pb_cipta">' . wp_nonce_field( 'pb_cipta', '_wpnonce', true, false )
		. '<p><label class="pb-big-label" for="pb-tajuk">Nama borang / program</label><input class="pb-big-input" id="pb-tajuk" name="tajuk" required maxlength="150" placeholder="Contoh: Khatan Perdana 2026"></p><div class="pb-tpl-grid">';
	$first = true;
	foreach ( pb_templates() as $k => $t ) {
		$n = count( array_filter( $t['fields'], fn( $f ) => pb_is_input( $f['type'] ) ) );
		echo '<label class="pb-tpl"><input type="radio" name="tpl" value="' . esc_attr( $k ) . '"' . checked( $first, true, false ) . '>'
			. '<span class="pb-tpl-card"><i class="fa-solid ' . esc_attr( $t['icon'] ) . '"></i><strong>' . esc_html( $t['label'] ) . '</strong><small>' . esc_html( $n ) . ' medan</small></span></label>';
		$first = false;
	}
	echo '</div><p><button class="button button-primary button-hero"><i class="fa-solid fa-arrow-right"></i> Teruskan</button></p></form></div>';
}

add_action( 'admin_post_pb_cipta', function () {
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_cipta' );
	$id = pb_create_form( wp_unslash( $_POST['tajuk'] ?? '' ), sanitize_key( $_POST['tpl'] ?? 'kosong' ) );
	if ( is_wp_error( $id ) ) { wp_die( esc_html( $id->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $id . '&pb_new=1' ) );
	exit;
} );

/* ================================================================= edit screen */

add_action( 'edit_form_after_title', function ( $post ) {
	if ( PB_CPT !== $post->post_type ) { return; }
	if ( isset( $_GET['pb_new'] ) ) {
		echo '<div class="notice notice-success inline pb-notice"><p><strong>Borang dicipta daripada templat.</strong> Semak teks, medan dan tetapan di bawah, kemudian klik <strong>Terbitkan</strong>. Selepas itu klik <strong>Cipta halaman</strong> (kotak kanan) untuk memaparkannya di laman web.</p></div>';
	}
	echo '<h2 class="pb-sec-title"><i class="fa-solid fa-align-left"></i> Teks pengenalan / syarat <small>— dipaparkan di atas borang</small></h2>';
} );

add_action( 'add_meta_boxes_' . PB_CPT, function () {
	add_meta_box( 'pb_medan', 'Medan Borang', 'pb_box_fields', PB_CPT, 'normal', 'high' );
	add_meta_box( 'pb_tetapan', 'Maklumat Program & Tetapan', 'pb_box_settings', PB_CPT, 'normal', 'high' );
	add_meta_box( 'pb_halaman', 'Halaman & Pendaftaran', 'pb_box_page', PB_CPT, 'side', 'high' );
} );

function pb_box_fields( $post ) {
	wp_nonce_field( 'pb_simpan_' . $post->ID, 'pb_simpan_nonce' );
	echo '<p class="description">Seret <i class="fa-solid fa-grip-vertical"></i> untuk menyusun. Klik pada medan untuk mengubah tajuk, pilihan dan sama ada ia wajib diisi.</p>'
		. '<div id="pb-builder" class="pb-builder"></div>'
		. '<textarea name="pb_fields_json" id="pb-fields-json" hidden>' . esc_textarea( wp_json_encode( pb_fields( $post->ID ), JSON_UNESCAPED_UNICODE ) ) . '</textarea>'
		. '<div class="pb-palette"><span class="pb-palette-title"><i class="fa-solid fa-plus"></i> Tambah medan:</span>';
	foreach ( pb_field_types() as $k => $t ) {
		echo '<button type="button" class="button pb-add" data-type="' . esc_attr( $k ) . '"><i class="fa-solid ' . esc_attr( $t[1] ) . '"></i> ' . esc_html( $t[0] ) . '</button>';
	}
	echo '</div>';
}

function pb_box_settings( $post ) {
	$s   = pb_settings( $post->ID );
	$dt  = fn( $v ) => $v ? gmdate( 'Y-m-d\TH:i', strtotime( $v ) ) : '';
	$row = function ( $label, $html, $help = '' ) { echo '<div class="pb-set"><label>' . $label . '</label><div>' . $html . ( $help ? '<p class="description">' . $help . '</p>' : '' ) . '</div></div>'; };
	$inp = fn( $k, $ph = '', $type = 'text', $v = null ) => '<input type="' . $type . '" class="widefat" name="pb_s[' . $k . ']" value="' . esc_attr( $v ?? $s[ $k ] ) . '" placeholder="' . esc_attr( $ph ) . '">';

	echo '<div class="pb-set-grid"><div class="pb-set-col"><h3><i class="fa-solid fa-calendar-check"></i> Maklumat program <small>(dipaparkan dalam kad di atas borang; biarkan kosong untuk sembunyi)</small></h3>';
	$row( 'Tarikh program', $inp( 'tarikh_program', '', 'date', $s['tarikh_program'] ? gmdate( 'Y-m-d', strtotime( $s['tarikh_program'] ) ) : '' ), 'Juga digunakan untuk semakan umur (medan Tarikh).' );
	$row( 'Masa', $inp( 'masa', '8.00 pagi – 1.00 tengah hari' ) );
	$row( 'Tempat', $inp( 'tempat', pb_masjid() ) );
	$row( 'Yuran', $inp( 'yuran', 'Percuma / RM20 seorang' ) );
	$row( 'Pertanyaan', $inp( 'hubungi', 'Nama — 01X-XXX XXXX' ) );
	echo '<h3 style="margin-top:22px"><i class="fa-solid fa-images"></i> Slaid laman utama</h3>';
	$row( '', '<label><input type="checkbox" name="pb_s[slaid]" value="1"' . checked( $s['slaid'], 1, false ) . '> Papar sebagai slaid di laman utama semasa pendaftaran dibuka</label>', 'Memerlukan plugin <a href="https://github.com/mohdneotech/slaid-masjid" target="_blank" rel="noopener">Slaid Utama Masjid</a> (atau tema yang menyokong penapis <code>masjid_hero_slides</code>). Slaid hilang sendiri apabila pendaftaran ditutup atau penuh. Perlu ada halaman borang (Cipta halaman).' );
	$row( 'Ayat ringkas slaid', $inp( 'slaid_teks', 'Cth: Terbuka kepada kanak-kanak lelaki 5–12 tahun. Tempat terhad!' ) );
	echo '</div><div class="pb-set-col"><h3><i class="fa-solid fa-sliders"></i> Tetapan pendaftaran</h3>';
	$row( 'Dibuka mulai', $inp( 'buka', '', 'datetime-local', $dt( $s['buka'] ) ), 'Kosong = dibuka sebaik diterbitkan.' );
	$row( 'Tarikh &amp; masa tutup', $inp( 'tutup', '', 'datetime-local', $dt( $s['tutup'] ) ), 'Kosong = tiada tarikh tutup.' );
	$row( 'Kuota peserta', '<input type="number" min="0" max="100000" class="small-text" name="pb_s[kuota]" value="' . esc_attr( (int) $s['kuota'] ) . '"> <span class="description">0 = tiada had</span>' );
	$row( 'Apabila penuh', '<select name="pb_s[penuh]"><option value="tutup"' . selected( $s['penuh'], 'tutup', false ) . '>Tutup pendaftaran</option><option value="menunggu"' . selected( $s['penuh'], 'menunggu', false ) . '>Terima ke senarai menunggu</option></select>' );
	$row( '', '<label><input type="checkbox" name="pb_s[tunjuk_baki]" value="1"' . checked( $s['tunjuk_baki'], 1, false ) . '> Papar baki tempat kepada orang awam</label>' );
	$row( 'Elak pendaftaran berganda', '<select name="pb_s[unik]" id="pb-unik" data-current="' . esc_attr( $s['unik'] ) . '"><option value="">— Tiada semakan —</option></select>', 'Medan yang tidak boleh berulang, cth. No. MyKid.' );
	$row( 'Awalan No. Rujukan', '<input type="text" class="small-text" maxlength="6" name="pb_s[prefix]" value="' . esc_attr( $s['prefix'] ) . '" style="text-transform:uppercase"> <span class="description">cth. KHT → KHT-001</span>' );
	$row( 'Teks butang', $inp( 'butang' ) );
	$row( 'Mesej selepas hantar', '<textarea class="widefat" rows="3" name="pb_s[mesej]">' . esc_textarea( $s['mesej'] ) . '</textarea>' );
	echo '</div></div>';
}

function pb_box_page( $post ) {
	$id    = $post->ID;
	$s     = pb_settings( $id );
	$state = pb_state( $id );
	$page  = $s['halaman'] && get_post( $s['halaman'] ) && 'trash' !== get_post_status( $s['halaman'] ) ? (int) $s['halaman'] : 0;
	echo '<p><strong>Status:</strong> <span class="pb-state pb-state-' . esc_attr( $state ) . '">' . esc_html( pb_state_label( $state ) ) . '</span></p>';
	if ( 'auto-draft' !== $post->post_status ) {
		$n = pb_count_all( $id );
		echo '<p><strong>Pendaftaran:</strong> ' . (int) $n . ( (int) $s['kuota'] ? ' (kuota ' . (int) $s['kuota'] . ', aktif ' . pb_count_active( $id ) . ')' : '' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-entri&borang=' . $id ) ) . '"><i class="fa-solid fa-list-check"></i> Lihat senarai pendaftaran</a></p><hr>';
	}
	if ( $page ) {
		echo '<p><strong>Halaman:</strong><br><a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html( get_permalink( $page ) ) . ' <i class="fa-solid fa-arrow-up-right-from-square"></i></a></p>'
			. '<p><button type="button" class="button pb-copy" data-copy="' . esc_attr( get_permalink( $page ) ) . '"><i class="fa-regular fa-copy"></i> Salin pautan</button> '
			. '<a class="button" href="' . esc_url( get_edit_post_link( $page ) ) . '">Sunting halaman</a></p>';
	} elseif ( 'auto-draft' !== $post->post_status ) {
		echo '<p class="description">Borang ini belum dipaparkan di mana-mana halaman.</p><p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pb_halaman&borang=' . $id ), 'pb_halaman_' . $id ) ) . '"><i class="fa-solid fa-file-circle-plus"></i> Cipta halaman</a></p>';
	}
	echo '<p class="description">Atau tampal kod ini dalam mana-mana halaman:</p><p><code class="pb-code">[borang id="' . (int) $id . '"]</code></p>';
}

add_action( 'save_post_' . PB_CPT, function ( $id, $post ) {
	if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
	if ( ! isset( $_POST['pb_simpan_nonce'] ) || ! wp_verify_nonce( $_POST['pb_simpan_nonce'], 'pb_simpan_' . $id ) ) { return; }
	if ( ! current_user_can( PB_CAP ) ) { return; }

	if ( isset( $_POST['pb_fields_json'] ) && is_string( $_POST['pb_fields_json'] ) ) {
		$raw = json_decode( wp_unslash( $_POST['pb_fields_json'] ), true );
		if ( is_array( $raw ) ) { update_post_meta( $id, '_pb_fields', wp_slash( wp_json_encode( pb_sanitize_fields( $raw ), JSON_UNESCAPED_UNICODE ) ) ); }
	}
	$in  = isset( $_POST['pb_s'] ) && is_array( $_POST['pb_s'] ) ? wp_unslash( $_POST['pb_s'] ) : [];
	$old = pb_settings( $id );
	$txt = fn( $k, $max = 190 ) => mb_substr( sanitize_text_field( (string) ( is_scalar( $in[ $k ] ?? '' ) ? ( $in[ $k ] ?? '' ) : '' ) ), 0, $max );
	$dt  = function ( $k ) use ( $in ) { $v = is_scalar( $in[ $k ] ?? '' ) ? trim( (string) ( $in[ $k ] ?? '' ) ) : ''; $t = $v ? strtotime( $v ) : false; return $t ? gmdate( 'Y-m-d H:i:s', $t ) : ''; };
	$ids = wp_list_pluck( pb_fields( $id ), 'id' );
	$unik = $txt( 'unik', 40 );
	$new = [
		'tarikh_program' => ( $d = $dt( 'tarikh_program' ) ) ? substr( $d, 0, 10 ) : '',
		'masa'           => $txt( 'masa' ),
		'tempat'         => $txt( 'tempat' ),
		'yuran'          => $txt( 'yuran' ),
		'hubungi'        => $txt( 'hubungi' ),
		'buka'           => $dt( 'buka' ),
		'tutup'          => $dt( 'tutup' ),
		'kuota'          => max( 0, min( 100000, (int) ( $in['kuota'] ?? 0 ) ) ),
		'penuh'          => ( $in['penuh'] ?? '' ) === 'menunggu' ? 'menunggu' : 'tutup',
		'tunjuk_baki'    => empty( $in['tunjuk_baki'] ) ? 0 : 1,
		'prefix'         => substr( preg_replace( '/[^A-Z0-9]/', '', strtoupper( $txt( 'prefix', 10 ) ) ), 0, 6 ) ?: 'REG',
		'unik'           => in_array( $unik, $ids, true ) ? $unik : '',
		'mesej'          => mb_substr( sanitize_textarea_field( (string) ( is_scalar( $in['mesej'] ?? '' ) ? ( $in['mesej'] ?? '' ) : '' ) ), 0, 600 ) ?: pb_default_settings()['mesej'],
		'butang'         => $txt( 'butang', 60 ) ?: 'Hantar Pendaftaran',
		'halaman'        => (int) $old['halaman'],
		'slaid'          => empty( $in['slaid'] ) ? 0 : 1,
		'slaid_teks'     => $txt( 'slaid_teks', 200 ),
	];
	update_post_meta( $id, '_pb_settings', $new );
}, 10, 2 );

/* ================================================================= create page / duplicate */

add_action( 'admin_post_pb_halaman', function () {
	$id = absint( $_GET['borang'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) || ! current_user_can( 'publish_pages' ) && ! current_user_can( 'edit_pages' ) ) { wp_die( 'Tiada kebenaran mencipta halaman.', 403 ); }
	check_admin_referer( 'pb_halaman_' . $id );
	if ( get_post_type( $id ) !== PB_CPT ) { wp_die( 'Borang tidak ditemui.', 404 ); }
	$parent = get_page_by_path( 'borang' );
	$pid    = wp_insert_post( [
		'post_type'    => 'page',
		'post_title'   => get_the_title( $id ),
		'post_content' => '<!-- wp:shortcode -->[borang id="' . $id . '"]<!-- /wp:shortcode -->',
		'post_status'  => 'publish' === get_post_status( $id ) && current_user_can( 'publish_pages' ) ? 'publish' : 'draft',
		'post_parent'  => $parent ? $parent->ID : 0,
		'menu_order'   => 50,
	], true );
	if ( is_wp_error( $pid ) ) { wp_die( esc_html( $pid->get_error_message() ) ); }
	update_post_meta( $pid, '_mp_icon', 'fa-pen-to-square' );
	$s = pb_settings( $id ); $s['halaman'] = $pid;
	update_post_meta( $id, '_pb_settings', $s );
	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $id . '&pb_halaman=1' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( isset( $_GET['pb_halaman'] ) && get_current_screen() && PB_CPT === get_current_screen()->post_type ) {
		echo '<div class="notice notice-success is-dismissible"><p><i class="fa-solid fa-circle-check"></i> Halaman borang telah dicipta di bawah menu <strong>Borang</strong>. Salin pautan di kotak kanan untuk dikongsi di WhatsApp / Facebook.</p></div>';
	}
	if ( isset( $_GET['pb_salin'] ) && get_current_screen() && PB_CPT === get_current_screen()->post_type ) {
		echo '<div class="notice notice-success is-dismissible"><p>Salinan borang telah dicipta sebagai draf. Kemas kini tarikh dan tetapan sebelum diterbitkan.</p></div>';
	}
} );

add_filter( 'post_row_actions', function ( $a, $post ) {
	if ( PB_CPT !== $post->post_type || ! current_user_can( PB_CAP ) ) { return $a; }
	unset( $a['inline hide-if-no-js'] );
	$a = [ 'pb_entri' => '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-entri&borang=' . $post->ID ) ) . '">Pendaftaran</a>' ] + $a;
	$a['pb_salin'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pb_salin&borang=' . $post->ID ), 'pb_salin_' . $post->ID ) ) . '">Salin borang</a>';
	return $a;
}, 10, 2 );

add_action( 'admin_post_pb_salin', function () {
	$id = absint( $_GET['borang'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_salin_' . $id );
	$src = get_post( $id );
	if ( ! $src || PB_CPT !== $src->post_type ) { wp_die( 'Borang tidak ditemui.', 404 ); }
	$new = wp_insert_post( [ 'post_type' => PB_CPT, 'post_status' => 'draft', 'post_title' => $src->post_title . ' (Salinan)', 'post_content' => $src->post_content ], true );
	if ( is_wp_error( $new ) ) { wp_die( esc_html( $new->get_error_message() ) ); }
	update_post_meta( $new, '_pb_fields', wp_slash( (string) get_post_meta( $id, '_pb_fields', true ) ) );
	$s = pb_settings( $id ); $s['halaman'] = 0; $s['buka'] = ''; $s['tutup'] = ''; $s['tarikh_program'] = '';
	update_post_meta( $new, '_pb_settings', $s );
	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new . '&pb_salin=1' ) );
	exit;
} );

/* ================================================================= list table columns */

add_filter( 'manage_' . PB_CPT . '_posts_columns', function ( $c ) {
	return [ 'cb' => $c['cb'], 'title' => 'Borang', 'pb_state' => 'Status', 'pb_n' => 'Pendaftaran', 'pb_prog' => 'Tarikh program', 'pb_code' => 'Kod pendek', 'date' => $c['date'] ];
} );
add_action( 'manage_' . PB_CPT . '_posts_custom_column', function ( $col, $id ) {
	$s = pb_settings( $id );
	switch ( $col ) {
		case 'pb_state': $st = pb_state( $id ); echo '<span class="pb-state pb-state-' . esc_attr( $st ) . '">' . esc_html( pb_state_label( $st ) ) . '</span>'; break;
		case 'pb_n':     echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-entri&borang=' . $id ) ) . '"><strong>' . pb_count_all( $id ) . '</strong></a>' . ( (int) $s['kuota'] ? ' / ' . (int) $s['kuota'] : '' ); break;
		case 'pb_prog':  echo esc_html( $s['tarikh_program'] ? pb_fmt_date( $s['tarikh_program'] ) : '—' ); break;
		case 'pb_code':  echo '<code>[borang id="' . (int) $id . '"]</code>'; break;
	}
}, 10, 2 );

/* ================================================================= help */

function pb_page_help() {
	echo '<div class="wrap pb-wrap pb-help"><h1><i class="fa-solid fa-circle-question"></i> Panduan Ringkas Borang Program</h1>
	<ol>
	<li><strong>Bina borang</strong> — <em>Borang Program → + Bina Borang Baharu</em>. Beri nama (cth. "Khatan Perdana 2026") dan pilih templat.</li>
	<li><strong>Semak teks pengenalan</strong> — kotak editor di atas: syarat, perkara perlu dibawa dan lain-lain.</li>
	<li><strong>Ubah medan</strong> — klik pada medan untuk tukar tajuk / pilihan / wajib. Seret untuk susun. Tambah medan baharu dengan butang di bawah senarai.</li>
	<li><strong>Isi maklumat program &amp; tetapan</strong> — tarikh, masa, tempat, yuran, tarikh tutup dan kuota.</li>
	<li><strong>Terbitkan</strong>, kemudian klik <strong>Cipta halaman</strong> di kotak kanan. Salin pautan dan kongsi di WhatsApp / Facebook.</li>
	<li><strong>Urus pendaftaran</strong> — <em>Senarai Pendaftaran</em>: tukar status (Disahkan / Senarai Menunggu / Hadir / Batal), cetak senarai kehadiran, muat turun CSV (Excel), atau tambah pendaftaran secara manual.</li>
	</ol>
	<h2>Tips</h2>
	<ul>
	<li>Untuk program tahun depan, gunakan <strong>Salin borang</strong> pada senarai borang — medan dan teks disalin, tarikh dikosongkan.</li>
	<li>Medan <strong>Tarikh</strong> boleh menyemak umur: isi "Umur minimum / maksimum" — umur dikira pada <em>Tarikh program</em>.</li>
	<li>Jangan padam medan selepas pendaftaran diterima — data lama untuk medan itu tidak akan dipaparkan lagi. Tukar tajuknya sahaja jika perlu.</li>
	<li>Maklumat pendaftar (termasuk No. KP dan kesihatan) hanya boleh dilihat oleh AJK yang log masuk. Padam borang lama selepas program selesai jika data tidak lagi diperlukan.</li>
	</ul></div>';
}
