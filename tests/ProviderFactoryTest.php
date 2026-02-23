<?php
/**
 * Tests for PackRelay_Provider_Factory.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class ProviderFactoryTest extends TestCase {

	public function test_create_defaults_to_divi(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return array( 'form_provider' => 'divi' );
				}
				return $default;
			}
		);

		$provider = \PackRelay_Provider_Factory::create();

		$this->assertInstanceOf( \PackRelay_Provider_Divi::class, $provider );
	}

	public function test_create_respects_settings(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return array( 'form_provider' => 'wpforms' );
				}
				return $default;
			}
		);

		$provider = \PackRelay_Provider_Factory::create();

		$this->assertInstanceOf( \PackRelay_Provider_WPForms::class, $provider );
	}

	public function test_create_with_explicit_slug(): void {
		$provider = \PackRelay_Provider_Factory::create( 'gravityforms' );

		$this->assertInstanceOf( \PackRelay_Provider_GravityForms::class, $provider );
	}

	public function test_create_falls_back_to_divi_for_unknown_slug(): void {
		$provider = \PackRelay_Provider_Factory::create( 'unknown' );

		$this->assertInstanceOf( \PackRelay_Provider_Divi::class, $provider );
	}

	public function test_get_available_providers_returns_all(): void {
		$providers = \PackRelay_Provider_Factory::get_available_providers();

		$this->assertArrayHasKey( 'divi', $providers );
		$this->assertArrayHasKey( 'wpforms', $providers );
		$this->assertArrayHasKey( 'gravityforms', $providers );

		$this->assertSame( 'Divi', $providers['divi']['label'] );
		$this->assertSame( 'WPForms', $providers['wpforms']['label'] );
		$this->assertSame( 'Gravity Forms', $providers['gravityforms']['label'] );
	}
}
