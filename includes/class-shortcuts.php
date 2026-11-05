<?php
defined( 'ABSPATH' ) || exit;

class Adminova_Shortcuts {
	// literal strings so translation tools can extract them
	private static function statuses(): array {
		return array(
			'processing' => __( 'Processing', 'adminova' ),
			'on-hold'    => __( 'On hold', 'adminova' ),
			'pending'    => __( 'Pending payment', 'adminova' ),
		);
	}
	public static function hooks(): void {
		add_action( 'admin_bar_menu', array( self::class, 'bar' ), 80 );
	}

	private static function hpos( ?bool $hpos ): bool {
		return $hpos ?? ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
	}

	public static function new_order_url( ?bool $hpos = null ): string {
		return admin_url( self::hpos( $hpos ) ? 'admin.php?page=wc-orders&action=new' : 'post-new.php?post_type=shop_order' );
	}

	public static function orders_url( string $status = '', ?bool $hpos = null ): string {
		$hpos = self::hpos( $hpos );
		$base = admin_url( $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' );
		return $status === '' ? $base : add_query_arg( $hpos ? 'status' : 'post_status', 'wc-' . $status, $base );
	}

	public static function bar( $bar ): void {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}
		$counts = Adminova_Stats::get()['by_status'];
		$bar->add_node(
			array(
				'id'    => 'adminova-orders',
				'title' => __( 'Orders', 'adminova' ),
				'href'  => self::orders_url(),
			)
		);
		foreach ( self::statuses() as $slug => $label ) {
			$bar->add_node(
				array(
					'id'     => "adminova-orders-$slug",
					'parent' => 'adminova-orders',
					'title'  => sprintf( '%s (%d)', $label, $counts[ $slug ] ?? 0 ),
					'href'   => self::orders_url( $slug ),
				)
			);
		}
	}
}
