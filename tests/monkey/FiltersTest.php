<?php
use Brain\Monkey\Functions;

final class FiltersTest extends MonkeyTestCase {
	private array $stored = array();

	protected function setUp(): void {
		parent::setUp();
		$this->stored = array();
		Functions\when( 'get_user_meta' )->alias( fn() => $this->stored );
		Functions\when( 'update_user_meta' )->alias(
			function ( $uid, $key, $val ) {
				$this->stored = $val;
			}
		);
	}

	public function test_saves_only_whitelisted_params_under_a_cleaned_name(): void {
		$this->can( 'edit_shop_orders' );
		$_POST = array(
			'name' => ' <b>Ready</b> ',
			'q'    => array(
				'status' => 'wc-processing',
				'action' => 'delete',
				'evil'   => 'x',
			),
		);

		$this->assertRedirectsWith( 'filter', array( Adminova_Filters::class, 'save' ) );
		$this->assertSame( array( 'Ready' => array( 'status' => 'wc-processing' ) ), $this->stored );
		$this->assertSame( array( 'adminova_filter' ), $this->nonces );
	}

	public function test_refuses_a_21st_filter_but_allows_overwriting_an_existing_one(): void {
		$this->can( 'edit_shop_orders' );
		$this->stored = array_fill_keys( array_map( fn( $i ) => "f$i", range( 1, Adminova_Filters::MAX_SAVED ) ), array() );
		$_POST        = array(
			'name' => 'brand new',
			'q'    => array( 's' => 'a' ),
		);
		$before       = $this->stored;

		$this->assertRedirectsWith( 'failed', array( Adminova_Filters::class, 'save' ) );
		$this->assertSame( $before, $this->stored );

		$_POST['name'] = 'f3';
		$this->assertRedirectsWith( 'filter', array( Adminova_Filters::class, 'save' ) );
		$this->assertSame( array( 's' => 'a' ), $this->stored['f3'] );
	}

	public function test_refuses_an_empty_name(): void {
		$this->can( 'edit_shop_orders' );
		$_POST = array( 'name' => '  ' );
		$this->assertRedirectsWith( 'failed', array( Adminova_Filters::class, 'save' ) );
		$this->assertSame( array(), $this->stored );
	}

	public function test_delete_removes_only_the_named_filter(): void {
		$this->can( 'edit_shop_orders' );
		$this->stored = array(
			'a' => array( 's' => '1' ),
			'b' => array( 's' => '2' ),
		);
		$_POST        = array( 'name' => 'a' );

		$this->assertRedirectsWith( 'filter', array( Adminova_Filters::class, 'delete' ) );
		$this->assertSame( array( 'b' => array( 's' => '2' ) ), $this->stored );
	}

	public function test_save_and_delete_die_without_permission(): void {
		foreach ( array( 'save', 'delete' ) as $method ) {
			try {
				Adminova_Filters::$method();
				$this->fail( "$method should die" );
			} catch ( DieException $e ) {
				$this->assertSame( 403, $e->getCode() );
			}
		}
		$this->assertSame( array(), $this->stored );
	}

	public function test_dashboard_pills_are_hidden_without_permission_or_without_filters(): void {
		ob_start();
		Adminova_Filters::dashboard();
		$this->assertSame( '', ob_get_clean() );

		$this->can( 'edit_shop_orders' );
		ob_start();
		Adminova_Filters::dashboard();
		$this->assertSame( '', ob_get_clean(), 'nothing saved, nothing shown' );
	}
}
