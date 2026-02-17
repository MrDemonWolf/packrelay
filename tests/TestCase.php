<?php
/**
 * Base test case with Brain Monkey setup.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

namespace PackRelay\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Class TestCase
 */
abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey and common WP function stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Common WordPress function stubs.
		Functions\stubs(
			array(
				'sanitize_text_field' => function ( $str ) {
					return trim( strip_tags( (string) $str ) );
				},
				'sanitize_email'      => function ( $email ) {
					return filter_var( $email, FILTER_SANITIZE_EMAIL );
				},
				'absint'              => function ( $val ) {
					return abs( intval( $val ) );
				},
				'wp_json_encode'      => function ( $data ) {
					return json_encode( $data );
				},
				'esc_html'            => function ( $text ) {
					return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},
				'esc_html__'          => function ( $text ) {
					return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},
				'esc_attr'            => function ( $text ) {
					return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},
				'__'                  => function ( $text ) {
					return $text;
				},
				'is_email'            => function ( $email ) {
					return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
				},
				'current_time'        => function () {
					return gmdate( 'Y-m-d H:i:s' );
				},
				'wp_parse_args'       => function ( $args, $defaults ) {
					return array_merge( $defaults, (array) $args );
				},
				'plugin_dir_path'     => function ( $file ) {
					return dirname( $file ) . '/';
				},
				'plugin_dir_url'      => function () {
					return 'https://example.com/wp-content/plugins/packrelay/';
				},
				'plugin_basename'     => function ( $file ) {
					return 'packrelay/' . basename( $file );
				},
			)
		);
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
