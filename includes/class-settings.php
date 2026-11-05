<?php
defined( 'ABSPATH' ) || exit;

// Settings > Adminova: one checkbox per extra.
class Adminova_Settings {
	public static function hooks(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_filter( 'plugin_action_links_adminova/adminova.php', array( self::class, 'links' ) );
	}

	private static function labels(): array {
		return array(
			'skin'      => array( __( 'Modern look (dark mode, accent colour)', 'adminova' ), __( 'Restyles the whole admin.', 'adminova' ) ),
			'palette'   => array( __( 'Quick search (Ctrl/Cmd+K)', 'adminova' ), __( 'Jump to pages, search orders and products.', 'adminova' ) ),
			'dashboard' => array( __( 'Dashboard overview', 'adminova' ), __( 'Sales, chart, latest orders, status links.', 'adminova' ) ),
			'shortcuts' => array( __( 'Orders menu in the top bar', 'adminova' ), __( 'Counts per status with links.', 'adminova' ) ),
			'alerts'    => array( __( 'New-order pop-up with sound', 'adminova' ), __( 'While an admin page is open.', 'adminova' ) ),
			'print'     => array( __( 'Print order slips (bulk action)', 'adminova' ), '' ),
			'export'    => array( __( 'Export orders to CSV (bulk action)', 'adminova' ), '' ),
			'filters'   => array( __( 'Saved order filters', 'adminova' ), __( 'Per user, shown above the orders list.', 'adminova' ) ),
			'notes'     => array( __( 'Quick order note on the dashboard', 'adminova' ), '' ),
			'restock'   => array( __( 'Restock button for low-stock products', 'adminova' ), '' ),
			'profit'    => array( __( 'Profit (needs cost prices on products)', 'adminova' ), __( 'Adds a Cost price field to products and a Profit tile.', 'adminova' ) ),
			'customers' => array( __( 'Customer history on the order screen', 'adminova' ), '' ),
			'notify'    => array( __( 'New-order email / Telegram message', 'adminova' ), __( 'Set the destinations below.', 'adminova' ) ),
			'undo'      => array( __( 'Log and undo order status changes', 'adminova' ), '' ),
		);
	}

	public static function menu(): void {
		add_options_page( 'Adminova', 'Adminova', 'manage_options', 'adminova', array( self::class, 'render' ) );
	}

	public static function links( $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=adminova' ) ) . '">' . esc_html__( 'Settings', 'adminova' ) . '</a>' );
		return $links;
	}

	public static function register(): void {
		register_setting(
			'adminova',
			Adminova_Features::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Adminova_Features', 'sanitize' ),
				'default'           => Adminova_Features::DEFAULTS,
			)
		);
		Adminova_Notify::register_settings();
	}

	public static function render(): void {
		echo '<div class="wrap"><h1>Adminova</h1><form method="post" action="options.php">';
		settings_fields( 'adminova' );
		echo '<table class="form-table">';
		foreach ( self::labels() as $slug => $l ) {
			printf(
				'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label></td></tr>',
				esc_html( $l[0] ),
				esc_attr( Adminova_Features::OPTION ),
				esc_attr( $slug ),
				checked( Adminova_Features::enabled( $slug ), true, false ),
				esc_html( $l[1] )
			);
		}
		do_action( 'adminova_settings_fields' );
		echo '</table>';
		submit_button();
		echo '</form>';
		do_action( 'adminova_settings_page' );
		echo '</div>';
	}
}
