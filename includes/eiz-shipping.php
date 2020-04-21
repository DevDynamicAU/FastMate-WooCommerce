<?php
/*
Class: Eiz_Shipping_Method
Author: Eiz
Developer: Zeta Digital
Version: 0.1.0
Author URI: https://www.eiz.com.au
*/

if (!class_exists('Eiz_Shipping_Method')) {
    class Eiz_Shipping_Method extends WC_Shipping_Method
    {
        protected $token;

        /**
         * Constructor for shipping class
         *
         * @access public
         * @param int $instance_id
         */
        public function __construct($instance_id = 0)
        {
            $this->id = 'eiz';
            $this->instance_id = empty($instance_id) ? 99 : absint($instance_id);
            $this->method_title = __('EIZ realtime shipping quote', 'eiz');
            $this->method_description = __('Dynamic shipping rates at checkout, by <a href="https://www.eiz.com.au" target="_blank">EIZ</a>.<br> You can test Eiz Shipping Quote <a href="' . admin_url('admin.php?page=eiz_shipping_quote_test') . '"> here</a>', 'eiz');
            $this->supports = array(
                'shipping-zones',
                'settings',
                'instance-settings',
                'instance-settings-modal',
            );
            $this->init();
            $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Eiz', 'eiz');

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init()
        {
            // Load the settings
            $this->init_form_fields();
            $this->init_settings();
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_action('update_option_woocommerce_eiz_settings', array($this, 'clear_session'), 10, 2);
            add_action('woocommerce_update_options_shipping_eiz', array($this, 'saveOptions'));
        }

        public static function add_settings_tab($settings_tabs)
        {
            $settings_tabs['shipping&section=eiz'] = __('Eiz', 'eiz-shipping');
            return $settings_tabs;
        }

        /**
         * Clear session when option save
         *
         * @access public
         * @return void
         */
        public function clear_session($old_value, $new_value)
        {
            $_SESSION['access_token'] = null;
        }

        public function saveOptions()
        {
            $option_key = 'woocommerce_eiz_settings';
            $token_option = $this->get_token()['name'];

            $value = get_option($option_key);
            if (!empty($value)) {
                $value = unserialize($value);
            } else {
                $value = [];
            }

            if (isset($_POST['woocommerce_eiz_' . $token_option])) {
                update_option($token_option, $_POST['woocommerce_eiz_' . $token_option]);
                $value['woocommerce_eiz_' . $token_option] = $_POST['woocommerce_eiz_' . $token_option];
            }

            $add_up_mode_option = 'woocommerce_eiz_add_up_mode';
            if (isset($_POST[$add_up_mode_option])) {
                $value[$add_up_mode_option] = $_POST[$add_up_mode_option];
            }

            $add_up_value_option = 'woocommerce_eiz_add_up_value';
            if (isset($_POST[$add_up_value_option])) {
                $value[$add_up_value_option] = $_POST[$add_up_value_option];
            }

            $skip_couriers = 'woocommerce_eiz_skip_couriers';
            if (isset($_POST[$skip_couriers])) {
                $value[$skip_couriers] = $_POST[$skip_couriers];
            }

            update_option($option_key, serialize($value));
        }

        /**
         * Notification when access token is not set
         *
         * @access public
         * @return void
         */
        public function eiz_admin_notice()
        {
            $token = 'eiz_access_token_' . get_current_network_id();
            if ((get_option($token) == '')) {
                echo '<div class="error">Please go to <bold>WooCommerce > Settings > Shipping > Eiz</bold> to add your Access Token </div>';
            }
        }

        /**
         * Define settings field for this shipping
         * @return void
         */
        function init_form_fields()
        {
            $token = $this->get_token();
            $token_fields = [];

            $token_fields[$token['name']] = [
                'title' => __('Eiz Access Token', 'eiz-shipping'),
                'type' => 'text',
                'description' => __('Enter your Eiz Access Token.', 'eiz-shipping'),
                'desc_tip' => true,
                'default' => $token['value']
            ];
			
			
            if (isset($token['value']) && $token['value'] != ''){

				if (!class_exists('Eiz_Check_API')) {
					include_once 'eiz-check-api.php'; // Include Eiz Couriers API
				}
				
                $token = $this->get_token();
                Eiz_Check_API::init($token['value']);
                $response = Eiz_Check_API::check();
				
				if(!empty($response->status_code) && $response->status_code == 400){
					$this->form_fields = array_merge(
						array(
							'skip_couriers' => array(
								'title' => __( 'Subscribe', 'eiz-subscribe' ),
								'type' => 'subscribe'
							)
						),
						$this->form_fields
					); 
				}else{
					if(!empty($response->data->html)){
						$this->form_fields = array_merge(
							array(
								'eiz-notice' => array(
									'title' => __( 'notice', 'eiz-notice' ),
									'type' => 'notice',
									'html' => $response->data->html
								)
							),
							$this->form_fields
						); 
					}
					$this->form_fields = array_merge(
						array(
							'add_up_mode' => array(
								'title' => __('Add-up Mode', 'eiz-shipping'),
								'desc' => __('This option let you add amount or percentage to shipping price', 'eiz-shipping'),
								'type' => 'select',
								'class' => 'wc-enhanced-select',
								'css' => 'min-width: 350px;',
								'desc_tip' => true,
								'options' => array(
									'none' => __('None', 'eiz-shipping'),
									'percentage' => __('Percentage', 'eiz-shipping'),
									'fixed_amount'   => __('Fixed Amount', 'eiz-shipping')
								),
								'default' => isset(unserialize(get_option('woocommerce_eiz_settings'))['woocommerce_eiz_add_up_mode']) ? unserialize(get_option('woocommerce_eiz_settings'))['woocommerce_eiz_add_up_mode'] : 'none'
							),
							'add_up_value' => array(
								'title' => __('Add-up Value', 'eiz-shipping'),
								'type' => 'decimal',
								'description' => __('Enter your Add-up value. ', 'eiz-shipping'),
								'desc_tip' => true,
								'default' => isset(unserialize(get_option('woocommerce_eiz_settings'))['woocommerce_eiz_add_up_value']) ? unserialize(get_option('woocommerce_eiz_settings'))['woocommerce_eiz_add_up_value'] : ''
							),
							'skip_couriers' => array(
								'title' => __( 'Couriers', 'eiz-couriers' ),
								'type' => 'couriers'
							)
						),
						$this->form_fields
					);
					$this->form_fields = array_merge($token_fields, $this->form_fields);
				}
            }else{
				$this->form_fields = array_merge([
					'skip_couriers' => array(
						'title' => __( 'Login', 'eiz-login' ),
						'type' => 'login'
					),
					'eiz_access_token' => [
						//'title' => __('Eiz Access Token', 'eiz-shipping'),
						'type' => 'hidden',
						//'description' => __('Enter your Eiz Access Token.', 'eiz-shipping'),
						'desc_tip' => false,
						'default' => $token['value']
					]
				], $this->form_fields);
			}

            
        }
		
		public function generate_login_html($key, $data){
            $defaults = array(
                'class' => '',
                'css' => '',
                'custom_attributes' => array(),
                'desc_tip' => true,
                'description' => 'Configure couriers availibity',
                'title' => 'Couriers',
            );
            $data = wp_parse_args($data, $defaults);
			ob_start();
            ?>
				<div style="border: 1px solid black; padding: 5px;">
					<h4>Login into eiz.com.au</h4>
					<p>To be able to use EIZ realtime shipping quote, you need to sign into your EIZ account. If you are new to EIZ, feel free to <a target="_blank" href="https://eiz.com.au">sign-up</a> an EIZ account for free and set up your carrier integration.</p>
					<b>Username:</b> <input type="text" id="eiz_username" /><br/><br/>
					<b>Password:&nbsp;</b> <input type="password" id="eiz_password" /><br/>
					<p>
						<a style="padding: 5px; background-color: grey; color: white; width: 50px; cursor: pointer;" onclick="loginEiz()">Login</a> <a target="_blank" href="https://eiz.com.au">Register EIZ account for free</a>
					</p>
				</div>
				<script>  
					jQuery(document).ready(function(){  
						 
					 });
					 
					function loginEiz(){
						var username = jQuery('#eiz_username').val();
						var password = jQuery('#eiz_password').val();
						jQuery.ajax({
							url: 'https://app.eiz.com.au/api/auth/auth',
							type: 'post',
							data: {
							  "email": username,
							  "password": password
							},
							dataType: 'json',
							success: function (data) {
								jQuery('#woocommerce_eiz_eiz_access_token').val(data.data.token);
								jQuery('button[name="save"]').click();
							}
						});
					}
				 </script>
            <?php
            return ob_get_clean();
		}
		
		public function generate_subscribe_html($key, $data){
            $defaults = array(
                'class' => '',
                'css' => '',
                'custom_attributes' => array(),
                'desc_tip' => true,
                'description' => 'Configure couriers availibity',
                'title' => 'Couriers',
            );
            $data = wp_parse_args($data, $defaults);
			ob_start();
            ?>
				<div style="border: 1px solid black; padding: 5px;">
					<h4>Subscribe WooCommerce plugin on EIZ to enable service, it is FREE.</h4>
					<p>Thanks for choosing EIZ. EIZ provide both freight management and order management for you woocommerce. The next thing to do is to subscribe <a target="_blank" href="https://eiz.com.au/app/app/plugin/woocommerce/detail">sign-up</a> WooCommerce in your EIZ account. It is FREE.</p>
					<p>
						<a style="padding: 5px; background-color: grey; color: white; width: 50px; cursor: pointer;" target="_blank" href="https://eiz.com.au/app/app/plugin/woocommerce/detail">Go to subscribe page</a> 
					</p>
				</div>
				<script>  
					jQuery(document).ready(function(){  
						  
					 });
				 </script>
            <?php
            return ob_get_clean();
		}
		
		public function generate_notice_html($key, $data){
            $defaults = array(
                'class' => '',
                'css' => '',
                'custom_attributes' => array(),
                'desc_tip' => true,
                'description' => 'Configure couriers availibity',
                'title' => 'Couriers',
            );
            $data = wp_parse_args($data, $defaults);
			ob_start();
			echo $data['html'];
            return ob_get_clean();
		}

        /**
         * Generate Couriers Settings HTML
         */
        public function generate_couriers_html($key, $data) {
            $defaults = array(
                'class' => '',
                'css' => '',
                'custom_attributes' => array(),
                'desc_tip' => true,
                'description' => 'Configure couriers availibity',
                'title' => 'Couriers',
            );
            $data = wp_parse_args($data, $defaults);

            // Get Couriers Settings from API
            if (!class_exists('Eiz_Couriers_API')) {
                include_once 'eiz-couriers-api.php'; // Include Eiz Couriers API
            }
            try {
                $token = $this->get_token();
                Eiz_Couriers_API::init($token['value']);
                $couriers = Eiz_Couriers_API::getCouriers();
            } catch (Exception $e) {
                $couriers = array();
            }

            $field = $this->plugin_id . $this->id . '_' . $key;
            $all_couriers = [];
			
			$activeCourier = [];
			$inActiveCourier = [];
            foreach($couriers as $courier){
				if($courier['subscribed']){
					$activeCourier[] = $courier;
				}else{
					$inActiveCourier[] = $courier;
				}
                if (isset($courier['shippingMethods'])){
                    foreach($courier['shippingMethods'] as $method){
                        $all_couriers[] = $method['id'];
                    }                 
                }
            }
            $skipped_couriers = isset(unserialize(get_option('woocommerce_eiz_settings'))[$field]) ? unserialize(get_option('woocommerce_eiz_settings'))[$field] : $all_couriers;
			

            // Generate Couriers Settings HTML
            ob_start();
            ?>
            <style>
                .couriers-table {
                    border-collapse: collapse;
                }

                .couriers-table th, .couriers-table td {
                    border: 2px solid grey;
                    padding: 15px;
                    text-align: left;
                    height: 1px;
                }
                .container {
                    width: 100%;
                    height: 100%;
                    display: table;
                }
                .container .row {
                    display: table-row;
                }
                .container .cell {
                    display: table-cell;
                }
                .logo .cell {
                    vertical-align: top;
                }
                .selection .cell {
                    vertical-align: middle;
                }
                .action .cell {
                    text-align: center;
                    vertical-align: bottom;
                }
                .activate {
                    box-shadow:inset 0px 1px 0px 0px #cf866c;
                    background:linear-gradient(to bottom, #d0451b 5%, #bc3315 100%);
                    background-color:#d0451b;
                    border-radius:3px;
                    border:1px solid #942911;
                    display:inline-block;
                    cursor:pointer;
                    color:#ffffff;
                    font-family:Arial;
                    font-size:13px;
                    padding:6px 24px;
                    text-decoration:none;
                    text-shadow:0px 1px 0px #854629;
                }
                .activate:hover {
                    background:linear-gradient(to bottom, #bc3315 5%, #d0451b 100%);
                    background-color:#bc3315;
                }
                .configure {
                    box-shadow:inset 0px 1px 0px 0px #91b8b3;
                    background:linear-gradient(to bottom, #768d87 5%, #6c7c7c 100%);
                    background-color:#768d87;
                    border-radius:3px;
                    border:1px solid #566963;
                    display:inline-block;
                    cursor:pointer;
                    color:#ffffff;
                    font-family:Arial;
                    font-size:13px;
                    padding:6px 24px;
                    text-decoration:none;
                    text-shadow:0px 1px 0px #2b665e;
                }
                .configure:hover {
                    background:linear-gradient(to bottom, #6c7c7c 5%, #768d87 100%);
                    background-color:#6c7c7c;
                }
            </style>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                    <?php echo $this->get_tooltip_html($data); ?>
                </th>
                <td class="forminp">
                    <table class='couriers-table'>
                    <?php for ($i = 0; $i < count($activeCourier); $i++) { 
                            if ($i % 4 == 0) {
                                echo "<tr></tr>";
                            }
                    ?>
                    <td>
                        <div class="container">
                            <div class="row logo">
                                <div class="cell">
                                    <img style="width: 180px; height: 60px;" src="<?php echo $activeCourier[$i]['cover']; ?>" />
                                </div>
                            </div>
                            <div class="row selections">
                                <div class="cell">
                                <?php 
                                if (isset($activeCourier[$i]['shippingMethods'])) { 
                                    foreach ($activeCourier[$i]['shippingMethods'] as $method) { ?>
                                        <p><input type="checkbox" <?php if (in_array($method['id'], $skipped_couriers)) echo 'checked' ?> name="woocommerce_eiz_skip_couriers[]" value="<?php echo $method['id']; ?>"> <?php echo $method['name']; ?> </p>
                                    <?php 
                                    } 
                                } ?>  
                                <br>                                  
                                </div>
                            </div>
                            <div class="row action">
                                <div class="cell">
                                    <?php if ($activeCourier[$i]['subscribed']){ ?>
                                        <a class='configure' href="https://eiz.com.au/app/app/account/settings/couriers/<?php echo $activeCourier[$i]['name'] ?>" target='_blank'>Configure Courier</a>
                                    <?php } else { ?>
                                        <a class='activate' href="https://eiz.com.au/app/app/account/settings/couriers/<?php echo $activeCourier[$i]['name'] ?>" target='_blank'>+ Activate Courier</a>
                                    <?php } ?>  
                                </div>
                            </div>
                        </div>
                    </td>
                    <?php
                    } ?>
                    </table>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field); ?>">More carriers</label>
                </th>
                <td class="forminp">
                    <table class='couriers-table'>
                    <?php for ($i = 0; $i < count($inActiveCourier); $i++) { 
                            if ($i % 4 == 0) {
                                echo "<tr></tr>";
                            }
                    ?>
                    <td>
                        <div class="container">
                            <div class="row logo">
                                <div class="cell">
                                    <img style="width: 180px; height: 60px;" src="<?php echo $inActiveCourier[$i]['cover']; ?>" />
                                </div>
                            </div>
                            <div class="row selections">
                                <div class="cell">
                                <?php 
                                if (isset($inActiveCourier[$i]['shippingMethods'])) { 
                                    foreach ($inActiveCourier[$i]['shippingMethods'] as $method) { ?>
                                        <p><?php echo $method['name']; ?> <input type="checkbox" <?php if (in_array($method['id'], $skipped_couriers)) echo 'checked' ?> name="woocommerce_eiz_skip_couriers[]" value="<?php echo $method['id']; ?>"></p>
                                    <?php 
                                    } 
                                } ?>  
                                <br>                                  
                                </div>
                            </div>
                            <div class="row action">
                                <div class="cell">
                                    <?php if ($inActiveCourier[$i]['subscribed']){ ?>
                                        <a class='configure' href="<?php echo $inActiveCourier[$i]['url'] ?>" target='_blank'>Configure Courier</a>
                                    <?php } else { ?>
                                        <a class='activate' href="https://eiz.com.au/app/app/plugin/<?php echo $inActiveCourier[$i]['name'] ?>/detail" target='_blank'>+ Activate Courier</a>
                                    <?php } ?>  
                                </div>
                            </div>
                        </div>
                    </td>
                    <?php
                    } ?>
                    </table>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        /**
         * @return array
         */
        protected function get_token()
        {
            if (!empty($this->token)) {
                return $this->token;
            }

            $token = 'eiz_access_token_' . get_current_network_id();
            if (!get_option($token) && !$this->get_option($token)) {
                $token = 'eiz_access_token';
            }

            $this->token = ['name' => $token, 'value' => $this->get_option($token) ?: get_option($token)];
            return $this->token;
        }

        /**
         * This function is used to calculate the shipping cost
         *
         * @access public
         * @param mixed $package
         * @return void
         */
        public function calculate_shipping($package = array())
        {
			
            $destination = $package["destination"];
            $items = array();
            $product_factory = new WC_Product_Factory();

            foreach ($package["contents"] as $key => &$item) {
                // Assume it is simple product
                $product = $product_factory->get_product($item["product_id"]);
                // Check version
                if (WC()->version < '2.7.0') {
                    // If this item is variation, get variation product instead
                    if ($item["data"]->product_type == "variation") {
                        $product = $product_factory->get_product($item["variation_id"]);
                    }
                    // Exclude virtual and downloadable product
                    if ($item["data"]->virtual == "yes") {
                        continue;
                    }
                } else {
                    if ($item["data"]->get_type() == "variation") {
                        $product = $product_factory->get_product($item["variation_id"]);
                    }
                    if ($item["data"]->get_virtual() == "yes") {
                        continue;
                    }
                }
                
                $items[] = array(
                    "weight" => $this->weightToKg($product->get_weight()),
                    "height" => $this->defaultDimension($this->dimensionToCm($product->get_height())),
                    "width" => $this->defaultDimension($this->dimensionToCm($product->get_width())),
                    "length" => $this->defaultDimension($this->dimensionToCm($product->get_length())),
                    'qty' => $item["quantity"]
                );
				
				$item['product'] = [
                    "weight" => $this->weightToKg($product->get_weight()),
                    "height" => $this->defaultDimension($this->dimensionToCm($product->get_height())),
                    "width" => $this->defaultDimension($this->dimensionToCm($product->get_width())),
                    "length" => $this->defaultDimension($this->dimensionToCm($product->get_length())),
					'shippingClass' => empty($product->get_shipping_class())?'individualPackage':$product->get_shipping_class(),
				];
            }
			
			//print_r(json_encode($package));exit;

            if (!class_exists('Eiz_Shipping_API')) {
                include_once 'eiz-shipping-api.php'; // Include Eiz Shipping API
            }

            try {
                $token = $this->get_token();
                Eiz_Shipping_API::init($token['value']);
                $perferred_rates = Eiz_Shipping_API::getShippingRate($package);
				//print_r($perferred_rates);exit;
            } catch (Exception $e) {
                $perferred_rates = array();
            }
            $eiz_settings = unserialize(get_option('woocommerce_eiz_settings'));
            foreach ($perferred_rates as $rate) {
                if (!in_array($rate["shippingMethodId"], $eiz_settings['woocommerce_eiz_skip_couriers'])){
                    continue;
                }
                $add_up_mode = $eiz_settings['woocommerce_eiz_add_up_mode'];
                $amount = $rate["amount"];
                if (isset($add_up_mode) && $add_up_mode != 'none'){
                    $add_up_value = (float)$eiz_settings['woocommerce_eiz_add_up_value'];
                    if ($add_up_value > 0){
                        if ($add_up_mode == 'percentage'){
                            $amount = round($amount * (1 + $add_up_value / 100), 2);
                        }
                        else if ($add_up_mode == 'fixed_amount'){
                            $amount += round($add_up_value, 2);
                        }
                    }
                }
                $shipping_rate = array(
                    'id' => $rate["shippingMethodId"],
                    'label' => $rate["serviceProvider"] . ' - ' . $rate["displayName"],
                    'cost' => $amount,
                    'meta_data' => array(
                        'Shipping Method ID' => $rate["shippingMethodId"] . "(" . $rate['indexName'] . ")",
                        'Service Provider' => $rate["serviceProvider"],
                        'Group Name' => $rate["groupName"],
                    )
                );

                $this->add_rate($shipping_rate);
            }
        }

        /**
         * This function converts weight to kg
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function weightToKg($weight)
        {
            $weight_unit = get_option('woocommerce_weight_unit');
            if ($weight_unit != 'kg') {
                if ($weight_unit == 'g') {
                    return $weight * 0.001;
                } else if ($weight_unit == 'lbs') {
                    return $weight * 0.453592;
                } else if ($weight_unit == 'oz') {
                    return $weight * 0.0283495;
                }
            }

            return $weight;
        }

        /**
         * This function converts dimension to cm
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function dimensionToCm($length)
        {
            $dimension_unit = get_option('woocommerce_dimension_unit');
            if ($dimension_unit != 'cm') {
                if ($dimension_unit == 'm') {
                    return $length * 100;
                } else if ($dimension_unit == 'mm') {
                    return $length * 0.1;
                } else if ($dimension_unit == 'in') {
                    return $length * 2.54;
                } else if ($dimension_unit == 'yd') {
                    return $length * 91.44;
                }
            }

            return $length;
        }

        /**
         * This function converts default value to 1 if it is 0 or negative
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function defaultDimension($length)
        {
            return $length > 0 ? $length : 1;
        }
    }
}