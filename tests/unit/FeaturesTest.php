<?php
use PHPUnit\Framework\TestCase;

final class FeaturesTest extends TestCase {
	public function test_unsaved_features_use_their_defaults(): void {
		$this->assertTrue(Adminova_Features::resolve([], 'dashboard'));
		$this->assertFalse(Adminova_Features::resolve([], 'notify'));
		$this->assertFalse(Adminova_Features::resolve(false, 'profit'));
	}

	public function test_saved_value_wins_over_default(): void {
		$this->assertFalse(Adminova_Features::resolve(['dashboard' => false], 'dashboard'));
		$this->assertTrue(Adminova_Features::resolve(['notify' => true], 'notify'));
	}

	public function test_unknown_feature_is_off(): void {
		$this->assertFalse(Adminova_Features::resolve([], 'nope'));
	}

	public function test_unchecked_boxes_are_saved_as_off(): void {
		$out = Adminova_Features::sanitize(['dashboard' => '1']);
		$this->assertTrue($out['dashboard']);
		$this->assertFalse($out['print']);
		$this->assertSame(array_keys(Adminova_Features::DEFAULTS), array_keys($out));
	}

	public function test_unknown_keys_are_dropped(): void {
		$this->assertArrayNotHasKey('evil', Adminova_Features::sanitize(['evil' => '1']));
	}

	public function test_non_array_input_falls_back_to_defaults(): void {
		$this->assertSame(Adminova_Features::DEFAULTS, Adminova_Features::sanitize('x'));
	}

	public function test_every_hooked_feature_has_a_default_and_a_class_with_hooks(): void {
		foreach (Adminova_Features::HOOKED as $slug => $class) {
			$this->assertArrayHasKey($slug, Adminova_Features::DEFAULTS);
			$this->assertTrue(method_exists($class, 'hooks'), "$class::hooks()");
		}
	}
}
