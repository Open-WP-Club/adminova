<?php
defined( 'ABSPATH' ) || exit;

// Email (and optional Telegram) message for each new order, for when nobody has the admin open.
class Adminova_Notify {
	const OPTION = 'adminova_notify';

	public static function hooks(): void {
		add_action( 'woocommerce_order_status_processing', array( self::class, 'send' ) );
		add_action( 'woocommerce_order_status_on-hold', array( self::class, 'send' ) );
	}

	public static function valid_token( $v ): bool {
		return is_string( $v ) && 1 === preg_match( '/^\d{6,12}:[A-Za-z0-9_-]{30,50}$/', $v );
	}

	public static function valid_chat( $v ): bool {
		return is_string( $v ) && 1 === preg_match( '/^-?\d{1,20}$/', $v );
	}

	public static function message( string $number, string $name, string $total, string $url ): string {
		return sprintf( "New order #%s from %s\nTotal: %s\n%s", $number, $name, $total, $url );
	}

	public static function send( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_adminova_notified' ) ) {
			return; // status can flip back and forth; tell once per order
		}
		$order->update_meta_data( '_adminova_notified', '1' );
		$order->save();

		$cfg  = wp_parse_args(
			get_option( self::OPTION, array() ),
			array(
				'email'    => '',
				'tg_token' => '',
				'tg_chat'  => '',
			)
		);
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$msg  = self::message(
			$order->get_order_number(),
			$name ? $name : __( 'Guest', 'adminova' ),
			html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ),
			$order->get_edit_order_url()
		);
		wp_mail( $cfg['email'] ? $cfg['email'] : get_option( 'admin_email' ), 'New order #' . $order->get_order_number(), $msg );
		if ( self::valid_token( $cfg['tg_token'] ) && self::valid_chat( $cfg['tg_chat'] ) ) {
			// fixed host; the token is validated above so it can't redirect the request elsewhere
			wp_remote_post(
				'https://api.telegram.org/bot' . $cfg['tg_token'] . '/sendMessage',
				array(
					'body'     => array(
						'chat_id' => $cfg['tg_chat'],
						'text'    => $msg,
					),
					'timeout'  => 5,
					'blocking' => false,
				)
			);
		}
	}

	public static function register_settings(): void {
		register_setting(
			'adminova',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
		add_action( 'adminova_settings_fields', array( self::class, 'fields' ) );
	}

	public static function sanitize( $in ): array {
		$in  = is_array( $in ) ? $in : array();
		$old = get_option( self::OPTION, array() );
		$tok = isset( $in['tg_token'] ) ? trim( $in['tg_token'] ) : '';
		return array(
			'email'    => sanitize_email( $in['email'] ?? '' ),
			// a blank token field keeps the saved one; "-" clears it
			'tg_token' => '' === $tok ? ( $old['tg_token'] ?? '' ) : ( self::valid_token( $tok ) ? $tok : '' ),
			'tg_chat'  => self::valid_chat( $in['tg_chat'] ?? '' ) ? $in['tg_chat'] : '',
		);
	}

	public static function fields(): void {
		$cfg = wp_parse_args(
			get_option( self::OPTION, array() ),
			array(
				'email'    => '',
				'tg_token' => '',
				'tg_chat'  => '',
			)
		);
		printf(
			'<tr><th>%1$s</th><td><input type="email" name="adminova_notify[email]" value="%2$s" class="regular-text" placeholder="%3$s"></td></tr>'
			. '<tr><th>%4$s</th><td><input type="password" name="adminova_notify[tg_token]" class="regular-text" autocomplete="off" placeholder="%5$s"><p class="description">%6$s</p></td></tr>'
			. '<tr><th>%7$s</th><td><input type="text" name="adminova_notify[tg_chat]" value="%8$s" class="regular-text"></td></tr>',
			esc_html__( 'New-order email to', 'adminova' ),
			esc_attr( $cfg['email'] ),
			esc_attr( get_option( 'admin_email' ) ),
			esc_html__( 'Telegram bot token', 'adminova' ),
			esc_attr( $cfg['tg_token'] ? __( 'saved (leave blank to keep)', 'adminova' ) : '' ),
			esc_html__( 'Optional. Sent only to api.telegram.org when a new order arrives.', 'adminova' ),
			esc_html__( 'Telegram chat ID', 'adminova' ),
			esc_attr( $cfg['tg_chat'] )
		);
	}
}
