<?

	class Shop_PayPal_Pro_Payment extends Shop_PaymentType
	{
		/*
		 * PayPal and Cardinal Centinel url endpoints
		 */
		protected $endpoints = array(
			'PAYPAL' => array(
				'LIVE' => 'api-3t.paypal.com',
				'TEST' => 'api-3t.sandbox.paypal.com'
			),
			'CC' => array(
				'LIVE' => 'https://paypal.cardinalcommerce.com/maps/txns.asp',
				'TEST' => 'https://centineltest.cardinalcommerce.com/maps/txns.asp'
			)
		);
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
				'name'=>'PayPal Pro',
				'description'=>'PayPal Pro payment method, with payment form hosted on your server.'
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
			$host_obj->add_field('test_mode', 'Sandbox Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Use the PayPal Sandbox Test Environment to try out Website Payments. You should be logged into the PayPal Sandbox to work in test mode.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_form_partial($host_obj->get_partial_path('hint.htm'))->tab('Configuration');

				$host_obj->add_field('api_signature', 'API Signature')->tab('Configuration')->renderAs(frm_text)->comment('You can find your API signature, user name and password on PayPal profile page in Account Information/API Access section.', 'above', true)->validation()->fn('trim')->required('Please provide PayPal API signature.');
				$host_obj->add_field('api_user_name', 'API User Name', 'left')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide PayPal API user name.');
				$host_obj->add_field('api_password', 'API Password', 'right')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide PayPal API password.');
			}
			
			$host_obj->add_field('paypal_action', 'PayPal Action', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('Action PayPal should perform with customer\'s credit card.', 'above');

			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');

			$host_obj->add_field('adjust_tax_value', 'Adjust tax value')->tab('Configuration')->renderAs(frm_checkbox)->comment('If needed for the order, the tax amount sent to PayPal will be adjusted to accommodate for tax inclusive pricing and increased product price precision.', 'above');
			$host_obj->add_field('skip_order_details', 'Do not submit order details')->tab('Configuration')->renderAs(frm_checkbox)->comment('When selected, the order details (tax, shipping cost, order items) will not be sent to PayPal.', 'above');
			
			if ($context !== 'preview')
			{
				$host_obj->add_field('cc_test_mode', 'Test Mode')->tab('3D Secure')->renderAs(frm_onoffswitcher)->comment('Use the Cardinal Centinel Test Environment to test 3D Secure.', 'above');

				$host_obj->add_form_partial($host_obj->get_partial_path('3dsecure_hint.htm'))->tab('3D Secure');

				$host_obj->add_field('enable_3d_secure', 'Enable 3D Secure')->tab('3D Secure')->renderAs(frm_checkbox)->comment('To use this feature, you must first create an account with Cardinal Centinel.', 'above');
				$host_obj->add_field('enable_severe_3d_secure', 'Strict 3D Secure Card Validation')->tab('3D Secure')->renderAs(frm_checkbox)->comment('Select this to allow only transactions where liability is shifted from the merchant; this will prevent payment in cases when 3DSecure service is unavailable, there are issues with your Cardinal Centiel account and for cards that 3DSecure does not apply for (eg. prepaid/corporate credit cards).', 'above');
				$host_obj->add_field('cc_pid', 'Cardinal Centinel Processor ID')->tab('3D Secure')->renderAs(frm_text);
				$host_obj->add_field('cc_mid', 'Cardinal Centinel Merchant ID')->tab('3D Secure')->renderAs(frm_text);
				$host_obj->add_field('cc_transaction_pass', 'Cardinal Centinel Transaction Password')->tab('3D Secure')->renderAs(frm_text);
			}
		}
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}
		
		public function get_paypal_action_options($current_key_value = -1)
		{
			$options = array(
				'Sale'=>'Capture',
				'Authorization'=>'Authorization only'
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Used to ensure all Cardinal Centinel data is entered if 3D Secure is enabled
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
			if($host_obj->enable_3d_secure && !strlen($host_obj->cc_pid))
				$host_obj->field_error('cc_pid', 'Please enter the Cardinal Centinel Processor ID to enable 3D Secure.');
			if($host_obj->enable_3d_secure && !strlen($host_obj->cc_mid))
				$host_obj->field_error('cc_mid', 'Please enter the Cardinal Centinel Merchant ID to enable 3D Secure.');
			if($host_obj->enable_3d_secure && !strlen($host_obj->cc_transaction_pass))
				$host_obj->field_error('cc_transaction_pass', 'Please enter the Cardinal Centinel Transaction Password to enable 3D Secure.');
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
			$host_obj->add_field('CREDITCARDTYPE', 'Credit Card Type')->renderAs(frm_dropdown)->comment('Please select a credit card type.', 'above')->validation()->fn('trim')->required();
			$host_obj->add_field('FIRSTNAME', 'First Name', 'left')->renderAs(frm_text)->comment('Cardholder first name', 'above')->validation()->fn('trim')->required('Please specify a cardholder first name');
			$host_obj->add_field('LASTNAME', 'Last Name', 'right')->renderAs(frm_text)->comment('Cardholder last name', 'above')->validation()->fn('trim')->required('Please specify a cardholder last name');
			$host_obj->add_field('ACCT', 'Credit Card Number', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a credit card number')->regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');
			$host_obj->add_field('CVV2', 'CVV2', 'right')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify Card Verification Number')->numeric();

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
			
			$host_obj->add_field('ISSUENUMBER', 'Issue Number')->comment('Please specify the Issue Number or Start Date for Solo and Maestro cards', 'above')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();

			$host_obj->add_field('STARTDATE_MONTH', 'Start Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();
			$host_obj->add_field('STARTDATE_YEAR', 'Start Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();
		}
		
		public function get_CREDITCARDTYPE_options()
		{
			return array(
				'Visa'=>'Visa',
				'MasterCard'=>'Master Card',
				'Discover'=>'Discover',
				'Amex'=>'American Express',
				'Maestro'=>'Maestro',
				'Solo'=>'Solo'
			);
		}

		/*
		 * Register access points for 3D Secure
		 * ls_paypal_pro_authenticate_3d is where the customer lands when returning from the card issuer
		 * ls_paypal_pro_redirect_3d is used to redirect the customer to card issuer page
		 */
		public function register_access_points()
		{
			return array(
				'ls_paypal_pro_authenticate_3d'=>'authenticate_3d',
				'ls_paypal_pro_redirect_3d'=>'redirect_3d'
			);
		}

		/**
		 * Returns true if the payment type is applicable for a specified order amount
		 * @param float $amount Specifies an order amount
		 * @param $host_obj ActiveRecord object to add fields to
		 * @return true
		 */
		public function is_applicable($amount, $host_obj)
		{
			$currency_converter = Shop_CurrencyConverter::create();
			$currency = Shop_CurrencySettings::get();

			return $currency_converter->convert($amount, $currency->code, 'USD') <= 10000;
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
			$errno = null;
			$errorstr = null;

			$fp = null;
			try
			{
				$fp = @fsockopen('ssl://'.$endpoint, 443, $errno, $errorstr, 60);
			}
			catch (Exception $ex) {}
			if (!$fp)
				throw new Phpr_SystemException("Error connecting to PayPal server. Error number: $errno, error: $errorstr");

			$poststring = $this->format_form_fields($fields);

			fputs($fp, "POST /nvp HTTP/1.1\r\n"); 
			fputs($fp, "Host: $endpoint\r\n"); 
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n"); 
			fputs($fp, "Content-length: ".strlen($poststring)."\r\n"); 
			fputs($fp, "Connection: close\r\n\r\n"); 
			fputs($fp, $poststring . "\r\n\r\n"); 

			$response = null;
			while(!feof($fp))
				$response .= fgets($fp, 4096);
				
			return $response;
		}

		private function parse_response($response)
		{
			$matches = array();
			preg_match('/Content\-Length:\s([0-9]+)/i', $response, $matches);
			if (!count($matches))
				throw new Phpr_ApplicationException('Invalid PayPal response');

			$elements = substr($response, $matches[1]*-1);
			$elements = explode('&', $elements);

			$result = array();
			foreach ($elements as $element)
			{
				$element = explode('=', $element);
				if (isset($element[0]) && isset($element[1]))
					$result[$element[0]] = urldecode($element[1]);
			}
			
			return $result;
		}

		private function prepare_fields_log($fields)
		{
			unset($fields['PWD']);
			unset($fields['USER']);
			unset($fields['SIGNATURE']);
			unset($fields['VERSION']);
			unset($fields['METHOD']);
			unset($fields['CVV2']);
			$fields['ACCT'] = '...'.substr($fields['ACCT'], -4);
			unset($fields['XID']);
			
			return $fields;
		}
		
		protected function get_avs_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'AVS response code is empty';
			
			$status_names = array(
				'A'=>'Address only match (no ZIP)',
				'B'=>'Address only match (no ZIP)',
				'C'=>'No match',
				'D'=>'Address and Postal Code match',
				'E'=>'Not allowed for MOTO (Internet/Phone) transactions',
				'F'=>'Address and Postal Code match',
				'G'=>'Not applicable',
				'I'=>'Not applicable',
				'N'=>'No match',
				'P'=>'Postal Code only match (no Address)',
				'R'=>'Retry/not applicable',
				'S'=>'Service not Supported',
				'U'=>'Unavailable/Not applicable',
				'W'=>'Nine-digit ZIP code match (no Address)',
				'X'=>'Exact match',
				'Y'=>'Address and five-digit ZIP match',
				'Z'=>'Five-digit ZIP code match (no Address)',
				'0'=>'All the address information matched',
				'1'=>'None of the address information matched',
				'2'=>'Part of the address information matched',
				'3'=>'The merchant did not provide AVS information. Not processed.',
				'4'=>'Address not checked, or acquirer had no response',
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
				'N'=>'No match',
				'P'=>'Not processed',
				'S'=>'Service not supported',
				'U'=>'Service not available',
				'X'=>'No response',
				'0'=>'Match',
				'1'=>'No match',
				'2'=>'The merchant has not implemented CVV2 code handling',
				'3'=>'Merchant has indicated that CVV2 is not present on card',
				'4'=>'Service not available'
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown CCV response code';
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
			$validation->add('CREDITCARDTYPE', 'Credit card type')->fn('trim')->required('Please specify a credit card type.');
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');
			
			$validation->add('ISSUENUMBER', 'Issue Number')->fn('trim')->numeric();

			$validation->add('STARTDATE_MONTH', 'Start Month', 'left')->fn('trim')->numeric();
			$validation->add('STARTDATE_YEAR', 'Start Year', 'right')->fn('trim')->numeric();

			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');
			$validation->add('CVV2', 'CVV2')->fn('trim')->required('Please specify CVV2 value.')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.');

			try
			{
				if (!$validation->validate($data))
					$validation->throwException();
			} catch (Exception $ex)
			{
				$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
				throw $ex;
			}

			//3D Secure: before proceeding if the payment form was previously submitted ($_SESSION['PayPal_form_hash'] is set) - if yes, check that the payment form values are unchanged.
			if(array_key_exists('PayPal_form_hash', $_SESSION) && $this->payment_form_hash($data) != $_SESSION['PayPal_form_hash'] && array_key_exists('PayPalProFields', $_SESSION))
			{
				unset($_SESSION['PayPalProFields']);
				unset($_SESSION['PayPalPro']);
			}
			$_SESSION['PayPal_form_hash'] = $this->payment_form_hash($data);

			/*
			 * 3D Secure lookup performed before going to PayPal
			 * Only to be performed for Visa, MasterCard & Maestro, if enabled, not in the backend and only if it hasn't been performed yet for this payment form data
			 */
			if(($data['CREDITCARDTYPE'] == 'Visa' || $data['CREDITCARDTYPE'] == 'MasterCard' || $data['CREDITCARDTYPE'] == 'Maestro')
				&& !$back_end && $host_obj->enable_3d_secure
				&& (!array_key_exists('PayPalProFields', $_SESSION) || !array_key_exists('AUTHSTATUS3DS', $_SESSION['PayPalProFields'])))
			{
				try
				{
					$response = $this->get_3d_secure_lookup_response($host_obj, $validation, $order);
					if(get_class($response) != 'CentinelClient')
					{
						//missing response, possibly due to a timeout while connecting
						if($host_obj->enable_severe_3d_secure)
							throw new Phpr_ApplicationException("Unable to authenticate: 3D Secure authentication unavailable.");
					}
					$error_no = $response->getValue("ErrorNo");

					if(intval($error_no) > 0)
					{
						//is strict 3DSecure is not enabled, ignore timeout errors and merchant account errors and proceed with payment
						if(!$host_obj->enable_severe_3d_secure && $error_no != '8030' && $error_no != '1001')
							throw new Phpr_ApplicationException("Unable to authenticate: ".$response->getValue("ErrorDesc")." (".$error_no.")");
					}
					else
					{
						$enrolled =  $response->getValue("Enrolled");
						$_SESSION['PayPalProFields']['MPIVENDOR3DS'] = $enrolled;
						$asc_url = $response->getValue("ACSUrl");

						if($enrolled == 'Y' && strlen($asc_url))
						{
							//CC is enrolled in the 3D secure program, direct the customer to the card issuer to perform the verification
							$authentication_url = root_url('/ls_paypal_pro_authenticate_3d/'.$order->order_hash, true);
							
							//redirect to the card issuer
							$fields['PaReq'] = $response->getValue("Payload");
							$fields['TermUrl'] = $authentication_url;
							$fields['ASCurl'] = $asc_url;

							$_SESSION['PayPalPro']['3d']['ASCurl'] = $asc_url;
							$_SESSION['PayPalPro']['3d']['TermUrl'] = $authentication_url;
							$_SESSION['PayPalPro']['3d']['PaReq'] = $response->getValue("Payload");

							$_SESSION['PayPalPro']['TransactionId'] = $response->getValue("TransactionId");
							$_SESSION['PayPalPro']['order_hash'] = $order->order_hash;
							//return false to prevent the payment form from redirecting
							return false;
						}
						elseif($enrolled == 'N')
						{
							//card is not enrolled in 3D secure, complete the payment
							$_SESSION['PayPalProFields']['ECI3DS'] = $response->getValue("EciFlag");
							$_SESSION['PayPalProFields']['VERSION'] = '59.0';
						}
						elseif($enrolled == 'U' && intval($error_no) < 1)
						{
							//authentication was unavailable
							if($host_obj->enable_severe_3d_secure)
								throw new Phpr_ApplicationException("Unable to authenticate.");
						}
					}
				}
				catch(Exception $ex)
				{
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
					throw new Phpr_ApplicationException($ex->getMessage());
				}
			}
			
			/*
			 * Send request
			 */
			
			@set_time_limit(3600);
			$endpoint = $host_obj->test_mode ? $this->endpoints['PAYPAL']['TEST'] : $this->endpoints['PAYPAL']['LIVE'];
			$fields = array();
			$response = null;
			$response_fields = array();

			try
			{
				$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];
				
				if (strlen($validation->fieldValues['STARTDATE_MONTH']))
					$startMonth = $validation->fieldValues['STARTDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['STARTDATE_MONTH'] : $validation->fieldValues['STARTDATE_MONTH'];
				else
					$startMonth = null;

				$userIp = Phpr::$request->getUserIp();
				if ($userIp == '::1')
					$userIp = '192.168.0.1';

				//include fields added by 3D Secure if any are set
				if(array_key_exists('PayPalProFields', $_SESSION))
					foreach($_SESSION['PayPalProFields'] as $k => $v)
					{
						$fields[$k] = $v;
					}

				$fields['PWD'] = $host_obj->api_password;
				$fields['USER'] = $host_obj->api_user_name;
				$fields['SIGNATURE'] = $host_obj->api_signature;
				if(!array_key_exists('VERSION', $fields))
					$fields['VERSION'] = '3.0';
				$fields['METHOD'] = 'DoDirectPayment';

				$fields['CREDITCARDTYPE'] = $validation->fieldValues['CREDITCARDTYPE'];
				$fields['ACCT'] = $validation->fieldValues['ACCT'];
				$fields['EXPDATE'] = $expMonth.$validation->fieldValues['EXPDATE_YEAR'];
				$fields['STARTDATE'] = $startMonth.$validation->fieldValues['STARTDATE_YEAR'];
				$fields['CVV2'] = $validation->fieldValues['CVV2'];
				$fields['ISSUENUMBER'] = $validation->fieldValues['ISSUENUMBER'];
				$fields['CURRENCYCODE'] = Shop_CurrencySettings::get()->code;
				
				$fields['FIRSTNAME'] = $validation->fieldValues['FIRSTNAME'];
				$fields['LASTNAME'] = $validation->fieldValues['LASTNAME'];
				$fields['IPADDRESS'] = $userIp;
				$fields['STREET'] = $order->billing_street_addr;
				
				if ($order->billing_state)
					$fields['STATE'] = $order->billing_state->code;
					
				$fields['COUNTRY'] = $order->billing_country->name;
				$fields['CITY'] = $order->billing_city;
				$fields['ZIP'] = $order->billing_zip;
				$fields['COUNTRYCODE'] = $order->billing_country->code;
				$fields['PAYMENTACTION'] = $host_obj->paypal_action;

				if(!$host_obj->skip_order_details)
				{
					$fields['SHIPPINGAMT'] = $order->shipping_quote;
					$fields['TAXAMT'] = number_format($order->goods_tax + $order->shipping_tax, 2, '.', '');

					$item_index = 0;
					$item_amount = 0;
					foreach ($order->items as $item)
					{
						$fields['L_NAME'.$item_index] = mb_substr($item->output_product_name(true, true), 0, 127);
						$fields['L_AMT'.$item_index] = number_format($item->unit_total_price, 2, '.', '');
						$item_amount = $item_amount + ($fields['L_AMT'.$item_index]*$item->quantity);
						$fields['L_QTY'.$item_index] = $item->quantity;
						$item_index++;
					}
					$fields['ITEMAMT'] = $order->subtotal;
					
					if (!ceil($order->subtotal) && $order->shipping_quote)
					{
						$fields['SHIPPINGAMT'] = '0.00';
						
						$fields['L_NAME'.$item_index] = 'Shipping';
						$fields['L_AMT'.$item_index] = number_format($order->shipping_quote, 2, '.', '');
						$fields['L_QTY'.$item_index] = 1;
						$item_index++;
						
						$fields['ITEMAMT'] = $order->shipping_quote;
					}

					if($host_obj->adjust_tax_value)
					{
						//If the sum of item, shipping and tax amounts differs from the stored order total,
						//adjust the tax amount for the difference
						$order_total = $fields['ITEMAMT'] + $fields['SHIPPINGAMT'] + $fields['TAXAMT'];
						if(strval($order_total) != strval($order->total))
						{
							$difference = $order_total - $order->total;
							if($fields['TAXAMT'] > 0 && $fields['TAXAMT'] >= $difference)
								$fields['TAXAMT'] = $fields['TAXAMT'] - $difference;
						}
					}
				}

				$fields['AMT'] = $order->total;
				
				// if ($order->discount)
				// {
				// 	$fields['L_NAME'.$item_index] = 'Discount';
				// 	$fields['L_AMT'.$item_index] = number_format(-1*$order->discount, 2, '.', '');
				// 	$fields['L_QTY'.$item_index] = 1;
				// 	$item_index++;
				// }

				$fields['SHIPTONAME'] = $order->shipping_first_name.' '.$order->shipping_last_name;
				$fields['SHIPTOSTREET'] = $order->shipping_street_addr;
				$fields['SHIPTOCITY'] = $order->shipping_city;
				$fields['SHIPTOCOUNTRYCODE'] = $order->shipping_country->code;
				
				if ($order->shipping_state)
					$fields['SHIPTOSTATE'] = $order->shipping_state->code;

				$fields['SHIPTOPHONENUM'] = $order->shipping_phone;
				$fields['SHIPTOZIP'] = $order->shipping_zip;
				
				$fields['INVNUM'] = $order->id;
				$fields['ButtonSource'] = 'LemonStand_Cart_DP';

				$response = $this->post_data($endpoint, $fields);

				/*
				 * Process result
				 */

				$response_fields = $this->parse_response($response);
				if (!isset($response_fields['ACK']))
					throw new Phpr_ApplicationException('Invalid PayPal response.');
					
				if ($response_fields['ACK'] !== 'Success' && $response_fields['ACK'] !== 'SuccessWithWarning')
				{
					for ($i=5; $i>=0; $i--)
					{
						if (isset($response_fields['L_LONGMESSAGE'.$i]))
							throw new Phpr_ApplicationException($response_fields['L_LONGMESSAGE'.$i]);
					}

					throw new Phpr_ApplicationException('Invalid PayPal response.');
				}

				/*
				 * Successful payment. Set order status and mark it as paid.
				 */

				$fields = $this->prepare_fields_log($fields);
				
				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					$fields, 
					$response_fields, 
					$response,
					$response_fields['CVV2MATCH'],
					$this->get_ccv_status_text($response_fields['CVV2MATCH']),
					$response_fields['AVSCODE'], 
					$this->get_avs_status_text($response_fields['AVSCODE'])
				);

				if(array_key_exists('TRANSACTIONID', $response_fields))
				{
					if($host_obj->paypal_action == 'Sale')
					{
						$status = 'Captured';
						$status_code = 'captured';
					}
					else
					{
						$status = 'Authorized';
						$status_code = 'authorized';
					}
					$this->update_transaction_status($host_obj, $order, $response_fields['TRANSACTIONID'], $status, $status_code);
				}

				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
				$order->set_payment_processed();

				//clear 3D Secure info from session
				unset($_SESSION['PayPalProFields']);
				unset($_SESSION['PayPalPro']);
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($fields);
				
				$cvv_code = null;
				$cvv_message = null;
				$avs_code = null;
				$avs_message = null;
				
				if (array_key_exists('CVV2MATCH', $response_fields))
				{
					$cvv_code = $response_fields['CVV2MATCH'];
					$cvv_message = $this->get_ccv_status_text($response_fields['CVV2MATCH']);
					$avs_code = $response_fields['AVSCODE'];
					$avs_message = $this->get_avs_status_text($response_fields['AVSCODE']);
				}
				
				$this->log_payment_attempt(
					$order, 
					$ex->getMessage(), 
					0, 
					$fields, 
					$response_fields, 
					$response,
					$cvv_code,
					$cvv_message,
					$avs_code,
					$avs_message
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in PayPal Pro payment method.');
		}

		/**
		 * Creates a salted hash value of the payment form values
		 * Used to check if the payment form info was changed between 3D Secure
		 * authorization and the final payment
		 */
		protected function payment_form_hash($post)
		{
			$string = $post['ACCT'].$post['CVV2'].$post['EXPDATE_MONTH'].$post['EXPDATE_YEAR'];
			$hash = Phpr_SecurityFramework::create()->salted_hash($string);
			return $hash;
		}

		/**
		 * Used to display any errors encountered during 3D Secure authorization
		 * If a javascript function PayPalErrorCallback(string) is defined in the parent window, that is used.
		 * If not, we assume the default iframe implementation is being used and display the error there.
		 * Should only be used to display the error received after customer returns to site from credit card issuer website
		 * Optionally logs the payment attempt for the order
		 * Also clears session info for 3DSecure authentication so the customer can retry authenticating the same card
		 * @param $custom_message string Message to display to the customer
		 * @param $log_message string Log message to be entered for the payment attempt
		 * @param $order Shop_Order object that the payment attempt is to be recorded for (if provided, payment attempt will be logged)
		 * @param $response string Raw gateway response text
		 */
		private function authentication_error_display($host_obj, $custom_message, $log_message, $order, $response = null)
		{
			unset($_SESSION['PayPalProFields']);
			unset($_SESSION['PayPalPro']);

			if($log_message && $order)
			{
				if(is_array($response))
					$this->log_payment_attempt($order, $log_message, 0, array(), $response, null);
				else
					$this->log_payment_attempt($order, $log_message, 0, array(), array(), $response);
			}

			$string = "<script type='text/javascript'>
				if(typeof(window.parent.PayPalErrorCallback) == 'function')
					window.parent.PayPalErrorCallback('".$custom_message."');
				else
				{
					if(window.parent.document.getElementById('paypal_submit_button'))
						window.parent.document.getElementById('paypal_submit_button').disabled = false;
					if(window.parent.document.getElementById('paypal_pro_payment_form_div-".$host_obj->id."'))
						window.parent.document.getElementById('paypal_pro_payment_form_div-".$host_obj->id."').style.display='';
					if(window.parent.document.getElementById('paypal_3DSecure_head'))
						window.parent.document.getElementById('paypal_3DSecure_head').innerHTML='';";
			if($custom_message)
				$string .= "if(window.parent.document.getElementById('paypal_3DSecure_result'))
				{
					window.parent.document.getElementById('paypal_3DSecure_result').innerHTML = '".str_replace("'", "\'", $custom_message)."'
					window.parent.document.getElementById('paypal_3DSecure_result').setAttribute('class', 'authentication_error');
				}";
			$string .= "if(window.parent.document.getElementById('paypal_3DSecure_iframe'))
					window.parent.document.getElementById('paypal_3DSecure_iframe').innerHTML = '';
				}
				</script>";
			
			return $string;
		}

		/**
		 * Redirects the page to the card issuer to perform 3D Secure authorization
		 */
		public function redirect_3d($params)
		{
			if(array_key_exists('PayPalPro', $_SESSION))
				echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
					"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
					<html xmlns="http://www.w3.org/1999/xhtml">
						<head></head>
						<body>
							<form name="form" id="3d_form" action="'.$_SESSION['PayPalPro']['3d']['ASCurl'].'" method="POST">
								<input type="hidden" name="PaReq" value="'.$_SESSION['PayPalPro']['3d']['PaReq'].'" />
								<input type="hidden" name="TermUrl" value="'.$_SESSION['PayPalPro']['3d']['TermUrl'].'" />
								<input type="hidden" name="MD" value=" " />
								<noscript>
									<p>Please click button below to Authenticate your card</p><input type="submit" value="Go" /></p>
								</noscript>
							</form>
							<script type="text/javascript">setTimeout(function() { document.getElementById("3d_form").submit(); }, 50);</script>
						</body>
					</html>';
		}

		/**
		 * Step 1 of 3D Secure: sends the credit card info to Cardinal Centinel
		 * to determine if card is enrolled in 3D Secure
		 * @param $host_obj ActiveRecord
		 * @param $validation Phpr_Validation
		 * @param $order Shop_Order
		 * @return $result CentinelClient
		 */
		private function get_3d_secure_lookup_response($host_obj, $validation, $order)
		{
			try
			{
				$this->init_sdk($host_obj);
				if(!strlen($host_obj->cc_pid) || !strlen($host_obj->cc_mid) || !strlen($host_obj->cc_transaction_pass))
					throw new Phpr_ApplicationException("3D Secure error: payment method configuration missing!");

				$cc_endpoint = $host_obj->cc_test_mode ? $this->endpoints['CC']['TEST'] : $this->endpoints['CC']['LIVE'];
				$currency = Shop_CurrencySettings::get();
				$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];

				$centinelClient = new CentinelClient();
				$centinelClient->Add("MsgType", "cmpi_lookup");
				$centinelClient->Add("Version", "1.7");
				$centinelClient->Add("ProcessorId", h($host_obj->cc_pid));
				$centinelClient->Add("MerchantId", h($host_obj->cc_mid));
				$centinelClient->Add("TransactionPwd", h($host_obj->cc_transaction_pass));
				$centinelClient->Add("TransactionType", "C");
				$centinelClient->Add("TransactionMode", "S");
				$centinelClient->Add("Amount", ($order->total * 100));
				$centinelClient->Add("CurrencyCode", $currency->iso_4217_code);
				$centinelClient->Add("CardNumber", $validation->fieldValues['ACCT']);
				$centinelClient->Add("CardExpMonth", $expMonth);
				$centinelClient->Add("CardExpYear", $validation->fieldValues['EXPDATE_YEAR']);
				$centinelClient->Add("OrderNumber", h($order->id));
				$centinelClient->sendHTTP($cc_endpoint, "10", "20");
				return $centinelClient;
			}
			catch (Phpr_ApplicationException $ex)
			{
				$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
			}
			catch (Exception $ex)
			{
				$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}

		/**
		 * Step 2 of 3D Secure
		 * Used when customer returns from card issuer page to the store, called in the iframe
		 * CC posts to this page with a single field: $_POST['PaRes'] which is then used in a cmpi_authenticate message to CC
		 */
		public function authenticate_3d($params)
		{
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
				$this->init_sdk($host_obj);
				if (!($payment_method_obj instanceof Shop_PayPal_Pro_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');
				
				$cc_endpoint = $host_obj->cc_test_mode ? $this->endpoints['CC']['TEST'] : $this->endpoints['CC']['LIVE'];
				
				//authoneticate the 3d secure result
				$centinelClient = new CentinelClient;
				$centinelClient->Add("MsgType", "cmpi_authenticate");
				$centinelClient->Add("Version", "1.7");
				$centinelClient->Add("ProcessorId", h($host_obj->cc_pid));
				$centinelClient->Add("MerchantId", h($host_obj->cc_mid));
				$centinelClient->Add("TransactionPwd", h($host_obj->cc_transaction_pass));
				$centinelClient->Add("TransactionType", "C");
				$centinelClient->Add("TransactionId", $_SESSION['PayPalPro']['TransactionId']);
				$centinelClient->Add("PAResPayload", h(post('PaRes')));

				$centinelClient->sendHTTP($cc_endpoint, "10", "20");

				$pares_status = $centinelClient->getValue("PAResStatus");
				$error_no = $centinelClient->getValue("ErrorNo");

				if($error_no > 0 && $host_obj->enable_severe_3d_secure)
				{
					echo $this->authentication_error_display($host_obj, "Authentication failed! Please try again or use a different CC/Payment method.", "error log message here", $order, $centinelClient->response_raw);
				}
				elseif($pares_status == 'Y' || $pares_status == 'A' || (!$host_obj->enable_severe_3d_secure && ($pares_status == 'U' || $pares_status == '')))
				{
					$signature_verification = $centinelClient->getValue("SignatureVerification");

					//if $signature_verification is 'N', then we failed to verify the authorization and the chargeback liability remains on the merchant
					if($signature_verification == 'Y' || (!$host_obj->enable_severe_3d_secure && ($pares_status == '' || $signature_verification == 'N')))
					{
						//fields we need to pass to PayPal to get the liability shift
						$_SESSION['PayPalProFields']['VERSION'] = '59.0';
						$_SESSION['PayPalProFields']['AUTHSTATUS3DS'] = $centinelClient->getValue("PAResStatus");
						$_SESSION['PayPalProFields']['CAVV'] = $centinelClient->getValue("Cavv");
						$_SESSION['PayPalProFields']['ECI3DS'] = $centinelClient->getValue("EciFlag");
						$_SESSION['PayPalProFields']['XID'] = $centinelClient->getValue("Xid");

						//authentication successful, close the iframe and ask the customer to complete the payment
						echo "<script type='text/javascript'>
							if(typeof(window.parent.PayPalSuccessCallback) == 'function')
								window.parent.PayPalSuccessCallback();
							else
							{
								var complete_payment = 'Complete Payment';
								window.parent.document.getElementById('paypal_submit_button').disabled = false;
								if(window.parent.document.getElementById('complete_payment_text'))
								{
									window.parent.document.getElementById('paypal_submit_button').value = window.parent.document.getElementById('complete_payment_text').value;
									complete_payment = window.parent.document.getElementById('complete_payment_text').value;
								}
								window.parent.document.getElementById('paypal_pro_payment_form_div-".$host_obj->id."').style.display='';
								window.parent.document.getElementById('paypal_3DSecure_info').style.display='none';
								window.parent.document.getElementById('paypal_3DSecure_head').innerHTML = '';
								window.parent.document.getElementById('paypal_3DSecure_result').innerHTML = '<p class=\"paypal_message\">Authentication passed. Click '+complete_payment+' to complete your payment.</p>';
								window.parent.document.getElementById('paypal_3DSecure_result').setAttribute('class', 'authentication_success');
								window.parent.document.getElementById('paypal_3DSecure_iframe').innerHTML= '';
							}
							</script>";
					}
					else
						echo $this->authentication_error_display($host_obj, "<p class=\"paypal_message\">Authentication failed. Please try again or use a different credit card/payment method.</p>", "3D Secure signature verification failed", $order, $centinelClient->response);
				}
				elseif($pares_status == 'N')
					echo $this->authentication_error_display($host_obj, "<p class=\"paypal_message\">Authentication failed. Please try again or use a different credit card/payment method.</p>", "3D Secure authentication failed, transaction not permitted", $order, $centinelClient->response);
				elseif($pares_status == 'U')
				{
					echo $this->authentication_error_display($host_obj, "<p class=\"paypal_message\">Unable to authenticate. Please try again or use a different credit card/payment method.</p>", "3D Secure unavailable", $order, $centinelClient->response);
				}
			}
			catch (Exception $ex)
			{
				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}

		/*
		* Includes Cardinal Centinel Thin Client
		*/
		protected function init_sdk($host_obj)
		{
			if (self::$sdk_initialized)
				return;
			
			self::$sdk_initialized = true;
			require_once(PATH_APP.'/modules/shop/payment_types/shop_paypal_pro_payment/CentinelClient.php');
		}

		public function extend_transaction_preview($payment_method_obj, $controller, $transaction)
		{
			$payment_method_obj->load_xml_data();
			if($payment_method_obj->test_mode)
				$url = "https://sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=";
			else $url = "https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=";
			$controller->viewData['url'] = $url;
			$controller->viewData['transaction_id'] = $transaction->transaction_id;
			$controller->renderPartial(PATH_APP.'/modules/shop/payment_types/shop_paypal_pro_payment/_payment_transaction.htm');
		}
	}

?>