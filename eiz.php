<?php
/**
 * @package Eiz
 * @version 0.1.0
 */
/*
Plugin Name: Shipping rates for EIZ
Description: Ability to robustly obtain shipping rates from EIZ.
Author: DevDynamic
Original Developer: Zeta Digital
Version: 0.1.0
Author URI: https://devdynamic.com.au
*/

// Define the plugin version
define('EIZ_VERSION', '0.1.0');

// Check that we have Wordpress to work with. If not, then quit the plugin
if (!defined('WPINC')) {
	die;
}

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	if (!class_exists('WC_Integration_Eiz')) {
		class WC_Integration_Eiz {
			// Construct the plugin
			public function __construct() {
				// Add the plugin for when woocommerce shipping starts
				add_action('woocommerce_shipping_init', array($this, 'init'));

				// Add the plugin links to the woocommerce settings page
				add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
				include_once 'includes/eiz-shipping-quote-test.php';
				//add_action('admin_menu', array($this, 'quote_test_menu'));
			}

			// Initialize the plugin
			public function init() {
				add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

				if (class_exists('WC_Integration')) {
					// Include our integration class
					include_once 'includes/eiz-shipping.php';

					// Register the integration
					add_filter('woocommerce_shipping_methods', array($this, 'add_integration'));
				}
			}

			public function add_shipping_method($methods) {
				if (is_array($methods)) {
					$methods['eiz'] = 'Eiz_Shipping_Method';
				}
				
				return $methods;
			}

			// Add a new integration to WooCommerce
			public function add_integration($integrations) {
				$integrations[] = 'Eiz_Shipping_Method';
				
				return $integrations;
			}

			// Add Settings link to plugin page
			public function plugin_action_links($links) {
				return array_merge($links, array(
					'<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=eiz') . '"> ' . __('Settings', 'eiz') . '</a>',
					'<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=eiz') . '"> ' . __('User guide', 'eiz') . '</a>',
				));
			}

			// Add quote test to tools menu
			public function quote_test_menu() {
				add_submenu_page(
					'woocommerce',
					'Eiz Shipping Quote Test Tool',
					'Eiz Shipping Test',
					'edit_plugins',
					'eiz_shipping_quote_test',
					'eiz_shipping_quote_test'
				);
			}
		}

		$WC_Integration_Eiz = new WC_Integration_Eiz();
	}
}