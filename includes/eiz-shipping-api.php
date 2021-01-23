<?php
/*
Class: Eiz_Shipping_API
Author: DevDynamic
Original Developer: Zeta Digital
Version: 0.1.0
Author URI: https://devdynamic.com.au
*/

if (!defined('WPINC')) {
	die;
}

if (!class_exists('Eiz_Shipping_API')) {
	class Eiz_Shipping_API {
		private static $accessToken = '';
		//private static $api_url = "https://app.eiz.com.au/api/auth/fulfillments/v2/quote";
		private static $api_url = "https://app.eiz.com.au/api/auth/woocommerce/APPgetQuote";

		/**
	 	 * @param null $token
		 * @throws Exception
		*/
		public static function init($token = null) {
			// todo get this function from a common file, as its repeated
			if (!is_null($token)) {
				self::$accessToken = trim(esc_attr($token), " ");
			} else {
				$token_option_name = 'eiz_access_token_' . get_current_network_id();
				self::$accessToken = trim(esc_attr(get_option($token_option_name)), " ");
			}

			if (self::$accessToken == '') {
				throw new Exception('Missing Access Token!');
			}
		}

		public static function getShippingRate($package) {
			//print_r($package["contents"]);
			$url = self::$api_url;
			$requestArray1 = array(
				"shipFrom" => array(
					"from_suburb" => get_option('woocommerce_store_city'),
					"from_postcode" => get_option('woocommerce_store_postcode'),
					"from_country" => explode(":", get_option('woocommerce_default_country'))[0]
				),
				"shipTo" => array(
					"shipTo_suburb" => isset($package['destination']['city']) ? $package['destination']['city'] : '',
					"shipTo_postcode" => ($package['destination']["postcode"] == '') ? 0 : $package['destination']["postcode"],
					"shipTo_country" => $package['destination']["country"]
				),
				"parcels" => $items
			);
			
			$requestArray = [
				'package' => $package,
				'store' => [
					'shopUrl' => get_permalink( woocommerce_get_page_id( 'shop' ) ),
					"from_suburb" => get_option('woocommerce_store_city'),
					"from_postcode" => get_option('woocommerce_store_postcode'),
					"from_country" => explode(":", get_option('woocommerce_default_country'))[0],
				],
			];

			$response = wp_remote_post($url, array(
				'headers' => array(
					'content-type' => 'application/json',
					'Authorization' => 'Bearer ' . self::$accessToken
				),
				'body' => json_encode($requestArray),
				'method' => 'POST',
				'timeout' => 25
			));
			
			//print_r($response['body']);

			if (is_wp_error($response)) {
				$error_message = $response->get_error_message();
				
				if ($error_message == 'fsocket timed out') {
					throw new Exception("Sorry, the shipping rates are currently unavailable, please refresh the page or try again later");
				} else {
					throw new Exception("Sorry, something went wrong with the shipping rates. If the problem persists, please contact us!");
				}
			} else {
				if ($response['response']['code'] == 200) {
					$body = json_decode($response['body'], true);
					
					return $body["data"]['data']["availableQuotes"];
				}
			}

			return array(); 
		}
	}
}