<?

	class Shop_Braintree_TranspRedirect_Payment extends Shop_PaymentType
	{
		protected static $environment_included = false;
		protected $error_result = null;
		
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
				'name'=>'Braintree Transparent Redirect',
				'description'=>'Implementation of the Braintree tokenization integration method.',
				'custom_payment_form'=>'backend_payment_form.htm'
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
			$host_obj->add_field('test_mode', 'Sandbox Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('The sandbox environment is used for testing integration and feature configuration.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_field('merchant_id', 'Merchant Id')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide Merchant ID.');
				$host_obj->add_field('public_key', 'Public Key', 'left')->tab('Configuration')->renderAs(frm_password)->validation()->fn('trim');
				$host_obj->add_field('private_key', 'Private Key', 'right')->tab('Configuration')->renderAs(frm_password)->validation()->fn('trim');
			}

			$host_obj->add_field('card_action', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_card_action_options($current_key_value = -1)
		{
			$options = array(
				'Sale'=>'Sale',
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
			$public_key = trim($host_obj->public_key);
			$private_key = trim($host_obj->private_key);
			
			if (!strlen($public_key))
			{
				if (!isset($host_obj->fetched_data['public_key']) || !strlen($host_obj->fetched_data['public_key']))
					$host_obj->validation->setError('Please enter public key', 'public_key', true);

				$host_obj->public_key = $host_obj->fetched_data['public_key'];
			}

			if (!strlen($private_key))
			{
				if (!isset($host_obj->fetched_data['private_key']) || !strlen($host_obj->fetched_data['private_key']))
					$host_obj->validation->setError('Please enter private key', 'private_key', true);

				$host_obj->private_key = $host_obj->fetched_data['private_key'];
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
			$host_obj->order_status = Shop_OrderStatus::get_status_paid()->id;
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in the Beanstream Transparent Redirect payment method.');
		}
		
		protected function init_braintree_environment($host_obj)
		{
			if (self::$environment_included)
				return;
				
			require_once(PATH_APP.'/modules/shop/payment_types/shop_braintree_transpredirect_payment/Braintree.php');

			Braintree_Configuration::environment($host_obj->test_mode ? 'sandbox' : 'production');
			Braintree_Configuration::merchantId($host_obj->merchant_id);
			Braintree_Configuration::publicKey($host_obj->public_key);
			Braintree_Configuration::privateKey($host_obj->private_key);
		}
		
		public function get_form_action($host_obj, $order, $backend = false)
		{
			$this->init_braintree_environment($host_obj);
			$this->process_redirect($host_obj, $order, $backend);
			return Braintree_TransparentRedirect::url();
		}
		
		public function get_field_value($name)
		{
			if (!$this->error_result)
				return null;
				
			return h($this->error_result->valueForHtmlField($name));
		}
		
		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$this->init_braintree_environment($host_obj);

			$currentUrl = Phpr::$request->getCurrentUrl();
			$currentUrl = preg_replace('/\?.*$/', '', $currentUrl);

			$transaction_data = array(
				'redirectUrl' => $currentUrl,
				'transaction' => array(
					'amount' => $order->total, 
					'type' => 'sale',
					'orderId' => $order->id,
					'customer' => array(
						'firstName' => $order->billing_first_name,
						'lastName' => $order->billing_last_name,
						'email' => $order->billing_email
					),
					'billing' => array(
						'company' => $order->billing_company,
						'streetAddress' => $order->billing_street_addr,
						'locality'=>$order->billing_city,
						'postalCode' => $order->billing_zip,
						'region' => ($order->billing_state ? $order->billing_state->name : null)
					)
				)
			);
			
			$profile = $host_obj->find_customer_profile($order->customer);
			if ($profile)
				$transaction_data['transaction']['customerId'] = $order->customer_id;
			else
				$transaction_data['transaction']['customer']['id'] = $order->customer_id;

			$country_name = $this->get_country_name($order->billing_country->code_3);
			if ($country_name)
				$transaction_data['transaction']['billing']['countryName'] = $country_name;

			if ($host_obj->card_action == 'Sale')
				$transaction_data['transaction']['options'] = array('submitForSettlement'=>1);

			$tr_data = Braintree_TransparentRedirect::transactionData($transaction_data);
			$fields['tr_data'] = $tr_data;

			return $fields;
		}
		
		protected function get_ccv_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'CCV response code is empty';

			$status_names = array(
				'M'=>'Match',
				'N'=>'Did not match',
				'U'=>'Not verified',
				'I'=>'Not provided',
				'A'=>'Not applicable'
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown CCV response code';
		}
		
		protected function get_avs_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'AVS response code is empty';
			
			$status_names = array(
				'M'=>'Matches',
				'N'=>'Does not Match',
				'U'=>'Not Verified',
				'I'=>'Not Provided',
				'A'=>'Not Applicable'
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown AVS response code';
		}
		
		protected function format_avs_result_code($transaction)
		{
			return $transaction->avsStreetAddressResponseCode.'/'.$transaction->avsPostalCodeResponseCode;
		}
		
		protected function format_avs_result_message($transaction)
		{
			$result = 'Street address: '.$this->get_avs_status_text($transaction->avsStreetAddressResponseCode).'.';
			$result .= ' Postal code: '.$this->get_avs_status_text($transaction->avsPostalCodeResponseCode).'. ';
			if ($transaction->avsErrorResponseCode)
			{
				$result .= 'AVS error: ';
				switch ($transaction->avsErrorResponseCode)
				{
					case 'S' :
						$result .= 'issuing bank does not support AVS';
					break;
					case 'E' :
						$result .= 'AVS system error';
					break;
					case 'A': 
						$result .= 'not applicable';
					break;
					default:
						$result .= 'unknown';
				}
			}
			
			return $result;
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
			 * This method is never called with the Braintree transparent redirect payment method
			 */
		}
		
		protected function process_redirect($host_obj, $order, $backend = false)
		{
			$this->init_braintree_environment($host_obj);

			try
			{
				$this->process_gateway_response($host_obj, $order, $backend);
			} catch (Exception $ex)
			{
				$error_message = Phpr::$session->flash['error'] = $this->get_exception_description($ex);
				
				$cvv_code = null;
				$cvv_message = null;
				$avs_code = null;
				$avs_message = null;

				if ($this->error_result && isset($this->error_result->transaction))
				{
					$cvv_code = $this->error_result->transaction->cvvResponseCode;
					$cvv_message = $this->get_ccv_status_text($cvv_code);
					$avs_code = $this->format_avs_result_code($this->error_result->transaction);
					$avs_message = $this->format_avs_result_message($this->error_result->transaction);
				}

				$this->log_payment_attempt(
					$order, 
					$error_message, 
					0, 
					array(), 
					$this->prepare_response_log(Braintree_Transaction::$response_array), 
					null,
					$cvv_code,
					$cvv_message,
					$avs_code, 
					$avs_message
				);
				Phpr::$session->flash['error'] = $error_message;
			}
		}
		
		protected function get_exception_description($ex)
		{
			if ($ex instanceof Braintree_Exception_ForgedQueryString)
				return 'Invalid query string';
				
			if ($ex instanceof Braintree_Exception_NotFound)
				return 'Record not found';

			if ($ex instanceof Braintree_Exception_Unexpected)
				return 'Payment gateway returned unexpected error';
			
			if ($ex instanceof Braintree_Exception_SSLCertificate)
				return 'Invalid SSL certificate';
			
			if ($ex instanceof Braintree_Exception_ServerError)
				return 'Payment gateway error';
			
			if ($ex instanceof Braintree_Exception_DownForMaintenance)
				return 'The payment gateway is down for maintenance';
			
			if ($ex instanceof Braintree_Exception_Configuration)
				return 'Invalid payment method configuration';
			
			if ($ex instanceof Braintree_Exception_Authorization)
				return 'Payment gateway authorization failed';
				
			if ($ex instanceof Braintree_Exception_Authentication)
				return 'Payment gateway authentication failed';

			return $ex->getMessage();
		}
		
		protected function process_gateway_response($host_obj, $order, $backend = false)
		{
			if (Phpr::$request->getField('id'))
			{
				$query_string = preg_replace('/^q=[^&]+&/', '', $_SERVER['QUERY_STRING']);

				$result = Braintree_TransparentRedirect::confirm($query_string);

				$this->process_transaction_response($host_obj, $order, $backend, $result, true);
			}
		}
		
		protected function process_transaction_response($host_obj, $order, $backend, $result, $process_token, $redirect = true)
		{
			if ($result->transaction != null || isset($result->transaction->status))
			{
				$this->error_result = $result;

				if ($result->transaction->status == Braintree_Transaction::GATEWAY_REJECTED)
				{
					$message = 'Transaction has been rejected by the payment gateway. ';
					if ($result->transaction->gatewayRejectionReason == 'cvv')
						$message .= 'Reason: invalid card validation number';
					elseif ($result->transaction->gatewayRejectionReason == 'avs')
						$message .= 'Reason: billing address verification failed.';
					elseif ($result->transaction->gatewayRejectionReason == 'avs_and_cvv')
						$message .= 'Reason: card validation number and billing address verification failed.';
					elseif ($result->transaction->gatewayRejectionReason == 'duplicate')
						$message .= 'Reason: duplicate transaction.';
					else
						$message .= 'Error message: '.$result->message;
					
					throw new Phpr_ApplicationException($message);
				}

				if ($result->transaction->status == Braintree_Transaction::FAILED)
					throw new Phpr_ApplicationException('Transaction failed.');

				if ($result->transaction->status == Braintree_Transaction::PROCESSOR_DECLINED)
					throw new Phpr_ApplicationException('Transaction declined.');
			}

			if ($result->success)
			{
				$transaction = $result->transaction;

				if ($transaction->amount != $order->total)
					throw new Phpr_ApplicationException('Invalid order total');

				if ($transaction->orderId != $order->id)
					throw new Phpr_ApplicationException('Order not found');
				
				/*
				 * Log payment attempt
				 */
				$cvv_code = $transaction->cvvResponseCode;
				$cvv_message = $this->get_ccv_status_text($cvv_code);
				$avs_code = $this->format_avs_result_code($transaction);
				$avs_message = $this->format_avs_result_message($transaction);
				
				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					array(), 
					$this->prepare_response_log(Braintree_Transaction::$response_array), 
					null, 
					$cvv_code,
					$cvv_message,
					$avs_code, 
					$avs_message
				);

				/*
				 * Log transaction create/change
				 */
				$this->update_transaction_status($host_obj, $order, $transaction->id, $this->get_status_name($transaction->status), $transaction->status);

				/*
				 * Change order status
				 */
				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);

				/*
				 * Mark order as paid
				 */
				$order->set_payment_processed();
				
				/*
				 * Save the customer payment profile if the CC token is presented in the response
				 */
				
				if ($process_token && $transaction->creditCardDetails->token)
				{
					$profile_data = array('token'=>$transaction->creditCardDetails->token);

					$profile = $host_obj->find_customer_profile($order->customer);
					if (!$profile)
						$profile = $host_obj->init_customer_profile($order->customer);
					
					$profile->set_profile_data($profile_data, $transaction->creditCardDetails->last4);
				}
				
				if (!$backend)
				{
					if ($redirect)
					{
						$return_page = $order->payment_method->receipt_page;
						if ($return_page)
							Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
						else 
							throw new Phpr_SystemException('Braintree Transparent Redirect receipt page is not found.');
					}
				} else if($redirect)
					Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
			} else {
				$this->error_result = $result;

				$errors = $result->errors->deepAll();
				$error_str = array();
				foreach ($errors as $error)
					$error_str[] = $error->message;

				$error_str = implode(' ', $error_str);
				throw new Phpr_ApplicationException($error_str);
			}
		}
		
		protected function get_status_name($status_id)
		{
			$names = array(
				Braintree_Transaction::AUTHORIZING => 'Authorizing',
				Braintree_Transaction::AUTHORIZED => 'Authorized',
				Braintree_Transaction::GATEWAY_REJECTED => 'Rejected by the gateway',
				Braintree_Transaction::FAILED => 'Failed',
				Braintree_Transaction::PROCESSOR_DECLINED => 'Declined by the processor',
				Braintree_Transaction::SETTLED => 'Settled',
				Braintree_Transaction::SETTLING => 'Settling',
				Braintree_Transaction::SUBMITTED_FOR_SETTLEMENT => 'Submitted for settlement',
				Braintree_Transaction::VOIDED => 'Voided'
			);
			
			if (array_key_exists($status_id, $names))
				return $names[$status_id];
				
			return 'Unknown';
		}
		
		protected function prepare_response_log($array)
		{
			$response = $this->flatten_array($array);

			$result = array();
			foreach ($response as $key=>$value)
			{
				$key = str_replace('_apiErrorResponse_', '', $key);
				if (is_object($value))
					continue;

				$result[$key] = $value;
			}
			
			if (isset($result['params_trData']))
				unset($result['params_trData']);

			if (isset($result['params_publicKey']))
				unset($result['params_publicKey']);
				
			return $result;
		}
		
		protected function flatten_array($array, $parent_key_name = null)
		{
			$result = array();
			foreach ($array as $key=>$values)
			{
				$key_name = $parent_key_name.'_'.$key;
				
				if (is_array($values))
					$result += $this->flatten_array($values, $key_name);
				else
					$result[$key_name] = $values;
			}
			
			return $result;
		}

		protected function get_country_name($code)
		{
			$countries = array(
				'AFG' => 'Afghanistan',
				'ALB' => 'Albania',
				'DZA' => 'Algeria',
				'ASM' => 'American Samoa',
				'AND' => 'Andorra',
				'AGO' => 'Angola',
				'AIA' => 'Anguilla',
				'ATA' => 'Antarctica',
				'ATG' => 'Antigua and Barbuda',
				'ARG' => 'Argentina',
				'ARM' => 'Armenia',
				'ABW' => 'Aruba',
				'AUS' => 'Australia',
				'AUT' => 'Austria',
				'AZE' => 'Azerbaijan',
				'BHS' => 'Bahamas',
				'BHR' => 'Bahrain',
				'BGD' => 'Bangladesh',
				'BRB' => 'Barbados',
				'BLR' => 'Belarus',
				'BEL' => 'Belgium',
				'BLZ' => 'Belize',
				'BEN' => 'Benin',
				'BMU' => 'Bermuda',
				'BTN' => 'Bhutan',
				'BOL' => 'Bolivia',
				'BIH' => 'Bosnia and Herzegovina',
				'BWA' => 'Botswana',
				'BVT' => 'Bouvet Island',
				'BRA' => 'Brazil',
				'IOT' => 'British Indian Ocean Territory',
				'BRN' => 'Brunei Darussalam',
				'BGR' => 'Bulgaria',
				'BFA' => 'Burkina Faso',
				'BDI' => 'Burundi',
				'KHM' => 'Cambodia',
				'CMR' => 'Cameroon',
				'CAN' => 'Canada',
				'CPV' => 'Cape Verde',
				'CYM' => 'Cayman Islands',
				'CAF' => 'Central African Republic',
				'TCD' => 'Chad',
				'CHL' => 'Chile',
				'CHN' => 'China',
				'CXR' => 'Christmas Island',
				'CCK' => 'Cocos (Keeling) Islands',
				'COL' => 'Colombia',
				'COM' => 'Comoros',
				'COG' => 'Congo (Brazzaville)',
				'COD' => 'Congo (Kinshasa)',
				'COK' => 'Cook Islands',
				'CRI' => 'Costa Rica',
				'CIV' => 'CÃ´te d’Ivoire',
				'HRV' => 'Croatia',
				'CUB' => 'Cuba',
				'CYP' => 'Cyprus',
				'CZE' => 'Czech Republic',
				'DNK' => 'Denmark',
				'DJI' => 'Djibouti',
				'DMA' => 'Dominica',
				'DOM' => 'Dominican Republic',
				'ECU' => 'Ecuador',
				'EGY' => 'Egypt',
				'SLV' => 'El Salvador',
				'GNQ' => 'Equatorial Guinea',
				'ERI' => 'Eritrea',
				'EST' => 'Estonia',
				'ETH' => 'Ethiopia',
				'FLK' => 'Falkland Islands',
				'FRO' => 'Faroe Islands',
				'FJI' => 'Fiji',
				'FIN' => 'Finland',
				'FRA' => 'France',
				'GUF' => 'French Guiana',
				'PYF' => 'French Polynesia',
				'ATF' => 'French Southern Lands',
				'GAB' => 'Gabon',
				'GMB' => 'Gambia',
				'GEO' => 'Georgia',
				'DEU' => 'Germany',
				'GHA' => 'Ghana',
				'GIB' => 'Gibraltar',
				'GRC' => 'Greece',
				'GRL' => 'Greenland',
				'GRD' => 'Grenada',
				'GLP' => 'Guadeloupe',
				'GUM' => 'Guam',
				'GTM' => 'Guatemala',
				'GGY' => 'Guernsey',
				'GIN' => 'Guinea',
				'GNB' => 'Guinea-Bissau',
				'GUY' => 'Guyana',
				'HTI' => 'Haiti',
				'HMD' => 'Heard and McDonald Islands',
				'HND' => 'Honduras',
				'HKG' => 'Hong Kong',
				'HUN' => 'Hungary',
				'ISL' => 'Iceland',
				'IND' => 'India',
				'IDN' => 'Indonesia',
				'IRN' => 'Iran',
				'IRQ' => 'Iraq',
				'IRL' => 'Ireland',
				'IMN' => 'Isle of Man',
				'ISR' => 'Israel',
				'ITA' => 'Italy',
				'JAM' => 'Jamaica',
				'JPN' => 'Japan',
				'JEY' => 'Jersey',
				'JOR' => 'Jordan',
				'KAZ' => 'Kazakhstan',
				'KEN' => 'Kenya',
				'KIR' => 'Kiribati',
				'PRK' => 'Korea, North',
				'KOR' => 'Korea, South',
				'KWT' => 'Kuwait',
				'KGZ' => 'Kyrgyzstan',
				'LAO' => 'Laos',
				'LVA' => 'Latvia',
				'LBN' => 'Lebanon',
				'LSO' => 'Lesotho',
				'LBR' => 'Liberia',
				'LBY' => 'Libya',
				'LIE' => 'Liechtenstein',
				'LTU' => 'Lithuania',
				'LUX' => 'Luxembourg',
				'MAC' => 'Macau',
				'MKD' => 'Macedonia',
				'MDG' => 'Madagascar',
				'MWI' => 'Malawi',
				'MYS' => 'Malaysia',
				'MDV' => 'Maldives',
				'MLI' => 'Mali',
				'MLT' => 'Malta',
				'MHL' => 'Marshall Islands',
				'MTQ' => 'Martinique',
				'MRT' => 'Mauritania',
				'MUS' => 'Mauritius',
				'MYT' => 'Mayotte',
				'MEX' => 'Mexico',
				'FSM' => 'Micronesia',
				'MDA' => 'Moldova',
				'MCO' => 'Monaco',
				'MNG' => 'Mongolia',
				'MNE' => 'Montenegro',
				'MSR' => 'Montserrat',
				'MAR' => 'Morocco',
				'MOZ' => 'Mozambique',
				'MMR' => 'Myanmar',
				'NAM' => 'Namibia',
				'NRU' => 'Nauru',
				'NPL' => 'Nepal',
				'NLD' => 'Netherlands',
				'ANT' => 'Netherlands Antilles',
				'NCL' => 'New Caledonia',
				'NZL' => 'New Zealand',
				'NIC' => 'Nicaragua',
				'NER' => 'Niger',
				'NGA' => 'Nigeria',
				'NIU' => 'Niue',
				'NFK' => 'Norfolk Island',
				'MNP' => 'Northern Mariana Islands',
				'NOR' => 'Norway',
				'OMN' => 'Oman',
				'PAK' => 'Pakistan',
				'PLW' => 'Palau',
				'PSE' => 'Palestine',
				'PAN' => 'Panama',
				'PNG' => 'Papua New Guinea',
				'PRY' => 'Paraguay',
				'PER' => 'Peru',
				'PHL' => 'Philippines',
				'PCN' => 'Pitcairn',
				'POL' => 'Poland',
				'PRT' => 'Portugal',
				'PRI' => 'Puerto Rico',
				'QAT' => 'Qatar',
				'REU' => 'Reunion',
				'ROU' => 'Romania',
				'RUS' => 'Russian Federation',
				'RWA' => 'Rwanda',
				'BLM' => 'Saint Barthélemy',
				'SHN' => 'Saint Helena',
				'KNA' => 'Saint Kitts and Nevis',
				'LCA' => 'Saint Lucia',
				'MAF' => 'Saint Martin (French part)',
				'SPM' => 'Saint Pierre and Miquelon',
				'VCT' => 'Saint Vincent and the Grenadines',
				'WSM' => 'Samoa',
				'SMR' => 'San Marino',
				'STP' => 'Sao Tome and Principe',
				'SAU' => 'Saudi Arabia',
				'SEN' => 'Senegal',
				'SRB' => 'Serbia',
				'SYC' => 'Seychelles',
				'SLE' => 'Sierra Leone',
				'SGP' => 'Singapore',
				'SVK' => 'Slovakia',
				'SVN' => 'Slovenia',
				'SLB' => 'Solomon Islands',
				'SOM' => 'Somalia',
				'ZAF' => 'South Africa',
				'SGS' => 'South Georgia and South Sandwich Islands',
				'ESP' => 'Spain',
				'LKA' => 'Sri Lanka',
				'SDN' => 'Sudan',
				'SUR' => 'Suriname',
				'SJM' => 'Svalbard and Jan Mayen Islands',
				'SWZ' => 'Swaziland',
				'SWE' => 'Sweden',
				'CHE' => 'Switzerland',
				'SYR' => 'Syria',
				'TWN' => 'Taiwan',
				'TJK' => 'Tajikistan',
				'TZA' => 'Tanzania',
				'THA' => 'Thailand',
				'TLS' => 'Timor-Leste',
				'TGO' => 'Togo',
				'TKL' => 'Tokelau',
				'TON' => 'Tonga',
				'TTO' => 'Trinidad and Tobago',
				'TUN' => 'Tunisia',
				'TUR' => 'Turkey',
				'TKM' => 'Turkmenistan',
				'TCA' => 'Turks and Caicos Islands',
				'TUV' => 'Tuvalu',
				'UGA' => 'Uganda',
				'UKR' => 'Ukraine',
				'ARE' => 'United Arab Emirates',
				'GBR' => 'United Kingdom',
				'UMI' => 'United States Minor Outlying Islands',
				'USA' => 'United States of America',
				'URY' => 'Uruguay',
				'UZB' => 'Uzbekistan',
				'VUT' => 'Vanuatu',
				'VAT' => 'Vatican City',
				'VEN' => 'Venezuela',
				'VGB' => 'Virgin Islands, British',
				'VIR' => 'Virgin Islands, U.S.',
				'WLF' => 'Wallis and Futuna Islands',
				'ESH' => 'Western Sahara',
				'YEM' => 'Yemen',
				'ZMB' => 'Zambia',
				'ZWE' => 'Zimbabwe'
			);

			if (array_key_exists($code, $countries))
				return $countries[$code];
				
			return null;
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
			$this->init_braintree_environment($host_obj);

			switch ($transaction_status_code)
			{
				case Braintree_Transaction::AUTHORIZED :
					return array(
						'settle' => 'Submit for settlement',
						'void' => 'Void'
					);
				break;
				case Braintree_Transaction::SUBMITTED_FOR_SETTLEMENT :
					return array(
						'void' => 'Void'
					);
				break;
				case Braintree_Transaction::SETTLED :
					return array(
						'refund' => 'Refund'
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
			$this->init_braintree_environment($host_obj);

			try
			{
				$override_status_name = false;
				
				switch ($new_transaction_status_code)
				{
					case 'settle' : 
						$submitResult = Braintree_Transaction::submitForSettlement($transaction_id);
					break;
					case 'void' : 
						$submitResult = Braintree_Transaction::void($transaction_id);
					break;
					case 'refund' : 
						$submitResult = Braintree_Transaction::refund($transaction_id);
						$override_status_name = 'Refund/Submitted for settlement';
					break;
					default:
						throw new Phpr_ApplicationException('Unknown transaction status code: '.$new_transaction_status_code);
				}

				if (!$submitResult->success)
				{
					$errors = $submitResult->errors->deepAll();
					$error_str = array();
					foreach ($errors as $error)
						$error_str[] = $error->message;

					$error_str = implode(' ', $error_str);

					if ($error_str)
						throw new Phpr_ApplicationException($error_str);
					else
						throw new Phpr_ApplicationException('Error updating transaction status.');
				} else {
					$transaction = $submitResult->transaction;
					$status_name = $override_status_name ? $override_status_name : $this->get_status_name($transaction->status);
					return new Shop_TransactionUpdate(
						$transaction->status,
						$status_name
					);
				}
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException($this->get_exception_description($ex));
			}
		}
		
		/**
		 * Request a status of a specific transaction a specific transaction.
		 * The method must return an instance of the Shop_TransactionUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @return Shop_TransactionUpdate Transaction update information
		 */
		public function request_transaction_status($host_obj, $transaction_id)
		{
			$this->init_braintree_environment($host_obj);

			try
			{
				$transaction = Braintree_Transaction::find($transaction_id);
				if (!$transaction)
					throw new Phpr_ApplicationException('Transaction not found');

				return new Shop_TransactionUpdate(
					$transaction->status,
					$this->get_status_name($transaction->status)
				);
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException($this->get_exception_description($ex));
			}
		}

		/*
		 * Customer payment profiles support
		 */

		/**
		 * This method should return TRUE if the payment module supports customer payment profiles.
		 * The payment module must implement the update_customer_profile(), delete_customer_profile() and pay_from_profile() methods if this method returns true..
		 */
		public function supports_payment_profiles()
		{
			return true;
		}
		
		/**
		 * Creates a customer profile on the payment gateway. If the profile already exists the method should update it.
		 * @param Db_ActiveRecord $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Customer $customer Shop_Customer object to create a profile for
		 * @param array $data Posted payment form data
		 * @return Shop_CustomerPaymentProfile Returns the customer profile object
		 */
		public function update_customer_profile($host_obj, $customer, $data)
		{
			/* This method is never called in the transparent redirect scenario */
		}
		
		/**
		 * Deletes a customer profile from the payment gateway.
		 * @param Db_ActiveRecord $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Customer $customer Shop_Customer object
		 * @param Shop_CustomerPaymentProfile $profile Customer profile object
		 */
		public function delete_customer_profile($host_obj, $customer, $profile)
		{
			if (!isset($profile->profile_data['token']))
				return;
			
			$this->init_braintree_environment($host_obj);
			
			try
			{
				Braintree_Customer::delete($customer->id);
			} 
			catch (exception $ex)
			{
				throw new Phpr_ApplicationException($this->get_exception_description($ex));
			}
		}

		/**
		 * Creates a payment transaction from an existing payment profile.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Order $order An order object to pay		
		 * @param $back_end Determines whether the function is called from the administration area
		 * @param boolean $redirect Determines whether the browser should be redirected to the receipt page after successful payment
		 */
		public function pay_from_profile($host_obj, $order, $back_end = false, $redirect = true)
		{
			$this->init_braintree_environment($host_obj);

			$profile = $host_obj->find_customer_profile($order->customer);
			if (!$profile)
				throw new Phpr_ApplicationException('Payment profile not found');

			$transaction_data = array(
				'amount' => $order->total, 
				'type' => 'sale',
				'orderId' => $order->id,
				'customer' => array(
					'firstName' => $order->billing_first_name,
					'lastName' => $order->billing_last_name,
					'email' => $order->billing_email
				)
			);

			if ($host_obj->card_action == 'Sale')
				$transaction_data['options'] = array('submitForSettlement'=>1);

			$tr_data = Braintree_CreditCard::sale($profile->profile_data['token'], $transaction_data);
			
			try
			{
				$this->process_transaction_response($host_obj, $order, $back_end, $tr_data, true, $redirect);
			} catch (Exception $ex)
			{
				$error_message = Phpr::$session->flash['error'] = $this->get_exception_description($ex);
				$this->log_payment_attempt($order, $error_message, 0, array(), $this->prepare_response_log(Braintree_Transaction::$response_array), null);
				Phpr::$session->flash['error'] = $error_message;

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
		
		public function get_payment_profile_form_action($host_obj, $customer)
		{
			$this->init_braintree_environment($host_obj);
			
			if (Phpr::$request->getField('id'))
			{
				$query_string = preg_replace('/^q=[^&]+&/', '', $_SERVER['QUERY_STRING']);
				
				try
				{
					try
					{
						$result = Braintree_TransparentRedirect::confirm($query_string);
					}
					catch (exception $ex)
					{
						throw new Cms_Exception($this->get_exception_description($ex));
					}

					if ($result->success)
					{
						$profile = $host_obj->find_customer_profile($customer);
						if (!$profile)
						{
							$profile = $profile = $host_obj->init_customer_profile($customer);
							$credit_card = $result->customer->creditCards[0];
						} else
							$credit_card = $result->creditCard;
						
						$profile_data = array('token'=>$credit_card->token);
						$profile->set_profile_data($profile_data, $credit_card->last4);
						
						Phpr::$session->flash['success'] = 'The payment profile has been successfully updated.';
						$return_page = Cms_Page::create()->find_by_action_reference('shop:payment_profiles');
						if (!$return_page)
							throw new Cms_Exception('The Payment Profiles page is not found.');

						Phpr::$response->redirect(root_url($return_page->url));
					} else {
						$this->error_result = $result;

						if (isset($result->verification))
						{
							if ($result->verification['status'] == Braintree_Transaction::GATEWAY_REJECTED)
							{
								$message = 'Verification has been rejected by the payment gateway. ';
								if ($result->verification['gatewayRejectionReason'] == 'cvv')
									$message .= 'Reason: invalid card validation number';
								elseif ($result->verification['gatewayRejectionReason'] == 'avs')
									$message .= 'Reason: billing address verification failed.';
								elseif ($result->verification['gatewayRejectionReason'] == 'avs_and_cvv')
									$message .= 'Reason: card validation number and billing address verification failed.';
								elseif ($result->verification['gatewayRejectionReason'] == 'duplicate')
									$message .= 'Reason: duplicate transaction.';
								else
									$message .= 'Error message: '.$result->message;
								
								throw new Phpr_ApplicationException($message);
							}
			
							if ($result->verification['status'] == Braintree_Transaction::FAILED)
								throw new Phpr_ApplicationException('Verification failed.');
			
							if ($result->verification['status'] == Braintree_Transaction::PROCESSOR_DECLINED)
								throw new Phpr_ApplicationException('Verification declined.');
						}

						$errors = $result->errors->deepAll();
						$error_str = array();
						foreach ($errors as $error)
							$error_str[] = $error->message;

						$error_str = implode(' ', $error_str);
						throw new Phpr_ApplicationException($error_str);
					}
				} catch (exception $ex)
				{
					Phpr::$session->flash['error'] = $ex->getMessage();
				}
			}
			
			return Braintree_TransparentRedirect::url();
		}
		
		public function get_payment_profile_form_hidden_fields($host_obj, $customer)
		{
			$this->init_braintree_environment($host_obj);

			$currentUrl = Phpr::$request->getCurrentUrl();
			$currentUrl = preg_replace('/\?.*$/', '', $currentUrl);
			
			$profile = $host_obj->find_customer_profile($customer);
				
			$transaction_fields = array(
			    'redirectUrl' => $currentUrl,
				'creditCard'=>array(
					'billingAddress'=>array(
						'firstName' => $customer->first_name,
						'lastName' => $customer->last_name,
						'streetAddress' => $customer->billing_street_addr,
						'postalCode' => $customer->billing_zip,
						'region'=>($customer->billing_state ? $customer->billing_state->name : null),
						'countryCodeAlpha2'=>$customer->billing_country->code,
						'company'=>$customer->company
					)
				)
			);
			
			$country_name = $this->get_country_name($customer->billing_country->code_3);
			if ($country_name)
				$transaction_fields['creditCard']['billingAddress']['countryName'] = $country_name;

			if ($profile)
			{
				$transaction_fields['paymentMethodToken'] = $profile->profile_data['token'];
				$transaction_fields['creditCard']['billingAddress']['options']['updateExisting'] = 1;
				$tr_data = Braintree_TransparentRedirect::updateCreditCardData($transaction_fields);
			} else
			{
				$transaction_fields['customer'] = array(
					'id' => $customer->id,
					'creditCard' => $transaction_fields['creditCard'],
					'email'=>$customer->email,
					'phone'=>$customer->phone,
					'firstName'=>$customer->first_name,
					'lastName'=>$customer->last_name,
					'company'=>$customer->company
				);

				unset($transaction_fields['creditCard']);
				$tr_data = Braintree_TransparentRedirect::createCustomerData($transaction_fields);
			}

			$fields = array('tr_data'=>$tr_data);

			return $fields;
		}
	}

?>