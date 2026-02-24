<?php
/**
 * WordPress admin settings page.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Settings
 *
 * Registers the settings page and fields using the WordPress Settings API.
 */
class PackRelay_Settings {

	/**
	 * Option name for all plugin settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'packrelay_settings';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'packrelay';

	/**
	 * Add the settings page as a submenu under PackRelay.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'packrelay-entries',
			__( 'PackRelay Settings', 'packrelay' ),
			__( 'Settings', 'packrelay' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register all settings, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		// Provider section.
		add_settings_section(
			'packrelay_provider',
			__( 'Form Provider', 'packrelay' ),
			array( $this, 'render_provider_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'form_provider',
			__( 'Form Builder', 'packrelay' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'packrelay_provider',
			array(
				'field'            => 'form_provider',
				'options'          => $this->get_provider_options(),
				'disabled_options' => $this->get_disabled_providers(),
				'desc'             => __( 'Select the form builder plugin to use with PackRelay.', 'packrelay' ),
			)
		);

		// App Check section.
		add_settings_section(
			'packrelay_appcheck',
			__( 'Firebase App Check', 'packrelay' ),
			array( $this, 'render_appcheck_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'firebase_project_id',
			__( 'Firebase Project ID', 'packrelay' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'packrelay_appcheck',
			array(
				'field' => 'firebase_project_id',
				'type'  => 'text',
				'desc'  => __( 'Your Firebase project ID for App Check verification.', 'packrelay' ),
			)
		);

		// General section.
		add_settings_section(
			'packrelay_general',
			__( 'General Settings', 'packrelay' ),
			null,
			self::PAGE_SLUG
		);

		add_settings_field(
			'notification_email',
			__( 'Notification Email', 'packrelay' ),
			array( $this, 'render_notification_email_field' ),
			self::PAGE_SLUG,
			'packrelay_general'
		);

		add_settings_field(
			'allowed_form_ids',
			__( 'Allowed Form IDs', 'packrelay' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'packrelay_general',
			array(
				'field' => 'allowed_form_ids',
				'type'  => 'text',
				'desc'  => __( 'Comma-separated list of form IDs that PackRelay can accept submissions for. For Divi, use post_id:form_index format (e.g. 42:0).', 'packrelay' ),
			)
		);

		add_settings_field(
			'allowed_origins',
			__( 'Allowed Origins', 'packrelay' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'packrelay_general',
			array(
				'field' => 'allowed_origins',
				'type'  => 'text',
				'desc'  => __( 'Comma-separated CORS origins allowed to call the PackRelay REST API (e.g., https://app.example.com, capacitor://localhost). This controls which domains can make cross-origin requests to your site. Firebase App Check verifies the request comes from your authorized app, while Allowed Origins controls which domains the browser permits to make the request. Both work together — App Check validates the app identity, CORS validates the request origin. Leave blank to block all cross-origin requests (same-origin only).', 'packrelay' ),
			)
		);

		// Email template section.
		add_settings_section(
			'packrelay_email_template',
			__( 'Email Notification Template', 'packrelay' ),
			array( $this, 'render_email_template_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'notification_subject',
			__( 'Email Subject', 'packrelay' ),
			array( $this, 'render_template_field' ),
			self::PAGE_SLUG,
			'packrelay_email_template',
			array(
				'field'   => 'notification_subject',
				'type'    => 'text',
				'desc'    => __( 'Subject line for notification emails sent via the REST API.', 'packrelay' ),
			)
		);

		add_settings_field(
			'notification_body',
			__( 'Email Body', 'packrelay' ),
			array( $this, 'render_template_field' ),
			self::PAGE_SLUG,
			'packrelay_email_template',
			array(
				'field'   => 'notification_body',
				'type'    => 'textarea',
				'desc'    => __( 'Body template for notification emails sent via the REST API.', 'packrelay' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once PACKRELAY_PLUGIN_DIR . 'admin/settings-page.php';
	}

	/**
	 * Render the Provider section description.
	 */
	public function render_provider_section() {
		echo '<p>' . esc_html__( 'Choose which form builder PackRelay should integrate with.', 'packrelay' ) . '</p>';
	}

