<?php
defined( 'ABSPATH' ) || exit;

function pb_entries_url( $args = [] ) { return add_query_arg( $args, admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-entri' ) ); }
function pb_get_entry( $eid ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . pb_t() . ' WHERE id=%d', $eid ), ARRAY_A );
	if ( $r ) { $r['data'] = (array) json_decode( (string) $r['data'], true ); $r['fail'] = (array) json_decode( (string) $r['fail'], true ); }
	return $r;
}
/** Fields shown as list columns: those ticked "Papar dalam senarai", else the first three inputs. */
function pb_list_fields( $id ) {
	$in  = array_filter( pb_input_fields( $id ), fn( $f ) => ! in_array( $f['type'], [ 'file', 'consent' ], true ) );
	$sel = array_values( array_filter( $in, fn( $f ) => ! empty( $f['list'] ) ) );
	return $sel ?: array_slice( array_values( $in ), 0, 3 );
}
function pb_forms() { return get_posts( [ 'post_type' => PB_CPT, 'post_status' => [ 'publish', 'draft', 'private', 'future' ], 'numberposts' => 200, 'orderby' => 'date', 'order' => 'DESC' ] ); }

/* ================================================================= page router */

function pb_page_entries() {
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.' ); }
	$forms = pb_forms();
	$id    = absint( pb_gs( 'borang' ) ) ?: ( $forms ? $forms[0]->ID : 0 );
	echo '<div class="wrap pb-wrap">';
	if ( ! $id || get_post_type( $id ) !== PB_CPT ) {
		echo '<h1>Senarai Pendaftaran</h1><p>Tiada borang lagi. <a href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT . '&page=pb-baharu' ) ) . '">Bina borang pertama</a>.</p></div>';
		return;
	}
	if ( $msg = pb_flash_get( 'pb_msg' ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( is_string( $msg ) ? $msg : '' ) . '</p></div>'; }
	$view = sanitize_key( pb_gs( 'view' ) );
	if ( 'lihat' === $view ) { pb_view_entry( $id, absint( pb_gs( 'e' ) ), false ); }
	elseif ( 'sunting' === $view ) { pb_view_entry( $id, absint( pb_gs( 'e' ) ), true ); }
	elseif ( 'tambah' === $view ) { pb_view_add( $id ); }
	else { pb_view_list( $id, $forms ); }
	echo '</div>';
}

/* ================================================================= list */

function pb_view_list( $id, $forms ) {
	global $wpdb;
	$s      = pb_settings( $id );
	$status = sanitize_key( pb_gs( 'status' ) );
	$q      = mb_substr( trim( pb_gs( 'q' ) ), 0, 60 );
	$counts = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) n FROM ' . pb_t() . ' WHERE borang_id=%d GROUP BY status', $id ), OBJECT_K );
	$total  = array_sum( array_map( fn( $r ) => (int) $r->n, $counts ) );

	$where = $wpdb->prepare( 'borang_id=%d', $id );
	if ( $status && isset( pb_statuses()[ $status ] ) ) { $where .= $wpdb->prepare( ' AND status=%s', $status ); }
	if ( '' !== $q ) {
		$like  = '%' . $wpdb->esc_like( $q ) . '%';
		$likeu = '%' . $wpdb->esc_like( trim( wp_json_encode( $q ), '"' ) ) . '%'; // JSON-escaped form (non-ASCII)
		$where .= $wpdb->prepare( ' AND (no_rujukan LIKE %s OR data LIKE %s OR data LIKE %s OR kunci LIKE %s)', $like, $like, $likeu, '%' . $wpdb->esc_like( strtolower( preg_replace( '/[\s\-]+/', '', $q ) ) ) . '%' );
	}
	$rows = $wpdb->get_results( 'SELECT * FROM ' . pb_t() . " WHERE $where ORDER BY id DESC LIMIT 1000", ARRAY_A );
	$cols = pb_list_fields( $id );

	echo '<h1 class="wp-heading-inline"><i class="fa-solid fa-list-check"></i> Senarai Pendaftaran</h1> ';
	echo '<a class="page-title-action" href="' . esc_url( pb_entries_url( [ 'borang' => $id, 'view' => 'tambah' ] ) ) . '"><i class="fa-solid fa-user-plus"></i> Tambah manual</a>';
	echo '<hr class="wp-header-end">';

	// form switcher + summary
	echo '<form method="get" class="pb-toolbar"><input type="hidden" name="post_type" value="' . PB_CPT . '"><input type="hidden" name="page" value="pb-entri">'
		. '<label class="screen-reader-text" for="pb-sel">Borang</label><select id="pb-sel" name="borang" onchange="this.form.submit()">';
	foreach ( $forms as $f ) { echo '<option value="' . (int) $f->ID . '"' . selected( $f->ID, $id, false ) . '>' . esc_html( $f->post_title . ( 'publish' !== $f->post_status ? ' (draf)' : '' ) ) . '</option>'; }
	echo '</select> <input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Cari nama, No. KP, telefon, rujukan…"> <button class="button">Cari</button>';
	if ( $status ) { echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">'; }
	echo ' <a class="button" href="' . esc_url( get_edit_post_link( $id ) ) . '"><i class="fa-solid fa-pen"></i> Sunting borang</a>';
	if ( $s['halaman'] && get_post( $s['halaman'] ) ) { echo ' <a class="button" target="_blank" rel="noopener" href="' . esc_url( get_permalink( $s['halaman'] ) ) . '"><i class="fa-solid fa-eye"></i> Lihat halaman</a>'; }
	echo '</form>';

	$st = pb_state( $id );
	echo '<div class="pb-kpis"><div class="pb-kpi"><span>Jumlah</span><strong>' . $total . '</strong></div>';
	if ( (int) $s['kuota'] ) { echo '<div class="pb-kpi"><span>Kuota (aktif)</span><strong>' . pb_count_active( $id ) . ' / ' . (int) $s['kuota'] . '</strong></div>'; }
	foreach ( pb_statuses() as $k => $v ) { echo '<div class="pb-kpi pb-kpi-' . esc_attr( $v[1] ) . '"><span>' . esc_html( $v[0] ) . '</span><strong>' . (int) ( $counts[ $k ]->n ?? 0 ) . '</strong></div>'; }
	echo '<div class="pb-kpi"><span>Borang</span><strong class="pb-state pb-state-' . esc_attr( $st ) . '">' . esc_html( pb_state_label( $st ) ) . '</strong></div></div>';

	// status tabs
	echo '<ul class="subsubsub"><li><a href="' . esc_url( pb_entries_url( [ 'borang' => $id ] ) ) . '"' . ( ! $status ? ' class="current"' : '' ) . '>Semua <span class="count">(' . $total . ')</span></a></li>';
	foreach ( pb_statuses() as $k => $v ) { echo '<li> | <a href="' . esc_url( pb_entries_url( [ 'borang' => $id, 'status' => $k ] ) ) . '"' . ( $status === $k ? ' class="current"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . (int) ( $counts[ $k ]->n ?? 0 ) . ')</span></a></li>'; }
	echo '</ul>';

	// exports
	$exp = fn( $a ) => wp_nonce_url( admin_url( 'admin-post.php?action=' . $a . '&borang=' . $id . ( $status ? '&status=' . $status : '' ) ), $a . '_' . $id );
	echo '<p class="pb-exports"><a class="button" href="' . esc_url( $exp( 'pb_csv' ) ) . '"><i class="fa-solid fa-file-csv"></i> Muat turun CSV (Excel)</a> '
		. '<a class="button" target="_blank" href="' . esc_url( $exp( 'pb_cetak' ) ) . '"><i class="fa-solid fa-print"></i> Cetak senarai' . ( $status ? ' (' . esc_html( pb_status_label( $status ) ) . ')' : ' (kecuali batal)' ) . '</a></p>';

	// table + bulk
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pb_pukal"><input type="hidden" name="borang" value="' . $id . '">' . wp_nonce_field( 'pb_pukal_' . $id, '_wpnonce', true, false );
	echo '<div class="tablenav top"><div class="alignleft actions bulkactions"><select name="pukal"><option value="">Tindakan pukal</option>';
	foreach ( pb_statuses() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '">Tukar status → ' . esc_html( $v[0] ) . '</option>'; }
	if ( current_user_can( 'manage_options' ) ) { echo '<option value="padam">Padam kekal</option>'; }
	echo '</select> <button class="button" onclick="var s=this.form.pukal.value;return s!==\'padam\'||confirm(\'Padam kekal pendaftaran yang dipilih?\')">Laksana</button></div>'
		. '<div class="tablenav-pages"><span class="displaying-num">' . count( $rows ) . ' rekod</span></div></div>';
	echo '<table class="wp-list-table widefat fixed striped pb-table"><thead><tr><td class="manage-column check-column"><input type="checkbox" onclick="document.querySelectorAll(\'.pb-cb\').forEach(c=>c.checked=this.checked)"></td><th style="width:8em">No. Rujukan</th>';
	foreach ( $cols as $c ) { echo '<th>' . esc_html( $c['label'] ) . '</th>'; }
	echo '<th style="width:10em">Status</th><th style="width:11em">Tarikh daftar</th></tr></thead><tbody>';
	if ( ! $rows ) { echo '<tr><td colspan="' . ( count( $cols ) + 4 ) . '"><em>Tiada pendaftaran' . ( $q ? ' yang sepadan' : ' lagi' ) . '.</em></td></tr>'; }
	foreach ( $rows as $r ) {
		$d   = (array) json_decode( (string) $r['data'], true );
		$url = pb_entries_url( [ 'borang' => $id, 'view' => 'lihat', 'e' => $r['id'] ] );
		echo '<tr><th class="check-column"><input class="pb-cb" type="checkbox" name="e[]" value="' . (int) $r['id'] . '"></th><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $r['no_rujukan'] ) . '</strong></a>' . ( 'manual' === $r['sumber'] ? '<br><small class="pb-muted">manual</small>' : '' ) . '</td>';
		foreach ( $cols as $i => $c ) {
			$v = esc_html( pb_display_value( $c, $d[ $c['id'] ] ?? '' ) );
			echo '<td>' . ( 0 === $i ? '<a href="' . esc_url( $url ) . '">' . ( $v ?: '—' ) . '</a>' : $v ) . '</td>';
		}
		echo '<td>' . pb_status_badge( $r['status'] ) . ( $r['catatan'] ? ' <i class="fa-regular fa-comment pb-muted" title="' . esc_attr( $r['catatan'] ) . '"></i>' : '' ) . '</td><td>' . esc_html( mysql2date( 'j M Y, g:i a', $r['dicipta'] ) ) . '</td></tr>';
	}
	echo '</tbody></table></form>';
	if ( count( $rows ) >= 1000 ) { echo '<p class="description">Memaparkan 1000 rekod terkini. Gunakan carian atau CSV untuk semua.</p>'; }
}

/* ================================================================= single entry */

function pb_view_entry( $id, $eid, $edit ) {
	$r = pb_get_entry( $eid );
	if ( ! $r || (int) $r['borang_id'] !== $id ) { echo '<h1>Pendaftaran tidak ditemui</h1><p><a href="' . esc_url( pb_entries_url( [ 'borang' => $id ] ) ) . '">&larr; Kembali</a></p>'; return; }
	$back = pb_entries_url( [ 'borang' => $id ] );
	echo '<p><a href="' . esc_url( $back ) . '">&larr; Senarai pendaftaran — ' . esc_html( get_the_title( $id ) ) . '</a></p>';
	echo '<h1><i class="fa-solid fa-id-badge"></i> ' . esc_html( $r['no_rujukan'] ) . ' ' . pb_status_badge( $r['status'] ) . '</h1>';

	if ( $edit ) {
		$has_file = (bool) array_filter( pb_fields( $id ), fn( $f ) => 'file' === $f['type'] );
		echo '<form method="post" class="pb-admin-form pb-ui" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $has_file ? ' enctype="multipart/form-data"' : '' ) . '><input type="hidden" name="action" value="pb_kemaskini"><input type="hidden" name="e" value="' . (int) $eid . '">' . wp_nonce_field( 'pb_kemaskini_' . $eid, '_wpnonce', true, false )
			. pb_render_fields( $id, $r['data'] )
			. ( $has_file ? '<p class="description">Fail sedia ada dikekalkan kecuali anda memuat naik fail baharu.</p>' : '' )
			. '<p><button class="button button-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan perubahan</button> <a class="button" href="' . esc_url( pb_entries_url( [ 'borang' => $id, 'view' => 'lihat', 'e' => $eid ] ) ) . '">Batal</a></p></form>';
		return;
	}

	echo '<div class="pb-entry-grid"><div class="pb-card"><table class="widefat striped pb-detail"><tbody>';
	foreach ( pb_fields( $id ) as $f ) {
		if ( 'heading' === $f['type'] ) { echo '<tr class="pb-detail-h"><th colspan="2">' . esc_html( $f['label'] ) . '</th></tr>'; continue; }
		if ( ! pb_is_input( $f['type'] ) ) { continue; }
		if ( 'file' === $f['type'] ) {
			$rel = $r['fail'][ $f['id'] ] ?? '';
			echo '<tr><th>' . esc_html( $f['label'] ) . '</th><td>' . ( $rel && pb_private_path( $rel ) ? '<a target="_blank" href="' . esc_url( pb_file_url( $rel ) ) . '"><i class="fa-solid fa-paperclip"></i> Buka fail</a>' : '—' ) . '</td></tr>';
			continue;
		}
		$v = pb_display_value( $f, $r['data'][ $f['id'] ] ?? '' );
		echo '<tr><th>' . esc_html( 'consent' === $f['type'] ? pb_short( $f['label'], 70 ) : $f['label'] ) . '</th><td>' . ( '' !== $v ? nl2br( esc_html( $v ) ) : '<span class="pb-muted">—</span>' ) . '</td></tr>';
	}
	// values for fields that were removed from the form after this entry was made
	$known = wp_list_pluck( pb_fields( $id ), 'id' );
	foreach ( $r['data'] as $k => $v ) { if ( ! in_array( $k, $known, true ) && '' !== $v && [] !== $v ) { echo '<tr><th class="pb-muted">(medan lama) ' . esc_html( $k ) . '</th><td>' . esc_html( is_array( $v ) ? implode( ', ', $v ) : $v ) . '</td></tr>'; } }
	echo '</tbody></table></div>';

	echo '<div class="pb-card pb-side"><h2>Tindakan AJK</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pb_status"><input type="hidden" name="e" value="' . (int) $eid . '">' . wp_nonce_field( 'pb_status_' . $eid, '_wpnonce', true, false )
		. '<p><label for="pb-st"><strong>Status</strong></label><br><select id="pb-st" name="status">';
	foreach ( pb_statuses() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['status'], $k, false ) . '>' . esc_html( $v[0] ) . '</option>'; }
	echo '</select></p><p><label for="pb-cat"><strong>Catatan dalaman</strong></label><textarea id="pb-cat" class="widefat" rows="4" name="catatan" maxlength="1000">' . esc_textarea( (string) $r['catatan'] ) . '</textarea></p>'
		. '<p><button class="button button-primary"><i class="fa-solid fa-check"></i> Simpan</button></p></form>';
	$hp = '';
	foreach ( pb_input_fields( $id ) as $f ) { if ( 'tel' === $f['type'] && ! empty( $r['data'][ $f['id'] ] ) ) { $hp = pb_digits( $r['data'][ $f['id'] ] ); break; } }
	if ( $hp ) {
		$wa = ( 0 === strpos( $hp, '0' ) ? '6' : '' ) . $hp;
		echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . $wa ) . '"><i class="fa-brands fa-whatsapp"></i> WhatsApp pendaftar</a></p>';
	}
	echo '<p><a class="button" href="' . esc_url( pb_entries_url( [ 'borang' => $id, 'view' => 'sunting', 'e' => $eid ] ) ) . '"><i class="fa-solid fa-pen"></i> Sunting maklumat</a></p>';
	echo '<hr><p class="pb-muted small">Didaftar: ' . esc_html( mysql2date( 'j M Y, g:i a', $r['dicipta'] ) ) . ' (' . esc_html( 'manual' === $r['sumber'] ? 'manual oleh AJK' : 'dalam talian' ) . ')';
	if ( $r['dikemaskini'] ) { $u = get_userdata( (int) $r['oleh'] ); echo '<br>Dikemas kini: ' . esc_html( mysql2date( 'j M Y, g:i a', $r['dikemaskini'] ) ) . ( $u ? ' oleh ' . esc_html( $u->display_name ) : '' ); }
	echo '</p>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Padam kekal pendaftaran ini?\')"><input type="hidden" name="action" value="pb_padam"><input type="hidden" name="e" value="' . (int) $eid . '">' . wp_nonce_field( 'pb_padam_' . $eid, '_wpnonce', true, false ) . '<button class="button-link pb-danger"><i class="fa-solid fa-trash"></i> Padam kekal</button></form>';
	}
	echo '</div></div>';
}

