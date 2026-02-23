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

// Stub WP_List_Table class.
if ( ! class_exists( 'WP_List_Table' ) ) {
	class WP_List_Table {
		public $items = array();
		public $_column_headers = array();
		protected $_pagination_args = array();

		public function __construct( $args = array() ) {}

		public function get_columns() {
			return array();
		}

		public function get_pagenum() {
			return 1;
		}

		public function set_pagination_args( $args ) {
			$this->_pagination_args = $args;
		}

		public function row_actions( $actions, $always_visible = false ) {
			$action_count = count( $actions );
			if ( ! $action_count ) {
				return '';
			}

			$out = '<div class="row-actions">';
			$i   = 0;
			foreach ( $actions as $action => $link ) {
				++$i;
				$sep  = ( $i < $action_count ) ? ' | ' : '';
				$out .= "<span class='$action'>$link$sep</span>";
			}
			$out .= '</div>';

			return $out;
		}

		public function prepare_items() {}

		public function display() {}

		public function get_bulk_actions() {
			return array();
		}

		public function no_items() {}
	}
}

// Define WordPress constants.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// Create stub wp-admin/includes/upgrade.php if it doesn't exist.
$upgrade_dir = ABSPATH . 'wp-admin/includes';
if ( ! is_dir( $upgrade_dir ) ) {
	mkdir( $upgrade_dir, 0755, true );
}
if ( ! file_exists( $upgrade_dir . '/upgrade.php' ) ) {
	file_put_contents( $upgrade_dir . '/upgrade.php', "<?php\n// Stub for tests.\nif ( ! function_exists( 'dbDelta' ) ) {\n\tfunction dbDelta( \$queries = '', \$execute = true ) { return array(); }\n}\n" );
}

// Stub is_wp_error function.
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

// Load plugin files.
require_once __DIR__ . '/../includes/providers/class-packrelay-provider.php';
require_once __DIR__ . '/../includes/providers/class-packrelay-provider-divi.php';
require_once __DIR__ . '/../includes/providers/class-packrelay-provider-wpforms.php';
require_once __DIR__ . '/../includes/providers/class-packrelay-provider-gravityforms.php';
require_once __DIR__ . '/../includes/class-packrelay-provider-factory.php';
require_once __DIR__ . '/../includes/class-packrelay-loader.php';
require_once __DIR__ . '/../includes/class-packrelay-activator.php';
require_once __DIR__ . '/../includes/class-packrelay-deactivator.php';
require_once __DIR__ . '/../includes/class-packrelay-settings.php';
require_once __DIR__ . '/../includes/class-packrelay-appcheck.php';
require_once __DIR__ . '/../includes/class-packrelay-entry-store.php';
require_once __DIR__ . '/../includes/class-packrelay-entries-list-table.php';
require_once __DIR__ . '/../includes/class-packrelay-entries-page.php';
require_once __DIR__ . '/../includes/class-packrelay-rest-api.php';
require_once __DIR__ . '/../includes/class-packrelay.php';
