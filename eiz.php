<?php
/**
 * @package Eiz
 * @version 0.1.0
 */
/*
Plugin Name: Eiz realtime shipping quote
Plugin URI: http://wordpress.org/plugins/eiz
Description: Eiz plugin for WooCommerce, get realtime check out shipping quote.
Author: Eiz Australia
Developer: Zeta Digital
Version: 0.1.0
Author URI: https://www.eiz.com.au
*/

define('EIZ_VERSION', '0.1.0');

if (!defined('WPINC')) {
    die;
}

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Integration_Eiz')) {
        class WC_Integration_Eiz
        {
            /**
             * Construct the plugin
             */
            public function __construct()
            {
                add_action('init', array($this, 'register_session'));
                add_action('woocommerce_shipping_init', array($this, 'init'));
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                include_once 'includes/eiz-shipping-quote-test.php';
                //add_action('admin_menu', array($this, 'quote_test_menu'));
            }

            /**
             *  Register a session
             */
            public function register_session()
            {
                if (!session_id())
                    session_start();
            }
            
            /**
             * Initialize the plugin
             */
            public function init()
            {
                add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
                if (class_exists('WC_Integration')) {
                    include_once 'includes/eiz-shipping.php'; // Include our integration class
                    add_filter('woocommerce_shipping_methods', array($this, 'add_integration')); // Register the integration
                }
            }
            
            public function add_shipping_method($methods)
            {
                if (is_array($methods)) {
                    $methods['eiz'] = 'Eiz_Shipping_Method';
                }
                return $methods;
            }

            /**
             * Add a new integration to WooCommerce
             */
            public function add_integration($integrations)
            {
                $integrations[] = 'Eiz_Shipping_Method';
                return $integrations;
            }
            
            /**
             *  Add Settings link to plugin page
             */
            public function plugin_action_links($links)
            {
                return array_merge($links, array(
                    '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=eiz') . '"> ' . __('Settings', 'eiz') . '</a>',
					'<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=eiz') . '"> ' . __('User guide', 'eiz') . '</a>',
                ));
            }

            /**
             *  Add quote test to tools menu
             */
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