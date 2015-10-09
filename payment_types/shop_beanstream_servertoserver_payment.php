<?

	class Shop_Beanstream_ServerToServer_Payment extends Shop_PaymentType
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
				'name'=>'Beanstream Server-To-Server Integration',
				'description'=>'Beanstream server-to-server protocol implementation. Prevents browser redirects from occurring during the transaction process.'
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
			if ($context !== 'preview')
			{
				$host_obj->add_field('merchant_id', 'Merchant ID')->tab('Configuration')->renderAs(frm_text)->comment('Include the 9-digit Beanstream ID number here.', 'above')->validation()->fn('trim')->required('Please provide Merchant ID.');
			}

			$host_obj->add_field('transaction_type', 'Transaction Type')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above', true);
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

		public function get_transaction_type_options($current_key_value = -1)
		{
			$options = array(
				'PA'=>'Pre-authorization',
				'P'=>'Purchase'
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
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host_obj)
		{
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
			$host_obj->add_field('PHONE', 'Phone number')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a customer phone number')->numeric();

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
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
			$poststring = $this->format_form_fields($fields);
			
			$ch = curl_init(); 
			curl_setopt( $ch, CURLOPT_POST, 1 );
			
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $endpoint);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $poststring);

			$response = curl_exec($ch);
			
			if (curl_errno($ch))
				throw new Phpr_ApplicationException( "Error connecting the payment gateway: ".curl_error($ch) );
			else
				curl_close($ch);
				
			return $response;
		}

		private function parse_response($response)
		{
			$elements = explode('&', $response);

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
			if (isset($fields['merchant_id']))
				unset($fields['merchant_id']);

			if (isset($fields['trnCardNumber']))
				$fields['trnCardNumber'] = '...'.substr($fields['trnCardNumber'], -4);
			
			if (isset($fields['trnCardCvd']))
				unset($fields['trnCardCvd']);

			return $fields;
		}

		private function prepare_response_log($response)
		{
			return $response;
		}
		
		protected function get_cvd_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'CVD response code is empty';

			$status_names = array(
				'1'=>'CVD Match',
				'4'=>'CVD Should have been present',
				'2'=>'CVD Mismatch',
				'5'=>'CVD Issuer unable to process request',
				'3'=>'CVD Not Verified',
				'6'=>'CVD Not Provided'
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown CVD response code';
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
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');
			$validation->add('PHONE', 'Phone number')->fn('trim')->required('Please specify a phone number.');

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
				
			/*
			 * Send request
			 */
			
			@set_time_limit(3600);
			
			$endpoint = 'https://www.beanstream.com/scripts/process_transaction.asp';

			$fields = array();
			$response = null;
			$response_fields = array();
			$currency = Shop_CurrencySettings::get();

			try
			{
				$validation->fieldValues['EXPDATE_MONTH'] = (int)$validation->fieldValues['EXPDATE_MONTH'];
				$validation->fieldValues['EXPDATE_YEAR'] = (int)$validation->fieldValues['EXPDATE_YEAR'];
				
				$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];
				$expYear = $validation->fieldValues['EXPDATE_YEAR'] > 2000 ? $validation->fieldValues['EXPDATE_YEAR'] - 2000 : $validation->fieldValues['EXPDATE_YEAR'];
				if ($expYear < 10)
					$expYear = '0'.$expYear;

				$userIp = Phpr::$request->getUserIp();
				
				$fields = array();
				$fields['requestType'] = 'BACKEND';
				$fields['merchant_id'] = $host_obj->merchant_id;
				$fields['trnCardOwner'] = $validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME'];
				$fields['trnCardNumber'] = $validation->fieldValues['ACCT'];
				$fields['trnCardCvd'] = $validation->fieldValues['CVV2'];

				$fields['trnExpMonth'] = $expMonth;
				$fields['trnExpYear'] = $expYear;
				$fields['trnOrderNumber'] = $order->id;
				$fields['trnAmount'] = $order->total;
				$fields['ordEmailAddress'] = $order->billing_email;
				$fields['ordName'] = $order->billing_first_name.' '.$order->billing_last_name;
				$fields['ordPhoneNumber'] = $validation->fieldValues['PHONE'];
				$fields['ordAddress1'] = $order->billing_street_addr;
				$fields['ordAddress2'] = '';
				$fields['ordCity'] = $order->billing_city;
				$fields['trnType'] = $host_obj->transaction_type;
				
				$state_code = '--';

				if ($order->billing_state && ($order->billing_country->code == 'US' || $order->billing_country->code == 'CA'))
					$state_code = $order->billing_state->code;

				$fields['ordProvince'] = $state_code;

				$fields['ordCountry'] = $order->billing_country->code;
				$fields['ordPostalCode'] = $order->billing_zip;
				$fields['customerIP'] = $userIp;

				/*
				 * Post request
				 */
				
				$response = $this->post_data($endpoint, $fields);

				/*
				 * Process result
				 */

				$response_fields = $this->parse_response($response);

				if (!isset($response_fields['trnApproved']))
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
					
				if (!isset($response_fields['trnApproved']) || ($response_fields['trnApproved'] !== '1'))
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
						
				/*
				 * Successful payment. Set order status and mark it as paid.
				 */
				
				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					$this->prepare_fields_log($fields), 
					$this->prepare_fields_log($response_fields), 
					$this->prepare_response_log($response), 
					$response_fields['cvdId'],
					$this->get_cvd_status_text($response_fields['cvdId']),
					$response_fields['avsId'],
					$response_fields['avsMessage']
				);
			
				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
				$order->set_payment_processed();
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($fields);
				
				$error_message = $ex->getMessage();
				if (isset($response_fields['messageText']))
					$error_message = strip_tags(str_replace('<br>', ' ', $response_fields['messageText']));
					
				$cvv_code = null;
				$cvv_message = null;
				$avs_code = null;
				$avs_message = null;
				
				if (array_key_exists('avsId', $response_fields))
				{
					$avs_code = $response_fields['avsId'];
					$avs_message = $response_fields['avsMessage'];
				}
				
				if (array_key_exists('cvdId', $response_fields))
				{
					$cvv_code = $response_fields['cvdId'];
					$cvv_message = $this->get_cvd_status_text($cvv_code);
				}
				
				$this->log_payment_attempt(
					$order, 
					$error_message, 
					0, 
					$fields, 
					$this->prepare_fields_log($response_fields), 
					$this->prepare_response_log($response),
					$cvv_code,
					$cvv_message,
					$avs_code,
					$avs_message
				);

				throw new Phpr_ApplicationException($error_message);
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in HSBC payment method.');
		}
	}

?>