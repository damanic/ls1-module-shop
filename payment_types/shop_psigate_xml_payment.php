<?

	class Shop_PsiGate_Xml_Payment extends Shop_PaymentType
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
				'name'=>'PSiGate XML',
				'description'=>'PSiGate XML payment method for USA and Canada.'
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
			$host_obj->add_field('test_mode', 'Use Test Server')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Connect to PSiGate test server (dev.psigate.com). Use this option of you have PSiGate developer test account.<br/><br/><strong>Important!</strong> 
			PSiGate module requires port 7934 to be open in the server firewall. The test mode uses port number 7989.', 'above', true);

			if ($context !== 'preview')
			{
				$host_obj->add_field('store_id', 'PSiGate StoreID', 'left')->tab('Configuration')->renderAs(frm_text)->comment('PSiGate provides the StoreID within the PSiGate Welcome Email.', 'above')->validation()->fn('trim')->required('Please provide StoreID.');
				$host_obj->add_field('passphrase', 'Passphrase', 'right')->tab('Configuration')->renderAs(frm_text)->comment('PSiGate provides the Passphrase within the PSiGate Welcome Email.', 'above')->validation()->fn('trim')->required('Please provide Passphrase.');
			}

			$host_obj->add_field('card_action', 'Card Action', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_card_action_options($current_key_value = -1)
		{
			$options = array(
				0=>'Sale (capture funds)',
				1=>'PreAuth (reserve funds)'
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

		private function format_form_fields(&$fields)
		{
			$result = array();
			foreach($fields as $key=>$val)
			    $result[] = urlencode($key)."=".urlencode($val); 
			
			return implode('&', $result);
		}
		
		
		private function fields_to_plain_array($fields)
		{
			$result = array();

			foreach ($fields as $name=>$value)
			{
				if (!is_array($value))
				{
					if (strlen($value))
						$result[$name] = $value;
				}
				else
				{
					foreach ($value as $item_index=>$item_data)
					{
						$item_params = array();
						foreach ($item_data as $item_key=>$item_value)
							$item_params[] = $item_key.'='.$item_value;
						
						$result['Item '.$item_index] = implode(', ', $item_params);
					}
				}
			}
			
			return $result;
		}
		
		private function fields_to_xml($fields)
		{
			$field_str = '<?xml version="1.0" encoding="UTF-8"?><Order>';

			foreach ($fields as $name=>$value)
			{
				if (!is_array($value))
				{
					if (strlen($value))
						$field_str .= "<$name>".h($value)."</$name>";
				}
				else
				{
					foreach ($value as $item_data)
					{
						$field_str .= '<Item>';
						foreach ($item_data as $item_key=>$item_value)
							$field_str .= "<$item_key>".h($item_value)."</$item_key>";
						
						$field_str .= '</Item>';
					}
				}
			}
			
			$field_str .= '</Order>';
			
			return $field_str;
		}
		
		private function post_data($endpoint, $endport, $fields)
		{
			$field_str = $this->fields_to_xml($fields);
			$url = "https://".$endpoint.":".$endport."/Messenger/XMLMessenger";

			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, $url);
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
			$vals = array();
			$index = array();
			$xml_parser = xml_parser_create();
			xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 0);
			xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 1);
			xml_parse_into_struct($xml_parser, $response, $vals, $index);
			xml_parser_free($xml_parser);

			$result = array();
			foreach ($index as $key=>$data)
			{
				$value = $vals[$data[0]];
				if (isset($value['value']))
					$result[$key] = $value['value'];
			}

			return $result;
		}

		private function prepare_fields_log($fields)
		{
			unset($fields['StoreID']);
			unset($fields['Passphrase']);
			unset($fields['CardIDNumber']);
			$fields['CardNumber'] = '...'.substr($fields['CardNumber'], -4);
			
			return $fields;
		}
		
		protected function get_avs_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'AVS response code is empty';
			
			$status_names = array(
				'X'=>'Address and 9-digit ZIP code match.',
				'Y'=>'Address and 5-digit ZIP code match.',
				'A'=>'Address matches; ZIP code does NOT.',
				'W'=>'Address does NOT match; 9-digit ZIP code matches.',
				'Z'=>'Address does NOT match; 5-digit ZIP code matches.',
				'N'=>'Address does NOT match; ZIP code does NOT match.',
				'U'=>'Information unavailable or card-issuing bank does not support AVS.',
				'S'=>'Card-issuing bank does NOT support AVS.',
				'R'=>'The system was unavailable or timed out.',
				'E'=>'Transaction ineligible for AVS or edit error found.',
				'D'=>'Street Address and Postal Code match for International Transaction',
				'M'=>'Street Address and Postal Code match for International Transaction',
				'B'=>'Street Address Match for International Transaction. Postal Code not verified due to incompatible formats',
				'P'=>'Postal Codes match for International Transaction but street address not verified due to incompatible formats',
				'C'=>'Street Address and Postal Code not verified for International Transaction due to incompatible formats',
				'I'=>'Address Information not verified by International issuer',
				'G'=>'Non-US. Issuer does not participate'
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
				'S'=>'Not passed',
				'U'=>'Issuer does not support CardID verification'
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
			
			$endport = $host_obj->test_mode ? 7989 : 7934;
			$endpoint = $host_obj->test_mode ? "dev.psigate.com" : "secure.psigate.com";

			$fields = array();
			$response = null;
			$response_fields = array();

			try
			{
				$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];
				$expYear = $validation->fieldValues['EXPDATE_YEAR'] > 2000 ? $validation->fieldValues['EXPDATE_YEAR'] - 2000 : $validation->fieldValues['EXPDATE_YEAR'];
				if ($expYear < 10)
					$expYear = '0'.$expYear;

				$userIp = Phpr::$request->getUserIp();
				if ($userIp == '::1')
					$userIp = '192.168.0.1';

				$fields['StoreID'] = $host_obj->store_id;
				$fields['Passphrase'] = $host_obj->passphrase;
				$fields['PaymentType'] = 'CC';
				$fields['CardAction'] = $host_obj->card_action;
				$fields['CardNumber'] = $validation->fieldValues['ACCT'];
				$fields['CardExpMonth'] = $expMonth;
				$fields['CardExpYear'] = $expYear;
				$fields['CardIDNumber'] = $validation->fieldValues['CVV2'];

				$fields['Subtotal'] = $order->subtotal;
				$fields['Bname'] = $validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME'];
				$fields['Bcompany'] = $order->billing_company;
				$fields['Baddress1'] = $order->billing_street_addr;
				$fields['Bcity'] = $order->billing_city;
				
				if ($order->billing_state)
					$fields['Bprovince'] = $order->billing_state->name;
					
				$fields['Bpostalcode'] = $order->billing_zip;
				$fields['Bcountry'] = $order->billing_country->name;
				$fields['Phone'] = $order->billing_phone;
				$fields['Email'] = $order->billing_email;
				
				$fields['Tax1'] = $order->goods_tax + $order->shipping_tax;
				$fields['ShippingTotal'] = $order->shipping_quote;
				
				$fields['CustomerIP'] = $userIp;
				$fields['OrderID'] = $order->id;

				$fields['Items'] = array();
				
				$items = array();
				$item_index = 0;
				foreach ($order->items as $item)
				{
					$item_array = array();
					$item_array['ItemID'] = $item->id;
					$item_array['ItemDescription'] = $item->output_product_name(true, true);
					$item_array['ItemQty'] = $item->quantity;
					$item_array['ItemPrice'] = $item->unit_total_price;
					
					$fields['Items'][] = $item_array;
				}

				$response = $this->post_data($endpoint, $endport, $fields);

				/*
				 * Process result
				 */
		
				$response_fields = $this->parse_response($response);
				if (!isset($response_fields['Approved']))
					throw new Phpr_ApplicationException('Invalid payment gateway response.');
					
				if ($response_fields['Approved'] !== 'APPROVED')
				{
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
				}
		
				/*
				 * Successful payment. Set order status and mark it as paid.
				 */
				
				$cvv_code = null;
				$cvv_message = null;
				$avs_code = null;
				$avs_message = null;
				
				if (array_key_exists('AVSResult', $response_fields))
				{
					$avs_code = $response_fields['AVSResult'];
					$avs_message = $this->get_avs_status_text($response_fields['AVSResult']);
				}

				if (array_key_exists('CardIDResult', $response_fields))
				{
					$cvv_code = $response_fields['CardIDResult'];
					$cvv_message = $this->get_ccv_status_text($response_fields['CardIDResult']);
				}

				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					$this->prepare_fields_log($this->fields_to_plain_array($fields)), 
					$response_fields, 
					$response,
					$cvv_code,
					$cvv_message,
					$avs_code,
					$avs_message
				);

				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
				$order->set_payment_processed();
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($this->fields_to_plain_array($fields));
				
				$error_message = $ex->getMessage();
				if (isset($response_fields['ErrMsg']))
					$error_message = $response_fields['ErrMsg'];
					
				$cvv_code = null;
				$cvv_message = null;
				$avs_code = null;
				$avs_message = null;
				
				if (array_key_exists('AVSResult', $response_fields))
				{
					$avs_code = $response_fields['AVSResult'];
					$avs_message = $this->get_avs_status_text($response_fields['AVSResult']);
				}

				if (array_key_exists('CardIDResult', $response_fields))
				{
					$cvv_code = $response_fields['CardIDResult'];
					$cvv_message = $this->get_ccv_status_text($response_fields['CardIDResult']);
				}
				
				$this->log_payment_attempt(
					$order, 
					$error_message, 
					0, 
					$fields, 
					$response_fields, 
					$response,
					$cvv_code,
					$cvv_message,
					$avs_code,
					$avs_message
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in PSiGate XML payment method.');
		}
	}

?>