<?php
defined( 'ABSPATH' ) || exit;

/** Reference date for age checks: programme date, else today. */
function pb_ref_date( $id ) {
	$s = pb_settings( $id );
	return ( $s['tarikh_program'] && strtotime( $s['tarikh_program'] ) ) ? gmdate( 'Y-m-d', strtotime( $s['tarikh_program'] ) ) : current_time( 'Y-m-d' );
}
function pb_age_on( $dob, $ref ) {
	try { return ( new DateTime( $dob ) )->diff( new DateTime( $ref ) )->y; } catch ( Exception $e ) { return null; }
}
/** HTML min/max date attributes for an age range. */
function pb_age_bounds( $f, $ref ) {
	$b = [];
	if ( '' !== $f['max'] && is_numeric( $f['max'] ) ) { $b['min'] = gmdate( 'Y-m-d', strtotime( $ref . ' -' . ( (int) $f['max'] + 1 ) . ' years +1 day' ) ); }
	if ( '' !== $f['min'] && is_numeric( $f['min'] ) ) { $b['max'] = gmdate( 'Y-m-d', strtotime( $ref . ' -' . (int) $f['min'] . ' years' ) ); }
	return $b;
}

/**
 * Render the field controls.
 * @param int   $id     form id
 * @param array $values previously posted / stored values (field id => value)
 */
