<?php
defined( 'ABSPATH' ) || exit;

class Adminova_Stats {
	const KEY = 'adminova_stats';
	const TTL = 300;

	public static function hooks(): void {
		add_action( 'woocommerce_new_order', array( self::class, 'flush' ) );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'flush' ) );
		add_action( 'woocommerce_product_set_stock', array( self::class, 'flush' ) );
	}

	public static function flush(): void {
		delete_transient( self::KEY );
	}

	public static function delta_pct( float $today, float $yesterday ): ?float {
		return $yesterday > 0 ? round( ( $today - $yesterday ) / $yesterday * 100, 1 ) : null;
	}

	public static function get(): array {
		$cached = get_transient( self::KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$data = self::compute();
		set_transient( self::KEY, $data, self::TTL );
		return $data;
	}

	private static function day( int $offset ): array {
		$tz     = wp_timezone();
		$start  = new DateTimeImmutable( "today $offset day", $tz );
		$end    = $start->modify( '+1 day -1 second' );
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'processing', 'completed' ),
				'date_created' => $start->getTimestamp() . '...' . $end->getTimestamp(),
			)
		);
		// ponytail: sums one day of orders in PHP, switch to a SQL SUM if a single day exceeds ~5k orders
		$revenue = 0.0;
		foreach ( $orders as $o ) {
			$revenue += (float) $o->get_total();
		}
		return array(
			'label'   => wp_date( 'D', $start->getTimestamp() ),
			'orders'  => count( $orders ),
			'revenue' => $revenue,
		);
	}

	private static function compute(): array {
		$empty = array(
			'today'     => array(
				'orders'  => 0,
				'revenue' => 0.0,
			),
			'yesterday' => array(
				'orders'  => 0,
				'revenue' => 0.0,
			),
			'delta_pct' => null,
			'week'      => array(),
			'recent'    => array(),
			'by_status' => array(),
			'low_stock' => array(),
		);
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $empty;
		}
		$week = array();
		for ( $i = -6; $i <= 0; $i++ ) {
			$week[] = self::day( $i );
		}
		$today     = $week[6];
		$yesterday = $week[5];

		$by_status = array();
		foreach ( array_keys( wc_get_order_statuses() ) as $key ) {
			$slug               = substr( $key, 3 );
			$by_status[ $slug ] = (int) wc_orders_count( $slug );
		}

		return array(
			'today'     => $today,
			'yesterday' => $yesterday,
			'delta_pct' => self::delta_pct( $today['revenue'], $yesterday['revenue'] ),
			'week'      => $week,
			'by_status' => $by_status,
			'recent'    => self::recent(),
			'low_stock' => self::low_stock(),
		);
	}

	private static function recent(): array {
		return array_map(
			fn( $o ) => array(
				'number'   => $o->get_order_number(),
				'name'     => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ?: __( 'Guest', 'adminova' ),
				'total'    => (float) $o->get_total(),
				'currency' => $o->get_currency(),
				'status'   => $o->get_status(),
				'url'      => $o->get_edit_order_url(),
			),
			wc_get_orders(
				array(
					'limit'   => 5,
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			)
		);
	}

	private static function low_stock(): array {
		global $wpdb;
		$limit = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS name, CAST(s.meta_value AS SIGNED) AS qty
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_stock'
			 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_manage_stock' AND m.meta_value = 'yes'
			 WHERE p.post_type IN ('product','product_variation') AND p.post_status = 'publish'
			   AND CAST(s.meta_value AS SIGNED) <= %d
			 ORDER BY qty ASC LIMIT 5",
				$limit
			)
		);
		return array_map(
			fn( $r ) => array(
				'id'   => (int) $r->id,
				'name' => $r->name,
				'qty'  => (int) $r->qty,
			),
			$rows ?: array()
		);
	}
}
