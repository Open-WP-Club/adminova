<?php
defined( 'ABSPATH' ) || exit;

// Saved order-list filters, per user. Shown above the orders list and as pills on the dashboard.
class Adminova_Filters {
	const META      = 'adminova_filters';
	const MAX_SAVED = 20;
	const ALLOWED   = array( 'status', 'post_status', 's', 'm', '_customer_user', '_payment_method' );

	public static function hooks(): void {
		add_action( 'admin_notices', array( self::class, 'bar' ) );
		add_action( 'adminova_dashboard_widget', array( self::class, 'dashboard' ) );
		add_action( 'admin_post_adminova_filter_save', array( self::class, 'save' ) );
		add_action( 'admin_post_adminova_filter_delete', array( self::class, 'delete' ) );
	}

	// Only whitelisted list parameters are ever stored or replayed.
	public static function clean_params( $q ): array {
		$out = array();
		foreach ( is_array( $q ) ? $q : array() as $k => $v ) {
			if ( in_array( $k, self::ALLOWED, true ) && is_scalar( $v ) && '' !== (string) $v ) {
				$out[ $k ] = sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}

	public static function clean_name( $v ): string {
		return mb_substr( trim( wp_strip_all_tags( is_scalar( $v ) ? (string) $v : '' ) ), 0, 40 );
	}

	private static function saved(): array {
		$s = get_user_meta( get_current_user_id(), self::META, true );
		return is_array( $s ) ? $s : array();
	}

	private static function links( bool $with_delete ): void {
		foreach ( self::saved() as $name => $params ) {
			$name = (string) $name;
			printf( '<a class="adminova-pill" href="%s">%s</a>', esc_url( add_query_arg( $params, Adminova_Shortcuts::orders_url() ) ), esc_html( $name ) );
			if ( $with_delete ) {
				printf(
					'<form method="post" action="%s" class="adminova-inline"><input type="hidden" name="action" value="adminova_filter_delete"><input type="hidden" name="name" value="%s">%s<button class="button-link" title="%s">&times;</button></form>',
					esc_url( admin_url( 'admin-post.php' ) ),
					esc_attr( $name ),
					wp_nonce_field( 'adminova_filter', '_wpnonce', true, false ),
					esc_attr__( 'Remove', 'adminova' )
				);
			}
		}
	}

	public static function dashboard(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) || ! self::saved() ) {
			return;
		}
		echo '<h4>' . esc_html__( 'Saved filters', 'adminova' ) . '</h4><p class="adminova-pills">';
		self::links( false );
		echo '</p>';
	}

	public static function bar(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, Adminova_Bulk::SCREENS, true ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		echo '<div class="adminova-filters">';
		self::links( true );
		printf( '<form method="post" action="%s" class="adminova-inline"><input type="hidden" name="action" value="adminova_filter_save">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'adminova_filter' );
		foreach ( self::clean_params( $_GET ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, re-sanitised on save
			printf( '<input type="hidden" name="q[%s]" value="%s">', esc_attr( $k ), esc_attr( $v ) );
		}
		printf( '<input type="text" name="name" maxlength="40" required placeholder="%s"> <button class="button">%s</button></form></div>', esc_attr__( 'Save this view as...', 'adminova' ), esc_html__( 'Save', 'adminova' ) );
	}

	public static function save(): void {
		check_admin_referer( 'adminova_filter' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'adminova' ), '', 403 );
		}
		$name   = self::clean_name( wp_unslash( $_POST['name'] ?? '' ) );
		$params = self::clean_params( wp_unslash( $_POST['q'] ?? array() ) );
		$saved  = self::saved();
		if ( '' === $name || ( count( $saved ) >= self::MAX_SAVED && ! isset( $saved[ $name ] ) ) ) {
			Adminova::back( 'failed' );
		}
		$saved[ $name ] = $params;
		update_user_meta( get_current_user_id(), self::META, $saved );
		Adminova::back( 'filter' );
	}

	public static function delete(): void {
		check_admin_referer( 'adminova_filter' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'adminova' ), '', 403 );
		}
		$saved = self::saved();
		unset( $saved[ self::clean_name( wp_unslash( $_POST['name'] ?? '' ) ) ] );
		update_user_meta( get_current_user_id(), self::META, $saved );
		Adminova::back( 'filter' );
	}
}
