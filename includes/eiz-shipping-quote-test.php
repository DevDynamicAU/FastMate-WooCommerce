<?php
function eiz_shipping_quote_test() {
	?>
		<div class="wrap">
			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
			<br />
			<div class="form-group">  
				<form name="shipping_quote" id="shipping_quote" action="" method="post">
					<input type="hidden" name="quote_test_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>" />

					<table class="table table-bordered" id="dynamic_field">  
						<tr>
							<td><label style="width:150px; display:inline-block;">Destination Suburb</label></td> 
							<td><input style="width:200px;" type="text" name="suburb" placeholder="Enter destination suburb" class="form-control" required/></td>
						</tr>
						<tr>
							<td><label style="width:150px; display:inline-block;">Destination Postcode</label></td> 
							<td><input style="width:200px;" type="text" name="postcode" placeholder="Enter destination postcode" class="form-control" required/></td>  
						</tr>
						<tr>
							<td><label style="width:50px; display:inline-block;">Item 1</label></td> 
							<td><input style="width:200px;" type="number" step="0.01" name="weights[]" placeholder="Enter item weight in kg" class="form-control" required/></td>  
							<td><button type="button" name="add" id="add" class="button button-danger">Add Item</button></td>
							<br>
						</tr>
					</table>
					<br>
					<input type="submit" name="submit" class="button button-primary" value="Get Quote" />
				</form>
			</div>
		</div>
		<script>
			jQuery(document).ready(function(){  
				var i = 1;  
				jQuery('#add').click(function(){  
					i++;
					jQuery('#dynamic_field').append('<tr id="row' + i + '"><td><label style="width:50px; display:inline-block;">Item ' + i + '</label></td> <td><input style="width:200px;" type="number" step="0.01" name="weights[]" placeholder="Enter item weight in kg" class="form-control weight_list" required/></td><td><button type="button" name="remove" id="' + i + '" class="btn btn-danger btn_remove">X</button></td></tr><br>');  
				});

				jQuery(document).on('click', '.btn_remove', function(){  
					var button_id = jQuery(this).attr("id");   
					jQuery('#row'+button_id+'').remove();  
				});
			});
		 </script>
	<?php

	if(key_exists('quote_test_nonce', $_POST)) {
		if (wp_verify_nonce($_POST['quote_test_nonce'], basename(__FILE__))){
			$weights = sanitize_text_field($_POST['weights']);
			$total_weight = 0;

			foreach ($weights as $weight) {
				$total_weight += $weight;
			}

			if (!class_exists('Eiz_Shipping_API')) {
				include_once 'eiz-shipping-api.php';
			}

			try {
				Eiz_Shipping_API::init(get_token());
				
				$destination = [
					'city' => sanitize_text_field($_POST['suburb']),
					'postcode' => sanitize_text_field($_POST['postcode']),
					'country' => 'AU'
				];

				$items = [['weight' => $total_weight, 'qty' => 1, 'length' => 0, 'height' => 0, 'width' => 0]];
				$rates = Eiz_Shipping_API::getShippingRate($destination, $items);
			} catch (Exception $e) {
				$rates = array();
			}

			$eiz_settings = unserialize(get_option('woocommerce_eiz_settings'));
			echo'<h2>Eiz Shipping Quote</h2>';
			
			for ($i = 0; $i < count($rates); $i++){
				if (!in_array($rates[$i]["shippingMethodId"], $eiz_settings['woocommerce_eiz_skip_couriers'])){
					continue;
				}

				$add_up_mode = $eiz_settings['woocommerce_eiz_add_up_mode'];
				$amount = $rates[$i]["amount"];

				if (isset($add_up_mode) && $add_up_mode != 'none') {
					$add_up_value = (float)$eiz_settings['woocommerce_eiz_add_up_value'];
					if ($add_up_value > 0) {
						if ($add_up_mode == 'percentage') {
							$amount = round($amount * (1 + $add_up_value / 100), 2);
						} else if ($add_up_mode == 'fixed_amount') {
							$amount += round($add_up_value, 2);
						}
					}
				}

				echo '<p><strong>' . ($i + 1) . '. ' . $rates[$i]["serviceProvider"] . ' - ' . $rates[$i]["displayName"] . ', $' . $amount . '</strong></p>';
			}
		} else {
			echo '<p><strong>Invalid Nonce!</strong></p>';
		}
	}
}

function get_token() {
	$token = 'eiz_access_token_' . get_current_network_id();
	
	if (!get_option($token)) {
		$token = 'eiz_access_token';
	}

	return get_option($token);
}
?>
