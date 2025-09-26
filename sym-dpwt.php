<?php
/**
 * Plugin Name:       SYM - DPWT
 * Plugin URI:        https://sevenyellowmonkeys.dk
 * Description:       Extra functionalities for DPWT Accommodation
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jan Eliasen
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sym-dpwt
 * Domain Path:       /languages
 *
 * @package SymDpwt
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SYM_DPWT_VERSION' ) ) {
    define( 'SYM_DPWT_VERSION', '0.1.0' );
}

if ( ! defined( 'SYM_DPWT_PLUGIN_FILE' ) ) {
    define( 'SYM_DPWT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'SYM_DPWT_PLUGIN_DIR' ) ) {
    define( 'SYM_DPWT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SYM_DPWT_PLUGIN_URL' ) ) {
    define( 'SYM_DPWT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Bootstraps the plugin.
 */
function sym_dpwt_bootstrap() {
    // TODO: Register hooks, load text domain, and include additional files here.
}

add_action( 'plugins_loaded', 'sym_dpwt_bootstrap' );
