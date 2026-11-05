<?php
// Runs inside wp-env's tests-cli container (PHPUnit 9 + the WordPress test library), with WooCommerce installed.
$adminova_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/wordpress-phpunit';

// The WordPress test library needs the Yoast polyfills; they come from this plugin's dev dependencies.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $adminova_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		// wp-env names the folder after the download URL (woocommerce.latest-stable)
		foreach ( array( '/woocommerce/', '/woocommerce.latest-stable/' ) as $dir ) {
			if ( file_exists( WP_PLUGIN_DIR . $dir . 'woocommerce.php' ) ) {
				require WP_PLUGIN_DIR . $dir . 'woocommerce.php';
				break;
			}
		}
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
