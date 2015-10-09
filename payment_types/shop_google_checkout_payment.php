<?

	class Shop_Google_Checkout_Payment extends Shop_PaymentType {
		public $schema_url = 'http://checkout.google.com/schema/2';
		private $test_url = 'https://sandbox.google.com/checkout/api/checkout/v2/merchantCheckout/Merchant/';
		private $live_url = 'https://checkout.google.com/api/checkout/v2/merchantCheckout/Merchant/';
		private $server_url;
		private $base_url;
		private $request_url;
		
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
				'name' => 'Google Checkout',
				'description' => 'Google Checkout payment method.',
				'custom_payment_form' => 'backend-form.php'
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
			$host->add_field('test_mode', 'Sandbox Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Use the Google Checkout sandbox server (sandbox.google.com). This option is for testing orders with a Google Checkout buyer sandbox account.', 'above', true);

			if($context !== 'preview') {
				$host->add_form_partial($host->get_partial_path('api_callback_hint.htm'))->tab('Configuration');
				
				$host->add_field('merchant_id', 'Merchant ID', 'left')->tab('Configuration')->renderAs(frm_text)->comment('Google Checkout provides the Merchant ID on the Integration page of their backend.', 'above')->validation()->fn('trim')->required('Please provide Merchant ID.');
				$host->add_field('merchant_key', 'Merchant Key', 'right')->tab('Configuration')->renderAs(frm_text)->comment('Google Checkout provides the Merchant Key on the Integration page of their backend.', 'above')->validation()->fn('trim')->required('Please provide Merchant Key.');
			}

			$host->add_field('order_status', 'Order Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment. Please note that payments may take a few minutes to be processed, and marked successful.', 'above');
		}
		
		public function get_order_status_options($current_key_value = -1) {
			if($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}

		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host) {
		}
		
		/**
		 * Validates configuration data after it is loaded from database
		 * Use host object to access fields previously added with build_config_ui method.
		 * You can alter field values if you need
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_load($host) {
			if($host->test_mode)
				$this->server_url = 'https://sandbox.google.com/checkout/';
			else
				$this->server_url = 'https://checkout.google.com/';
				
			$this->base_url = $this->server_url . 'api/checkout/v2/'; 
			$this->request_url = $this->base_url . 'request/Merchant/' . $host->merchant_id;
		}

		/**
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host) {
			$host->test_mode = 1;
			$host->order_status = Shop_OrderStatus::get_status_paid()->id;
		}

		/**
		 * Builds the back-end payment form 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host ActiveRecord object to add fields to
		 */
		public function build_payment_form($host) {
		}

		/**
		 * Payment processing
		 */
		
		private function hmac_sha1($host, $data) {
			$key = $host->merchant_key;
	
			$block_size = 64;

			if(strlen($key) > $blocksize) 
				$key = pack('H*', sha1($key));
	
			$key = str_pad($key, $block_size, chr(0x00));
			$ipad = str_repeat(chr(0x36), $block_size);
			$opad = str_repeat(chr(0x5c), $block_size);
			$hmac = pack('H*', sha1(($key^$opad).pack('H*', sha1(($key^$ipad).$data))));
			
			return $hmac;
		}
			
		private function post_data($url, $data, $host = null) {
			set_time_limit(0);
			
			$headers = array();
			$headers[] = "Content-Type: application/xml; charset=UTF-8";
			$headers[] = "Accept: application/xml; charset=UTF-8";
			$headers[] = "User-Agent: GC-PHP-Sample_code (v1.3.0/ropu)";
			
			if($host)
				$headers[] = "Authorization: Basic " . base64_encode($host->merchant_id . ':' . $host->merchant_key);
			
 			$c1 = curl_init(); 
			curl_setopt($c1, CURLOPT_URL, $url);
			curl_setopt($c1, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($c1, CURLOPT_POST, true);
			curl_setopt($c1, CURLOPT_HEADER, false);
			curl_setopt($c1, CURLOPT_RETURNTRANSFER, false);
			curl_setopt($c1, CURLOPT_TIMEOUT, 40);
			curl_setopt($c1, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($c1, CURLOPT_POSTFIELDS, $data);

			ob_start();
			curl_exec($c1);
			$response = ob_get_clean();

			if(curl_errno($c1))
				throw new Phpr_ApplicationException("Error connecting the payment gateway: " . curl_error($c1));
			
			curl_close($c1);
				
			return $response;
		}
		
		/**
		 * Processes payment using passed data
		 * @param array $data Posted payment form data
		 * @param $host ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function process_payment_form($data, $host, $order, $back_end = false) {
			/*
			 * We do not need any code here since payments are processed on the eWay server.
			 */
		}
		
		public function get_form_action($host, $order, $backend = false) {
			return root_url('ls_google_checkout_start');
		}
		
		public function get_hidden_fields($host, $order, $backend = false) {
			$result = array();
			$result['order_id'] = $order->id;
			$result['backend'] = $backend ? 1 : 0;
			
			return $result;
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
				'ls_google_checkout_start' => 'on_payment_start',
				'ls_google_checkout_complete' => 'on_payment_complete'
			);
		}
		
		public function on_payment_start($params) {
			$request_fields = array();
			$response_fields = array();
			$response_text = '';
		
			// find order
			$order = Shop_Order::create()->find(post('order_id'));
			
			if(!$order)
				throw new Phpr_ApplicationException('Order not found.');

			if(!$order->payment_method)
				throw new Phpr_ApplicationException('Payment method not found.');

			$order->payment_method->define_form_fields(); // load payment method settings

			$host = $order->payment_method;
			$backend = post('backend');
			
			$this->validate_config_on_load($host); // need to reload settings
		
			if($backend) {
				$return_url = site_url(url('/shop/orders/payment_accepted/' . $order->id));
			}
			else {
				$return_page = $order->payment_method->receipt_page;
				
				if($return_page)
					$return_url = site_url($return_page->url . '/' . $order->order_hash) . '?utm_nooverride=1';
				else 
					throw new Phpr_SystemException('Google Checkout receipt page is not found.');
			}
			
			$request_text = $this->format_xml_template('checkout-request.xml', array(
				'self' => $this,
				'host' => $host,
				'order' => $order,
				'return_url' => $return_url
			));
			
			$response_text = $this->post_data($this->request_url, $request_text, $host);

			try {
				$response_doc = new DOMDocument('1.0');
				$response_doc->loadXML($response_text);
				$path = new DOMXPath($response_doc);
				$path->registerNameSpace('g', $this->schema_url);
				
				$eror_message = $path->query('//g:error-message');
				if ($eror_message->length)
					throw new Phpr_ApplicationException($eror_message->item(0)->nodeValue);
				
				$redirect_url = $path->query('//g:redirect-url')->item(0)->nodeValue;
				
				die(include(PATH_APP . '/modules/shop/payment_types/' . strtolower(get_class($this)) . '/redirect.php'));
			}
			catch(Phpr_Exception $e) {
				$this->log_payment_attempt($order, $e->getMessage(), 0, $request_fields, $response_fields, $response_text);
			}
			catch(Exception $e) {
				$this->log_payment_attempt($order, 'Invalid gateway response.', 0, $request_fields, $response_fields, $response_text);
			}
		}

		public function on_payment_complete($params) {
			$request_fields = array();
			$response_fields = array();
			$response_text = '';
			
			try {
				// retrieve the XML sent in the HTTP POST request
				$response_text = file_get_contents("php://input");
				
				if(get_magic_quotes_gpc())
					$response_text = stripslashes($response_text);
		
				$doc = new DOMDocument('1.0');
				$doc->loadXML($response_text);
				$path = new DOMXPath($doc);
				$path->registerNameSpace('g', $this->schema_url);
				
				$order_hash = $path->query('//g:shopping-cart/g:merchant-private-data/g:order-hash');
				
				if(!$order_hash->length)
					return;
				
				$order_hash = $order_hash->item(0)->nodeValue;
	
				$order = Shop_Order::create()->find_by_order_hash($order_hash);
				
				if(!$order)
					throw new Phpr_ApplicationException('Order not found.');
	
				if(!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');
	
				$order->payment_method->define_form_fields(); // load payment method settings
				
				// set the order as processing
				$order->set_payment_processed();
	
				$host = $order->payment_method;
				
				$this->validate_config_on_load($host); // need to reload settings
				
				if($path->query('/g:new-order-notification')->length) {
					$serial = $path->query('/g:new-order-notification')->item(0)->getAttribute('serial-number');
					
					$request_text = $this->format_xml_template('acknowledgement-request.xml', array(
						'self' => $this,
						'serial' => $serial
					), false);
					
					die($request_text);
				}
				else if($path->query('/g:authorization-amount-notification')->length) {
					$order_number = $path->query('//g:google-order-number')->item(0)->nodeValue;
					$amount = $path->query('//g:authorization-amount')->item(0)->nodeValue;
					$currency = $path->query('//g:authorization-amount')->item(0)->getAttribute('currency');
					
					$request_text = $this->format_xml_template('charge-order-request.xml', array(
						'self' => $this,
						'order_number' => $order_number,
						'currency' => $currency,
						'amount' => $amount,
						'order' => $order
					), false);
	
					$response_text = $this->post_data($this->request_url, $request_text, $host);
					
					$response_doc = new DOMDocument('1.0');
					$response_doc->loadXML($response_text);
					
					$request_doc = new DOMDocument('1.0');
					$request_doc->loadXML($request_text);
					
					$request_fields = Core_Xml::to_plain_array($request_doc, true);
					$response_fields = Core_Xml::to_plain_array($response_doc, true);
					
					$path = new DOMXPath($response_doc);
					$path->registerNameSpace('g', $this->schema_url);
					
					if($path->query('/g:request-received')->length) {
						$financial_state = $path->query('//g:fulfillment-order-state')->item(0)->nodeValue;
						$fulfillment_state = $path->query('//g:financial-order-state')->item(0)->nodeValue;
						
						if($financial_state !== 'DELIVERED' || $fulfillment_state !== 'CHARGED') {
							$message = 'Error fulfilling payment.';
							
							switch($financial_state) {
								case 'PAYMENT_DECLINED':
									$message = 'The payment has been declined.';
								break;
								case 'CANCELLED':
									$message = 'The order has been canceled.';
								break;
								case 'CANCELLED_BY_GOOGLE':
									$message = 'Google has canceled the order.';
								break;
							}
						
							$this->log_payment_attempt($order, $message, 0, $request_fields, $response_fields, $response_text);
							
							return;
						}
					
						Shop_OrderStatusLog::create_record($host->order_status, $order);
						
						$this->log_payment_attempt($order, 'Successful payment', 1, $request_fields, $response_fields, $response_text);
						
						$this->update_transaction_status($host, $order, $order_number, 'Approved', 'charge');
					}
				}
			}
			catch(Phpr_Exception $e) {
				$this->log_payment_attempt($order, $e->getMessage(), 0, $request_fields, $response_fields, $response_text);
			}
			catch(Exception $e) {
				$this->log_payment_attempt($order, 'Invalid gateway response.', 0, $request_fields, $response_fields, $response_text);
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