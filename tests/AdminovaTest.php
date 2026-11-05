<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AdminovaTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['adminova_hooks'] = [];
	}

	public function test_init_registers_assets_hook_by_default(): void {
		Adminova::init();
		$this->assertNotEmpty($GLOBALS['adminova_hooks']['admin_enqueue_scripts']);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_init_registers_nothing_when_disabled(): void {
		define('ADMINOVA_DISABLE', true);
		Adminova::init();
		$this->assertSame([], $GLOBALS['adminova_hooks']);
	}

	public static function themes(): array {
		return [
			'dark'   => ['dark', 'dark'],
			'light'  => ['light', 'light'],
			'script' => ['<script>', ''],
			'array'  => [['dark'], ''],
			'empty'  => ['', ''],
		];
	}

	#[DataProvider('themes')]
	public function test_sanitize_theme(mixed $input, string $expected): void {
		$this->assertSame($expected, Adminova::sanitize_theme($input));
	}

	public static function accents(): array {
		return [
			'valid, lowercased' => ['#AABBCC', '#aabbcc'],
			'css injection'     => ['#fff;}body{display:none', ''],
			'short hex'         => ['#fff', ''],
			'no hash'           => ['aabbcc', ''],
			'not a string'      => [123456, ''],
		];
	}

	#[DataProvider('accents')]
	public function test_sanitize_accent(mixed $input, string $expected): void {
		$this->assertSame($expected, Adminova::sanitize_accent($input));
	}

	public function test_dark_is_the_default_and_light_is_opt_in(): void {
		$this->assertTrue(Adminova::is_dark(''));
		$this->assertTrue(Adminova::is_dark('dark'));
		$this->assertFalse(Adminova::is_dark('light'));
	}
}
