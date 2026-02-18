<?php
/**
 * Tests for PackRelay_ReCaptcha.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class ReCaptchaTest extends TestCase {

	private \PackRelay_ReCaptcha $recaptcha;

	protected function setUp(): void {
		parent::setUp();
		$this->recaptcha = new \PackRelay_ReCaptcha();
	}

	private function stub_settings( array $overrides = array() ): void {
		$defaults = array(
			'recaptcha_site_key'   => 'test-site-key',
			'recaptcha_secret_key' => 'test-secret-key',
			'recaptcha_threshold'  => 0.5,
			'notification_email'   => '',
			'allowed_form_ids'     => '',
			'allowed_origins'      => '',
		);

		$settings = array_merge( $defaults, $overrides );

		Functions\expect( 'get_option' )
			->with( 'packrelay_settings', \Mockery::any() )
			->andReturn( $settings );
	}

	public function test_empty_token_returns_failure(): void {
		$result = $this->recaptcha->verify( '', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'recaptcha_failed', $result['code'] );
	}

	public function test_missing_secret_key_returns_failure(): void {
		$this->stub_settings( array( 'recaptcha_secret_key' => '' ) );

		$result = $this->recaptcha->verify( 'some-token', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'recaptcha_failed', $result['code'] );
	}

	public function test_successful_verification(): void {
		$this->stub_settings();

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( array( 'body' => '{"success":true,"score":0.9}' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( '{"success":true,"score":0.9}' );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_recaptcha_threshold', \Mockery::any(), 123 )
			->andReturnUsing( function ( $hook, $value ) {
				return $value;
			} );

		$result = $this->recaptcha->verify( 'valid-token', 123, '127.0.0.1' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 0.9, $result['score'] );
	}

	public function test_failed_verification_from_google(): void {
		$this->stub_settings();

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( array( 'body' => '{"success":false}' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( '{"success":false}' );

		$result = $this->recaptcha->verify( 'invalid-token', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'recaptcha_failed', $result['code'] );
	}

	public function test_low_score_returns_failure(): void {
		$this->stub_settings( array( 'recaptcha_threshold' => 0.5 ) );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( array( 'body' => '{"success":true,"score":0.2}' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( '{"success":true,"score":0.2}' );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_recaptcha_threshold', \Mockery::any(), 123 )
			->andReturnUsing( function ( $hook, $value ) {
				return $value;
			} );

		$result = $this->recaptcha->verify( 'bot-token', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'recaptcha_low_score', $result['code'] );
		$this->assertSame( 0.2, $result['score'] );
	}

	public function test_network_error_returns_failure(): void {
		$this->stub_settings();

		$wp_error = new \WP_Error( 'http_request_failed', 'Connection timed out' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( $wp_error );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( true );

		$result = $this->recaptcha->verify( 'some-token', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'recaptcha_failed', $result['code'] );
	}

	public function test_threshold_filter_is_applied(): void {
		$this->stub_settings( array( 'recaptcha_threshold' => 0.5 ) );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( array( 'body' => '{"success":true,"score":0.6}' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( '{"success":true,"score":0.6}' );

		// Filter raises threshold above the score.
		Functions\expect( 'apply_filters' )
			->with( 'packrelay_recaptcha_threshold', \Mockery::any(), 456 )
			->andReturn( 0.8 );

		$result = $this->recaptcha->verify( 'some-token', 456 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'recaptcha_low_score', $result['code'] );
	}
}
