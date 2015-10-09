<?

	class Shop_SagePay_Direct_Payment extends Shop_PaymentType
	{
		protected $endpoints = array(
			'SIMULATOR'=>array(
				'GATEWAY' => 'https://test.sagepay.com/Simulator/VSPDirectGateway.asp',
				'CALLBACK' => 'https://test.sagepay.com/Simulator/VSPDirectCallback.asp'
			),
			'TEST'=>array(
				'GATEWAY' => 'https://test.sagepay.com/gateway/service/vspdirect-register.vsp',
				'CALLBACK' => 'https://test.sagepay.com/gateway/service/direct3dcallback.vsp'
			),
			'Live'=>array(
				'GATEWAY' => 'https://live.sagepay.com/gateway/service/vspdirect-register.vsp',
				'CALLBACK' => 'https://live.sagepay.com/gateway/service/direct3dcallback.vsp'
			)
		);
		
		/**
		 * Returns information about the payment type
		 * Must return array: array(
		 *		'name'=>'Authorize.net', 
		 *		'custom_payment_form'=>false,
		 *		'offline'=>false,
		 *		'pay_offline_message'=>null
		 * ).
		 * Use custom_payment_form key to specify a name of a partial to use for building a back-end
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
				'name'=>'Sage Pay Direct',
				'description'=>'UK payment gateway - Sage Pay. "Direct" integration method.'
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
			$host_obj->add_field('mode', 'Mode')->tab('Configuration')->renderAs(frm_dropdown);

			if ($context !== 'preview')
				$host_obj->add_field('vendor', 'Vendor Login Name')->tab('Configuration')->renderAs(frm_text)->comment('Should contain the Vendor Name supplied by Sage Pay when your account was created.', 'above')->validation()->fn('trim')->required('Please provide Vendor Name.');

			$host_obj->add_field('transaction_type', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}

		public function get_mode_options($current_key_value = -1)
		{
			$options = array(
				'SIMULATOR'=>'Simulator',
				'TEST'=>'Test',
				'Live'=>'Live',
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		public function get_transaction_type_options($current_key_value = -1)
		{
			$options = array(
				'PAYMENT'=>'Authorization and Capture',
				'AUTHENTICATE'=>'Authorization Only'
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
			$host_obj->mode = 'SIMULATOR';
			$host_obj->order_status = Shop_OrderStatus::get_status_paid()->id;
		}

		/**
		 * Registers a hidden page with specific URL. Use this method for cases when you 
		 * need to have a hidden landing page for a specific payment gateway. For example, 
		 * PayPal needs a landing page for the auto-return feature.
		 * @return array Returns an array containing page URLs and methods to call for each URL:
		 * return array('ls_paypal_autoreturn'=>'process_paypal_autoreturn'). The processing methods must be declared 
		 * in the payment type class. Processing methods must accept one parameter - an array of URL segments 
		 * following the access point. For example, if URL is /paypal_autoreturn/1234 an array with single
		 * value '1234' will be passed to process_paypal_autoreturn method 
		 */
		public function register_access_points()
		{
			return array(
				'ls_sagepay_authenticate_3d'=>'authenticate_3d',
				'ls_sagepay_redirect_3d'=>'redirect_3d'
			);
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

			$host_obj->add_field('CARD_TYPE', 'Card Type')->renderAs(frm_dropdown)->emptyOption('<please select>');
			
			$host_obj->add_field('ACCT', 'Credit Card Number')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a credit card number')->regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');

			$host_obj->add_field('START_MONTH', 'Start Date Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->comment('Please specify card start month, if applicable', 'above')->validation()->fn('trim')->numeric();
			$host_obj->add_field('START_YEAR', 'Start Date Year', 'right')->comment('Please specify card start year, if applicable', 'above')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
			
			$host_obj->add_field('CVV2', 'CVV2')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify Card Verification Number')->numeric();
			
			$host_obj->add_field('ISSUENUMBER', 'Issue Number')->comment('Please specify Issue Number, if applicable', 'above')->renderAs(frm_text)->validation()->fn('trim');
			$host_obj->add_field('GiftAid', 'Gift Aid')->renderAs(frm_checkbox)->comment('Select the checkbox if the customer wishes to donate the tax. Applies to donations only. More information available on <a href="http://www.hmrc.gov.uk/individuals/giving/gift-aid.htm">HM Revenue & Custom website.</a>', 'above', true);
		}
		
		public function get_CARD_TYPE_options($current_key_value = -1)
		{
			$options = array(
				"VISA"=>"VISA Credit",
				"DELTA"=>"VISA Debit",
				"UKE"=>"VISA Electron",
				"MC"=>"MasterCard",
				"MAESTRO"=>"Maestro",
				"AMEX"=>"American Express",
				"DC"=>"Diner's Club",
				"JCB"=>"JCB Card",
				"LASER"=>"Laser",
				"SOLO"=>"Solo"
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
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
				$poststring[] = urlencode($key)."=".urlencode($val); 
		
			$poststring = implode('&', $poststring);

			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, $endpoint);
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
			$response = explode(chr(10), $response);

			$output = array();
			for ($i=0; $i<count($response); $i++)
			{
				$splitAt = strpos($response[$i], "=");
				$output[trim(substr($response[$i], 0, $splitAt))] = trim(substr($response[$i], ($splitAt+1)));
			}

			return $output;
		}

		private function prepare_fields_log($fields)
		{
			if (isset($fields['Vendor']))
				unset($fields['Vendor']);

			if (isset($fields['CV2']))
				unset($fields['CV2']);
			
			if (isset($fields['CardNumber']))
				$fields['CardNumber'] = '...'.substr($fields['CardNumber'], -4);
			
			return $fields;
		}
		
		protected function process_avs_cv2_result($response_fields, &$cvv_code, &$cvv_message, &$avs_code, &$avs_message)
		{
			$cvv_code = null;
			$avs_code = null;
			$cvv_message = null;
			$avs_message = null;

			if (array_key_exists('AddressResult', $response_fields) && strlen($response_fields['AddressResult']))
			{
				$avs_code = ' ';
				$avs_message = 'Address: '.$response_fields['AddressResult'];
			}

			if (array_key_exists('PostCodeResult', $response_fields) && strlen($response_fields['PostCodeResult']))
			{
				if (!strlen($avs_code))
					$avs_code .= ' ';

				if (strlen($avs_message))
					$avs_message .= ', ';

				$avs_message .= 'Post code: '.$response_fields['PostCodeResult'];
			}
			
			if (array_key_exists('CV2Result', $response_fields) && strlen($response_fields['CV2Result']))
			{
				$cvv_code = ' ';
				$cvv_message = $response_fields['CV2Result'];
			}
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

			$validation = new Phpr_Validation();
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('CARD_TYPE', 'Card type')->fn('trim')->required('Please specify a card type.');
			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');
			
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

			$validation->add('START_MONTH', 'Start date month')->fn('trim')->regexp('/^[0-9]*$/', 'Credit card start date month can contain only digits.');
			$validation->add('START_YEAR', 'Start date year')->fn('trim')->regexp('/^[0-9]*$/', 'Credit card start date year can contain only digits.');

			$validation->add('CVV2', 'CVV2')->fn('trim')->required('Please specify CVV2 value.')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.');
			
			$validation->add('ISSUENUMBER', 'Issue number')->fn('trim');

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
			
			$endpoint = $this->endpoints[$host_obj->mode]['GATEWAY'];

			$fields = array();
			$response = null;
			$response_fields = array();

			$currency = Shop_CurrencySettings::get();

			try
			{
				$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];
				$expYear = $validation->fieldValues['EXPDATE_YEAR'] > 2000 ? $validation->fieldValues['EXPDATE_YEAR'] - 2000 : $validation->fieldValues['EXPDATE_YEAR'];
				if ($expYear < 10)
					$expYear = '0'.$expYear;
					
				$startMonth = $validation->fieldValues['START_MONTH'] < 10 ? '0'.$validation->fieldValues['START_MONTH'] : $validation->fieldValues['START_MONTH'];
				$startYear = $validation->fieldValues['START_YEAR'] > 2000 ? $validation->fieldValues['START_YEAR'] - 2000 : $validation->fieldValues['START_YEAR'];
				if ($startYear < 10)
					$startYear = '0'.$startYear;
					
				if ($startMonth == '0') $startMonth = '';
				if ($startYear == '0') $startYear = '';

				$userIp = Phpr::$request->getUserIp();
				
				$intRandNum = rand(0,32000)*rand(0,32000);
				$strVendorTxCode = $intRandNum;

				$fields['Vendor'] = $host_obj->vendor;
				$fields['TxType'] = $host_obj->transaction_type;
				$fields['VPSProtocol'] = '2.23';
				$fields['VendorTxCode'] = $strVendorTxCode;
				$fields['ClientIPAddress'] = $userIp;

				$fields['Amount'] = $order->total;
				$fields['Currency'] = $currency->code;
				$fields['Description'] = 'Order #'.$order->id;
				
				$fields['CardHolder'] = $validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME'];
				$fields['CardNumber'] = $validation->fieldValues['ACCT'];
				$fields['StartDate'] = $startMonth.$startYear;
				$fields['ExpiryDate'] = $expMonth.$expYear;
				$fields['IssueNumber'] = $validation->fieldValues['ISSUENUMBER'];
				$fields['CV2'] = $validation->fieldValues['CVV2'];
				$fields['CardType'] = $validation->fieldValues['CARD_TYPE'];

				if(array_key_exists('GiftAid', $data) && strlen($data['GiftAid']))
					$fields['GiftAidPayment'] = 1;
				
				/*
				 * Billing information
				 */
				
				$fields['BillingSurname'] = $order->billing_last_name;
				$fields['BillingFirstnames'] = $order->billing_first_name;
				$fields['BillingAddress1'] = $order->billing_street_addr;
				$fields['BillingCity'] = $order->billing_city;
				$fields['BillingPostCode'] = $order->billing_zip;
				$fields['BillingCountry'] = $order->billing_country->code;
				
				if ($order->billing_state && $order->billing_country->code == 'US')
					$fields['BillingState'] = $order->billing_state->code;

				$fields['BillingPhone'] = $order->billing_phone;
				$fields['BillingPhone'] = $order->billing_phone;
				$fields['CustomerEMail'] = $order->billing_email;

				/*
				 * Shipping information
				 */
				
				$fields['DeliverySurname'] = $order->shipping_last_name;
				$fields['DeliveryFirstnames'] = $order->shipping_first_name;
				$fields['DeliveryAddress1'] = $order->shipping_street_addr;
				$fields['DeliveryCity'] = $order->shipping_city;
				$fields['DeliveryPostCode'] = $order->shipping_zip;
				$fields['DeliveryCountry'] = $order->shipping_country->code;

				if ($order->shipping_state && $order->shipping_country->code == 'US')
					$fields['DeliveryState'] = $order->shipping_state->code;

				$fields['DeliveryPhone'] = $order->shipping_phone;
				
				/*
				 * Items information
				 */

				$items = array();
				foreach ($order->items as $item)
				{
					$item_array = array();
					
					$product_name = str_replace("\n", "", $item->output_product_name(true, true));
					$product_name = str_replace(':', ' - ', $product_name);
					
					$single_price = $item->unit_total_price;
					
					$item_array[] = $product_name;
					$item_array[] = $item->quantity;
					$item_array[] = $single_price;
					$item_array[] = $item->tax;
					$item_array[] = $single_price + $item->tax;
					$item_array[] = ($single_price + $item->tax)*$item->quantity;
					
					$items[] = implode(':', $item_array);
				}
				
				/*
				 * Add "shipping cost product"
				 */
				
				if ($order->shipping_quote > 0)
				{
					$item_array = array();
					
					$item_array[] = 'Shipping';
					$item_array[] = '--';
					$item_array[] = $order->shipping_quote;
					$item_array[] = $item->shipping_tax;
					$item_array[] = $order->shipping_quote + $item->shipping_tax;
					$item_array[] = $order->shipping_quote + $item->shipping_tax;
					
					$items[] = implode(':', $item_array);
				}

				$items = count($items).':'.implode(':', $items);

				$fields['Basket'] = $items;

				$response = $this->post_data($endpoint, $fields);

				/*
				 * Process result
				 */
				
				$response_fields = $this->parse_response($response);
				if (!array_key_exists('Status', $response_fields))
					throw new Phpr_ApplicationException('Invalid payment gateway response.');
				
				if ($response_fields['Status'] != 'OK' && $response_fields['Status'] != 'AUTHENTICATED' && $response_fields['Status'] != 'REGISTERED' && $response_fields['Status'] != '3DAUTH')
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
		
				if ($response_fields['Status'] == '3DAUTH') {
					if($back_end)
						$authentication_url = root_url('/ls_sagepay_authenticate_3d/'.$order->order_hash.'/backend', true);
					else
						$authentication_url = root_url('/ls_sagepay_authenticate_3d/'.$order->order_hash, true);
					
					$data = self::param(array('ACSURL' => $response_fields['ACSURL'], 'PAReq' => $response_fields['PAReq'], 'MD' => $response_fields['MD'], 'TermUrl' => $authentication_url));
					
					Phpr::$response->redirect(root_url('ls_sagepay_redirect_3d/?' . $data));
				}
				else {
					/*
					 * Successful payment. Set order status and mark it as paid.
					 */
					
					$cvv_code = null;
					$avs_code = null;
					$cvv_message = null;
					$avs_message = null;
					$this->process_avs_cv2_result($response_fields, $cvv_code, $cvv_message, $avs_code, $avs_message);

					$this->log_payment_attempt($order, 'Successful payment', 1, $this->prepare_fields_log($fields), $response_fields, $response, $cvv_code, $cvv_message, $avs_code, $avs_message);

					Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
					$order->set_payment_processed();
				}
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($fields);
				
				$error_message = $ex->getMessage();
				if (isset($response_fields['StatusDetail']))
					$error_message = $response_fields['StatusDetail'];
					
				$cvv_code = null;
				$avs_code = null;
				$cvv_message = null;
				$avs_message = null;
				$this->process_avs_cv2_result($response_fields, $cvv_code, $cvv_message, $avs_code, $avs_message);

				$this->log_payment_attempt($order, $error_message, 0, $fields, $response_fields, $response, $cvv_code, $cvv_message, $avs_code, $avs_message);

				if (!$back_end)
					throw new Phpr_ApplicationException($ex->getMessage());
				else
					throw new Phpr_ApplicationException($error_message);
			}
		}
		
		public function authenticate_3d($params)
		{
			$is_backend = array_key_exists(1, $params) ? $params[1] === 'backend' : false;

			$order = null;
			$fields = array();
			$response = array();
			$response_fields = array();
			
			try
			{
				/*
				 * Find order and load payment method settings
				 */

				$order_hash = array_key_exists(0, $params) ? $params[0] : null;
				if (!$order_hash)
					throw new Phpr_ApplicationException('Order not found');

				$order = Shop_Order::create()->find_by_order_hash($order_hash);
				
				if (!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if (!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();

				$host_obj = $order->payment_method;
				
				$payment_method_obj = $host_obj->get_paymenttype_object();
				
				if (!($payment_method_obj instanceof Shop_SagePay_Direct_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$endpoint = $this->endpoints[$host_obj->mode]['CALLBACK'];

				$fields['PARes'] = post('PaRes');
				$fields['MD'] = post('MD');

				$response = $this->post_data($endpoint, $fields);

				/*
				 * Process result
				 */
				
				$response_fields = $this->parse_response($response);
				if (!array_key_exists('Status', $response_fields))
					throw new Phpr_ApplicationException('Invalid payment gateway response.');

				if ($response_fields['Status'] != 'OK' && $response_fields['Status'] != 'AUTHENTICATED' && $response_fields['Status'] != 'REGISTERED')
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
		
				/*
				 * Successful payment. Set order status and mark it as paid.
				 */

				if ($order->set_payment_processed())
				{
					$cvv_code = null;
					$avs_code = null;
					$cvv_message = null;
					$avs_message = null;
					$this->log_payment_attempt($order, 'Successful payment', 1, $this->prepare_fields_log($fields), $response_fields, $response, $cvv_code, $cvv_message, $avs_code, $avs_message);
					Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
					$this->process_avs_cv2_result($response_fields, $cvv_code, $cvv_message, $avs_code, $avs_message);
				}
				
				if (!$is_backend)
				{
					$return_page = $order->payment_method->receipt_page;
					if ($return_page)
						Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
					else 
						throw new Phpr_ApplicationException('SagePay Direct Receipt page is not found.');
				} else 
				{
					Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
				}
			}
			catch (Exception $ex)
			{
				if ($order)
				{
					$cvv_code = null;
					$avs_code = null;
					$cvv_message = null;
					$avs_message = null;
					$this->process_avs_cv2_result($response_fields, $cvv_code, $cvv_message, $avs_code, $avs_message);
					
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), Phpr::$request->get_fields, $response, $cvv_code, $cvv_message, $avs_code, $avs_message);
				}

				if (!$is_backend) {
					$pay_page = Cms_Page::create()->find_by_action_reference('shop:pay');
					$pay_page_url = null;
					if ($pay_page && $order)
						$pay_page_url = $pay_page->url.'/'.$order->order_hash;

					include(PATH_APP.'/modules/shop/payment_types/shop_sagepay_direct_payment/error.htm');
				} else {
					if (!$order)
						include(PATH_APP.'/modules/shop/payment_types/shop_sagepay_direct_payment/error.htm');
					else {
						Phpr::$session->flash['error'] = $ex->getMessage();

						Phpr::$response->redirect(url('/shop/orders/pay/'.$order->id));
					}
				}
			}
		}
		
		public function redirect_3d($params) 
		{
			die(include(PATH_APP.'/modules/shop/payment_types/shop_sagepay_direct_payment/3d_form.htm'));
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in Sage Pay Direct payment method.');
		}
		
		public static function param($data)
		{
			$param = array();
			
			foreach($data as $k => $v)
				$param[] = $k . '=' . urlencode($v);
			
			return implode('&', $param);
		}
	}

?>