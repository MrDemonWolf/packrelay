<?php
/**
 * Tests for PackRelay_Entries_List_Table.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

class EntriesListTableTest extends TestCase {

	private \PackRelay_Entries_List_Table $table;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb         = \Mockery::mock();
		$wpdb->prefix = 'wp_';

		$this->table = new \PackRelay_Entries_List_Table();
	}

	// === column_source ===

	public function test_column_source_mobile_badge_for_divi(): void {
		$result = $this->table->column_source( array( 'provider' => 'divi' ) );
		$this->assertStringContainsString( 'packrelay-source-badge mobile', $result );
		$this->assertStringContainsString( 'Mobile App', $result );
	}

	public function test_column_source_mobile_badge_for_wpforms(): void {
		$result = $this->table->column_source( array( 'provider' => 'wpforms' ) );
		$this->assertStringContainsString( 'packrelay-source-badge mobile', $result );
	}

	public function test_column_source_mobile_badge_for_gravityforms(): void {
		$result = $this->table->column_source( array( 'provider' => 'gravityforms' ) );
		$this->assertStringContainsString( 'packrelay-source-badge mobile', $result );
	}

	public function test_column_source_frontend_badge_for_divi_frontend(): void {
		$result = $this->table->column_source( array( 'provider' => 'divi_frontend' ) );
		$this->assertStringContainsString( 'packrelay-source-badge frontend', $result );
		$this->assertStringContainsString( 'Divi Frontend', $result );
	}

	public function test_column_source_returns_span_tag(): void {
		$result = $this->table->column_source( array( 'provider' => 'divi' ) );
		$this->assertStringStartsWith( '<span', $result );
		$this->assertStringEndsWith( '</span>', $result );
	}

	// === column_provider ===

	public function test_column_provider_badge_for_divi(): void {
		$result = $this->table->column_provider( array( 'provider' => 'divi' ) );
		$this->assertStringContainsString( 'packrelay-provider-badge', $result );
		$this->assertStringContainsString( 'Divi', $result );
	}

	public function test_column_provider_badge_for_divi_frontend(): void {
		$result = $this->table->column_provider( array( 'provider' => 'divi_frontend' ) );
		$this->assertStringContainsString( 'packrelay-provider-badge', $result );
		$this->assertStringContainsString( 'Divi', $result );
	}

	public function test_column_provider_badge_for_wpforms(): void {
		$result = $this->table->column_provider( array( 'provider' => 'wpforms' ) );
		$this->assertStringContainsString( 'packrelay-provider-badge', $result );
		$this->assertStringContainsString( 'WPForms', $result );
	}

	public function test_column_provider_badge_for_gravityforms(): void {
		$result = $this->table->column_provider( array( 'provider' => 'gravityforms' ) );
		$this->assertStringContainsString( 'packrelay-provider-badge', $result );
		$this->assertStringContainsString( 'Gravity Forms', $result );
	}

	public function test_column_provider_falls_back_to_raw_value_for_unknown_provider(): void {
		$result = $this->table->column_provider( array( 'provider' => 'custom_builder' ) );
		$this->assertStringContainsString( 'packrelay-provider-badge', $result );
		$this->assertStringContainsString( 'custom_builder', $result );
	}

	// === other columns ===

	public function test_column_form_id_returns_escaped_value(): void {
		$result = $this->table->column_form_id( array( 'form_id' => '42:0' ) );
		$this->assertSame( '42:0', $result );
	}

	public function test_column_ip_address_returns_value(): void {
		$result = $this->table->column_ip_address( array( 'ip_address' => '127.0.0.1' ) );
		$this->assertSame( '127.0.0.1', $result );
	}

	public function test_column_date_created_returns_date_string(): void {
		$result = $this->table->column_date_created( array( 'date_created' => '2026-06-11 12:00:00' ) );
		$this->assertSame( '2026-06-11 12:00:00', $result );
	}

	public function test_column_fields_returns_mdash_for_invalid_json(): void {
		$result = $this->table->column_fields( array( 'fields' => 'not-json' ) );
		$this->assertSame( '&mdash;', $result );
	}

	public function test_column_fields_returns_mdash_for_non_array_json(): void {
		$result = $this->table->column_fields( array( 'fields' => '"just a string"' ) );
		$this->assertSame( '&mdash;', $result );
	}

	public function test_column_fields_renders_key_value_preview(): void {
		$fields = json_encode( array( 'name' => 'John Doe', 'email' => 'john@example.com' ) );
		$result = $this->table->column_fields( array( 'fields' => $fields ) );
		$this->assertStringContainsString( 'name', $result );
		$this->assertStringContainsString( 'John Doe', $result );
		$this->assertStringContainsString( 'email', $result );
	}

	public function test_column_fields_limits_preview_to_three_fields(): void {
		$fields = json_encode( array( 'a' => '1', 'b' => '2', 'c' => '3', 'd' => '4' ) );
		$result = $this->table->column_fields( array( 'fields' => $fields ) );
		// At most 3 key: value pairs, each separated by <br>.
		$this->assertLessThanOrEqual( 2, substr_count( $result, '<br>' ) );
	}

	public function test_column_cb_contains_entry_id(): void {
		$result = $this->table->column_cb( array( 'id' => 99 ) );
		$this->assertStringContainsString( 'value="99"', $result );
		$this->assertStringContainsString( 'entry_ids[]', $result );
	}

	// === structure ===

	public function test_get_columns_contains_required_keys(): void {
		$columns = $this->table->get_columns();
		foreach ( array( 'cb', 'id', 'source', 'provider', 'form_id', 'fields', 'ip_address', 'date_created' ) as $key ) {
			$this->assertArrayHasKey( $key, $columns, "Missing column: $key" );
		}
	}

	public function test_get_sortable_columns_includes_id_and_date(): void {
		$sortable = $this->table->get_sortable_columns();
		$this->assertArrayHasKey( 'id', $sortable );
		$this->assertArrayHasKey( 'date_created', $sortable );
	}

	public function test_get_bulk_actions_includes_delete(): void {
		$actions = $this->table->get_bulk_actions();
		$this->assertArrayHasKey( 'delete', $actions );
		$this->assertSame( 'Delete', $actions['delete'] );
	}
}
