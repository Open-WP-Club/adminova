<?php
use Brain\Monkey\Functions;

final class AdminovaCoreTest extends MonkeyTestCase {
	private array $meta = array();

	protected function setUp(): void {
		parent::setUp();
		$this->meta = array();
		Functions\when( 'update_user_meta' )->alias(
			function ( $uid, $key, $val ) {
				$this->meta[ $key ] = $val;
			}
		);
		Functions\when( 'delete_user_meta' )->alias(
			function ( $uid, $key ) {
				unset( $this->meta[ $key ] );
			}
		);
		Functions\when( 'wp_send_json_success' )->justReturn( null );
	}

	private function json( callable $fn ): int {
		try {
			$fn();
		} catch ( JsonErrorException $e ) {
			return $e->getCode();
		}
		return 0;
	}

	public function test_theme_choice_is_saved_for_the_current_user(): void {
		$this->can( 'edit_posts' );
		$_POST = array( 'theme' => 'light' );
		Adminova::ajax_set_theme();
		$this->assertSame( array( 'adminova_theme' => 'light' ), $this->meta );
		$this->assertSame( array( 'adminova_theme' ), $this->nonces );
	}

	public function test_invalid_theme_is_rejected_with_400(): void {
		$this->can( 'edit_posts' );
		$_POST = array( 'theme' => '<script>' );
		$this->assertSame( 400, $this->json( array( Adminova::class, 'ajax_set_theme' ) ) );
		$this->assertSame( array(), $this->meta );
	}

	public function test_theme_change_is_rejected_with_403_without_edit_posts(): void {
		$_POST = array( 'theme' => 'dark' );
		$this->assertSame( 403, $this->json( array( Adminova::class, 'ajax_set_theme' ) ) );
		$this->assertSame( array(), $this->meta );
	}

	public function test_profile_accent_is_saved_when_valid_and_cleared_when_not(): void {
		$this->can( 'edit_user' );
		$_POST = array( 'adminova_accent' => '#FF8800' );
		Adminova::save_profile_field( 7 );
		$this->assertSame( '#ff8800', $this->meta['adminova_accent'] );

		$_POST = array( 'adminova_accent' => 'red;}body{display:none' );
		Adminova::save_profile_field( 7 );
		$this->assertArrayNotHasKey( 'adminova_accent', $this->meta );
	}

	public function test_profile_accent_needs_permission_to_edit_that_user(): void {
		$_POST = array( 'adminova_accent' => '#ff8800' );
		Adminova::save_profile_field( 7 );
		$this->assertSame( array(), $this->meta );
	}

	public function test_dark_mode_class_is_added_server_side_by_default(): void {
		$this->can( 'edit_posts' );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		$this->assertSame( ' foo adminova adminova-dark', Adminova::body_class( ' foo' ) );

		Functions\when( 'get_user_meta' )->justReturn( 'light' );
		$this->assertSame( ' foo adminova', Adminova::body_class( ' foo' ) );
	}

	public function test_body_class_is_untouched_for_people_who_cannot_edit_posts(): void {
		$this->assertSame( ' foo', Adminova::body_class( ' foo' ) );
	}

	public function test_done_notice_only_prints_known_messages(): void {
		$_GET = array( 'adminova_done' => 'note' );
		ob_start();
		Adminova::done_notice();
		$this->assertStringContainsString( 'Note added.', ob_get_clean() );

		$_GET = array( 'adminova_done' => '<script>alert(1)</script>' );
		ob_start();
		Adminova::done_notice();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_every_feature_switch_controls_its_hooks(): void {
		$all = array_fill_keys( array_keys( Adminova_Features::DEFAULTS ), false );
		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => Adminova_Features::OPTION === $k ? $all : $d );
		Adminova::init();

		$this->assertFalse( has_action( 'wp_dashboard_setup', array( Adminova_Dashboard::class, 'register' ) ) );
		$this->assertFalse( has_action( 'admin_post_adminova_note', array( Adminova_Notes::class, 'save' ) ) );
		$this->assertFalse( has_action( 'admin_post_adminova_undo', array( Adminova_Undo::class, 'undo' ) ) );
		$this->assertFalse( has_filter( 'admin_body_class', array( Adminova::class, 'body_class' ) ) );
		$this->assertFalse( has_action( 'admin_bar_menu', array( Adminova::class, 'search_button' ) ) );
		$this->assertNotFalse( has_action( 'admin_menu', array( Adminova_Settings::class, 'menu' ) ), 'settings page stays so you can switch things back on' );
	}

	public function test_switching_a_feature_on_registers_its_hooks(): void {
		$on = array_fill_keys( array_keys( Adminova_Features::DEFAULTS ), true );
		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => Adminova_Features::OPTION === $k ? $on : $d );
		Adminova::init();

		$this->assertNotFalse( has_action( 'wp_dashboard_setup', array( Adminova_Dashboard::class, 'register' ) ) );
		$this->assertNotFalse( has_action( 'admin_post_adminova_note', array( Adminova_Notes::class, 'save' ) ) );
		$this->assertNotFalse( has_action( 'admin_post_adminova_restock', array( Adminova_Restock::class, 'handle' ) ) );
		$this->assertNotFalse( has_action( 'admin_post_adminova_undo', array( Adminova_Undo::class, 'undo' ) ) );
		$this->assertNotFalse( has_filter( 'admin_body_class', array( Adminova::class, 'body_class' ) ) );
	}

	public function test_settings_page_has_a_checkbox_for_every_feature(): void {
		Functions\when( 'settings_fields' )->justReturn( null );
		Functions\when( 'submit_button' )->justReturn( null );
		Functions\when( 'checked' )->justReturn( '' );
		ob_start();
		Adminova_Settings::render();
		$html = ob_get_clean();
		foreach ( array_keys( Adminova_Features::DEFAULTS ) as $slug ) {
			$this->assertStringContainsString( 'name="adminova_features[' . $slug . ']"', $html, "missing toggle for $slug" );
		}
	}
}
