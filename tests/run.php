<?php
// Minimal WP-free test runner: php tests/run.php
define('ABSPATH', __DIR__);
$GLOBALS['hooks'] = [];
function add_action($h, $cb, $p = 10) { $GLOBALS['hooks'][$h][] = $cb; }
function add_filter($h, $cb, $p = 10) { add_action($h, $cb); }
function add_query_arg($k, $v, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . "$k=$v"; }
function admin_url($p = '') { return 'http://x/wp-admin/' . $p; }

$root = dirname(__DIR__) . '/includes/';
foreach (['class-adminova.php', 'class-stats.php', 'class-shortcuts.php', 'class-dashboard.php'] as $f) require $root . $f;

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

exit($fails ? 1 : 0);
