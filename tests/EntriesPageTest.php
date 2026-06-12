<?php
/**
 * Tests for PackRelay_Entries_Page.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class EntriesPageTest extends TestCase {

	private \PackRelay_Entries_Page $page;

	protected function setUp(): void {
		parent::setUp();
		$this->page = new \PackRelay_Entries_Page();
	}

	public function test_add_menu_pages(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' ),
				'manage_options',
				'packrelay-entries',
				\Mockery::type( 'array' ),
				'dashicons-email-alt'
			);

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'packrelay-entries',
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' ),
				'manage_options',
				'packrelay-entries',
				\Mockery::type( 'array' )
			);

		$this->page->add_menu_pages();
	}

	public function test_sanitize_csv_cell_neutralizes_formula_triggers(): void {
		$this->assertSame( "'=HYPERLINK(\"http://evil\")", $this->page->sanitize_csv_cell( '=HYPERLINK("http://evil")' ) );
		$this->assertSame( "'+cmd", $this->page->sanitize_csv_cell( '+cmd' ) );
		$this->assertSame( "'-1+1", $this->page->sanitize_csv_cell( '-1+1' ) );
		$this->assertSame( "'@SUM(A1)", $this->page->sanitize_csv_cell( '@SUM(A1)' ) );
		$this->assertSame( "'\tx", $this->page->sanitize_csv_cell( "\tx" ) );
		$this->assertSame( "'\rx", $this->page->sanitize_csv_cell( "\rx" ) );
	}

	public function test_sanitize_csv_cell_leaves_safe_values_unchanged(): void {
		$this->assertSame( 'John Doe', $this->page->sanitize_csv_cell( 'John Doe' ) );
		$this->assertSame( 'john@example.com', $this->page->sanitize_csv_cell( 'john@example.com' ) );
		$this->assertSame( '', $this->page->sanitize_csv_cell( '' ) );
		$this->assertSame( '42', $this->page->sanitize_csv_cell( 42 ) );
	}

	public function test_sanitize_csv_cell_casts_numeric_types_to_string(): void {
		$this->assertSame( '0', $this->page->sanitize_csv_cell( 0 ) );
		$this->assertSame( '3.14', $this->page->sanitize_csv_cell( 3.14 ) );
	}

	public function test_get_view_url_includes_entry_id_and_nonce(): void {
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin.php' );
		Functions\when( 'add_query_arg' )->alias(
			function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'wp_nonce_url' )->alias(
			function ( $url, $action ) {
				return $url . '&_wpnonce=testhash';
			}
		);

		$url = \PackRelay_Entries_Page::get_view_url( 7 );

		$this->assertStringContainsString( 'entry_id=7', $url );
		$this->assertStringContainsString( 'action=view', $url );
		$this->assertStringContainsString( '_wpnonce=', $url );
	}

	public function test_get_view_url_casts_entry_id_to_positive_int(): void {
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin.php' );
		Functions\when( 'add_query_arg' )->alias(
			function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'wp_nonce_url' )->returnArg();

		$url = \PackRelay_Entries_Page::get_view_url( '5abc' );
		$this->assertStringContainsString( 'entry_id=5', $url );
	}
}
