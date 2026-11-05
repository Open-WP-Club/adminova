<?php
defined( 'ABSPATH' ) || exit;

// Optional cost price per product, so the dashboard can show profit next to sales.
class Adminova_Profit {
	const META = '_adminova_cost';

	public static function hooks(): void {
		add_action( 'woocommerce_product_options_pricing', array( self::class, 'field' ) );
		add_action( 'woocommerce_process_product_meta', array( self::class, 'save' ) );
	}

	public static function line( float $line_total, ?float $cost, int $qty ): ?float {
		return null === $cost ? null : round( $line_total - $cost * $qty, 2 );
	}

	public static function cost_for( int $product_id ): ?float {
		static $cache = array();
		if ( ! array_key_exists( $product_id, $cache ) ) {
			$v                    = get_post_meta( $product_id, self::META, true );
			$cache[ $product_id ] = '' === $v || false === $v ? null : (float) $v;
		}
		return $cache[ $product_id ];
	}

	// Returns [profit over items that have a cost, number of items without a cost]. Refunds are not subtracted.
	public static function order_profit( $order ): array {
		$profit  = 0.0;
		$missing = 0;
		foreach ( $order->get_items() as $item ) {
			$p                                  = self::line( (float) $item->get_total(), self::cost_for( (int) $item->get_product_id() ), (int) $item->get_quantity() );
			null === $p ? ++$missing : $profit += $p;
		}
		return array( $profit, $missing );
	}

	public static function field(): void {
		woocommerce_wp_text_input(
			array(
				'id'          => self::META,
				'label'       => __( 'Cost price', 'adminova' ),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( 'What the item costs you. Used by Adminova to show profit.', 'adminova' ),
			)
		);
	}

	public static function save( $post_id ): void {
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		$v = isset( $_POST[ self::META ] ) ? wc_format_decimal( wp_unslash( $_POST[ self::META ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product-save nonce
		'' === $v ? delete_post_meta( $post_id, self::META ) : update_post_meta( $post_id, self::META, $v );
	}
}
