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
	 * Add the settings page under the Settings menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'PackRelay Settings', 'packrelay' ),
			__( 'PackRelay', 'packrelay' ),
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

		// reCAPTCHA section.
		add_settings_section(
			'packrelay_recaptcha',
			__( 'Google reCAPTCHA v3', 'packrelay' ),
			array( $this, 'render_recaptcha_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'recaptcha_site_key',
			__( 'Site Key', 'packrelay' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'packrelay_recaptcha',
			array(
				'field' => 'recaptcha_site_key',
				'type'  => 'text',
				'desc'  => __( 'Your Google reCAPTCHA v3 site key.', 'packrelay' ),
			)
		);

		add_settings_field(
			'recaptcha_secret_key',
			__( 'Secret Key', 'packrelay' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'packrelay_recaptcha',
			array(
				'field' => 'recaptcha_secret_key',
				'type'  => 'password',
				'desc'  => __( 'Your Google reCAPTCHA v3 secret key.', 'packrelay' ),
			)
		);

		add_settings_field(
			'recaptcha_threshold',
			__( 'Score Threshold', 'packrelay' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'packrelay_recaptcha',
			array(
				'field' => 'recaptcha_threshold',
				'min'   => 0,
				'max'   => 1,
				'step'  => 0.1,
				'desc'  => __( 'Minimum reCAPTCHA score to accept (0.0–1.0, default: 0.5).', 'packrelay' ),
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
				'desc'  => __( 'Comma-separated list of WPForms form IDs that PackRelay can accept submissions for.', 'packrelay' ),
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
				'desc'  => __( 'Comma-separated list of allowed CORS origins. Leave blank to allow all.', 'packrelay' ),
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
	 * Render the reCAPTCHA section description.
	 */
	public function render_recaptcha_section() {
		echo '<p>' . esc_html__( 'Configure your Google reCAPTCHA v3 keys for spam protection.', 'packrelay' ) . '</p>';
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
	 * Render a number input field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$settings = self::get_settings();
		$field    = $args['field'];
		$value    = $settings[ $field ] ?? 0.5;
		$min      = $args['min'] ?? 0;
		$max      = $args['max'] ?? 1;
		$step     = $args['step'] ?? 0.1;
		$desc     = $args['desc'] ?? '';

		printf(
			'<input type="number" id="packrelay_%s" name="%s[%s]" value="%s" min="%s" max="%s" step="%s" />',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $field ),
			esc_attr( $value ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step )
		);

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

		$sanitized['recaptcha_site_key']   = sanitize_text_field( $input['recaptcha_site_key'] ?? '' );
		$sanitized['recaptcha_secret_key'] = sanitize_text_field( $input['recaptcha_secret_key'] ?? '' );
		$sanitized['recaptcha_threshold']  = floatval( $input['recaptcha_threshold'] ?? 0.5 );
		$sanitized['notification_email']   = sanitize_email( $input['notification_email'] ?? '' );
		$sanitized['allowed_form_ids']     = sanitize_text_field( $input['allowed_form_ids'] ?? '' );
		$sanitized['allowed_origins']      = sanitize_text_field( $input['allowed_origins'] ?? '' );

		// Clamp threshold between 0 and 1.
		$sanitized['recaptcha_threshold'] = max( 0.0, min( 1.0, $sanitized['recaptcha_threshold'] ) );

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
			'recaptcha_site_key'   => '',
			'recaptcha_secret_key' => '',
			'recaptcha_threshold'  => 0.5,
			'notification_email'   => '',
			'allowed_form_ids'     => '',
			'allowed_origins'      => '',
		);
	}
}
