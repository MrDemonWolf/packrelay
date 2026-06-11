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
}
