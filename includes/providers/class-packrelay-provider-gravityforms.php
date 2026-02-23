<?php
/**
 * Gravity Forms provider.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Provider_GravityForms
 *
 * Handles Gravity Forms integration. Uses GFAPI for form/entry operations.
 * Notifications fire automatically via GFAPI::submit_form().
 */
class PackRelay_Provider_GravityForms extends PackRelay_Provider {

	/**
	 * Check if Gravity Forms is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return class_exists( 'GFAPI' );
	}

	/**
	 * Get a Gravity Forms form by ID.
	 *
	 * @param string $form_id The form ID.
	 * @return array|false
	 */
	public function get_form( $form_id ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return false;
		}

		$form = \GFAPI::get_form( absint( $form_id ) );

		if ( ! $form || is_wp_error( $form ) ) {
			return false;
		}

		$fields = array();
		if ( ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$fields[] = array(
					'id'       => (string) $field->id,
					'type'     => $field->type ?? '',
					'label'    => $field->label ?? '',
					'required' => ! empty( $field->isRequired ),
				);
			}
		}

		return array(
			'title'     => $form['title'] ?? '',
			'fields'    => $fields,
			'form_data' => $form,
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
	 * Create an entry via GFAPI::submit_form().
	 *
	 * @param string           $form_id The form ID.
	 * @param array            $fields  Field ID => value pairs.
	 * @param \WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function create_entry( $form_id, $fields, $request ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array(
				'success' => false,
				'code'    => 'entry_failed',
				'message' => __( 'Gravity Forms is not available.', 'packrelay' ),
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

		// Map field_id => value to GF's input_FIELDID => value format.
		$input_values = array();
		foreach ( $fields as $field_id => $value ) {
			$input_values[ 'input_' . $field_id ] = sanitize_text_field( $value );
		}

		// Bypass reCAPTCHA validation since App Check replaces it.
		$bypass_recaptcha = function ( $result, $value, $form, $field ) {
			if ( 'captcha' === $field->type ) {
				$result['is_valid'] = true;
			}
			return $result;
		};

		add_filter( 'gform_field_validation', $bypass_recaptcha, 10, 4 );

		$result = \GFAPI::submit_form( absint( $form_id ), $input_values );

		remove_filter( 'gform_field_validation', $bypass_recaptcha, 10 );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'code'    => 'entry_failed',
				'message' => __( 'Failed to submit form via Gravity Forms.', 'packrelay' ),
			);
		}

		if ( ! empty( $result['is_valid'] ) && ! empty( $result['entry_id'] ) ) {
			return array(
				'success'  => true,
				'entry_id' => $result['entry_id'],
			);
		}

		return array(
			'success' => false,
			'code'    => 'entry_failed',
			'message' => __( 'Gravity Forms validation failed.', 'packrelay' ),
		);
	}

	/**
	 * Send notifications — no-op since GFAPI::submit_form() handles this.
	 *
	 * @param string $form_id   The form ID.
	 * @param int    $entry_id  The entry ID.
	 * @param array  $fields    Submitted field data.
	 * @param array  $form_data The form data.
	 */
	public function send_notifications( $form_id, $entry_id, $fields, $form_data ) {
		// GFAPI::submit_form() already fired notifications during create_entry().
	}

	/**
	 * Get the provider label.
	 *
	 * @return string
	 */
	public function get_label() {
		return 'Gravity Forms';
	}

	/**
	 * Get the provider slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'gravityforms';
	}
}
