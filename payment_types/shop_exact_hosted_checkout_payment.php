<?

	class Shop_Exact_Hosted_Checkout_Payment extends Shop_PaymentType
	{
		/**
		 * Returns information about the payment type
		 * Must return array: array(
		 *		'name'=>'Authorize.net', 
		 *		'custom_payment_form'=>false,
		 *		'offline'=>false,
		 *		'pay_offline_message'=>null
		 * ).
		 * Use custom_paymen_form key to specify a name of a partial to use for building a back-end
		 * payment form. Usually it is needed for forms which ACTION refer outside web services, 
		 * like PayPal Standard. Otherwise override build_payment_form method to build back-end payment
		 * forms.
		 * If the payment type provides a front-end partial (containing the payment form), 
		 * it should be called in following way: payment:name, in lower case, e.g. payment:authorize.net
		 *
		 * Set index 'offline' to true to specify that the payments of this type cannot be processed online 
		 * and thus they have no payment form. You may specify a message to display on the payment page
		 * for offline payment type, using 'pay_offline_message' index.
		 *
		 * @return array
		 */
		public function get_info()
		{
			return array(
				'name'=>'E-xact Hosted Checkout',
				'custom_payment_form'=>'backend_payment_form.htm',
				'description'=>'E-xact Hosted Checkout payment method. This payment method also works with VersaPay payment gateway (Canada).'
			);
		}
		
		/**
		 * Builds the payment type administration user interface 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host_obj, $context = null)
		{
			$host_obj->add_field('demo_mode', 'Use Demo Gateway')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('In Demo Mode requests are sent to E-xact demo gateway (https://rpm-demo.e-xact.com).', 'above');

			$host_obj->add_field('test_mode', 'Create Test Transactions')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Mark all transactions as test transactions. You can create test transactions in the live environment.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_field('x_login', 'Payment Page Id', 'left')->tab('Configuration')->renderAs(frm_text)->comment('The merchant Payment Page Id is specified in the E-xact Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide Payment Page ID.');
				
				$host_obj->add_field('api_transaction_key', 'Transaction Key', 'right')->tab('Configuration')->renderAs(frm_text)->comment('The Transaction Key is generated and set in the E-xact Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide Transaction Key.');
			}

			$host_obj->add_field('x_type', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');

			$host_obj->add_field('receipt_link_text', 'Receipt Return Link Text')->tab('Configuration')->renderAs(frm_text)->comment('Text for the return link on the E-xact order receipt page.', 'above', true)->validation()->fn('trim');

			if ($context !== 'preview')
			{
				$host_obj->add_field('relay_response_key', 'Relay Response Key')->tab('Configuration')->renderAs(frm_password)->comment('The Relay Response Key is configured in the E-xact Merchant Interface.', 'above', true)->validation()->fn('trim');

				$host_obj->add_form_partial($host_obj->get_partial_path('hint.htm'))->tab('Configuration');
			}
		}
		
		public function get_x_type_options($current_key_value = -1)
		{
			$options = array(
				'AUTH_CAPTURE'=>'Authorization and Capture',
				'AUTH_ONLY'=>'Authorization Only'
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		/**
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host_obj)
		{
			$host_obj->test_mode = 1;
			$host_obj->order_status = Shop_OrderStatus::get_status_paid()->id;
			$host_obj->receipt_link_text = 'Return to merchant';
		}

		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
			$hash_value = trim($host_obj->relay_response_key);
			
			if (!strlen($hash_value))
			{
				if (!isset($host_obj->fetched_data['relay_response_key']) || !strlen($host_obj->fetched_data['relay_response_key']))
					$host_obj->validation->setError('Please enter Relay Response Key value', 'relay_response_key', true);

				$host_obj->relay_response_key = $host_obj->fetched_data['relay_response_key'];
			}
		}
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}
		
		public function get_form_action($host_obj)
		{
			if ($host_obj->demo_mode)
				return 'https://rpm-demo.e-xact.com/pay';

			return "https://checkout.e-xact.com/pay";
		}
		
		/**
		 * Processes payment using passed data
		 * @param array $data Posted payment form data
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function process_payment_form($data, $host_obj, $order, $back_end = false)
		{
			/*
			 * We do not need any code here since payments are processed on Authorize.Net server.
			 */
		}

		/**
		 * This function is called before an order status deletion.
		 * Use this method to check whether the payment method
		 * references an order status. If so, throw Phpr_ApplicationException 
		 * with explanation why the status cannot be deleted.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_OrderStatus $status Specifies a status to be deleted
		 */
		public function status_deletion_check($host_obj, $status)
		{
			if ($host_obj->order_status == $status->id)
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in E-xact Hosted Checkout payment method.');
		}
		
		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$result = array();
			$currency = Shop_CurrencySettings::get();

			$amount = $order->total;
			$userIp = Phpr::$request->getUserIp();
			if ($userIp == '::1')
				$userIp = '192.168.0.1';

			$timeStamp = time();
			$sequence = $order->id + $timeStamp - 1251679000;
			$hash = hash_hmac("md5", $host_obj->x_login . "^" . $sequence . "^" . $timeStamp . "^" . $amount . "^".$currency->code, $host_obj->api_transaction_key);

			$fields['x_login'] = $host_obj->x_login;
			$fields['x_version'] = '3.1';
			$fields['x_relay_response'] = '';
			
			if ($host_obj->test_mode)
				$fields['x_test_request'] = 'TRUE';
			
			$fields['x_type'] = $host_obj->x_type;
			$fields['x_method'] = 'CC';
			$fields['x_show_form'] = 'PAYMENT_FORM';
			$fields['x_fp_sequence'] = $sequence;
			$fields['x_fp_hash'] = $hash;
			$fields['x_fp_timestamp'] = $timeStamp;
			$fields['x_currency_code'] = $currency->code;
			
			$fields['x_amount'] = $amount;
			$fields['x_description'] = 'Order #'.$order->id;
			$fields['x_tax'] = ($order->goods_tax + $order->shipping_tax);
			$fields['x_email'] = $order->billing_email;
			
			$fields['x_first_name'] = $order->billing_first_name;
			$fields['x_last_name'] = $order->billing_last_name;
			$fields['x_address'] = $order->billing_street_addr;
			
			if ($order->billing_state)
				$fields['x_state'] = $order->billing_state->code;
				
			$fields['x_zip'] = $order->billing_zip;
			$fields['x_country'] = $order->billing_country->name;
			$fields['x_city'] = $order->billing_city;
			
			$fields['x_phone'] = $order->billing_phone;
			$fields['x_company'] = $order->billing_company;
			
			$fields['x_invoice_num'] = $order->id;
			$fields['x_customer_ip'] = $userIp;
			
			$fields['x_ship_to_first_name'] = $order->shipping_first_name;
			$fields['x_ship_to_last_name'] = $order->shipping_last_name;
			
			if ($order->shipping_company)
				$fields['x_ship_to_company'] = $order->shipping_company;
				
			$fields['x_ship_to_address'] = $order->shipping_street_addr;
			$fields['x_ship_to_city'] = $order->shipping_city;
			
			if ($order->shipping_state)
				$fields['x_ship_to_state'] = $order->shipping_state->code;
				
			$fields['x_ship_to_zip'] = $order->shipping_zip;
			$fields['x_ship_to_country'] = $order->shipping_country->name;
			
			$fields['x_receipt_link_method'] = 'POST';
			$fields['x_receipt_link_text'] = $host_obj->receipt_link_text;
			
			if (!$backend)
				$fields['x_receipt_link_url'] = Phpr::$request->getRootUrl().root_url('/ls_exact_receipt_return/'.$order->order_hash);
			else 
				$fields['x_receipt_link_url'] = Phpr::$request->getRootUrl().root_url('/ls_exact_receipt_return/'.$order->order_hash.'/backend');

			$fields['x_line_item'] = array();
			$item_index = 0;
			foreach ($order->items as $item)
			{
				$item_array = array();
				
				$product_name = str_replace("\n", "", $item->output_product_name(true, true));
				
				$item_array[] = $item->product->sku;
				$item_array[] = Phpr_Html::strTrim($item->product->name, 28);
				$item_array[] = Phpr_Html::strTrim($product_name, 252);
				$item_array[] = $item->quantity;
				$item_array[] = $item->unit_total_price;
				$item_array[] = $item->tax > 0 ? 'Y' : 'N';
				
				$fields['x_line_item'][] = implode('<|>', $item_array);
			}
			
			/*
			 * Add "shipping cost product"
			 */
			
			if ($order->shipping_quote)
			{
				$item_array = array();
				$item_array[] = 'Shipping';
				$item_array[] = 'Shipping';
				$item_array[] = Phpr_Html::strTrim('Shipping - '.$order->shipping_method->name, 252);
				$item_array[] = 1;
				$item_array[] = $order->shipping_quote;
				$item_array[] = $item->shipping_tax > 0 ? 'Y' : 'N';
				
				$fields['x_line_item'][] = implode('<|>', $item_array);
			}

			return $fields;
		}
		
		/**
		 * Registers a hidden page with specific URL. Use this method for cases when you 
		 * need to have a hidden landing page for a specific payment gateway. For example, 
		 * PayPal needs a landing page for the auto-return feature.
		 * Important! Payment module access point names should have the ls_ prefix.
		 * @return array Returns an array containing page URLs and methods to call for each URL:
		 * return array('ls_paypal_autoreturn'=>'process_paypal_autoreturn'). The processing methods must be declared 
		 * in the payment type class. Processing methods must accept one parameter - an array of URL segments 
		 * following the access point. For example, if URL is /ls_paypal_autoreturn/1234 an array with single
		 * value '1234' will be passed to process_paypal_autoreturn method 
		 */
		public function register_access_points()
		{
			return array(
				'ls_exact_silent_post'=>'process_payment_silent_post',
				'ls_exact_receipt_return'=>'process_receipt_return'
			);
		}
		
		protected function get_avs_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'AVS response code is empty';
			
			$status_names = array(
				'A'=>'Address matches but ZIP/Postal code does not',
				'B'=>'Address not provided',
				'E'=>'AVS Error',
				'G'=>'Card issuer does not support AVS',
				'N'=>'No match on address and ZIP/Postal Code',
				'P'=>'AVS does not apply to the transaction',
				'R'=>'System unavailable - Retry',
				'S'=>'Card issuer does not support AVS',
				'U'=>'No address available',
				'W'=>'9 digit ZIP/Postal Code match, address does not',
				'X'=>'Address and 9 digit ZIP/Postal Code match'
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown AVS response code';
		}
		
		protected function get_ccv_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'CCV response code is empty';

			$status_names = array(
				'M'=>'Match',
				'N'=>'No Match',
				'P'=>'Not Processed',
				'S'=>'Should have been provided',
				'U'=>'Issuer unable to process request'
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown CCV response code';
		}

		public function process_payment_silent_post($params)
		{
			$fields = $_POST;
			$order = null;

			try
			{
				/*
				 * Find order and load payment method settings
				 */

				$order_id = post('x_invoice_num');
				if (!$order_id)
					throw new Phpr_ApplicationException('Order not found');

				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if (!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();
			
				if (!($payment_method_obj instanceof Shop_Exact_Hosted_Checkout_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = array_key_exists(1, $params) ? $params[1] == 'backend' : false;

				/*
				 * Validate the transaction
				 */
			
				$hash = strtolower(md5($order->payment_method->relay_response_key.$order->payment_method->x_login.post('x_trans_id').post('x_amount')));

				if ($hash != post('x_MD5_Hash'))
					throw new Phpr_ApplicationException('Invalid transaction.');
				
				if (post('x_response_code') != 1)
					throw new Phpr_ApplicationException('Invalid response code.');
				
				if ($order->set_payment_processed())
				{
					Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
					
					$this->log_payment_attempt(
						$order, 
						'Successful payment', 
						1, 
						array(), 
						$fields, 
						null,
						post('x_cvv2_resp_code'),
						$this->get_ccv_status_text(post('x_cvv2_resp_code')),
						post('x_avs_code'),
						$this->get_avs_status_text(post('x_avs_code'))
					);
				}
			}
			catch (Exception $ex)
			{
				if ($order)
				{
					$this->log_payment_attempt(
						$order, 
						$ex->getMessage(), 
						0, 
						array(), 
						$fields, 
						null,
						post('x_cvv2_resp_code'),
						$this->get_ccv_status_text(post('x_cvv2_resp_code')),
						post('x_avs_code'),
						$this->get_avs_status_text(post('x_avs_code'))
					);
				}

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
		
		public function process_receipt_return($params)
		{
			/*
			 * Find order and load payment method settings
			 */
			
			$fields = $_POST;

			try
			{
				$order_hash = array_key_exists(0, $params) ? $params[0] : null;
				if (!$order_hash)
					throw new Phpr_ApplicationException('Order not found');

				$order = Shop_Order::create()->find_by_order_hash($order_hash);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if (!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();
			
				if (!($payment_method_obj instanceof Shop_Exact_Hosted_Checkout_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = array_key_exists(1, $params) ? $params[1] == 'backend' : false;

				/*
				 * Validate the transaction
				 */
			
				$hash = strtolower(md5($order->payment_method->relay_response_key.$order->payment_method->x_login.post('x_trans_id').post('x_amount')));
				if ($hash != post('x_MD5_Hash'))
					throw new Phpr_ApplicationException('Invalid transaction.');
				
				if (post('x_response_code') != 1)
					throw new Phpr_ApplicationException('Invalid response code.');
				
				if ($order->set_payment_processed())
				{
					Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);

					$this->log_payment_attempt(
						$order, 
						'Successful payment', 
						1,
						array(), 
						$fields, 
						null,
						post('x_cvv2_resp_code'),
						$this->get_ccv_status_text(post('x_cvv2_resp_code')),
						post('x_avs_code'),
						$this->get_avs_status_text(post('x_avs_code'))
					);
				}

				if (!$is_backend)
				{
					$return_page = $order->payment_method->receipt_page;
					if ($return_page)
						Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
					else 
						throw new Phpr_ApplicationException('E-xact Hosted receipt page not found.');
				} else 
				{
					Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
				}
			}
			catch (Exception $ex)
			{
				if ($order)
					$this->log_payment_attempt(
						$order, 
						$ex->getMessage(), 
						0, 
						array(), 
						$fields, 
						null,
						post('x_cvv2_resp_code'),
						$this->get_ccv_status_text(post('x_cvv2_resp_code')),
						post('x_avs_code'),
						$this->get_avs_status_text(post('x_avs_code'))
					);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
	}
	
?>