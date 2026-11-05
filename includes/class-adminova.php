<?php
defined( 'ABSPATH' ) || exit;

class Adminova {
	const THEMES = array( 'light', 'dark' );

	public static function init(): void {
		if ( defined( 'ADMINOVA_DISABLE' ) && ADMINOVA_DISABLE ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_notices', array( self::class, 'done_notice' ) );
		Adminova_Stats::hooks();
		Adminova_Settings::hooks();
		if ( Adminova_Features::enabled( 'skin' ) ) {
			add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
			add_action( 'admin_bar_menu', array( self::class, 'theme_toggle' ), 90 );
			add_action( 'wp_ajax_adminova_set_theme', array( self::class, 'ajax_set_theme' ) );
			add_action( 'show_user_profile', array( self::class, 'profile_field' ) );
			add_action( 'personal_options_update', array( self::class, 'save_profile_field' ) );
		}
		if ( Adminova_Features::enabled( 'palette' ) ) {
			add_action( 'admin_bar_menu', array( self::class, 'search_button' ), 85 );
		}
		foreach ( Adminova_Features::HOOKED as $slug => $class ) {
			if ( Adminova_Features::enabled( $slug ) ) {
				$class::hooks();
			}
		}
	}

	// After an admin-post action: go back where the user came from, with a result flag for done_notice().
	public static function back( string $done = '' ): void {
		$url = wp_get_referer() ? wp_get_referer() : admin_url();
		wp_safe_redirect( $done ? add_query_arg( 'adminova_done', $done, $url ) : $url );
		exit;
	}

	public static function done_notice(): void {
		$msgs = array(
			'note'    => array( __( 'Note added.', 'adminova' ), 'success' ),
			'restock' => array( __( 'Stock updated.', 'adminova' ), 'success' ),
			'filter'  => array( __( 'Saved filters updated.', 'adminova' ), 'success' ),
			'undo'    => array( __( 'Status change undone.', 'adminova' ), 'success' ),
			'failed'  => array( __( 'Nothing was changed.', 'adminova' ), 'warning' ),
		);
		$k    = sanitize_key( wp_unslash( $_GET['adminova_done'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks a fixed message
		if ( isset( $msgs[ $k ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $msgs[ $k ][1] ), esc_html( $msgs[ $k ][0] ) );
		}
	}

	public static function sanitize_theme( $v ): string {
		return is_string( $v ) && in_array( $v, self::THEMES, true ) ? $v : '';
	}

	public static function sanitize_accent( $v ): string {
		return is_string( $v ) && preg_match( '/^#[0-9a-f]{6}$/i', $v ) ? strtolower( $v ) : '';
	}

	public static function is_dark( $meta ): bool {
		return $meta !== 'light';
	}

	public static function enqueue(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_enqueue_style( 'adminova', ADMINOVA_URL . 'assets/admin.css', array(), ADMINOVA_VERSION );
		wp_enqueue_script( 'adminova', ADMINOVA_URL . 'assets/admin.js', array( 'jquery', 'heartbeat' ), ADMINOVA_VERSION, true );
		$accent = self::sanitize_accent( get_user_meta( get_current_user_id(), 'adminova_accent', true ) );
		if ( $accent ) {
			wp_add_inline_style( 'adminova', "body.adminova,body.adminova.adminova-dark{--adminova-accent:$accent}" );
		}
		$woo = function_exists( 'wc_get_orders' );
		wp_localize_script(
			'adminova',
			'adminova',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'now'      => time(),
				'palette'  => Adminova_Features::enabled( 'palette' ),
				'alerts'   => Adminova_Features::enabled( 'alerts' ),
				'nonce'    => wp_create_nonce( 'adminova_theme' ),
				'orders'   => $woo && current_user_can( 'edit_shop_orders' ) ? Adminova_Shortcuts::orders_url() . '&s=' : '',
				'products' => $woo && current_user_can( 'edit_products' ) ? admin_url( 'edit.php?post_type=product&s=' ) : '',
				'i18n'     => array(
					'placeholder' => __( 'Jump to a page, or search orders and products...', 'adminova' ),
					'orders'      => __( 'Search orders:', 'adminova' ),
					'products'    => __( 'Search products:', 'adminova' ),
					'newOrder'    => __( 'New order', 'adminova' ),
				),
			)
		);
	}

	// Server-side class so dark mode never flashes light on load.
	public static function body_class( $classes ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $classes;
		}
		$dark = self::is_dark( get_user_meta( get_current_user_id(), 'adminova_theme', true ) );
		return $classes . ' adminova' . ( $dark ? ' adminova-dark' : '' );
	}

	public static function theme_toggle( $bar ): void {
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'adminova-theme',
				'title' => __( 'Dark mode', 'adminova' ),
				'href'  => '#',
			)
		);
	}

	public static function search_button( $bar ): void {
		if ( is_admin() && current_user_can( 'edit_posts' ) ) {
			$bar->add_node(
				array(
					'id'    => 'adminova-search',
					'title' => __( 'Search (Ctrl K)', 'adminova' ),
					'href'  => '#',
				)
			);
		}
	}

	public static function profile_field( $user ): void {
		$accent = self::sanitize_accent( get_user_meta( $user->ID, 'adminova_accent', true ) ) ?: '#4f46e5';
		printf(
			'<table class="form-table"><tr><th>%s</th><td><input type="color" name="adminova_accent" value="%s"></td></tr></table>',
			esc_html__( 'Adminova accent colour', 'adminova' ),
			esc_attr( $accent )
		);
	}

	// core already verified the profile-form nonce before firing personal_options_update
	public static function save_profile_field( $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$accent = self::sanitize_accent( wp_unslash( $_POST['adminova_accent'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verified the profile-form nonce
		$accent ? update_user_meta( $user_id, 'adminova_accent', $accent ) : delete_user_meta( $user_id, 'adminova_accent' );
	}

	public static function ajax_set_theme(): void {
		check_ajax_referer( 'adminova_theme', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$theme = self::sanitize_theme( $_POST['theme'] ?? '' );
		if ( $theme === '' ) {
			wp_send_json_error( null, 400 );
		}
		update_user_meta( get_current_user_id(), 'adminova_theme', $theme );
		wp_send_json_success();
	}
}
