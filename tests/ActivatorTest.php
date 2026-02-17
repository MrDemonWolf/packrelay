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

		\PackRelay_Activator::activate();

		$this->assertTrue( $updated, 'update_option should be called with packrelay_settings' );
	}

	public function test_activate_does_not_overwrite_existing_options(): void {
		$existing = array(
			'recaptcha_site_key'   => 'existing-key',
			'recaptcha_secret_key' => 'existing-secret',
			'recaptcha_threshold'  => 0.7,
			'notification_email'   => 'test@example.com',
			'allowed_form_ids'     => '1,2,3',
			'allowed_origins'      => '',
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

		\PackRelay_Activator::activate();

		$this->assertFalse( $updated, 'update_option should not be called when settings exist' );
	}

	public function test_is_wpforms_active_returns_false_when_not_installed(): void {
		$this->assertFalse( \PackRelay_Activator::is_wpforms_active() );
	}
}
