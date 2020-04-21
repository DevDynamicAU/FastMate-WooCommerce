<?php
/*
Class: Eiz_Couriers_API
Author: Eiz
Developer: Zeta Digital
Version: 0.1.0
Author URI: https://www.eiz.com.au
*/

if (!defined('WPINC')) {
    die;
}

if (!class_exists('Eiz_Couriers_API')) {
    class Eiz_Couriers_API
    {
        private static $accessToken = '';
        private static $api_url = "https://app.eiz.com.au/api/auth/woocommerce/APPgetCouriers";

        /**
         * @param null $token
         * @throws Exception
         */
        public static function init($token = null)
        {
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

        public static function getCouriers()
        {
            $url = self::$api_url;
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . self::$accessToken
                ),
                'method' => 'GET',
                'timeout' => 25
            ));
			
			//print_r($response['body']);exit;
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, the couriers are currently unavailable, please refresh the page or try again later");
                } else {
                    throw new Exception("Sorry, something went wrong with the couriers. If the problem persists, please contact us!");
                }
            } else {
                if ($response['response']['code'] == 200) {
                    $body = json_decode($response['body'], true);
                    return $body["data"]["carriers"];
                }
            }

            return array(); 
        }
    }
}