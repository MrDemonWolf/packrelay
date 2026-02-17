<?php
/**
 * Tests for PackRelay_Settings.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class SettingsTest extends TestCase {

	private \PackRelay_Settings $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new \PackRelay_Settings();
	}

	public function test_add_settings_page(): void {
		Functions\expect( 'add_options_page' )
			->once()
			->with(
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' ),
				'manage_options',
				'packrelay',
				\Mockery::type( 'array' )
			);

		$this->settings->add_settings_page();
	}

	public function test_register_settings(): void {
		Functions\expect( 'register_setting' )->once();
		Functions\expect( 'add_settings_section' )->twice();
		Functions\expect( 'add_settings_field' )->times( 6 );

		$this->settings->register_settings();
	}

	public function test_sanitize_settings(): void {
		$input = array(
			'recaptcha_site_key'   => '<script>key</script>',
			'recaptcha_secret_key' => 'secret-123',
			'recaptcha_threshold'  => '0.7',
			'notification_email'   => 'test@example.com',
			'allowed_form_ids'     => '1, 2, 3',
			'allowed_origins'      => 'https://app.example.com',
		);

		$result = $this->settings->sanitize_settings( $input );

		$this->assertSame( 'key', $result['recaptcha_site_key'] );
		$this->assertSame( 'secret-123', $result['recaptcha_secret_key'] );
		$this->assertSame( 0.7, $result['recaptcha_threshold'] );
		$this->assertSame( 'test@example.com', $result['notification_email'] );
		$this->assertSame( '1, 2, 3', $result['allowed_form_ids'] );
		$this->assertSame( 'https://app.example.com', $result['allowed_origins'] );
	}

	public function test_sanitize_clamps_threshold(): void {
		$result = $this->settings->sanitize_settings(
			array( 'recaptcha_threshold' => '1.5' )
		);
		$this->assertSame( 1.0, $result['recaptcha_threshold'] );

		$result = $this->settings->sanitize_settings(
			array( 'recaptcha_threshold' => '-0.5' )
		);
		$this->assertSame( 0.0, $result['recaptcha_threshold'] );
	}

	public function test_get_defaults(): void {
		$defaults = \PackRelay_Settings::get_defaults();

		$this->assertArrayHasKey( 'recaptcha_site_key', $defaults );
		$this->assertArrayHasKey( 'recaptcha_secret_key', $defaults );
		$this->assertArrayHasKey( 'recaptcha_threshold', $defaults );
		$this->assertArrayHasKey( 'notification_email', $defaults );
		$this->assertArrayHasKey( 'allowed_form_ids', $defaults );
		$this->assertArrayHasKey( 'allowed_origins', $defaults );
		$this->assertSame( 0.5, $defaults['recaptcha_threshold'] );
	}

	public function test_get_settings_merges_with_defaults(): void {
		Functions\expect( 'get_option' )
			->with( 'packrelay_settings', \Mockery::any() )
			->andReturn( array( 'recaptcha_threshold' => 0.8 ) );

		$settings = \PackRelay_Settings::get_settings();

		$this->assertSame( 0.8, $settings['recaptcha_threshold'] );
		$this->assertSame( '', $settings['recaptcha_site_key'] );
	}
}