function pb_render_fields( $id, $values = [] ) {
	$ref = pb_ref_date( $id );
	ob_start();
	echo '<div class="row g-3">';
	foreach ( pb_fields( $id ) as $f ) {
		$fid  = $f['id'];
		$name = 'pb[' . $fid . ']';
		$dom  = 'pb-' . $id . '-' . $fid;
		$val  = $values[ $fid ] ?? '';
		$req  = $f['required'] ? ' required' : '';
		$star = $f['required'] ? ' <span class="text-danger" aria-hidden="true">*</span>' : '';
		$col  = 'half' === $f['width'] ? 'col-md-6' : 'col-12';
		$help = $f['help'] ? '<div class="form-text" id="' . esc_attr( $dom ) . '-h">' . esc_html( $f['help'] ) . '</div>' : '';
		$desc = $f['help'] ? ' aria-describedby="' . esc_attr( $dom ) . '-h"' : '';
		$ph   = $f['placeholder'] ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';

		switch ( $f['type'] ) {
			case 'heading':
				echo '<div class="col-12 pb-heading"><h3 class="h5 mb-0"><i class="fa-solid fa-circle-chevron-right text-mp me-2"></i>' . esc_html( $f['label'] ) . '</h3></div>';
				break;
			case 'paragraph':
				echo '<div class="col-12 pb-note small">' . wpautop( wp_kses( $f['label'], pb_kses_text() ) ) . '</div>';
				break;
			case 'textarea':
				echo '<div class="' . $col . '"><label class="form-label fw-semibold" for="' . esc_attr( $dom ) . '">' . esc_html( $f['label'] ) . $star . '</label>'
					. '<textarea class="form-control" rows="3" maxlength="2000" id="' . esc_attr( $dom ) . '" name="' . esc_attr( $name ) . '"' . $req . $ph . $desc . '>' . esc_textarea( is_scalar( $val ) ? $val : '' ) . '</textarea>' . $help . '</div>';
				break;
			case 'select':
				echo '<div class="' . $col . '"><label class="form-label fw-semibold" for="' . esc_attr( $dom ) . '">' . esc_html( $f['label'] ) . $star . '</label>'
					. '<select class="form-select" id="' . esc_attr( $dom ) . '" name="' . esc_attr( $name ) . '"' . $req . $desc . '><option value="">— Pilih —</option>';
				foreach ( $f['options'] as $o ) { echo '<option' . selected( $val, $o, false ) . '>' . esc_html( $o ) . '</option>'; }
				echo '</select>' . $help . '</div>';
				break;
			case 'radio':
			case 'checkbox':
				$multi = 'checkbox' === $f['type'];
				echo '<div class="' . $col . '"><fieldset' . $desc . ( $f['required'] && $multi ? ' data-pb-req-group="1"' : '' ) . '><legend class="form-label fw-semibold fs-6 mb-1">' . esc_html( $f['label'] ) . $star . '</legend><div class="pb-choices">';
				foreach ( $f['options'] as $i => $o ) {
					$chk = $multi ? in_array( $o, (array) $val, true ) : ( $val === $o );
					echo '<div class="form-check"><input class="form-check-input" type="' . ( $multi ? 'checkbox' : 'radio' ) . '" id="' . esc_attr( "$dom-$i" ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( $o ) . '"' . checked( $chk, true, false ) . ( $f['required'] && ! $multi ? ' required' : '' ) . '>'
						. '<label class="form-check-label" for="' . esc_attr( "$dom-$i" ) . '">' . esc_html( $o ) . '</label></div>';
				}
				echo '</div></fieldset>' . $help . '</div>';
				break;
			case 'consent':
				echo '<div class="col-12"><div class="form-check pb-consent"><input class="form-check-input" type="checkbox" value="1" id="' . esc_attr( $dom ) . '" name="' . esc_attr( $name ) . '"' . checked( (bool) $val, true, false ) . $req . '>'
					. '<label class="form-check-label" for="' . esc_attr( $dom ) . '">' . esc_html( $f['label'] ) . $star . '</label></div>' . $help . '</div>';
				break;
			case 'file':
				echo '<div class="' . $col . '"><label class="form-label fw-semibold" for="' . esc_attr( $dom ) . '">' . esc_html( $f['label'] ) . $star . '</label>'
					. '<input class="form-control" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" id="' . esc_attr( $dom ) . '" name="pb_f[' . esc_attr( $fid ) . ']"' . $req . $desc . '>'
					. ( $help ?: '' ) . '<div class="form-text">JPG, PNG atau PDF, maksimum 10 MB.</div></div>';
				break;
			default: // text, ic, tel, email, number, date
				$attr = '';
				$type = 'text';
				if ( 'ic' === $f['type'] ) { $attr = ' inputmode="numeric" maxlength="14" pattern="[0-9\-\s]{12,14}" autocomplete="off"'; $ph = $ph ?: ' placeholder="Contoh: 150412101234"'; }
				if ( 'tel' === $f['type'] ) { $type = 'tel'; $attr = ' inputmode="tel" maxlength="20" autocomplete="tel"'; }
				if ( 'email' === $f['type'] ) { $type = 'email'; $attr = ' maxlength="150" autocomplete="email"'; }
				if ( 'text' === $f['type'] ) { $attr = ' maxlength="190"'; }
				if ( 'number' === $f['type'] ) {
					$type = 'number'; $attr = ' step="any"';
					if ( is_numeric( $f['min'] ) ) { $attr .= ' min="' . esc_attr( $f['min'] ) . '"'; }
					if ( is_numeric( $f['max'] ) ) { $attr .= ' max="' . esc_attr( $f['max'] ) . '"'; }
				}
				if ( 'date' === $f['type'] ) {
					$type = 'date';
					foreach ( pb_age_bounds( $f, $ref ) as $k => $v ) { $attr .= ' ' . $k . '="' . esc_attr( $v ) . '"'; }
				}
				$shown = is_scalar( $val ) ? $val : '';
				if ( 'ic' === $f['type'] && $shown ) { $shown = pb_fmt_ic( $shown ); }
				echo '<div class="' . $col . '"><label class="form-label fw-semibold" for="' . esc_attr( $dom ) . '">' . esc_html( $f['label'] ) . $star . '</label>'
					. '<input class="form-control" type="' . $type . '" id="' . esc_attr( $dom ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $shown ) . '"' . $req . $ph . $attr . $desc . '>' . $help . '</div>';
		}
	}
	echo '</div>';
	return ob_get_clean();
}

/**
 * Validate posted values against the form definition.
 * @return array{0: array, 1: string[]} [ clean values, errors ]
 */
function pb_collect( $id ) {
	$in   = isset( $_POST['pb'] ) && is_array( $_POST['pb'] ) ? wp_unslash( $_POST['pb'] ) : [];
	$ref  = pb_ref_date( $id );
	$out  = [];
	$errs = [];
	foreach ( pb_input_fields( $id ) as $f ) {
		$fid = $f['id'];
		$lbl = $f['label'];
		if ( 'file' === $f['type'] ) { continue; } // handled after validation
		$raw = $in[ $fid ] ?? '';
		if ( 'checkbox' === $f['type'] ) {
			$v = array_values( array_intersect( $f['options'], array_map( 'strval', array_filter( (array) $raw, 'is_scalar' ) ) ) );
			if ( $f['required'] && ! $v ) { $errs[] = "Sila pilih sekurang-kurangnya satu: $lbl."; }
			$out[ $fid ] = $v;
			continue;
		}
		if ( ! is_scalar( $raw ) ) { $raw = ''; }
		$v = 'textarea' === $f['type'] ? mb_substr( sanitize_textarea_field( (string) $raw ), 0, 2000 ) : mb_substr( trim( sanitize_text_field( (string) $raw ) ), 0, 190 );

		if ( '' === $v || ( 'consent' === $f['type'] && ! $v ) ) {
			if ( $f['required'] ) { $errs[] = 'consent' === $f['type'] ? 'Sila tandakan akuan / persetujuan yang diwajibkan.' : "Sila isi: $lbl."; }
			$out[ $fid ] = 'consent' === $f['type'] ? 0 : '';
			continue;
		}
		switch ( $f['type'] ) {
			case 'ic':
				$v = pb_digits( $v );
				if ( 12 !== strlen( $v ) ) { $errs[] = "$lbl mestilah 12 digit."; }
				break;
			case 'tel':
				$d = pb_digits( $v );
				if ( strlen( $d ) < 9 || strlen( $d ) > 13 ) { $errs[] = "$lbl tidak sah."; }
				break;
			case 'email':
				if ( ! is_email( $v ) ) { $errs[] = "$lbl tidak sah."; }
				break;
			case 'number':
				if ( ! is_numeric( $v ) ) { $errs[] = "$lbl mestilah nombor."; break; }
				if ( is_numeric( $f['min'] ) && (float) $v < (float) $f['min'] ) { $errs[] = "$lbl mestilah sekurang-kurangnya {$f['min']}."; }
				if ( is_numeric( $f['max'] ) && (float) $v > (float) $f['max'] ) { $errs[] = "$lbl tidak boleh melebihi {$f['max']}."; }
				break;
			case 'date':
				$t = strtotime( $v );
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) || ! $t ) { $errs[] = "$lbl tidak sah."; break; }
				$age = pb_age_on( $v, $ref );
				if ( is_numeric( $f['min'] ) && ( null === $age || $age < (int) $f['min'] ) ) { $errs[] = "$lbl: umur mestilah sekurang-kurangnya {$f['min']} tahun pada " . pb_fmt_date( $ref, false ) . '.'; }
				if ( is_numeric( $f['max'] ) && ( null === $age || $age > (int) $f['max'] ) ) { $errs[] = "$lbl: umur tidak boleh melebihi {$f['max']} tahun pada " . pb_fmt_date( $ref, false ) . '.'; }
				break;
			case 'select':
			case 'radio':
				if ( ! in_array( $v, $f['options'], true ) ) { $errs[] = "Pilihan tidak sah: $lbl."; }
				break;
			case 'consent':
				$v = 1;
				break;
		}
		$out[ $fid ] = $v;
	}
	return [ $out, $errs ];
}

