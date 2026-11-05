<?php
// Runs inside wp-env's tests-cli container (PHPUnit 9 + the WordPress test library), with WooCommerce installed.
$adminova_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/wordpress-phpunit';

require_once $adminova_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		require dirname( __DIR__, 2 ) . '/adminova.php';
	}
);

// WooCommerce needs its tables and roles on the throwaway test database.
tests_add_filter(
	'setup_theme',
	function () {
		WC_Install::install();
		$GLOBALS['wp_roles'] = new WP_Roles();
	}
);

require $adminova_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/AdminovaTestCase.php';
