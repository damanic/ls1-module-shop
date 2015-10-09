<?

	class Shop_SecureTrading_Xml_Payment extends Shop_PaymentType
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
				'name'=>'SecureTrading Xpay',
				'description'=>'SecureTrading payment method with payment page hosted on your server.'
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
				$host_obj->add_field('site_reference', 'Site Reference')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please enter Site Reference.');
				$host_obj->add_field('certificate', 'Certificate')->tab('Configuration')->renderAs(frm_textarea)->comment('Content of the certificate file. The certificate file should be obtained from SecureTrading support. The text field do not show the certificate text even if it is specified.<br/><strong>Important!</strong> For Xpay4 the field must contain the alias of the key/certificate you wish to use for this request. The alias is usually the same as your sitereference.', 'above', true)->hideContent()->validation()->fn('trim');
			}

			$host_obj->add_field('port', 'XPay Port')->tab('Configuration')->renderAs(frm_text)->comment('Port number to connect XPay Java application.<br/><strong>Important!</strong> Port 6666 must be open in the server firewall software in order to allow the XPay Java application to connect to the payment gateway.', 'above', true)->validation()->fn('trim')->required('Please enter port number.')->numeric('The port must be a number.');

			$host_obj->add_field('card_action', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}

		public function get_card_action_options($current_key_value = -1)
		{
			$options = array(
				'1'=>'Authorization/Capture',
				'0'=>'Authorize Only'
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
			$hash_value = trim($host_obj->certificate);
			
			if (!strlen($hash_value))
			{
				if (!isset($host_obj->fetched_data['certificate']) || !strlen($host_obj->fetched_data['certificate']))
					$host_obj->validation->setError('Please paste certificate file content', 'certificate', true);

				$host_obj->certificate = $host_obj->fetched_data['certificate'];
			}
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
			$host_obj->order_status = Shop_OrderStatus::get_status_paid()->id;
			$host_obj->port = 5000;
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

			$host_obj->add_field('START_MONTH', 'Start Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();
			$host_obj->add_field('START_YEAR', 'Start Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();

			$host_obj->add_field('ISSUE', 'Issue Number')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();
		}
		
		public function get_CREDITCARDTYPE_options()
		{
			return array(
				'Visa'=>'Visa',
				'MasterCard'=>'Master Card',
				'Solo'=>'Solo',
				'Maestro'=>'Switch/Maestro',
				'Delta'=>'Delta',
				'Amex'=>'American Express',
				'Electron'=>'Electron'
			);
		}

		/*
		 * Payment processing
		 */

		private function post_data($endpoint, $document)
		{
			$field_str = $document->saveXML();

			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, $endpoint);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 40);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $field_str);
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
			$doc = new DOMDocument();
			try
			{
				$doc->loadXML($response);
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Invalid payment gateway response.');
			}

			return Core_Xml::to_plain_array($doc, true);
		}

		private function prepare_fields_log($fields)
		{
			unset($fields['Certificate']);
			unset($fields['SecurityCode']);
			unset($fields['SiteReference']);
			
			if (isset($fields['Number']))
				$fields['Number'] = '...'.substr($fields['Number'], -4);
			
			return $fields;
		}

		private function prepare_response_log($response)
		{
			return $response;
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

			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');

			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

			$validation->add('START_MONTH', 'Start month')->fn('trim')->regexp('/^[0-9]*$/', 'Credit card start month can contain only digits.');
			$validation->add('START_YEAR', 'Start year')->fn('trim')->regexp('/^[0-9]*$/', 'Credit card start year can contain only digits.');

			$validation->add('ISSUE', 'Issue number')->fn('trim');
			
			$validation->add('CVV2', 'CVV2')->fn('trim')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.');

			try
			{
				if (!$validation->validate($data))
					$validation->throwException();
					
				if ($validation->fieldValues['CREDITCARDTYPE'] == 'Solo' && !strlen($validation->fieldValues['ISSUE']))
					throw new Phpr_ApplicationException('The Issue Number field is required for Solo cards.');
			} catch (Exception $ex)
			{
				$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
				throw $ex;
			}
				
			/*
			 * Send request
			 */
			
			@set_time_limit(3600);
			
			$endpoint = 'http://localhost:'.$host_obj->port;

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

				$startMonth = $validation->fieldValues['START_MONTH'];
				$startYear = $validation->fieldValues['START_YEAR'];

				if (strlen($startMonth))
					$startMonth = $startMonth < 10 ? '0'.$startMonth : $startMonth;
					
				if (strlen($startYear))
				{
					$startYear = $startYear >= 2000 ? $startYear - 2000 : $startYear;
					if ($startYear < 10)
						$startYear = '0'.$startYear;
				}

				$userIp = Phpr::$request->getUserIp();
				if ($userIp == '::1')
					$userIp = '192.168.0.1';

				$document = new DOMDocument('1.0', 'utf-8');
				$request_block = Core_Xml::create_dom_element($document, $document, 'RequestBlock');
				$request_block->setAttribute('Version', '3.51');
				
				$request = Core_Xml::create_dom_element($document, $request_block, 'Request');
				$request->setAttribute('Type', 'AUTH');
				
				/*
				 * Operation details
				 */
				
				$operation = Core_Xml::create_dom_element($document, $request, 'Operation');
				Core_Xml::create_dom_element($document, $operation, 'Amount', round($order->total*100));
				Core_Xml::create_dom_element($document, $operation, 'Currency', $currency->code);
				Core_Xml::create_dom_element($document, $operation, 'SiteReference', $host_obj->site_reference);
				Core_Xml::create_dom_element($document, $operation, 'SettlementDay', $host_obj->card_action);
				
				/*
				 * Customer information
				 */
				
				$customer_info = Core_Xml::create_dom_element($document, $request, 'CustomerInfo');
				$postal = Core_Xml::create_dom_element($document, $customer_info, 'Postal');
				$name = Core_Xml::create_dom_element($document, $postal, 'Name');
				Core_Xml::create_dom_element($document, $name, 'FirstName', $validation->fieldValues['FIRSTNAME']);
				Core_Xml::create_dom_element($document, $name, 'LastName', $validation->fieldValues['LASTNAME']);
				Core_Xml::create_dom_element($document, $name, 'Company', $order->billing_company);
				Core_Xml::create_dom_element($document, $name, 'Street', $order->billing_street_addr);
				Core_Xml::create_dom_element($document, $name, 'City', $order->billing_city);
				if ($order->billing_state)
					Core_Xml::create_dom_element($document, $postal, 'StateProv', $order->billing_state->name);
				Core_Xml::create_dom_element($document, $postal, 'PostalCode', $order->billing_zip);
				Core_Xml::create_dom_element($document, $postal, 'CountryCode', $order->billing_country->code_3);
				
				$telecom = Core_Xml::create_dom_element($document, $customer_info, 'Telecom');
				Core_Xml::create_dom_element($document, $telecom, 'Phone', $order->billing_phone);
				
				$online = Core_Xml::create_dom_element($document, $customer_info, 'Online');
				Core_Xml::create_dom_element($document, $online, 'Email', $order->billing_email);
				
				/*
				 * Payment method information
				 */
				
				$payment_method = Core_Xml::create_dom_element($document, $request, 'PaymentMethod');
				$credit_card = Core_Xml::create_dom_element($document, $payment_method, 'CreditCard');
				Core_Xml::create_dom_element($document, $credit_card, 'Type', $validation->fieldValues['CREDITCARDTYPE']);
				Core_Xml::create_dom_element($document, $credit_card, 'Number', $validation->fieldValues['ACCT']);
				Core_Xml::create_dom_element($document, $credit_card, 'ExpiryDate', $expMonth.'/'.$expYear);
				Core_Xml::create_dom_element($document, $credit_card, 'SecurityCode', $validation->fieldValues['CVV2']);

				if (strlen($validation->fieldValues['ISSUE']))
					Core_Xml::create_dom_element($document, $credit_card, 'Issue', $validation->fieldValues['ISSUE']);

				if (strlen($startMonth) || strlen($startYear))
					Core_Xml::create_dom_element($document, $credit_card, 'StartDate', $startMonth.'/'.$startYear);
				
				/*
				 * Order information
				 */
				
				$order_node = Core_Xml::create_dom_element($document, $request, 'Order');
				Core_Xml::create_dom_element($document, $order_node, 'OrderReference', $order->id);
				Core_Xml::create_dom_element($document, $order_node, 'OrderInformation', 'Order #'.$order->id);
				
				/*
				 * Certificate
				 */
				
				$order_node = Core_Xml::create_dom_element($document, $request_block, 'Certificate', $host_obj->certificate);

				/*
				 * Post request
				 */

				$fields = Core_Xml::to_plain_array($document, true);
				$response = $this->post_data($endpoint, $document);

				/*
				 * Process result
				 */

				$response_fields = $this->parse_response($response);

				if (!isset($response_fields['Result']))
					throw new Phpr_ApplicationException('Invalid payment gateway response.');
					
				if ($response_fields['Result'] != 1)
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
						
				/*
				 * Successful payment. Set order status and mark it as paid.
				 */
				
				$this->log_payment_attempt($order, 'Successful payment', 1, $this->prepare_fields_log($fields), $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));
				
				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
				$order->set_payment_processed();
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($fields);
				
				$error_message = $ex->getMessage();
				if (isset($response_fields['Message']))
					$error_message = $response_fields['Message'];
				
				$this->log_payment_attempt($order, $error_message, 0, $fields, $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));

				if (!$back_end)
					throw new Phpr_ApplicationException($ex->getMessage());
				else
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in SecureTrading XPay payment method.');
		}
	}

?>