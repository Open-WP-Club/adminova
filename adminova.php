<?php
/**
 * Plugin Name: Adminova
 * Description: Modern look and a one-screen overview for the WordPress/WooCommerce admin. Set ADMINOVA_DISABLE in wp-config.php to switch off.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: adminova
 */
defined('ABSPATH') || exit;

define('ADMINOVA_VERSION', '0.1.0');
define('ADMINOVA_PATH', plugin_dir_path(__FILE__));
define('ADMINOVA_URL', plugin_dir_url(__FILE__));

add_action('before_woocommerce_init', function () {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

foreach (['adminova', 'stats', 'shortcuts', 'dashboard', 'alerts', 'print'] as $f) {
	require_once ADMINOVA_PATH . "includes/class-$f.php";
}

add_action('plugins_loaded', ['Adminova', 'init']);
