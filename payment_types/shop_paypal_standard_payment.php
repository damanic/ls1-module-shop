<?

	class Shop_PayPal_Standard_Payment extends Shop_PaymentType
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
				'name'=>'PayPal Standard',
				'custom_payment_form'=>'backend_payment_form.htm',
				'description'=>'PayPal Pro payment method, with payment form hosted on PayPal server.'
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
			$host_obj->add_field('test_mode', 'Sandbox Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Use the PayPal Sandbox Test Environment to try out Website Payments. You should be logged into the PayPal Sandbox to work in test mode.', 'above');
			$host_obj->add_field('business_email', 'Business Email')->tab('Configuration')->renderAs(frm_text)->comment('PayPal business account email address.', 'above')->validation()->fn('trim')->required('Please provide PayPal business account email address.')->email('Please provide valid email address in Business Email field.');

			$host_obj->add_field('shipping_address', 'Shipping Address')->tab('Configuration')->renderAs(frm_dropdown)->comment('Please specify whether you want PayPal to allow customers entering their shipping address in the payment form.', 'above')->validation()->fn('trim');
			$host_obj->add_field('address_override', 'Override Address')->tab('Configuration')->renderAs(frm_checkbox)->comment('The address specified on the Checkout page overrides the PayPal member\'s stored address.', 'above')->validation()->fn('trim');
			$host_obj->add_field('use_shipping_address', 'Use Shipping Address')->tab('Configuration')->renderAs(frm_checkbox)->comment('Use shipping address instead of billing address in PayPal transactions.', 'above')->validation()->fn('trim');
			
			$host_obj->add_field('skip_itemized_data', 'Do not submit itemized order information')->tab('Configuration')->renderAs(frm_checkbox)->comment('Enable this option if you don\'t want to submit itemized order information with a transaction. When the option is enabled only the order total amount is submitted to the payment gateway. The total amount includes the tax and shipping amounts.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_form_partial($host_obj->get_partial_path('hint.htm'))->tab('Configuration');
				$host_obj->add_field('pdt_token', 'PDT Token')->tab('Configuration')->renderAs(frm_text)->comment('PayPal Payment Data Transfer token.', 'above')->validation()->fn('trim')->required('Please provide PayPal Payment Data Transfer token.');
			}
			
			$host_obj->add_field('cancel_page', 'Cancel Page', 'left')->tab('Configuration')->renderAs(frm_dropdown)->formElementPartial(PATH_APP.'/modules/shop/controllers/partials/_page_selector.htm')->comment('Page which the customer’s browser is redirected to if payment is cancelled.', 'above')->emptyOption('<please select a page>');
			
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}
		
		public function get_cancel_page_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Cms_Page::create()->order('title')->find_all()->as_array('title', 'id');

			return Cms_Page::create()->find($current_key_value)->title;
		}
		
		public function get_shipping_address_options($current_key_value = -1)
		{
			$options = array(
				1 => 'Do not prompt for an address',
				0 => 'Prompt for an address, but do not require one',
				2 => 'Prompt for an address, and require one'
			);
			
			if ($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
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
			$host_obj->shipping_address = 1;
		}
		
		public function get_return_page_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Cms_Page::create()->order('title')->find_all()->as_array('title', 'id');

			return Cms_Page::create()->find($current_key_value)->title;
		}
		
		public function get_form_action($host_obj)
		{
			if ($host_obj->test_mode)
				return "https://www.sandbox.paypal.com/cgi-bin/webscr";
			else
				return "https://www.paypal.com/cgi-bin/webscr";
		}
		
		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$result = array();

			/*
			 * Billing information
			 */

			$result['first_name'] = $order->billing_first_name;
			$result['last_name'] = $order->billing_last_name;
			
			if (!$host_obj->use_shipping_address)
			{
				$result['address1'] = $order->billing_street_addr;
				$result['city'] = $order->billing_city;
				$result['country'] = $order->billing_country->code;

				if ($order->billing_state)
					$result['state'] = $order->billing_state->code;

				$result['zip'] = $order->billing_zip;
				$result['night_phone_a'] = $order->billing_phone;
			} else {
				$result['address1'] = $order->shipping_street_addr;
				$result['city'] = $order->shipping_city;
				$result['country'] = $order->shipping_country->code;

				if ($order->shipping_state)
					$result['state'] = $order->shipping_state->code;

				$result['zip'] = $order->shipping_zip;
				$result['night_phone_a'] = $order->shipping_phone;
			}

			if ($host_obj->address_override)
				$result['address_override'] = 1;

			/*
			 * Order items
			 */
			
			if ($host_obj->skip_itemized_data)
			{
				$result['item_name_1'] = 'Order #'.$order->id;
				$result['amount_1'] = round($order->total, 2);
				$result['quantity_1'] = 1;
			} else {
				$item_index = 1;
				foreach ($order->items as $item)
				{
					$result['item_name_'.$item_index] = $item->output_product_name(true, true);
					$result['amount_'.$item_index] = round($item->unit_total_price, 2);
					$result['quantity_'.$item_index] = $item->quantity;
					$item_index++;
				}
			}

			//$result['discount_amount_cart'] = $order->discount;
			
			/*
			 * Shipping
			 */

			if (!$host_obj->skip_itemized_data)
			{
				$result['item_name_'.$item_index] = 'Shipping Cost';
				$result['amount_'.$item_index] = $order->shipping_quote;
				$result['quantity_'.$item_index] = 1;

				$item_index++;
				if ($order->shipping_tax > 0)
				{
					$result['item_name_'.$item_index] = 'Shipping Tax';
					$result['amount_'.$item_index] = $order->shipping_tax;
					$result['quantity_'.$item_index] = 1;
				}
			}

			/*
			 * Payment setup
			 */
			
			$result['no_shipping'] = strlen($host_obj->shipping_address) ? $host_obj->shipping_address : 1;
			$result['cmd'] = '_cart';
			$result['upload'] = 1;
			if (!$host_obj->skip_itemized_data)
				$result['tax_cart'] = number_format($order->goods_tax, 2, '.', '');
			$result['invoice'] = $order->id;
			$result['business'] = $host_obj->business_email;
			$result['currency_code'] = Shop_CurrencySettings::get()->code;
			if (!$host_obj->skip_itemized_data)
				$result['tax'] = number_format($order->goods_tax, 2, '.', '');

			$result['notify_url'] = Phpr::$request->getRootUrl().root_url('/ls_paypal_ipn/'.$order->order_hash);

			if (!$backend)
			{
				$result['return'] = Phpr::$request->getRootUrl().root_url('/ls_paypal_autoreturn/'.$order->order_hash);

				$cancel_page = $this->get_cancel_page($host_obj);
				if ($cancel_page)
				{
					$result['cancel_return'] = Phpr::$request->getRootUrl().root_url($cancel_page->url);
					if ($cancel_page->action_reference == 'shop:pay')
						$result['cancel_return'] .= '/'.$order->order_hash;
					elseif ($cancel_page->action_reference == 'shop:order')
						$result['cancel_return'] .= '/'.$order->id;
				}
			} else 
			{
				$result['return'] = Phpr::$request->getRootUrl().root_url('/ls_paypal_autoreturn/'.$order->order_hash.'/backend');
				//	$result['return'] = Phpr::$request->getRootUrl().url('shop/orders/preview/'.$order->id.'?'.uniqid());
				$result['cancel_return'] = Phpr::$request->getRootUrl().url('shop/orders/pay/'.$order->id.'?'.uniqid());
			}
			
			$result['bn'] = 'LemonStand_Cart_WPS';
			$result['charset'] = 'utf-8';
			
			foreach($result as $key=>$value)
			{
				$result[$key] = str_replace("\n", ' ', $value);
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
			 * We do not need any code here since payments are processed on PayPal server.
			 */
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
				'ls_paypal_autoreturn'=>'process_paypal_autoreturn',
				'ls_paypal_ipn'=>'process_paypal_ipn'
			);
		}
		
		protected function get_cancel_page($host_obj)
		{
			$cancel_page = $host_obj->cancel_page;
			$page_info = Cms_PageReference::get_page_info($host_obj, 'cancel_page', $host_obj->cancel_page);
			if (is_object($page_info))
				$cancel_page = $page_info->page_id;

			if (!$cancel_page)
				return null;

			return Cms_Page::create()->find($cancel_page);
		}
		
		public function process_paypal_ipn($params)
		{
			try
			{
				$order = null;
				
				/*
				 * Find order and load paypal settings
				 */
				
				sleep(5);

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
				
				if (!($payment_method_obj instanceof Shop_PayPal_Standard_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');
				
				$endpoint = $order->payment_method->test_mode ? 
					"www.sandbox.paypal.com/cgi-bin/webscr" : 
					"www.paypal.com/cgi-bin/webscr";
				
				$fields = $_POST;
				foreach($fields as $key => $value)
				{
					//replace every \n that isn't part of \r\n with \r\n to prevent an invalid response from PayPal
					$fields[$key] = preg_replace("~(?<!\r)\n~","\r\n",$value);
				}
				$fields['cmd'] = '_notify-validate';
				if(array_key_exists('txn_id', $fields))
					$transaction_id = $fields['txn_id'];

				$response = Core_Http::post_data($endpoint, $fields);

				if (!$order->payment_processed(false))
				{
					if (post('mc_gross') != strval($this->get_paypal_total($order, $order->payment_method)))
						$this->log_payment_attempt($order, 'Invalid order total received in IPN: '.format_currency(post('mc_gross')), 0, array(), $_POST, $response);
					else
					{
						if (strpos($response, 'VERIFIED') !== false)
						{
							if ($order->set_payment_processed())
							{
								Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
								$this->log_payment_attempt($order, 'Successful payment', 1, array(), $_POST, $response);
								if(isset($transaction_id) && strlen($transaction_id))
									$this->update_transaction_status($order->payment_method, $order, $transaction_id, 'Processed', 'processed');
							}
						} else
							$this->log_payment_attempt($order, 'Invalid payment notification', 0, array(), $_POST, $response);
					}
				}
			}
			catch (Exception $ex)
			{
				if ($order)
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), $_POST, null);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
		
		public function process_paypal_autoreturn($params)
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
			
				if (!($payment_method_obj instanceof Shop_PayPal_Standard_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = array_key_exists(1, $params) ? $params[1] == 'backend' : false;

				/*
				 * Send PayPal PDT request
				 */

				if (!$order->payment_processed(false))
				{
					$transaction = Phpr::$request->getField('tx');
					if (!$transaction)
						throw new Phpr_ApplicationException('Invalid transaction value');

					$endpoint = $order->payment_method->test_mode ? 
						"www.sandbox.paypal.com/cgi-bin/webscr" : 
						"www.paypal.com/cgi-bin/webscr";

					$fields = array(
						'cmd'=>'_notify-synch',
						'tx'=>$transaction,
						'at'=>$order->payment_method->pdt_token
					);

					$response = Core_Http::post_data($endpoint, $fields);

					/*
					 * Mark order as paid
					 */
			
					if (strpos($response, 'SUCCESS') !== false)
					{
						$matches = array();

						if (!preg_match('/^invoice=([0-9]*)/m', $response, $matches))
							throw new Phpr_ApplicationException('Invalid response.');

						if ($matches[1] != $order->id)
							throw new Phpr_ApplicationException('Invalid invoice number.');
							
						if (!preg_match('/^mc_gross=([0-9\.]+)/m', $response, $matches))
							throw new Phpr_ApplicationException('Invalid response.');
							
						if ($matches[1] != strval($this->get_paypal_total($order, $order->payment_method)))
							throw new Phpr_ApplicationException('Invalid order total - order total received is '.$matches[1]);

						if ($order->set_payment_processed())
						{
							Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
							$this->log_payment_attempt($order, 'Successful payment', 1, array(), Phpr::$request->get_fields, $response);
							$transaction_id = Phpr::$request->getField('tx');
							if(strlen($transaction_id))
								$this->update_transaction_status($order->payment_method, $order, $transaction_id, 'Processed', 'processed');
						}
					}
				}
			
				if (!$is_backend)
				{
					$return_page = $order->payment_method->receipt_page;
					if ($return_page)
						Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
					else 
						throw new Phpr_ApplicationException('PayPal Standard Receipt page is not found.');
				} else 
				{
					Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
				}
			}
			catch (Exception $ex)
			{
				if ($order)
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), Phpr::$request->get_fields, $response);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}

		/**
		 * This function is called before a CMS page deletion.
		 * Use this method to check whether the payment method
		 * references a page. If so, throw Phpr_ApplicationException 
		 * with explanation why the page cannot be deleted.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Cms_Page $page Specifies a page to be deleted
		 */
		public function page_deletion_check($host_obj, $page)
		{
			if ($host_obj->cancel_page == $page->id)
				throw new Phpr_ApplicationException('Page cannot be deleted because it is used in PayPal Standard payment method as a cancel page.');
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in PayPal Standard payment method.');
		}
		
		/**
		* This function is used internally to determine the order total as calculated by PayPal
		* Used because LS stores order item prices with more precision than is sent to PayPal.
		* which occasionally leads to the order totals not matching
		*/
		private function get_paypal_total($order, $host_obj)
		{
			if ($host_obj->skip_itemized_data)
				return $order->total;
			
			$order_total = 0;
			//add up individual order items
			foreach ($order->items as $item)
			{
				$item_price = round($item->unit_total_price, 2);
				$order_total = $order_total + ($item->quantity * $item_price);
			}
			
			//add shipping quote + tax
			$order_total = $order_total + $order->shipping_quote;
			if ($order->shipping_tax > 0)
				$order_total = $order_total + $order->shipping_tax;
			
			//order items tax
			$cart_tax = round($order->goods_tax, 2);
			$order_total = $order_total + $cart_tax;
			
			return $order_total;
		}

		public function extend_transaction_preview($payment_method_obj, $controller, $transaction)
		{
			$payment_method_obj->load_xml_data();
			if($payment_method_obj->test_mode)
				$url = "https://sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=";
			else $url = "https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=";
			$controller->viewData['url'] = $url;
			$controller->viewData['transaction_id'] = $transaction->transaction_id;
			$controller->renderPartial(PATH_APP.'/modules/shop/payment_types/shop_paypal_standard_payment/_payment_transaction.htm');
		}
	}

?>