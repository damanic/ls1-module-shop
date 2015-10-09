<?

	class Shop_Beanstream_Basic_Payment extends Shop_PaymentType
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
				'name'=>'Beanstream Basic Integration',
				'custom_payment_form'=>'backend_payment_form.htm',
				'description'=>'A basic HTTP POST integration. The customer’s browser will be pointed to the Beanstream server at the time of processing.'
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

			if ($context !== 'preview')
				$host_obj->add_form_partial($host_obj->get_partial_path('hint.htm'))->tab('Configuration');
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
		
		/**
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host_obj)
		{
			$host_obj->order_status = Shop_OrderStatus::get_status_paid()->id;
			$host_obj->receipt_link_text = 'Return to merchant';
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
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}
		
		public function get_form_action($host_obj)
		{
			return "https://www.beanstream.com/scripts/payment/payment.asp";
//			https://www.beanstream.com/scripts/process_transaction.asp
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
			 * We do not need any code here since payments are processed on the payment gateway server.
			 */
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in Beanstream Basic POST payment method.');
		}
		
		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$result = array();
			$amount = $order->total;
			
			$userIp = Phpr::$request->getUserIp();

			$fields = array();
//			$fields['requestType'] = 'BACKEND';
			$fields['merchant_id'] = $host_obj->merchant_id;
			$fields['trnOrderNumber'] = $order->id;
			$fields['trnAmount'] = $amount;
			
			$fields['ordName'] = $order->billing_first_name.' '.$order->billing_last_name;
			$fields['ordEmailAddress'] = $order->billing_email;
			$fields['ordPhoneNumber'] = $order->billing_phone;
			$fields = $this->split_address_lines($order->billing_street_addr, $fields, 'ordAddress1', 'ordAddress2');
			
			$fields['ordCity'] = $order->billing_city;
			
			if ($order->billing_state)
				$fields['ordProvince'] = $order->billing_state->code;

			$fields['ordPostalCode'] = $order->billing_zip;
			$fields['ordCountry'] = $order->billing_country->code;
			
			$fields['shipName'] = $order->shipping_first_name.' '.$order->shipping_last_name;
			$fields['shipPhoneNumber'] = $order->shipping_phone;
			$fields = $this->split_address_lines($order->shipping_street_addr, $fields, 'shipAddress1', 'shipAddress2');

			$fields['shipCity'] = $order->shipping_city;
			
			if ($order->shipping_state)
				$fields['shipProvince'] = $order->shipping_state->code;
			
			$fields['shipPostalCode'] = $order->shipping_zip;
			$fields['shipCountry'] = $order->shipping_country->code;
			
//			$fields['customerIP'] = $userIp;

			$approved_page = null;
			if (!$backend)
			{
//				$error_page = Phpr::$request->getCurrentUrl();
				$return_page = $order->payment_method->receipt_page;
				if ($return_page)
					$approved_page = root_url($return_page->url.'/'.$order->order_hash.'?utm_nooverride=1', true);
			}
			else
			{
//				$error_page = root_url(url('shop/orders/pay/'.$order->id.'?'.uniqid()), true);
				$approved_page = root_url(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()), true);
			}

//			$fields['errorPage'] = urlencode($error_page);

			if ($approved_page)
				$fields['approvedPage'] = $approved_page;

			return $fields;
		}
		
		private function split_address_lines($address, $data, $line_1_key, $line_2_key)
		{
			$address = str_replace("\r\n", "\n", $address);
			$address_parts = explode("\n", $address, 2);

			$cnt = count($address_parts);
			if ($cnt > 1)
			{
				$data[$line_1_key] = $address_parts[0];
				$data[$line_2_key] = str_replace("\n", " ", $address_parts[1]);
				
				return $data;
			}
			
			if ($cnt > 0)
				$data[$line_1_key] = $address_parts[0];
				
			return $data;
		}
		
		/**
		 * Registers a hidden page with specific URL. Use this method for cases when you 
		 * need to have a hidden landing page for a specific payment gateway. For example, 
		 * PayPal needs a landing page for the auto-return feature.
		 * Important! Payment module access point names should have the ls_ prefix.
		 * @return array Returns an array containing page URLs and methods to call for each URL:
		 * return array('ls_paypal_autoreturn'=>'process_paypal_autoreturn'). The processing methods must be declared 
		 * in the payment type class. Processing methods must accept one parameter - an array of URL segments 
		 * following the access point. For example, if URL is /ls_paypal_autoreturn/1234 an array with single
		 * value '1234' will be passed to process_paypal_autoreturn method 
		 */
		public function register_access_points()
		{
			return array(
				'ls_beanstream_notification'=>'process_payment_notification'
			);
		}
		
		public function process_payment_notification($params)
		{
			$fields = $_POST;
			$order = null;

			try
			{
				/*
				 * Find order and load payment method settings
				 */

				$order_id = post('trnOrderNumber');
				if (!$order_id)
					throw new Phpr_ApplicationException('Order not found');

				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if (!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();
			
				if (!($payment_method_obj instanceof Shop_Beanstream_Basic_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				/*
				 * Validate the transaction
				 */
				
				if (post('trnAmount') != $order->total)
					throw new Phpr_ApplicationException('Invalid transaction data.');
			
				if (post('trnApproved') != 1)
					throw new Phpr_ApplicationException('Transaction not approved: '.post('messageText'));
				
				if ($order->set_payment_processed())
				{
					Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
					$this->log_payment_attempt($order, 'Successful payment', 1, array(), $fields, null);
				}
			}
			catch (Exception $ex)
			{
				if ($order)
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), $fields, null);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
	}
	
?>