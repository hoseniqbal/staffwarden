<?php
/**
 * Plugin Name:       StaffWarden: Staff Activity Log for WooCommerce
 * Plugin URI:        https://github.com/hoseniqbal/staffwarden
 * Description:       Records who changed prices, stock, orders, refunds, coupons, payment settings and user roles, with a clear timeline and refund emails.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Iqbal Hossen
 * Author URI:        https://profiles.wordpress.org/iqbal511138/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       staffwarden
 * WC requires at least: 7.6
 * WC tested up to:   11.3
 *
 * @package StaffWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STAFFWARDEN_VERSION', '1.0.0' );
define( 'STAFFWARDEN_DB_VERSION', '1' );
define( 'STAFFWARDEN_FILE', __FILE__ );
define( 'STAFFWARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'STAFFWARDEN_URL', plugin_dir_url( __FILE__ ) );

require_once STAFFWARDEN_PATH . 'includes/class-staffwarden-settings.php';
require_once STAFFWARDEN_PATH . 'includes/class-staffwarden-log.php';
require_once STAFFWARDEN_PATH . 'includes/class-staffwarden-features.php';
require_once STAFFWARDEN_PATH . 'includes/class-staffwarden-admin.php';
require_once STAFFWARDEN_PATH . 'includes/class-staffwarden-plugin.php';

register_activation_hook( __FILE__, array( 'StaffWarden_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'StaffWarden_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'StaffWarden_Plugin', 'init' ) );
