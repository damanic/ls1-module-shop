<?

	class Shop_FirstData_Api_Payment extends Shop_PaymentType
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
				'name'=>'First Data/LinkPoint API, North America',
				'description'=>'API implementation of the First Data/LinkPoint payment gateway, for North America.'
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
			$host_obj->add_field('server_url', 'Host Name')->tab('Configuration')->renderAs(frm_text)->comment('You can find the host name in the First Data/LinkPoint Welcome email message in the <strong>API secure host name</strong> field. The host name must start with <strong>https://</strong>. Example: https://secure.linkpt.net.', 'above', true)->validation()->fn('trim')->required('Please specify host name.', 'above', true);
			$host_obj->add_field('server_port', 'Port Number')->tab('Configuration')->renderAs(frm_text)->comment('You can find the port number in the First Data Welcome email message', 'above', true)->validation()->fn('trim')->required('Please specify port number.')->numeric();

			if ($context !== 'preview')
			{
				$host_obj->add_field('store_id', 'Store Name')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide Store Name.');
				
				$host_obj->add_field('certificate', 'Certificate')->tab('Configuration')->renderAs(frm_textarea)->comment('Content of the certificate file. The certificate file should be obtained from the First Data online terminal. The text field do not show the certificate text even if it is specified.', 'above')->hideContent()->validation()->fn('trim');
			}

			$host_obj->add_field('card_action', 'Card Action', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_card_action_options($current_key_value = -1)
		{
			$options = array(
				'SALE'=>'Purchase (sale)',
				'PREAUTH'=>'Authorize only (reserve funds)'
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
			$host = mb_strtolower($host_obj->server_url);
			if (!preg_match(',^https://,', $host))
				$host_obj->validation->setError('The Host Name must start with https://', 'server_url', true);

			if (substr($host_obj->server_url, -1) == '/')
				$host_obj->server_url = substr($host_obj->server_url, 0, -1);
			
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
			$host_obj->server_url = 'https://';
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
			$host_obj->add_field('ACCT', 'Credit Card Number')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a credit card number')->regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');

			$host_obj->add_field('CVV2', 'Card Code', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify Card Verification Number')->numeric();
			$host_obj->add_field('CVV2_NOT_PRESENT', 'Code not on card', 'right')->renderAs(frm_checkbox);

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
		}

		/*
		 * Payment processing
		 */

		private function post_data($host_obj, $document)
		{
			$field_str = $document->saveXML();

			$endpoint = $host_obj->server_url.':'.$host_obj->server_port.'/LSGSXML';
			$cert_file = PATH_APP.'/temp/'.uniqid();
			if (!file_put_contents($cert_file, $host_obj->certificate))
				throw new Phpr_SystemException('Error creating temporary file');
				
			try
			{
				$ch = curl_init(); 
				curl_setopt($ch, CURLOPT_URL, $endpoint);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT, 40);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $field_str);
				curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt ($ch, CURLOPT_SSLCERT, $cert_file);

				$response = curl_exec($ch);

				if (curl_errno($ch))
					throw new Phpr_ApplicationException( "Error connecting the payment gateway: ".curl_error($ch) );
				else
					curl_close($ch);
					
				@unlink($cert_file);
			} catch (Exception $ex)
			{
				if (file_exists($cert_file))
					@unlink($cert_file);
				
				throw $ex;
			}
				
			return $response;
		}

		private function parse_response($response)
		{
			$doc = new DOMDocument();
			try
			{
				/*
				 * The response returned by the First Data needs fixing before parsing
				 */

				if (substr($response, 0, 1) == '<')
				{
					$response = '<response>'.$response.'</response>';
					$response = '<?xml version="1.0" encoding="utf-8"?>'.$response;
				}

				$doc->loadXML($response);
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Invalid payment gateway response.');
			}

			return Core_Xml::to_plain_array($doc, true);
		}

		private function prepare_fields_log($fields)
		{
			unset($fields['configfile']);

			if (isset($fields['cvmvalue']))
				unset($fields['cvmvalue']);

			if (isset($fields['cardnumber']))
				$fields['cardnumber'] = '...'.substr($fields['cardnumber'], -4);
			
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
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');
			$validation->add('CVV2', 'Card code')->fn('trim')->regexp('/^[0-9]*$/', 'Card code can contain only digits.');
			
			try
			{
				if (!$validation->validate($data))
					$validation->throwException();

				if (!post('CVV2_NOT_PRESENT') && !strlen($validation->fieldValues['CVV2']))
					$validation->setError('Please specify card code', 'CVV2', true);

			} catch (Exception $ex)
			{
				$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
				throw $ex;
			}
				
			/*
			 * Send request
			 */
			
			@set_time_limit(3600);
			
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
				
				$document = new DOMDocument('1.0', 'utf-8');
				$order_element = Core_Xml::create_dom_element($document, $document, 'order');
				$options = Core_Xml::create_dom_element($document, $order_element, 'orderoptions');
				Core_Xml::create_dom_element($document, $options, 'ordertype', $host_obj->card_action);
				Core_Xml::create_dom_element($document, $options, 'result', 'LIVE');
				
				$creditcard = Core_Xml::create_dom_element($document, $order_element, 'creditcard');
				Core_Xml::create_dom_element($document, $creditcard, 'cardnumber', $validation->fieldValues['ACCT']);
				Core_Xml::create_dom_element($document, $creditcard, 'cardexpmonth', $this->safe_xml($expMonth));
				Core_Xml::create_dom_element($document, $creditcard, 'cardexpyear', $this->safe_xml($expYear));
				Core_Xml::create_dom_element($document, $creditcard, 'cvmvalue', $validation->fieldValues['CVV2']);
				
				$ind_value = post('CVV2_NOT_PRESENT') ? 'not_present' : 'provided';
				Core_Xml::create_dom_element($document, $creditcard, 'cvmindicator', $ind_value);

				/*
				 * Merchant information
				 */
				
				$merchantinfo = Core_Xml::create_dom_element($document, $order_element, 'merchantinfo');
				Core_Xml::create_dom_element($document, $merchantinfo, 'configfile', $host_obj->store_id);
				Core_Xml::create_dom_element($document, $merchantinfo, 'host', substr($host_obj->server_url, 8));
				Core_Xml::create_dom_element($document, $merchantinfo, 'port', $host_obj->server_port);
				Core_Xml::create_dom_element($document, $merchantinfo, 'keyfile', 'keyfile.pem');
				
				/*
				 * Payment information
				 */

				$payment = Core_Xml::create_dom_element($document, $order_element, 'payment');
				Core_Xml::create_dom_element($document, $payment, 'chargetotal', $order->total);
				Core_Xml::create_dom_element($document, $payment, 'tax', $order->goods_tax + $order->shipping_tax);
				Core_Xml::create_dom_element($document, $payment, 'shipping', $order->shipping_quote);
				Core_Xml::create_dom_element($document, $payment, 'subtotal', $order->subtotal);

				/*
				 * Billing information
				 */

				$billing = Core_Xml::create_dom_element($document, $order_element, 'billing');
				Core_Xml::create_dom_element($document, $billing, 'name', $this->safe_xml($validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME']));
				Core_Xml::create_dom_element($document, $billing, 'company', $this->safe_xml($order->billing_company));
				Core_Xml::create_dom_element($document, $billing, 'address1', $this->safe_xml($order->billing_street_addr));
				Core_Xml::create_dom_element($document, $billing, 'city', $this->safe_xml($order->billing_city));

				if ($order->billing_state)
					Core_Xml::create_dom_element($document, $billing, 'state', $order->billing_state->code);
				Core_Xml::create_dom_element($document, $billing, 'zip', $this->safe_xml($order->billing_zip));
				Core_Xml::create_dom_element($document, $billing, 'country', $order->billing_country->code);
				Core_Xml::create_dom_element($document, $billing, 'email', $this->safe_xml($order->billing_email));
				Core_Xml::create_dom_element($document, $billing, 'phone', $this->safe_xml($order->billing_phone));

				/*
				 * Shipping information
				 */

				$shipping = Core_Xml::create_dom_element($document, $order_element, 'shipping');
				Core_Xml::create_dom_element($document, $shipping, 'name', $this->safe_xml($order->shipping_first_name.' '.$order->shipping_last_name));
				Core_Xml::create_dom_element($document, $shipping, 'address1', $this->safe_xml($order->shipping_street_addr));
				Core_Xml::create_dom_element($document, $shipping, 'city', $this->safe_xml($order->shipping_city));
				if ($order->shipping_state)
					Core_Xml::create_dom_element($document, $shipping, 'state', $order->shipping_state->code);
				Core_Xml::create_dom_element($document, $shipping, 'zip', $order->shipping_zip);
				Core_Xml::create_dom_element($document, $shipping, 'country', $order->shipping_country->code);

				/*
				 * Transaction details
				 */
				
				$timestamp = Phpr_Date::userDate(Phpr_DateTime::gmtNow())->format('%H:%M:%S');

				$trans_details = Core_Xml::create_dom_element($document, $order_element, 'transactiondetails');
				Core_Xml::create_dom_element($document, $trans_details, 'oid', $order->id.'-'.$timestamp);
				Core_Xml::create_dom_element($document, $trans_details, 'ponumber', $this->safe_xml($order->billing_phone));
				
				$exempt = ($order->goods_tax + $order->shipping_tax) > 0 ? 'n' : 'y';
				Core_Xml::create_dom_element($document, $trans_details, 'taxexempt', $exempt);
				Core_Xml::create_dom_element($document, $trans_details, 'terminaltype', 'UNSPECIFIED');
				Core_Xml::create_dom_element($document, $trans_details, 'ip', $userIp);
				Core_Xml::create_dom_element($document, $trans_details, 'transactionorigin', 'ECI');

				/*
				 * Notes
				 */

				$notes = Core_Xml::create_dom_element($document, $order_element, 'notes');
				Core_Xml::create_dom_element($document, $notes, 'comments', $order->customer_notes);
				
				/*
				 * Items
				 */
				
				$items = Core_Xml::create_dom_element($document, $order_element, 'items');
				foreach ($order->items as $item)
				{
					$item_node = Core_Xml::create_dom_element($document, $items, 'item');
					Core_Xml::create_dom_element($document, $item_node, 'id', $item->product_sku);
					Core_Xml::create_dom_element($document, $item_node, 'description', h($item->output_product_name(true, true)));
					Core_Xml::create_dom_element($document, $item_node, 'quantity', $item->quantity);
					Core_Xml::create_dom_element($document, $item_node, 'price', $item->unit_total_price);
				}

				/*
				 * Post request
				 */
				
				$fields = Core_Xml::to_plain_array($document, true);
				$response = $this->post_data($host_obj, $document);

				/*
				 * Process result
				 */

				$response_fields = $this->parse_response($response);

				if (!array_key_exists('r_approved', $response_fields))
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');

				if ($response_fields['r_approved'] != 'APPROVED')
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
				if (isset($response_fields['r_error']))
					$error_message = $response_fields['r_error'];

				if (!$error_message && isset($response_fields['r_message']))
					$error_message = $response_fields['r_message'];
				
				$this->log_payment_attempt($order, $error_message, 0, $fields, $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));

				if (!$back_end)
					throw new Phpr_ApplicationException($ex->getMessage());
				else
					throw new Phpr_ApplicationException($error_message);
			}			
		}
		
		protected function safe_xml($str)
		{
			$str = str_replace('&', '&amp;', $str);
			
			return $str;
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in First Data API payment method.');
		}
	}

?>