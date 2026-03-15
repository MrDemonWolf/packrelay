<?php
/**
 * REST API endpoint registration and handling.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_REST_API
 *
 * Registers REST endpoints for form submission and field retrieval.
 */
class PackRelay_REST_API {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'packrelay/v1';

	/**
	 * App Check instance.
	 *
	 * @var PackRelay_AppCheck
	 */
	private $appcheck;

	/**
	 * Provider instance.
	 *
	 * @var PackRelay_Provider
	 */
	private $provider;

	/**
	 * Entry store instance.
	 *
	 * @var PackRelay_Entry_Store
	 */
	private $entry_store;

	/**
	 * Constructor.
	 *
	 * @param PackRelay_AppCheck|null    $appcheck    Optional App Check instance.
	 * @param PackRelay_Provider|null    $provider    Optional provider instance.
	 * @param PackRelay_Entry_Store|null $entry_store Optional entry store instance.
	 */
	public function __construct( $appcheck = null, $provider = null, $entry_store = null ) {
		$this->appcheck    = $appcheck ?: new PackRelay_AppCheck();
		$this->provider    = $provider ?: PackRelay_Provider_Factory::create();
		$this->entry_store = $entry_store ?: new PackRelay_Entry_Store();
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/submit/(?P<form_id>[0-9a-fA-F:_-]+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_submit' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'form_id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return (bool) preg_match( '/^\d+(?::(?:\d+|[a-f0-9-]{36}))?$/i', $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'OPTIONS',
					'callback'            => array( $this, 'handle_options' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<form_id>[0-9a-fA-F:_-]+)/fields',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_get_fields' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'form_id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return (bool) preg_match( '/^\d+(?::(?:\d+|[a-f0-9-]{36}))?$/i', $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'OPTIONS',
					'callback'            => array( $this, 'handle_options' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle form submission.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_submit( $request ) {
		$form_id = $request->get_param( 'form_id' );

		// Check form ID allowlist.
		if ( ! $this->is_form_allowed( $form_id ) ) {
			return $this->error_response( 'form_not_found', __( 'The specified form is not available.', 'packrelay' ), 404 );
		}

		// Verify the form exists via provider.
		$form = $this->provider->get_form( $form_id );
		if ( ! $form ) {
			return $this->error_response( 'form_not_found', __( 'The specified form does not exist.', 'packrelay' ), 404 );
		}

		// Verify App Check token.
		$token = $request->get_param( 'app_check_token' );

		$appcheck_result = $this->appcheck->verify( $token, $form_id );
		if ( ! $appcheck_result['success'] ) {
			return $this->error_response(
				$appcheck_result['code'],
				$appcheck_result['message'],
				403
			);
		}

		// Validate fields.
		$fields = $request->get_param( 'fields' );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $this->error_response( 'missing_fields', __( 'Required fields are missing from the request.', 'packrelay' ), 400 );
		}

		// Validate email fields.
		$form_fields = $this->provider->get_field_types( $form_id );
		foreach ( $form_fields as $field_id => $field_type ) {
			if ( 'email' === $field_type && isset( $fields[ $field_id ] ) && ! empty( $fields[ $field_id ] ) ) {
				if ( ! is_email( $fields[ $field_id ] ) ) {
					return $this->error_response( 'invalid_email', __( 'Invalid email address provided.', 'packrelay' ), 400 );
				}
			}
		}

		// Create entry via provider.
		$entry_result = $this->provider->create_entry( $form_id, $fields, $request );
		if ( ! $entry_result['success'] ) {
			return $this->error_response(
				$entry_result['code'],
				$entry_result['message'],
				500
			);
		}

		$entry_id = $entry_result['entry_id'];

		// Log to unified entry store (for non-Divi providers that have their own storage).
		if ( 'divi' !== $this->provider->get_slug() ) {
			$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

			/**
			 * Filter the trusted proxy headers used for IP detection.
			 *
			 * X-Forwarded-For is spoofable without a trusted proxy configuration.
			 * Return an empty array to disable proxy header trust entirely.
			 *
			 * @param array $headers Trusted proxy headers.
			 */
			$trusted_headers = apply_filters( 'packrelay_trusted_proxy_headers', array( 'X-Forwarded-For' ) );

			if ( in_array( 'X-Forwarded-For', $trusted_headers, true ) ) {
				$forwarded_for = $request->get_header( 'X-Forwarded-For' );
				if ( ! empty( $forwarded_for ) ) {
					$ips          = array_map( 'trim', explode( ',', $forwarded_for ) );
					$candidate_ip = $ips[0];

					if ( filter_var( $candidate_ip, FILTER_VALIDATE_IP ) ) {
						$ip = $candidate_ip;
					}
				}
			}

			$this->entry_store->add(
				array(
					'provider'     => $this->provider->get_slug(),
					'form_id'      => $form_id,
					'fields'       => wp_json_encode( $fields ),
					'ip_address'   => $ip,
					'user_agent'   => sanitize_text_field( $request->get_header( 'User-Agent' ) ?? '' ),
					'date_created' => current_time( 'mysql' ),
				)
			);
		}

		// Send notifications via provider.
		$this->provider->send_notifications( $form_id, $entry_id, $fields, $form );

		/**
		 * Fires after successful entry creation.
		 *
		 * @param int              $entry_id The entry ID.
		 * @param string           $form_id  The form ID.
		 * @param array            $fields   The submitted fields.
		 * @param \WP_REST_Request $request  The REST request.
		 */
		do_action( 'packrelay_entry_created', $entry_id, $form_id, $fields, $request );

		$response = array(
			'success'  => true,
			'message'  => __( 'Form submitted successfully.', 'packrelay' ),
			'entry_id' => $entry_id,
		);

		/**
		 * Filter the REST API response before returning.
		 *
		 * @param array  $response The response data.
		 * @param int    $entry_id The entry ID.
		 * @param string $form_id  The form ID.
		 */
		$response = apply_filters( 'packrelay_rest_response', $response, $entry_id, $form_id );

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle GET form fields.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_get_fields( $request ) {
		$form_id = $request->get_param( 'form_id' );

		if ( ! $this->is_form_allowed( $form_id ) ) {
			return $this->error_response( 'form_not_found', __( 'The specified form is not available.', 'packrelay' ), 404 );
		}

		$form = $this->provider->get_form( $form_id );
		if ( ! $form ) {
			return $this->error_response( 'form_not_found', __( 'The specified form does not exist.', 'packrelay' ), 404 );
		}

		$response = new \WP_REST_Response(
			array(
				'success'    => true,
				'form_id'    => $form_id,
				'form_title' => $form['title'] ?? '',
				'fields'     => $form['fields'] ?? array(),
			),
			200
		);

		$response->header( 'Cache-Control', 'public, max-age=300' );

		return $response;
	}

	/**
	 * Handle OPTIONS preflight request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_options() {
		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param bool              $served  Whether the request has been served.
	 * @param \WP_HTTP_Response $result  Result to send.
	 * @param \WP_REST_Request  $request Request used to generate the response.
	 * @param \WP_REST_Server   $server  Server instance.
	 * @return bool
	 */
	public function add_cors_headers( $served, $result, $request, $server ) {
		$route = $request->get_route();

		if ( 0 !== strpos( $route, '/' . self::NAMESPACE ) ) {
			return $served;
		}

		$origin          = $request->get_header( 'Origin' );
		$allowed_origins = $this->get_allowed_origins();

		if ( ! empty( $allowed_origins ) && $origin && in_array( $origin, $allowed_origins, true ) ) {
			$safe_origin = str_replace( array( "\r", "\n" ), '', $origin );
			$safe_origin = esc_url( $safe_origin );
			if ( ! empty( $safe_origin ) ) {
				header( 'Access-Control-Allow-Origin: ' . $safe_origin );
			}
		}

		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type' );

		return $served;
	}

	/**
	 * Check if a form ID is in the allowlist.
	 *
	 * @param string $form_id The form ID.
	 * @return bool
	 */
	private function is_form_allowed( $form_id ) {
		$settings    = PackRelay_Settings::get_settings();
		$allowed_ids = array_filter( array_map( 'trim', explode( ',', $settings['allowed_form_ids'] ?? '' ) ) );

		/**
		 * Filter allowed form IDs.
		 *
		 * @param array $allowed_ids The allowed form IDs.
		 */
		$allowed_ids = apply_filters( 'packrelay_allowed_form_ids', $allowed_ids );

		if ( empty( $allowed_ids ) ) {
			return false;
		}

		return in_array( (string) $form_id, $allowed_ids, true );
	}

	/**
	 * Get allowed CORS origins.
	 *
	 * @return array
	 */
	private function get_allowed_origins() {
		$settings = PackRelay_Settings::get_settings();
		$origins  = $settings['allowed_origins'] ?? '';

		if ( empty( $origins ) ) {
			return array();
		}

		return array_filter( array_map( 'trim', explode( ',', $origins ) ) );
	}

	/**
	 * Build an error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_REST_Response
	 */
	private function error_response( $code, $message, $status ) {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}
}
