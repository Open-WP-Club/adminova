<?php
// Minimal WP-free test runner: php tests/run.php
define('ABSPATH', __DIR__);
$GLOBALS['hooks'] = [];
function add_action($h, $cb, $p = 10) { $GLOBALS['hooks'][$h][] = $cb; }
function add_filter($h, $cb, $p = 10) { add_action($h, $cb); }
function add_query_arg($k, $v, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . "$k=$v"; }
function absint($v) { return abs((int) $v); }
function admin_url($p = '') { return 'http://x/wp-admin/' . $p; }

define('DAY_IN_SECONDS', 86400);
$root = dirname(__DIR__) . '/includes/';
foreach (['class-adminova.php', 'class-stats.php', 'class-shortcuts.php', 'class-dashboard.php', 'class-alerts.php', 'class-print.php'] as $f) require $root . $f;

$fails = 0;
function check($name, $ok) { global $fails; echo ($ok ? 'PASS' : 'FAIL') . " $name\n"; if (!$ok) $fails++; }

// Task 1: init hooks assets by default, nothing when disabled
Adminova::init();
check('init registers assets hook', !empty($GLOBALS['hooks']['admin_enqueue_scripts']));
$GLOBALS['hooks'] = [];
define('ADMINOVA_DISABLE', true);
Adminova::init();
check('init registers nothing when disabled', $GLOBALS['hooks'] === []);

// Task 3: stats
check('delta null when yesterday is 0', Adminova_Stats::delta_pct(50, 0) === null);
check('delta computed', Adminova_Stats::delta_pct(150, 100) === 50.0);
check('delta negative', Adminova_Stats::delta_pct(50, 100) === -50.0);

// Task 4: shortcuts
check('hpos url', Adminova_Shortcuts::orders_url('processing', true) === 'http://x/wp-admin/admin.php?page=wc-orders&status=wc-processing');
check('legacy url', Adminova_Shortcuts::orders_url('processing', false) === 'http://x/wp-admin/edit.php?post_type=shop_order&post_status=wc-processing');

// Task 5: theme sanitizing
check('theme dark ok', Adminova::sanitize_theme('dark') === 'dark');
check('theme invalid rejected', Adminova::sanitize_theme('<script>') === '');
check('theme non-string rejected', Adminova::sanitize_theme(['dark']) === '');

check('new order hpos url', Adminova_Shortcuts::new_order_url(true) === 'http://x/wp-admin/admin.php?page=wc-orders&action=new');
check('new order legacy url', Adminova_Shortcuts::new_order_url(false) === 'http://x/wp-admin/post-new.php?post_type=shop_order');
check('bar heights scale to max', Adminova_Dashboard::bar_heights([0, 50, 100], 80) === [0, 40, 80]);
check('bar heights all zero', Adminova_Dashboard::bar_heights([0, 0], 80) === [0, 0]);
check('bar heights empty', Adminova_Dashboard::bar_heights([], 80) === []);
check('accent ok', Adminova::sanitize_accent('#AABBCC') === '#aabbcc');
check('accent rejects css injection', Adminova::sanitize_accent('#fff;}body{display:none') === '');
check('accent rejects short hex', Adminova::sanitize_accent('#fff') === '');

check('since keeps recent value', Adminova_Alerts::since(1000, 1500) === 1000);
check('since clamps stale value', Adminova_Alerts::since(5, 200000) === 200000);
check('since rejects garbage', Adminova_Alerts::since('x', 200000) === 200000);
check('print ids parsed and deduped', Adminova_Print::parse_ids('3, 3,x,0,7') === [3, 7]);
check('print ids from array', Adminova_Print::parse_ids(['5', '-2']) === [5, 2]);
check('print ids capped', count(Adminova_Print::parse_ids(range(1, 500))) === 100);

// dark is the default; only an explicit 'light' choice turns it off
check('dark by default', Adminova::is_dark('') === true);
check('light when chosen', Adminova::is_dark('light') === false);
check('dark when chosen', Adminova::is_dark('dark') === true);

exit($fails ? 1 : 0);
