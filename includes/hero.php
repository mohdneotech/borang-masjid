<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hero slides: every published form with "Papar sebagai slaid" ticked, while registration is open (or wait-listing),
 * adds a slide via the `masjid_hero_slides` filter (read by the Slaid Utama Masjid plugin's [slaid_masjid] slider)
 * and `perepat_hero_slides` (Perepat theme's built-in hero carousel).
 */

function pb_hero_forms() {
	static $ids = null;
	if ( null !== $ids ) { return $ids; }
	$ids = [];
	foreach ( get_posts( [ 'post_type' => PB_CPT, 'post_status' => 'publish', 'numberposts' => 20, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ] ) as $id ) {
		$s = pb_settings( $id );
		if ( empty( $s['slaid'] ) || ! $s['halaman'] || 'publish' !== get_post_status( $s['halaman'] ) ) { continue; }
		if ( ! in_array( pb_state( $id ), [ 'buka', 'menunggu' ], true ) ) { continue; }
		$ids[] = $id;
	}
	return $ids;
}

function pb_add_hero_slides( $slides ) {
	foreach ( pb_hero_forms() as $id ) { $slides[] = pb_hero_slide( $id ); }
	return $slides;
}
add_filter( 'masjid_hero_slides', 'pb_add_hero_slides' );
add_filter( 'perepat_hero_slides', 'pb_add_hero_slides' );

add_action( 'wp_enqueue_scripts', function () {
	if ( is_front_page() && pb_hero_forms() ) { pb_enqueue_public( false ); }
}, 20 );

function pb_hero_slide( $id ) {
	$s     = pb_settings( $id );
	$url   = get_permalink( $s['halaman'] );
	$state = pb_state( $id );
	$t     = $s['tarikh_program'] ? strtotime( $s['tarikh_program'] ) : 0;
	$hari  = [ 'Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu' ];
	$bulan = [ 1 => 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember' ];
	$teks  = $s['slaid_teks'] ?: ( get_post_field( 'post_excerpt', $s['halaman'] ) ?: '' );
	$kuota = (int) $s['kuota'];
	$left  = $kuota ? max( 0, $kuota - pb_count_active( $id ) ) : 0;
	pb_enqueue_public( false );
	$days  = $s['tutup'] ? (int) floor( ( strtotime( $s['tutup'] ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) : null;

	ob_start(); ?>
	<div class="row align-items-center g-5 pb-5 pb-slide">
		<div class="col-lg-7">
			<span class="badge-soft d-inline-block mb-3 me-1"><i class="fa-solid <?php echo 'menunggu' === $state ? 'fa-hourglass-half' : 'fa-bullhorn'; ?> me-1 text-leaf"></i> <?php echo 'menunggu' === $state ? 'Kuota penuh — senarai menunggu' : 'Pendaftaran Dibuka'; ?></span>
			<?php if ( null !== $days && $days >= 0 && $days <= 14 ) : ?><span class="badge-soft pb-slide-urgent d-inline-block mb-3"><i class="fa-solid fa-hourglass-end me-1"></i> <?php echo $days ? 'Tutup dalam ' . (int) $days . ' hari' : 'Tutup hari ini'; ?></span><?php endif; ?>
			<h2 class="pb-slide-title mb-3"><?php echo esc_html( get_the_title( $id ) ); ?></h2>
			<?php if ( $teks ) : ?><p class="lead mb-4"><?php echo esc_html( $teks ); ?></p><?php endif; ?>
			<ul class="pb-slide-facts list-unstyled mb-4">
				<?php if ( $t ) : ?><li><i class="fa-solid fa-calendar-day"></i><?php echo esc_html( pb_fmt_date( $s['tarikh_program'] ) ); ?><?php echo $s['masa'] ? ' · ' . esc_html( $s['masa'] ) : ''; ?></li><?php endif; ?>
				<?php if ( $s['tempat'] ) : ?><li><i class="fa-solid fa-location-dot"></i><?php echo esc_html( $s['tempat'] ); ?></li><?php endif; ?>
				<?php if ( $s['yuran'] ) : ?><li><i class="fa-solid fa-tag"></i>Yuran: <?php echo esc_html( $s['yuran'] ); ?></li><?php endif; ?>
				<?php if ( $kuota && $s['tunjuk_baki'] ) : ?><li class="d-lg-none"><i class="fa-solid fa-users"></i>Tempat berbaki: <?php echo $left ? (int) $left . ' / ' . $kuota : 'Penuh'; ?></li><?php endif; ?>
				<?php if ( $s['tutup'] ) : ?><li><i class="fa-solid fa-hourglass-end"></i>Tutup: <?php echo esc_html( pb_fmt_datetime( $s['tutup'] ) ); ?></li><?php endif; ?>
			</ul>
			<div class="d-flex flex-wrap gap-2">
				<a class="btn btn-leaf btn-lg" href="<?php echo esc_url( $url ); ?>"><i class="fa-solid fa-pen-to-square"></i> <?php echo esc_html( $s['butang'] ?: 'Daftar Sekarang' ); ?></a>
				<a class="btn btn-outline-light btn-lg" href="<?php echo esc_url( $url ); ?>#pb-<?php echo (int) $id; ?>"><i class="fa-solid fa-circle-info"></i> Syarat &amp; maklumat</a>
			</div>
		</div>
		<div class="col-lg-5 d-none d-lg-block">
			<div class="pb-slide-card mx-auto">
				<?php if ( $t ) : ?>
				<div class="pb-cal">
					<div class="pb-cal-m"><?php echo esc_html( $bulan[ (int) gmdate( 'n', $t ) ] . ' ' . gmdate( 'Y', $t ) ); ?></div>
					<div class="pb-cal-d"><?php echo (int) gmdate( 'j', $t ); ?></div>
					<div class="pb-cal-w"><?php echo esc_html( $hari[ (int) gmdate( 'w', $t ) ] ); ?></div>
				</div>
				<?php endif; ?>
				<?php if ( $kuota && $s['tunjuk_baki'] ) : $pct = min( 100, round( ( $kuota - $left ) / $kuota * 100 ) ); ?>
				<div class="pb-slide-quota">
					<div class="d-flex justify-content-between small mb-1"><span><i class="fa-solid fa-users me-1"></i>Tempat berbaki</span><strong><?php echo $left ? (int) $left . ' / ' . $kuota : 'Penuh'; ?></strong></div>
					<div class="progress" role="progressbar" aria-label="Tempat terisi" aria-valuenow="<?php echo (int) $pct; ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?php echo (int) $pct; ?>%"></div></div>
				</div>
				<?php endif; ?>
				<a class="pb-slide-cta" href="<?php echo esc_url( $url ); ?>"><?php echo 'menunggu' === $state ? 'Masuk senarai menunggu' : 'Daftar sekarang'; ?> <i class="fa-solid fa-arrow-right ms-1"></i></a>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