/** Store uploads for file fields. @return array{0: array, 1: string[]} */
function pb_collect_files( $id, $require = true ) {
	$files = [];
	$errs  = [];
	foreach ( pb_input_fields( $id ) as $f ) {
		if ( 'file' !== $f['type'] ) { continue; }
		$r = pb_store_upload( $f['id'] );
		if ( is_wp_error( $r ) ) { $errs[] = $f['label'] . ': ' . $r->get_error_message(); continue; }
		if ( ! $r && $f['required'] && $require ) { $errs[] = 'Sila muat naik: ' . $f['label'] . '.'; continue; }
		if ( $r ) { $files[ $f['id'] ] = $r; }
	}
	if ( $errs ) { foreach ( $files as $rel ) { pb_delete_private( $rel ); } $files = []; }
	return [ $files, $errs ];
}

/** Insert a registration row. Returns [ id, ref ] or WP_Error. */
function pb_insert_entry( $id, $values, $files, $status, $source = 'online' ) {
	global $wpdb;
	$s     = pb_settings( $id );
	$kunci = '';
	if ( $s['unik'] && isset( $values[ $s['unik'] ] ) && is_scalar( $values[ $s['unik'] ] ) ) {
		$kunci = mb_substr( strtolower( preg_replace( '/[\s\-]+/', '', (string) $values[ $s['unik'] ] ) ), 0, 100 );
	}
	if ( $kunci ) {
		$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT no_rujukan FROM ' . pb_t() . " WHERE borang_id=%d AND kunci=%s AND status<>'batal' LIMIT 1", $id, $kunci ) );
		if ( $dup ) {
			$lbl = '';
			foreach ( pb_fields( $id ) as $f ) { if ( $f['id'] === $s['unik'] ) { $lbl = $f['label']; } }
			return new WP_Error( 'dup', sprintf( '%s ini telah didaftarkan (No. Rujukan %s). Hubungi AJK jika perlu membuat perubahan.', $lbl ?: 'Maklumat', $dup ) );
		}
	}
	$ok = $wpdb->insert( pb_t(), [
		'borang_id' => $id,
		'kunci'     => $kunci,
		'data'      => wp_json_encode( $values, JSON_UNESCAPED_UNICODE ),
		'fail'      => $files ? wp_json_encode( $files ) : null,
		'status'    => $status,
		'sumber'    => $source,
		'ip_hash'   => 'online' === $source ? hash_hmac( 'sha256', pb_client_ip(), wp_salt( 'auth' ) ) : '',
		'dicipta'   => current_time( 'mysql' ),
		'oleh'      => get_current_user_id(),
	] );
	if ( ! $ok ) { return new WP_Error( 'db', 'Pendaftaran tidak dapat disimpan. Sila cuba lagi.' ); }
	$eid = (int) $wpdb->insert_id;
	// per-form running number; atomic increment so deleted rows never cause a reused reference
	add_post_meta( $id, '_pb_seq', 0, true );
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id=%d AND meta_key='_pb_seq'", $id ) );
	$seq = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_pb_seq'", $id ) );
	wp_cache_delete( $id, 'post_meta' );
	$pre = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $s['prefix'] ) ) ?: 'REG';
	$ref = $pre . '-' . str_pad( (string) $seq, 3, '0', STR_PAD_LEFT );
	$wpdb->update( pb_t(), [ 'no_rujukan' => $ref ], [ 'id' => $eid ] );
	return [ $eid, $ref ];
}
