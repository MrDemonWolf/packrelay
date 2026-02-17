<?php
/**
 * Google reCAPTCHA v3 server-side verification.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_ReCaptcha
 *
 * Verifies reCAPTCHA v3 tokens with Google's API.
 */
class PackRelay_ReCaptcha {

	/**
	 * Google reCAPTCHA verification URL.
	 *
	 * @var string
	 */
	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Verify a reCAPTCHA token.
	 *
	 * @param string $token   The reCAPTCHA token from the client.
	 * @param int    $form_id The form ID for per-form threshold filtering.
	 * @param string $ip      The client's IP address.
	 * @return array{ success: bool, code?: string, message?: string, score?: float }
	 */
	public function verify( $token, $form_id, $ip = '' ) {
		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'code'    => 'recaptcha_failed',
				'message' => __( 'reCAPTCHA token is missing.', 'packrelay' ),
			);
		}

		$settings   = PackRelay_Settings::get_settings();
		$secret_key = $settings['recaptcha_secret_key'];

		if ( empty( $secret_key ) ) {
			return array(
				'success' => false,
				'code'    => 'recaptcha_failed',
				'message' => __( 'reCAPTCHA secret key is not configured.', 'packrelay' ),
			);
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'code'    => 'recaptcha_failed',
				'message' => __( 'reCAPTCHA verification request failed.', 'packrelay' ),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return array(
				'success' => false,
				'code'    => 'recaptcha_failed',
				'message' => __( 'reCAPTCHA verification returned an unexpected response.', 'packrelay' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body ) || empty( $body['success'] ) ) {
			return array(
				'success' => false,
				'code'    => 'recaptcha_failed',
				'message' => __( 'reCAPTCHA verification failed. Please try again.', 'packrelay' ),
			);
		}

		$threshold = floatval( $settings['recaptcha_threshold'] ?? 0.5 );

		/**
		 * Filter the reCAPTCHA score threshold per form.
		 *
		 * @param float $threshold The score threshold.
		 * @param int   $form_id   The form ID.
		 */
		$threshold = apply_filters( 'packrelay_recaptcha_threshold', $threshold, $form_id );

		$score = floatval( $body['score'] ?? 0 );

		if ( $score < $threshold ) {
			return array(
				'success' => false,
				'code'    => 'recaptcha_low_score',
				'message' => __( 'reCAPTCHA score too low. Submission rejected.', 'packrelay' ),
				'score'   => $score,
			);
		}

		return array(
			'success' => true,
			'score'   => $score,
		);
	}
}
