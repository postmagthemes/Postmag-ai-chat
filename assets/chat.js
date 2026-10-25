/* PostMag AI Chat – front-end chat widget (no dependencies) */
(function () {
	'use strict';
	var C = window.PMAIC;
	if (!C || !C.endpoint) return;
	var T = C.i18n || {};

	var STORE = 'pmaic_chat_v1';
	var history = load();
	var busy = false;

	/* ---------- helpers ---------- */
	function load() {
		try { return JSON.parse(sessionStorage.getItem(STORE)) || []; } catch (e) { return []; }
	}
	function save() {
		try { sessionStorage.setItem(STORE, JSON.stringify(history.slice(-20))); } catch (e) {}
	}
	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (text != null) n.textContent = text;
		return n;
	}
	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	// Small, safe markdown subset: links, bold, inline code, bullet lists, paragraphs.
	function md(text) {
		var lines = esc(text).split(/\n/);
		var html = '', inList = false;
		lines.forEach(function (line) {
			var m = line.match(/^\s*(?:[-*•]|\d+\.)\s+(.*)$/);
			if (m) {
				if (!inList) { html += '<ul>'; inList = true; }
				html += '<li>' + inline(m[1]) + '</li>';
			} else {
				if (inList) { html += '</ul>'; inList = false; }
				if (line.trim()) html += '<p>' + inline(line) + '</p>';
			}
		});
		if (inList) html += '</ul>';
		return html;
	}
	function inline(s) {
		s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (_, label, url) {
			var external = url.indexOf(location.origin) !== 0;
			return '<a href="' + url + '"' + (external ? ' target="_blank" rel="noopener nofollow"' : '') + '>' + label + '</a>';
		});
		s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
		s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
		return s;
	}

	/* ---------- build UI ---------- */
	var root = el('div', 'pmaic pmaic--' + (C.position === 'left' ? 'left' : 'right'));
	root.style.setProperty('--pmaic-accent', C.accent || '#2563eb');

	var launcher = el('button', 'pmaic-launcher');
	launcher.type = 'button';
	launcher.setAttribute('aria-label', T.open + ': ' + C.name);
	launcher.setAttribute('aria-expanded', 'false');
	launcher.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M12 3C6.5 3 2 6.9 2 11.7c0 2.6 1.3 4.9 3.4 6.5L4.6 21.4a.6.6 0 0 0 .85.68l3.9-1.9c.85.2 1.7.3 2.65.3 5.5 0 10-3.9 10-8.7S17.5 3 12 3Z"/></svg>';

	var panel = el('div', 'pmaic-panel');
	panel.setAttribute('role', 'dialog');
	panel.setAttribute('aria-label', C.name);
	panel.hidden = true;

	var head = el('div', 'pmaic-head');
	var titleWrap = el('div', 'pmaic-title');
	titleWrap.appendChild(el('strong', null, C.name));
	titleWrap.appendChild(el('span', 'pmaic-sub', C.preview ? T.preview : T.subtitle));
	var actions = el('div', 'pmaic-actions');
	var resetBtn = el('button', 'pmaic-icon', '↺');
	resetBtn.type = 'button';
	resetBtn.title = T.reset;
	resetBtn.setAttribute('aria-label', T.reset);
	var closeBtn = el('button', 'pmaic-icon', '×');
	closeBtn.type = 'button';
	closeBtn.setAttribute('aria-label', T.close);
	actions.appendChild(resetBtn);
	actions.appendChild(closeBtn);
	head.appendChild(titleWrap);
	head.appendChild(actions);

	var log = el('div', 'pmaic-log');
	log.setAttribute('aria-live', 'polite');

	var form = el('form', 'pmaic-form');
	var input = el('textarea', 'pmaic-input');
	input.rows = 1;
	input.maxLength = 500;
	input.placeholder = T.placeholder;
	input.setAttribute('aria-label', T.question);
	var send = el('button', 'pmaic-send');
	send.type = 'submit';
	send.setAttribute('aria-label', T.send);
	send.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z"/></svg>';
	form.appendChild(input);
	form.appendChild(send);

	var foot = el('div', 'pmaic-foot', T.disclaimer + ' ');
	if (C.contact) {
		var a = el('a', null, T.contact);
		a.href = C.contact;
		foot.appendChild(a);
	}

	panel.appendChild(head);
	panel.appendChild(log);
	panel.appendChild(form);
	panel.appendChild(foot);
	root.appendChild(panel);
	root.appendChild(launcher);
	document.body.appendChild(root);

	/* ---------- rendering ---------- */
	function bubble(role, text, sources) {
		var b = el('div', 'pmaic-msg pmaic-msg--' + role);
		if (role === 'assistant') {
			b.innerHTML = md(text);
			if (sources && sources.length) {
				var s = el('div', 'pmaic-sources');
				s.appendChild(el('span', null, T.sources + ' '));
				sources.forEach(function (src, i) {
					var link = el('a', null, src.title);
					link.href = src.url;
					s.appendChild(link);
					if (i < sources.length - 1) s.appendChild(document.createTextNode(' · '));
				});
				b.appendChild(s);
			}
		} else {
			b.textContent = text;
		}
		log.appendChild(b);
		log.scrollTop = log.scrollHeight;
		return b;
	}

	function renderAll() {
		log.innerHTML = '';
		bubble('assistant', C.welcome);
		history.forEach(function (m) { bubble(m.role, m.content, m.sources); });
	}

	/* ---------- open / close ---------- */
	function open() {
		panel.hidden = false;
		root.classList.add('is-open');
		launcher.setAttribute('aria-expanded', 'true');
		if (!log.childNodes.length) renderAll();
		setTimeout(function () { input.focus(); }, 50);
	}
	function close() {
		panel.hidden = true;
		root.classList.remove('is-open');
		launcher.setAttribute('aria-expanded', 'false');
		launcher.focus();
	}
	launcher.addEventListener('click', function () { panel.hidden ? open() : close(); });
	closeBtn.addEventListener('click', close);
	resetBtn.addEventListener('click', function () { history = []; save(); renderAll(); input.focus(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });

	/* ---------- sending ---------- */
	input.addEventListener('input', function () {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 110) + 'px';
	});
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); }
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var q = input.value.trim();
		if (!q || busy) return;

		busy = true;
		send.disabled = true;
		input.value = '';
		input.style.height = 'auto';

		var prior = history.map(function (m) { return { role: m.role, content: m.content }; }).slice(-6);
		history.push({ role: 'user', content: q });
		bubble('user', q);
		var typing = el('div', 'pmaic-msg pmaic-msg--assistant pmaic-typing');
		typing.innerHTML = '<span></span><span></span><span></span>';
		log.appendChild(typing);
		log.scrollTop = log.scrollHeight;

		var headers = { 'Content-Type': 'application/json' };
		if (C.nonce) headers['X-WP-Nonce'] = C.nonce;

		fetch(C.endpoint, {
			method: 'POST',
			headers: headers,
			credentials: 'same-origin',
			body: JSON.stringify({ message: q, history: prior })
		})
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
			.then(function (res) {
				typing.remove();
				if (!res.ok || !res.d.answer) throw new Error((res.d && (res.d.error || res.d.message)) || T.error);
				history.push({ role: 'assistant', content: res.d.answer, sources: res.d.sources || [] });
				save();
				bubble('assistant', res.d.answer, res.d.sources);
			})
			.catch(function (err) {
				typing.remove();
				history.pop(); // drop the unanswered question from context
				save();
				var b = bubble('assistant', err.message || T.error);
				b.classList.add('pmaic-msg--error');
			})
			.finally(function () {
				busy = false;
				send.disabled = false;
				input.focus();
			});
	});

	// Re-open automatically if the visitor had a conversation on the previous page.
	if (history.length) {
		try { if (sessionStorage.getItem(STORE + '_open') === '1') open(); } catch (e) {}
	}
	window.addEventListener('pagehide', function () {
		try { sessionStorage.setItem(STORE + '_open', panel.hidden ? '0' : '1'); } catch (e) {}
	});
})();
