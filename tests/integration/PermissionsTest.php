<?php
class PermissionsTest extends AdminovaTestCase {
	public function test_shop_manager_sees_sales_on_the_dashboard(): void {
		$this->actingAs( 'shop_manager' );
		$this->makeOrder( 'completed', '80.00' );
		$out = $this->render( array( 'Adminova_Dashboard', 'render' ) );
		$this->assertStringContainsString( 'Sales today', $out );
		$this->assertStringContainsString( 'adminova-chart', $out );
	}

	public function test_an_order_editor_without_reports_sees_no_money(): void {
		$this->makeOrder( 'completed', '80.00' );
		$this->actingAsOrderEditor();
		$this->assertFalse( current_user_can( 'view_woocommerce_reports' ), 'precondition' );

		$out = $this->render( array( 'Adminova_Dashboard', 'render' ) );
		$this->assertStringContainsString( 'Orders today', $out );
		$this->assertStringNotContainsString( 'Sales today', $out );
		$this->assertStringNotContainsString( '80.00', $out );
		$this->assertStringNotContainsString( 'adminova-chart', $out );
	}

	public function test_dashboard_widget_is_not_registered_for_people_who_cannot_edit_orders(): void {
		$this->actingAs( 'editor' );
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		wp_dashboard_setup();
		global $wp_meta_boxes;
		$this->assertArrayNotHasKey( 'adminova_overview', $wp_meta_boxes['dashboard']['normal']['core'] ?? array() );
	}

	public function test_dashboard_widget_is_registered_first_for_a_shop_manager(): void {
		$this->actingAs( 'shop_manager' );
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		wp_dashboard_setup();
		global $wp_meta_boxes;
		$this->assertSame( 'adminova_overview', array_key_first( $wp_meta_boxes['dashboard']['normal']['core'] ) );
	}

	public function test_alerts_answer_order_editors_but_not_other_users(): void {
		$this->actingAs( 'subscriber' );
		$this->assertArrayNotHasKey( 'adminova_new', apply_filters( 'heartbeat_received', array(), array( 'adminova_since' => time() ) ) );

		$this->actingAs( 'shop_manager' );
		$since = time() - 60;
		$this->makeOrder( 'processing', '12.00' );
		$r = apply_filters( 'heartbeat_received', array(), array( 'adminova_since' => $since ) );
		$this->assertCount( 1, $r['adminova_new']['orders'] );
		$this->assertStringContainsString( '12.00', $r['adminova_new']['orders'][0]['total'] );
	}

	public function test_alert_amount_is_withheld_from_order_editors_without_reports(): void {
		$this->makeOrder( 'processing', '12.00' );
		$this->actingAsOrderEditor();
		$r = apply_filters( 'heartbeat_received', array(), array( 'adminova_since' => time() - 60 ) );
		$this->assertSame( '', $r['adminova_new']['orders'][0]['total'] );
	}

	public function test_orders_menu_in_the_admin_bar_needs_manage_woocommerce(): void {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		$this->actingAs( 'editor' );
		$bar = new WP_Admin_Bar();
		Adminova_Shortcuts::bar( $bar );
		$this->assertNull( $bar->get_node( 'adminova-orders' ) );

		$this->actingAs( 'shop_manager' );
		$bar = new WP_Admin_Bar();
		Adminova_Shortcuts::bar( $bar );
		$this->assertNotNull( $bar->get_node( 'adminova-orders' ) );
	}

	public function test_orders_url_matches_the_storage_mode_in_use(): void {
		$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$this->assertStringContainsString( $hpos ? 'page=wc-orders' : 'post_type=shop_order', Adminova_Shortcuts::orders_url() );
	}
}
