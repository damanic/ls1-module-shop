<?

	class Shop_Authorize_Net_Dpm_Payment extends Shop_PaymentType {
		protected static $sdk_initialized = false;

		const TEST_URL = "https://test.authorize.net/gateway/transact.dll";
		const LIVE_URL = "https://secure.authorize.net/gateway/transact.dll";
	
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
		public function get_info() {
			return array(
				'name' => 'Authorize.Net DPM',
				'custom_payment_form' => 'backend_payment_form.htm',
				'description' => 'Authorize.net Direct Post Method (DPM) method with self-hosted payment form.'
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
		 * @param $host ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host, $context = null) {
			$host->add_field('test_mode', 'Create Test Transactions')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Mark all transactions as test transactions. You can create test transactions in the live environment.', 'above');
			
			$host->add_field('use_test_server', 'Use Test Server')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Connect to Authorize.Net test server (test.authorize.net). Use this option of you have Authorize.Net developer test account.', 'above');
			
			if($context !== 'preview') {
				$host->add_form_partial($host->get_partial_path('relay_response_hint.htm'))->tab('Configuration');

				$host->add_field('api_login', 'API Login ID', 'left')->tab('Configuration')->renderAs(frm_text)->comment('The merchant API Login ID is provided in the Authorize.Net Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide API Login ID.');
				$host->add_field('api_transaction_key', 'Transaction Key', 'right')->tab('Configuration')->renderAs(frm_text)->comment('The merchant Transaction Key is provided in the Authorize.Net Merchant Interface.', 'above')->validation()->fn('trim')->required('Please provide Transaction Key.');
			}

			$host->add_field('transaction_type', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');
			
			$host->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');

			if($context !== 'preview')
			{
				$host->add_field('md5_hash_value', 'MD5 Hash Value')->tab('Configuration')->renderAs(frm_password)->comment('The MD5 Hash value is a random value that you configure in the Merchant Interface.', 'above', true)->validation()->fn('trim');

				$host->add_form_partial($host->get_partial_path('md5_hint.htm'))->tab('Configuration');
			}
		}
		
		public function get_transaction_type_options($current_key_value = -1) {
			$options = array(
				'AUTH_CAPTURE' => 'Authorization and Capture',
				'AUTH_ONLY' => 'Authorization Only'
			);
			
			if($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		/**
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host) {
			$host->test_mode = true;
			$host->use_test_server = true;
			$host->order_status = Shop_OrderStatus::get_status_paid()->id;
		}

		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host) {
			$hash_value = trim($host->md5_hash_value);
			
			if(!strlen($hash_value)) {
				if(!isset($host->fetched_data['md5_hash_value']) || !strlen($host->fetched_data['md5_hash_value']))
					$host->validation->setError('Please enter MD5 Hash value', 'md5_hash_value', true);

				$host->md5_hash_value = $host->fetched_data['md5_hash_value'];
			}
		}
		
		public function get_order_status_options($current_key_value = -1) {
			if($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}
		
		public function get_form_action($host) {
			return $host->use_test_server ? self::TEST_URL : self::LIVE_URL;
		}
		
		/**
		 * Processes payment using passed data
		 * @param array $data Posted payment form data
		 * @param $host ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function process_payment_form($data, $host, $order, $back_end = false) {
			/*
			 * We do not need any code here since payments are processed on Authorize.Net server.
			 */
		}
		
		public function generate_fingerprint($api_login_id, $sequence, $timestamp, $amount, $transaction_key) {
			if(function_exists('hash_hmac'))
				return hash_hmac('md5', $api_login_id . "^" . $sequence . "^" . $timestamp . "^" . $amount . "^", $transaction_key); 

			return bin2hex(mhash(MHASH_MD5, $api_login_id . "^" . $sequence . "^" . $timestamp . "^" . $amount . "^", $transaction_key));
		}
		
		/**
		 * This method is called before the payment form is rendered
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function before_render_payment_form($host_obj)
		{
			$error_message = Phpr::$request->getField('dpm_error');
			if ($error_message)
				Phpr::$session->flash['error'] = $error_message;
		}

		public function get_hidden_fields($host, $order, $backend = false) {
			$result = array();
			$currency = Shop_CurrencySettings::get();
			$currency_converter = Shop_CurrencyConverter::create();
			$amount = number_format($currency_converter->convert($order->total, $currency->code, 'USD'), 2);
			$timestamp = time();
			$sequence = $order->id + $timestamp - 1251679000;
			$hash = $this->generate_fingerprint($host->api_login, $sequence, $timestamp, $amount, $host->api_transaction_key);
			$type = $host->transaction_type;
			$relay_url = root_url('/ls_authorize_net_relay_response/' . ($backend ? 'backend/' : ''), true);

			$fields['x_amount'] = (string)$amount;
			$fields['x_backend'] = $backend ? 'true' : 'false';
			$fields['x_relay_response'] = 'true';
			$fields['x_relay_url'] = $relay_url;
			$fields['x_login'] = $host->api_login;
			$fields['x_type'] = $host->transaction_type;
			
			if($host->test_mode)
				$fields['x_test_request'] = 'TRUE';
			
			$fields['x_fp_sequence'] = $sequence;
			$fields['x_fp_hash'] = $hash;
			$fields['x_fp_timestamp'] = $timestamp;
			//$fields['x_currency_code'] = $currency->code;
			
			$fields['x_amount'] = $amount;
			$fields['x_description'] = 'Order #' . $order->id;
			$fields['x_tax'] = $order->goods_tax + $order->shipping_tax;
			$fields['x_email'] = $order->billing_email;
			
			$fields['x_first_name'] = $order->billing_first_name;
			$fields['x_last_name'] = $order->billing_last_name;
			$fields['x_address'] = $order->billing_street_addr;
			
			if($order->billing_state)
				$fields['x_state'] = $order->billing_state->code;
			
			$fields['x_zip'] = $order->billing_zip;
			$fields['x_country'] = $order->billing_country->name;
			$fields['x_city'] = $order->billing_city;
			
			$fields['x_phone'] = $order->billing_phone;
			$fields['x_company'] = $order->billing_company;
			
			$fields['x_invoice_num'] = $order->id;
			
			$user_ip = Phpr::$request->getUserIp();
			
			if($user_ip == '::1')
				$user_ip = '192.168.0.1';
				
			$fields['x_customer_ip'] = $user_ip;
			
			$fields['x_ship_to_first_name'] = $order->shipping_first_name;
			$fields['x_ship_to_last_name'] = $order->shipping_last_name;
			
			if($order->shipping_company)
				$fields['x_ship_to_company'] = $order->shipping_company;
				
			$fields['x_ship_to_address'] = $order->shipping_street_addr;
			$fields['x_ship_to_city'] = $order->shipping_city;
			
			if($order->shipping_state)
				$fields['x_ship_to_state'] = $order->shipping_state->code;
				
			$fields['x_ship_to_zip'] = $order->shipping_zip;
			$fields['x_ship_to_country'] = $order->shipping_country->name;
			
			foreach ($fields as &$field)
				$field = Core_String::asciify($field, true);
			
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
		public function register_access_points() {
			return array(
				'ls_authorize_net_relay_response' => 'process_payment_relay_response'
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
		
		public function relay_error($message, $order) 
		{
			if (!$order)
				die(print($message));
			
			$pay_page = Cms_Page::create()->find_by_action_reference('shop:pay');
			if (!$pay_page)
				die(print($message));
				
			Phpr::$session->flash['error'] = $message;

			$redirect_url = root_url($pay_page->url.'/'.$order->order_hash, true);
			if (substr($redirect_url, -1) != '/')
				$redirect_url .= '/';
				
			$redirect_url .= '?dpm_error='.urlencode($message);
			
			die(include(PATH_APP . '/modules/shop/payment_types/shop_authorize_net_dpm_payment/relay_response.php'));
		}
		
		public function process_payment_relay_response($params) {
			$fields = $_POST;
			$order = null;
			
			try {
				// find order and load payment method settings
				$order_hash = post('x_invoice_num');

				if ($order_hash)
					$order = Shop_Order::create()->find($order_hash);
				
				if(post('x_response_code') !== '1')
					throw new Phpr_ApplicationException(post('x_response_reason_text'));

				if(!$order)
					throw new Phpr_ApplicationException('Order not found.');
				
				if(!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();
			
				if(!($payment_method_obj instanceof Shop_Authorize_Net_Dpm_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = post('x_backend') === 'true';

				// validate the transaction
				$hash = strtoupper(md5($order->payment_method->md5_hash_value . $order->payment_method->api_login . post('x_trans_id') . post('x_amount')));

				if($hash != post('x_MD5_Hash'))
					throw new Phpr_ApplicationException('Invalid transaction.');
				
				/*
				 * Mark order as paid
				 */
				
				if($order->set_payment_processed())
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
				
				if(!$is_backend) {
					$return_page = $order->payment_method->receipt_page;
					
					if($return_page)
						$redirect_url = root_url($return_page->url . '/' . $order->order_hash . '?utm_nooverride=1', true);
					else 
						throw new Phpr_ApplicationException($this->get_info()->name . ' return page is not found.');
				} 
				else {
					$redirect_url = root_url('/', true) . url('/shop/orders/payment_accepted/' . $order->id . '?utm_nooverride=1&nocache' . uniqid());
				}
				
				die(include(PATH_APP . '/modules/shop/payment_types/shop_authorize_net_dpm_payment/relay_response.php'));
			}
			catch(Exception $ex) {
				if($order) {
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), $fields, null);
				}
				
				$this->relay_error($ex->getMessage(), $order);
			}
		}
		
		/**
		 * This function is called before an order status deletion.
		 * Use this method to check whether the payment method
		 * references an order status. If so, throw Phpr_ApplicationException 
		 * with explanation why the status cannot be deleted.
		 * @param $host ActiveRecord object containing configuration fields values
		 * @param Shop_OrderStatus $status Specifies a status to be deleted
		 */
		public function status_deletion_check($host, $status) {
			if($host->order_status == $status->id)
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in ' . $this->get_info()->name . ' payment method.');
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
