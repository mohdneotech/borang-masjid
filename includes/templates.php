<?php
defined( 'ABSPATH' ) || exit;

/** Create a draft form from a template. @return int|WP_Error */
function pb_create_form( $title, $key = 'kosong', $settings = [] ) {
	$tpls  = pb_templates();
	$t     = $tpls[ $key ] ?? $tpls['kosong'];
	$title = mb_substr( sanitize_text_field( (string) $title ), 0, 150 ) ?: $t['label'];
	$id    = wp_insert_post( [ 'post_type' => PB_CPT, 'post_status' => 'draft', 'post_title' => $title, 'post_content' => $t['intro'] ], true );
	if ( is_wp_error( $id ) ) { return $id; }
	$fields = pb_sanitize_fields( $t['fields'] );
	$s      = array_merge( pb_default_settings(), $t['settings'], $settings );
	foreach ( $fields as $f ) { if ( 'ic' === $f['type'] && ! $s['unik'] ) { $s['unik'] = $f['id']; } }
	update_post_meta( $id, '_pb_fields', wp_slash( wp_json_encode( $fields, JSON_UNESCAPED_UNICODE ) ) );
	update_post_meta( $id, '_pb_settings', $s );
	return $id;
}

/**
 * Starter templates for the builder. Each: label, icon, settings (partial), intro (HTML), fields.
 * For date fields, min/max are AGES in years (checked on the programme date, or today if none).
 */
