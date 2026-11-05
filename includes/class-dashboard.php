<?php
defined('ABSPATH') || exit;

class Adminova_Dashboard {
	public static function hooks(): void {
		add_action('wp_dashboard_setup', [self::class, 'register'], 99);
	}

	public static function register(): void {
		if (!function_exists('wc_get_orders') || !current_user_can('edit_shop_orders')) {
			return;
		}
		global $wp_meta_boxes;
		foreach (['dashboard_site_health', 'dashboard_right_now', 'dashboard_activity', 'dashboard_quick_press', 'dashboard_primary', 'woocommerce_dashboard_status'] as $id) {
			remove_meta_box($id, 'dashboard', 'normal');
			remove_meta_box($id, 'dashboard', 'side');
		}
		wp_add_dashboard_widget('adminova_overview', __('Overview', 'adminova'), [self::class, 'render']);
		// put our widget first in the main column
		$core = &$wp_meta_boxes['dashboard']['normal']['core'];
		if (isset($core['adminova_overview'])) {
			$core = ['adminova_overview' => $core['adminova_overview']] + $core;
		}
	}

	public static function render(): void {
		$s = Adminova_Stats::get();
		$delta = $s['delta_pct'];
		echo '<div class="adminova-grid">';
		self::tile('💰', __('Sales today', 'adminova'), wc_price($s['today']['revenue']),
			$delta === null ? __('no sales yesterday', 'adminova') : sprintf(__('%+.1f%% vs yesterday', 'adminova'), $delta),
			$delta === null ? '' : ($delta >= 0 ? 'up' : 'down'));
		self::tile('🛒', __('Orders today', 'adminova'), (string) $s['today']['orders'],
			sprintf(__('%d yesterday', 'adminova'), $s['yesterday']['orders']));
		echo '</div><h4>' . esc_html__('Orders by status', 'adminova') . '</h4><p class="adminova-pills">';
		foreach ($s['by_status'] as $slug => $n) {
			if ($n > 0) {
				printf('<a class="adminova-pill status-%1$s" href="%2$s">%3$s <b>%4$d</b></a>',
					esc_attr($slug), esc_url(Adminova_Shortcuts::orders_url($slug)),
					esc_html(wc_get_order_status_name($slug)), $n);
			}
		}
		echo '</p>';
		if ($s['low_stock']) {
			echo '<h4>' . esc_html__('Low stock', 'adminova') . '</h4><ul>';
			foreach ($s['low_stock'] as $p) {
				printf('<li><a href="%s">%s</a> <b>%d</b></li>', esc_url(get_edit_post_link($p['id'])), esc_html($p['name']), $p['qty']);
			}
			echo '</ul>';
		}
	}

	private static function tile(string $icon, string $label, string $value, string $note, string $trend = ''): void {
		printf('<div class="adminova-tile"><i>%s</i><span>%s</span><strong>%s</strong><em class="%s">%s</em></div>',
			$icon, esc_html($label), wp_kses_post($value), esc_attr($trend), esc_html($note));
	}
}
