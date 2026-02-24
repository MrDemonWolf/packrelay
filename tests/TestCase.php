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
				'shortcode_parse_atts' => function ( $text ) {
					$atts    = array();
					$text    = preg_replace( "/[\x{00a0}\x{200b}]+/u", ' ', $text );
					if ( preg_match_all( '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)/', $text, $match, PREG_SET_ORDER ) ) {
						foreach ( $match as $m ) {
							if ( ! empty( $m[1] ) ) {
								$atts[ strtolower( $m[1] ) ] = $m[2];
							} elseif ( ! empty( $m[3] ) ) {
								$atts[ strtolower( $m[3] ) ] = $m[4];
							} elseif ( ! empty( $m[5] ) ) {
								$atts[ strtolower( $m[5] ) ] = $m[6];
							}
						}
					}
					return $atts;
				},
				'sanitize_textarea_field' => function ( $str ) {
					return trim( strip_tags( (string) $str ) );
				},
				'get_bloginfo'        => function ( $show = '' ) {
					return 'Test Site';
				},
				'selected'            => function ( $selected, $current = true, $echo = true ) {
					$result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
					if ( $echo ) {
						echo $result;
					}
					return $result;
				},
				'submit_button'       => function () {},
				'add_query_arg'       => function ( $args, $url = '' ) {
					return $url . '?' . http_build_query( $args );
				},
				'admin_url'           => function ( $path = '' ) {
					return 'https://example.com/wp-admin/' . $path;
				},
				'wp_nonce_url'        => function ( $url, $action = '' ) {
					return $url . '&_wpnonce=test';
				},
				'wp_verify_nonce'     => function () {
					return true;
				},
				'get_admin_page_title' => function () {
					return 'PackRelay Settings';
				},
				'current_user_can'    => function () {
					return true;
				},
				'settings_fields'     => function () {},
				'do_settings_sections' => function () {},
				'esc_url'             => function ( $url ) {
					return $url;
				},
				'esc_js'              => function ( $text ) {
					return $text;
				},
				'get_the_ID'          => function () {
					return 0;
				},
				'get_the_title'       => function ( $post = 0 ) {
					return 'Test Page';
				},
				'get_permalink'       => function ( $post = 0 ) {
					return 'https://example.com/?p=' . $post;
				},
				'paginate_links'      => function () {
					return '';
				},
				'date_i18n'           => function ( $format, $timestamp = false ) {
					return gmdate( $format, $timestamp ? $timestamp : time() );
				},
				'number_format_i18n'  => function ( $number, $decimals = 0 ) {
					return number_format( $number, $decimals );
				},
				'_n'                  => function ( $single, $plural, $number ) {
					return ( 1 === $number ) ? $single : $plural;
				},
				'wp_redirect'         => function () {
					return true;
				},
				'check_admin_referer' => function () {
					return true;
				},
				'wp_unslash'          => function ( $value ) {
					return is_string( $value ) ? stripslashes( $value ) : $value;
				},
				'wp_enqueue_style'    => function () {},
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
