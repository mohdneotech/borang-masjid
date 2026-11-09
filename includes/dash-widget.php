<?php
/**
 * WordPress Dashboard (wp-admin/index.php) widget: "Borang Program" — registrations per form.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( PB_CAP ) ) { return; }
	wp_add_dashboard_widget( 'pb_dw_borang', 'Borang Program — Pendaftaran', 'pb_dw_render' );
} );

/** Forms worth showing: published, and either still open / upcoming or with a programme date in the last 30 days. */
function pb_dw_forms( $limit = 4 ) {
	$ids   = get_posts( [ 'post_type' => PB_CPT, 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ] );
	$today = current_time( 'Y-m-d' );
	$rank  = [ 'buka' => 0, 'menunggu' => 1, 'belum' => 2, 'penuh' => 3, 'tutup' => 4 ];
	$out   = [];
	foreach ( $ids as $id ) {
		$s  = pb_settings( $id );
		$st = pb_state( $id );
		$tp = (string) $s['tarikh_program'];
		if ( 'tutup' === $st && $tp && $tp < gmdate( 'Y-m-d', strtotime( $today . ' -30 days' ) ) ) { continue; }
		$out[] = [ 'id' => $id, 's' => $s, 'st' => $st, 'r' => ( $rank[ $st ] ?? 9 ) . ( $tp ?: '9999' ) ];
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['r'], $b['r'] ) );
	return [ array_slice( $out, 0, $limit ), count( $out ) ];
}

