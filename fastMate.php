<?php
 
/**
 * Plugin Name: FastMate Shipping
 * Description: Custom Shipping Method for WooCommerce that supports Fastway Couriers.
 * Version: 1.0.0
 * Author: DevDynamic
 * Author URI: https://devdynamic.com.au
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: fastmate
 */
 
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	function fastMate_shipping_method() {
		if ( ! class_exists( 'FastMate_Shipping_Method' ) ) {
			class FastMate_Shipping_Method extends WC_Shipping_Method {
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct($instance_id = 0) {
					$this->id					= 'fastmate_shipping';
					$this->method_title			= __( 'FastMate Shipping', $this->id );
					$this->method_description	= __( 'Custom Shipping Method for WooCommerce that supports Fastway Couriers', $this->id );
					
					$this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
					$this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'FastMate Shipping', $this->id );

					$this->init();
				}
 
				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields();
					$this->init_settings();

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
					
					add_action( 'woocommerce_review_order_before_cart_contents', 'fastMate_validate_order' , 10 );
					add_action( 'woocommerce_after_checkout_validation', 'fastMate_validate_order' , 10 );
				
					add_filter('woocommerce_get_sections_shipping', array( $this, 'add_shipping_settings_section_tab') );
				}
 
				/**
				 * Define settings field for this shipping
				 * @return void 
				 */
				function init_form_fields() {
					$this->form_fields = array( 'enabled' => array(
													'title' => __( 'Enable', $this->id ),
													'type' => 'checkbox',
													'description' => __( 'Enable this shipping.', $this->id ),
													'default' => 'yes'
												),
												'title' => array(
													'title' => __( 'Title', $this->id ),
													'type' => 'text',
													'description' => __( 'Title to be display on site', $this->id ),
													'default' => __( 'Default Shipping', $this->id )
												),
												'flatRate' => array(
													'title' => __( 'Flat Rate', $this->id ),
													'type' => 'decimal',
													'description' => __( 'This is the default rate if the Fastway API returns no route, but you still want to give a shipping price', $this->id ),
													'default' => 13.95
												),
												'maxRate' => array(
													'title' => __( 'Max Freight Rate', $this->id ),
													'type' => 'decimal',
													'description' => __( 'This is the maximum freight that a customer is charged, regardless of what the Fastway API returns.', $this->id ),
													'default' => 13.95
												),
												'weight' => array(
													'title' => __( 'Weight (kg)', $this->id ),
													'type' => 'number',
													'description' => __( 'Maximum allowed weight. If the order is over this amount, then a message will be displayed on the checkout page', $this->id ),
													'default' => 100
												),
												'debugEnabled' => array(
													'title' => __( 'Enable Debug', $this->id ),
													'type' => 'checkbox',
													'description' => __( 'Enable debug mode. Used to display information to help in identifying issues.', $this->id ),
													'default' => 'no'
												),
										);
				}
 
				public function add_shipping_settings_section_tab( $section ) {
					$section[$this->id] = __('FastMate Shipping', $this->id);
		
					return $section;
				}

				function isDebugEnabled() {
					$getSetting = isset( $this->settings['debugEnabled'] ) ? $this->settings['debugEnabled'] : 'no';
					
					return $getSetting == 'yes';
				}

				public function showDebugNotice($msg, $msgType='notice') {
					if (! wc_has_notice($msg, $msgType) ) {
						wc_add_notice($msg, $msgType);
					}
				}

				/**
				 * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
				 * NOTE This function is used when the user gets to the cart page initially, but the hook woocommerce_package_rates is required to calc the cost
				 * when a user changes the address in the calculator, so we use that hook instead. Therefore, no price calculation is needed here as that hook
				 * also appears to run when the user gets to the cart page.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package = array() ) {
					// Add the rate to the list of enabled rates.
					// We just use a base rate of 0 as shipping_rate_cost_calculation() will calculate a new rate and update this one.
					$rate = array(
						'id' => $this->id,
						'label' => $this->settings['title'],
						'cost' => 0
					);

					$this->add_rate( $rate );
				}
			}
		}
	} // finish function fastMate_shipping_method
	
	add_action( 'woocommerce_shipping_init', 'fastMate_shipping_method' );

	function add_action_links ( $actions ) {
		//$myShippingMethod = new FastMate_Shipping_Method();
		//$pluginID = $myShippingMethod->id;

		$mylinks = array(
			'<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=fastmate_shipping') . '"> ' . __('Settings', 'fastmate_shipping') . '</a>',
		);

		$actions = array_merge( $actions, $mylinks );
		
		return $actions;
	}

	/**
	 * Function to add the shipping method into the list of shipping methods
	 */
	function add_fastmate_shipping_method( $methods ) {
		$myShippingMethod = new FastMate_Shipping_Method();
		// $methods[<method_id>] = <Class Name>
		$methods[$myShippingMethod->id] = 'FastMate_Shipping_Method';

		return $methods;
	}

	/**
	 * Function to validate that the packages in the order are OK to process.
	 */
	function fastmate_validate_order( $posted ) {
		$myShippingMethod = new FastMate_Shipping_Method();
		$pluginID = $myShippingMethod->id;

		if ($myShippingMethod->isDebugEnabled()) {
			$myShippingMethod->showDebugNotice('Running validate_order', 'notice');
		}

		// Get the packages
		$packages = WC()->shipping->get_packages();
 
		// Get the chosen shipping methods i.e. the ones from the shipping zones and any added by the plugin
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		
		// If the plugin shipping method is one of the chosen shipping methods, continue the validation
		if ( is_array( $chosen_methods ) && in_array( $pluginID, $chosen_methods ) ) {

			// Loop the packages in the order
			foreach ( $packages as $i => $package ) {

				// Finish this iteration of the loop if the chosen method is not the one for the plugin
				if ( $chosen_methods[ $i ] != $pluginID ) {
					continue;
				}

				// Get the weight limit setting from the plugin settings
				$weightLimit = (int) $myShippingMethod->settings['weight'];
				$weight = 0;
 
				// Get the package contents and calculate a total weight
				foreach ( $package['contents'] as $item_id => $values ) {
					$_product = $values['data'];
					$weight = $weight + $_product->get_weight() * $values['quantity'];
				}
 
				// Convert the weight into kg
				$weight = wc_get_weight( $weight, 'kg' );

				if ($myShippingMethod->isDebugEnabled() ) {
					$myShippingMethod->showDebugNotice('Total weight calculated is ' . $weight . 'kg(s)', 'notice');
				}

				// If the weight is too much, then display a notice
				if ( $weight > $weightLimit ) {
					$message = sprintf( __( 'Sorry, %d kg exceeds the maximum weight of %d kg for %s', $pluginID ), $weight, $weightLimit, $myShippingMethod->title );
					$messageType = "error";
 
					if ( ! wc_has_notice( $message, $messageType ) ) {
						wc_add_notice( $message, $messageType );
					}
				}
			} // end foreach
		}
	}

	/**
	 * Function to return a suburb that is per the 
	 */
	function getCorrectSuburb($suburb, $postCode) {
		// Aust Post API - yajPbjzaZdwAQBwAxqZ888wfJh2G0u5Z
		// Aust Post endpoint https://digitalapi.auspost.com.au/postcode/search.json
		//todo lookup the postcode and return the location details

		// Set the result to the same suburb as we had passed in. The switch below will only update it if the postcode is one that we need to update.
		$result = $suburb;
		$sthRockySuburbs = array(
			"Allenstown",
			"Depot Hill",
			"Fairy Bower",
			"Great Keppel Island",
			"Port Curtis",
			"Rockhampton",
			"Rockhampton Dc",
			"Rockhampton City",
			"Rockhampton Hospital",
			"The Keppels",
			"The Range",
			"Wandal",
			"West Rockhampton"
		);

		$nthRockySuburbs = array(
			"Berserker",
			"Cnetral Queensland University",
			"Frenchville",
			"Greenlake",
			"IronPot",
			"Kawana",
			"Koongal",
			"Lakes Creek",
			"Limestone Creek",
			"Mount Archer",
			"Nankin",
			"Nerimbera",
			"Norman Gardens",
			"Park Avenue",
			"Red Hill Rockhampton",
			"Red Hill",
			"Rockhampton Dc",
			"Rockhampton",
			"Rockyview",
			"Sandringham",
			"The Common"
		);

		// ques do we just check that the suburb is in the above lists?
		switch ($postCode) {
			case 4700:
					// The suburb being searched for is not a valid one for the region.
					// Set the returned suburb to be blank so the flat rate should apply.
					$result = in_array($suburb, $sthRockySuburbs) ? "Rockhampton" : "";
				break;

			case 4701:
					// The suburb being searched for is not a valid one for the region.
					// Set the returned suburb to be blank so the flat rate should apply.
					$result = in_array($suburb, $nthRockySuburbs) ? "Rockhampton Dc" : "";
				break;
		}

		return $result;
	}

	function shipping_rate_cost_calculation( $rates, $package ) {
		$myShippingMethod = new FastMate_Shipping_Method();
		$pluginID = $myShippingMethod->id;

		if ( $myShippingMethod->isDebugEnabled() ) {
			$myShippingMethod->showDebugNotice('running custom rate cost calc', 'notice');
		}

		// Get the flat rate and max rate from the settings
		$flatRate = floatval($myShippingMethod->settings['flatRate']);
		$maxRate = floatval($myShippingMethod->settings['maxRate']);
		$weight = 0;
		$cost = 0;
		
		$country = $package["destination"]["country"];
		$state = $package["destination"]["state"];
		$postCode = $package["destination"]["postcode"];
		$suburb = getCorrectSuburb($package["destination"]["city"], $postCode);
	
		$apiURL = 'https://au.api.fastway.org/v6/psc/lookup';
		$apiKey = '486087df4106f1bd5f83f44344c4240a'; // Simone 486087df4106f1bd5f83f44344c4240a, Peter 20bd734c0b64dba249b4aa7dcbd7ae7c
		$rfCode = 'CAP';
		$destPostcode = $postCode;
		$destSuburb = $suburb;
		
		if ( $myShippingMethod->isDebugEnabled() ) {
			$myShippingMethod->showDebugNotice('API Values :<br>Dest Country | ' . $country . '<br>State | ' . $state . '<br>PostCode | ' . $destPostcode . '<br>Suburb | ' . $destSuburb, 'notice');
		}
	
		$apiArgs = array(
			'api_key' => $apiKey,
			'RFCode' => $rfCode,
			'DestPostcode' => $destPostcode,
			'Suburb' => $destSuburb,
			'WeightInKg' => 1
		);
			
		try {
			$response = wp_remote_get($apiURL . '?' . http_build_query($apiArgs));
	
			if (! is_wp_error($response) ) {
				$responseBody = wp_remote_retrieve_body( $response );
			} else {
				wp_add_notice('There was an error calculating the shipping.');
				return;
			}
	
			$result = json_decode( $responseBody );
	
			$result = $result->result;
			
			$cheapestService = $result->cheapest_service;
			$apiCost = floatval($cheapestService[0]->totalprice_normal);
			$cost = $apiCost == 0 ? $flatRate : $apiCost;

			// If the cost we got is greater than the maximum we allow, use the max, else use the cost we calculated.
			$cost = $cost > $maxRate ? $maxRate : $cost;

			if ( $myShippingMethod->isDebugEnabled() ) {
				$myShippingMethod->showDebugNotice('Got a new cost of ' . $cost . ' from the API', 'notice');
			}

		} catch (Exception $e) {
			var_dump($e);
		}
	
		foreach( $rates as $rate_key => $rate ) {
			if ( $myShippingMethod->isDebugEnabled() ) {
				$myShippingMethod->showDebugNotice('Processing rate ' . $rate->method_id, 'notice');
			}

			// Only update the rate cost if the rate we are updating is the one for this plugin
			if ( $pluginID === $rate->method_id ) {
				$initial_cost = $rates[$rate_key]->cost;
	
				// Set Custom rate cost, set to 2 decimal places
				$rates[$rate_key]->cost = round($cost, 2);
	
				// Taxes rate cost (if enabled)
				$new_taxes = array();
				$has_taxes = false;

				foreach ( $rate->taxes as $key => $tax ) {
					if ( $tax > 0 ) {
						// Calculating the tax rate unit
						$tax_rate = $tax / $initial_cost;
						
						// Calculating the new tax cost
						$new_tax_cost = $tax_rate * $cost;
						
						// Save the calculated new tax rate cost in the array
						$new_taxes[$key] = round( $new_tax_cost, 2 );
						$has_taxes = true;
					}
				}

				// Set new tax rates cost (if enabled)
				if ( $has_taxes ) {
					$rate->taxes = $new_taxes;
				}
			} else {
				if($myShippingMethod->isDebugEnabled() ) {
					$myShippingMethod->showDebugNotice('Skipping ' . $rate->method_id . ' as it does not match ' . $pluginID, 'notice');
				}
			}
		}
	
		return $rates;
	}

	add_filter( 'woocommerce_shipping_methods', 'add_fastmate_shipping_method' );
	add_filter( 'woocommerce_package_rates', 'shipping_rate_cost_calculation', 10, 2 );

	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links' );
}