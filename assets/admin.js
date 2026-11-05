(function () {
	var link = document.getElementById('wp-admin-bar-adminova-theme');
	if (!link) return;
	link.addEventListener('click', function (e) {
		e.preventDefault();
		var dark = document.body.classList.toggle('adminova-dark');
		var f = new FormData();
		f.append('action', 'adminova_set_theme');
		f.append('nonce', adminova.nonce);
		f.append('theme', dark ? 'dark' : 'light');
		fetch(adminova.ajax, { method: 'POST', body: f, credentials: 'same-origin' });
	});
})();
