<?

	class Shop_Authorize_Net_Aim_Payment extends Shop_PaymentType
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
				'name'=>'Authorize.Net AIM',
				'description'=>'Authorize.net Advanced Integration Method.'
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
			$host_obj->add_field('test_mode', 'Create Test Transactions')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Mark all transactions as test transactions. You can create test transactions in the live environment. <strong>Important!</strong> Test transactions are not supported by the Authorize.Net customer profiles. Use a test server, or put your live account into the test mode if you want to test payments with stored credit cards.', 'above', true);
			$host_obj->add_field('use_test_server', 'Use Test Server')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Connect to Authorize.Net test server (test.authorize.net). Use this option if you have Authorize.Net developer test account.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_field('api_login', 'API Login ID', 'left')->tab('Configuration')->renderAs(frm_text)->comment('The merchant API Login ID is provided in the Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide API Login ID.');
				$host_obj->add_field('api_transaction_key', 'Transaction Key', 'right')->tab('Configuration')->renderAs(frm_text)->comment('The merchant Transaction Key is provided in the Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide Transaction Key.');
			}

			$host_obj->add_field('use_store_currency', 'Use store currency')->tab('Configuration')->renderAs(frm_checkbox)->comment('If the checkbox is not checked, the order amount is converted to USD before it\'s sent to Authorize.Net.', 'above');

			$host_obj->add_field('transaction_type', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');

			$host_obj->add_field('skip_itemized_data', 'Do not submit itemized order information')->tab('Configuration')->renderAs(frm_checkbox)->comment('Enable this option if you don\'t want to submit itemized order information with a transaction. Please note that Authorize.Net allows up to 30 line items per transaction. This feature is automatically enabled for all orders which have more than 30 unique items.', 'above');
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
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}

		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
			
		}
		
		/**
		 * Validates configuration data after it is loaded from database
		 * Use host object to access fields previously added with build_config_ui method.
		 * You can alter field values if you need
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_load($host_obj)
		{
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
		}

		/**
		 * Builds the back-end payment form 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 */
		public function build_payment_form($host_obj)
		{
			$host_obj->add_field('FIRSTNAME', 'First Name', 'left')->renderAs(frm_text)->comment('Cardholder first name', 'above')->validation()->fn('trim')->required('Please specify a cardholder first name');
			$host_obj->add_field('LASTNAME', 'Last Name', 'right')->renderAs(frm_text)->comment('Cardholder last name', 'above')->validation()->fn('trim')->required('Please specify a cardholder last name');
			$host_obj->add_field('ACCT', 'Credit Card Number', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a credit card number')->regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');
			$host_obj->add_field('CVV2', 'CVV2', 'right')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify Card Verification Number')->numeric();

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();

			$host_obj->add_field('create_customer_profile', 'Save credit card')->renderAs(frm_checkbox)->comment('The credit card information will be saved on Authorize.Net server. You and the customer can use the saved credit card data for future payments.');
		}

		/*
		 * Payment processing
		 */

		private function format_form_fields(&$fields)
		{
			$result = array();
			foreach($fields as $key=>$val)
			    $result[] = urlencode($key)."=".urlencode($val); 
			
			return implode('&', $result);
		}
		
		private function post_data($endpoint, $fields)
		{
			$poststring = array();

			foreach($fields as $key=>$val)
			{
				if ($key != 'x_line_item')
					$poststring[] = urlencode($key)."=".urlencode(Core_String::asciify($val, true)); 
				else
				{
					foreach ($val as $item)
						$poststring[] = urlencode($key)."=".urlencode(mb_convert_encoding($item, 'HTML-ENTITIES', 'UTF-8'));
				}
			}

			$poststring = implode('&', $poststring);
			$url = "https://".$endpoint;

			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 40);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $poststring);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			$response = curl_exec($ch);
			
			if (curl_errno($ch))
				throw new Phpr_ApplicationException( "Error connecting the payment gateway: ".curl_error($ch) );
			else
				curl_close($ch);
				
			return $response;
		}

		private function parse_response($response)
		{
			return explode("|", $response);
		}

		private function prepare_fields_log($fields)
		{
			unset($fields['x_login']);
			unset($fields['x_tran_key']);
			unset($fields['x_card_code']);

			if (isset($fields['x_line_item']))
			{
				foreach ($fields['x_line_item'] as $index=>$line_item)
					$fields['x_line_item_'.$index] = $line_item;

				unset($fields['x_line_item']);
			}

			$fields['x_card_num'] = '...'.substr($fields['x_card_num'], -4);

			return $fields;
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

		protected function init_validation_obj()
		{
			$validation = new Phpr_Validation();
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.')->minLength(13, "Invalid credit card number")->maxLength(16, "Invalid credit card number");
			$validation->add('CVV2', 'CVV2')->fn('trim')->required('Please specify CVV2 value.')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.')->minLength(3, "Invalid credit card code (CVV2)")->maxLength(4, "Invalid credit card code (CVV2)");

			return $validation;
		}

		protected function prepare_exp_date($validation, $profile_mode = false)
		{
			$validation->fieldValues['EXPDATE_MONTH'] = (int)$validation->fieldValues['EXPDATE_MONTH'];
			$validation->fieldValues['EXPDATE_YEAR'] = (int)$validation->fieldValues['EXPDATE_YEAR'];
			
			$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];
			$expYear = $validation->fieldValues['EXPDATE_YEAR'];
			if (!$profile_mode)
				$expYear = $expYear > 2000 ? $expYear - 2000 : $expYear;

			if ($expYear < 10)
				$expYear = '0'.$expYear;

			return $profile_mode ? $expYear.'-'.$expMonth : $expMonth.'-'.$expYear;
		}
		
		protected function get_response_field(&$response, $index)
		{
			if (array_key_exists($index, $response))
				return $response[$index];
				
			return null;
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
			 * Validate input data
			 */
			$validation = $this->init_validation_obj();

			try
			{
				if (!$validation->validate($data))
					$validation->throwException();
			} catch (Exception $ex)
			{
				$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
				throw $ex;
			}
				
			/*
			 * Send request
			 */
			
			@set_time_limit(3600);
			
			$endpoint = $host_obj->use_test_server ? "test.authorize.net/gateway/transact.dll" : "secure.authorize.net/gateway/transact.dll";

			$fields = array();
			$response = null;
			$response_fields = array();

			$currency = Shop_CurrencySettings::get();
			$currency_converter = Shop_CurrencyConverter::create();

			$customer_profile = (isset($data['create_customer_profile']) && $data['create_customer_profile']) ? $this->update_customer_profile($host_obj, $order->customer, $data) : null;

			try
			{
				$userIp = Phpr::$request->getUserIp();
				if ($userIp == '::1')
					$userIp = '192.168.0.1';

				$fields['x_login'] = $host_obj->api_login;
				$fields['x_tran_key'] = $host_obj->api_transaction_key;
				$fields['x_version'] = '3.1';
				
				if ($host_obj->test_mode)
					$fields['x_test_request'] = 'TRUE';

				$fields['x_delim_data'] = 'TRUE';
				$fields['x_delim_char'] = '|';
				$fields['x_relay_response'] = 'FALSE';
				$fields['x_type'] = $host_obj->transaction_type;
				$fields['x_method'] = 'CC';
				
				$fields['x_card_num'] = $validation->fieldValues['ACCT'];
				$fields['x_card_code'] = $validation->fieldValues['CVV2'];
				$fields['x_exp_date'] = $this->prepare_exp_date($validation);

				$fields['x_amount'] = $host_obj->use_store_currency ? $order->total : $currency_converter->convert($order->total, $currency->code, 'USD');
				$fields['x_description'] = 'Order #'.$order->id;
				$fields['x_tax'] = $host_obj->use_store_currency ? ($order->goods_tax + $order->shipping_tax) : $currency_converter->convert($order->goods_tax + $order->shipping_tax, $currency->code, 'USD');
				
				$fields['x_email'] = $order->billing_email;
				
				$fields['x_first_name'] = $validation->fieldValues['FIRSTNAME'];
				$fields['x_last_name'] = $validation->fieldValues['LASTNAME'];
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
				$fields['x_customer_id'] = $order->customer->id;
				
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
				
				if (!$host_obj->skip_itemized_data && $order->items->count <= 29)
				{
					$fields['x_line_item'] = array();
					$item_index = 0;
					foreach ($order->items as $item)
					{
						$item_array = array();

						$product_name = str_replace("\n", "", $item->output_product_name(true, true));

						$item_array[] = Phpr_Html::strTrim($item->product->sku ? $item->product->sku : $item->product->id, 28);

						if(function_exists('iconv'))
							$name = iconv('UTF-8', 'ASCII//TRANSLIT', $item->product->name);
						else
							$name = preg_replace('/[^(\x20-\x7F)]*/', '', $item->product->name);
						
						$item_array[] = Phpr_Html::strTrim($name, 28);
						$item_array[] = Phpr_Html::strTrim($product_name, 252);
						$item_array[] = $item->quantity;
						$item_array[] = $host_obj->use_store_currency ? $item->unit_total_price : $currency_converter->convert($item->unit_total_price, $currency->code, 'USD');

						$item_array[] = $item->tax > 0 ? 'Y' : 'N';

						$fields['x_line_item'][] = implode('<|>', $item_array);
					}

					/*
					 * Add "shipping cost product"
					 */
				
					if ((float)$order->shipping_quote)
					{
						$item_array = array();
						$item_array[] = 'Shipping';
						$item_array[] = 'Shipping';
						$item_array[] = Phpr_Html::strTrim('Shipping - '.$order->shipping_method->name, 252);
						$item_array[] = 1;
						$item_array[] = $host_obj->use_store_currency ? $order->shipping_quote : $currency_converter->convert($order->shipping_quote, $currency->code, 'USD');
					
						$item_array[] = $item->shipping_tax > 0 ? 'Y' : 'N';
					
						$fields['x_line_item'][] = implode('<|>', $item_array);
					}
				}

				$response = $this->post_data($endpoint, $fields);

				/*
				 * Process result
				 */
		
				$response_fields = $this->parse_response($response);
				if (!array_key_exists(0, $response_fields))
					throw new Phpr_ApplicationException('Invalid Authorize.Net response.');

				if ($response_fields[0] != 1)
					throw new Phpr_ApplicationException($response_fields[3]);
		
				/*
				 * Successful payment. Set order status and mark it as paid.
				 */

				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					$this->prepare_fields_log($fields), 
					$response_fields, 
					$response,
					$response_fields[38],
					$this->get_ccv_status_text($response_fields[38]),
					$response_fields[5], 
					$this->get_avs_status_text($response_fields[5])
				);
				
				/*
				 * Log transaction create/change
				 */
				$this->update_transaction_status($host_obj, $order, $response_fields[6], $this->get_status_name($response_fields[11]), $response_fields[11]);

				/*
				 * Change order status
				 */

				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);

				/*
				 * Mark order as paid
				 */
				$order->set_payment_processed();
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($fields);
				
				$ccv_code = $this->get_response_field($response_fields, 38);
				$avs_code = $this->get_response_field($response_fields, 5);
				
				$this->log_payment_attempt(
					$order, 
					$ex->getMessage(), 
					0, 
					$fields, 
					$response_fields, 
					$response,
					$ccv_code,
					$this->get_ccv_status_text($ccv_code),
					$avs_code, 
					$this->get_avs_status_text($avs_code)
				);
				
				throw new Phpr_ApplicationException($ex->getMessage());
			}
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in Authorize.Net AIM payment method.');
		}
		
		protected function init_sdk($host_obj)
		{
			if (self::$sdk_initialized)
				return;
				
			self::$sdk_initialized = true;
			
			define('AUTHORIZENET_SANDBOX', $host_obj->use_test_server ? true : false);
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
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_code Specifies a transaction status code returned by the payment gateway. Example: authorized
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
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_code Specifies a transaction status code returned by the payment gateway. Example: authorized
		 * @param string $new_transaction_status_code Specifies a destination transaction status code
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
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
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
		
		/*
		 * Customer payment profiles support
		 */

		/**
		 * This method should return TRUE if the payment module supports customer payment profiles.
		 */
		public function supports_payment_profiles()
		{
			return true;
		}
		
		/**
		 * Creates a customer profile on the payment gateway. If the profile already exists the method should update it.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $customer Shop_Customer object to create a profile for
		 * @param array $data Posted payment form data
		 * @return Shop_CustomerProfile Returns the customer profile object
		 */
		public function update_customer_profile($host_obj, $customer, $data)
		{
			$this->init_sdk($host_obj);

			/*
			 * Find the existing profile
			 */
			
			$profile = $host_obj->find_customer_profile($customer);
			$new_profile_required = !$profile || !isset($profile->profile_data['customer_profile_id']);

			/*
			 * Validate input data
			 */
			$validation = $this->init_validation_obj();

			if (!$validation->validate($data))
				$validation->throwException();
			
			/*
			 * Create the customer profile
			 */

			$customerProfile = new AuthorizeNetCustomer();
			$customerProfile->merchantCustomerId = $customer->id;
			$customerProfile->email = $customer->email;

			/*
			 * Payment profile
			 */
			
			$paymentProfile = new AuthorizeNetPaymentProfile();
			$paymentProfile->payment->creditCard->cardNumber = $validation->fieldValues['ACCT'];
			$paymentProfile->payment->creditCard->expirationDate = $this->prepare_exp_date($validation, true);
			$paymentProfile->payment->creditCard->cardCode = $validation->fieldValues['CVV2'];

			$paymentProfile->billTo->firstName = h($validation->fieldValues['FIRSTNAME']);
			$paymentProfile->billTo->lastName = h($validation->fieldValues['LASTNAME']);
			$paymentProfile->billTo->company = h($customer->company);
			$paymentProfile->billTo->address = h($customer->billing_street_addr);
			$paymentProfile->billTo->city = h($customer->billing_city);
			$paymentProfile->billTo->country = h($customer->billing_country->name);
			if ($customer->billing_state)
				$paymentProfile->billTo->state = h($customer->billing_state->code);
			$paymentProfile->billTo->zip = h($customer->billing_zip);
			$paymentProfile->billTo->phoneNumber = h($customer->phone);
			
			if ($new_profile_required)
				$customerProfile->paymentProfiles[] = $paymentProfile;

			/*
			 * Shipping address
			 */

			$address = new AuthorizeNetAddress();
			$address->firstName = h($customer->shipping_first_name);
			$address->lastName = h($customer->shipping_last_name);
			$address->company = h($customer->shipping_company);
			$address->address = h($customer->shipping_street_addr);
			$address->city = h($customer->shipping_city);
			$address->country = h($customer->shipping_country->name);
			if ($customer->shipping_state)
				$address->state = h($customer->shipping_state->code);
			$address->zip = h($customer->shipping_zip);
			$address->phoneNumber = h($customer->shipping_phone);

			if ($new_profile_required)
				$customerProfile->shipToList[] = $address;

			$request = new AuthorizeNetCIM();

			/*
			 * Create or update the profile
			 */

			if ($new_profile_required)
			{
				/*
				 * Profile is not found or empty - create new profile
				 */

				$response = $request->createCustomerProfile($customerProfile, "liveMode");
				if ($response->isError())
					throw new Phpr_ApplicationException('Validation failed: '.$response->getMessageText());

				$this->check_validation_response($response);

				if (!$profile)
					$profile = $host_obj->init_customer_profile($customer);

				$profile_data = array(
					'customer_profile_id' => $response->getCustomerProfileId(),
					'address_profile_id' => (string)$response->xml->customerShippingAddressIdList->numericString,
					'payment_profile_id' => (string)$response->xml->customerPaymentProfileIdList->numericString
				);
				
				$profile->set_profile_data($profile_data, substr($validation->fieldValues['ACCT'], -4));
			} else
			{
				/*
				 * Profile exists - update the profile on the gateway
				 */
				$response = $request->updateCustomerProfile($profile->profile_data['customer_profile_id'], $customerProfile);
				if (!$response->isOk())
					throw new Phpr_ApplicationException('Error updating customer profile. '.$response->getMessageText());

				$response = $request->updateCustomerShippingAddress($profile->profile_data['customer_profile_id'], $profile->profile_data['address_profile_id'], $address);
				if (!$response->isOk())
					throw new Phpr_ApplicationException('Error customer address profile. '.$response->getMessageText());

				$response = $request->updateCustomerPaymentProfile($profile->profile_data['customer_profile_id'], $profile->profile_data['payment_profile_id'], $paymentProfile, 'liveMode');
				if ($response->isError())
					throw new Phpr_ApplicationException('Validation failed: '.$response->getMessageText());

				$vr = $response->getValidationResponse();
			    if (!$vr->approved)
					throw new Phpr_ApplicationException('Card validation failed: '.$vr->response_reason_text);
					
				$profile->set_cc_num(substr($validation->fieldValues['ACCT'], -4));
			}
			
			return $profile;
		}
		
		protected function check_validation_response($response)
		{
			$validationResponses = $response->getValidationResponses();
			foreach ($validationResponses as $vr) 
			{
			    if (!$vr->approved)
					throw new Phpr_ApplicationException('Card validation failed: '.$vr->response_reason_text);
			}
		}
		
		/**
		 * Creates a payment transaction from an existing payment profile.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Order $order An order object to pay		
		 * @param $back_end Determines whether the function is called from the administration area
		 * @param boolean $redirect Determines whether the browser should be redirected to the receipt page after successful payment
		 */
		public function pay_from_profile($host_obj, $order, $back_end = false, $redirect = true)
		{
			$this->init_sdk($host_obj);
			
			$profile = $host_obj->find_customer_profile($order->customer);
			if (!$profile || !isset($profile->profile_data['customer_profile_id']))
				throw new Phpr_ApplicationException('Payment profile not found');
				
			$currency = Shop_CurrencySettings::get();
			$currency_converter = Shop_CurrencyConverter::create();

			$request = new AuthorizeNetCIM();

			$transaction = new AuthorizeNetTransaction();
			$transaction->amount = $host_obj->use_store_currency ? $order->total : $currency_converter->convert($order->total, $currency->code, 'USD');
			$transaction->tax->amount = $host_obj->use_store_currency ? ($order->goods_tax + $order->shipping_tax) : $currency_converter->convert($order->goods_tax + $order->shipping_tax, $currency->code, 'USD');
			$transaction->customerProfileId = $profile->profile_data['customer_profile_id'];
			$transaction->customerPaymentProfileId = $profile->profile_data['payment_profile_id'];
			$transaction->customerShippingAddressId = $profile->profile_data['address_profile_id'];
			$transaction->order->invoiceNumber = $order->id;
			$transaction->order->description = 'Order #'.$order->id;
			$transaction->order->purchaseOrderNumber = $order->id;
			
			if (!$host_obj->skip_itemized_data && $order->items->count <= 29)
			{
				foreach ($order->items as $item)
				{
					$product_name = str_replace("\n", "", $item->output_product_name(true, true));

					$lineItem = new AuthorizeNetLineItem();
					$lineItem->itemId = $item->product->id;
					$lineItem->name = str_replace('&', '&amp;', Phpr_Html::strTrim($item->product->name, 28));
					$lineItem->description = str_replace('&', '&amp;', Phpr_Html::strTrim($product_name, 252));
					$lineItem->quantity = $item->quantity;
					$lineItem->unitPrice = $host_obj->use_store_currency ? $item->unit_total_price : $currency_converter->convert($item->unit_total_price, $currency->code, 'USD');
					$lineItem->taxable = $item->tax > 0;
					$transaction->lineItems[] = $lineItem;
				}
				/*
				 * Add "shipping cost product"
				 */
			
				if ((float)$order->shipping_quote)
				{
					$lineItem = new AuthorizeNetLineItem();
					$lineItem->itemId = 'shipping';
					$lineItem->name = 'Shipping';
					$lineItem->description = Phpr_Html::strTrim('Shipping - '.$order->shipping_method->name, 252);
					$lineItem->quantity = 1;
					$lineItem->unitPrice = $host_obj->use_store_currency ? $order->shipping_quote : $currency_converter->convert($order->shipping_quote, $currency->code, 'USD');
					$lineItem->taxable = $item->shipping_tax > 0;
					$transaction->lineItems[] = $lineItem;
				}
			}

			$transaction_type = $host_obj->transaction_type == 'AUTH_CAPTURE' ? 'AuthCapture' : 'AuthOnly';
			$response_array = array();
			$response_text = null;
			$transactionResponse = null;
			$response = null;
			
			try
			{
				$extra_options = 'x_customer_ip='.Phpr::$request->getUserIp().'&x_duplicate_window=5';
				$response = $request->createCustomerProfileTransaction($transaction_type, $transaction, $extra_options);

				if ($response->isError())
					throw new Phpr_ApplicationException($response->getMessageText());
					
				$transactionResponse = $response->getTransactionResponse();
				if (!$transactionResponse->approved)
					throw new Phpr_ApplicationException($transactionResponse->error_message);    
					
				$response_text = $transactionResponse->response;
				$response_array = $transactionResponse->_response_array;

				$transactionId = $transactionResponse->transaction_id;

				/*
				 * Successful payment. Set order status and mark it as paid.
				 */

				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					$this->prepare_xml_fields_log($request->getPostString()), 
					$response_array, 
					$response_text,
					$transactionResponse->card_code_response,
					$this->get_ccv_status_text($transactionResponse->card_code_response),
					$transactionResponse->avs_response, 
					$this->get_avs_status_text($transactionResponse->avs_response)
				);
				
				/*
				 * Log transaction create/change
				 */
				$this->update_transaction_status($host_obj, $order, $transactionResponse->transaction_id, $this->get_status_name($transactionResponse->transaction_type), $transactionResponse->transaction_type);

				/*
				 * Change order status
				 */

				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);

				/*
				 * Mark order as paid
				 */
				$order->set_payment_processed();
			} catch (exception $ex)
			{
				if ($transactionResponse)
					$this->log_payment_attempt(
						$order, 
						$ex->getMessage(), 
						0, 
						$this->prepare_xml_fields_log($request->getPostString()), 
						$response_array, 
						$response_text, 
						$transactionResponse->card_code_response,
						$this->get_ccv_status_text($transactionResponse->card_code_response),
						$transactionResponse->avs_response, 
						$this->get_avs_status_text($transactionResponse->avs_response)
				);
				elseif ($response)
					$this->log_payment_attempt($order, $ex->getMessage(), 0, $this->prepare_xml_fields_log($request->getPostString()), $this->prepare_xml_fields_log($response->response), $response->response);
				else
					$this->log_payment_attempt($order, $ex->getMessage(), 0, $this->prepare_xml_fields_log($request->getPostString()), array(), null);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
		
		/**
		 * Deletes a customer profile from the payment gateway.
		 * @param Db_ActiveRecord $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Customer $customer Shop_Customer object
		 * @param Shop_CustomerPaymentProfile $profile Customer profile object
		 */
		public function delete_customer_profile($host_obj, $customer, $profile)
		{
			if (!isset($profile->profile_data['customer_profile_id']))
				return;
			
			$this->init_sdk($host_obj);
			
			$request = new AuthorizeNetCIM();
			$response = $request->deleteCustomerProfile($profile->profile_data['customer_profile_id']);
			if (!$response->isOk())
				throw new Phpr_ApplicationException('Error deleting customer payment profile. '.$response->getMessageText());
		}

		protected function prepare_xml_fields_log($xml_str)
		{
			$doc = new DOMDocument();
			try
			{
				$xml_str = str_replace('xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"', '', $xml_str);
				$doc->loadXML($xml_str);
			} catch (Exception $ex)
			{
				return array();
			}

			$result = Core_Xml::to_plain_array($doc, true);
			
			if (isset($result['transactionKey']))
				unset($result['transactionKey']);

			if (isset($result['name']))
				unset($result['name']);

			return $result;
		}
	}

?>