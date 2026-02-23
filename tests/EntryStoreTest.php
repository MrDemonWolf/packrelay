<?php
/**
 * Tests for PackRelay_Entry_Store.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class EntryStoreTest extends TestCase {

	private \PackRelay_Entry_Store $store;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';

		$this->store = new \PackRelay_Entry_Store();
	}

	public function test_get_table_name(): void {
		$this->assertSame( 'wp_packrelay_entries', \PackRelay_Entry_Store::get_table_name() );
	}

	public function test_add_returns_insert_id(): void {
		global $wpdb;
		$wpdb->insert_id = 42;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->store->add(
			array(
				'provider'   => 'divi',
				'form_id'    => '42:0',
				'fields'     => '{"0":"John"}',
				'ip_address' => '127.0.0.1',
				'user_agent' => 'Test/1.0',
			)
		);

		$this->assertSame( 42, $result );
	}

	public function test_add_returns_false_on_failure(): void {
		global $wpdb;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$result = $this->store->add(
			array(
				'provider' => 'divi',
				'form_id'  => '42:0',
				'fields'   => '{}',
			)
		);

		$this->assertFalse( $result );
	}

	public function test_get_entry_returns_row(): void {
		global $wpdb;

		$entry = array(
			'id'           => '1',
			'provider'     => 'divi',
			'form_id'      => '42:0',
			'fields'       => '{"0":"John"}',
			'ip_address'   => '127.0.0.1',
			'user_agent'   => 'Test/1.0',
			'date_created' => '2026-01-01 00:00:00',
		);

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'SELECT * FROM wp_packrelay_entries WHERE id = 1' );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $entry );

		$result = $this->store->get_entry( 1 );

		$this->assertSame( $entry, $result );
	}

	public function test_delete_entry_returns_true_on_success(): void {
		global $wpdb;

		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_packrelay_entries', array( 'id' => 1 ), array( '%d' ) )
			->andReturn( 1 );

		$result = $this->store->delete_entry( 1 );

		$this->assertTrue( $result );
	}

	public function test_delete_entry_returns_false_on_failure(): void {
		global $wpdb;

		$wpdb->shouldReceive( 'delete' )
			->once()
			->andReturn( false );

		$result = $this->store->delete_entry( 999 );

		$this->assertFalse( $result );
	}

	public function test_count_returns_integer(): void {
		global $wpdb;

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '5' );

		$result = $this->store->count();

		$this->assertSame( 5, $result );
	}

	public function test_count_with_provider_filter(): void {
		global $wpdb;

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT COUNT(*) FROM wp_packrelay_entries WHERE provider = 'divi'" );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '3' );

		$result = $this->store->count( array( 'provider' => 'divi' ) );

		$this->assertSame( 3, $result );
	}
}
