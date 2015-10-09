<?

	class Shop_SecureTrading_PaymentPages_Payment extends Shop_PaymentType
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
				'name'=>'SecureTrading Payment Pages',
				'custom_payment_form'=>'backend_payment_form.htm',
				'description'=>'SecureTrading payment method with payment page hosted on the SecureTrading server.'
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
				$callback_url = root_url('/ls_securetrading_callback', true);
				
				$host_obj->add_field('site_reference', 'Site Reference')->comment('Please, specify the following URL in the url1 parameter of the callback.txt file: <br/>'.$callback_url, 'above', true)->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please enter Site Reference.');
				$host_obj->add_field('email', 'Merchant Email')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please enter merchant email address.')->email();
			}
			
			$host_obj->add_field('card_action', 'Transaction Type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request you wish to perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
			$host_obj->add_field('cancel_page', 'Cancel Page')->comment('Page to which the customer’s browser is redirected if payment is cancelled.<br/>You can use <strong>$lemonstand_return_url</strong> and <strong>$lemonstand_cancel_url</strong> variables in SecureTrading page templates to refer the Receipt and Cancel pages.', 'above', true)->tab('Configuration')->renderAs(frm_dropdown);
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
		
		public function get_cancel_page_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Cms_Page::create()->order('title')->find_all()->as_array('title', 'id');

			return Cms_Page::create()->find($current_key_value)->title;
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
			return "https://securetrading.net/authorize/form.cgi";
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
			 * We do not need any code here since payments are processed on Authorize.Net server.
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in SecureTrading Payment Pages payment method.');
		}
		
		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$result = array();
			$currency = Shop_CurrencySettings::get();
			$currency_converter = Shop_CurrencyConverter::create();

//			$amount = $currency_converter->convert($order->total, $currency->code, 'USD');
			$amount = $order->total;
			
			$userIp = Phpr::$request->getUserIp();
			if ($userIp == '::1')
				$userIp = '192.168.0.1';

			$timeStamp = time();
			
			$fields['email'] = $order->billing_email;
			$fields['name'] = $order->billing_first_name.' '.$order->billing_last_name;
			$fields['address'] = $order->billing_street_addr;

			$fields['merchant'] = $host_obj->site_reference;
			$fields['merchantemail'] = $host_obj->email;
			$fields['amount'] = round($order->total*100);
			// $fields['currency'] = 'USD';
			$fields['currency'] = $currency->code;
			
			$fields['customeremail'] = $order->billing_email;
			$fields['settlementday'] = $order->card_action;
			
			$fields['orderref'] = $order->id;
			$fields['orderinfo'] = 'Order #'.$order->id;
			
			$fields['town'] = $order->billing_city;
			$fields['country'] = $order->billing_country->name;
			
			if ($order->billing_state)
				$fields['county'] = $order->billing_state->name;

			$fields['postcode'] = $order->billing_zip;
			$fields['telephone'] = $order->billing_phone;

			$fields['callbackurl'] = 1;

			if (!$backend)
			{
				$fields['lemonstand_return_url'] = root_url('/ls_securetrading_return/'.$order->order_hash, true);

				$cancel_page = Cms_Page::create()->find($host_obj->cancel_page);
				if ($cancel_page)
				{
					$fields['lemonstand_cancel_url'] = root_url($cancel_page->url, true);
					if ($cancel_page->action_reference == 'shop:pay')
						$fields['lemonstand_cancel_url'] .= '/'.$order->order_hash;
					elseif ($cancel_page->action_reference == 'shop:order')
						$fields['lemonstand_cancel_url'] .= '/'.$order->id;
				}
			} else 
			{
				$fields['lemonstand_return_url'] = root_url('/ls_securetrading_return/'.$order->order_hash.'/backend', true);
				$fields['lemonstand_cancel_url'] = Phpr::$request->getRootUrl().url('shop/orders/pay/'.$order->id.'?'.uniqid());
			}

			return $fields;
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
				'ls_securetrading_callback'=>'process_callback',
				'ls_securetrading_return'=>'process_return'
			);
		}
		
		public function process_callback($params)
		{
			$fields = $_POST;
			$order = null;

			try
			{
				if (!isset($_SERVER['HTTP_USER_AGENT']) || (strpos($_SERVER['HTTP_USER_AGENT'], 'SecureTrading') === false))
					return;

				if (!post('orderref'))
					return;

				$order = Shop_Order::create()->find(post('orderref'));
				if (!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if (!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();
		
				if (!($payment_method_obj instanceof Shop_SecureTrading_PaymentPages_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				if ($order->set_payment_processed())
				{
					Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
					$this->log_payment_attempt($order, 'Successful payment', 1, array(), $fields, null);
				}
			} catch (Exception $ex)
			{
				if ($order)
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), $fields, null);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
		
		public function process_return($params)
		{
			try
			{
				$order = null;
				
				$response = null;
				
				/*
				 * Find order and load paypal settings
				 */
			
				$order_hash = array_key_exists(0, $params) ? $params[0] : null;
				if (!$order_hash)
					throw new Phpr_ApplicationException('Order not found');

				$order = Shop_Order::create()->find_by_order_hash($order_hash);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if (!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();
			
				if (!($payment_method_obj instanceof Shop_SecureTrading_PaymentPages_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = array_key_exists(1, $params) ? $params[1] == 'backend' : false;

				if (!$is_backend)
				{
					$return_page = $order->payment_method->receipt_page;
					if ($return_page)
						Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
					else 
						throw new Phpr_ApplicationException('SecureTrading Payment Pages Receipt page is not found.');
				} else 
				{
					Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
				}
			}
			catch (Exception $ex)
			{
				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
	}
	
?>