<?php
/**
 * Firebase App Check server-side verification.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_AppCheck
 *
 * Verifies Firebase App Check tokens using kreait/firebase-php.
 */
class PackRelay_AppCheck {

	/**
	 * Firebase Factory instance.
	 *
	 * @var \Kreait\Firebase\Factory
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @param \Kreait\Firebase\Factory|null $factory Optional factory for DI/testing.
	 */
	public function __construct( $factory = null ) {
		$this->factory = $factory ?: new \Kreait\Firebase\Factory();
	}

	/**
	 * Verify a Firebase App Check token.
	 *
	 * @param string $token   The App Check token from the client.
	 * @param int    $form_id The form ID.
	 * @return array{ success: bool, code?: string, message?: string, app_id?: string }
	 */
	public function verify( $token, $form_id ) {
		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'code'    => 'appcheck_missing',
				'message' => __( 'App Check token is missing.', 'packrelay' ),
			);
		}

		$settings   = PackRelay_Settings::get_settings();
		$project_id = $settings['firebase_project_id'] ?? '';

		if ( empty( $project_id ) ) {
			return array(
				'success' => false,
				'code'    => 'appcheck_failed',
				'message' => __( 'Firebase project ID is not configured.', 'packrelay' ),
			);
		}

		try {
			$app_check = $this->factory
				->withProjectId( $project_id )
				->createAppCheck();

			$verified_token = $app_check->verifyToken( $token );
			$app_id         = $verified_token->appId;

			/**
			 * Fires after successful App Check verification.
			 *
			 * @param string $app_id  The verified app ID.
			 * @param int    $form_id The form ID.
			 * @param string $token   The original token.
			 */
			do_action( 'packrelay_appcheck_verified', $app_id, $form_id, $token );

			return array(
				'success' => true,
				'app_id'  => $app_id,
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'code'    => 'appcheck_failed',
				'message' => __( 'App Check verification failed. Please try again.', 'packrelay' ),
			);
		}
	}
}
