<?

	class Shop_HSBC_Payment extends Shop_PaymentType
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
				'name'=>'HSBC API',
				'description'=>'API implementation of the HSBC payment gateway.'
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
			$host_obj->add_field('test_mode', 'Create Test Transactions')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Mark all transactions as test transactions.', 'above');
			
			$host_obj->add_field('global_iris', 'Use Global Iris API XML Connector')->tab('Configuration')->renderAs(frm_checkbox)->comment('Global Iris is the replacement for Secure ePayments. Merchants who currently use Secure ePayments 
			will need to migrate to Global Iris, as Secure ePayments is being decommissioned. Please contact HSBC for details.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_field('client_id', 'HSBC Client ID')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide Client ID.');
				$host_obj->add_field('username', 'HSBC User name', 'left')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide user name.');
				$host_obj->add_field('password', 'HSBC Password', 'right')->tab('Configuration')->renderAs(frm_password)->validation()->fn('trim');
			}

			$host_obj->add_field('card_action', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
			
			$host_obj->add_field('post_bill_to', 'Post customer details to the gateway')->tab('Configuration')->renderAs(frm_checkbox)->comment('Uncheck this checkbox if you do not need customer details to be sent to the HSBC gateway.', 'above');
			$host_obj->add_field('post_ship_to', 'Post shipping details to the gateway')->tab('Configuration')->renderAs(frm_checkbox)->comment('Uncheck this checkbox if you do not need customer shipping name and address to be sent to the HSBC gateway.', 'above');
			$host_obj->add_field('post_item_list', 'Post order item list to the gateway')->tab('Configuration')->renderAs(frm_checkbox)->comment('Uncheck this checkbox if you do not need order content details to be sent to the HSBC gateway.', 'above');
		}
		
		public function get_card_action_options($current_key_value = -1)
		{
			$options = array(
				'Auth'=>'Authorization/Capture',
				'PreAuth'=>'Authorize Only'
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
			$hash_value = trim($host_obj->password);
			
			if (!strlen($hash_value))
			{
				if (!isset($host_obj->fetched_data['password']) || !strlen($host_obj->fetched_data['password']))
					$host_obj->validation->setError('Please enter password', 'password', true);

				$host_obj->password = $host_obj->fetched_data['password'];
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
			$host_obj->test_mode = 1;
			$host_obj->post_bill_to = 1;
			$host_obj->post_ship_to = 1;
			$host_obj->post_item_list = 1;
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


			$host_obj->add_field('START_MONTH', 'Start Month', 'left')->renderAs(frm_text)->comment('The start date is required for Maestro and Solo cards only.', 'above')->validation()->fn('trim')->numeric();
			$host_obj->add_field('START_YEAR', 'Start Year', 'right')->renderAs(frm_text)->comment('The start date is required for Maestro and Solo cards only.', 'above')->validation()->fn('trim')->numeric();
			
			$host_obj->add_field('ISSUE_NUM', 'Issue Number')->renderAs(frm_text)->comment('The issue number is required for Maestro and Solo cards only. The issue number is embossed on the card.', 'above')->validation()->fn('trim')->numeric();
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
			curl_setopt($ch, CURLOPT_SSLVERSION, 3);
			curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'RC4-MD5');
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
			unset($fields['ClientId']);
			unset($fields['Name']);
			unset($fields['Password']);
			unset($fields['Cvv2Val']);
			if (isset($fields['Number']))
				$fields['Number'] = '...'.substr($fields['Number'], -4);
			
			return $fields;
		}

		private function prepare_response_log($response)
		{
			$response = preg_replace(',\<ClientId[^>]*\>([0-9]*)\<\/ClientId\>,m', '<ClientId>*****</ClientId>', $response);
			$response = preg_replace(',\<Name[^>]*\>([0-9]*)\<\/Name\>,m', '<Name>*****</Name>', $response);
			$response = preg_replace(',\<Password[^>]*\>([0-9]*)\<\/Password\>,m', '<Password>*****</Password>', $response);
			$response = preg_replace(',\<Password[^>]*\>([0-9]*)\<\/Password\>,m', '<Password>*****</Password>', $response);
			$response = preg_replace(',\<Number[^>]*\>([0-9]*)\<\/Number\>,m', '<Number>*****</Number>', $response);
			$response = preg_replace(',\<Cvv2Val[^>]*\>([0-9]*)\<\/Cvv2Val\>,m', '<Cvv2Val>*****</Cvv2Val>', $response);

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
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

			$validation->add('START_MONTH', 'Start month')->fn('trim')->regexp('/^[0-9]*$/', 'Credit card start month can contain only digits.');
			$validation->add('START_YEAR', 'Start year')->fn('trim')->regexp('/^[0-9]*$/', 'Credit card start year can contain only digits.');

			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');
			$validation->add('CVV2', 'CVV2')->fn('trim')->required('Please specify CVV2 value.')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.');

			$validation->add('ISSUE_NUM', 'Issue number')->fn('trim');

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
			
			if (!$host_obj->global_iris)
				$endpoint = 'https://www.secure-epayments.apixml.hsbc.com';
			else
				$endpoint = 'https://apixml.globaliris.com';

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

				if (strlen($validation->fieldValues['START_MONTH']))
					$validation->fieldValues['START_MONTH'] = (int)$validation->fieldValues['START_MONTH'];

				if (strlen($validation->fieldValues['START_YEAR']))
					$validation->fieldValues['START_YEAR'] = (int)$validation->fieldValues['START_YEAR'];
					
				$startMonth = $validation->fieldValues['START_MONTH'] < 10 ? '0'.$validation->fieldValues['START_MONTH'] : $validation->fieldValues['START_MONTH'];
				$startYear = $validation->fieldValues['START_YEAR'] > 2000 ? $validation->fieldValues['START_YEAR'] - 2000 : $validation->fieldValues['START_YEAR'];
				if ($startYear < 10)
					$startYear = '0'.$startYear;
					
				if ($startMonth == '0') $startMonth = '';
				if ($startYear == '0') $startYear = '';

				$userIp = Phpr::$request->getUserIp();
				if ($userIp == '::1')
					$userIp = '192.168.0.1';
				
				$document = new DOMDocument('1.0', 'utf-8');
				$doclist = Core_Xml::create_dom_element($document, $document, 'EngineDocList');
				Core_Xml::create_dom_element($document, $doclist, 'DocVersion', '1.0')->setAttribute('DataType', 'String');
				$engine_doc = Core_Xml::create_dom_element($document, $doclist, 'EngineDoc');
				Core_Xml::create_dom_element($document, $engine_doc, 'ContentType', 'OrderFormDoc')->setAttribute('DataType', 'String');
				Core_Xml::create_dom_element($document, $engine_doc, 'IPAddress', $userIp)->setAttribute('DataType', 'String');
				
				/*
				 * Authentication
				 */
				
				$user = Core_Xml::create_dom_element($document, $engine_doc, 'User');
				Core_Xml::create_dom_element($document, $user, 'ClientId', $host_obj->client_id)->setAttribute('DataType', 'S32');
				Core_Xml::create_dom_element($document, $user, 'Name', $host_obj->username)->setAttribute('DataType', 'String');
				Core_Xml::create_dom_element($document, $user, 'Password', $host_obj->password)->setAttribute('DataType', 'String');
				
				/*
				 * Instructions
				 */
				
				$instructions = Core_Xml::create_dom_element($document, $engine_doc, 'Instructions');
				Core_Xml::create_dom_element($document, $instructions, 'Pipeline', 'Payment')->setAttribute('DataType', 'String');
				
				/*
				 * Order information
				 */
				
				$order_form = Core_Xml::create_dom_element($document, $engine_doc, 'OrderFormDoc');
				Core_Xml::create_dom_element($document, $order_form, 'Id', $order->id)->setAttribute('DataType', 'String');
				
				if (!$host_obj->test_mode)
					Core_Xml::create_dom_element($document, $order_form, 'Mode', 'P')->setAttribute('DataType', 'String');
				else
					Core_Xml::create_dom_element($document, $order_form, 'Mode', 'Y')->setAttribute('DataType', 'String');

				/*
				 * Consumer information
				 */
				
				$consumer = Core_Xml::create_dom_element($document, $order_form, 'Consumer');
				Core_Xml::create_dom_element($document, $consumer, 'Email', $order->billing_email)->setAttribute('DataType', 'String');
				
				$payment_mech = Core_Xml::create_dom_element($document, $consumer, 'PaymentMech');
				Core_Xml::create_dom_element($document, $payment_mech, 'Type', 'CreditCard')->setAttribute('DataType', 'String');

				$credit_card = Core_Xml::create_dom_element($document, $payment_mech, 'CreditCard');
				Core_Xml::create_dom_element($document, $credit_card, 'Number', $validation->fieldValues['ACCT'])->setAttribute('DataType', 'String');
				$exp_date = Core_Xml::create_dom_element($document, $credit_card, 'Expires', $expMonth.'/'.$expYear);
				$exp_date->setAttribute('DataType', 'ExpirationDate');
				$exp_date->setAttribute('Locale', $currency->iso_4217_code);

				if (strlen($startMonth) || strlen($startYear))
				{
					$exp_date = Core_Xml::create_dom_element($document, $credit_card, 'StartDate', $startMonth.'/'.$startYear);
					$exp_date->setAttribute('DataType', 'StartDate');
					$exp_date->setAttribute('Locale', $currency->iso_4217_code);
				}
				
				Core_Xml::create_dom_element($document, $credit_card, 'Cvv2Indicator', 1)->setAttribute('DataType', 'String');
				Core_Xml::create_dom_element($document, $credit_card, 'Cvv2Val', $validation->fieldValues['CVV2'])->setAttribute('DataType', 'String');
				Core_Xml::create_dom_element($document, $credit_card, 'IssueNum', $validation->fieldValues['ISSUE_NUM'])->setAttribute('DataType', 'String');

				/*
				 * Bill to information
				 */
				
				if ($host_obj->post_bill_to)
				{
					$bill_to = Core_Xml::create_dom_element($document, $consumer, 'BillTo');
					$bill_to_location = Core_Xml::create_dom_element($document, $bill_to, 'Location');
					Core_Xml::create_dom_element($document, $bill_to_location, 'TelVoice', Phpr_Html::strTrim($order->billing_phone, 30))->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $bill_to_location, 'Email', Phpr_Html::strTrim($order->billing_email, 64))->setAttribute('DataType', 'String');

					$bill_to_address = Core_Xml::create_dom_element($document, $bill_to_location, 'Address');
					Core_Xml::create_dom_element($document, $bill_to_address, 'City', Phpr_Html::strTrim($order->billing_city, 25))->setAttribute('DataType', 'String');
					if ($order->billing_company)
						Core_Xml::create_dom_element($document, $bill_to_address, 'Company', Phpr_Html::strTrim($order->billing_company, 40))->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $bill_to_address, 'Country', $order->billing_country->code_iso_numeric)->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $bill_to_address, 'FirstName', Phpr_Html::strTrim($validation->fieldValues['FIRSTNAME'], 32))->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $bill_to_address, 'LastName', Phpr_Html::strTrim($validation->fieldValues['LASTNAME'], 32))->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $bill_to_address, 'PostalCode', Phpr_Html::strTrim($order->billing_zip, 20))->setAttribute('DataType', 'String');
				
					if ($order->billing_state)
						Core_Xml::create_dom_element($document, $bill_to_address, 'StateProv', Phpr_Html::strTrim($order->billing_state->name, 25))->setAttribute('DataType', 'String');
				
					Core_Xml::create_dom_element($document, $bill_to_address, 'Street1', Phpr_Html::strTrim($order->billing_street_addr, 60))->setAttribute('DataType', 'String');
				}
				
				/*
				 * Ship to information
				 */
				
				if ($host_obj->post_ship_to)
				{
					$ship_to = Core_Xml::create_dom_element($document, $consumer, 'ShipTo');
					$ship_to_location = Core_Xml::create_dom_element($document, $ship_to, 'Location');
					Core_Xml::create_dom_element($document, $ship_to_location, 'TelVoice', Phpr_Html::strTrim($order->shipping_phone, 30))->setAttribute('DataType', 'String');
				
					$ship_to_address = Core_Xml::create_dom_element($document, $ship_to_location, 'Address');
					Core_Xml::create_dom_element($document, $ship_to_address, 'City', Phpr_Html::strTrim($order->shipping_city, 25))->setAttribute('DataType', 'String');
					if ($order->shipping_company)
						Core_Xml::create_dom_element($document, $ship_to_address, 'Company', Phpr_Html::strTrim($order->shipping_company, 40))->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $ship_to_address, 'Country', $order->shipping_country->code_iso_numeric)->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $ship_to_address, 'FirstName', Phpr_Html::strTrim($order->shipping_first_name, 32))->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $ship_to_address, 'LastName', Phpr_Html::strTrim($order->shipping_last_name, 32))->setAttribute('DataType', 'String');
					Core_Xml::create_dom_element($document, $ship_to_address, 'PostalCode', Phpr_Html::strTrim($order->shipping_zip, 20))->setAttribute('DataType', 'String');
				
					if ($order->shipping_state)
						Core_Xml::create_dom_element($document, $ship_to_address, 'StateProv', Phpr_Html::strTrim($order->shipping_state->name, 25))->setAttribute('DataType', 'String');

					Core_Xml::create_dom_element($document, $ship_to_address, 'Street1', Phpr_Html::strTrim($order->shipping_street_addr, 60))->setAttribute('DataType', 'String');
				}
				
				/*
				 * Transaction information
				 */
				
				$transaction = Core_Xml::create_dom_element($document, $order_form, 'Transaction');
				Core_Xml::create_dom_element($document, $transaction, 'Type', $host_obj->card_action)->setAttribute('DataType', 'String');

				$current_totals = Core_Xml::create_dom_element($document, $transaction, 'CurrentTotals');
				$totals = Core_Xml::create_dom_element($document, $current_totals, 'Totals');

				$total = Core_Xml::create_dom_element($document, $totals, 'Total', round($order->total*100));
				$total->setAttribute('DataType', 'Money');
				$total->setAttribute('Currency', $currency->iso_4217_code);
				
				$shipping_cost = Core_Xml::create_dom_element($document, $totals, 'Ship', round($order->shipping_quote*100));
				$shipping_cost->setAttribute('DataType', 'Money');
				$shipping_cost->setAttribute('Currency', $currency->iso_4217_code);
				
				$shipping_tax = Core_Xml::create_dom_element($document, $totals, 'VaShipAmt', round($order->shipping_tax*100));
				$shipping_tax->setAttribute('DataType', 'Money');
				$shipping_tax->setAttribute('Currency', $currency->iso_4217_code);
				
				$tax = Core_Xml::create_dom_element($document, $totals, 'VatTax', round(($order->goods_tax)*100));
				$tax->setAttribute('DataType', 'Money');
				$tax->setAttribute('Currency', $currency->iso_4217_code);
				
				/*
				 * Order items
				 */

				if ($host_obj->post_item_list)
				{
					$item_list = Core_Xml::create_dom_element($document, $order_form, 'OrderItemList');
					foreach ($order->items as $item)
					{
						$item_node = Core_Xml::create_dom_element($document, $item_list, 'OrderItem');
						Core_Xml::create_dom_element($document, $item_node, 'Desc', Phpr_Html::strTrim($item->output_product_name(true, true), 64))->setAttribute('DataType', 'String');;
						Core_Xml::create_dom_element($document, $item_node, 'ProductCode', Phpr_Html::strTrim($item->product_sku, 12))->setAttribute('DataType', 'String');
						Core_Xml::create_dom_element($document, $item_node, 'QtyNumeric', $item->quantity)->setAttribute('DataType', 'Numeric');
						Core_Xml::create_dom_element($document, $item_node, 'ItemNumber', $item->id)->setAttribute('DataType', 'S32');
						Core_Xml::create_dom_element($document, $item_node, 'Id', $item->id)->setAttribute('DataType', 'String');
					
						$price = Core_Xml::create_dom_element($document, $item_node, 'Price', round($item->unit_total_price*100));
						$price->setAttribute('DataType', 'Money');
						$price->setAttribute('Currency', $currency->iso_4217_code);
					}
				}

				/*
				 * Post request
				 */
				
				$fields = Core_Xml::to_plain_array($document, true);
				$response = $this->post_data($endpoint, $document);

				/*
				 * Process result
				 */

				$response_fields = $this->parse_response($response);

				if (!isset($response_fields['TransactionStatus']))
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
					
				if (!isset($response_fields['TransactionStatus']) || ($response_fields['TransactionStatus'] !== 'A' && $response_fields['TransactionStatus'] !== 'F'))
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
				if (isset($response_fields['Text']))
					$error_message = $response_fields['Text'];
				
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in HSBC payment method.');
		}
	}

?>