<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class RedirectException extends RuntimeException {}
class DieException extends RuntimeException {}
class JsonErrorException extends RuntimeException {}

// Shared setup: translation/escaping stubs, a controllable current_user_can(), and redirect/die/json
// calls that throw so a handler's `exit` path can be asserted without killing PHPUnit.
abstract class MonkeyTestCase extends TestCase {
	use MockeryPHPUnitIntegration;

	/** @var string[] capabilities the fake current user has */
	protected array $caps = array();
	/** @var array[] every current_user_can() call: [cap, ...args] */
	protected array $capChecks = array();
	/** @var string[] nonce actions passed to check_admin_referer / check_ajax_referer */
	protected array $nonces = array();
	protected array $post;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->caps      = array();
		$this->capChecks = array();
		$this->nonces    = array();
		$_POST           = array();
		$_GET            = array();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs(
			array(
				'wp_unslash'              => null,
				'absint'                  => fn( $v ) => abs( (int) $v ),
				'sanitize_text_field'     => fn( $v ) => trim( strip_tags( (string) $v ) ),
				'sanitize_textarea_field' => fn( $v ) => trim( strip_tags( (string) $v ) ),
				'sanitize_key'            => fn( $v ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ),
				'sanitize_email'          => fn( $v ) => filter_var( (string) $v, FILTER_VALIDATE_EMAIL ) ? (string) $v : '',
				'wp_strip_all_tags'       => fn( $v ) => strip_tags( (string) $v ),
				'wp_kses_post'            => null,
				'wp_parse_args'           => fn( $a, $d = array() ) => array_merge( $d, (array) $a ),
				'admin_url'               => fn( $p = '' ) => 'http://x/wp-admin/' . $p,
				'wp_get_referer'          => fn() => 'http://x/wp-admin/index.php',
				'get_current_user_id'     => fn() => 7,
				'wp_generate_uuid4'       => fn() => 'uuid-' . mt_rand(),
				'wp_nonce_field'          => '<input type="hidden" name="_wpnonce" value="n">',
			)
		);
		Functions\when( 'add_query_arg' )->alias(
			function ( $a, $b = null, $c = null ) {
				[ $args, $url ] = is_array( $a ) ? array( $a, $b ) : array( array( $a => $b ), $c );
				return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
			}
		);
		Functions\when( 'current_user_can' )->alias(
			function ( $cap, ...$args ) {
				$this->capChecks[] = array_merge( array( $cap ), $args );
				return in_array( $cap, $this->caps, true );
			}
		);
		Functions\when( 'check_admin_referer' )->alias(
			function ( $action ) {
				$this->nonces[] = $action;
				return 1;
			}
		);
		Functions\when( 'check_ajax_referer' )->alias(
			function ( $action ) {
				$this->nonces[] = $action;
				return 1;
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( $url ) {
				throw new RedirectException( $url );
			}
		);
		Functions\when( 'wp_die' )->alias(
			function ( $message = '', $title = '', $args = array() ) {
				throw new DieException( (string) $message, is_int( $args ) ? $args : (int) ( $args['response'] ?? 0 ) );
			}
		);
		Functions\when( 'wp_send_json_error' )->alias(
			function ( $data = null, $code = 0 ) {
				throw new JsonErrorException( 'json error', (int) $code );
			}
		);
		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => $d );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	protected function can( string ...$caps ): void {
		$this->caps = $caps;
	}

	/** Runs $fn and returns the URL it redirected to ('' if it never redirected). */
	protected function redirectOf( callable $fn ): string {
		try {
			$fn();
		} catch ( RedirectException $e ) {
			return $e->getMessage();
		}
		return '';
	}

	protected function assertRedirectsWith( string $done, callable $fn ): void {
		$this->assertStringContainsString( 'adminova_done=' . $done, $this->redirectOf( $fn ) );
	}

	/** A WC_Order-like mock: unspecified methods return null, so only what a test cares about is set. */
	protected function order( int $id = 1, string $type = 'shop_order', array $methods = array() ) {
		$o = Mockery::mock( 'WC_Order' )->shouldIgnoreMissing();
		$o->shouldReceive( 'get_id' )->andReturn( $id )->byDefault();
		$o->shouldReceive( 'get_type' )->andReturn( $type )->byDefault();
		foreach ( $methods as $m => $ret ) {
			$o->shouldReceive( $m )->andReturn( $ret );
		}
		return $o;
	}
}
