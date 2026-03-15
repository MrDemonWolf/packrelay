<?php
/**
 * Divi form builder provider.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Provider_Divi
 *
 * Handles Divi contact form integration. Divi forms use post_id:form_index
 * format (e.g. 42:0) and store form config in post_content shortcodes.
 */
class PackRelay_Provider_Divi extends PackRelay_Provider {

	/**
	 * Check if Divi is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Divi 4: theme or plugin.
		if ( function_exists( 'et_setup_theme' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' ) ) {
			return true;
		}

		// Divi 5: check for builder-5 directory.
		$theme_dir = get_template_directory();
		if ( is_dir( $theme_dir . '/builder-5' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Divi 5 is running.
	 *
	 * @return bool
	 */
	private function is_divi5() {
		$theme_dir = get_template_directory();
		if ( is_dir( $theme_dir . '/builder-5' ) ) {
			return true;
		}

		$divi_version = defined( 'ET_CORE_VERSION' ) ? ET_CORE_VERSION : '';
		if ( ! empty( $divi_version ) && version_compare( $divi_version, '5.0', '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get a Divi form by composite ID (post_id:form_index).
	 *
	 * @param string $form_id The form ID in post_id:form_index format.
	 * @return array|false
	 */
	public function get_form( $form_id ) {
		$parsed = $this->parse_form_id( $form_id );
		if ( ! $parsed ) {
			return false;
		}

		// Divi 5 UUID-based form ID.
		if ( $this->is_divi5() && ! empty( $parsed['form_uid'] ) ) {
			return $this->get_form_divi5( $parsed['post_id'], $parsed['form_uid'] );
		}

		// Divi 4 shortcode-based form.
		$post = get_post( $parsed['post_id'] );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		$forms = $this->extract_contact_forms( $post->post_content );
		if ( ! isset( $forms[ $parsed['form_index'] ] ) ) {
			return false;
		}

		$form_shortcode = $forms[ $parsed['form_index'] ];
		$form_atts      = shortcode_parse_atts( $form_shortcode['atts'] );
		$title          = $form_atts['title'] ?? '';
		$email          = $form_atts['email'] ?? '';

		$fields = $this->parse_fields( $form_shortcode['content'] );

		return array(
			'title'  => $title,
			'email'  => $email,
			'fields' => $fields,
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

		$normalized = array();
		foreach ( $form['fields'] as $index => $field ) {
			$normalized[] = array(
				'id'       => (string) $index,
				'type'     => $field['type'],
				'label'    => $field['label'],
				'required' => $field['required'],
			);
		}

		return $normalized;
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
		foreach ( $form['fields'] as $index => $field ) {
			$types[ (string) $index ] = $field['type'];
		}

		return $types;
	}

	/**
	 * Create an entry via the entry store.
	 *
	 * @param string           $form_id The form ID.
	 * @param array            $fields  Field ID => value pairs.
	 * @param \WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function create_entry( $form_id, $fields, $request ) {
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
			$sanitized_fields[ sanitize_text_field( $field_id ) ] = sanitize_text_field( $value );
		}

		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

		/** This filter is documented in includes/class-packrelay-rest-api.php */
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

		$store    = new PackRelay_Entry_Store();
		$entry_id = $store->add(
			array(
				'provider'     => $this->get_slug(),
				'form_id'      => $form_id,
				'fields'       => wp_json_encode( $sanitized_fields ),
				'ip_address'   => $ip,
				'user_agent'   => sanitize_text_field( $request->get_header( 'User-Agent' ) ?? '' ),
				'date_created' => current_time( 'mysql' ),
			)
		);

		if ( ! $entry_id ) {
			return array(
				'success' => false,
				'code'    => 'entry_failed',
				'message' => __( 'Failed to save entry.', 'packrelay' ),
			);
		}

		return array(
			'success'  => true,
			'entry_id' => $entry_id,
		);
	}

	/**
	 * Send notifications via wp_mail() matching Divi's native email format.
	 *
	 * @param string $form_id   The form ID.
	 * @param int    $entry_id  The entry ID.
	 * @param array  $fields    Submitted field data.
	 * @param array  $form_data The form data from get_form().
	 */
	public function send_notifications( $form_id, $entry_id, $fields, $form_data ) {
		$to = $form_data['email'] ?? '';

		if ( empty( $to ) ) {
			$settings = PackRelay_Settings::get_settings();
			$to       = $settings['notification_email'];
		}

		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		if ( empty( $to ) ) {
			return;
		}

		$form_title = $form_data['title'] ?? '';
		if ( empty( $form_title ) ) {
			/* translators: %s: form ID */
			$form_title = sprintf( __( 'Form %s', 'packrelay' ), $form_id );
		}

		/* translators: %s: form title */
		$subject = sprintf( __( '[PackRelay] New submission: %s', 'packrelay' ), $form_title );

		$body = '<h2>' . esc_html( $form_title ) . '</h2>';
		$body .= '<table style="border-collapse: collapse; width: 100%;">';

		foreach ( $fields as $field_id => $value ) {
			$body .= '<tr>';
			$body .= '<td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">';
			$body .= esc_html( $field_id );
			$body .= '</td>';
			$body .= '<td style="padding: 8px; border: 1px solid #ddd;">';
			$body .= esc_html( $value );
			$body .= '</td>';
			$body .= '</tr>';
		}

		$body .= '</table>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$mail_args = array(
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
			'headers' => $headers,
		);

		/**
		 * Filter the email notification arguments.
		 *
		 * @param array  $mail_args The email arguments.
		 * @param int    $entry_id  The entry ID.
		 * @param string $form_id   The form ID.
		 */
		$mail_args = apply_filters( 'packrelay_notification_args', $mail_args, $entry_id, $form_id );

		wp_mail(
			$mail_args['to'],
			$mail_args['subject'],
			$mail_args['body'],
			$mail_args['headers']
		);
	}

	/**
	 * Get the provider label.
	 *
	 * @return string
	 */
	public function get_label() {
		return 'Divi';
	}

	/**
	 * Get the provider slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'divi';
	}

	/**
	 * Parse a composite form ID.
	 *
	 * @param string $form_id The form ID (e.g. "42:0" or "42").
	 * @return array|false Array with post_id and form_index, or false.
	 */
	private function parse_form_id( $form_id ) {
		$form_id = (string) $form_id;

		// Divi 5 UUID format: post_id:uuid.
		if ( preg_match( '/^(\d+):([a-f0-9-]{36})$/i', $form_id, $matches ) ) {
			return array(
				'post_id'    => (int) $matches[1],
				'form_index' => 0,
				'form_uid'   => $matches[2],
			);
		}

		// Divi 4 format: post_id:form_index or just post_id.
		if ( ! preg_match( '/^(\d+)(?::(\d+))?$/', $form_id, $matches ) ) {
			return false;
		}

		return array(
			'post_id'    => (int) $matches[1],
			'form_index' => isset( $matches[2] ) ? (int) $matches[2] : 0,
			'form_uid'   => '',
		);
	}

	/**
	 * Extract contact form shortcodes from post content.
	 *
	 * @param string $content The post content.
	 * @return array Array of form data with 'atts' and 'content' keys.
	 */
	private function extract_contact_forms( $content ) {
		$forms = array();

		if ( preg_match_all(
			'/\[et_pb_contact_form([^\]]*)\](.*?)\[\/et_pb_contact_form\]/s',
			$content,
			$matches,
			PREG_SET_ORDER
		) ) {
			foreach ( $matches as $match ) {
				$forms[] = array(
					'atts'    => $match[1],
					'content' => $match[2],
				);
			}
		}

		return $forms;
	}

	/**
	 * Parse field shortcodes from form content.
	 *
	 * @param string $content The form shortcode content.
	 * @return array
	 */
	private function parse_fields( $content ) {
		$fields = array();

		if ( preg_match_all(
			'/\[et_pb_contact_field([^\]]*)\]/s',
			$content,
			$matches
		) ) {
			foreach ( $matches[1] as $index => $atts_str ) {
				$atts = shortcode_parse_atts( $atts_str );

				$divi_type = $atts['field_type'] ?? 'input';
				$type      = $this->map_field_type( $divi_type );

				$fields[] = array(
					'label'    => $atts['field_title'] ?? '',
					'type'     => $type,
					'required' => 'off' !== ( $atts['required_mark'] ?? 'on' ),
				);
			}
		}

		return $fields;
	}

	/**
	 * Map Divi field types to normalized types.
	 *
	 * @param string $divi_type The Divi field type.
	 * @return string
	 */
	private function map_field_type( $divi_type ) {
		$map = array(
			'input'           => 'text',
			'text'            => 'textarea',
			'email'           => 'email',
			'url'             => 'url',
			'checkbox'        => 'checkbox',
			'booleancheckbox' => 'checkbox',
			'radio'           => 'radio',
			'select'          => 'select',
			'hidden'          => 'hidden',
		);

		return $map[ $divi_type ] ?? 'text';
	}

	/**
	 * Get a Divi 5 form by parsing blocks.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $form_uid The form unique ID.
	 * @return array|false
	 */
	private function get_form_divi5( $post_id, $form_uid ) {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		$blocks     = parse_blocks( $post->post_content );
		$form_block = $this->find_contact_form_block( $blocks, $form_uid );

		if ( ! $form_block ) {
			return false;
		}

		$attrs = $form_block['attrs'] ?? array();
		$title = $attrs['title'] ?? '';
		$email = $attrs['email'] ?? '';

		$fields = $this->parse_fields_divi5( $form_block );

		return array(
			'title'  => $title,
			'email'  => $email,
			'fields' => $fields,
		);
	}

	/**
	 * Recursively find a divi/contact-form block by unique ID.
	 *
	 * @param array  $blocks   Array of parsed blocks.
	 * @param string $form_uid The form unique ID to find.
	 * @return array|false The matching block or false.
	 */
	private function find_contact_form_block( $blocks, $form_uid ) {
		foreach ( $blocks as $block ) {
			if ( 'divi/contact-form' === ( $block['blockName'] ?? '' ) ) {
				$block_uid = $block['attrs']['uniqueId'] ?? ( $block['attrs']['unique_id'] ?? '' );
				if ( $block_uid === $form_uid ) {
					return $block;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find_contact_form_block( $block['innerBlocks'], $form_uid );
				if ( $found ) {
					return $found;
				}
			}
		}

		return false;
	}

	/**
	 * Parse fields from a Divi 5 contact form block's inner blocks.
	 *
	 * @param array $form_block The contact form block.
	 * @return array
	 */
	private function parse_fields_divi5( $form_block ) {
		$fields       = array();
		$inner_blocks = $form_block['innerBlocks'] ?? array();

		foreach ( $inner_blocks as $block ) {
			if ( 'divi/contact-form-field' === ( $block['blockName'] ?? '' ) ) {
				$attrs = $block['attrs'] ?? array();

				$divi_type = $attrs['fieldType'] ?? ( $attrs['field_type'] ?? 'input' );
				$type      = $this->map_field_type( $divi_type );

				$fields[] = array(
					'label'    => $attrs['fieldTitle'] ?? ( $attrs['field_title'] ?? '' ),
					'type'     => $type,
					'required' => 'off' !== ( $attrs['requiredMark'] ?? ( $attrs['required_mark'] ?? 'on' ) ),
				);
			}
		}

		return $fields;
	}
}