/* ================================================================= manual add */

function pb_view_add( $id ) {
	$has_file = (bool) array_filter( pb_fields( $id ), fn( $f ) => 'file' === $f['type'] );
	echo '<p><a href="' . esc_url( pb_entries_url( [ 'borang' => $id ] ) ) . '">&larr; Senarai pendaftaran</a></p><h1><i class="fa-solid fa-user-plus"></i> Tambah pendaftaran manual — ' . esc_html( get_the_title( $id ) ) . '</h1>'
		. '<p class="description">Untuk pendaftaran di kaunter / borang kertas. Kuota dan tarikh tutup tidak dikenakan; medan wajib tetap disemak.</p>'
		. '<form method="post" class="pb-admin-form pb-ui" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $has_file ? ' enctype="multipart/form-data"' : '' ) . '><input type="hidden" name="action" value="pb_manual"><input type="hidden" name="borang" value="' . $id . '">' . wp_nonce_field( 'pb_manual_' . $id, '_wpnonce', true, false )
		. pb_render_fields( $id, [] )
		. '<p><label><strong>Status</strong> <select name="status">';
	foreach ( pb_statuses() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, 'sah', false ) . '>' . esc_html( $v[0] ) . '</option>'; }
	echo '</select></label></p><p><button class="button button-primary button-large"><i class="fa-solid fa-floppy-disk"></i> Simpan pendaftaran</button></p></form>';
}

