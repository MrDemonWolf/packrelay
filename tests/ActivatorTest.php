<?php
/**
 * Tests for PackRelay_Activator.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class ActivatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'get_charset_collate' )->andReturn( '' )->byDefault();
	}

	public function test_activate_sets_default_options_when_none_exist(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return false;
				}
				if ( 'admin_email' === $key ) {
					return 'admin@example.com';
				}
				return $default;
			}
		);

		$updated = false;
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) use ( &$updated ) {
				if ( 'packrelay_settings' === $key && is_array( $value ) ) {
					$updated = true;
				}
			}
		);

		Functions\when( 'set_transient' )->justReturn( true );

		// Stub dbDelta and require_once for create_table.
		Functions\when( 'dbDelta' )->justReturn( array() );

		\PackRelay_Activator::activate();

		$this->assertTrue( $updated, 'update_option should be called with packrelay_settings' );
	}

	public function test_activate_does_not_overwrite_existing_options(): void {
		$existing = array(
			'form_provider'       => 'wpforms',
			'firebase_project_id' => 'existing-project',
			'notification_email'  => 'test@example.com',
			'allowed_form_ids'    => '1,2,3',
			'allowed_origins'     => '',
		);

		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) use ( $existing ) {
				if ( 'packrelay_settings' === $key ) {
					return $existing;
				}
				return $default;
			}
		);

		$updated = false;
		Functions\when( 'update_option' )->alias(
			function () use ( &$updated ) {
				$updated = true;
			}
		);

		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'dbDelta' )->justReturn( array() );

		\PackRelay_Activator::activate();

		$this->assertFalse( $updated, 'update_option should not be called when settings exist' );
	}

	public function test_activate_defaults_include_form_provider(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return false;
				}
				if ( 'admin_email' === $key ) {
					return 'admin@example.com';
				}
				return $default;
			}
		);

		$saved_defaults = null;
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) use ( &$saved_defaults ) {
				if ( 'packrelay_settings' === $key ) {
					$saved_defaults = $value;
				}
			}
		);

		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'dbDelta' )->justReturn( array() );

		\PackRelay_Activator::activate();

		$this->assertArrayHasKey( 'form_provider', $saved_defaults );
		$this->assertSame( 'divi', $saved_defaults['form_provider'] );
	}

	public function test_is_provider_available_returns_false_when_not_installed(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return array( 'form_provider' => 'divi' );
				}
				return $default;
			}
		);

		$this->assertFalse( \PackRelay_Activator::is_provider_available() );
	}
}
