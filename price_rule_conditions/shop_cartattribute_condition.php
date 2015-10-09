<?

	class Shop_CartAttribute_Condition extends Shop_ModelAttributesConditionBase
	{
		protected $model_class = 'Shop_CartAttributeContainer';
		
		public function get_condition_type()
		{
			return Shop_RuleConditionBase::type_cart_attribute;
		}
		
		public function get_grouping_title()
		{
			return 'Shopping cart attribute';
		}
		
		/**
		 * Returns a condition title for displaying in the condition settings form
		 */
		public function get_title($host_obj)
		{
			return "Shopping cart attribute";
		}
		
		public function get_value_dropdown_options($host_obj, $controller)
		{
			$attribute = $host_obj->subcondition;
			
			if ($attribute == 'shipping_country')
			{
				$records = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 order by name');
				$result = array();
				foreach ($records as $country)
					$result[$country->id] = $country->name;

				return $result;
			}
			
			if ($attribute == 'shipping_state')
			{
				$records = Db_DbHelper::objectArray('select shop_states.id, shop_countries.name as country_name, 
				shop_states.name as state_name
				from shop_countries, shop_states 
				where shop_states.country_id=shop_countries.id
				order by shop_countries.name, shop_states.name');
				
				$result = array();
				foreach ($records as $state)
					$result[$state->id] = $state->country_name.'/'.$state->state_name;
				
				return $result;
			}

			return parent::get_value_dropdown_options($host_obj, $controller);
		}
		
		protected function get_reference_visible_columns($model, $model_columns)
		{
			if ($model instanceof Shop_CountryState)
				return array('country_state_name');
			
			return parent::get_reference_visible_columns($model, $model_columns);
		}
		
		public function get_reference_search_fields($model, $columns)
		{
			if ($model instanceof Shop_CountryState)
				return array("concat(shop_countries.name, '/', shop_states.name)");
			
			return $columns;
		}
		
		/**
		 * Checks whether the condition is TRUE for specified parameters
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function is_true(&$params, $host_obj)
		{
			$attribute = $host_obj->subcondition;

			if ($attribute == 'shipping_method')
			{
				if (!array_key_exists('shipping_method', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the shipping_method element is not found in the condition parameters.');

				$shipping_method = $params['shipping_method'];
				return parent::eval_is_true(null, $host_obj, $shipping_method);
			}

			if ($attribute == 'payment_method')
			{
				if (!array_key_exists('payment_method', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the payment_method element is not found in the condition parameters.');

				$payment_method = $params['payment_method'];
				return parent::eval_is_true(null, $host_obj, $payment_method);
			}

			if ($attribute == 'shipping_country')
			{
				if (!array_key_exists('shipping_address', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the shipping_address element is not found in the condition parameters.');

				$shipping_address = $params['shipping_address'];
				
				$test_country = Shop_Country::create();
				$test_country->id = $shipping_address->country;
				
				return parent::eval_is_true(null, $host_obj, $test_country);
			}
			
			if ($attribute == 'shipping_state')
			{
				if (!array_key_exists('shipping_address', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the shipping_address element is not found in the condition parameters.');

				$shipping_address = $params['shipping_address'];
				
				$test_state = Shop_Country::create();
				$test_state->id = $shipping_address->state;
				
				return parent::eval_is_true(null, $host_obj, $test_state);
			}

			if ($attribute == 'shipping_zip')
			{
				if (!array_key_exists('shipping_address', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the shipping_address element is not found in the condition parameters.');

				$shipping_address = $params['shipping_address'];

				return parent::eval_is_true(null, $host_obj, $shipping_address->zip);
			}

			if ($attribute == 'subtotal')
			{
				if (!array_key_exists('subtotal', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the subtotal element is not found in the condition parameters.');

				return parent::eval_is_true(null, $host_obj, $params['subtotal']);
			}

			if ($attribute == 'total_quantity')
			{
				if (!array_key_exists('cart_items', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the cart_items element is not found in the condition parameters.');

				$quantity = 0;
				foreach ($params['cart_items'] as $cart_item)
					$quantity += $cart_item->quantity;

				return parent::eval_is_true(null, $host_obj, $quantity);
			}
			
			if ($attribute == 'total_weight')
			{
				if (!array_key_exists('cart_items', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the cart_items element is not found in the condition parameters.');

				$weight = 0;
				foreach ($params['cart_items'] as $cart_item)
					$weight += $cart_item->total_weight();

				return parent::eval_is_true(null, $host_obj, $weight);
			}
			
			if ($attribute == 'total_discount')
			{
				if (!array_key_exists('cart_discount', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart attribute condition: the cart_discount element is not found in the condition parameters.');

				return parent::eval_is_true(null, $host_obj, $params['cart_discount']);
			}

			return false;
		}
	}
	
	/**
	 * The discount engine uses this class for building the condition management user interface only.
	 */
	class Shop_CartAttributeContainer extends Db_ActiveRecord
	{
		public $table_name = 'shop_customer_cart_items';

		public $custom_columns = array(
			'subtotal'=>db_float,
			'total_quantity'=>db_number,
			'total_discount'=>db_float,
			'total_weight'=>db_float,
			'shipping_zip'=>db_text,
			'shipping_method_id'=>db_number,
			'payment_method_id'=>db_number,
			'shipping_country_id'=>db_number,
			'shipping_state_id'=>db_number
		);
		
		public $belongs_to = array(
			'shipping_country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'shipping_country_id'),
			'shipping_state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'shipping_state_id'),
			'shipping_method'=>array('class_name'=>'Shop_ShippingOption', 'foreign_key'=>'shipping_method_id'),
			'payment_method'=>array('class_name'=>'Shop_PaymentMethod', 'foreign_key'=>'payment_method_id')
		);
		
		public function define_columns($context = null)
		{
			$this->define_column('subtotal', 'Subtotal');
			$this->define_column('total_quantity', 'Total quantity');
			$this->define_column('total_weight', 'Total weight');
			$this->define_column('total_discount', 'Total cart discount');
			$this->define_column('shipping_zip', 'Shipping ZIP/postal code');
			$this->define_relation_column('shipping_country', 'shipping_country', 'Shipping Country ', db_varchar, '@name');
			$this->define_relation_column('shipping_state', 'shipping_state', 'Shipping State ', db_varchar, '@name');
			$this->define_relation_column('shipping_method', 'shipping_method', 'Shipping Method ', db_varchar, '@name');
			$this->define_relation_column('payment_method', 'payment_method', 'Payment Method ', db_varchar, '@name');
		}
		
		public function define_form_fields($context = null)
		{
			$this->add_form_field('shipping_country');
			$this->add_form_field('shipping_state');
			$this->add_form_field('shipping_method');
			$this->add_form_field('payment_method');
		}
		
		public function get_condition_attributes()
		{
			$fields = array_keys($this->custom_columns);
			$fields[] = 'shipping_country';
			$fields[] = 'shipping_state';
			$fields[] = 'shipping_method';
			$fields[] = 'payment_method';

			$result = array();
			$definitions = $this->get_column_definitions();
			foreach ($fields as $field)
			{
				if (isset($definitions[$field]))
					$result[$field] = $definitions[$field]->displayName;
			}

			return $result;
		}
	}


?>