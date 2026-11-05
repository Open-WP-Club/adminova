<?php
// WP-free bootstrap: stubs just enough of WordPress to load the classes and test the pure logic.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
$GLOBALS['adminova_hooks'] = [];

function add_action($hook, $cb, $priority = 10, $args = 1) { $GLOBALS['adminova_hooks'][$hook][] = $cb; }
function add_filter($hook, $cb, $priority = 10, $args = 1) { add_action($hook, $cb); }
function add_query_arg($key, $value, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . "$key=$value"; }
function admin_url($path = '') { return 'http://x/wp-admin/' . $path; }
function get_option($k, $d = false) { return $d; }
function __($t) { return $t; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function wp_strip_all_tags($v) { return strip_tags((string) $v); }
function absint($v) { return abs((int) $v); }

foreach (['features', 'adminova', 'stats', 'bulk', 'settings', 'shortcuts', 'dashboard', 'alerts', 'print', 'export', 'filters', 'notes', 'restock', 'profit', 'customers', 'notify', 'undo'] as $f) {
	require dirname(__DIR__, 2) . "/includes/class-$f.php";
}
