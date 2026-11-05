<?php
defined( 'ABSPATH' ) || exit;

// Add a private note to an order straight from the dashboard.
class Adminova_Notes {
	const MAX_LEN = 1000;

	public static function hooks(): void {
		add_action( 'adminova_dashboard_widget', array( self::class, 'form' ) );
		add_action( 'admin_post_adminova_note', array( self::class, 'save' ) );
	}

	public static function clean_note( $v ): string {
		return mb_substr( trim( is_scalar( $v ) ? (string) $v : '' ), 0, self::MAX_LEN );
	}

	public static function form(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		printf(
			'<h4>%s</h4><form method="post" action="%s" class="adminova-notes"><input type="hidden" name="action" value="adminova_note">%s<input type="number" name="order" min="1" required placeholder="%s"><input type="text" name="note" maxlength="%d" required placeholder="%s"><button class="button">%s</button></form>',
			esc_html__( 'Quick order note', 'adminova' ),
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'adminova_note', '_wpnonce', true, false ),
			esc_attr__( 'Order #', 'adminova' ),
			absint( self::MAX_LEN ),
			esc_attr__( 'Private note...', 'adminova' ),
			esc_html__( 'Add', 'adminova' )
		);
	}

	public static function save(): void {
		check_admin_referer( 'adminova_note' );
		$id    = absint( $_POST['order'] ?? 0 );
		$note  = self::clean_note( sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ) );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : false;
		if ( ! $order || 'shop_order' !== $order->get_type() || '' === $note || ! current_user_can( 'edit_shop_order', $id ) ) {
			Adminova::back( 'failed' );
		}
		$order->add_order_note( $note, 0, true ); // private note, attributed to the current user
		Adminova::back( 'note' );
	}
}
