<?

	class Shop_Custom_Payment extends Shop_PaymentType
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
				'name'=>'Custom Payment Method',
				'description'=>'Use this payment method for creating payment forms with custom payment processing.',
				'has_receipt_page'=>false,
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
			$host_obj->add_field('payment_page', 'Payment Page')->tab('Configuration')->renderAs(frm_dropdown)->formElementPartial(PATH_APP.'/modules/shop/controllers/partials/_page_selector.htm')->comment('Page to which the customer’s browser is redirected if this payment was selected during checkout. If no page is selected, the default Payment page will be used.', 'above')->emptyOption('<please select a page>');

			$host_obj->add_field('pre_order_status', 'Order Start Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select a status to assign the order if this payment method was selected during checkout.', 'above')->emptyOption('<default order status/do not change the order status>');

			$host_obj->add_field('suppress_new_notification', 'Suppress the New Order Notification')->tab('Configuration')->renderAs(frm_checkbox)->comment('Use this checkbox if you want to disable the New Order Notification for new orders with this payment method assigned.');
		}
		
		public function get_pre_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			if ($current_key_value)
				return Shop_OrderStatus::create()->find($current_key_value)->name;
			else return null;
		}
		
		public function get_payment_page_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Cms_Page::create()->order('title')->find_all()->as_array('title', 'id');

			if ($current_key_value)
				return Cms_Page::create()->find($current_key_value)->title;
			else
				return null;
		}

		public function has_payment_form()
		{
			return false;
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
		}
		
		/*
		 * Payment processing
		 */

		/**
		 * Processes payment using passed data
		 * @param array $data Posted payment form data
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function process_payment_form($data, $host_obj, $order, $back_end = false)
		{
			
		}
		
		/**
		 * This method is called before the payment form is rendered
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function before_render_payment_form($host_obj)
		{
			$pay_page =  $this->get_custom_payment_page($host_obj);
			$controller = Cms_Controller::get_instance();
			if ($pay_page && $controller)
			{
				if (isset($controller->data['order']))
				{
					if ($controller->page && $controller->page->id == $pay_page->id)
						return;

					Phpr::$response->redirect(root_url($pay_page->url.'/'.$controller->data['order']->order_hash));
				}
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
			if ($host_obj->payment_page == $page->id)
				throw new Phpr_ApplicationException('Page cannot be deleted because it is used in the '.$host_obj->name.' payment method as a payment page.');
		}
		
		public function get_custom_payment_page($host_obj)
		{
			$payment_page = $host_obj->payment_page;
			$page_info = Cms_PageReference::get_page_info($host_obj, 'payment_page', $host_obj->payment_page);
			if (is_object($page_info))
				$payment_page = $page_info->page_id;

			if (!$payment_page)
				return null;

			return Cms_Page::create()->find($payment_page);
		}
		
		/**
		 * This method is called than an order with this payment method is created
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function order_after_create($host_obj, $order)
		{
			if (!$host_obj->pre_order_status)
				return;

			$status_new = Shop_OrderStatus::get_status_new();
			if ($status_new && $status_new->id == $host_obj->pre_order_status)
				return;
				
			$order = Shop_Order::create()->find($order->id);

			Shop_OrderStatusLog::create_record($host_obj->pre_order_status, $order, null);
		}
		
		/**
		 * This method should return FALSE to suppress the New Order Notification for new orders
		 * with this payment method assigned
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function allow_new_order_notification($host_obj, $order)
		{
			return !$host_obj->suppress_new_notification;
		}
	}

?>