function pb_dw_render() {
	global $wpdb;
	[ $forms, $total ] = pb_dw_forms();
	$T        = pb_t();
	$stat     = pb_statuses();
	$colours  = [ 'baru' => 'new', 'sah' => 'ok', 'menunggu' => 'warn', 'hadir' => 'info', 'batal' => 'mute', 'tolak' => 'bad' ];

	$all     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $T" );
	$baru    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $T WHERE status='baru'" );
	$minggu  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $T WHERE dicipta >= %s", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - WEEK_IN_SECONDS ) ) );
	$dibuka  = count( array_filter( $forms, fn( $f ) => in_array( $f['st'], [ 'buka', 'menunggu' ], true ) ) );

	echo '<div class="pbdw">';
	echo '<div class="pbdw-sum"><span><b>' . (int) $dibuka . '</b> borang dibuka</span><span><b>' . number_format_i18n( $minggu ) . '</b> pendaftaran 7 hari lalu</span><span><b>' . number_format_i18n( $all ) . '</b> jumlah pendaftaran</span></div>';
	if ( $baru ) {
		echo '<p class="pbdw-alert"><span class="dashicons dashicons-warning"></span> <a href="' . esc_url( pb_entries_url( [ 'status' => 'baru' ] ) ) . '">' . number_format_i18n( $baru ) . ' pendaftaran Baharu belum disemak</a></p>';
	}
	if ( ! $forms ) {
		echo '<p class="description">Tiada borang aktif. <a href="' . esc_url( admin_url( 'post-new.php?post_type=' . PB_CPT ) ) . '">Bina borang baharu →</a></p></div>';
		return;
	}

	foreach ( $forms as $f ) {
		$id   = $f['id'];
		$s    = $f['s'];
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) n FROM $T WHERE borang_id=%d GROUP BY status", $id ), OBJECT_K );
		$cnt  = array_map( fn( $r ) => (int) $r->n, $rows );
		$act  = pb_count_active( $id );
		$kuota = (int) $s['kuota'];
		$url  = pb_entries_url( [ 'borang' => $id ] );

		$meta = [];
		if ( $s['tarikh_program'] ) { $meta[] = 'Program: ' . esc_html( pb_fmt_date( $s['tarikh_program'] ) ); }
		if ( $s['tutup'] && in_array( $f['st'], [ 'buka', 'menunggu', 'belum' ], true ) ) {
			$left = (int) floor( ( strtotime( $s['tutup'] ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
			$meta[] = 'Tutup ' . ( $left < 1 ? '<b>hari ini</b>' : 'dalam ' . $left . ' hari' );
		}

		echo '<div class="pbdw-form"><div class="pbdw-form-h"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $id ) ) . '</a><span class="pbdw-chip is-' . esc_attr( $f['st'] ) . '">' . esc_html( pb_state_label( $f['st'] ) ) . '</span></div>';
		if ( $meta ) { echo '<div class="pbdw-form-meta">' . implode( ' · ', $meta ) . '</div>'; }

		// quota / places
		if ( $kuota > 0 ) {
			$pc = min( 100, round( $act / $kuota * 100 ) );
			echo '<div class="pbdw-quota"><span>Tempat diisi</span><b>' . number_format_i18n( $act ) . ' / ' . number_format_i18n( $kuota ) . ' <small>(' . (int) $pc . '%)</small></b></div>';
			echo '<div class="pbdw-bar"><i class="pbdw-c-' . ( $pc >= 100 ? 'bad' : ( $pc >= 80 ? 'warn' : 'ok' ) ) . '" style="width:' . (int) $pc . '%"></i></div>';
		} else {
			echo '<div class="pbdw-quota"><span>Pendaftaran aktif</span><b>' . number_format_i18n( $act ) . ' <small>(tiada had kuota)</small></b></div>';
		}

		// status breakdown
		$tot = array_sum( $cnt );
		if ( $tot ) {
			echo '<div class="pbdw-stack">';
			foreach ( $stat as $k => $l ) { if ( ! empty( $cnt[ $k ] ) ) { echo '<i class="pbdw-c-' . esc_attr( $colours[ $k ] ?? 'mute' ) . '" style="width:' . esc_attr( round( $cnt[ $k ] / $tot * 100, 2 ) ) . '%"></i>'; } }
			echo '</div><ul class="pbdw-legend">';
			foreach ( $stat as $k => $l ) {
				if ( empty( $cnt[ $k ] ) ) { continue; }
				echo '<li><a href="' . esc_url( pb_entries_url( [ 'borang' => $id, 'status' => $k ] ) ) . '"><span class="pbdw-dot pbdw-c-' . esc_attr( $colours[ $k ] ?? 'mute' ) . '"></span>' . esc_html( $l[0] ) . ' <b>' . number_format_i18n( $cnt[ $k ] ) . '</b></a></li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="pbdw-none">Belum ada pendaftaran.</p>';
		}
		echo '</div>';
	}
	if ( $total > count( $forms ) ) { echo '<p class="description">+ ' . (int) ( $total - count( $forms ) ) . ' borang lain.</p>'; }
	echo '<p class="pbdw-foot"><a href="' . esc_url( pb_entries_url() ) . '">Senarai Pendaftaran →</a> <a href="' . esc_url( admin_url( 'edit.php?post_type=' . PB_CPT ) ) . '">Semua Borang</a></p>';
	echo '</div>';
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'index.php' !== $hook || ! current_user_can( PB_CAP ) ) { return; }
	wp_add_inline_style( 'dashboard', '
.pbdw-sum{display:flex;flex-wrap:wrap;gap:6px 16px;margin:0 0 10px;font-size:12.5px;color:#50575e}.pbdw-sum b{font-size:16px;color:#14532d}
.pbdw-alert{margin:0 0 10px;padding:7px 10px;background:#fcf9e8;border-left:4px solid #dba617}.pbdw-alert .dashicons{color:#996800}
.pbdw-form{border:1px solid #e2e4e7;border-radius:6px;padding:10px 12px;margin:0 0 10px}
.pbdw-form-h{display:flex;gap:8px;align-items:flex-start;justify-content:space-between}
.pbdw-form-h a{font-weight:600;font-size:13.5px;text-decoration:none}
.pbdw-form-meta{font-size:12px;color:#646970;margin:2px 0 8px}
.pbdw-chip{flex:0 0 auto;font-size:11px;line-height:1;padding:4px 7px;border-radius:10px;background:#f0f0f1;color:#50575e;white-space:nowrap}
.pbdw-chip.is-buka{background:#d1fae5;color:#14532d}.pbdw-chip.is-menunggu,.pbdw-chip.is-belum{background:#fcf0c3;color:#6b4d00}
.pbdw-quota{display:flex;justify-content:space-between;gap:8px;font-size:12px;margin:6px 0 3px}.pbdw-quota small{font-weight:400;color:#646970}
.pbdw-bar,.pbdw-stack{display:flex;height:10px;border-radius:5px;overflow:hidden;background:#e2e4e7;margin:0 0 6px}
.pbdw-bar i,.pbdw-stack i{display:block;height:100%}
.pbdw-legend{display:flex;flex-wrap:wrap;gap:4px 14px;margin:0;font-size:12px}.pbdw-legend li{margin:0}
.pbdw-legend a{text-decoration:none;color:#1d2327}.pbdw-legend a:hover{color:#2271b1}
.pbdw-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px}
.pbdw-c-ok{background:#15803d}.pbdw-c-warn{background:#dba617}.pbdw-c-info{background:#2271b1}.pbdw-c-mute{background:#a7aaad}.pbdw-c-bad{background:#d63638}.pbdw-c-new{background:#72aee6}
.pbdw-none{margin:4px 0 0;color:#646970;font-size:12px}
.pbdw-foot{margin:12px -12px -12px;padding:9px 12px;border-top:1px solid #f0f0f1;background:#f6f7f7;display:flex;flex-wrap:wrap;gap:6px 16px}
' );
} );
