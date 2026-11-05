(function () {
	var cfg = window.adminova || {};

	// dark-mode toggle
	var toggle = document.getElementById('wp-admin-bar-adminova-theme');
	if (toggle) {
		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			var dark = document.body.classList.toggle('adminova-dark');
			var f = new FormData();
			f.append('action', 'adminova_set_theme');
			f.append('nonce', cfg.nonce);
			f.append('theme', dark ? 'dark' : 'light');
			fetch(cfg.ajax, { method: 'POST', body: f, credentials: 'same-origin' });
		});
	}

	// Ctrl/Cmd+K palette: menu items are read from the sidebar, orders/products go to the stock search
	var box, input, list, items = [], shown = [], sel = 0;

	function collect() {
		var seen = {};
		items = [];
		document.querySelectorAll('#adminmenu a[href]').forEach(function (a) {
			var t = a.textContent.replace(/\s+/g, ' ').trim();
			if (t && a.getAttribute('href').charAt(0) !== '#' && !seen[a.href]) {
				seen[a.href] = 1;
				items.push({ t: t, u: a.href });
			}
		});
	}

	function mark() {
		Array.prototype.forEach.call(list.children, function (li, i) {
			li.className = i === sel ? 'on' : '';
		});
	}

	function render() {
		var q = input.value.trim(), lq = q.toLowerCase();
		shown = items.filter(function (i) { return !lq || i.t.toLowerCase().indexOf(lq) > -1; }).slice(0, 8);
		if (q && cfg.orders) shown.push({ t: cfg.i18n.orders + ' ' + q, u: cfg.orders + encodeURIComponent(q) });
		if (q && cfg.products) shown.push({ t: cfg.i18n.products + ' ' + q, u: cfg.products + encodeURIComponent(q) });
		sel = 0;
		list.textContent = '';
		shown.forEach(function (it) {
			var li = document.createElement('li');
			li.textContent = it.t;
			li.addEventListener('mousedown', function () { location.href = it.u; });
			list.appendChild(li);
		});
		mark();
	}

	function close() { if (box) box.hidden = true; }

	function open() {
		if (!box) {
			box = document.createElement('div');
			box.id = 'adminova-palette';
			var card = document.createElement('div');
			input = document.createElement('input');
			input.type = 'search';
			input.placeholder = cfg.i18n ? cfg.i18n.placeholder : '';
			list = document.createElement('ul');
			card.appendChild(input);
			card.appendChild(list);
			box.appendChild(card);
			document.body.appendChild(box);
			box.addEventListener('mousedown', function (e) { if (e.target === box) close(); });
			input.addEventListener('input', render);
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') close();
				else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
					e.preventDefault();
					sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + shown.length) % (shown.length || 1);
					mark();
				} else if (e.key === 'Enter' && shown[sel]) location.href = shown[sel].u;
			});
		}
		collect();
		box.hidden = false;
		input.value = '';
		render();
		input.focus();
	}

	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' && !document.body.classList.contains('block-editor-page')) {
			e.preventDefault();
			open();
		}
	});
	var btn = document.getElementById('wp-admin-bar-adminova-search');
	if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); open(); });
})();
