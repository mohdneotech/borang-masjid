/* Borang Program Masjid — public form helpers (no dependencies) */
(function () {
	'use strict';
	document.querySelectorAll('form[data-pb-form]').forEach(function (form) {
		var busy = false;
		form.addEventListener('submit', function (e) {
			// required checkbox groups: at least one ticked
			var ok = true;
			form.querySelectorAll('fieldset[data-pb-req-group]').forEach(function (fs) {
				var any = fs.querySelector('input:checked');
				fs.classList.toggle('pb-invalid', !any);
				if (!any) { ok = false; }
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
