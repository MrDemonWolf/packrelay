<?php
/**
 * Tests for PackRelay_Provider_WPForms.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class ProviderWPFormsTest extends TestCase {

	private \PackRelay_Provider_WPForms $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->provider = new \PackRelay_Provider_WPForms();
	}

	public function test_get_slug(): void {
		$this->assertSame( 'wpforms', $this->provider->get_slug() );
	}

	public function test_get_label(): void {
		$this->assertSame( 'WPForms', $this->provider->get_label() );
	}

	public function test_is_available_returns_false_without_wpforms(): void {
		$this->assertFalse( $this->provider->is_available() );
	}

	public function test_get_form_returns_normalized_data(): void {
		$form_mock               = new \stdClass();
		$form_mock->post_content = '{"settings":{"form_title":"Test"},"fields":{}}';

		$form_handler = \Mockery::mock();
		$form_handler->shouldReceive( 'get' )->with( 123 )->andReturn( $form_mock );

		$wpforms_mock       = new \stdClass();
		$wpforms_mock->form = $form_handler;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'wpforms_decode' )
			->andReturn( array(
				'settings' => array( 'form_title' => 'Contact' ),
				'fields'   => array(
					array( 'id' => 1, 'type' => 'name', 'label' => 'Name', 'required' => '1' ),
					array( 'id' => 2, 'type' => 'email', 'label' => 'Email', 'required' => '1' ),
				),
			) );

		$form = $this->provider->get_form( '123' );

		$this->assertIsArray( $form );
		$this->assertSame( 'Contact', $form['title'] );
		$this->assertCount( 2, $form['fields'] );
		$this->assertSame( '1', $form['fields'][0]['id'] );
		$this->assertSame( 'name', $form['fields'][0]['type'] );
	}

	public function test_create_entry_succeeds(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->andReturn( 42 );

		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), '123', \Mockery::any() )
			->andReturnUsing( function ( $_hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$request->set_header( 'User-Agent', 'TestAgent/1.0' );

		$result = $this->provider->create_entry(
			'123',
			array( '1' => 'John', '2' => 'john@example.com' ),
			$request
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 42, $result['entry_id'] );
	}

	public function test_create_entry_fails_without_wpforms(): void {
		// wpforms() returns object with null entry handler.
		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = null;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		$request = new \WP_REST_Request();
		$result  = $this->provider->create_entry( '123', array( '1' => 'John' ), $request );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'entry_failed', $result['code'] );
	}

	public function test_create_entry_returns_failure_when_add_fails(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->andReturn( false );

		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), '123', \Mockery::any() )
			->andReturnUsing( function ( $_hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$result  = $this->provider->create_entry( '123', array( '1' => 'John' ), $request );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'entry_failed', $result['code'] );
	}

	public function test_fields_are_sanitized(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->with(
				\Mockery::on(
					function ( $data ) {
						$fields = json_decode( $data['fields'], true );
						return 'Clean' === $fields[1];
					}
				)
			)
			->andReturn( 1 );

		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), '123', \Mockery::any() )
			->andReturnUsing( function ( $_hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$result  = $this->provider->create_entry( '123', array( '1' => '<script>Clean</script>' ), $request );

		$this->assertTrue( $result['success'] );
	}

	public function test_pre_save_fields_filter_applied(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->andReturn( 10 );

		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), '123', \Mockery::any() )
			->andReturn( array( '1' => 'Modified' ) );

		$request = new \WP_REST_Request();
		$result  = $this->provider->create_entry( '123', array( '1' => 'Original' ), $request );

		$this->assertTrue( $result['success'] );
	}
}
