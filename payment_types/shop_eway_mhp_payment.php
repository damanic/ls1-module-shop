<?

	class Shop_Eway_Mhp_Payment extends Shop_PaymentType
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
				'name'=>'eWAY Merchant Hosted Payment',
				'description'=>'Australian payment gateway. The payment form is hosted on your server.'
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
			$host_obj->add_field('test_gateway', 'Use Test Gateway')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Use eWay Test gateway. You can find test customer ID and credit card number on <a href="http://www.eway.com.au/Developer/eway-api/hosted-payment-solution.aspx" target="_blank">this page</a>.', 'above', true);

			if ($context !== 'preview')
			{
				$host_obj->add_field('ewayCustomerID', 'Customer Id')->comment('Your unique eWAY customer ID assigned to you when you join eWAY. eg 11438715', 'above')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide Customer Id.');
			}

			$host_obj->add_field('card_action', 'Card Action', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_card_action_options($current_key_value = -1)
		{
			$options = array(
				'REALTIME'=>'Purchase',
				'STORE'=>'Authorize only (reserve funds)'
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
			$host_obj->add_field('CVV2', 'Card Code', 'right')->renderAs(frm_text)->validation()->fn('trim')->numeric();

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
		}

		/*
		 * Payment processing
		 */

		private function post_data($host_obj, $document)
		{
			$field_str = $document->saveXML();
			
			if (!$host_obj->test_gateway)
			{
				if ($host_obj->card_action == 'REALTIME')
				{
					$endpoint = 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';
				} else
				{
					$endpoint = 'https://www.eway.com.au/gateway/xmlstored.asp';
				}
			} else
				$endpoint = 'https://www.eway.com.au/gateway/xmltest/testpage.asp';

			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, $endpoint);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 40);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $field_str);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
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
			if (isset($fields['ewayCustomerID']))
				unset($fields['ewayCustomerID']);

			if (isset($fields['ewayCardNumber']))
				$fields['ewayCardNumber'] = '...'.substr($fields['ewayCardNumber'], -4);
			
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
				$root_element = Core_Xml::create_dom_element($document, $document, 'ewaygateway');
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerID', $host_obj->ewayCustomerID);
				Core_Xml::create_dom_element($document, $root_element, 'ewayTotalAmount', round($order->total*100));
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerFirstName', $order->billing_first_name);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerLastName', $order->billing_last_name);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerEmail', $order->billing_email);
				
				$cust_address = $order->billing_street_addr.', '.$order->billing_city;
				if ($order->billing_state)
					$cust_address .= ', '.$order->billing_state->code;
					
				$cust_address .= ', '.$order->billing_country->name;
				
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerAddress', Phpr_Html::strTrim($cust_address, 255));
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerPostcode', Phpr_Html::strTrim($order->billing_zip, 6));

				$product_descriptions = array();
				foreach ($order->items as $item)
				{
					$product_description = str_replace(array("\n", "&amp;", "&"), array("", " and ", " and "), $item->output_product_name(true, true));
					$product_description .= ' x '.$item->quantity;
					$product_descriptions[] = $product_description;
				}
				
				$product_descriptions = implode(",\n", $product_descriptions);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerInvoiceDescription', Phpr_Html::strTrim($product_descriptions, 255), true);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCustomerInvoiceRef', $order->id);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCardHoldersName', Phpr_Html::strTrim($validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME'], 50));
				Core_Xml::create_dom_element($document, $root_element, 'ewayCardNumber', $validation->fieldValues['ACCT']);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCardExpiryMonth', $expMonth);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCardExpiryYear', $expYear);
				Core_Xml::create_dom_element($document, $root_element, 'ewayCVN', $validation->fieldValues['CVV2']);
				
				Core_Xml::create_dom_element($document, $root_element, 'ewayTrxnNumber', '');
				Core_Xml::create_dom_element($document, $root_element, 'ewayOption1', '');
				Core_Xml::create_dom_element($document, $root_element, 'ewayOption2', '');
				Core_Xml::create_dom_element($document, $root_element, 'ewayOption3', '');

				/*
				 * Post request
				 */
				
				$fields = Core_Xml::to_plain_array($document, true);
				$response = $this->post_data($host_obj, $document);

				/*
				 * Process result
				 */

				$response_fields = $this->parse_response($response);

				if (!array_key_exists('ewayTrxnStatus', $response_fields))
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');

				if ($response_fields['ewayTrxnStatus'] != 'True')
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
				if (isset($response_fields['ewayTrxnError']))
					$error_message = $response_fields['ewayTrxnError'];

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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in eWay Hosted Payments method.');
		}
	}

?>