function pb_templates() {
	$f = function ( $type, $label, $extra = [] ) { return array_merge( [ 'type' => $type, 'label' => $label ], $extra ); };

	$ibubapa = [
		$f( 'heading', 'Maklumat Ibu Bapa / Penjaga' ),
		$f( 'text', 'Nama ibu / bapa / penjaga', [ 'required' => 1, 'list' => 1 ] ),
		$f( 'radio', 'Hubungan dengan peserta', [ 'required' => 1, 'width' => 'half', 'options' => [ 'Bapa', 'Ibu', 'Penjaga' ] ] ),
		$f( 'ic', 'No. Kad Pengenalan ibu / bapa / penjaga', [ 'required' => 1, 'width' => 'half' ] ),
		$f( 'tel', 'No. telefon bimbit (WhatsApp)', [ 'required' => 1, 'width' => 'half', 'list' => 1, 'placeholder' => '012-345 6789' ] ),
		$f( 'tel', 'No. telefon kecemasan (alternatif)', [ 'width' => 'half' ] ),
		$f( 'textarea', 'Alamat rumah', [ 'required' => 1 ] ),
		$f( 'radio', 'Ahli kariah ' . pb_masjid() . '?', [ 'required' => 1, 'options' => [ 'Ya', 'Tidak' ] ] ),
	];

	return [
		'kosong' => [
			'label'    => 'Borang kosong',
			'icon'     => 'fa-file',
			'settings' => [ 'prefix' => 'REG' ],
			'intro'    => '',
			'fields'   => [
				$f( 'text', 'Nama penuh', [ 'required' => 1, 'list' => 1 ] ),
				$f( 'tel', 'No. telefon bimbit', [ 'required' => 1, 'list' => 1, 'width' => 'half' ] ),
			],
		],

		'khatan' => [
			'label'    => 'Khatan Perdana',
			'icon'     => 'fa-child',
			'settings' => [ 'prefix' => 'KHT', 'kuota' => 50, 'penuh' => 'menunggu', 'yuran' => 'Percuma', 'tempat' => pb_masjid(), 'butang' => 'Daftar Peserta',
				'mesej' => 'Terima kasih. Pendaftaran peserta Khatan Perdana telah diterima. AJK akan menghubungi ibu bapa / penjaga melalui telefon atau WhatsApp untuk pengesahan dan taklimat.' ],
			'intro'    => "<p><strong>Syarat penyertaan</strong></p>\n<ul>\n<li>Terbuka kepada kanak-kanak lelaki beragama Islam berumur <strong>5 hingga 12 tahun</strong> pada hari program.</li>\n<li><strong>Satu borang untuk seorang peserta.</strong> Jika mendaftarkan lebih daripada seorang anak, sila hantar borang sekali lagi.</li>\n<li>Ibu / bapa / penjaga <strong>wajib hadir</strong> bersama peserta sepanjang program.</li>\n<li>Tempat adalah terhad dan tertakluk kepada pengesahan AJK. Apabila kuota penuh, pendaftaran baharu dimasukkan ke senarai menunggu.</li>\n<li>Prosedur dijalankan oleh pengamal perubatan bertauliah yang dilantik oleh penganjur.</li>\n</ul>\n<p><strong>Perlu dibawa pada hari program</strong></p>\n<ul>\n<li>Salinan MyKid / sijil lahir peserta.</li>\n<li>Kain pelikat atau seluar longgar, dan pakaian ganti.</li>\n<li>Pastikan peserta bersarapan ringan sebelum hadir.</li>\n</ul>",
			'fields'   => array_merge( [
				$f( 'heading', 'Maklumat Peserta' ),
				$f( 'text', 'Nama penuh peserta', [ 'required' => 1, 'list' => 1, 'help' => 'Seperti dalam MyKid / sijil lahir.' ] ),
				$f( 'ic', 'No. MyKid / sijil lahir peserta', [ 'required' => 1, 'width' => 'half', 'list' => 1, 'id' => 'mykid' ] ),
				$f( 'date', 'Tarikh lahir peserta', [ 'required' => 1, 'width' => 'half', 'min' => '5', 'max' => '12', 'help' => 'Peserta mesti berumur 5 hingga 12 tahun pada hari program.' ] ),
				$f( 'text', 'Nama sekolah', [ 'width' => 'half' ] ),
				$f( 'select', 'Saiz baju', [ 'width' => 'half', 'options' => [ 'XS (4–5 tahun)', 'S (6–7 tahun)', 'M (8–9 tahun)', 'L (10–11 tahun)', 'XL (12 tahun)' ], 'help' => 'Untuk cenderahati peserta (jika disediakan).' ] ),
				$f( 'heading', 'Maklumat Kesihatan Peserta' ),
				$f( 'paragraph', 'Maklumat ini penting untuk keselamatan anak anda dan hanya dilihat oleh AJK dan pasukan perubatan.' ),
				$f( 'checkbox', 'Adakah peserta mempunyai masalah kesihatan berikut?', [ 'required' => 1, 'options' => [ 'Tiada', 'Alahan ubat / ubat bius', 'Asma', 'Masalah darah (hemofilia / mudah berdarah)', 'G6PD', 'Sawan / epilepsi', 'Penyakit jantung', 'Lain-lain' ] ] ),
				$f( 'textarea', 'Butiran masalah kesihatan / ubat yang sedang diambil', [ 'help' => 'Isi jika ada. Contoh: alahan penisilin, ubat asma harian.' ] ),
			], $ibubapa, [
				$f( 'heading', 'Akuan' ),
				$f( 'consent', 'Saya adalah ibu / bapa / penjaga sah kepada peserta di atas dan memberi kebenaran untuk peserta menjalani prosedur berkhatan oleh pengamal perubatan bertauliah yang dilantik penganjur. Saya mengesahkan semua maklumat yang diberikan adalah benar.', [ 'required' => 1 ] ),
				$f( 'consent', 'Saya membenarkan gambar peserta diambil untuk dokumentasi dan hebahan program masjid.' ),
			] ),
		],

		'program' => [
			'label'    => 'Program / Kursus / Kelas',
			'icon'     => 'fa-chalkboard-user',
			'settings' => [ 'prefix' => 'PRG', 'butang' => 'Daftar Sekarang' ],
			'intro'    => '<p>Sila isi maklumat di bawah untuk mendaftar. Tempat adalah terhad.</p>',
			'fields'   => [
				$f( 'heading', 'Maklumat Peserta' ),
				$f( 'text', 'Nama penuh', [ 'required' => 1, 'list' => 1 ] ),
				$f( 'ic', 'No. Kad Pengenalan', [ 'required' => 1, 'width' => 'half', 'id' => 'nokp' ] ),
				$f( 'tel', 'No. telefon bimbit (WhatsApp)', [ 'required' => 1, 'width' => 'half', 'list' => 1 ] ),
				$f( 'email', 'E-mel', [ 'width' => 'half' ] ),
				$f( 'radio', 'Jantina', [ 'required' => 1, 'width' => 'half', 'options' => [ 'Lelaki', 'Perempuan' ] ] ),
				$f( 'radio', 'Ahli kariah ' . pb_masjid() . '?', [ 'required' => 1, 'options' => [ 'Ya', 'Tidak' ] ] ),
				$f( 'textarea', 'Catatan / pertanyaan' ),
				$f( 'consent', 'Saya mengesahkan maklumat yang diberikan adalah benar.', [ 'required' => 1 ] ),
			],
		],

		'sukarelawan' => [
			'label'    => 'Sukarelawan / Gotong-royong',
			'icon'     => 'fa-hands-helping',
			'settings' => [ 'prefix' => 'SKR', 'butang' => 'Daftar Sebagai Sukarelawan' ],
			'intro'    => '<p>Jom sumbang tenaga untuk program masjid. Sila pilih tugasan yang anda boleh bantu.</p>',
			'fields'   => [
				$f( 'text', 'Nama penuh', [ 'required' => 1, 'list' => 1 ] ),
				$f( 'tel', 'No. telefon bimbit (WhatsApp)', [ 'required' => 1, 'width' => 'half', 'list' => 1 ] ),
				$f( 'number', 'Umur', [ 'width' => 'half', 'min' => '10', 'max' => '90' ] ),
				$f( 'checkbox', 'Tugasan yang boleh dibantu', [ 'required' => 1, 'list' => 1, 'options' => [ 'Pendaftaran / kaunter', 'Masakan / juadah', 'Kebersihan', 'Kawalan trafik / parkir', 'Dokumentasi / foto', 'Lain-lain' ] ] ),
				$f( 'select', 'Saiz baju-T', [ 'width' => 'half', 'options' => [ 'S', 'M', 'L', 'XL', '2XL', '3XL' ] ] ),
				$f( 'textarea', 'Catatan' ),
			],
		],
	];
}
