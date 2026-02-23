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
		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'packrelay-entries',
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
		Functions\expect( 'add_settings_section' )->times( 3 );
		Functions\expect( 'add_settings_field' )->times( 5 );

		$this->settings->register_settings();
	}

	public function test_sanitize_settings(): void {
		$input = array(
			'form_provider'       => 'wpforms',
			'firebase_project_id' => '<script>proj</script>',
			'notification_email'  => 'test@example.com',
			'allowed_form_ids'    => '1, 2, 3',
			'allowed_origins'     => 'https://app.example.com',
		);

		$result = $this->settings->sanitize_settings( $input );

		$this->assertSame( 'wpforms', $result['form_provider'] );
		$this->assertSame( 'proj', $result['firebase_project_id'] );
		$this->assertSame( 'test@example.com', $result['notification_email'] );
		$this->assertSame( '1, 2, 3', $result['allowed_form_ids'] );
		$this->assertSame( 'https://app.example.com', $result['allowed_origins'] );
	}

	public function test_sanitize_settings_rejects_invalid_provider(): void {
		$input = array(
			'form_provider' => 'invalid_provider',
		);

		$result = $this->settings->sanitize_settings( $input );

		$this->assertSame( 'divi', $result['form_provider'] );
	}

	public function test_get_defaults(): void {
		$defaults = \PackRelay_Settings::get_defaults();

		$this->assertArrayHasKey( 'form_provider', $defaults );
		$this->assertArrayHasKey( 'firebase_project_id', $defaults );
		$this->assertArrayHasKey( 'notification_email', $defaults );
		$this->assertArrayHasKey( 'allowed_form_ids', $defaults );
		$this->assertArrayHasKey( 'allowed_origins', $defaults );
		$this->assertSame( 'divi', $defaults['form_provider'] );
		$this->assertSame( 'mrdemonwolf-official-app', $defaults['firebase_project_id'] );
	}

	public function test_get_settings_merges_with_defaults(): void {
		Functions\expect( 'get_option' )
			->with( 'packrelay_settings', \Mockery::any() )
			->andReturn( array( 'firebase_project_id' => 'custom-project' ) );

		$settings = \PackRelay_Settings::get_settings();

		$this->assertSame( 'custom-project', $settings['firebase_project_id'] );
		$this->assertSame( '', $settings['notification_email'] );
		$this->assertSame( 'divi', $settings['form_provider'] );
	}
}