	/**
	 * Render the App Check section description.
	 */
	public function render_appcheck_section() {
		echo '<p>' . esc_html__( 'Configure your Firebase project for App Check verification.', 'packrelay' ) . '</p>';
	}

	/**
	 * Render the Email Template section description.
	 */
	public function render_email_template_section() {
		echo '<p>' . esc_html__( 'Customize the email notification sent for Mobile App (REST API) submissions. Divi frontend submissions use Divi\'s own email handling.', 'packrelay' ) . '</p>';
	}

	/**
	 * Render a text input field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$settings = self::get_settings();
		$field    = $args['field'];
		$type     = $args['type'] ?? 'text';
		$value    = $settings[ $field ] ?? '';
		$desc     = $args['desc'] ?? '';

		printf(
			'<input type="%s" id="packrelay_%s" name="%s[%s]" value="%s" class="regular-text" />',
			esc_attr( $type ),
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $field ),
			esc_attr( $value )
		);

		if ( $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}

	/**
	 * Render the notification email field with reset button.
	 */
	public function render_notification_email_field() {
		$settings = self::get_settings();
		$value    = $settings['notification_email'] ?? '';

		printf(
			'<input type="email" id="packrelay_notification_email" name="%s[notification_email]" value="%s" class="regular-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);

		echo ' <button type="button" class="button button-secondary" id="packrelay-reset-email">';
		esc_html_e( 'Reset to Admin Email', 'packrelay' );
		echo '</button>';

		echo '<p class="description">' . esc_html__( 'Where to send submission notifications. Defaults to admin email.', 'packrelay' ) . '</p>';
	}

	/**
	 * Render a template field with placeholder variable buttons.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_template_field( $args ) {
		$settings = self::get_settings();
		$field    = $args['field'];
		$type     = $args['type'] ?? 'text';
		$value    = $settings[ $field ] ?? '';
		$desc     = $args['desc'] ?? '';

		if ( 'textarea' === $type ) {
			printf(
				'<textarea id="packrelay_%s" name="%s[%s]" rows="6" class="large-text">%s</textarea>',
				esc_attr( $field ),
				esc_attr( self::OPTION_NAME ),
				esc_attr( $field ),
				esc_html( $value )
			);
		} else {
			printf(
				'<input type="text" id="packrelay_%s" name="%s[%s]" value="%s" class="regular-text" />',
				esc_attr( $field ),
				esc_attr( self::OPTION_NAME ),
				esc_attr( $field ),
				esc_attr( $value )
			);
		}

		if ( $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}

		// Placeholder variable buttons.
		$variables = self::get_template_variables();

		echo '<div class="packrelay-placeholder-buttons">';
		echo '<p class="description"><strong>' . esc_html__( 'Insert variable:', 'packrelay' ) . '</strong></p>';
		foreach ( $variables as $var => $label ) {
			printf(
				'<button type="button" class="button button-small packrelay-placeholder-btn" data-target="packrelay_%s" data-value="%s">%s</button> ',
				esc_attr( $field ),
				esc_attr( $var ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	/**
	 * Render a select field.
	 *
	 * @param array $args Field arguments with 'field', 'options', 'disabled_options', and 'desc'.
	 */
	public function render_select_field( $args ) {
		$settings         = self::get_settings();
		$field            = $args['field'];
		$value            = $settings[ $field ] ?? '';
		$options          = $args['options'] ?? array();
		$disabled_options = $args['disabled_options'] ?? array();
		$desc             = $args['desc'] ?? '';

		printf(
			'<select id="packrelay_%s" name="%s[%s]">',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $field )
		);

		foreach ( $options as $opt_value => $opt_label ) {
			$disabled = in_array( $opt_value, $disabled_options, true ) ? ' disabled="disabled"' : '';
			printf(
				'<option value="%s" %s%s>%s</option>',
				esc_attr( $opt_value ),
				selected( $value, $opt_value, false ),
				$disabled,
				esc_html( $opt_label )
			);
		}

		echo '</select>';

		if ( $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$valid_providers = array( 'divi', 'wpforms', 'gravityforms' );
		$provider        = sanitize_text_field( $input['form_provider'] ?? 'divi' );

		// Reject disabled (unavailable) providers.
		$disabled = $this->get_disabled_providers();
		if ( in_array( $provider, $disabled, true ) ) {
			$provider = 'divi';
		}

		$sanitized['form_provider'] = in_array( $provider, $valid_providers, true ) ? $provider : 'divi';

		$sanitized['firebase_project_id']  = sanitize_text_field( $input['firebase_project_id'] ?? '' );
		$sanitized['notification_email']   = sanitize_email( $input['notification_email'] ?? '' );
		$sanitized['allowed_form_ids']     = sanitize_text_field( $input['allowed_form_ids'] ?? '' );
		$sanitized['allowed_origins']      = sanitize_text_field( $input['allowed_origins'] ?? '' );
		$sanitized['notification_subject'] = sanitize_text_field( $input['notification_subject'] ?? '' );
		$sanitized['notification_body']    = sanitize_textarea_field( $input['notification_body'] ?? '' );

		return $sanitized;
	}

	/**
	 * Get current plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return wp_parse_args(
			get_option( self::OPTION_NAME, array() ),
			self::get_defaults()
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'form_provider'        => 'divi',
			'firebase_project_id'  => 'mrdemonwolf-official-app',
			'notification_email'   => '',
			'allowed_form_ids'     => '',
			'allowed_origins'      => '',
			'notification_subject' => 'New {form_name} submission from {site_name}',
			'notification_body'    => "A new form submission was received.\n\n{all_fields}\n\nSubmitted: {submission_date}",
		);
	}

	/**
	 * Get available template variables for email templates.
	 *
	 * @return array Variable => human-readable label.
	 */
	public static function get_template_variables() {
		return array(
			'{site_name}'       => '{site_name}',
			'{admin_email}'     => '{admin_email}',
			'{form_name}'       => '{form_name}',
			'{form_id}'         => '{form_id}',
			'{entry_id}'        => '{entry_id}',
			'{submission_date}' => '{submission_date}',
			'{all_fields}'      => '{all_fields}',
			'{ip_address}'      => '{ip_address}',
		);
	}

	/**
	 * Parse a template string replacing variables with actual values.
	 *
	 * @param string $template The template string.
	 * @param array  $data     Data array with keys: form_name, form_id, entry_id, fields, ip_address.
	 * @return string Parsed template.
	 */
	public static function parse_template( $template, $data ) {
		$fields     = $data['fields'] ?? array();
		$all_fields = '';

		if ( is_array( $fields ) ) {
			$lines = array();
			foreach ( $fields as $key => $value ) {
				$lines[] = $key . ': ' . $value;
			}
			$all_fields = implode( "\n", $lines );
		}

		$replacements = array(
			'{site_name}'       => get_bloginfo( 'name' ),
			'{admin_email}'     => get_option( 'admin_email' ),
			'{form_name}'       => $data['form_name'] ?? '',
			'{form_id}'         => $data['form_id'] ?? '',
			'{entry_id}'        => $data['entry_id'] ?? '',
			'{submission_date}' => current_time( 'mysql' ),
			'{all_fields}'      => $all_fields,
			'{ip_address}'      => $data['ip_address'] ?? '',
		);

		$result = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		// Replace individual field variables {field:FIELD_KEY}.
		if ( is_array( $fields ) ) {
			foreach ( $fields as $key => $value ) {
				$result = str_replace( '{field:' . $key . '}', $value, $result );
			}
		}

		return $result;
	}

	/**
	 * Get provider options for the select field.
	 *
	 * @return array
	 */
	private function get_provider_options() {
		$providers = PackRelay_Provider_Factory::get_available_providers();
		$options   = array();

		foreach ( $providers as $slug => $data ) {
			$label = $data['label'];
			if ( ! $data['available'] ) {
				$label .= ' ' . __( '(not installed)', 'packrelay' );
			}
			$options[ $slug ] = $label;
		}

		return $options;
	}

	/**
	 * Get list of provider slugs that are unavailable (should be disabled).
	 *
	 * @return array
	 */
	private function get_disabled_providers() {
		$providers = PackRelay_Provider_Factory::get_available_providers();
		$disabled  = array();

		foreach ( $providers as $slug => $data ) {
			if ( ! $data['available'] ) {
				$disabled[] = $slug;
			}
		}

		return $disabled;
	}
}
