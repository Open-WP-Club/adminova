<?php
defined('ABSPATH') || exit;

// New-order alerts over the built-in WP Heartbeat: the browser sends the newest order time it has seen, the server answers with anything newer.
class Adminova_Alerts {
	const MAX_AGE = DAY_IN_SECONDS;

	public static function hooks(): void {
		add_filter('heartbeat_received', [self::class, 'received'], 10, 2);
		add_filter('heartbeat_settings', fn($s) => ['interval' => 30] + $s);
	}

	// Clamp so a stale or tampered value can't make us scan the whole order history.
	public static function since($v, int $now): int {
		$v = is_numeric($v) ? (int) $v : 0;
		return $v < $now - self::MAX_AGE ? $now : $v;
	}

	public static function received($response, $data) {
		if (!isset($data['adminova_since']) || !function_exists('wc_get_orders') || !current_user_can('edit_shop_orders')) {
			return $response;
		}
		$since = self::since($data['adminova_since'], time());
		$orders = wc_get_orders([
			'limit'        => 5,
			'status'       => ['processing', 'on-hold'],
			'date_created' => '>' . $since,
			'orderby'      => 'date',
			'order'        => 'ASC',
		]);
		$reports = current_user_can('view_woocommerce_reports');
		$list = [];
		foreach ($orders as $o) {
			$since = max($since, (int) $o->get_date_created()->getTimestamp());
			$list[] = [
				'number' => $o->get_order_number(),
				'name'   => trim($o->get_billing_first_name() . ' ' . $o->get_billing_last_name()) ?: __('Guest', 'adminova'),
				'total'  => $reports ? html_entity_decode(wp_strip_all_tags(wc_price($o->get_total(), ['currency' => $o->get_currency()]))) : '',
				'url'    => $o->get_edit_order_url(),
			];
		}
		// ponytail: an order landing in the same second as the newest one seen can be missed, use order IDs if that ever matters
		$response['adminova_new'] = ['since' => $since, 'orders' => $list];
		return $response;
	}
}
