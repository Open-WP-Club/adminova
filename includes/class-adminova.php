<?php
defined('ABSPATH') || exit;

class Adminova {
	const THEMES = ['light', 'dark'];

	public static function init(): void {
		if (defined('ADMINOVA_DISABLE') && ADMINOVA_DISABLE) {
			return;
		}
		add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
		add_filter('admin_body_class', [self::class, 'body_class']);
		add_action('admin_bar_menu', [self::class, 'theme_toggle'], 90);
		add_action('wp_ajax_adminova_set_theme', [self::class, 'ajax_set_theme']);
		Adminova_Stats::hooks();
		Adminova_Dashboard::hooks();
		Adminova_Shortcuts::hooks();
	}

	public static function sanitize_theme($v): string {
		return is_string($v) && in_array($v, self::THEMES, true) ? $v : '';
	}

	public static function enqueue(): void {
		if (!current_user_can('edit_posts')) {
			return;
		}
		wp_enqueue_style('adminova', ADMINOVA_URL . 'assets/admin.css', [], ADMINOVA_VERSION);
		wp_enqueue_script('adminova', ADMINOVA_URL . 'assets/admin.js', [], ADMINOVA_VERSION, true);
		wp_localize_script('adminova', 'adminova', [
			'ajax'  => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('adminova_theme'),
		]);
	}

	// Server-side class so dark mode never flashes light on load.
	public static function body_class($classes): string {
		if (!current_user_can('edit_posts')) {
			return $classes;
		}
		$dark = get_user_meta(get_current_user_id(), 'adminova_theme', true) === 'dark';
		return $classes . ' adminova' . ($dark ? ' adminova-dark' : '');
	}

	public static function theme_toggle($bar): void {
		if (!is_admin() || !current_user_can('edit_posts')) {
			return;
		}
		$bar->add_node(['id' => 'adminova-theme', 'title' => __('Dark mode', 'adminova'), 'href' => '#']);
	}

	public static function ajax_set_theme(): void {
		check_ajax_referer('adminova_theme', 'nonce');
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(null, 403);
		}
		$theme = self::sanitize_theme($_POST['theme'] ?? '');
		if ($theme === '') {
			wp_send_json_error(null, 400);
		}
		update_user_meta(get_current_user_id(), 'adminova_theme', $theme);
		wp_send_json_success();
	}
}
