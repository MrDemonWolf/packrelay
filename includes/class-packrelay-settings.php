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
				'field'   => 'form_provider',
				'options' => $this->get_provider_options(),
				'desc'    => __( 'Select the form builder plugin to use with PackRelay.', 'packrelay' ),
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
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'packrelay_general',
			array(
				'field' => 'notification_email',
				'type'  => 'email',
				'desc'  => __( 'Where to send submission notifications. Defaults to admin email.', 'packrelay' ),
			)
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
				'desc'  => __( 'Comma-separated list of allowed CORS origins (e.g., https://app.example.com, capacitor://localhost). Cross-origin requests are blocked when blank.', 'packrelay' ),
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
	 * Render a select field.
	 *
	 * @param array $args Field arguments with 'field', 'options', and 'desc'.
	 */
	public function render_select_field( $args ) {
		$settings = self::get_settings();
		$field    = $args['field'];
		$value    = $settings[ $field ] ?? '';
		$options  = $args['options'] ?? array();
		$desc     = $args['desc'] ?? '';

		printf(
			'<select id="packrelay_%s" name="%s[%s]">',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $field )
		);

		foreach ( $options as $opt_value => $opt_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $opt_value ),
				selected( $value, $opt_value, false ),
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
		$sanitized['form_provider'] = in_array( $provider, $valid_providers, true ) ? $provider : 'divi';

		$sanitized['firebase_project_id'] = sanitize_text_field( $input['firebase_project_id'] ?? '' );
		$sanitized['notification_email']  = sanitize_email( $input['notification_email'] ?? '' );
		$sanitized['allowed_form_ids']    = sanitize_text_field( $input['allowed_form_ids'] ?? '' );
		$sanitized['allowed_origins']     = sanitize_text_field( $input['allowed_origins'] ?? '' );

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
			'form_provider'       => 'divi',
			'firebase_project_id' => 'mrdemonwolf-official-app',
			'notification_email'  => '',
			'allowed_form_ids'    => '',
			'allowed_origins'     => '',
		);
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
}
