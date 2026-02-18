<?php
/**
 * Tests for PackRelay_Loader.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

class LoaderTest extends TestCase {

	private \PackRelay_Loader $loader;

	protected function setUp(): void {
		parent::setUp();
		$this->loader = new \PackRelay_Loader();
	}

	public function test_add_action_stores_hook(): void {
		$component = new \stdClass();
		$this->loader->add_action( 'init', $component, 'some_method' );

		$actions = $this->loader->get_actions();
		$this->assertCount( 1, $actions );
		$this->assertSame( 'init', $actions[0]['hook'] );
		$this->assertSame( $component, $actions[0]['component'] );
		$this->assertSame( 'some_method', $actions[0]['callback'] );
		$this->assertSame( 10, $actions[0]['priority'] );
		$this->assertSame( 1, $actions[0]['accepted_args'] );
	}

	public function test_add_filter_stores_hook(): void {
		$component = new \stdClass();
		$this->loader->add_filter( 'the_content', $component, 'filter_method', 20, 2 );

		$filters = $this->loader->get_filters();
		$this->assertCount( 1, $filters );
		$this->assertSame( 'the_content', $filters[0]['hook'] );
		$this->assertSame( 20, $filters[0]['priority'] );
		$this->assertSame( 2, $filters[0]['accepted_args'] );
	}

	public function test_run_registers_actions_with_wordpress(): void {
		$component = new \stdClass();
		$this->loader->add_action( 'init', $component, 'do_init' );

		Functions\expect( 'add_action' )
			->once()
			->with( 'init', array( $component, 'do_init' ), 10, 1 );

		$this->loader->run();
	}

	public function test_run_registers_filters_with_wordpress(): void {
		$component = new \stdClass();
		$this->loader->add_filter( 'the_title', $component, 'filter_title', 5, 1 );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'the_title', array( $component, 'filter_title' ), 5, 1 );

		$this->loader->run();
	}

	public function test_multiple_hooks_registered(): void {
		$component = new \stdClass();
		$this->loader->add_action( 'init', $component, 'method_a' );
		$this->loader->add_action( 'wp_loaded', $component, 'method_b' );
		$this->loader->add_filter( 'the_content', $component, 'method_c' );

		$this->assertCount( 2, $this->loader->get_actions() );
		$this->assertCount( 1, $this->loader->get_filters() );
	}
}
