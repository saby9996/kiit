<?php
/**
 * Plugin Name: Advanced Order Export For WooCommerce
 * Plugin URI: 
 * Description: Ultimate plugin to export WooCommerce sales
 * Author: AlgolPlus
 * Author URI: http://algolplus.com/
 * Version: 1.3.0
 * Text Domain: woocommerce-order-export
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2015 AlgolPlus LLC. (algol.plus@gmail.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package     woocommerce-order-export
 * @author      AlgolPlus LLC
 * @Category    Plugin
 * @copyright   Copyright (c) 2015 AlgolPlus LLC
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */
if ( !defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly
	
// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	// do 2nd check for Multisite !
	include_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	if ( ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {
		return;
	}
}

include 'classes/class-wc-order-export-admin.php';
include 'classes/class-wc-order-export-engine.php';
include 'classes/class-wc-order-export-data-extractor.php';

$wc_order_export = new WC_Order_Export_Admin();
register_activation_hook( __FILE__, array($wc_order_export,'install') );
register_deactivation_hook( __FILE__, array($wc_order_export,'uninstall') );

// fight with ugly themes which add empty lines
if ( $wc_order_export->must_run_ajax_methods() AND !ob_get_level() )
	ob_start();