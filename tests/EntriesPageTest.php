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
}
