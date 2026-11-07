/* Borang Program Masjid — field builder (wp-admin, jQuery UI sortable from core) */
jQuery(function ($) {
	'use strict';
	var T = (window.PB_BUILDER || {}).types || {};
	var $wrap = $('#pb-builder');
	var $json = $('#pb-fields-json');
	if (!$wrap.length) { return; }
	var fields = [];
	try { fields = JSON.parse($json.val() || '[]') || []; } catch (e) { fields = []; }

	function uid() { return 'f_' + Math.random().toString(36).slice(2, 8); }
	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
	function hasOptions(t) { return t === 'select' || t === 'radio' || t === 'checkbox'; }
	function plain(s) { return $('<div>').html(s || '').text(); }

	function summary(f) {
		var t = T[f.type] || { label: f.type, icon: 'fa-square', input: true };
		var lbl = plain(f.label) || '(tiada tajuk)';
		if (lbl.length > 90) { lbl = lbl.slice(0, 90) + '…'; }
		var badges = '';
		if (f.required) { badges += '<span class="pb-b pb-b-req">Wajib</span>'; }
		if (f.width === 'half') { badges += '<span class="pb-b">½ lebar</span>'; }
		if (f.list) { badges += '<span class="pb-b">Senarai</span>'; }
		if (hasOptions(f.type)) { badges += '<span class="pb-b">' + (f.options || []).length + ' pilihan</span>'; }
		return '<span class="pb-grip" title="Seret untuk susun"><i class="fa-solid fa-grip-vertical"></i></span>' +
			'<span class="pb-ti"><i class="fa-solid ' + esc(t.icon) + '"></i></span>' +
			'<span class="pb-lbl ' + (f.type === 'heading' ? 'pb-lbl-h' : '') + '">' + esc(lbl) + '<small>' + esc(t.label) + '</small></span>' +
			'<span class="pb-badges">' + badges + '</span>' +
			'<span class="pb-acts">' +
			'<button type="button" class="pb-act" data-act="up" title="Naik"><i class="fa-solid fa-arrow-up"></i></button>' +
			'<button type="button" class="pb-act" data-act="down" title="Turun"><i class="fa-solid fa-arrow-down"></i></button>' +
			'<button type="button" class="pb-act" data-act="dup" title="Salin medan"><i class="fa-regular fa-copy"></i></button>' +
			'<button type="button" class="pb-act pb-act-del" data-act="del" title="Buang medan"><i class="fa-solid fa-trash"></i></button>' +
			'</span>';
	}

	function editor(f, i) {
		var t = T[f.type] || {};
		var h = '<div class="pb-ed-grid">';
		if (f.type === 'paragraph' || f.type === 'consent') {
			h += '<p class="pb-full"><label>' + (f.type === 'consent' ? 'Teks akuan' : 'Teks / nota') + '</label><textarea rows="3" data-k="label">' + esc(f.label) + '</textarea></p>';
		} else {
			h += '<p class="pb-full"><label>' + (f.type === 'heading' ? 'Tajuk bahagian' : 'Tajuk medan (soalan)') + '</label><input type="text" data-k="label" value="' + esc(f.label) + '"></p>';
		}
		if (hasOptions(f.type)) {
			h += '<p class="pb-full"><label>Pilihan jawapan <span class="description">— satu pilihan setiap baris</span></label><textarea rows="5" data-k="options">' + esc((f.options || []).join('\n')) + '</textarea></p>';
		}
		if (t.input) {
			h += '<p class="pb-full"><label>Teks bantuan <span class="description">(pilihan, kecil di bawah medan)</span></label><input type="text" data-k="help" value="' + esc(f.help) + '"></p>';
			if (['text', 'textarea', 'tel', 'email', 'ic', 'number'].indexOf(f.type) > -1) {
				h += '<p><label>Contoh isian (placeholder)</label><input type="text" data-k="placeholder" value="' + esc(f.placeholder) + '"></p>';
			}
			if (f.type === 'number') {
				h += '<p><label>Nilai minimum</label><input type="number" data-k="min" value="' + esc(f.min) + '"></p><p><label>Nilai maksimum</label><input type="number" data-k="max" value="' + esc(f.max) + '"></p>';
			}
			if (f.type === 'date') {
				h += '<p><label>Umur minimum (tahun)</label><input type="number" min="0" max="120" data-k="min" value="' + esc(f.min) + '"></p><p><label>Umur maksimum (tahun)</label><input type="number" min="0" max="120" data-k="max" value="' + esc(f.max) + '"></p>' +
					'<p class="pb-full description">Untuk tarikh lahir: umur dikira pada <strong>Tarikh program</strong> (atau hari ini jika kosong). Biarkan kosong jika bukan tarikh lahir.</p>';
			}
			h += '<p><label>Lebar</label><select data-k="width"><option value="full"' + (f.width !== 'half' ? ' selected' : '') + '>Penuh</option><option value="half"' + (f.width === 'half' ? ' selected' : '') + '>Separuh (2 medan sebaris)</option></select></p>';
			h += '<p class="pb-checks"><label><input type="checkbox" data-k="required"' + (f.required ? ' checked' : '') + '> Wajib diisi</label>' +
				(f.type !== 'file' && f.type !== 'consent' ? '<label><input type="checkbox" data-k="list"' + (f.list ? ' checked' : '') + '> Papar dalam senarai pendaftaran / cetakan</label>' : '') + '</p>';
		}
		h += '</div>';
		return h;
	}

	function render(openIdx) {
		$wrap.empty();
		if (!fields.length) { $wrap.append('<div class="pb-empty">Belum ada medan. Tambah medan dengan butang di bawah.</div>'); }
		fields.forEach(function (f, i) {
			var $it = $('<div class="pb-fld" />').attr('data-i', i).toggleClass('pb-fld-h', f.type === 'heading');
			$it.append($('<div class="pb-fld-head" tabindex="0" role="button" aria-expanded="false" />').html(summary(f)));
			$it.append($('<div class="pb-fld-body" hidden />').html(editor(f, i)));
			$wrap.append($it);
			if (i === openIdx) { toggle($it, true); }
		});
		sync();
	}
	function toggle($it, open) {
		var $b = $it.children('.pb-fld-body');
		open = open === undefined ? $b.prop('hidden') : open;
		$b.prop('hidden', !open);
		$it.toggleClass('pb-open', open).children('.pb-fld-head').attr('aria-expanded', open ? 'true' : 'false');
		if (open) { $b.find('input,textarea').first().trigger('focus'); }
	}
	function sync() {
		$json.val(JSON.stringify(fields));
		var $u = $('#pb-unik'), cur = $u.val() || $u.data('current') || '';
		if ($u.length) {
			$u.find('option:not(:first)').remove();
			fields.forEach(function (f) {
				if (['text', 'ic', 'tel', 'email'].indexOf(f.type) > -1) {
					$u.append($('<option />').val(f.id).text(plain(f.label) || f.id).prop('selected', f.id === cur));
				}
			});
		}
	}

	$wrap.on('click keydown', '.pb-fld-head', function (e) {
		if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
		if ($(e.target).closest('.pb-act').length) { return; }
		e.preventDefault();
		toggle($(this).closest('.pb-fld'));
	});
	$wrap.on('click', '.pb-act', function (e) {
		e.preventDefault(); e.stopPropagation();
		var i = +$(this).closest('.pb-fld').data('i'), act = $(this).data('act');
		if (act === 'del') {
			if (!confirm('Buang medan "' + (plain(fields[i].label) || '') + '"?\nData pendaftaran sedia ada untuk medan ini tidak lagi dipaparkan.')) { return; }
			fields.splice(i, 1); render();
		} else if (act === 'dup') {
			var c = $.extend(true, {}, fields[i]); c.id = uid(); fields.splice(i + 1, 0, c); render(i + 1);
		} else if (act === 'up' && i > 0) {
			fields.splice(i - 1, 0, fields.splice(i, 1)[0]); render();
		} else if (act === 'down' && i < fields.length - 1) {
			fields.splice(i + 1, 0, fields.splice(i, 1)[0]); render();
		}
	});
	$wrap.on('input change', '[data-k]', function () {
		var $it = $(this).closest('.pb-fld'), i = +$it.data('i'), k = $(this).data('k'), f = fields[i];
		if ($(this).is(':checkbox')) { f[k] = this.checked ? 1 : 0; }
		else if (k === 'options') { f.options = $(this).val().split('\n').map(function (s) { return s.trim(); }).filter(Boolean); }
		else { f[k] = $(this).val(); }
		$it.children('.pb-fld-head').html(summary(f));
		sync();
	});
	$('.pb-add').on('click', function () {
		var t = $(this).data('type');
		var f = { id: uid(), type: t, label: '', help: '', placeholder: '', required: 0, width: 'full', options: [], min: '', max: '', list: 0 };
		if (hasOptions(t)) { f.options = ['Pilihan 1', 'Pilihan 2']; }
		if (t === 'heading') { f.label = 'Bahagian baharu'; }
		if (t === 'consent') { f.label = 'Saya mengesahkan maklumat yang diberikan adalah benar.'; f.required = 1; }
		fields.push(f);
		render(fields.length - 1);
		$wrap.children().last()[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
	});

	$wrap.sortable({
		handle: '.pb-grip', items: '> .pb-fld', axis: 'y', placeholder: 'pb-fld-ph', forcePlaceholderSize: true,
		update: function () {
			var order = $wrap.children('.pb-fld').map(function () { return +$(this).data('i'); }).get();
			fields = order.map(function (i) { return fields[i]; });
			render();
		}
	});

	render(); // normalises the JSON first, so the baseline below matches what the page would save

	// warn on leaving with unsaved field changes
	var initial = $json.val(), saving = false;
	$('#post').on('submit', function () { sync(); saving = true; });
	$(window).on('beforeunload', function () { if (!saving && $json.val() !== initial) { return 'Perubahan belum disimpan.'; } });

	$(document).on('click', '.pb-copy', function () {
		var v = $(this).data('copy'), $b = $(this);
		(navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () {
			var o = $b.html(); $b.text('Disalin ✓'); setTimeout(function () { $b.html(o); }, 1500);
		}, function () { window.prompt('Salin pautan:', v); });
	});

});
