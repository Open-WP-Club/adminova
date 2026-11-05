<?php
defined( 'ABSPATH' ) || exit;

// Log of the last order status changes made in the admin (single or bulk), with an Undo button.
class Adminova_Undo {
	const OPTION = 'adminova_status_log';
	const MAX    = 50;

	private static $undoing = false;

	public static function hooks(): void {
		add_action( 'woocommerce_order_status_changed', array( self::class, 'log' ), 10, 3 );
		add_action( 'admin_post_adminova_undo', array( self::class, 'undo' ) );
		add_action( 'adminova_settings_page', array( self::class, 'render' ) );
	}

	public static function push( array $log, array $entry, int $max = self::MAX ): array {
		array_unshift( $log, $entry );
		return array_slice( $log, 0, $max );
	}

	// Only undo if the order is still where that change left it, so a later change is never overwritten.
	public static function can_undo( array $entry, string $current ): bool {
		return '' !== $entry['from'] && $entry['from'] !== $entry['to'] && $entry['to'] === $current;
	}

	public static function log( $id, $from, $to ): void {
		if ( self::$undoing || ! is_admin() ) {
			return;
		}
		$entry = array(
			'key'  => wp_generate_uuid4(),
			'id'   => (int) $id,
			'from' => (string) $from,
			'to'   => (string) $to,
			'user' => get_current_user_id(),
			'time' => time(),
		);
		update_option( self::OPTION, self::push( (array) get_option( self::OPTION, array() ), $entry ), false );
	}

	public static function undo(): void {
		$key = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
		check_admin_referer( 'adminova_undo_' . $key );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'adminova' ), '', 403 );
		}
		$log = (array) get_option( self::OPTION, array() );
		foreach ( $log as $i => $e ) {
			if ( $e['key'] !== $key ) {
				continue;
			}
			$order = wc_get_order( $e['id'] );
			if ( $order && current_user_can( 'edit_shop_order', $e['id'] ) && self::can_undo( $e, $order->get_status() ) ) {
				self::$undoing = true;
				$order->update_status( $e['from'], __( 'Adminova: status change undone.', 'adminova' ) );
				self::$undoing = false;
				unset( $log[ $i ] );
				update_option( self::OPTION, array_values( $log ), false );
				Adminova::back( 'undo' );
			}
		}
		Adminova::back( 'failed' );
	}

	public static function render(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Recent order status changes', 'adminova' ) . '</h2>';
		$log = array_slice( (array) get_option( self::OPTION, array() ), 0, 20 );
		if ( ! $log ) {
			echo '<p>' . esc_html__( 'Nothing yet.', 'adminova' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><tbody>';
		foreach ( $log as $e ) {
			$order = wc_get_order( $e['id'] );
			$user  = get_userdata( $e['user'] );
			printf(
				'<tr><td>#%d</td><td>%s &rarr; %s</td><td>%s</td><td>%s</td><td>',
				(int) $e['id'],
				esc_html( wc_get_order_status_name( $e['from'] ) ),
				esc_html( wc_get_order_status_name( $e['to'] ) ),
				esc_html( $user ? $user->display_name : '-' ),
				esc_html( human_time_diff( $e['time'] ) . ' ' . __( 'ago', 'adminova' ) )
			);
			if ( $order && self::can_undo( $e, $order->get_status() ) ) {
				printf(
					'<form method="post" action="%s"><input type="hidden" name="action" value="adminova_undo"><input type="hidden" name="key" value="%s">%s<button class="button">%s</button></form>',
					esc_url( admin_url( 'admin-post.php' ) ),
					esc_attr( $e['key'] ),
					wp_nonce_field( 'adminova_undo_' . $e['key'], '_wpnonce', true, false ),
					esc_html__( 'Undo', 'adminova' )
				);
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
