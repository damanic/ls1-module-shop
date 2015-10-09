<?

	class Shop_Authorize_Net_Sim_Payment extends Shop_PaymentType
	{
		protected static $sdk_initialized = false;

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
				'name'=>'Authorize.Net SIM',
				'custom_payment_form'=>'backend_payment_form.htm',
				'description'=>'Authorize.net Simple Integration Method with hosted payment form.'
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
			$host_obj->add_field('test_mode', 'Create Test Transactions')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Mark all transactions as test transactions. You can create test transactions in the live environment.', 'above');
			
			$host_obj->add_field('use_test_server', 'Use Test Server')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Connect to Authorize.Net test server (test.authorize.net). Use this option of you have Authorize.Net developer test account.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_field('api_login', 'API Login ID', 'left')->tab('Configuration')->renderAs(frm_text)->comment('The merchant API Login ID is provided in the Authorize.Net Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide API Login ID.');
				$host_obj->add_field('api_transaction_key', 'Transaction Key', 'right')->tab('Configuration')->renderAs(frm_text)->comment('The merchant Transaction Key is provided in the Authorize.Net Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide Transaction Key.');
			}

			$host_obj->add_field('transaction_type', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');

			$host_obj->add_field('receipt_link_text', 'Receipt Return Link Text')->tab('Configuration')->renderAs(frm_text)->comment('Text for the return link on the Authorize.Net order receipt page.', 'above', true)->validation()->fn('trim');
			
			if ($context !== 'preview')
				$host_obj->add_form_partial($host_obj->get_partial_path('silent_post_hint.htm'))->tab('Configuration');

			if ($context !== 'preview')
			{
				$host_obj->add_field('md5_hash_value', 'MD5 Hash Value')->tab('Configuration')->renderAs(frm_password)->comment('The MD5 Hash value is a random value that you configure in the Merchant Interface.', 'above', true)->validation()->fn('trim');

				$host_obj->add_form_partial($host_obj->get_partial_path('md5_hint.htm'))->tab('Configuration');
			}
		}
		
		public function get_transaction_type_options($current_key_value = -1)
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
			$host_obj->use_test_server = 1;
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
			$hash_value = trim($host_obj->md5_hash_value);
			
			if (!strlen($hash_value))
			{
				if (!isset($host_obj->fetched_data['md5_hash_value']) || !strlen($host_obj->fetched_data['md5_hash_value']))
					$host_obj->validation->setError('Please enter MD5 Hash value', 'md5_hash_value', true);

				$host_obj->md5_hash_value = $host_obj->fetched_data['md5_hash_value'];
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
			if ($host_obj->use_test_server)
				return "https://test.authorize.net/gateway/transact.dll";
			else
				return "https://secure.authorize.net/gateway/transact.dll";
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in eSELECTplus API payment method.');
		}
		
		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$result = array();
			$currency = Shop_CurrencySettings::get();
			$currency_converter = Shop_CurrencyConverter::create();

			$amount = $currency_converter->convert($order->total, $currency->code, 'USD');
			
			$userIp = Phpr::$request->getUserIp();
			if ($userIp == '::1')
				$userIp = '192.168.0.1';

			$timeStamp = time();
			$sequence = $order->id + $timeStamp - 1251679000;
			$hash = hash_hmac("md5", $host_obj->api_login . "^" . $sequence . "^" . $timeStamp . "^" . $amount . "^", $host_obj->api_transaction_key);

			$fields['x_login'] = $host_obj->api_login;
			$fields['x_version'] = '3.1';
			$fields['x_relay_response'] = 'FALSE';
			
			if ($host_obj->test_mode)
				$fields['x_test_request'] = 'TRUE';
			
			$fields['x_type'] = $host_obj->transaction_type;
			$fields['x_method'] = 'CC';
			$fields['x_show_form'] = 'PAYMENT_FORM';
			$fields['x_fp_sequence'] = $sequence;
			$fields['x_fp_hash'] = $hash;
			$fields['x_fp_timestamp'] = $timeStamp;
			
			$fields['x_amount'] = $amount;
			$fields['x_description'] = 'Order #'.$order->id;
			$fields['x_tax'] = $currency_converter->convert($order->goods_tax + $order->shipping_tax, $currency->code, 'USD');
			
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
				$fields['x_receipt_link_url'] = Phpr::$request->getRootUrl().root_url('/ls_authorize_net_receipt_return/'.$order->order_hash);
			else 
				$fields['x_receipt_link_url'] = Phpr::$request->getRootUrl().root_url('/ls_authorize_net_receipt_return/'.$order->order_hash.'/backend');

			$fields['x_line_item'] = array();
			$item_index = 0;

			foreach ($order->items as $item)
			{
				$item_array = array();
				
				$product_name = str_replace("\n", "", $item->output_product_name(true, true));
				
				$item_array[] = Phpr_Html::strTrim($item->product->sku ? $item->product->sku : $item->product->id, 28);
				$item_array[] = Phpr_Html::strTrim($item->product->name, 28);
				$item_array[] = Phpr_Html::strTrim($product_name, 252);
				$item_array[] = $item->quantity;
				$item_array[] = $currency_converter->convert($item->unit_total_price, $currency->code, 'USD');
				
				$item_array[] = $item->tax > 0 ? 'Y' : 'N';
				
				$fields['x_line_item'][] = implode('<|>', $item_array);
			}
			
			foreach ($fields as &$field)
				$field = Core_String::asciify($field, true);
			
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
				$item_array[] = $currency_converter->convert($order->shipping_quote, $currency->code, 'USD');
				
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
				'ls_authorize_net_silent_post'=>'process_payment_silent_post',
				'ls_authorize_net_receipt_return'=>'process_receipt_return'
			);
		}

		protected function get_status_name($status_id)
		{
			$status_id = strtoupper($status_id);
			
			$names = array(
				'AUTHORIZEDPENDINGCAPTURE'=>'Authorized, pending capture',
				'CAPTUREDPENDINGSETTLEMENT'=>'Captured, pending settlement',
				'COMMUNICATIONERROR'=>'Communication error',
				'REFUNDSETTLEDSUCCESSFULLY'=>'Refund, settled successfully',
				'REFUNDPENDINGSETTLEMENT'=>'Refund, pending settlement',
				'APPROVEDREVIEW'=>'Approved review',
				'DECLINED'=>'Declined',
				'COULDNOTVOID'=>'Could not void',
				'EXPIRED'=>'Expired',
				'GENERALERROR'=>'General error',
				'PENDINGFINALSETTLEMENT'=>'Pending final settlement',
				'PENDINGSETTLEMENT'=>'Pending settlement',
				'FAILEDREVIEW'=>'Failed review',
				'SETTLEDSUCCESSFULLY'=>'Settled successfully',
				'SETTLEMENTERROR'=>'Settlement error',
				'UNDERREVIEW'=>'Under review',
				'UPDATINGSETTLEMENT'=>'Updating settlement',
				'VOIDED'=>'Voided',
				'FDSPENDINGREVIEW'=>'FDS, pending review',
				'FDSAUTHORIZEDPENDINGREVIEW'=>'FDS authorized, pending review',
				'RETURNEDITEM'=>'Returned item',
				'CHARGEBACK'=>'Chargeback',
				'CHARGEBACKREVERSAL'=>'Chargeback reversal',
				'AUTHORIZEDPENDINGRELEASE'=>'Authorized, pending release',
				'AUTH_CAPTURE'=>'Authorization and Capture',
				'AUTH_ONLY'=>'Authorization',
				'CAPTURE_ONLY'=>'Capture',
				'CREDIT'=>'Credit',
				'PRIOR_AUTH_CAPTURE'=>'Prior Authorization and Capture',
				'VOID'=>'Void'
			);
			
			if (array_key_exists($status_id, $names))
				return $names[$status_id];
				
			return 'Unknown';
		}
		
		protected function get_avs_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'AVS response code is empty';
			
			$status_names = array(
				'A'=>'Address (Street) matches, ZIP does not',
				'B'=>'Address information not provided for AVS check',
				'E'=>'AVS error',
				'G'=>'Non-U.S. Card Issuing Bank',
				'N'=>'No Match on Address (Street) or ZIP',
				'P'=>'AVS not applicable for this transaction',
				'R'=>'Retry – System unavailable or timed out',
				'S'=>'Service not supported by issuer',
				'U'=>'Address information is unavailable',
				'W'=>'Nine digit ZIP matches, Address (Street) does not',
				'X'=>'Address (Street) and nine digit ZIP match',
				'Y'=>'Address (Street) and five digit ZIP match',
				'Z'=>'Five digit ZIP matches, Address (Street) does not'
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
				'S'=>'Should have been present',
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
			
				if (!($payment_method_obj instanceof Shop_Authorize_Net_Sim_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = array_key_exists(1, $params) ? $params[1] == 'backend' : false;

				/*
				 * Validate the transaction
				 */
			
				$hash = strtoupper(md5($order->payment_method->md5_hash_value.$order->payment_method->api_login.post('x_trans_id').post('x_amount')));

				if ($hash != post('x_MD5_Hash'))
					throw new Phpr_ApplicationException('Invalid transaction.');
				
				if (post('x_response_code') != 1)
					throw new Phpr_ApplicationException('Invalid response code.');
					
				/*
				 * Mark order as paid
				 */
				
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
					
					/*
					 * Log transaction create/change
					 */

					$this->update_transaction_status($order->payment_method, $order, post('x_trans_id'), $this->get_status_name(post('x_type')), post('x_type'));
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
						$this->get_avs_status_text(post('x_avs_code')));

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
			
				if (!($payment_method_obj instanceof Shop_Authorize_Net_Sim_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = array_key_exists(1, $params) ? $params[1] == 'backend' : false;
			
				/*
				 * Validate the transaction
				 */
			
				$hash = strtoupper(md5($order->payment_method->md5_hash_value.$order->payment_method->api_login.post('x_trans_id').post('x_amount')));

				if ($hash != post('x_MD5_Hash'))
					throw new Phpr_ApplicationException('Invalid transaction.');
				
				if (post('x_response_code') != 1)
					throw new Phpr_ApplicationException('Invalid response code.');
				
				/*
				 * Mark order as paid
				 */
				
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
					
					/*
					 * Log transaction create/change
					 */

					$this->update_transaction_status($order->payment_method, $order, post('x_trans_id'), $this->get_status_name(post('x_type')), post('x_type'));
				}

				if (!$is_backend)
				{
					$return_page = $order->payment_method->receipt_page;
					if ($return_page)
						Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash.'?utm_nooverride=1'));
					else 
						throw new Phpr_ApplicationException('Authorize.Net SIM Thank You page is not found.');
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
		
		protected function init_sdk($host_obj)
		{
			if (self::$sdk_initialized)
				return;
				
			self::$sdk_initialized = true;
			
			define ('AUTHORIZENET_SANDBOX', $host_obj->use_test_server ? true : false);
			define('AUTHORIZENET_API_LOGIN_ID', $host_obj->api_login);
			define('AUTHORIZENET_TRANSACTION_KEY', $host_obj->api_transaction_key);
			
			$path = dirname(__FILE__).'/'.strtolower(get_class($this));

			require_once $path.'/lib/shared/AuthorizeNetRequest.php';
			require_once $path.'/lib/shared/AuthorizeNetTypes.php';
			require_once $path.'/lib/shared/AuthorizeNetXMLResponse.php';
			require_once $path.'/lib/shared/AuthorizeNetResponse.php';
			require_once $path.'/lib/AuthorizeNetAIM.php';
			require_once $path.'/lib/AuthorizeNetCIM.php';
			require_once $path.'/lib/AuthorizeNetTD.php';
		}
		
		/*
		 * Transaction management methods
		 */
		
		/**
		 * This method should return TRUE if the payment gateway supports requesting a status of a specific transaction
		 */
		public function supports_transaction_status_query()
		{
			return true;
		}

		/**
		 * Returns a list of available transitions from a specific transaction status
		 * The method returns an associative array with keys corresponding transaction statuses 
		 * and values corresponding transaction status actions: array('V'=>'Void', 'S'=>'Submit for settlement')
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaciton identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_code Specifies a transaciton status code returned by the payment gateway. Example: authorized
		 */
		public function list_available_transaction_transitions($host_obj, $transaction_id, $transaction_status_code)
		{
			$transaction_status_code = strtoupper($transaction_status_code);

			switch ($transaction_status_code)
			{
				case 'AUTH_ONLY' :
				case 'AUTHORIZEDPENDINGCAPTURE' :
					return array(
						'prior_auth_capture' => 'Prior Authorization and Capture',
						'void' => 'Void'
					);
				break;
				case 'AUTH_CAPTURE' :
					return array(
						'credit' => 'Credit (refund)',
						'void' => 'Void'
					);
				break;
				case 'SETTLEDSUCCESSFULLY' :
					return array(
						'credit' => 'Credit (refund)'
					);
				break;
				case 'AUTHORIZEDPENDINGCAPTURE' :
				case 'CAPTUREDPENDINGSETTLEMENT' :
				case 'REFUNDPENDINGSETTLEMENT' :
				case 'APPROVEDREVIEW' :
				case 'PENDINGFINALSETTLEMENT' :
				case 'PENDINGSETTLEMENT' :
				case 'AUTH_CAPTURE' :
				case 'AUTH_ONLY' :
				case 'CAPTURE_ONLY' :
				case 'PRIOR_AUTH_CAPTURE' :
					return array(
						'void' => 'Void'
					);
				break;
			}
			
			return array();
		}
		
		/**
		 * Contacts the payment gateway and sets specific status on a specific transaction.
		 * The method must return an instance of the Shop_TransactionUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Order $order LemonStand order object the transaction is bound to
		 * @param string $transaction_id Specifies a transaciton identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_code Specifies a transaciton status code returned by the payment gateway. Example: authorized
		 * @param string $new_transaction_status_code Specifies a destination transaciton status code
		 * @return Shop_TransactionUpdate Transaction update information
		 */
		public function set_transaction_status($host_obj, $order, $transaction_id, $transaction_status_code, $new_transaction_status_code)
		{
			$this->init_sdk($host_obj);
			

			$td_request = new AuthorizeNetTD();
			$td_request->VERIFY_PEER = false;
			
			$transaction_details = $td_request->getTransactionDetails($transaction_id);
			
			if (!$transaction_details->xml)
				throw new Phpr_ApplicationException('Error requesting transaction status: cannot load data from the gateway.');
			
			if ($transaction_details->isError())
				throw new Phpr_ApplicationException($transaction_details->getErrorMessage());
			
			$aim_request = new AuthorizeNetAIM();
			$aim_request->VERIFY_PEER = false;
			$aim_request->setFields(array('trans_id' => $transaction_id));
			
			$override_status_name = false;

			switch ($new_transaction_status_code)
			{
				case 'prior_auth_capture' : 
		        	$submitResult = $aim_request->priorAuthCapture();
				break;
				case 'void' : 
					
	        		$submitResult = $aim_request->void();
				break;
				case 'credit' : 
					$aim_request->setFields(array(
						'card_num' => substr((string)$transaction_details->xml->transaction->payment->creditCard->cardNumber, -4),
						'amount' => (string)$transaction_details->xml->transaction->authAmount
					));
        			$submitResult = $aim_request->credit();

					$override_status_name = 'Refund requested';
				break;
				default:
					throw new Phpr_ApplicationException('Unknown transaction status code: '.$new_transaction_status_code);
			}

			if (!$submitResult->approved)
			{
				$error_str = $submitResult->error_message;

				if ($error_str)
					throw new Phpr_ApplicationException($error_str);
				else
					throw new Phpr_ApplicationException('Error updating transaction status.');
			} else {
				$result = $this->request_transaction_status($host_obj, $transaction_id);
				if ($override_status_name)
					$result->transaction_status_name = $override_status_name;

				return $result;
			}
		}
		
		/**
		 * Request a status of a specific transaction a specific transaction.
		 * The method must return an instance of the Shop_TransactionUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaciton identifier returned by the payment gateway. Example: kjkls
		 * @return Shop_TransactionUpdate Transaction update information
		 */
		public function request_transaction_status($host_obj, $transaction_id)
		{
			$this->init_sdk($host_obj);

			$request = new AuthorizeNetTD();
			$request->VERIFY_PEER = false;
			
			$response = $request->getTransactionDetails($transaction_id);
			if ($response->isError())
				throw new Phpr_ApplicationException($response->getErrorMessage());
				
			if (!$response->xml)
				throw new Phpr_ApplicationException('Error requesting transaction status: cannot load data from the gateway.');

			$status = (string)($response->xml->transaction->transactionStatus);

			return new Shop_TransactionUpdate(
				$status,
				$this->get_status_name($status)
			);
		}
	}
	
?>