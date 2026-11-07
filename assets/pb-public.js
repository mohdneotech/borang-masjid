/* Borang Program Masjid — public form helpers (no dependencies) */
(function () {
	'use strict';
	document.querySelectorAll('form[data-pb-form]').forEach(function (form) {
		var busy = false;
		// conditional fields: show + enable only when the controlling field has the given value
		var conds = Array.prototype.slice.call(form.querySelectorAll('.pb-cond[data-pb-bila]'));
		function condMet(c) {
			var name = c.getAttribute('data-pb-bila'), want = c.getAttribute('data-pb-nilai');
			var els = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
			for (var i = 0; i < els.length; i++) {
				var el = els[i];
				if ((el.type === 'radio' || el.type === 'checkbox') ? (el.checked && el.value === want) : el.value === want) { return true; }
			}
			return false;
		}
		function applyConds() {
			conds.forEach(function (c) {
				var on = condMet(c);
				c.hidden = !on;
				c.querySelectorAll('input,select,textarea').forEach(function (el) { el.disabled = !on; });
			});
		}
		if (conds.length) { form.addEventListener('change', applyConds); applyConds(); }
		form.addEventListener('submit', function (e) {
			// required checkbox groups: at least one ticked
			var ok = true;
			form.querySelectorAll('fieldset[data-pb-req-group]').forEach(function (fs) {
				if (fs.closest('.pb-cond[hidden]')) { return; }
				var any = fs.querySelector('input:checked');
				fs.classList.toggle('pb-invalid', !any);
				if (!any) { ok = false; }
			});
			// attachments: type and size checked before upload (server checks again)
			form.querySelectorAll('input[type=file]').forEach(function (inp) {
				var f = inp.files && inp.files[0], msg = '';
				if (f && !inp.disabled) {
					if (f.size > 10 * 1024 * 1024) { msg = 'Saiz fail melebihi 10 MB. Sila pilih fail yang lebih kecil.'; }
					else if (!/\.(jpe?g|png|webp|pdf)$/i.test(f.name)) { msg = 'Jenis fail tidak dibenarkan. Sila pilih gambar (JPG/PNG) atau PDF.'; }
				}
				inp.setCustomValidity(msg);
				var box = inp.parentNode.querySelector('.pb-file-err');
				if (msg && !box) { box = document.createElement('div'); box.className = 'pb-file-err small text-danger mt-1'; box.setAttribute('role', 'alert'); inp.insertAdjacentElement('afterend', box); }
				if (box) { box.textContent = msg; box.hidden = !msg; }
			});
			if (!form.checkValidity() || !ok) {
				e.preventDefault();
				form.classList.add('was-validated');
				var bad = form.querySelector(':invalid, .pb-invalid');
				if (bad) {
					bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
					var inp = bad.matches('input,select,textarea') ? bad : bad.querySelector('input');
					if (inp) { setTimeout(function () { inp.focus({ preventScroll: true }); }, 300); }
				}
				return;
			}
			if (busy) { e.preventDefault(); return; }
			busy = true;
			var b = form.querySelector('button[type=submit]');
			if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Menghantar…'; }
		});
		form.querySelectorAll('input[type=file]').forEach(function (inp) {
			inp.addEventListener('change', function () { inp.setCustomValidity(''); var b = inp.parentNode.querySelector('.pb-file-err'); if (b) { b.hidden = true; } });
		});
		form.querySelectorAll('fieldset[data-pb-req-group] input').forEach(function (i) {
			i.addEventListener('change', function () { i.closest('fieldset').classList.remove('pb-invalid'); });
		});
		// "Tiada" in a checkbox group clears the other ticks (and vice versa)
		form.querySelectorAll('fieldset').forEach(function (fs) {
			var boxes = Array.prototype.slice.call(fs.querySelectorAll('input[type=checkbox]'));
			var none = boxes.filter(function (b) { return /^tiada$/i.test(b.value); })[0];
			if (!none) { return; }
			boxes.forEach(function (b) {
				b.addEventListener('change', function () {
					if (!b.checked) { return; }
					if (b === none) { boxes.forEach(function (o) { if (o !== none) { o.checked = false; } }); }
					else { none.checked = false; }
				});
			});
		});
	});
	var err = document.querySelector('.pb-ui [id^="pb-err-"]');
	var done = document.querySelector('.pb-ui .pb-success');
	var target = err || done;
	if (target) { target.scrollIntoView({ block: 'center' }); if (err) { err.focus({ preventScroll: true }); } }
})();
