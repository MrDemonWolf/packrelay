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
	 * ReCaptcha instance.
	 *
	 * @var PackRelay_ReCaptcha
	 */
	private $recaptcha;

	/**
	 * Entry instance.
	 *
	 * @var PackRelay_Entry
	 */
	private $entry;

	/**
	 * Notification instance.
	 *
	 * @var PackRelay_Notification
	 */
	private $notification;

	/**
	 * Constructor.
	 *
	 * @param PackRelay_ReCaptcha|null    $recaptcha    Optional reCAPTCHA instance.
	 * @param PackRelay_Entry|null        $entry        Optional entry instance.
	 * @param PackRelay_Notification|null $notification Optional notification instance.
	 */
	public function __construct( $recaptcha = null, $entry = null, $notification = null ) {
		$this->recaptcha    = $recaptcha ?: new PackRelay_ReCaptcha();
		$this->entry        = $entry ?: new PackRelay_Entry();
		$this->notification = $notification ?: new PackRelay_Notification();
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/submit/(?P<form_id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_submit' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'form_id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
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
			'/forms/(?P<form_id>\d+)/fields',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_get_fields' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'form_id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
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

		// Verify the form exists in WPForms.
		$form = $this->get_wpforms_form( $form_id );
		if ( ! $form ) {
			return $this->error_response( 'form_not_found', __( 'The specified WPForms form does not exist.', 'packrelay' ), 404 );
		}

		// Verify reCAPTCHA.
		$token = $request->get_param( 'recaptcha_token' );
		$ip    = $this->get_client_ip( $request );

		$recaptcha_result = $this->recaptcha->verify( $token, $form_id, $ip );
		if ( ! $recaptcha_result['success'] ) {
			return $this->error_response(
				$recaptcha_result['code'],
				$recaptcha_result['message'],
				403
			);
		}

		// Validate fields.
		$fields = $request->get_param( 'fields' );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $this->error_response( 'missing_fields', __( 'Required fields are missing from the request.', 'packrelay' ), 400 );
		}

		// Validate email fields.
		$form_fields = $this->get_form_field_types( $form );
		foreach ( $form_fields as $field_id => $field_type ) {
			if ( 'email' === $field_type && isset( $fields[ $field_id ] ) && ! empty( $fields[ $field_id ] ) ) {
				if ( ! is_email( $fields[ $field_id ] ) ) {
					return $this->error_response( 'invalid_email', __( 'Invalid email address provided.', 'packrelay' ), 400 );
				}
			}
		}

		// Create entry.
		$entry_result = $this->entry->create( $form_id, $fields, $request );
		if ( ! $entry_result['success'] ) {
			return $this->error_response(
				$entry_result['code'],
				$entry_result['message'],
				500
			);
		}

		$entry_id   = $entry_result['entry_id'];
		$form_title = $form['settings']['form_title'] ?? '';

		// Send notification.
		$this->notification->send( $entry_id, $form_id, $fields, $form_title );

		/**
		 * Fires after successful entry creation.
		 *
		 * @param int              $entry_id The entry ID.
		 * @param int              $form_id  The form ID.
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
		 * @param array $response The response data.
		 * @param int   $entry_id The entry ID.
		 * @param int   $form_id  The form ID.
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

		$form = $this->get_wpforms_form( $form_id );
		if ( ! $form ) {
			return $this->error_response( 'form_not_found', __( 'The specified WPForms form does not exist.', 'packrelay' ), 404 );
		}

		$form_title = $form['settings']['form_title'] ?? '';
		$fields     = array();

		if ( ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$fields[] = array(
					'id'       => (string) ( $field['id'] ?? '' ),
					'type'     => $field['type'] ?? '',
					'label'    => $field['label'] ?? '',
					'required' => ! empty( $field['required'] ),
				);
			}
		}

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'form_id'    => $form_id,
				'form_title' => $form_title,
				'fields'     => $fields,
			),
			200
		);
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
			header( 'Access-Control-Allow-Origin: ' . $origin );
		}

		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type' );

		return $served;
	}

	/**
	 * Check if a form ID is in the allowlist.
	 *
	 * @param int $form_id The form ID.
	 * @return bool
	 */
	private function is_form_allowed( $form_id ) {
		$settings    = PackRelay_Settings::get_settings();
		$allowed_ids = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $settings['allowed_form_ids'] ?? '' ) ) ) );

		/**
		 * Filter allowed form IDs.
		 *
		 * @param array $allowed_ids The allowed form IDs.
		 */
		$allowed_ids = apply_filters( 'packrelay_allowed_form_ids', $allowed_ids );

		if ( empty( $allowed_ids ) ) {
			return false;
		}

		return in_array( absint( $form_id ), $allowed_ids, true );
	}

	/**
	 * Get a WPForms form by ID.
	 *
	 * @param int $form_id The form ID.
	 * @return array|false The form data or false.
	 */
	private function get_wpforms_form( $form_id ) {
		if ( ! function_exists( 'wpforms' ) ) {
			return false;
		}

		$form = wpforms()->form->get( absint( $form_id ) );

		if ( ! $form ) {
			return false;
		}

		$form_data = wpforms_decode( $form->post_content );

		if ( empty( $form_data ) ) {
			return false;
		}

		return $form_data;
	}

	/**
	 * Get field types from form data.
	 *
	 * @param array $form The decoded form data.
	 * @return array Field ID => type mapping.
	 */
	private function get_form_field_types( $form ) {
		$types = array();

		if ( ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$types[ (string) $field['id'] ] = $field['type'] ?? '';
			}
		}

		return $types;
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
	 * Get the client IP address from the request.
	 *
	 * Uses REMOTE_ADDR as the primary source. Falls back to the first valid
	 * IP from X-Forwarded-For only when the header is present.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return string
	 */
	private function get_client_ip( $request ) {
		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

		$forwarded_for = $request->get_header( 'X-Forwarded-For' );
		if ( ! empty( $forwarded_for ) ) {
			// X-Forwarded-For may contain a comma-separated list; take the first entry.
			$ips          = array_map( 'trim', explode( ',', $forwarded_for ) );
			$candidate_ip = $ips[0];

			if ( filter_var( $candidate_ip, FILTER_VALIDATE_IP ) ) {
				$ip = $candidate_ip;
			}
		}

		return $ip;
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
