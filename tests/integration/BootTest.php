<?php
class BootTest extends AdminovaTestCase {
	public function test_plugin_and_woocommerce_are_loaded(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
		$this->assertTrue( class_exists( 'Adminova' ) );
		$this->assertNotFalse( has_action( 'plugins_loaded', array( 'Adminova', 'init' ) ) );
	}

	public function test_default_features_register_their_hooks(): void {
		Adminova::init();
		$this->assertNotFalse( has_action( 'wp_dashboard_setup', array( 'Adminova_Dashboard', 'register' ) ) );
		$this->assertNotFalse( has_action( 'admin_post_adminova_restock', array( 'Adminova_Restock', 'handle' ) ) );
		$this->assertFalse( has_action( 'woocommerce_order_status_processing', array( 'Adminova_Notify', 'send' ) ), 'notify is off by default' );
	}

	public function test_saving_the_settings_form_turns_features_off_and_on(): void {
		$this->actingAs( 'administrator' );
		update_option( Adminova_Features::OPTION, Adminova_Features::sanitize( array( 'dashboard' => '1' ) ) );
		$this->assertTrue( Adminova_Features::enabled( 'dashboard' ) );
		$this->assertFalse( Adminova_Features::enabled( 'undo' ) );
		$this->assertFalse( Adminova_Features::enabled( 'notify' ) );
	}

	public function test_settings_page_renders_for_an_administrator(): void {
		$this->actingAs( 'administrator' );
		$html = $this->render( array( 'Adminova_Settings', 'render' ) );
		foreach ( array_keys( Adminova_Features::DEFAULTS ) as $slug ) {
			$this->assertStringContainsString( 'adminova_features[' . $slug . ']', $html );
		}
	}

	public function test_uninstall_removes_options_and_user_settings(): void {
		$uid = $this->actingAs( 'administrator' );
		update_option( 'adminova_features', array( 'dashboard' => true ) );
		update_option( 'adminova_notify', array( 'email' => 'a@b.co' ) );
		update_option( 'adminova_status_log', array( array( 'id' => 1 ) ) );
		update_user_meta( $uid, 'adminova_theme', 'light' );
		update_user_meta( $uid, 'adminova_filters', array( 'x' => array() ) );

		define( 'WP_UNINSTALL_PLUGIN', 'adminova/adminova.php' );
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertFalse( get_option( 'adminova_features' ) );
		$this->assertFalse( get_option( 'adminova_notify' ) );
		$this->assertFalse( get_option( 'adminova_status_log' ) );
		$this->assertSame( '', get_user_meta( $uid, 'adminova_theme', true ) );
		$this->assertSame( '', get_user_meta( $uid, 'adminova_filters', true ) );
	}
}
