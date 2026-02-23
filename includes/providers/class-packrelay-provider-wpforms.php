<?php
/**
 * WPForms provider.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Provider_WPForms
 *
 * Handles WPForms integration with native notification support.
 */
class PackRelay_Provider_WPForms extends PackRelay_Provider {

	/**
	 * Check if WPForms is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'wpforms' );
	}

	/**
	 * Get a WPForms form by ID.
	 *
	 * @param string $form_id The form ID.
	 * @return array|false
	 */
	public function get_form( $form_id ) {
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

		$fields = array();
		if ( ! empty( $form_data['fields'] ) ) {
			foreach ( $form_data['fields'] as $field ) {
				$fields[] = array(
					'id'       => (string) ( $field['id'] ?? '' ),
					'type'     => $field['type'] ?? '',
					'label'    => $field['label'] ?? '',
					'required' => ! empty( $field['required'] ),
				);
			}
		}

		return array(
			'title'     => $form_data['settings']['form_title'] ?? '',
			'fields'    => $fields,
			'form_data' => $form_data,
		);
	}

	/**
	 * Get normalized field arrays.
	 *
	 * @param string $form_id The form ID.
	 * @return array
	 */
	public function get_fields( $form_id ) {
		$form = $this->get_form( $form_id );
		if ( ! $form ) {
			return array();
		}

		return $form['fields'];
	}

	/**
	 * Get field type mapping.
	 *
	 * @param string $form_id The form ID.
	 * @return array
	 */
	public function get_field_types( $form_id ) {
		$form = $this->get_form( $form_id );
		if ( ! $form ) {
			return array();
		}

		$types = array();
		foreach ( $form['fields'] as $field ) {
			$types[ $field['id'] ] = $field['type'];
		}

		return $types;
	}

	/**
	 * Create an entry via WPForms entry API.
	 *
	 * @param string           $form_id The form ID.
	 * @param array            $fields  Field ID => value pairs.
	 * @param \WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function create_entry( $form_id, $fields, $request ) {
		if ( ! function_exists( 'wpforms' ) || ! wpforms()->entry ) {
			return array(
				'success' => false,
				'code'    => 'entry_failed',
				'message' => __( 'WPForms is not available.', 'packrelay' ),
			);
		}

		/**
		 * Filter the fields before saving.
		 *
		 * @param array            $fields  Field data.
		 * @param string           $form_id The form ID.
		 * @param \WP_REST_Request $request The REST request.
		 */
		$fields = apply_filters( 'packrelay_pre_save_fields', $fields, $form_id, $request );

		$sanitized_fields = array();
		foreach ( $fields as $field_id => $value ) {
			$sanitized_fields[ absint( $field_id ) ] = sanitize_text_field( $value );
		}

		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

		$forwarded_for = $request->get_header( 'X-Forwarded-For' );
		if ( ! empty( $forwarded_for ) ) {
			$ips          = array_map( 'trim', explode( ',', $forwarded_for ) );
			$candidate_ip = $ips[0];

			if ( filter_var( $candidate_ip, FILTER_VALIDATE_IP ) ) {
				$ip = $candidate_ip;
			}
		}

		$entry_data = array(
			'form_id'    => absint( $form_id ),
			'fields'     => wp_json_encode( $sanitized_fields ),
			'date'       => current_time( 'mysql' ),
			'ip_address' => $ip,
			'user_agent' => sanitize_text_field( $request->get_header( 'User-Agent' ) ?? '' ),
		);

		$entry_id = wpforms()->entry->add( $entry_data );

		if ( ! $entry_id ) {
			return array(
				'success' => false,
				'code'    => 'entry_failed',
				'message' => __( 'Failed to save entry to WPForms.', 'packrelay' ),
			);
		}

		return array(
			'success'  => true,
			'entry_id' => $entry_id,
		);
	}

	/**
	 * Send notifications using WPForms' native email system.
	 *
	 * @param string $form_id   The form ID.
	 * @param int    $entry_id  The entry ID.
	 * @param array  $fields    Submitted field data.
	 * @param array  $form_data The form data from get_form().
	 */
	public function send_notifications( $form_id, $entry_id, $fields, $form_data ) {
		if ( ! function_exists( 'wpforms' ) || ! isset( wpforms()->process ) ) {
			return;
		}

		$wpf_form_data = $form_data['form_data'] ?? array();
		if ( empty( $wpf_form_data ) ) {
			return;
		}

		// Build WPForms-format fields array.
		$wpf_fields = array();
		foreach ( $fields as $field_id => $value ) {
			$wpf_fields[ $field_id ] = array(
				'id'    => $field_id,
				'value' => $value,
				'name'  => $wpf_form_data['fields'][ $field_id ]['label'] ?? '',
				'type'  => $wpf_form_data['fields'][ $field_id ]['type'] ?? '',
			);
		}

		// Set the entry ID on the process instance.
		wpforms()->process->entry_id = $entry_id;

		// Force synchronous email delivery.
		add_filter( 'wpforms_tasks_entry_emails_trigger_send_same_process', '__return_true' );

		// Build entry object.
		$entry = (object) array(
			'entry_id' => $entry_id,
			'fields'   => $wpf_fields,
		);

		wpforms()->process->entry_email( $wpf_fields, $entry, $wpf_form_data );
	}

	/**
	 * Get the provider label.
	 *
	 * @return string
	 */
	public function get_label() {
		return 'WPForms';
	}

	/**
	 * Get the provider slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'wpforms';
	}
}
