<?php
/**
 * Divi front-end form submission capture.
 *
 * Hooks into Divi's et_pb_contact_form_submit action to save submissions
 * to the unified entries table.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Divi_Submissions
 *
 * Captures Divi front-end form submissions and provides helper utilities.
 */
class PackRelay_Divi_Submissions {

	/**
	 * Entry store instance.
	 *
	 * @var PackRelay_Entry_Store
	 */
	private $entry_store;

	/**
	 * Constructor.
	 *
	 * @param PackRelay_Entry_Store|null $entry_store Optional entry store instance for testing.
	 */
	public function __construct( $entry_store = null ) {
		$this->entry_store = $entry_store ? $entry_store : new PackRelay_Entry_Store();
	}

	/**
	 * Save a Divi front-end form submission.
	 *
	 * Hooked to et_pb_contact_form_submit action.
	 *
	 * @param array  $processed_fields  Array of processed field data from Divi.
	 * @param bool   $et_contact_error  Whether Divi detected validation errors.
	 * @param array  $contact_form_info Form metadata from Divi.
	 */
	public function save_submission( $processed_fields, $et_contact_error, $contact_form_info ) {
		if ( $et_contact_error ) {
			return;
		}

		$fields = array();
		if ( is_array( $processed_fields ) ) {
			foreach ( $processed_fields as $field ) {
				$label = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '';
				$value = isset( $field['value'] ) ? sanitize_text_field( $field['value'] ) : '';
				if ( '' !== $label ) {
					$fields[ $label ] = $value;
				}
			}
		}

		$form_id    = isset( $contact_form_info['contact_form_unique_id'] ) ? sanitize_text_field( $contact_form_info['contact_form_unique_id'] ) : '';
		$form_number = isset( $contact_form_info['contact_form_number'] ) ? absint( $contact_form_info['contact_form_number'] ) : 0;
		$form_title = isset( $contact_form_info['title'] ) ? sanitize_text_field( $contact_form_info['title'] ) : '';

		$page_id    = get_the_ID() ? absint( get_the_ID() ) : 0;
		$page_title = $page_id ? get_the_title( $page_id ) : '';

		$this->entry_store->add(
			array(
				'provider'    => 'divi_frontend',
				'form_id'     => $form_id ? $form_id : (string) $form_number,
				'form_name'   => $form_title ? $form_title : __( 'Contact Form', 'packrelay' ),
				'page_id'     => $page_id,
				'page_title'  => $page_title,
				'referer_url' => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'fields'      => wp_json_encode( $fields ),
				'ip_address'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);
	}

	/**
	 * Extract a contact name from form fields.
	 *
	 * @param array $fields Decoded fields array.
	 * @return string
	 */
	public static function extract_name( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		$name_keys = array( 'name', 'Name', 'full_name', 'Full Name', 'your_name', 'Your Name', 'first_name', 'First Name' );
		foreach ( $name_keys as $key ) {
			if ( ! empty( $fields[ $key ] ) ) {
				return $fields[ $key ];
			}
		}

		return '';
	}

	/**
	 * Extract a contact email from form fields.
	 *
	 * @param array $fields Decoded fields array.
	 * @return string
	 */
	public static function extract_email( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		$email_keys = array( 'email', 'Email', 'email_address', 'Email Address', 'your_email', 'Your Email' );
		foreach ( $email_keys as $key ) {
			if ( ! empty( $fields[ $key ] ) ) {
				return $fields[ $key ];
			}
		}

		return '';
	}
}
