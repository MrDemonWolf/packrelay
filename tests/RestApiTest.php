<?php
/**
 * Tests for PackRelay_REST_API.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class RestApiTest extends TestCase {

	private \PackRelay_REST_API $rest_api;

	protected function setUp(): void {
		parent::setUp();

		$this->stub_settings();
		$this->rest_api = new \PackRelay_REST_API();
	}

	private function stub_settings( array $overrides = array() ): void {
		$defaults = array(
			'recaptcha_site_key'   => 'test-site-key',
			'recaptcha_secret_key' => 'test-secret-key',
			'recaptcha_threshold'  => 0.5,
			'notification_email'   => 'admin@example.com',
			'allowed_form_ids'     => '123,456',
			'allowed_origins'      => '',
		);

		$settings = array_merge( $defaults, $overrides );

		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) use ( $settings ) {
				if ( 'packrelay_settings' === $key ) {
					return $settings;
				}
				if ( 'admin_email' === $key ) {
					return 'admin@example.com';
				}
				return $default;
			}
		);
	}

	public function test_register_routes(): void {
		Functions\expect( 'register_rest_route' )
			->twice();

		$this->rest_api->register_routes();
	}

	public function test_submit_rejects_unallowed_form(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		$request = new \WP_REST_Request( 'POST', '/packrelay/v1/submit/999' );
		$request->set_param( 'form_id', 999 );

		$response = $this->rest_api->handle_submit( $request );

		$this->assertSame( 404, $response->status );
		$this->assertSame( 'form_not_found', $response->data['code'] );
	}

	public function test_submit_rejects_missing_recaptcha(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		// Stub wpforms form lookup.
		$form_mock       = new \stdClass();
		$form_mock->post_content = '{"settings":{"form_title":"Test"},"fields":{}}';

		$form_handler = \Mockery::mock();
		$form_handler->shouldReceive( 'get' )->with( 123 )->andReturn( $form_mock );

		$wpforms_mock       = new \stdClass();
		$wpforms_mock->form = $form_handler;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'wpforms_decode' )
			->andReturn( array( 'settings' => array( 'form_title' => 'Test' ), 'fields' => array() ) );

		$request = new \WP_REST_Request( 'POST', '/packrelay/v1/submit/123' );
		$request->set_param( 'form_id', 123 );
		$request->set_param( 'fields', array( '1' => 'John' ) );
		// No recaptcha_token set.

		$response = $this->rest_api->handle_submit( $request );

		$this->assertSame( 403, $response->status );
		$this->assertSame( 'recaptcha_failed', $response->data['code'] );
	}

	public function test_submit_rejects_missing_fields(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		// Stub form.
		$form_mock               = new \stdClass();
		$form_mock->post_content = '{"settings":{"form_title":"Test"},"fields":{}}';

		$form_handler = \Mockery::mock();
		$form_handler->shouldReceive( 'get' )->with( 123 )->andReturn( $form_mock );

		$wpforms_mock       = new \stdClass();
		$wpforms_mock->form = $form_handler;

		Functions\expect( 'wpforms' )->andReturn( $wpforms_mock );
		Functions\expect( 'wpforms_decode' )
			->andReturn( array( 'settings' => array( 'form_title' => 'Test' ), 'fields' => array() ) );

		// Stub recaptcha to pass.
		Functions\expect( 'wp_remote_post' )->andReturn( array( 'body' => '{"success":true,"score":0.9}' ) );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->andReturn( '{"success":true,"score":0.9}' );
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_recaptcha_threshold', \Mockery::any(), \Mockery::any() )
			->andReturnUsing( function ( $hook, $val ) {
				return $val;
			} );

		$request = new \WP_REST_Request( 'POST', '/packrelay/v1/submit/123' );
		$request->set_param( 'form_id', 123 );
		$request->set_param( 'recaptcha_token', 'valid-token' );
		// No fields set.

		$response = $this->rest_api->handle_submit( $request );

		$this->assertSame( 400, $response->status );
		$this->assertSame( 'missing_fields', $response->data['code'] );
	}

	public function test_handle_options_returns_200(): void {
		$response = $this->rest_api->handle_options();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->status );
	}

	public function test_get_fields_rejects_unallowed_form(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		$request = new \WP_REST_Request( 'GET', '/packrelay/v1/forms/999/fields' );
		$request->set_param( 'form_id', 999 );

		$response = $this->rest_api->handle_get_fields( $request );

		$this->assertSame( 404, $response->status );
	}

	public function test_get_fields_returns_field_structure(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		$form_mock               = new \stdClass();
		$form_mock->post_content = '';

		$form_handler = \Mockery::mock();
		$form_handler->shouldReceive( 'get' )->with( 123 )->andReturn( $form_mock );

		$wpforms_mock       = new \stdClass();
		$wpforms_mock->form = $form_handler;

		Functions\expect( 'wpforms' )->andReturn( $wpforms_mock );
		Functions\expect( 'wpforms_decode' )->andReturn(
			array(
				'settings' => array( 'form_title' => 'Contact' ),
				'fields'   => array(
					array( 'id' => 1, 'type' => 'name', 'label' => 'Name', 'required' => '1' ),
					array( 'id' => 2, 'type' => 'email', 'label' => 'Email', 'required' => '1' ),
				),
			)
		);

		$request = new \WP_REST_Request( 'GET', '/packrelay/v1/forms/123/fields' );
		$request->set_param( 'form_id', 123 );

		$response = $this->rest_api->handle_get_fields( $request );

		$this->assertSame( 200, $response->status );
		$data = $response->data;
		$this->assertTrue( $data['success'] );
		$this->assertSame( 'Contact', $data['form_title'] );
		$this->assertCount( 2, $data['fields'] );
		$this->assertSame( 'name', $data['fields'][0]['type'] );
	}

	public function test_cors_headers_only_for_packrelay_routes(): void {
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$result  = new \WP_HTTP_Response();
		$server  = new \WP_REST_Server();

		$served = $this->rest_api->add_cors_headers( false, $result, $request, $server );

		$this->assertFalse( $served );
	}
}
