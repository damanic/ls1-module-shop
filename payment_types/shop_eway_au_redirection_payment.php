<?

	class Shop_Eway_Au_Redirection_Payment extends Shop_PaymentType
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
				'name'=>'eWAY Redirection Payment - Australia',
				'description'=>'DEPRECATED. Please use "eWAY Redirection Payment" method instead. This eWAY solution allows your customers’ to be redirected to a secure eWAY payment page via HTTP FORM POST.',
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
				$host_obj->add_form_partial($host_obj->get_partial_path('hint.htm'))->tab('Configuration');
				$host_obj->add_field('ewayCustomerID', 'Customer Id', 'left')->comment('Your unique eWAY customer ID assigned to you when you join eWAY. eg 11438715', 'above')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide Customer Id.');
			}

			$host_obj->add_field('ewaySiteTitle', 'Website Title',' right')->comment('The name of your website will be displayed on the Shared Page for the user to see.', 'above')->tab('Configuration')->renderAs(frm_text)->validation()->required('Please enter the website title.');
			$host_obj->add_field('order_status', 'Order Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
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
			if (array_key_exists('ewayTrxnStatus', $_POST))
				$this->process_return($host_obj, $order, $backend);

			return "https://www.eway.com.au/gateway_cvn/payment.asp";
		}
		
		protected function process_return($host_obj, $order, $backend = false)
		{
			try
			{
				if ($order->payment_processed())
					throw new Phpr_ApplicationException('This order is already paid.');
				
				$status = post('ewayTrxnStatus');
				if ($status != 'True')
					throw new Phpr_ApplicationException(post('eWAYresponseText', 'The credit card has been declined by the gateway'));
				else
				{
					/*
					 * Log payment attempt
					 */
					$this->log_payment_attempt($order, 'Successful payment', 1, array(), $_POST, null);

					/*
					 * Change order status
					 */
					Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
					
					/*
					 * Log transaction create/change
					 */
					$this->update_transaction_status($host_obj, $order, post('ewayTrxnReference'), 'Approved', 'True');
					
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
			} catch (Exception $ex)
			{
				$error_message = Phpr::$session->flash['error'] = $ex->getMessage();

				$this->log_payment_attempt($order, $error_message, 0, array(), $_POST, null);
			}
		}
		
		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$result = array();
			$amount = $order->total;

			$userIp = Phpr::$request->getUserIp();

			$fields = array();
			$fields['ewayCustomerID'] = $host_obj->ewayCustomerID;
			$fields['ewayTotalAmount'] = $order->total*100;

			$fields['ewayCustomerFirstName'] = $order->billing_first_name;
			$fields['ewayCustomerLastName'] = $order->billing_last_name;
			$fields['ewayCustomerEmail'] = $order->billing_email;
			$fields['ewayCustomerAddress'] = $order->billing_street_addr;
			$fields['ewayCustomerPostcode'] = $order->billing_zip;
			$fields['ewayCustomerInvoiceRef'] = $order->id;
			$fields['ewaySiteTitle'] = $host_obj->ewaySiteTitle;
			$fields['ewayAutoRedirect'] = 1;

			$currentUrl = Phpr::$request->getCurrentUrl();
			$fields['ewayURL'] = $currentUrl;

			return $fields;
		}
	}
?>