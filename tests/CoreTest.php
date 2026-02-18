<?php
/**
 * Tests for PackRelay core class.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class CoreTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Reset the singleton.
		$reflection = new \ReflectionClass( \PackRelay::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setValue( null, null );

		// Stub functions used during construction.
		Functions\stubs(
			array(
				'get_option' => function ( $key, $default = false ) {
					if ( 'packrelay_settings' === $key ) {
						return array(
							'recaptcha_site_key'   => '',
							'recaptcha_secret_key' => '',
							'recaptcha_threshold'  => 0.5,
							'notification_email'   => '',
							'allowed_form_ids'     => '',
							'allowed_origins'      => '',
						);
					}
					return $default;
				},
			)
		);
	}

	public function test_get_instance_returns_singleton(): void {
		$instance1 = \PackRelay::get_instance();
		$instance2 = \PackRelay::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	public function test_get_instance_returns_packrelay(): void {
		$instance = \PackRelay::get_instance();

		$this->assertInstanceOf( \PackRelay::class, $instance );
	}

	public function test_get_loader_returns_loader(): void {
		$instance = \PackRelay::get_instance();

		$this->assertInstanceOf( \PackRelay_Loader::class, $instance->get_loader() );
	}

	public function test_hooks_are_registered(): void {
		$instance = \PackRelay::get_instance();
		$loader   = $instance->get_loader();
		$actions  = $loader->get_actions();

		$hook_names = array_column( $actions, 'hook' );
		$this->assertContains( 'admin_menu', $hook_names );
		$this->assertContains( 'admin_init', $hook_names );
		$this->assertContains( 'rest_api_init', $hook_names );
		$this->assertContains( 'admin_notices', $hook_names );
	}

	public function test_cors_filter_registered(): void {
		$instance = \PackRelay::get_instance();
		$loader   = $instance->get_loader();
		$filters  = $loader->get_filters();

		$hook_names = array_column( $filters, 'hook' );
		$this->assertContains( 'rest_pre_serve_request', $hook_names );
	}
}
