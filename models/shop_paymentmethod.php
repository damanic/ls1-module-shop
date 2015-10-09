<?

	/**
	 * Represents a payment method.
	 * Object if this class is available through the <em>$payment_method</em> property of the {@link Shop_Order} class.
	 * Also, a collection of payment method objects is available on the Payment Method step of the Checkout process.
	 * @documentable
	 * @property integer $id Specifies the payment method record identifier.
	 * @property string $name Specifies the payment method name.
	 * @property string $description Specifies the payment method description in plain text format. 
	 * @property string $ls_api_code Specifies the payment method API code.
	 * @see Shop_Order
	 * @see http://lemonstand.com/docs/checkout_page Checkout page
	 * @see http://lemonstand.com/docs/order_details_page Order details page
	 * @see http://lemonstand.com/docs/pay_page Payment page
	 * @see http://lemonstand.com/docs/developing_payment_modules Developing payment modules
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_PaymentMethod extends Db_ActiveRecord
	{
		public $table_name = 'shop_payment_methods';
		public $enabled = 1;
		public $backend_enabled = 1;
		public $order;

		protected $payment_type_obj = null;
		protected $added_fields = array();
		protected $hidden_fields = array();
		protected $form_context = null;

		public $custom_columns = array('payment_type_name'=>db_text, 'receipt_page'=>db_text);
		public $encrypted_columns = array('config_data');
		
		public $fetched_data = array();
		
		protected $form_fields_defined = false;
		protected static $cache = array();
		protected static $customer_group_filter_cache = null;
		
		protected $api_added_columns = array();

		public $has_and_belongs_to_many = array(
			'countries'=>array('class_name'=>'Shop_Country', 'join_table'=>'shop_paymentmethods_countries', 'order'=>'name'),
			'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'shop_paymentmethods_customer_groups', 'order'=>'name', 'foreign_key'=>'customer_group_id', 'primary_key'=>'shop_payment_method_id')
		);
		
		public $belongs_to = array(
			'receipt_page_link'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'receipt_page_id')
		);

		public static function create()
		{
			return new self();
		}

		/**
		 * Finds a payment method by the API code. 
		 * This method allows you to find specific payment methods. You can assign the LemonStand API Code to any payment method 
		 * in the payment method configuration form.
		 * @documentable
		 * @param string $code Specifies the payment method API code.
		 * @return Shop_PaymentMethod Returns the payment method object. Returns NULL if the payment method is not found.
		 */
		public static function find_by_api_code($code)
		{
			$code = mb_strtolower($code);
			return self::create()->where('ls_api_code=?', $code)->find();
		}

		/**
		 * Finds a payment method by its identifier.
		 * This method caches payment methods in memory and it is preferable to use this method instead of direct {@link Db_ActiveRecord} 
		 * operations due to the performance considerations.
		 * @documentable
		 * @param integer $id Specifies the payment method identifier.
		 * @return Shop_PaymentMethod Returns the payment method object. Returns NULL if the payment method is not found.
		 */
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}
		
		protected static function method_visible_for_customer_group($method_id, $customer_group_id)
		{
			if (self::$customer_group_filter_cache === null)
			{
				self::$customer_group_filter_cache = array();
				$filter_records = Db_DbHelper::objectArray('select * from shop_paymentmethods_customer_groups');
				foreach ($filter_records as $record)
				{
					if (!array_key_exists($record->shop_payment_method_id, self::$customer_group_filter_cache))
						self::$customer_group_filter_cache[$record->shop_payment_method_id] = array();
						
					self::$customer_group_filter_cache[$record->shop_payment_method_id][] = $record->customer_group_id;
				}
			}
			
			if (!array_key_exists($method_id, self::$customer_group_filter_cache))
				return true;
				
			return in_array($customer_group_id, self::$customer_group_filter_cache[$method_id]);
		}

		public static function list_applicable($country_id, $amount, $backend_only = false, $ignore_customer_group_filter = false, $customer_group_id = false, $order_items = array())
		{
			if($backend_only)
				$backend_where = 'backend_enabled = 1 and backend_enabled is not null';
			else
				$backend_where = 'enabled = 1 and enabled is not null';
				
			$methods = self::create()->order('shop_payment_methods.name')->where($backend_where)->where('(select count(*) from shop_paymentmethods_countries where shop_country_id=? and shop_paymentmethods_countries.shop_payment_method_id=shop_payment_methods.id) > 0 or (select count(*) from shop_paymentmethods_countries where shop_paymentmethods_countries.shop_payment_method_id=shop_payment_methods.id) = 0', $country_id)->find_all();
			
			$result = array();
			foreach ($methods as $method)
			{
				if (!$ignore_customer_group_filter)
				{
					if ($customer_group_id === false)
						$customer_group_id = Cms_Controller::get_customer_group_id();
					
					if (!self::method_visible_for_customer_group($method->id, $customer_group_id))
						continue;
				}

				$method->define_form_fields();
				if ($method->get_paymenttype_object()->is_applicable($amount, $method))
					$result[] = $method;
			}

			$event_params = array(
				'payment_methods' => $result,
				'amount' => $amount,
				'country_id' => $country_id,
				'customer_group_id' => $customer_group_id,
				'ignore_customer_group_filter' => $ignore_customer_group_filter,
				'backend' => $backend_only,
				'order_items' => $order_items
			);

			$updated_result = Backend::$events->fireEvent('shop:onFilterPaymentMethods', $event_params);
			foreach ($updated_result as $updated_option_list)
			{
				$result = $updated_option_list;
				break;
			}

			return new Db_DataCollection($result);
		}

		public static function page_deletion_check($page)
		{
			$methods = self::create()->find_all();
			
			foreach ($methods as $method)
			{
				$method->define_form_fields();
				$method->get_paymenttype_object()->page_deletion_check($method, $page);
			}
		}

		public static function order_status_deletion_check($status)
		{
			$methods = self::create()->find_all();
			
			foreach ($methods as $method)
			{
				$method->define_form_fields();
				$method->get_paymenttype_object()->status_deletion_check($method, $status);
			}
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required('Please specify the payment method name.');
			$this->define_column('payment_type_name', 'Payment Gateway');
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('enabled', 'Enabled on the front-end website');
			$this->define_column('backend_enabled', 'Enabled in the Administration Area');
			$this->define_column('ls_api_code', 'LemonStand API Code')->defaultInvisible()->validation()->fn('trim')->fn('mb_strtolower')->unique('Payment method with the specified LemonStand API code already exists.');
			
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			if (!$front_end)
			{
				$this->define_relation_column('receipt_page_link', 'receipt_page_link', 'Receipt Page ', db_varchar, 'concat(@title, \' [\', @url, \']\')')->validation();
				$this->define_multi_relation_column('countries', 'countries', 'Countries', '@name')->defaultInvisible();
				$this->define_multi_relation_column('customer_groups', 'customer_groups', 'Customer Groups', '@name')->defaultInvisible();
			}
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendPaymentMethodModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			if ($this->form_fields_defined)
				return false;

			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
				
			$this->form_fields_defined = true;
			
			$this->form_context = $context;
			
			if ($context != 'backend_payment_form')
			{
				$this->add_form_field('enabled')->tab('General Parameters');
				$backend_enabled = $this->add_form_field('backend_enabled')->tab('General Parameters');
				if($this->enabled)
					$backend_enabled->disabled();
				$this->add_form_field('name')->comment('Name of the payment method. It will be displayed on the front-end website.', 'above')->tab('General Parameters');
				$this->add_form_field('description')->comment('If provided, it will be displayed on the front-end website.', 'above')->tab('General Parameters')->size('small');

				$obj = $this->get_paymenttype_object();
				$method_info = $obj->get_info();

				$has_receipt_page = array_key_exists('has_receipt_page', $method_info) ? $method_info['has_receipt_page'] : true;
				if ($has_receipt_page && !$front_end)
					$this->add_form_field('receipt_page_link')->comment('Page to which the customer’s browser is redirected after successful payment.', 'above')->tab('General Parameters')->previewNoRelation()->optionsHtmlEncode(false);
			
				$this->add_form_field('ls_api_code')->comment('You can use the API Code for identifying the payment method in the API calls.', 'above')->tab('General Parameters');

				if (!$front_end)
				{
					$this->add_form_field('countries')->tab('Countries')->comment('Countries the payment method is applicable to. Uncheck all countries to make the payment method applicable to any country.', 'above')->referenceSort('name');
					$this->add_form_field('customer_groups')->tab('Visibility')->comment('Please select customer groups the payment method should be available for. If no groups are selected, the payment method will be available for all customer groups.', 'above');
				}

				$this->get_paymenttype_object()->build_config_ui($this, $context);
				if (!$this->is_new_record())
					$this->load_xml_data();
				else
					$this->get_paymenttype_object()->init_config_data($this);
			} else
			{
				$this->load_xml_data();
				$this->add_form_partial(PATH_APP.'/modules/shop/controllers/shop_orders/_pay_hidden_fields.htm')->tab('Payment Information');
				$this->get_paymenttype_object()->build_payment_form($this, $context);
			}
			
			Backend::$events->fireEvent('shop:onExtendPaymentMethodForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field) {
					$form_field->optionsMethod('get_api_added_field_options');
					$form_field->optionStateMethod('get_api_added_field_option_state');
				}
			}
		}
		
		public function get_api_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetPaymentMethodFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_api_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetPaymentMethodFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public function get_receipt_page_options($key_value=-1)
		{
			return Cms_Page::create()->get_page_tree_options($key_value);
		}
		
		public function get_countries_options($key_value=1)
		{
			$records = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 order by name');
			$result = array();
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}
		
		/**
		 * Throws validation exception on a specified field
		 * @param $field Specifies a field code (previously added with add_field method)
		 * @param $message Specifies an error message text
		 * @param $grid_row Specifies an index of grid row, for grid controls
		 * @param $grid_column Specifies a name of column, for grid controls
		 */
		public function field_error($field, $message, $grid_row = null, $grid_column = null)
		{
			if ($grid_row != null)
			{
				$rule = $this->validation->getRule($field);
				if ($rule)
					$rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
			}
			
			$this->validation->setError($message, $field, true);
		}
		
		public function before_save($deferred_session_key = null)
		{
			if ($this->enabled)
				$this->backend_enabled = 1;
			
			$this->get_paymenttype_object()->validate_config_on_save($this);
			
			$document = new SimpleXMLElement('<payment_type_settings></payment_type_settings>');
			foreach ($this->added_fields as $code=>$form_field)
			{
				$field_element = $document->addChild('field');
				$field_element->addChild('id', $code);
				$field_element->addChild('value', base64_encode(serialize($this->$code)));
			}
			
			foreach ($this->hidden_fields as $code=>$form_field)
			{
				$field_element = $document->addChild('field');
				$field_element->addChild('id', $code);
				$field_element->addChild('value', serialize($this->$code));
			}

			$this->config_data = $document->asXML();
		}

		public function add_field($code, $title, $side = 'full', $type = db_text)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $title)->validation();

			$form_field = $this->add_form_field($code, $side)->optionsMethod('get_added_field_options')->optionStateMethod('get_added_field_option_state');
			if ($this->form_context != 'backend_payment_form')
				$form_field->tab('Configuration');
			else
				$form_field->tab('Payment Information');
			
			$this->added_fields[$code] = $form_field;
			
			return $form_field;
		}
		
		public function add_hidden_field($code, $value = '', $type = db_text)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $code);
			$this->hidden_fields[$code] = $code;
			//$this->$code = $value;
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$obj = $this->get_paymenttype_object();
			$method_name = "get_{$db_name}_options";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");

			return $obj->$method_name($current_key_value);
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$obj = $this->get_paymenttype_object();
			$method_name = "get_{$db_name}_option_state";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
				
			return $obj->$method_name($key_value);
		}

		/**
		 * Returns a payment type object.
		 * Each payment method has an underlying payment type object. Payment type classes
		 * are specific for different payment gateways and implement operations
		 * required for working with a specific gateway.
		 * @documentable
		 * @see http://lemonstand.com/docs/developing_payment_modules Developing payment modules
		 * @return Shop_PaymentType Returns a payment type object.
		 */
		public function get_paymenttype_object()
		{
			if ($this->payment_type_obj !== null)
			 	return $this->payment_type_obj;
			
			if (!Phpr::$classLoader->load($this->class_name))
				throw new Phpr_ApplicationException("Class {$this->class_name} not found.");
				
			$class_name = $this->class_name;
				
			return $this->payment_type_obj = new $class_name();
		}
		
		public function eval_payment_type_name()
		{
			$obj = $this->get_paymenttype_object();
			$info = $obj->get_info();
			if (array_key_exists('name', $info))
				return $info['name'];
			
			return null;
		}
		
		public function eval_receipt_page()
		{
			$page_info = Cms_PageReference::get_page_info($this, 'receipt_page_id', null);
			if (!$page_info)
				return $this->receipt_page_link;
			
			if (is_object($page_info))
				return Cms_Page::create()->find($page_info->page_id);
				
			return null;
		}
		
		/**
		 * Renders the payment method payment form.
		 * This method is used inside the {@link http://lemonstand.com/docs/pay_page payment page}.
		 * @documentable
		 * @see http://lemonstand.com/docs/pay_page Payment page
		 * @param Cms_Controller $controller A CMS controller object.
		 */
		public function render_payment_form($controller)
		{
			$obj = $this->get_paymenttype_object();
			if ($obj)
			{
				$obj->before_render_payment_form($this);

				$class = get_class($obj);
				$pos = strpos($class, '_');
				$payment_type_file = strtolower(substr($class, $pos+1, -8));
				$partial_name = 'payment:'.$payment_type_file;

				if (Cms_Partial::create()->find_by_name($partial_name))
					$controller->render_partial($partial_name);
			}
		}
		
		/**
		 * Renders the payment method payment profile form.
		 * This method is used inside the {@link http://lemonstand.com/docs/implementing_customer_payment_profiles payment proile page}.
		 * @documentable
		 * @see http://lemonstand.com/docs/implementing_customer_payment_profiles Payment profile page
		 * @param Cms_Controller $controller A CMS controller object.
		 */
		public function render_payment_profile_form($controller)
		{
			$obj = $this->get_paymenttype_object();
			if ($obj)
			{
				$obj->before_render_payment_profile_form($this);

				$class = get_class($obj);
				$pos = strpos($class, '_');
				$payment_type_file = strtolower(substr($class, $pos+1, -8));
				$partial_name = 'payment_profile:'.$payment_type_file;

				if (Cms_Partial::create()->find_by_name($partial_name))
					$controller->render_partial($partial_name);
			}
		}

		public function before_delete($id=null) 
		{
			$bind = array(
				'id'=>$this->id
			);

			$count = Db_DbHelper::scalar('select count(*) from shop_orders where payment_method_id=:id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete this payment method because there are orders referring to it.');

			$count = Db_DbHelper::scalar('select count(*) from shop_payment_transactions where payment_method_id=:id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete this payment method because there are transactions referring to it.');
		}
		
		/**
		 * Determines whether the payment method has a payment form.
		 * This method returns TRUE for online payment methods. Some payment methods, for example 
		 * the custom payment method, do not have a payment form.
		 * This method is used inside the {@link http://lemonstand.com/docs/pay_page payment page}.
		 * @documentable
		 * @see http://lemonstand.com/docs/pay_page Payment page
		 * @see Shop_PaymentMethod::pay_offline_message() pay_offline_message()
		 * @return boolean Returns TRUE if the method has a payment form.
		 */
		public function has_payment_form()
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return false;

			return $obj->has_payment_form();
		}
		
		/**
		 * Returns the offline payment message if the payment method supports it.
		 * Some payment methods, for example the custom payment method, do not have a payment form and display an
		 * offline payment message instead.
		 * This method is used inside the {@link http://lemonstand.com/docs/pay_page payment page}.
		 * @documentable
		 * @see http://lemonstand.com/docs/pay_page Payment page
		 * @see Shop_PaymentMethod::has_payment_form() has_payment_form()
		 * @return string Returns the offline payment message. Returns FALSE if the message is not supported.
		 */
		public function pay_offline_message()
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return false;

			return $obj->pay_offline_message(); 
		}

		protected function load_xml_data()
		{
			if (!strlen($this->config_data))
				return;

			$object = new SimpleXMLElement($this->config_data);
			foreach ($object->children() as $child)
			{
				$code = $child->id;
				$value = base64_decode($child->value, true);
				$this->$code = unserialize($value !== false ? $value : $child->value);

				$code_array = (array)$code;
				$this->fetched_data[$code_array[0]] = $this->$code;
			}

			$this->get_paymenttype_object()->validate_config_on_load($this);
		}

		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		public function get_partial_path($partial_name = null)
		{
			$class_name = get_class($this->get_paymenttype_object());
			$classInfo = new ReflectionClass($class_name);
			return dirname($classInfo->getFileName()).'/'.strtolower($class_name).'/'.$partial_name;
		}
		
		/*
		 * Transaction management functions
		 */
		
		/**
		 * Checks whether the payment method supports requesting a status of a specific transaction.
		 * @documentable
		 * @return boolean Returns TRUE if transaction status requests are supported. Returns FALSE otherwise.
		 */
		public function supports_transaction_status_query()
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return false;

			return $obj->supports_transaction_status_query();
		}
		
		/**
		 * Returns a list of available transitions from a specific transaction status.
		 * The method returns an associative array with keys corresponding transaction statuses 
		 * and values corresponding transaction status actions: array('V'=>'Void', 'S'=>'Submit for settlement').
		 * Transaction statuses are specific for different payment gateways.
		 * @documentable
		 * @param string $transaction_id Gateway-specific transaction identifier
		 * @param string $transaction_code Gateway-specific transaction status code
		 * @return array Returns an array of transaction status actions and status names.
		 */
		public function list_available_transaction_transitions($transaction_id, $transaction_status_code)
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return array();
				
			$this->define_form_fields();

			$result = $obj->list_available_transaction_transitions($this, $transaction_id, $transaction_status_code);
			if (!is_array($result))
				$result = array();

			return $result;
		}

		/**
		 * Contacts the payment gateway and sets specific status for specific transaction.
		 * @documentable
		 * @param Shop_Order $order Order object the transaction belongs to.
		 * @param string $transaction_id Gateway-specific transaction identifier.
		 * @param string $transaction_code Current gateway-specific transaction status code.
		 * @param string $new_transaction_code Destination gateway-specific transaction status code.
		 * @return Shop_TransactionUpdate Returns the transaction update information.
		 */
		public function set_transaction_status($order, $transaction_id, $transaction_status_code, $new_transaction_status_code)
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return null;
				
			return $obj->set_transaction_status($this, $order, $transaction_id, $transaction_status_code, $new_transaction_status_code);
		}
		
		/**
		 * Returns status of a specific transaction.
		 * This method contacts the payment gateway and requests the actual transaction status.
		 * @documentable.
		 * @return Shop_TransactionUpdate Returns the transaction information.
		 */
		public function request_transaction_status($transaction_id)
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return null;
				
			return $obj->request_transaction_status($this, $transaction_id);
		}

		public function extend_transaction_preview($controller, $transaction)
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return null;

			return $obj->extend_transaction_preview($this, $controller, $transaction);
		}
		
		public static function create_partials()
		{
			$partial_list = Db_DbHelper::objectArray('select name, theme_id from partials');
			$partials = array();
			foreach ($partial_list as $partial)
			{
				if (!$partial->theme_id)
					$partial->theme_id = 0;
					
				if (!array_key_exists($partial->theme_id, $partials))
					$partials[$partial->theme_id] = array();
					
				$partials[$partial->theme_id][$partial->name] = $partial;
			}

			$payment_methods = self::create()->find_all();

			foreach ($payment_methods as $payment_method)
			{
				$class = $payment_method->class_name;

				if (preg_match('/_Payment$/i', $class) && get_parent_class($class) == 'Shop_PaymentType')
				{
					$pos = strpos($class, '_');
					$payment_type_file = strtolower(substr($class, $pos+1, -8));
					$payment_partial_name = 'payment:'.$payment_type_file;
					$payment_profile_partial_name = 'payment_profile:'.$payment_type_file;
					$classInfo = null;
					
					foreach ($partials as $theme_id=>$partial_list)
					{
						$theme = Cms_Theme::get_theme_by_id($theme_id);
						$extension = 'htm';
						if ($theme)
						{
							if ($theme->templating_engine == 'twig')
								$extension = 'twig';
						}
						else {
							if (Cms_SettingsManager::get()->default_templating_engine == 'twig')
								$extension = 'twig';
						}

						$payment_partial_exists = array_key_exists($payment_partial_name, $partial_list);
						$payment_profile_partial_exists = array_key_exists($payment_profile_partial_name, $partial_list);

						if (!$payment_partial_exists || !$payment_profile_partial_exists)
						{
							$classInfo = $classInfo ? $classInfo : new ReflectionClass($class);

							if (!$payment_partial_exists)
							{
								$file_path = dirname($classInfo->getFileName()).'/'.strtolower($class).'/front_end_partial.'.$extension;
								self::create_partial_from_file($payment_partial_name, "Payment form partial", $file_path, $theme_id);
							}

							if (!$payment_profile_partial_exists)
							{
								$file_path = dirname($classInfo->getFileName()).'/'.strtolower($class).'/payment_profile_partial.'.$extension;
								self::create_partial_from_file($payment_profile_partial_name, "Payment profile partial", $file_path, $theme_id);
							}
						}
					}
				}
			}
		}
		
		protected static function create_partial_from_file($name, $description, $file_path, $theme_id)
		{
			if (file_exists($file_path))
			{
				if ($theme_id == 0)
					$theme_id = null;

				$partial = Cms_Partial::create();
				$partial->name = $name;
				$partial->theme_id = $theme_id;
				$partial->description = $description;
				$partial->html_code = file_get_contents($file_path);
				$partial->save();
			}
		}
		
		/*
		 * Customer payment profiles support
		 */

		/**
		 * Finds and returns a customer payment profile for this payment method.
		 * @documentable
		 * @see Shop_CustomerPaymentProfile
		 * @param Shop_Customer $customer Specifies customer to find a profile for.
		 * @return Shop_CustomerPaymentProfile Returns the customer profile object. Returns NULL if the payment profile doesn't exist.
		 */
		public function find_customer_profile($customer)
		{
			if (!$customer)
				return null;
			
			return Shop_CustomerPaymentProfile::create()->where('customer_id=?', $customer->id)->where('payment_method_id=?', $this->id)->find();
		}
		
		/**
		 * Initializes a new empty customer payment profile. 
		 * This method should be used by payment methods internally. 
		 * @documentable
		 * @param Shop_Customer Specifies a customer object to initialize a profile for.
		 * @return Shop_CustomerPaymentProfile Returns the customer payment profile object.
		 */
		public function init_customer_profile($customer)
		{
			$obj = Shop_CustomerPaymentProfile::create();
			$obj->customer_id = $customer->id;
			$obj->payment_method_id = $this->id;

			return $obj;
		}
		
		/**
		 * Checks whether a customer profile for this payment method and a given customer exists.
		 * @documentable 
		 * @deprecated Use {@link Shop_PaymentMethod::profile_exists() profile_exists()} method.
		 * @param Shop_Customer $customer A customer object to find a profile for.
		 * @return boolean Returns TRUE if a profile exists. Returns FALSE otherwise.
		 */
		public function profle_exists($customer)
		{
			return $this->profile_exists($customer);
		}
		
		/**
		 * Checks whether a customer profile for this payment method and a given customer exists.
		 * @documentable 
		 * @param Shop_Customer $customer A customer object to find a profile for.
		 * @return boolean Returns TRUE if a profile exists. Returns FALSE otherwise.
		 */
		public function profile_exists($customer)
		{
			return $this->find_customer_profile($customer) ? true : false;
		}
		
		/**
		 * Checks whether the payment module supports payment profiles.
		 * @documentable
		 * @return boolean Returns TRUE if the module supports payment profiles. Returns FALSE otherwise.
		 */
		public function supports_payment_profiles()
		{
			return $this->get_paymenttype_object()->supports_payment_profiles();
		}
		
		/**
		 * Deletes a customer payment profile.
		 * The method deletes the payment profile from the database and from the payment gateway.
		 * @documentable
		 * @param Shop_Customer $customer Specifies a customer object to delete a profile for.
		 */
		public function delete_customer_profile($customer)
		{
			$payment_method_obj = $this->get_paymenttype_object();
			
			$profile = $this->find_customer_profile($customer);
			if (!$profile)
				throw new Phpr_ApplicationException('Customer profile not found');
			
			$payment_method_obj->delete_customer_profile($this, $customer, $profile);
			
			$profile->delete();
		}

		/**
		 * Allows to filter the payment method list before it is displayed on the checkout pages or in the backend.
		 * The event handler should accept a single parameter - the options array. The array contains the following fields:
		 * <ul>
		 *   <li><em>payment_methods</em> - a array of payment methods. Each element is the {@link Shop_PaymentMethod} object.</li>
		 *   <li><em>amount</em> - order total.</li>
		 *   <li><em>country_id</em> - {@link Shop_Country shipping country} identifier.</li>
		 *   <li><em>customer_group_id</em> - identifier of the {@link Shop_CustomerGroup customer group}.</li>
		 *   <li><em>ignore_customer_group_filter</em> - boolean, indicates if the customer group filter was ignored.</li>
		 *   <li><em>backend</em> - boolean, true when payment methods are being listed in the backend.</li>
		 *   <li><em>order_items</em> - a list of order items ({@link Shop_OrderItem} or {@link Shop_CartItem} objects, depending on the caller context).</li>
		 * </ul>
		 * The handler should return an updated payment methods array.
		 * 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onFilterPaymentMethods', $this, 'filter_payment_options');
		 * }
		 * 
		 * public function filter_payment_options($params)
		 * {
		 *   extract($params);
		 *   
		 *   if(count($order_items))
		 *   {
		 *     //remove a certain payment method if order contains products from a specific category
		 *     $hide_special = false;
		 *     foreach($order_items as $item)
		 *     {
		 *       $category_list = $item->product->category_list;
		 *       foreach($category_list as $category)
		 *       {
		 *         if($category->code == 'special')
		 *         {
		 *            $hide_special = true;
		 *            break;
		 *         }
		 *       }
		 *     }
		 *     if($hide_special)
		 *     {
		 *       $filtered_payment_methods = array();
		 *       foreach($payment_methods as $i => $method)
		 *       {
		 *         if($method->ls_api_code != 'special')
		 *           $filtered_payment_methods[$i] = $method;
		 *       }
		 *       return $filtered_payment_methods;
		 *     }
		 *   }
		 *   return $payment_methods;
		 * }
		 * </pre>
		 * @event shop:onFilterPaymentMethods
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param array $params Specifies the method parameters.
		 * @return array Returns updated list of shipping options.
		 */
		private function event_onFilterPaymentMethods($params) {}
			
		/**
		 * Allows to define new columns in the payment method model.
		 * The event handler should accept a single parameter - the payment method object. To add new columns to the payment method model, 
		 * call the {@link Db_ActiveRecord::define_column() define_column()} method of the payment method object. Before you add new columns to the model, 
		 * you should add them to the database (the <em>shop_payment_methods</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendPaymentMethodModel', $this, 'extend_payment_method_model');
		 *   Backend::$events->addEvent('shop:onExtendPaymentMethodForm', $this, 'extend_payment_method_form');
		 * }
		 * 
		 * public function extend_payment_method_model($payment_method)
		 * {
		 *   $payment_method->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_payment_method_form($payment_method, $context)
		 * {
		 *   $payment_method->add_form_field('x_extra_description')->tab('Custom');
		 * }
		 * </pre>
		 * @event shop:onExtendPaymentMethodModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendPaymentMethodForm
		 * @see shop:onGetPaymentMethodFieldOptions
		 * @see shop:onGetPaymentMethodFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables.
		 * @param Shop_PaymentMethod $payment_method Specifies the payment method object.
		 */
		private function event_onExtendPaymentMethodModel($payment_method) {}

		/**
		 * Allows to add new fields to the Create/Edit Payment Method form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendPaymentMethodModel} event. 
		 * To add new fields to the payment method form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * payment method object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendPaymentMethodModel', $this, 'extend_payment_method_model');
		 *   Backend::$events->addEvent('shop:onExtendPaymentMethodForm', $this, 'extend_payment_method_form');
		 * }
		 * 
		 * public function extend_payment_method_model($payment_method)
		 * {
		 *   $payment_method->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_payment_method_form($payment_method, $context)
		 * {
		 *   $payment_method->add_form_field('x_extra_description')->tab('Custom');
		 * }
		 * </pre>
		 * @event shop:onExtendPaymentMethodForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendPaymentMethodModel
		 * @see shop:onGetPaymentMethodFieldOptions
		 * @see shop:onGetPaymentMethodFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_PaymentMethod $payment_method Specifies the payment method object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendPaymentMethodForm($payment_method, $context) {}

		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendPaymentMethodForm} event.
		 * Usually you do not need to use this event for fields which represent 
		 * {@link http://lemonstand.com/docs/extending_models_with_related_columns data relations}. But if you want a standard 
		 * field (corresponding an integer-typed database column, for example), to be rendered as a drop-down list, you should 
		 * handle this event.
		 *
		 * The event handler should accept 2 parameters - the field name and a current field value. If the current
		 * field value is -1, the handler should return an array containing a list of options. If the current 
		 * field value is not -1, the handler should return a string (label), corresponding the value.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendPaymentMethodModel', $this, 'extend_payment_method_model');
		 *   Backend::$events->addEvent('shop:onExtendPaymentMethodForm', $this, 'extend_payment_method_form');
		 *   Backend::$events->addEvent('shop:onGetPaymentMethodFieldOptions', $this, 'get_payment_method_field_options');
		 * }
		 * 
		 * public function extend_payment_method_model($payment_method)
		 * {
		 *   $payment_method->define_column('x_color', 'Color');
		 * }
		 * 
		 * public function extend_payment_method_form($payment_method, $context)
		 * {
		 *   $payment_method->add_form_field('x_color')->tab('Custom')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_payment_method_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_color')
		 *   {
		 *     $options = array(
		 *       0 => 'Red',
		 *       1 => 'Green',
		 *       2 => 'Blue'
		 *     );
		 *     
		 *     if ($current_key_value == -1)
		 *       return $options;
		 *     
		 *     if (array_key_exists($current_key_value, $options))
		 *       return $options[$current_key_value];
		 *   }
		 * }
		 * </pre>
		 * @event shop:onGetPaymentMethodFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendPaymentMethodModel
		 * @see shop:onExtendPaymentMethodForm
		 * @see shop:onGetPaymentMethodFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetPaymentMethodFieldOptions($db_name, $field_value) {}

		/**
		 * Determines whether a custom radio button or checkbox list option is checked.
		 * This event should be handled if you added custom radio-button and or checkbox list fields with {@link shop:onExtendPaymentMethodForm} event.
		 * @event shop:onGetPaymentMethodFieldState
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendPaymentMethodModel
		 * @see shop:onExtendPaymentMethodForm
		 * @see shop:onGetPaymentMethodFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @param Shop_PaymentMethod $payment_method Specifies the payment method object.
		 * @return boolean Returns TRUE if the field is checked. Returns FALSE otherwise.
		 */
		private function event_onGetPaymentMethodFieldState($db_name, $field_value, $payment_method) {}
	}

?>