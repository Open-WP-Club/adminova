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

	public static function bar_heights(array $vals, int $max_h = 80): array {
		$max = $vals ? max($vals) : 0;
		return array_map(fn($v) => $max > 0 ? (int) round($v / $max * $max_h) : 0, $vals);
	}

	public static function render(): void {
		$s = Adminova_Stats::get();
		$delta = $s['delta_pct'];
		$reports = current_user_can('view_woocommerce_reports');

		echo '<div class="adminova-grid">';
		// revenue is financial data: order editors alone don't get it
		if ($reports) {
			self::tile('money-alt', __('Sales today', 'adminova'), wc_price($s['today']['revenue']),
				$delta === null ? __('no sales yesterday', 'adminova') : sprintf(__('%+.1f%% vs yesterday', 'adminova'), $delta),
				$delta === null ? '' : ($delta >= 0 ? 'up' : 'down'));
		}
		self::tile('cart', __('Orders today', 'adminova'), (string) $s['today']['orders'],
			sprintf(__('%d yesterday', 'adminova'), $s['yesterday']['orders']));
		echo '</div>';

		self::actions();
		if ($reports && $s['week']) {
			self::chart($s['week']);
		}

		if ($s['recent']) {
			echo '<h4>' . esc_html__('Latest orders', 'adminova') . '</h4><ul class="adminova-recent">';
			foreach ($s['recent'] as $o) {
				printf('<li><a href="%s">#%s %s</a><span>%s</span><mark class="status-%s">%s</mark></li>',
					esc_url($o['url']), esc_html($o['number']), esc_html($o['name']),
					$reports ? wp_kses_post(wc_price($o['total'], ['currency' => $o['currency']])) : '',
					esc_attr($o['status']), esc_html(wc_get_order_status_name($o['status'])));
			}
			echo '</ul>';
		}

		echo '<h4>' . esc_html__('Orders by status', 'adminova') . '</h4><p class="adminova-pills">';
		foreach ($s['by_status'] as $slug => $n) {
			if ($n > 0) {
				printf('<a class="adminova-pill status-%1$s" href="%2$s">%3$s <b>%4$d</b></a>',
					esc_attr($slug), esc_url(Adminova_Shortcuts::orders_url($slug)),
					esc_html(wc_get_order_status_name($slug)), $n);
			}
		}
		echo '</p>';
		if ($s['low_stock'] && current_user_can('edit_products')) {
			echo '<h4>' . esc_html__('Low stock', 'adminova') . '</h4><ul>';
			foreach ($s['low_stock'] as $p) {
				printf('<li><a href="%s">%s</a> <b>%d</b></li>', esc_url(get_edit_post_link($p['id'])), esc_html($p['name']), $p['qty']);
			}
			echo '</ul>';
		}
	}

	private static function actions(): void {
		$links = [
			['edit_shop_orders', Adminova_Shortcuts::new_order_url(), 'plus-alt2', __('New order', 'adminova')],
			['edit_products', admin_url('post-new.php?post_type=product'), 'products', __('New product', 'adminova')],
			['edit_shop_coupons', admin_url('edit.php?post_type=shop_coupon'), 'tickets-alt', __('Coupons', 'adminova')],
		];
		echo '<p class="adminova-actions">';
		foreach ($links as [$cap, $url, $icon, $label]) {
			if (current_user_can($cap)) {
				printf('<a class="button" href="%s"><span class="dashicons dashicons-%s"></span> %s</a>', esc_url($url), esc_attr($icon), esc_html($label));
			}
		}
		echo '</p>';
	}

	private static function chart(array $week): void {
		$heights = self::bar_heights(array_column($week, 'revenue'));
		printf('<h4>%s</h4><svg class="adminova-chart" viewBox="0 0 280 110" role="img" aria-label="%s">',
			esc_html__('Last 7 days', 'adminova'), esc_attr__('Sales for the last 7 days', 'adminova'));
		foreach ($heights as $i => $h) {
			$x = $i * 40 + 6;
			printf('<rect x="%d" y="%d" width="28" height="%d" rx="4"><title>%s</title></rect><text x="%d" y="106" text-anchor="middle">%s</text>',
				$x, 90 - max($h, 2), max($h, 2), esc_html(wp_strip_all_tags(wc_price($week[$i]['revenue']))), $x + 14, esc_html($week[$i]['label']));
		}
		echo '</svg>';
	}

	private static function tile(string $icon, string $label, string $value, string $note, string $trend = ''): void {
		printf('<div class="adminova-tile"><i class="dashicons dashicons-%s"></i><span>%s</span><strong>%s</strong><em class="%s">%s</em></div>',
			esc_attr($icon), esc_html($label), wp_kses_post($value), esc_attr($trend), esc_html($note));
	}
}
