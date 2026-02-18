<?php
/**
 * Tests for PackRelay_Entry.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;

class EntryTest extends TestCase {

	private \PackRelay_Entry $entry;

	protected function setUp(): void {
		parent::setUp();
		$this->entry = new \PackRelay_Entry();
	}

	public function test_create_fails_when_wpforms_not_available(): void {
		// wpforms() function is not defined, so it should fail.
		$request = new \WP_REST_Request();
		$result  = $this->entry->create( 123, array( '1' => 'John' ), $request );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'entry_failed', $result['code'] );
	}

	public function test_create_succeeds_with_wpforms(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->andReturn( 42 );

		$wpforms_mock         = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), 123, \Mockery::any() )
			->andReturnUsing( function ( $_hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$request->set_header( 'User-Agent', 'TestAgent/1.0' );

		$result = $this->entry->create(
			123,
			array( '1' => 'John', '2' => 'Doe', '3' => 'john@example.com' ),
			$request
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 42, $result['entry_id'] );
	}

	public function test_create_returns_failure_when_add_fails(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->andReturn( false );

		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), 123, \Mockery::any() )
			->andReturnUsing( function ( $_hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$result  = $this->entry->create( 123, array( '1' => 'John' ), $request );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'entry_failed', $result['code'] );
	}

	public function test_fields_are_sanitized(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->with(
				\Mockery::on(
					function ( $data ) {
						$fields = json_decode( $data['fields'], true );
						// Tags should be stripped.
						return 'Clean' === $fields[1];
					}
				)
			)
			->andReturn( 1 );

		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), 123, \Mockery::any() )
			->andReturnUsing( function ( $_hook, $fields ) {
				return $fields;
			} );

		$request = new \WP_REST_Request();
		$result  = $this->entry->create( 123, array( '1' => '<script>Clean</script>' ), $request );

		$this->assertTrue( $result['success'] );
	}

	public function test_pre_save_fields_filter_applied(): void {
		$entry_mock = \Mockery::mock();
		$entry_mock->shouldReceive( 'add' )
			->once()
			->andReturn( 10 );

		$wpforms_mock        = new \stdClass();
		$wpforms_mock->entry = $entry_mock;

		Functions\expect( 'wpforms' )
			->andReturn( $wpforms_mock );

		Functions\expect( 'apply_filters' )
			->with( 'packrelay_pre_save_fields', \Mockery::any(), 123, \Mockery::any() )
			->andReturn( array( '1' => 'Modified' ) );

		$request = new \WP_REST_Request();
		$result  = $this->entry->create( 123, array( '1' => 'Original' ), $request );

		$this->assertTrue( $result['success'] );
	}
}
