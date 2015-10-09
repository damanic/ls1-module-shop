<?

	class Shop_WorldPay_Html_Redirect_Payment extends Shop_PaymentType {
		public $test_url = 'https://secure-test.worldpay.com/wcc/purchase';
		public $live_url = 'https://secure.worldpay.com/wcc/purchase';
	
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
		public function get_info() {
			return array(
				'name' => 'WorldPay Redirect',
				'custom_payment_form' => 'backend_payment_form.htm',
				'description' => 'A basic form redirect integration. The customer\'s browser will be sent to the WorldPay server at the time of processing.'
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
		 * @param $host ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host, $context = null) {
			if($context !== 'preview') {
				$host->add_field('installation_id', 'Installation ID')->tab('Configuration')->renderAs(frm_text)->comment('The installation id provided to you in your WorldPay account area of type Select.', 'above')->validation()->fn('trim')->required('Please provide Installation ID.');
				
				$host->add_field('security_key', 'Security Key')->tab('Configuration')->renderAs(frm_text)->comment('If you have specified an MD5 security value in the WorldPay backend area, enter it here (Optional). For the field SignatureFields, use the value: amount:currency:cartId', 'above')->validation()->fn('trim');
			}
			
			$host->add_field('test_mode', 'Test Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('The test environment is used for testing integration and feature configuration.', 'above');

			$host->add_field('transaction_type', 'Transaction Type')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');
			
			$notification_url = root_url('/ls_worldpay_html_notification', true);
			
			$host->add_field('order_status', 'Order Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.<br/><strong>Important!</strong> To enable the automatic order status update feature, please enable Payment Response in the Installations/Administration form of your WorldPay account area, and specify the following URL for the Payment Response URL: &lt;wpdisplay item=MC_callback&gt;', 'above', true);
		}
		
		public function get_transaction_type_options($current_key_value = -1) {
			$options = array(
				'AC' => 'Purchase',
				'A' => 'Pre-Authorization'
			);
			
			if($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		/**
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host) {
			$host->order_status = Shop_OrderStatus::get_status_paid()->id;
			$host->receipt_link_text = 'Return to merchant';
		}

		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host) {
		}
		
		public function get_order_status_options($current_key_value = -1) {
			if($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}
		
		public function get_form_action($host) {
			return $host->test_mode ? $this->test_url : $this->live_url;
		}
		
		/**
		 * Processes payment using passed data
		 * @param array $data Posted payment form data
		 * @param $host ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function process_payment_form($data, $host, $order, $back_end = false) {
			/*
			 * We do not need any code here since payments are processed on the payment gateway server.
			 */
		}
		
		public function get_hidden_fields($host, $order, $backend = false) {
			$result = array();
			$currency = Shop_CurrencySettings::get();
			
			$fields = array();
			$fields['instId'] = $host->installation_id;
			$fields['cartId'] = $order->id;
			$fields['authMode'] = $host->transaction_type;
			$fields['amount'] = $order->total;
			$fields['currency'] = $currency->code;
			$fields['desc'] = ($backend ? 'Backend ' : '') . 'Order #' . $order->id;
			$fields['tel'] = $order->billing_phone;
			$fields['fax'] = '';
			$fields['email'] = $order->billing_email;
			if($order->billing_state)
				$fields['address'] = $order->billing_street_addr . ', ' . $order->billing_city . ', ' . $order->billing_state->name . ', ' . $order->billing_country->name;
			else
				$fields['address'] = $order->billing_street_addr . ', ' . $order->billing_city . ', ' . $order->billing_country->name;
			$fields['postcode'] = $order->billing_zip;
			$fields['country'] = $order->billing_country->code;
			$fields['testMode'] = $host->test_mode ? 100 : 0;
			$fields['fixContact'] = '';
			$fields['MC_callback'] = root_url('/ls_worldpay_html_notification', true);
			$fields['hideContact'] = '';
			
			if($host->test_mode) // we can force the result in test mode
				$fields['name'] = $host->transaction_type == 'A' ? 'AUTHORISED' : 'CAPTURED';
			else
				$fields['name'] = $order->billing_first_name + ' ' + $order->billing_last_name;
			
			if($host->security_key) {
				$fields['signatureFields'] = 'amount:currency:cartId';
				$fields['signature'] = md5($host->security_key . ':' . $fields['amount'] . ':' . $fields['currency'] . ':' . $fields['cartId']);
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
		public function register_access_points() {
			return array(
				'ls_worldpay_html_notification' => 'process_payment_notification'
			);
		}
		
		public function process_payment_notification($params) {
			$fields = $_POST;
			$order = null;
			
			try {
				/* Remove the postback security check because Worldpay have changed IP numbers
				 * if(strpos($_SERVER['REMOTE_ADDR'], '155.136.16.') !== 0)
				 *	throw new Phpr_ApplicationException('Notification is not secure.');
				 */

				$order_id = $fields['cartId'];
				if(!$order_id)
					throw new Phpr_ApplicationException('Order not found');

				$order = Shop_Order::create()->find($order_id);
				if(!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if(!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();
			
				if(!($payment_method_obj instanceof Shop_WorldPay_Html_Redirect_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				if($fields['authCost'] != $order->total)
					throw new Phpr_ApplicationException('Invalid transaction data.');
			
				if($fields['transStatus'] != 'Y')
					throw new Phpr_ApplicationException('Transaction not approved: ' . $fields['rawAuthMessage']);
				
				if($order->set_payment_processed())
				{
					Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
					$this->log_payment_attempt($order, 'Successful payment', 1, array(), $fields, null);
				}
				
				$backend = stristr($fields['desc'], 'backend');

				if(!$backend) {
					$return_page = $order->payment_method->receipt_page;
					
					if($return_page)
						$approved_page = $return_page->url . '/' . $order->order_hash . '?utm_nooverride=1';
				}
				else {
					$approved_page = Core_String::normalizeUri(Phpr::$config->get('BACKEND_URL', 'backend')) . 'shop/orders/payment_accepted/' . $order->id . '?utm_nooverride=1&nocache' . uniqid();
				}
				
				$url = root_url($approved_page, true);
					
				echo "<meta http-equiv='refresh' content='0;url=" . $url . "'>";
			}
			catch(Exception $ex) {
				if($order)
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), $fields, null);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
		
		/**
		 * This function is called before an order status deletion.
		 * Use this method to check whether the payment method
		 * references an order status. If so, throw Phpr_ApplicationException 
		 * with explanation why the status cannot be deleted.
		 * @param $host ActiveRecord object containing configuration fields values
		 * @param Shop_OrderStatus $status Specifies a status to be deleted
		 */
		public function status_deletion_check($host, $status) {
			$info = $this->get_info();
		
			if($host->order_status == $status->id)
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in ' . $info->name . ' payment method.');
		}
	}