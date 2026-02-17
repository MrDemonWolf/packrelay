<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

// Define WordPress constants used by the plugin.
define( 'ABSPATH', __DIR__ . '/../' );
define( 'PACKRELAY_VERSION', '1.0.0' );
define( 'PACKRELAY_PLUGIN_FILE', __DIR__ . '/../packrelay.php' );
define( 'PACKRELAY_PLUGIN_DIR', __DIR__ . '/../' );
define( 'PACKRELAY_PLUGIN_URL', 'https://example.com/wp-content/plugins/packrelay/' );
define( 'PACKRELAY_PLUGIN_BASENAME', 'packrelay/packrelay.php' );

// Load Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Stub WP_Error class.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors  = array();
		public $code    = '';
		public $message = '';

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			if ( $code ) {
				$this->errors[ $code ][] = $message;
			}
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

// Stub WP_REST_Response class.
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public $headers = array();

		public function __construct( $data = null, $status = 200, $headers = array() ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status() {
			return $this->status;
		}

		public function set_headers( $headers ) {
			$this->headers = array_merge( $this->headers, $headers );
		}
	}
}

// Stub WP_REST_Request class.
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params  = array();
		private $headers = array();
		private $route   = '';

		public function __construct( $method = 'GET', $route = '' ) {
			$this->route = $route;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function set_header( $key, $value ) {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		public function get_header( $key ) {
			return $this->headers[ strtolower( $key ) ] ?? null;
		}

		public function get_route() {
			return $this->route;
		}

		public function get_json_params() {
			return $this->params;
		}
	}
}

// Stub WP_HTTP_Response class.
if ( ! class_exists( 'WP_HTTP_Response' ) ) {
	class WP_HTTP_Response {
		public $data;
		public $status;

		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
	}
}

// Stub WP_REST_Server class.
if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE   = 'GET';
		const CREATABLE  = 'POST';
		const EDITABLE   = 'POST, PUT, PATCH';
		const DELETABLE  = 'DELETE';
		const ALLMETHODS  = 'GET, POST, PUT, PATCH, DELETE';
	}
}

// Load plugin files.
require_once __DIR__ . '/../includes/class-packrelay-loader.php';
require_once __DIR__ . '/../includes/class-packrelay-activator.php';
require_once __DIR__ . '/../includes/class-packrelay-deactivator.php';
require_once __DIR__ . '/../includes/class-packrelay-settings.php';
require_once __DIR__ . '/../includes/class-packrelay-recaptcha.php';
require_once __DIR__ . '/../includes/class-packrelay-entry.php';
require_once __DIR__ . '/../includes/class-packrelay-notification.php';
require_once __DIR__ . '/../includes/class-packrelay-rest-api.php';
require_once __DIR__ . '/../includes/class-packrelay.php';
