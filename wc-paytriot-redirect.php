<?php
/*
 * Plugin Name: WooCommerce Paytriot Redirect
 * Plugin URI: https://github.com/wlady/
 * Description: WooCommerce Paytriot Redirect
 * Author: Vladimir Zabara <wlady2001@gmail.com>
 * Author URI: https://github.com/wlady/
 * Version: 1.2.6
 * Text Domain: wc-paytriot
 * Requires PHP: 7.4
 * Requires at least: 4.7
 * Tested up to: 6.0
 * WC requires at least: 3.0
 * WC tested up to: 6.2
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace WCPaytriotRedirect;


defined( 'ABSPATH' ) or exit;

include( dirname( __FILE__ ) . '/vendor/autoload.php' );

define( 'PAYTRIOT_REDIRECT_VERSION', '1.2.6' );
define( 'PAYTRIOT_REDIRECT_SUPPORT_PHP', '7.4' );
define( 'PAYTRIOT_REDIRECT_SUPPORT_WP', '5.0' );
define( 'PAYTRIOT_REDIRECT_SUPPORT_WC', '3.0' );
define( 'PAYTRIOT_REDIRECT_PLUGIN_NAME', plugin_basename( __FILE__ ) );
define( 'PAYTRIOT_REDIRECT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialise Paytriot Gateway
 **/
add_action( 'plugins_loaded', function () {

	if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) {
		return;
	}

	if ( isset( $_GET['sid'] ) ) {
		session_id( $_GET['sid'] );
	}

	session_start();

	add_filter( 'plugin_action_links', function ( $actions, $plugin_file ) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			$plugin = plugin_basename( __FILE__ );
		}

		if ( $plugin == $plugin_file ) {
			$actions = array_merge( [
				'settings' => '<a href="' .
				              admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paytriot_redirect' ) . '">' .
				              __( 'Settings', 'wc-paytriot' ) .
				              '</a>',
			], $actions );
		}

		return $actions;
	} , 10, 2 );

	add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
		$methods[] = 'WCPaytriotRedirect\PaytriotRedirect';

		return $methods;
	}, 0 );

});



