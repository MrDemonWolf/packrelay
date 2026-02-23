<?php
/**
 * Tests for PackRelay_AppCheck.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;
use Kreait\Firebase\Contract\AppCheck;
use Kreait\Firebase\AppCheck\VerifyAppCheckTokenResponse;
use Kreait\Firebase\AppCheck\DecodedAppCheckToken;

class AppCheckTest extends TestCase {

	private function stub_settings( array $overrides = array() ): void {
		$defaults = array(
			'firebase_project_id' => 'mrdemonwolf-official-app',
			'notification_email'  => '',
			'allowed_form_ids'    => '',
			'allowed_origins'     => '',
		);

		$settings = array_merge( $defaults, $overrides );

		Functions\expect( 'get_option' )
			->with( 'packrelay_settings', \Mockery::any() )
			->andReturn( $settings );
	}

	/**
	 * Create a fake factory that returns the given AppCheck service mock.
	 *
	 * Since Kreait\Firebase\Factory is final, we use an anonymous class
	 * that implements the same interface our code calls.
	 */
	private function make_fake_factory( $appcheck_service ) {
		return new class( $appcheck_service ) {
			private $appcheck_service;

			public function __construct( $appcheck_service ) {
				$this->appcheck_service = $appcheck_service;
			}

			public function withProjectId( string $project_id ): self {
				return $this;
			}

			public function createAppCheck() {
				return $this->appcheck_service;
			}
		};
	}

	public function test_empty_token_returns_failure(): void {
		$appcheck = new \PackRelay_AppCheck();

		$result = $appcheck->verify( '', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'appcheck_missing', $result['code'] );
	}

	public function test_missing_project_id_returns_failure(): void {
		$this->stub_settings( array( 'firebase_project_id' => '' ) );

		$appcheck = new \PackRelay_AppCheck();
		$result   = $appcheck->verify( 'some-token', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'appcheck_failed', $result['code'] );
	}

	public function test_successful_verification(): void {
		$this->stub_settings();

		$decoded_token = DecodedAppCheckToken::fromArray( array(
			'app_id' => '1:1234567890:android:abcdef',
			'aud'    => array( 'projects/mrdemonwolf-official-app' ),
			'exp'    => time() + 3600,
			'iat'    => time(),
			'iss'    => 'https://firebaseappcheck.googleapis.com/mrdemonwolf-official-app',
			'sub'    => '1:1234567890:android:abcdef',
		) );

		$verified_response = new VerifyAppCheckTokenResponse(
			'1:1234567890:android:abcdef',
			$decoded_token
		);

		$appcheck_service = \Mockery::mock( AppCheck::class );
		$appcheck_service->shouldReceive( 'verifyToken' )
			->with( 'valid-token' )
			->once()
			->andReturn( $verified_response );

		$factory = $this->make_fake_factory( $appcheck_service );

		Functions\expect( 'do_action' )
			->with( 'packrelay_appcheck_verified', '1:1234567890:android:abcdef', 123, 'valid-token' )
			->once();

		$appcheck = new \PackRelay_AppCheck( $factory );
		$result   = $appcheck->verify( 'valid-token', 123 );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '1:1234567890:android:abcdef', $result['app_id'] );
	}

	public function test_invalid_token_returns_failure(): void {
		$this->stub_settings();

		$appcheck_service = \Mockery::mock( AppCheck::class );
		$appcheck_service->shouldReceive( 'verifyToken' )
			->with( 'invalid-token' )
			->once()
			->andThrow( new \RuntimeException( 'Token verification failed' ) );

		$factory = $this->make_fake_factory( $appcheck_service );

		$appcheck = new \PackRelay_AppCheck( $factory );
		$result   = $appcheck->verify( 'invalid-token', 123 );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'appcheck_failed', $result['code'] );
	}
}
