<?php
defined( 'ABSPATH' ) || exit;

// Shared plumbing for "bulk action on the orders list -> admin-post page" features (print, CSV export).
class Adminova_Bulk {
	const MAX     = 100;
	const SCREENS = array( 'woocommerce_page_wc-orders', 'edit-shop_order' );

	public static function parse_ids( $v ): array {
		$ids = array_filter( array_map( 'absint', is_array( $v ) ? $v : explode( ',', (string) $v ) ) );
		return array_slice( array_values( array_unique( $ids ) ), 0, self::MAX );
	}

	// nonce is bound to the action and the exact id list, so the link can't be edited to touch other orders
	public static function nonce_action( string $action, string $ids ): string {
		return $action . '_' . md5( $ids );
	}

	public static function register( string $action, string $label ): void {
		foreach ( self::SCREENS as $screen ) {
			add_filter(
				"bulk_actions-$screen",
				function ( $actions ) use ( $action, $label ) {
					$actions[ $action ] = $label;
					return $actions;
				}
			);
			add_filter(
				"handle_bulk_actions-$screen",
				function ( $redirect, $chosen, $ids ) use ( $action ) {
					if ( $chosen !== $action ) {
						return $redirect;
					}
					$list = implode( ',', self::parse_ids( $ids ) );
					$url  = add_query_arg(
						array(
							'action' => $action,
							'ids'    => $list,
						),
						admin_url( 'admin-post.php' )
					);
					return wp_nonce_url( $url, self::nonce_action( $action, $list ) );
				},
				10,
				3
			);
		}
	}

	// For the admin-post side: verified ids, or dies.
	public static function request_ids( string $action ): array {
		$ids = self::parse_ids( $_GET['ids'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified on the next line
		check_admin_referer( self::nonce_action( $action, implode( ',', $ids ) ) );
		if ( ! current_user_can( 'edit_shop_orders' ) || ! function_exists( 'wc_get_order' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'adminova' ), '', 403 );
		}
		return $ids;
	}
}
