<?php
use Brain\Monkey\Functions;

final class NotifyTest extends MonkeyTestCase {
	private array $mails = array();
	private array $posts = array();
	private array $config = array();

	protected function setUp(): void {
		parent::setUp();
		$this->mails  = array();
		$this->posts  = array();
		$this->config = array();
		Functions\when( 'get_option' )->alias(
			fn( $k, $d = false ) => match ( $k ) {
				Adminova_Notify::OPTION => $this->config,
				'admin_email'           => 'owner@example.com',
				default                 => $d,
			}
		);
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body ) {
				$this->mails[] = array( $to, $subject, $body );
				return true;
			}
		);
		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) {
				$this->posts[] = array( $url, $args );
				return array();
			}
		);
		Functions\when( 'wc_price' )->alias( fn( $v ) => '<b>' . $v . ' EUR</b>' );
	}

	private function freshOrder( $notified = '' ) {
		$o = $this->order(
			8,
			'shop_order',
			array(
				'get_meta'               => $notified,
				'get_order_number'       => '8',
				'get_billing_first_name' => 'Anna',
				'get_billing_last_name'  => '',
				'get_total'              => '9.00',
				'get_currency'           => 'EUR',
				'get_edit_order_url'     => 'http://x/o/8',
			)
		);
		Functions\when( 'wc_get_order' )->justReturn( $o );
		return $o;
	}

	public function test_emails_the_site_admin_by_default_and_marks_the_order(): void {
		$o = $this->freshOrder();
		$o->shouldReceive( 'update_meta_data' )->once()->with( '_adminova_notified', '1' );
		$o->shouldReceive( 'save' )->once();

		Adminova_Notify::send( 8 );

		$this->assertSame( 'owner@example.com', $this->mails[0][0] );
		$this->assertStringContainsString( 'New order #8 from Anna', $this->mails[0][2] );
		$this->assertStringContainsString( '9.00 EUR', $this->mails[0][2] );
		$this->assertSame( array(), $this->posts, 'no Telegram without a token' );
	}

	public function test_tells_only_once_per_order(): void {
		$o = $this->freshOrder( '1' );
		$o->shouldReceive( 'save' )->never();
		Adminova_Notify::send( 8 );
		$this->assertSame( array(), $this->mails );
	}

	public function test_uses_the_configured_address(): void {
		$this->config = array( 'email' => 'shop@example.com' );
		$this->freshOrder();
		Adminova_Notify::send( 8 );
		$this->assertSame( 'shop@example.com', $this->mails[0][0] );
	}

	public function test_sends_to_telegram_without_blocking_when_configured(): void {
		$token        = '123456789:AAEhBOweik6ad9r_QXMENQjcrOTcBdgpsAB';
		$this->config = array(
			'tg_token' => $token,
			'tg_chat'  => '-100123',
		);
		$this->freshOrder();
		Adminova_Notify::send( 8 );

		$this->assertSame( "https://api.telegram.org/bot$token/sendMessage", $this->posts[0][0] );
		$this->assertFalse( $this->posts[0][1]['blocking'] );
		$this->assertSame( '-100123', $this->posts[0][1]['body']['chat_id'] );
	}

	public function test_a_bad_token_never_reaches_the_http_layer(): void {
		$this->config = array(
			'tg_token' => 'evil.com/x@',
			'tg_chat'  => '-100123',
		);
		$this->freshOrder();
		Adminova_Notify::send( 8 );
		$this->assertSame( array(), $this->posts );
	}

	public function test_blank_token_field_keeps_the_saved_token(): void {
		$token        = '123456789:AAEhBOweik6ad9r_QXMENQjcrOTcBdgpsAB';
		$this->config = array( 'tg_token' => $token );
		$out          = Adminova_Notify::sanitize(
			array(
				'email'    => 'a@b.co',
				'tg_token' => '',
				'tg_chat'  => '12',
			)
		);
		$this->assertSame( $token, $out['tg_token'] );
		$this->assertSame( 'a@b.co', $out['email'] );
	}

	public function test_invalid_input_is_dropped_on_save(): void {
		$out = Adminova_Notify::sanitize(
			array(
				'email'    => 'not-an-email',
				'tg_token' => 'garbage',
				'tg_chat'  => 'abc',
			)
		);
		$this->assertSame( array( 'email' => '', 'tg_token' => '', 'tg_chat' => '' ), $out );
	}

	public function test_hooks_fire_on_paid_and_on_hold_orders(): void {
		Adminova_Notify::hooks();
		$this->assertTrue( has_action( 'woocommerce_order_status_processing', array( Adminova_Notify::class, 'send' ) ) !== false );
		$this->assertTrue( has_action( 'woocommerce_order_status_on-hold', array( Adminova_Notify::class, 'send' ) ) !== false );
	}
}
