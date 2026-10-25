/* PostMag AI Chat – settings screen */
(function () {
	'use strict';
	var A = window.PMAIC_ADMIN;
	if (!A) return;
	var t = A.i18n;
	var out = document.getElementById('pmaic-progress');
	var form = document.getElementById('pmaic-form');
	var select = document.getElementById('pmaic-provider');
	var dirty = false;

	function fmt(str) {
		var args = Array.prototype.slice.call(arguments, 1), i = 0;
		return str.replace(/%(\d)\$d|%d/g, function (m, n) { return n ? args[n - 1] : args[i++]; });
	}
	function post(data) {
		data._ajax_nonce = A.nonce;
		return fetch(A.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams(data) })
			.then(function (r) { return r.json(); });
	}
	function say(msg, cls) {
		if (!out) return;
		out.textContent = msg;
		out.className = cls || '';
	}

	// Show only the selected provider's fields.
	function toggle() {
		if (!select) return;
		document.querySelectorAll('.pmaic-provider-section').forEach(function (s) {
			s.hidden = s.getAttribute('data-provider') !== select.value;
		});
	}
	if (select) { select.addEventListener('change', toggle); toggle(); }
	if (form) form.addEventListener('input', function () { dirty = true; });

	// Rebuild index in batches.
	var rb = document.getElementById('pmaic-reindex');
	if (rb) rb.addEventListener('click', function () {
		rb.disabled = true;
		(function step(offset) {
			post({ action: 'pmaic_reindex', offset: offset }).then(function (r) {
				if (!r.success) throw new Error(r.data || t.failed);
				say(fmt(t.indexed, r.data.done, r.data.total));
				if (r.data.finished) {
					say(fmt(t.done, r.data.done), 'pmaic-ok');
					setTimeout(function () { location.reload(); }, 900);
				} else {
					step(r.data.done);
				}
			}).catch(function (e) { say(t.failed + ': ' + e.message, 'pmaic-err'); rb.disabled = false; });
		})(0);
	});

	// Test connection with the provider currently selected in the form.
	var tb = document.getElementById('pmaic-test');
	if (tb) tb.addEventListener('click', function () {
		if (dirty) { say(t.unsaved, 'pmaic-warn'); return; }
		tb.disabled = true;
		say(t.testing);
		post({ action: 'pmaic_test', provider: select ? select.value : '' }).then(function (r) {
			say(r.success ? t.connected + ' ' + r.data : t.failed + ': ' + r.data, r.success ? 'pmaic-ok' : 'pmaic-err');
		}).catch(function (e) { say(t.failed + ': ' + e.message, 'pmaic-err'); })
			.finally(function () { tb.disabled = false; });
	});

	// Load model lists into each provider's <datalist>.
	document.querySelectorAll('.pmaic-load-models').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = btn.getAttribute('data-provider');
			var list = document.getElementById('pmaic-models-' + id);
			var input = document.getElementById('pmaic-model-' + id);
			btn.disabled = true;
			say(t.loading);
			post({ action: 'pmaic_models', provider: id }).then(function (r) {
				if (!r.success) throw new Error(r.data || t.failed);
				list.innerHTML = '';
				r.data.forEach(function (m) {
					var o = document.createElement('option');
					o.value = m;
					list.appendChild(o);
				});
				say(fmt(t.models, r.data.length), 'pmaic-ok');
				input.focus();
			}).catch(function (e) { say(t.failed + ': ' + e.message, 'pmaic-err'); })
				.finally(function () { btn.disabled = false; });
		});
	});
})();
