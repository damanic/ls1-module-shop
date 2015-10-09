<?
	class Shop_EselectPlus_Payment extends Shop_PaymentType
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
				'name'=>'eSELECTplus API',
				'description'=>'eSELECTplus API. This payment method works with Moneris payment gateway (Canada).'
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
			$host_obj->add_field('test_mode', 'Use Test Server')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Connect to eSELECTplus test server (esqa.moneris.com). Please specify test account Store ID and API Token if you choose using the test server. You can find test Store ID and API Token in the eSELECTplus API documentation.', 'above', true);
			
			$host_obj->add_field('efraud_disabled', 'eFraud is disabled')->tab('Configuration')->renderAs(frm_checkbox)->comment('Enable this option if eFraud CVD verification is disabled in your eSELECTplus account.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_field('store_id', 'Store ID', 'left')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide Store ID.');
				
				$host_obj->add_field('api_token', 'API Token', 'right')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide API Token.');
			}

			$host_obj->add_field('card_action', 'Card Action', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_card_action_options($current_key_value = -1)
		{
			$options = array(
				'purchase'=>'Purchase (sale)',
				'preauth'=>'PreAuth (reserve funds)'
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

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
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
			unset($fields['store_id']);
			unset($fields['api_token']);

			if (isset($fields['cvd_value']))
				unset($fields['cvd_value']);

			if (isset($fields['pan']))
				$fields['pan'] = '...'.substr($fields['pan'], -4);
			
			return $fields;
		}
		
		private function prepare_response_log($response)
		{
			return $response;
		}
		
		protected function get_ccv_status_text($status_code)
		{
			$status_code = strtoupper(substr($status_code, -1));
			
			if (!strlen($status_code))
				return 'CCV response code is empty';

			$status_names = array(
				'M'=>'Match',
				'N'=>'No Match',
				'P'=>'Not Processed',
				'S'=>'CVD should be on the card, but Merchant has indicated that CVD is not present',
				'U'=>'Issuer is not a CVD participant'
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
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');
			$validation->add('CVV2', 'CVV2')->fn('trim')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.');

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
			
			if (!$host_obj->test_mode)
				$endpoint = 'https://www3.moneris.com:443/gateway2/servlet/MpgRequest';
			else
				$endpoint = 'https://esqa.moneris.com:443/gateway2/servlet/MpgRequest';

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

				$userIp = Phpr::$request->getUserIp();
				$timeStamp = time();
				//$order_id = $order->id + $timeStamp - 1251679000;
				
				// The payment gateway doesn't allow to use the same order ID for 
				// different payment attempts even if the previous payment attemt 
				// has failed.
				// In this solution the transaction ID increases every 5 seconds. 
				// It prevents double payments, but allows customers to try to pay
				// with another credit card. 
				//
				$order_id = $order->id + 114711229 + floor(time()/5);

				$document = new DOMDocument('1.0', 'utf-8');
				$request = Core_Xml::create_dom_element($document, $document, 'request');
				Core_Xml::create_dom_element($document, $request, 'store_id', $host_obj->store_id);
				Core_Xml::create_dom_element($document, $request, 'api_token', $host_obj->api_token);
				
				$transaction = Core_Xml::create_dom_element($document, $request, $host_obj->card_action);
				Core_Xml::create_dom_element($document, $transaction, 'order_id', $order_id);
				Core_Xml::create_dom_element($document, $transaction, 'cust_id', $order->id);
				Core_Xml::create_dom_element($document, $transaction, 'amount', $order->total);
				Core_Xml::create_dom_element($document, $transaction, 'pan', $validation->fieldValues['ACCT']);
				Core_Xml::create_dom_element($document, $transaction, 'expdate', $expYear.$expMonth);
				Core_Xml::create_dom_element($document, $transaction, 'crypt_type', 7);
				
				/*
				 * CVD information
				 */
				
				if (!$host_obj->efraud_disabled)
				{
					$cvd_info = Core_Xml::create_dom_element($document, $transaction, 'cvd_info');
					$cvd_value = $validation->fieldValues['CVV2'];
					$cvd_presented = strlen($cvd_value) ? 1 : 9;

					Core_Xml::create_dom_element($document, $cvd_info, 'cvd_indicator', $cvd_presented);
					Core_Xml::create_dom_element($document, $cvd_info, 'cvd_value', $cvd_value);
				}
				
				/*
				 * Customer information information
				 */
				
				$cust_info = Core_Xml::create_dom_element($document, $transaction, 'cust_info');
				Core_Xml::create_dom_element($document, $cust_info, 'email', $order->billing_email);
				Core_Xml::create_dom_element($document, $cust_info, 'instructions', $order->customer_notes);
				
				/*
				 * Billing information
				 */

				$billing = Core_Xml::create_dom_element($document, $cust_info, 'billing');
				Core_Xml::create_dom_element($document, $billing, 'first_name', h($validation->fieldValues['FIRSTNAME']));
				Core_Xml::create_dom_element($document, $billing, 'last_name', h($validation->fieldValues['LASTNAME']));
				Core_Xml::create_dom_element($document, $billing, 'company_name', h($order->billing_company));
				Core_Xml::create_dom_element($document, $billing, 'address', h($order->billing_street_addr));
				Core_Xml::create_dom_element($document, $billing, 'city', $order->billing_city);
				if ($order->billing_state)
					Core_Xml::create_dom_element($document, $billing, 'province', $order->billing_state->name);
				Core_Xml::create_dom_element($document, $billing, 'postal_code', $order->billing_zip);
				Core_Xml::create_dom_element($document, $billing, 'country', $order->billing_country->name);
				Core_Xml::create_dom_element($document, $billing, 'phone_number', $order->billing_phone);
				Core_Xml::create_dom_element($document, $billing, 'tax1', $order->goods_tax);
				Core_Xml::create_dom_element($document, $billing, 'tax2', $order->shipping_tax);
				Core_Xml::create_dom_element($document, $billing, 'tax3', '');
				Core_Xml::create_dom_element($document, $billing, 'shipping_cost', $order->shipping_quote);

				/*
				 * Shipping information
				 */
				
				$shipping = Core_Xml::create_dom_element($document, $cust_info, 'shipping');
				Core_Xml::create_dom_element($document, $shipping, 'first_name', h($order->shipping_first_name));
				Core_Xml::create_dom_element($document, $shipping, 'last_name', h($order->shipping_last_name));
				Core_Xml::create_dom_element($document, $shipping, 'company_name', h($order->shipping_company));
				Core_Xml::create_dom_element($document, $shipping, 'address', h($order->shipping_street_addr));
				Core_Xml::create_dom_element($document, $shipping, 'city', $order->shipping_city);
				if ($order->shipping_state)
					Core_Xml::create_dom_element($document, $shipping, 'province', $order->shipping_state->name);
				Core_Xml::create_dom_element($document, $shipping, 'postal_code', $order->shipping_zip);
				Core_Xml::create_dom_element($document, $shipping, 'country', $order->shipping_country->name);
				Core_Xml::create_dom_element($document, $shipping, 'phone_number', $order->shipping_phone);
				Core_Xml::create_dom_element($document, $shipping, 'tax1', '');
				Core_Xml::create_dom_element($document, $shipping, 'tax2', '');
				Core_Xml::create_dom_element($document, $shipping, 'tax3', '');
				Core_Xml::create_dom_element($document, $shipping, 'shipping_cost', '');
				
				/*
				 * Items
				 */
				
				foreach ($order->items as $item)
				{
					$item_node = Core_Xml::create_dom_element($document, $cust_info, 'item');
					Core_Xml::create_dom_element($document, $item_node, 'name', h($item->output_product_name(true, true)));
					Core_Xml::create_dom_element($document, $item_node, 'quantity', $item->quantity);
					Core_Xml::create_dom_element($document, $item_node, 'product_code', h($item->product_sku));
					Core_Xml::create_dom_element($document, $item_node, 'extended_amount', $item->unit_total_price);
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

				if (!isset($response_fields['ResponseCode']))
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
					
				if (!preg_match('/^[0-9]+$/', $response_fields['ResponseCode']))
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');

				$response_code = (int)$response_fields['ResponseCode'];

				if ($response_code >= 50)
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');

				/*
				 * Successful payment. Set order status and mark it as paid.
				 */

				$ccv_code = array_key_exists('CvdResultCode', $response_fields) ? $response_fields['CvdResultCode'] : null;

				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					$this->prepare_fields_log($fields), 
					$this->prepare_fields_log($response_fields), 
					$this->prepare_response_log($response),
					$ccv_code,
					$this->get_ccv_status_text($ccv_code)
				);

				if(isset($response_fields['TransID']))
				{
					if($response_fields['TransType'] == '00')
						$status_code = 'purchase';
					elseif($response_fields['TransType'] == '01')
						$status_code = 'preauth';
					else $status_code = 'unknown';

					$this->update_transaction_status($order->payment_method, $order, $order_id, $this->get_status_name($status_code), $status_code, serialize(array('order_id' => $order_id, 'txn_number' => $response_fields['TransID'])));
				}
				
				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
				$order->set_payment_processed();
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($fields);
				
				$error_message = $ex->getMessage();
				if (isset($response_fields['Message']))
					$error_message = $response_fields['Message'];
					
				$ccv_code = array_key_exists('CvdResultCode', $response_fields) ? $response_fields['CvdResultCode'] : null;
				
				$this->log_payment_attempt(
					$order, 
					$error_message, 
					0, 
					$fields, 
					$this->prepare_fields_log($response_fields), 
					$this->prepare_response_log($response),
					$ccv_code,
					$this->get_ccv_status_text($ccv_code)
				);

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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in eSelectPlus payment method.');
		}

		protected function get_status_name($status_id)
		{
			$status_id = strtoupper($status_id);
			
			$names = array(
				'PURCHASE'=>'Purchase (sale)',
				'PREAUTH'=>'PreAuth (funds reserved)',
				'VOID'=>'Void (transaction cancelled)',
				'CAPTURE'=> 'Capture (sale complete)',
				'RELEASE'=> 'Undo PreAuth (release reserved funds)'
			);
			
			if (array_key_exists($status_id, $names))
				return $names[$status_id];
			
			return 'Unknown';
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
				case 'PREAUTH' :
					return array(
						'capture' => 'Capture funds',
						'release' => 'Release funds'
					);
				break;
				case 'CAPTURE' :
				case 'PURCHASE' :
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
			//get the last record for this transaction
			$transaction_obj = Shop_PaymentTransaction::create()->where('payment_method_id=?', $host_obj->id)->order('created_at desc')->find_by_transaction_id($transaction_id);
			if(!$transaction_obj)
				throw new Phpr_ApplicationException('Transaction does not exist.');

			if(in_array(strtoupper($new_transaction_status_code), array('VOID', 'RELEASE', 'CAPTURE')))
			{
				$data_1 = unserialize($transaction_obj->data_1);

				$fields = array();
				$response = null;
				$response_fields = array();
				$currency = Shop_CurrencySettings::get();

				if (!$host_obj->test_mode)
					$endpoint = 'https://www3.moneris.com:443/gateway2/servlet/MpgRequest';
				else
					$endpoint = 'https://esqa.moneris.com:443/gateway2/servlet/MpgRequest';

				$document = new DOMDocument('1.0', 'utf-8');
				$request = Core_Xml::create_dom_element($document, $document, 'request');
				Core_Xml::create_dom_element($document, $request, 'store_id', $host_obj->store_id);
				Core_Xml::create_dom_element($document, $request, 'api_token', $host_obj->api_token);
				
				$timeStamp = time();

				// to void a transaction
				if(strtoupper($new_transaction_status_code) == 'VOID')
				{
					$transaction = Core_Xml::create_dom_element($document, $request, 'purchasecorrection');
					Core_Xml::create_dom_element($document, $transaction, 'order_id', $data_1['order_id']);
					Core_Xml::create_dom_element($document, $transaction, 'txn_number', $data_1['txn_number']);
					Core_Xml::create_dom_element($document, $transaction, 'crypt_type', '7');
				}
				else
				{
					//capture a transaction
					$transaction = Core_Xml::create_dom_element($document, $request, 'completion');
					Core_Xml::create_dom_element($document, $transaction, 'order_id', $data_1['order_id']);
					//to release preauthorized funds, send a capture for $0.00
					if(strtoupper($new_transaction_status_code) == 'RELEASE')
						Core_Xml::create_dom_element($document, $transaction, 'comp_amount', '0.00');
					else Core_Xml::create_dom_element($document, $transaction, 'comp_amount', $order->total);
					Core_Xml::create_dom_element($document, $transaction, 'txn_number', $data_1['txn_number']);
					Core_Xml::create_dom_element($document, $transaction, 'crypt_type', '7');
				}

				$response = $this->post_data($endpoint, $document);
				$response_fields = $this->parse_response($response);

				$response_code = (int)$response_fields['ResponseCode'];

				if (!preg_match('/^[0-9]+$/', $response_fields['ResponseCode']))
					throw new Phpr_ApplicationException('Transaction status could not be changed.');

				if ($response_code >= 50)
					throw new Phpr_ApplicationException('The transaction status could not be changed.');

				$data_1 = serialize(array('order_id' => $response_fields['ReceiptId'], 'txn_number' => $response_fields['TransID']));

				return new Shop_TransactionUpdate(
					$new_transaction_status_code,
					$this->get_status_name($new_transaction_status_code),
					$data_1
				);
			}

		}
	}

?>