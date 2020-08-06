<?

	/**
	 * Represents the generic payment type. 
	 * All other payment types must be derived from this class
	 */
	abstract class Shop_PaymentType extends Core_XmlController
	{
		/**
		 * Returns information about the payment type
		 * Must return array: array(
		 *		'name'=>'Authorize.net', 
		 *		'custom_payment_form'=>false,
		 *		'has_receipt_page'=>true,
		 *		'offline'=>false,
		 *		'pay_offline_message'=>null,
		 *		'description'=>'Authorize.net simple integration method with hosted payment form'
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
		abstract public function get_info();

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
		abstract public function build_config_ui($host_obj, $context = null);
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		abstract public function validate_config_on_save($host_obj);
		
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

		/**
		 * Returns a custom payment page.
		 * A payment method which allow custom payment pages selection must override this method
		 * and return a corresponding page object.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @return Cms_Page Returns a page object
		 */
		public function get_custom_payment_page($host_obj)
		{
			return null;
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
			
		}

		/**
		 * Returns true if the payment type is applicable for a specified order amount
		 * @param float $amount Specifies an order amount
		 * @param $host_obj ActiveRecord object to add fields to
		 * @return true
		 */
		public function is_applicable($amount, $host_obj)
		{
			return true;
		}

		/**
		 * Processes payment using passed data
		 * @param array $data Posted payment form data
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 * @param $back_end Determines whether the function is called from the administration area
		 */
		abstract public function process_payment_form($data, $host_obj, $order, $back_end = false);

		/**
		 * This method is called than an order with this payment method is created
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function order_after_create($host_obj, $order)
		{
		}

		/**
		 * Adds a log record to the order payment attempts log
		 * @param mixed $order Order object the payment attempt is belongs to
		 * @param string $message Log message
		 * @param bool $is_successful Indicates that the attempt was successful
		 * @param array $request_array An array containing data posted to the payment gateway
		 * @param array $response_array An array containing data received from the payment gateway
		 * @param string $response_text Raw gateway response text
		 * @param string $ccv_response_code Card code verification response code
		 * @param string $ccv_response_text Card code verification response text
		 * @param string $avs_response_code Address verification response code
		 * @param string $avs_response_text Address verification response text
		 */
		protected function log_payment_attempt($order, $message, $is_successful, $request_array, $response_array, $response_text, $ccv_response_code = null, $ccv_response_text = null, $avs_response_code = null, $avs_response_text = null)
		{
			$info = $this->get_info();

			$record = Shop_PaymentLogRecord::create();
			$record->order_id = $order->id;
			$record->payment_method_name = $info['name'];
			$record->message = $message;
			$record->is_successful = $is_successful;
			$record->request_data = $request_array;
			$record->response_data = $response_array;
			$record->raw_response_text = $response_text;
			$record->ccv_response_code = $ccv_response_code;
			$record->ccv_response_text = $ccv_response_text;
			$record->avs_response_code = $avs_response_code;
			$record->avs_response_text = $avs_response_text;
			
			$record->save();
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
			return array();
		}
		
		/**
		 * This method returns true for non-offline payment types
		 */
		public function has_payment_form()
		{
			$info = $this->get_info();
			return array_key_exists('offline', $info) && $info['offline'] ? false : true;
		}

		/**
		 * This method is called before the payment form is rendered
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function before_render_payment_form($host_obj)
		{
			
		}
		
		/**
		 * This method is called before the payment profile form is rendered
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function before_render_payment_profile_form($host_obj)
		{
			
		}
		
		/**
		 * Returns an offline payment message, specified with the 
		 * 'pay_offline_message' index in get_info() method result.
		 */
		public function pay_offline_message()
		{
			$info = $this->get_info();
			return array_key_exists('pay_offline_message', $info) ? $info['pay_offline_message'] : null;
		}
		
		/**
		 * This method should return FALSE to suppress the New Order Notification for new orders
		 * with this payment method assigned
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function allow_new_order_notification($host_obj, $order)
		{
			return true;
		}
		
		/*
		 * Transaction management functions
		 */
		
		/**
		 * Adds or updates the payment transaction status
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_name Specifies a transaction status name, for example: Approved
		 * @param string $transaction_status_code Specifies a transaction payment gateway-specific status code, for example: A
		 * @param string $data Additional data for the transactions required for a payment method
		 */
		public function update_transaction_status($host_obj, $order, $transaction_id, $transaction_status_name, $transaction_status_code, $data = null)
		{
			Shop_PaymentTransaction::update_transaction(
				$order, 
				$host_obj->id,
				$transaction_id, 
				$transaction_status_name,
				$transaction_status_code,
				null,
				$data
			);
		}

		/**
		 * Adds payment transaction to order history. Use in place of update_transaction_status when confirming a settled or refunded payment value
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @param object Shop_TransactionUpdate $transaction_data
		 */
		public function add_transaction($host_obj, $order, $transaction_id, $transaction_data, $user_note = null)
		{
			Shop_PaymentTransaction::add_transaction(
				$order,
				$host_obj->id,
				$transaction_id,
				$transaction_data,
				$user_note
			);
		}

		/**
		 * A gateway can provide a settlement exchange rate
		 * when converting foreign currency payments to local bank account currency.
		 * This method can be used to update the conversion rate stored on the order
		 * with the actual rate used by the payment gateway for conversion.
		 * The orders stored currency rate will only update if the given currency code matches the
		 * orders currency code
		 *
		 * @param $order ActiveRecord object Shop_Order
		 * @param string $payment_currency_code ISO currency code for payment
		 * @param string $exchange_rate Exchange rate from payment currency to this shops base currency
		 */
		public function update_currency_data( $order, $payment_currency_code, $exchange_rate){
			if(!empty($payment_currency_code) && ($order->get_currency_code() == $payment_currency_code)) {
				$order->set_currency($payment_currency_code, $exchange_rate);
			}
		}


		/**
		 * This method should return TRUE if the payment gateway supports requesting a status of a specific transaction.
		 * The payment module must implement the request_transaction_status() method if this method returns true..
		 */
		public function supports_transaction_status_query()
		{
			return false;
		}

		/**
		 * This method should return TRUE if the payment gateway supports requesting a status of a specific transaction dispute.
		 * The payment module must implement the request_dispute_status() method if this method returns true.
		 */
		public function supports_transaction_disputes()
		{
			return false;
		}

		/**
		 * This method should return TRUE if the payment method logs successful payment/refund values that can be used
		 * to calculate payment due and assist in refunds. For example: situations where products added/removed from order change the order total after initial payment.
		 */
		public function supports_multiple_payments()
		{
			return false;
		}

		/**
		 * Returns the balance paid on the order through the payment gateway
		 * @param $order ActiveRecord object Shop_Order
		 * @param boolean $include_pending If payments pending should be included (not yet settled)
		 * @param boolean $in_refunds Will the total paid out in refunds
		 */
		public function get_total_paid($order, $include_pending=true, $in_refunds = false){
			if($this->supports_multiple_payments()){

				if($in_refunds){
					return $this->get_refunds_total($order,$include_pending);
				}

				$total_paid = $this->get_payments_total($order, $include_pending) - $this->get_refunds_total($order,$include_pending);
				return round($total_paid, 2);
			}
			throw new Phpr_SystemException('Tracking total paid across multiple payments is not supported by this payment module.');
		}

		/**
		 * Returns total sum of all payments processed through the gateway
		 * @param $order ActiveRecord object Shop_Order
		 * @param boolean $include_pending If payments pending should be included (not yet settled)
		 */
		public function get_payments_total($order, $include_pending=true){
			if($this->supports_multiple_payments()){
				$total_payment = 0;
				$add_where = $include_pending ? null : 'AND transaction_complete = 1';
				$sql_where = "shop_payment_transactions.id in (SELECT MAX(id)
							 	     FROM shop_payment_transactions
							 		 WHERE order_id = :order_id $add_where
							 		 AND transaction_refund IS NULL
							 		 AND transaction_void IS NULL
							 		 GROUP BY transaction_id
							 		 ORDER BY created_at, transaction_complete DESC)";

				$void_transaction_ids = Shop_PaymentTransaction::get_void_transaction_ids($order);
				if(count($void_transaction_ids)){
					$sql_where .= ' AND shop_payment_transactions.transaction_id NOT IN (:void_transaction_ids)';
				}
				$bind = array(
					'order_id' => $order->id,
					'void_transaction_ids' => $void_transaction_ids
				);
				$transactions = Shop_PaymentTransaction::create()->where($sql_where, $bind)->find_all();
				if($transactions){
					foreach($transactions as $transaction){
						if($transaction->transaction_value ){
								$total_payment += $transaction->transaction_value;
						}

					}
				}
				return round($total_payment, 2);
			}
			throw new Phpr_SystemException('The payment gateway does not support this feature.');
		}

		/**
		 * Returns total sum of all refunds processed through the gateway
		 * @param $order ActiveRecord object Shop_Order
		 * @param boolean $include_pending If refunds pending should be included (not yet settled)
		 */
		public function get_refunds_total($order, $include_pending=true){
			if($this->supports_multiple_payments()){
				$total_refunded = 0;
				$add_where = $include_pending ? null : 'AND transaction_complete = 1';
				$sql_where = "shop_payment_transactions.id in (SELECT MAX(id)
							 	     FROM shop_payment_transactions
							 		 WHERE order_id = :order_id $add_where
							 		 AND transaction_refund = 1
							 		 AND transaction_void IS NULL
							 		 GROUP BY transaction_id
							 		 ORDER BY created_at, transaction_complete DESC)";

				$void_transaction_ids = Shop_PaymentTransaction::get_void_transaction_ids($order);
				if(count($void_transaction_ids)){
					$sql_where .= ' AND shop_payment_transactions.transaction_id NOT IN (:void_transaction_ids)';
				}
				$bind = array(
					'order_id' => $order->id,
					'void_transaction_ids' => $void_transaction_ids
				);
				$transactions = Shop_PaymentTransaction::create()->where($sql_where, $bind)->find_all();
				if($transactions){
					foreach($transactions as $transaction){
						if($transaction->transaction_value ){
							$total_refunded += $transaction->transaction_value;
						}

					}
				}
				return round($total_refunded, 2);
			}
			throw new Phpr_SystemException('The payment gateway does not support this feature.');
		}


		
		/**
		 * Returns a list of available transitions from a specific transaction status
		 * The method returns an associative array with keys corresponding transaction statuses 
		 * and values corresponding transaction status actions: array('V'=>'Void', 'S'=>'Submit for settlement')
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_code Specifies a transaction status code returned by the payment gateway. Example: authorized
		 */
		public function list_available_transaction_transitions($host_obj, $transaction_id, $transaction_status_code)
		{
			array();
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
			return null;
		}

		/**
		 * Request a status of a specific transaction.
		 * The method must return an instance of the Shop_TransactionUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @return Shop_TransactionUpdate Transaction update information
		 */
		public function request_transaction_status($host_obj, $transaction_id)
		{
			return null;
		}

		/**
		 * Request a status of a specific transaction dispute.
		 * The method must return an instance of the Shop_TransactionDisputeUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $dispute_case_id Specifies a dispute case identifier returned by the payment gateway.
		 * @return Shop_TransactionDisputeUpdate Dispute update information
		 */
		public function request_dispute_status($host_obj, $dispute_case_id)
		{
			return null;
		}

		/**
		 * Request all status transitions for a transaction
		 * The method must return an array containing instances of the Shop_TransactionUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @return array Returns an array of {@link Shop_TransactionUpdate} objects.
		 */
		public function request_transaction_history($host_obj, $transaction_id) {
			return null;
		}
		
		/**
		 * This method should return TRUE if the payment module supports customer payment profiles.
		 * The payment module must implement the update_customer_profile(), delete_customer_profile() and pay_from_profile() methods if this method returns true..
		 */
		public function supports_payment_profiles()
		{
			return false;
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
			throw new Phpr_SystemException('The update_customer_profile() method is not supported by the payment module.');
		}
		
		/**
		 * Deletes a customer profile from the payment gateway.
		 * @param Db_ActiveRecord $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Customer $customer Shop_Customer object
		 * @param Shop_CustomerPaymentProfile $profile Customer profile object
		 */
		public function delete_customer_profile($host_obj, $customer, $profile)
		{
			throw new Phpr_SystemException('The delete_customer_profile() method is not supported by the payment module.');
		}

		/**
		 * Creates a payment transaction from an existing payment profile.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Order $order An order object to pay		
		 * @param boolean $back_end Determines whether the function is called from the administration area
		 * @param boolean $redirect Determines whether the browser should be redirected to the receipt page after successful payment
		 */
		public function pay_from_profile($host_obj, $order, $back_end = false, $redirect = true)
		{
			throw new Phpr_SystemException('The pay_from_profile() method is not supported by the payment module.');
		}
		
		/**
		 * Allows to extend the transaction preview popup window with transaction-specific information.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Backend_Controller $controller Back-end controller object
		 * @param Shop_PaymentTransaction $transaction Transaction object
		 */
		public function extend_transaction_preview($host_obj, $controller, $transaction)
		{
		}

		/**
		 * Returns payment methods that use this payment type
		 * @param boolean $active_only Determines if only active backend/frontend payment methods should be returned
		 */
		public static function get_host_instances($active_only = true){
			$instances = Shop_PaymentMethod::create();
			if($active_only){
				$instances->where('(enabled = 1 OR backend_enabled = 1)');
			}
			$called_class = function_exists('get_called_class') ? get_called_class() : get_class(self);
			if($called_class !== 'Shop_PaymentType'){
				$instances->where('class_name = ?',$called_class);
			}
			return $instances->find_all();
		}
	}

?>