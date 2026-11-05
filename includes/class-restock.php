<?php
defined( 'ABSPATH' ) || exit;

// "+N" restock button on the dashboard's low-stock rows.
class Adminova_Restock {
	const DEFAULT_QTY = 10;
	const MAX_QTY     = 1000;

	public static function hooks(): void {
		add_action( 'admin_post_adminova_restock', array( self::class, 'handle' ) );
	}

	public static function clamp( $v ): int {
		return max( 1, min( self::MAX_QTY, absint( $v ) ) );
	}

	public static function form( int $product_id ): void {
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}
		printf(
			'<form method="post" action="%s" class="adminova-inline"><input type="hidden" name="action" value="adminova_restock"><input type="hidden" name="product" value="%d">%s<input type="number" name="qty" value="%d" min="1" max="%d"><button class="button">%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			absint( $product_id ),
			wp_nonce_field( 'adminova_restock_' . $product_id, '_wpnonce', true, false ),
			absint( self::DEFAULT_QTY ),
			absint( self::MAX_QTY ),
			esc_html__( 'Restock', 'adminova' )
		);
	}

	public static function handle(): void {
		$id = absint( $_POST['product'] ?? 0 );
		check_admin_referer( 'adminova_restock_' . $id );
		if ( ! current_user_can( 'edit_product', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'adminova' ), '', 403 );
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false;
		if ( ! $product || ! $product->managing_stock() ) {
			Adminova::back( 'failed' );
		}
		wc_update_product_stock( $product, self::clamp( wp_unslash( $_POST['qty'] ?? self::DEFAULT_QTY ) ), 'increase' );
		Adminova::back( 'restock' );
	}
}
