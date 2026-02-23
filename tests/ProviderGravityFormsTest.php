<?php
/**
 * Tests for PackRelay_Provider_GravityForms.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class ProviderGravityFormsTest extends TestCase {

	private \PackRelay_Provider_GravityForms $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->provider = new \PackRelay_Provider_GravityForms();
	}

	public function test_get_slug(): void {
		$this->assertSame( 'gravityforms', $this->provider->get_slug() );
	}

	public function test_get_label(): void {
		$this->assertSame( 'Gravity Forms', $this->provider->get_label() );
	}

	public function test_is_available_returns_false_without_gf(): void {
		$this->assertFalse( $this->provider->is_available() );
	}

	public function test_get_form_returns_false_without_gfapi(): void {
		$form = $this->provider->get_form( '1' );
		$this->assertFalse( $form );
	}

	public function test_create_entry_fails_without_gfapi(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), '1', \Mockery::any() )
			->andReturnUsing( function ( $_hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$result  = $this->provider->create_entry( '1', array( '1' => 'John' ), $request );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'entry_failed', $result['code'] );
	}

	public function test_send_notifications_is_noop(): void {
		// Should not throw any errors — it's a no-op.
		$this->provider->send_notifications( '1', 1, array(), array() );
		$this->assertTrue( true );
	}

	public function test_get_field_types_returns_empty_without_gfapi(): void {
		$types = $this->provider->get_field_types( '1' );
		$this->assertSame( array(), $types );
	}
}
