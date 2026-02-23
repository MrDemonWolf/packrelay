<?php
/**
 * Abstract provider base class.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Provider
 *
 * Base class for all form builder providers.
 */
abstract class PackRelay_Provider {

	/**
	 * Check if the form builder is available.
	 *
	 * @return bool
	 */
	abstract public function is_available();

	/**
	 * Get a form by ID.
	 *
	 * @param string $form_id The form ID.
	 * @return array|false Returns [ 'title' => string, 'fields' => [...] ] or false.
	 */
	abstract public function get_form( $form_id );

	/**
	 * Get normalized field arrays for a form.
	 *
	 * @param string $form_id The form ID.
	 * @return array Array of [ 'id', 'type', 'label', 'required' ] arrays.
	 */
	abstract public function get_fields( $form_id );

	/**
	 * Get field type mapping for a form.
	 *
	 * @param string $form_id The form ID.
	 * @return array Field ID => type mapping.
	 */
	abstract public function get_field_types( $form_id );

	/**
	 * Create an entry for a form submission.
	 *
	 * @param string           $form_id The form ID.
	 * @param array            $fields  Field ID => value pairs.
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array{ success: bool, entry_id?: int, code?: string, message?: string }
	 */
	abstract public function create_entry( $form_id, $fields, $request );

	/**
	 * Send notifications using the form builder's native system.
	 *
	 * @param string $form_id   The form ID.
	 * @param int    $entry_id  The entry ID.
	 * @param array  $fields    Submitted field data.
	 * @param array  $form_data The form data.
	 */
	abstract public function send_notifications( $form_id, $entry_id, $fields, $form_data );

	/**
	 * Get the human-readable label for this provider.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Get the slug identifier for this provider.
	 *
	 * @return string
	 */
	abstract public function get_slug();
}
