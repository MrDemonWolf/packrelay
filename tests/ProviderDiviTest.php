<?php
/**
 * Tests for PackRelay_Provider_Divi.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class ProviderDiviTest extends TestCase {

	private \PackRelay_Provider_Divi $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->provider = new \PackRelay_Provider_Divi();
	}

	public function test_get_slug(): void {
		$this->assertSame( 'divi', $this->provider->get_slug() );
	}

	public function test_get_label(): void {
		$this->assertSame( 'Divi', $this->provider->get_label() );
	}

	public function test_is_available_returns_false_without_divi(): void {
		$this->assertFalse( $this->provider->is_available() );
	}

	public function test_get_form_parses_shortcode(): void {
		$content = '[et_pb_contact_form title="Contact Us" email="test@example.com"]'
			. '[et_pb_contact_field field_title="Name" field_type="input" required_mark="on" /]'
			. '[et_pb_contact_field field_title="Email" field_type="email" /]'
			. '[et_pb_contact_field field_title="Message" field_type="text" required_mark="off" /]'
			. '[/et_pb_contact_form]';

		$post = (object) array(
			'ID'           => 42,
			'post_content' => $content,
		);

		Functions\expect( 'get_post' )
			->with( 42 )
			->andReturn( $post );

		$form = $this->provider->get_form( '42:0' );

		$this->assertIsArray( $form );
		$this->assertSame( 'Contact Us', $form['title'] );
		$this->assertSame( 'test@example.com', $form['email'] );
		$this->assertCount( 3, $form['fields'] );
		$this->assertSame( 'text', $form['fields'][0]['type'] );
		$this->assertSame( 'email', $form['fields'][1]['type'] );
		$this->assertSame( 'textarea', $form['fields'][2]['type'] );
		$this->assertTrue( $form['fields'][0]['required'] );
		$this->assertFalse( $form['fields'][2]['required'] );
	}

	public function test_get_form_returns_false_for_invalid_id(): void {
		$form = $this->provider->get_form( 'invalid' );
		$this->assertFalse( $form );
	}

	public function test_get_form_returns_false_when_post_not_found(): void {
		Functions\expect( 'get_post' )
			->with( 99 )
			->andReturn( null );

		$form = $this->provider->get_form( '99:0' );
		$this->assertFalse( $form );
	}

	public function test_get_form_selects_correct_form_by_index(): void {
		$content = '[et_pb_contact_form title="First Form" email="first@example.com"]'
			. '[et_pb_contact_field field_title="Name" field_type="input" /]'
			. '[/et_pb_contact_form]'
			. '[et_pb_contact_form title="Second Form" email="second@example.com"]'
			. '[et_pb_contact_field field_title="Phone" field_type="input" /]'
			. '[/et_pb_contact_form]';

		$post = (object) array(
			'ID'           => 42,
			'post_content' => $content,
		);

		Functions\expect( 'get_post' )
			->with( 42 )
			->andReturn( $post );

		$form = $this->provider->get_form( '42:1' );

		$this->assertSame( 'Second Form', $form['title'] );
		$this->assertSame( 'second@example.com', $form['email'] );
	}

	public function test_get_field_types_returns_mapping(): void {
		$content = '[et_pb_contact_form title="Test"]'
			. '[et_pb_contact_field field_title="Name" field_type="input" /]'
			. '[et_pb_contact_field field_title="Email" field_type="email" /]'
			. '[/et_pb_contact_form]';

		$post = (object) array(
			'ID'           => 42,
			'post_content' => $content,
		);

		Functions\expect( 'get_post' )
			->with( 42 )
			->andReturn( $post );

		$types = $this->provider->get_field_types( '42:0' );

		$this->assertSame( 'text', $types['0'] );
		$this->assertSame( 'email', $types['1'] );
	}

	public function test_create_entry_delegates_to_store(): void {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 5;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), '42:0', \Mockery::any() )
			->andReturnUsing( function ( $hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$request->set_header( 'User-Agent', 'TestAgent/1.0' );

		$result = $this->provider->create_entry(
			'42:0',
			array( '0' => 'John', '1' => 'john@example.com' ),
			$request
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 5, $result['entry_id'] );
	}

	public function test_send_notifications_calls_wp_mail(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return array( 'notification_email' => '' );
				}
				if ( 'admin_email' === $key ) {
					return 'fallback@example.com';
				}
				return $default;
			}
		);

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_notification_args', \Mockery::any(), 5, '42:0' )
			->andReturnUsing( function ( $hook, $args ) {
				return $args;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'test@example.com',
				\Mockery::on( function ( $subject ) {
					return str_contains( $subject, 'PackRelay' );
				} ),
				\Mockery::type( 'string' ),
				\Mockery::type( 'array' )
			);

		$form_data = array(
			'title' => 'Contact',
			'email' => 'test@example.com',
		);

		$this->provider->send_notifications(
			'42:0',
			5,
			array( '0' => 'John', '1' => 'john@example.com' ),
			$form_data
		);
	}

	public function test_send_notifications_falls_back_to_admin_email(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return array( 'notification_email' => 'settings@example.com' );
				}
				return $default;
			}
		);

		Functions\expect( 'apply_filters' )
			->andReturnUsing( function ( $hook, $args ) {
				return $args;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'settings@example.com',
				\Mockery::any(),
				\Mockery::any(),
				\Mockery::any()
			);

		$form_data = array(
			'title' => 'Contact',
			'email' => '',
		);

		$this->provider->send_notifications( '42:0', 5, array( '0' => 'Test' ), $form_data );
	}
}
