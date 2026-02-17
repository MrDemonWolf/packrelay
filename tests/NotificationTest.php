<?php
/**
 * Tests for PackRelay_Notification.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class NotificationTest extends TestCase {

	private \PackRelay_Notification $notification;

	protected function setUp(): void {
		parent::setUp();
		$this->notification = new \PackRelay_Notification();
	}

	private function stub_settings( array $overrides = array() ): void {
		$defaults = array(
			'recaptcha_site_key'   => '',
			'recaptcha_secret_key' => '',
			'recaptcha_threshold'  => 0.5,
			'notification_email'   => 'admin@example.com',
			'allowed_form_ids'     => '',
			'allowed_origins'      => '',
		);

		$settings = array_merge( $defaults, $overrides );

		Functions\expect( 'get_option' )
			->with( 'packrelay_settings', \Mockery::any() )
			->andReturn( $settings );
	}

	public function test_send_sends_email(): void {
		$this->stub_settings();

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_notification_args', \Mockery::any(), 42, 123 )
			->andReturnUsing( function ( $hook, $args ) {
				return $args;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'admin@example.com',
				\Mockery::on( function ( $subject ) {
					return str_contains( $subject, 'PackRelay' );
				} ),
				\Mockery::type( 'string' ),
				\Mockery::type( 'array' )
			)
			->andReturn( true );

		$result = $this->notification->send(
			42,
			123,
			array( '1' => 'John', '2' => 'Doe' ),
			'Contact Form'
		);

		$this->assertTrue( $result );
	}

	public function test_send_adds_reply_to_with_email_field(): void {
		$this->stub_settings();

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_notification_args', \Mockery::any(), 42, 123 )
			->andReturnUsing( function ( $hook, $args ) {
				return $args;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'admin@example.com',
				\Mockery::any(),
				\Mockery::any(),
				\Mockery::on( function ( $headers ) {
					$header_str = implode( "\n", $headers );
					return str_contains( $header_str, 'Reply-To: john@example.com' );
				} )
			)
			->andReturn( true );

		$this->notification->send(
			42,
			123,
			array( '1' => 'John', '3' => 'john@example.com' ),
			'Contact Form'
		);
	}

	public function test_send_falls_back_to_admin_email(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return array( 'notification_email' => '' );
				}
				if ( 'admin_email' === $key ) {
					return 'wp-admin@example.com';
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
			->with( 'wp-admin@example.com', \Mockery::any(), \Mockery::any(), \Mockery::any() )
			->andReturn( true );

		$this->notification->send( 1, 1, array( '1' => 'Test' ) );
	}

	public function test_send_returns_false_when_no_email(): void {
		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				if ( 'packrelay_settings' === $key ) {
					return array( 'notification_email' => '' );
				}
				if ( 'admin_email' === $key ) {
					return '';
				}
				return $default;
			}
		);

		$result = $this->notification->send( 1, 1, array( '1' => 'Test' ) );

		$this->assertFalse( $result );
	}

	public function test_notification_args_filter_applied(): void {
		$this->stub_settings();

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_notification_args', \Mockery::any(), 42, 123 )
			->andReturnUsing( function ( $hook, $args ) {
				$args['subject'] = 'Custom Subject';
				return $args;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->with( \Mockery::any(), 'Custom Subject', \Mockery::any(), \Mockery::any() )
			->andReturn( true );

		$this->notification->send( 42, 123, array( '1' => 'Test' ), 'Form' );
	}

	public function test_send_returns_false_on_wp_mail_failure(): void {
		$this->stub_settings();

		Functions\expect( 'apply_filters' )
			->andReturnUsing( function ( $hook, $args ) {
				return $args;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->andReturn( false );

		$result = $this->notification->send( 1, 1, array( '1' => 'Test' ), 'Form' );

		$this->assertFalse( $result );
	}
}
