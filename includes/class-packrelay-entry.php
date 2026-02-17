<?php
/**
 * WPForms entry creation logic.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Entry
 *
 * Creates entries in WPForms via its entry API.
 */
class PackRelay_Entry {

	/**
	 * Create a WPForms entry.
	 *
	 * @param int              $form_id The WPForms form ID.
	 * @param array            $fields  Field ID => value pairs.
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array{ success: bool, entry_id?: int, code?: string, message?: string }
	 */
	public function create( $form_id, $fields, $request ) {
		if ( ! function_exists( 'wpforms' ) || ! wpforms()->entry ) {
			return array(
				'success' => false,
				'code'    => 'entry_failed',
				'message' => __( 'WPForms is not available.', 'packrelay' ),
			);
		}

		/**
		 * Filter the fields before saving to WPForms.
		 *
		 * @param array            $fields  Field data.
		 * @param int              $form_id The form ID.
		 * @param \WP_REST_Request $request The REST request.
		 */
		$fields = apply_filters( 'packrelay_pre_save_fields', $fields, $form_id, $request );

		// Sanitize all field values.
		$sanitized_fields = array();
		foreach ( $fields as $field_id => $value ) {
			$sanitized_fields[ absint( $field_id ) ] = sanitize_text_field( $value );
		}

		$ip = sanitize_text_field( $request->get_header( 'X-Forwarded-For' ) ?? '' );
		if ( empty( $ip ) ) {
			$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
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
}
