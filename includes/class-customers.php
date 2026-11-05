<?php
defined( 'ABSPATH' ) || exit;

// "Customer history" box on the order screen: how often this customer ordered before and how much they spent.
class Adminova_Customers {
	const LIMIT = 100;

	public static function hooks(): void {
		add_action( 'add_meta_boxes', array( self::class, 'box' ) );
	}

	// Each row is one earlier order: its total and its timestamp.
	public static function summary( array $rows ): array {
		return array(
			'count' => count( $rows ),
			'spent' => array_sum( array_column( $rows, 'total' ) ),
			'last'  => $rows ? max( array_column( $rows, 'ts' ) ) : 0,
		);
	}

	public static function box(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		foreach ( array( 'woocommerce_page_wc-orders', 'shop_order' ) as $screen ) {
			add_meta_box( 'adminova_customer', __( 'Customer history', 'adminova' ), array( self::class, 'render' ), $screen, 'side' );
		}
	}

	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order || ! $order->get_billing_email() ) {
			echo '<p>' . esc_html__( 'No email on this order.', 'adminova' ) . '</p>';
			return;
		}
		$others = wc_get_orders(
			array(
				'billing_email' => $order->get_billing_email(),
				'status'        => array( 'processing', 'completed' ),
				'exclude'       => array( $order->get_id() ),
				'limit'         => self::LIMIT,
			)
		);
		$rows   = array();
		foreach ( $others as $o ) {
			$rows[] = array(
				'total' => (float) $o->get_total(),
				'ts'    => $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0,
			);
		}
		$s = self::summary( $rows );
		if ( ! $s['count'] ) {
			echo '<p>' . esc_html__( 'First order from this customer.', 'adminova' ) . '</p>';
			return;
		}
		/* translators: %d: number of earlier orders */
		printf( '<p>%s</p>', esc_html( sprintf( _n( '%d earlier order', '%d earlier orders', $s['count'], 'adminova' ), $s['count'] ) ) );
		if ( current_user_can( 'view_woocommerce_reports' ) ) {
			/* translators: %s: amount spent */
			printf( '<p>%s</p>', wp_kses_post( sprintf( __( 'Spent: %s', 'adminova' ), wc_price( $s['spent'] ) ) ) );
		}
		/* translators: %s: date of the most recent earlier order */
		printf( '<p>%s</p>', esc_html( sprintf( __( 'Last order: %s', 'adminova' ), wp_date( get_option( 'date_format' ), $s['last'] ) ) ) );
		if ( self::LIMIT === $s['count'] ) {
			echo '<p><em>' . esc_html__( 'Showing the latest 100 orders only.', 'adminova' ) . '</em></p>';
		}
	}
}
