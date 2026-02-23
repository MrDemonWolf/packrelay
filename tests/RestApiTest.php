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
	private $mock_appcheck;
	private $mock_provider;
	private $mock_entry_store;

	protected function setUp(): void {
		parent::setUp();

		$this->stub_settings();

		$this->mock_appcheck   = \Mockery::mock( \PackRelay_AppCheck::class );
		$this->mock_provider   = \Mockery::mock( \PackRelay_Provider::class );
		$this->mock_entry_store = \Mockery::mock( \PackRelay_Entry_Store::class );

		$this->mock_provider->shouldReceive( 'get_slug' )->andReturn( 'divi' )->byDefault();

		$this->rest_api = new \PackRelay_REST_API(
			$this->mock_appcheck,
			$this->mock_provider,
			$this->mock_entry_store
		);
	}

	private function stub_settings( array $overrides = array() ): void {
		$defaults = array(
			'form_provider'       => 'divi',
			'firebase_project_id' => 'mrdemonwolf-official-app',
			'notification_email'  => 'admin@example.com',
			'allowed_form_ids'    => '123,456',
			'allowed_origins'     => '',
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
		$request->set_param( 'form_id', '999' );

		$response = $this->rest_api->handle_submit( $request );

		$this->assertSame( 404, $response->status );
		$this->assertSame( 'form_not_found', $response->data['code'] );
	}

	public function test_submit_rejects_missing_appcheck_token(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		$this->mock_provider->shouldReceive( 'get_form' )
			->with( '123' )
			->andReturn( array( 'title' => 'Test', 'fields' => array() ) );

		$this->mock_appcheck->shouldReceive( 'verify' )
			->with( null, '123' )
			->once()
			->andReturn( array(
				'success' => false,
				'code'    => 'appcheck_missing',
				'message' => 'App Check token is missing.',
			) );

		$request = new \WP_REST_Request( 'POST', '/packrelay/v1/submit/123' );
		$request->set_param( 'form_id', '123' );
		$request->set_param( 'fields', array( '1' => 'John' ) );

		$response = $this->rest_api->handle_submit( $request );

		$this->assertSame( 403, $response->status );
		$this->assertSame( 'appcheck_missing', $response->data['code'] );
	}

	public function test_submit_rejects_missing_fields(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		$this->mock_provider->shouldReceive( 'get_form' )
			->with( '123' )
			->andReturn( array( 'title' => 'Test', 'fields' => array() ) );

		$this->mock_appcheck->shouldReceive( 'verify' )
			->with( 'valid-token', '123' )
			->once()
			->andReturn( array( 'success' => true, 'app_id' => 'test-app' ) );

		$this->mock_provider->shouldReceive( 'get_field_types' )
			->with( '123' )
			->andReturn( array() );

		$request = new \WP_REST_Request( 'POST', '/packrelay/v1/submit/123' );
		$request->set_param( 'form_id', '123' );
		$request->set_param( 'app_check_token', 'valid-token' );
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
		$request->set_param( 'form_id', '999' );

		$response = $this->rest_api->handle_get_fields( $request );

		$this->assertSame( 404, $response->status );
	}

	public function test_get_fields_returns_field_structure(): void {
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		$this->mock_provider->shouldReceive( 'get_form' )
			->with( '123' )
			->andReturn(
				array(
					'title'  => 'Contact',
					'fields' => array(
						array( 'id' => '1', 'type' => 'name', 'label' => 'Name', 'required' => true ),
						array( 'id' => '2', 'type' => 'email', 'label' => 'Email', 'required' => true ),
					),
				)
			);

		$request = new \WP_REST_Request( 'GET', '/packrelay/v1/forms/123/fields' );
		$request->set_param( 'form_id', '123' );

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

	public function test_submit_supports_divi_form_id_format(): void {
		$this->stub_settings( array( 'allowed_form_ids' => '42:0' ) );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_allowed_form_ids', \Mockery::any() )
			->andReturnUsing( function ( $hook, $ids ) {
				return $ids;
			} );

		$this->mock_provider->shouldReceive( 'get_form' )
			->with( '42:0' )
			->andReturn( array( 'title' => 'Divi Form', 'fields' => array() ) );

		$this->mock_appcheck->shouldReceive( 'verify' )
			->andReturn( array( 'success' => true, 'app_id' => 'test-app' ) );

		$this->mock_provider->shouldReceive( 'get_field_types' )
			->with( '42:0' )
			->andReturn( array() );

		$this->mock_provider->shouldReceive( 'create_entry' )
			->andReturn( array( 'success' => true, 'entry_id' => 1 ) );

		$this->mock_provider->shouldReceive( 'send_notifications' );

		Functions\expect( 'do_action' )
			->with( 'packrelay_entry_created', \Mockery::any(), \Mockery::any(), \Mockery::any(), \Mockery::any() );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_rest_response', \Mockery::any(), \Mockery::any(), \Mockery::any() )
			->andReturnUsing( function ( $hook, $response ) {
				return $response;
			} );

		$request = new \WP_REST_Request( 'POST', '/packrelay/v1/submit/42:0' );
		$request->set_param( 'form_id', '42:0' );
		$request->set_param( 'app_check_token', 'valid-token' );
		$request->set_param( 'fields', array( '0' => 'John' ) );

		$response = $this->rest_api->handle_submit( $request );

		$this->assertSame( 200, $response->status );
		$this->assertTrue( $response->data['success'] );
	}
}
