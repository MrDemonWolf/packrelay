<?php
/**
 * PackRelay — Multi-Builder REST API Bridge
 *
 * @package    PackRelay
 * @author     MrDemonWolf
 * @copyright  2026 MrDemonWolf, Inc.
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: PackRelay
 * Plugin URI:  https://github.com/mrdemonwolf/packrelay
 * Description: Accept form submissions from external apps and mobile clients via REST API with Firebase App Check protection. Supports Divi, WPForms, and Gravity Forms.
 * Version:     1.1.0
 * Author:      MrDemonWolf, Inc.
 * Author URI:  https://mrdemonwolf.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: packrelay
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PACKRELAY_VERSION', '1.1.0' );
define( 'PACKRELAY_PLUGIN_FILE', __FILE__ );
define( 'PACKRELAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PACKRELAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PACKRELAY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once PACKRELAY_PLUGIN_DIR . 'vendor/autoload.php';

// Auto-update from GitHub releases.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$packrelay_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/mrdemonwolf/packrelay/',
	PACKRELAY_PLUGIN_FILE,
	'packrelay'
);
$packrelay_update_checker->getVcsApi()->enableReleaseAssets();

// Provider abstraction.
require_once PACKRELAY_PLUGIN_DIR . 'includes/providers/class-packrelay-provider.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/providers/class-packrelay-provider-divi.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/providers/class-packrelay-provider-wpforms.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/providers/class-packrelay-provider-gravityforms.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-provider-factory.php';

// Core classes.
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-loader.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-activator.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-deactivator.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-settings.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-appcheck.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-entry-store.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-entries-list-table.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-entries-page.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-divi-submissions.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay-rest-api.php';
require_once PACKRELAY_PLUGIN_DIR . 'includes/class-packrelay.php';

register_activation_hook( __FILE__, array( 'PackRelay_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PackRelay_Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return PackRelay
 */
function packrelay() {
	return PackRelay::get_instance();
}

packrelay()->run();
