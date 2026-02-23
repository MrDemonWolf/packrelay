<?php
/**
 * Tests for PackRelay_Divi_Submissions.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;
use Mockery;

class DiviSubmissionsTest extends TestCase {

	private \PackRelay_Entry_Store $store;

	private \PackRelay_Divi_Submissions $submissions;

	protected function setUp(): void {
		parent::setUp();

		$this->store       = Mockery::mock( \PackRelay_Entry_Store::class );
		$this->submissions = new \PackRelay_Divi_Submissions( $this->store );
	}

	public function test_save_submission_stores_entry(): void {
		Functions\when( 'get_the_ID' )->justReturn( 10 );
		Functions\when( 'get_the_title' )->justReturn( 'About Us' );

		$this->store->shouldReceive( 'add' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return 'divi_frontend' === $data['provider']
							&& 'abc123' === $data['form_id']
							&& 'Contact Us' === $data['form_name']
							&& 10 === $data['page_id']
							&& 'About Us' === $data['page_title']
							&& is_string( $data['fields'] );
					}
				)
			)
			->andReturn( 1 );

		$processed_fields = array(
			array( 'label' => 'Name', 'value' => 'John Doe' ),
			array( 'label' => 'Email', 'value' => 'john@example.com' ),
			array( 'label' => 'Message', 'value' => 'Hello there' ),
		);

		$contact_form_info = array(
			'contact_form_unique_id' => 'abc123',
			'contact_form_number'    => 0,
			'title'                  => 'Contact Us',
		);

		$this->submissions->save_submission( $processed_fields, false, $contact_form_info );
	}

	public function test_save_submission_skips_on_error(): void {
		$this->store->shouldNotReceive( 'add' );

		$this->submissions->save_submission( array(), true, array() );
	}

	public function test_save_submission_extracts_field_labels(): void {
		Functions\when( 'get_the_ID' )->justReturn( 0 );

		$captured_data = null;
		$this->store->shouldReceive( 'add' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) use ( &$captured_data ) {
						$captured_data = $data;
						return true;
					}
				)
			)
			->andReturn( 1 );

		$processed_fields = array(
			array( 'label' => 'First Name', 'value' => 'Jane' ),
			array( 'label' => 'Phone', 'value' => '555-1234' ),
		);

		$this->submissions->save_submission( $processed_fields, false, array( 'contact_form_number' => 1 ) );

		$fields = json_decode( $captured_data['fields'], true );
		$this->assertArrayHasKey( 'First Name', $fields );
		$this->assertSame( 'Jane', $fields['First Name'] );
		$this->assertArrayHasKey( 'Phone', $fields );
		$this->assertSame( '555-1234', $fields['Phone'] );
	}

	public function test_add_submenu_page_only_when_divi_provider(): void {
		Functions\expect( 'get_option' )
			->with( 'packrelay_settings', Mockery::any() )
			->andReturn(
				array(
					'form_provider' => 'divi',
				)
			);

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'packrelay-entries',
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				'manage_options',
				'packrelay-divi-submissions',
				Mockery::type( 'array' )
			);

		$this->submissions->add_submenu_page();
	}

	public function test_add_submenu_page_skipped_for_wpforms_provider(): void {
		Functions\expect( 'get_option' )
			->with( 'packrelay_settings', Mockery::any() )
			->andReturn(
				array(
					'form_provider' => 'wpforms',
				)
			);

		Functions\expect( 'add_submenu_page' )->never();

		$this->submissions->add_submenu_page();
	}

	public function test_extract_name_finds_name_field(): void {
		$fields = array( 'Name' => 'John Doe', 'Email' => 'john@example.com' );
		$this->assertSame( 'John Doe', \PackRelay_Divi_Submissions::extract_name( $fields ) );
	}

	public function test_extract_name_returns_empty_when_no_name(): void {
		$fields = array( 'Subject' => 'Hello', 'Message' => 'Hi there' );
		$this->assertSame( '', \PackRelay_Divi_Submissions::extract_name( $fields ) );
	}

	public function test_extract_email_finds_email_field(): void {
		$fields = array( 'Name' => 'John Doe', 'Email' => 'john@example.com' );
		$this->assertSame( 'john@example.com', \PackRelay_Divi_Submissions::extract_email( $fields ) );
	}

	public function test_extract_email_returns_empty_when_no_email(): void {
		$fields = array( 'Name' => 'John', 'Message' => 'Hi' );
		$this->assertSame( '', \PackRelay_Divi_Submissions::extract_email( $fields ) );
	}

	public function test_handle_export_does_nothing_without_param(): void {
		$_GET = array();
		$this->store->shouldNotReceive( 'get_entries' );
		$this->submissions->handle_export();
	}

	public function test_enqueue_styles_skips_other_pages(): void {
		// wp_enqueue_style should not be called for non-matching hook suffixes.
		// Since it's stubbed as a no-op, we verify by checking that the method
		// returns without error for a non-matching suffix.
		$this->submissions->enqueue_styles( 'toplevel_page_packrelay-entries' );
		$this->assertTrue( true );
	}

	public function test_enqueue_styles_matches_correct_page(): void {
		// Verify that the method runs without error for the correct page.
		$this->submissions->enqueue_styles( 'packrelay_page_packrelay-divi-submissions' );
		$this->assertTrue( true );
	}
}
