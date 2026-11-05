<?php
/**
 * Plugin Name: Adminova
 * Description: Modern look and a one-screen overview for the WordPress/WooCommerce admin. Set ADMINOVA_DISABLE in wp-config.php to switch off.
 * Plugin URI: https://github.com/Open-WP-Club/adminova
 * Version: 0.1.0
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: adminova
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

define( 'ADMINOVA_VERSION', '0.1.0' );
define( 'ADMINOVA_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADMINOVA_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

foreach ( array( 'features', 'adminova', 'stats', 'bulk', 'settings', 'shortcuts', 'dashboard', 'alerts', 'print', 'export', 'filters', 'notes', 'restock', 'profit', 'customers', 'notify', 'undo' ) as $adminova_file ) {
	require_once ADMINOVA_PATH . "includes/class-$adminova_file.php";
}

add_action( 'plugins_loaded', array( 'Adminova', 'init' ) );