/* ================================================================= actions */

function pb_back( $id, $msg, $args = [] ) {
	wp_safe_redirect( pb_entries_url( array_merge( [ 'borang' => $id, 'pb_msg' => pb_flash_set( $msg ) ], $args ) ) );
	exit;
}
function pb_admin_errors( $errs ) {
	wp_die( '<h1>Sila semak semula</h1><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $errs ) ) . '</li></ul><p><a href="javascript:history.back()">&larr; Kembali ke borang</a></p>', 'Ralat', [ 'response' => 200 ] );
}

add_action( 'admin_post_pb_manual', function () {
	$id = absint( $_POST['borang'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_manual_' . $id );
	if ( get_post_type( $id ) !== PB_CPT ) { wp_die( 'Borang tidak ditemui.', 404 ); }
	[ $values, $errs ] = pb_collect( $id );
	if ( $errs ) { pb_admin_errors( $errs ); }
	[ $files, $ferrs ] = pb_collect_files( $id, false );
	if ( $ferrs ) { pb_admin_errors( $ferrs ); }
	$st  = sanitize_key( $_POST['status'] ?? 'sah' );
	$res = pb_insert_entry( $id, $values, $files, isset( pb_statuses()[ $st ] ) ? $st : 'sah', 'manual' );
	if ( is_wp_error( $res ) ) { foreach ( $files as $rel ) { pb_delete_private( $rel ); } pb_admin_errors( $res->get_error_messages() ); }
	pb_back( $id, 'Pendaftaran ' . $res[1] . ' disimpan.', [ 'view' => 'lihat', 'e' => $res[0] ] );
} );

add_action( 'admin_post_pb_kemaskini', function () {
	global $wpdb;
	$eid = absint( $_POST['e'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_kemaskini_' . $eid );
	$r = pb_get_entry( $eid );
	if ( ! $r ) { wp_die( 'Tidak ditemui.', 404 ); }
	$id = (int) $r['borang_id'];
	[ $values, $errs ] = pb_collect( $id );
	if ( $errs ) { pb_admin_errors( $errs ); }
	[ $files, $ferrs ] = pb_collect_files( $id, false );
	if ( $ferrs ) { pb_admin_errors( $ferrs ); }
	$s     = pb_settings( $id );
	$kunci = $s['unik'] && isset( $values[ $s['unik'] ] ) && is_scalar( $values[ $s['unik'] ] ) ? mb_substr( strtolower( preg_replace( '/[\s\-]+/', '', (string) $values[ $s['unik'] ] ) ), 0, 100 ) : '';
	if ( $kunci && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . pb_t() . " WHERE borang_id=%d AND kunci=%s AND status<>'batal' AND id<>%d LIMIT 1", $id, $kunci, $eid ) ) ) {
		foreach ( $files as $rel ) { pb_delete_private( $rel ); }
		pb_admin_errors( [ 'Nilai medan unik ini sudah digunakan oleh pendaftaran lain.' ] );
	}
	foreach ( $files as $k => $rel ) { if ( ! empty( $r['fail'][ $k ] ) ) { pb_delete_private( $r['fail'][ $k ] ); } }
	$wpdb->update( pb_t(), [
		'data'        => wp_json_encode( array_merge( $r['data'], $values ), JSON_UNESCAPED_UNICODE ),
		'fail'        => wp_json_encode( array_merge( $r['fail'], $files ) ),
		'kunci'       => $kunci,
		'dikemaskini' => current_time( 'mysql' ),
		'oleh'        => get_current_user_id(),
	], [ 'id' => $eid ] );
	pb_back( $id, 'Maklumat ' . $r['no_rujukan'] . ' dikemas kini.', [ 'view' => 'lihat', 'e' => $eid ] );
} );

add_action( 'admin_post_pb_status', function () {
	global $wpdb;
	$eid = absint( $_POST['e'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_status_' . $eid );
	$r = pb_get_entry( $eid );
	if ( ! $r ) { wp_die( 'Tidak ditemui.', 404 ); }
	$st = sanitize_key( $_POST['status'] ?? '' );
	$wpdb->update( pb_t(), [
		'status'      => isset( pb_statuses()[ $st ] ) ? $st : $r['status'],
		'catatan'     => mb_substr( sanitize_textarea_field( wp_unslash( (string) ( is_scalar( $_POST['catatan'] ?? '' ) ? ( $_POST['catatan'] ?? '' ) : '' ) ) ), 0, 1000 ),
		'dikemaskini' => current_time( 'mysql' ),
		'oleh'        => get_current_user_id(),
	], [ 'id' => $eid ] );
	pb_back( (int) $r['borang_id'], 'Status ' . $r['no_rujukan'] . ' disimpan.', [ 'view' => 'lihat', 'e' => $eid ] );
} );

add_action( 'admin_post_pb_padam', function () {
	global $wpdb;
	$eid = absint( $_POST['e'] ?? 0 );
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( PB_CAP ) ) { wp_die( 'Hanya pentadbir boleh memadam.', 403 ); }
	check_admin_referer( 'pb_padam_' . $eid );
	$r = pb_get_entry( $eid );
	if ( ! $r ) { wp_die( 'Tidak ditemui.', 404 ); }
	foreach ( $r['fail'] as $rel ) { pb_delete_private( $rel ); }
	$wpdb->delete( pb_t(), [ 'id' => $eid ] );
	pb_back( (int) $r['borang_id'], 'Pendaftaran ' . $r['no_rujukan'] . ' dipadam.' );
} );

add_action( 'admin_post_pb_pukal', function () {
	global $wpdb;
	$id = absint( $_POST['borang'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_pukal_' . $id );
	$ids = array_filter( array_map( 'absint', (array) ( $_POST['e'] ?? [] ) ) );
	$act = sanitize_key( $_POST['pukal'] ?? '' );
	if ( ! $ids || ! $act ) { pb_back( $id, 'Tiada rekod atau tindakan dipilih.' ); }
	$n = 0;
	foreach ( $ids as $eid ) {
		$r = pb_get_entry( $eid );
		if ( ! $r || (int) $r['borang_id'] !== $id ) { continue; }
		if ( 'padam' === $act ) {
			if ( ! current_user_can( 'manage_options' ) ) { break; }
			foreach ( $r['fail'] as $rel ) { pb_delete_private( $rel ); }
			$n += (int) $wpdb->delete( pb_t(), [ 'id' => $eid ] );
		} elseif ( isset( pb_statuses()[ $act ] ) ) {
			$n += (int) $wpdb->update( pb_t(), [ 'status' => $act, 'dikemaskini' => current_time( 'mysql' ), 'oleh' => get_current_user_id() ], [ 'id' => $eid ] );
		}
	}
	pb_back( $id, $n . ' rekod dikemas kini.' );
} );

/* ================================================================= CSV & print */

function pb_export_rows( $id, $status, $exclude_cancelled ) {
	global $wpdb;
	$where = $wpdb->prepare( 'borang_id=%d', $id );
	if ( $status && isset( pb_statuses()[ $status ] ) ) { $where .= $wpdb->prepare( ' AND status=%s', $status ); }
	elseif ( $exclude_cancelled ) { $where .= " AND status<>'batal'"; }
	return $wpdb->get_results( 'SELECT * FROM ' . pb_t() . " WHERE $where ORDER BY id ASC", ARRAY_A );
}

add_action( 'admin_post_pb_csv', function () {
	$id = absint( $_GET['borang'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_csv_' . $id );
	if ( get_post_type( $id ) !== PB_CPT ) { wp_die( 'Borang tidak ditemui.', 404 ); }
	$fields = array_values( array_filter( pb_input_fields( $id ), fn( $f ) => 'file' !== $f['type'] ) );
	$rows   = pb_export_rows( $id, sanitize_key( pb_gs( 'status' ) ), false );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( sanitize_title( get_the_title( $id ) ) . '-' . current_time( 'Ymd-Hi' ) ) . '.csv"' );
	$o = fopen( 'php://output', 'w' );
	fwrite( $o, "\xEF\xBB\xBF" ); // BOM so Excel reads Malay text / dashes correctly
	fputcsv( $o, array_merge( [ 'No. Rujukan', 'Status', 'Tarikh daftar', 'Sumber' ], array_map( fn( $f ) => 'consent' === $f['type'] ? pb_short( $f['label'], 50 ) : $f['label'], $fields ), [ 'Catatan AJK' ] ) );
	foreach ( $rows as $r ) {
		$d    = (array) json_decode( (string) $r['data'], true );
		$line = [ $r['no_rujukan'], pb_status_label( $r['status'] ), $r['dicipta'], $r['sumber'] ];
		foreach ( $fields as $f ) {
			$line[] = pb_display_value( $f, $d[ $f['id'] ] ?? '' ); // IC exported with dashes, so Excel keeps it as text
		}
		$line[] = (string) $r['catatan'];
		fputcsv( $o, array_map( 'pb_csv_cell', $line ) );
	}
	fclose( $o );
	exit;
} );

add_action( 'admin_post_pb_cetak', function () {
	$id = absint( $_GET['borang'] ?? 0 );
	if ( ! current_user_can( PB_CAP ) ) { wp_die( 'Tiada kebenaran.', 403 ); }
	check_admin_referer( 'pb_cetak_' . $id );
	if ( get_post_type( $id ) !== PB_CPT ) { wp_die( 'Borang tidak ditemui.', 404 ); }
	$status = sanitize_key( pb_gs( 'status' ) );
	$rows   = pb_export_rows( $id, $status, true );
	$cols   = pb_list_fields( $id );
	$s      = pb_settings( $id );
	nocache_headers();
	echo '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>' . esc_html( get_the_title( $id ) ) . ' — Senarai</title><style>
		@page{size:A4 landscape;margin:12mm}body{font:12px/1.4 system-ui,Segoe UI,Arial,sans-serif;color:#111;margin:0 0 20px}
		h1{font-size:18px;margin:0 0 4px}.meta{color:#555;margin-bottom:10px}table{width:100%;border-collapse:collapse}
		th,td{border:1px solid #999;padding:5px 6px;text-align:left;vertical-align:top}th{background:#eef3ee}td.sig{width:110px}
		tr{page-break-inside:avoid}.no{width:28px;text-align:center}.tools{margin:10px 0}@media print{.tools{display:none}}
	</style></head><body><div class="tools"><button onclick="window.print()">Cetak</button></div>';
	echo '<h1>' . esc_html( get_the_title( $id ) ) . '</h1><div class="meta">' . esc_html( get_bloginfo( 'name' ) )
		. ( $s['tarikh_program'] ? ' · ' . esc_html( pb_fmt_date( $s['tarikh_program'] ) ) : '' ) . ( $s['tempat'] ? ' · ' . esc_html( $s['tempat'] ) : '' )
		. ' · ' . count( $rows ) . ' peserta' . ( $status ? ' (' . esc_html( pb_status_label( $status ) ) . ')' : '' ) . ' · dicetak ' . esc_html( current_time( 'j/n/Y g:i a' ) ) . '</div>';
	echo '<table><thead><tr><th class="no">Bil</th><th>No. Rujukan</th>';
	foreach ( $cols as $c ) { echo '<th>' . esc_html( $c['label'] ) . '</th>'; }
	echo '<th>Status</th><th>Hadir / Tandatangan</th></tr></thead><tbody>';
	foreach ( $rows as $i => $r ) {
		$d = (array) json_decode( (string) $r['data'], true );
		echo '<tr><td class="no">' . ( $i + 1 ) . '</td><td>' . esc_html( $r['no_rujukan'] ) . '</td>';
		foreach ( $cols as $c ) { echo '<td>' . esc_html( pb_display_value( $c, $d[ $c['id'] ] ?? '' ) ) . '</td>'; }
		echo '<td>' . esc_html( pb_status_label( $r['status'] ) ) . '</td><td class="sig"></td></tr>';
	}
	echo '</tbody></table></body></html>';
	exit;
} );
