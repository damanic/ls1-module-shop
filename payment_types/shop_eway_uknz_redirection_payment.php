<?

	class Shop_Eway_UKNZ_Redirection_Payment extends Shop_PaymentType
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
				'name'=>'eWAY Redirection Payment',
				'description'=>'This eWAY solution allows your customers’ to be redirected to a secure eWAY payment page via HTTP FORM POST.',
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
			if ($context !== 'preview')
			{
				$host_obj->add_field('ewayCustomerID', 'Customer Id', 'left')->comment('Your unique eWAY customer ID', 'above')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide Customer Id.');
				$host_obj->add_field('ewayUserName', 'User Name', 'right')->comment('Your unique eWAY customer User Name', 'above')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide User Name.');
			}

			$host_obj->add_field('gateway', 'Gateway')->tab('Configuration')->renderAs(frm_dropdown);

			$host_obj->add_field('language', 'Language')->tab('Customization')->renderAs(frm_dropdown)->comment('Payment page language.', 'above');
			$host_obj->add_field('pageTitle', 'Page Title', 'left')->comment('This value is used to populate the browsers title bar at the top of the screen.', 'above')->tab('Customization')->renderAs(frm_text);
			$host_obj->add_field('pageFooter', 'Page Footer', 'right')->comment('This value will be displayed below the Transaction Details.', 'above')->tab('Customization')->renderAs(frm_text);
			$host_obj->add_field('pageDescription', 'Page Description')->comment('Used as a greeting message to the customer and is displayed above the customers’ order details.', 'above')->tab('Customization')->renderAs(frm_textarea)->size('small');
			$host_obj->add_field('companyName', 'Company Name')->comment('This will be displayed as the company the customer is purchasing from, including this is highly recommended.', 'above')->tab('Customization')->renderAs(frm_text);
			$host_obj->add_field('companyLogo', 'Company Logo')->comment('The url of the image can be hosted on the merchants website and pass the secure https:// path of the image to be displayed at the top of the website.', 'above')->tab('Customization')->renderAs(frm_text);
			$host_obj->add_field('pageBanner', 'Page Banner')->comment('The url of the image can be hosted on the merchants website and pass the secure https:// path of the image to be displayed at the top of the website. This is the second image block on the webpage and is restricted to 960px X 65px. A default secure image is used if none is supplied.', 'above')->tab('Customization')->renderAs(frm_text);
			
			$host_obj->add_field('order_status', 'Order Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}

		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}

		public function get_language_options($current_key_value = -1)
		{
			$values = array(
				'EN'=>'English',
				'ES'=>'Spanish',
				'FR'=>'French',
				'DE'=>'German',
				'NL'=>'Dutch' 
			);
			
			if ($current_key_value == -1)
				return $values;

			return array_key_exists($current_key_value, $values) ? $values[$current_key_value] : 'unknown';
		}

		public function get_gateway_options($current_key_value = -1)
		{
			$values = array(
				'au'=>'Australia',
				'nz'=>'New Zealand',
				'uk'=>'United Kingdom'
			);
			
			if ($current_key_value == -1)
				return $values;

			return array_key_exists($current_key_value, $values) ? $values[$current_key_value] : 'unknown';
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in eWay Redirection Payment method.');
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
			 * We do not need any code here since payments are processed on the eWay server.
			 */
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
		
		public function get_form_action($host_obj, $order, $backend = false)
		{
			$this->process_action($host_obj, $order, $backend);

			return Phpr::$request->getCurrentUrl();
		}

		protected function process_action($host_obj, $order, $backend)
		{
			if (post('action') == 'payment')
				$this->post_request($host_obj, $order, $backend);
			elseif (strlen(post('AccessPaymentCode')))
				$this->handle_response($host_obj, $order, $backend);
		}
		
		protected function get_currency_code($host_obj)
		{
			switch ($host_obj->gateway)
			{
				case 'au' : return 'AUD';
				case 'nz' : return 'NZD';
				case 'uk' : return 'GBP';
			}
			
			throw new Phpr_ApplicationException('Unknown eWay gateway: '.$host_obj->gateway);
		}
		
		protected function get_payment_request_url($host_obj)
		{
			switch ($host_obj->gateway)
			{
				case 'au' : return 'https://au.ewaygateway.com/Request/';
				case 'nz' : return 'https://nz.ewaygateway.com/Request/';
				case 'uk' : return 'https://payment.ewaygateway.com/Request/';
			}
			
			throw new Phpr_ApplicationException('Unknown eWay gateway: '.$host_obj->gateway);
		}

		protected function get_payment_result_url($host_obj)
		{
			switch ($host_obj->gateway)
			{
				case 'au' : return 'https://au.ewaygateway.com/Result/';
				case 'nz' : return 'https://nz.ewaygateway.com/Result/';
				case 'uk' : return 'https://payment.ewaygateway.com/Result/';
			}

			throw new Phpr_ApplicationException('Unknown eWay gateway: '.$host_obj->gateway);
		}

		protected function post_request($host_obj, $order, $backend)
		{
			try
			{
				$currency = Shop_CurrencySettings::get();

				$fields = array();
				$fields['CustomerID'] = $host_obj->ewayCustomerID;
				$fields['UserName'] = $host_obj->ewayUserName;
				$fields['Amount'] = $order->total;

				$fields['Currency'] = $currency->code;
				$fields['ReturnURL'] = Phpr::$request->getCurrentUrl();
				$fields['CancelURL'] = $fields['ReturnURL'];
				$fields['PageTitle'] = $host_obj->pageTitle;
				$fields['PageDescription'] = $host_obj->pageDescription;
				$fields['PageFooter'] = $host_obj->pageFooter;
				$fields['Language'] = $host_obj->language;
				$fields['CompanyName'] = $host_obj->companyName;
				$fields['CustomerFirstName'] = $order->billing_first_name;
				$fields['CustomerLastName'] = $order->billing_last_name;
				$fields['CustomerAddress'] = $order->billing_street_addr;
				$fields['CustomerCity'] = $order->billing_city;
				if ($order->billing_state)
					$fields['CustomerState'] = $order->billing_state->name;
				$fields['CustomerPostCode'] = $order->billing_zip;
				$fields['CustomerCountry'] = $order->billing_country->name;
				$fields['CustomerPhone'] = $order->billing_phone;
				$fields['CustomerEmail'] = $order->billing_email;
				$fields['MerchantReference'] = $order->id;
				$fields['MerchantInvoice'] = $order->id;
				$fields['InvoiceDescription'] = 'Order #'.$order->id;
				$fields['CompanyLogo'] = $host_obj->companyLogo;
				$fields['PageBanner'] = $host_obj->pageBanner;

				$response = $this->parse_response($this->post_data($this->get_payment_request_url($host_obj), $fields));
				$this->process_response_errors($response);

				Phpr::$response->redirect($response['URI']);
			}
			catch (Exception $ex)
			{
				Phpr::$session->flash['error'] = $ex->getMessage();
			}
		}
		
		protected function handle_response($host_obj, $order, $backend)
		{
			$error_message = null;
			$response_fields = array();
			try
			{
				$fields = array();
				$fields['CustomerID'] = $host_obj->ewayCustomerID;
				$fields['UserName'] = $host_obj->ewayUserName;
				$fields['AccessPaymentCode'] = post('AccessPaymentCode');
				
				$response = $this->parse_response($this->post_data($this->get_payment_result_url($host_obj), $fields));
				if (!array_key_exists('ResponseCode', $response))
					throw new Phpr_ApplicationException('Invalid payment gateway response.');

				$response_fields = $response;

				$code = $response['ResponseCode'];
				if ($code == 'CX')
					return;

				if ($response['TrxnStatus'] != 'true' &&  $response['TrxnStatus'] != 'True')
				{
					$error_message = isset($response['TrxnResponseMessage']) ? $response['TrxnResponseMessage'] : 'The credit card has been declined by the gateway.';
					throw new Phpr_ApplicationException('The credit card has been declined by the gateway.');
				} else
				{
					/*
					 * Log payment attempt
					 */
					$this->log_payment_attempt($order, 'Successful payment', 1, array(), $response_fields, null);

					/*
					 * Change order status
					 */
					Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
					
					/*
					 * Log transaction create/change
					 */
					$this->update_transaction_status($host_obj, $order, $response_fields['TrxnNumber'], 'Approved', 'True');
					
					/*
					 * Mark order as paid
					 */
					$order->set_payment_processed();
					
					if (!$backend)
					{
						$return_page = $order->payment_method->receipt_page;
						if ($return_page)
							Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
						else 
							throw new Phpr_SystemException('eWay Redirection receipt page is not found.');
					} else
						Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
				}
			}
			catch (Exception $ex)
			{
				$error_message = !strlen($error_message) ? $ex->getMessage() : $error_message;
				$this->log_payment_attempt($order, $error_message, 0, array(), $response_fields, null);

				if (!$backend)
					Phpr::$session->flash['error'] = $ex->getMessage();
				else
					Phpr::$session->flash['error'] = $error_message;
			}
		}
		
		private function process_response_errors($response)
		{
			if (!array_key_exists('Result', $response))
				throw new Phpr_ApplicationException('Invalid payment gateway response.');

			if ($response['Result'] != 'True')
			{
				if (!array_key_exists('Error', $response))
					throw new Phpr_ApplicationException('Unknown payment gateway error.');

				throw new Phpr_ApplicationException($response['Error']);
			}
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
	
		private function post_data($endpoint, $fields)
		{
			$poststring = array();

			foreach($fields as $key=>$val)
				$poststring[] = urlencode($key)."=".urlencode($val); 

			$poststring = implode('&', $poststring);

			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, $endpoint.'?'.$poststring);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 40);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

			$response = curl_exec($ch);

			if (curl_errno($ch))
				throw new Phpr_ApplicationException( "Error connecting the payment gateway: ".curl_error($ch) );
			else
				curl_close($ch);
			
			return $response;
		}
	
		private function format_form_fields(&$fields)
		{
			$result = array();
			foreach($fields as $key=>$val)
			    $result[] = urlencode($key)."=".urlencode($val); 
		
			return implode('&', $result);
		}
	}

?>