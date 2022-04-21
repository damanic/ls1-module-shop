<?

	class Shop_CartItemAttribute_Condition extends Shop_ModelAttributesConditionBase
	{
		protected $model_class = 'Shop_CartItemAttributeContainer';
		
		public function get_condition_type()
		{
			return Shop_RuleConditionBase::type_cart_product_attribute;
		}
		
		public function get_grouping_title()
		{
			return 'Shopping cart item attribute';
		}
		
		public function get_title($host_obj)
		{
			return "Shopping cart item attribute";
		}
		
		public function get_value_control_type($host_obj)
		{
			$attribute = $host_obj->subcondition;

            if (in_array($attribute, array('discounted_bundle_item', 'bundle_item')))
				return 'dropdown';
			
			return parent::get_value_control_type($host_obj);
		}
		
		public function get_value_dropdown_options($host_obj, $controller)
		{
			$attribute = $host_obj->subcondition;

            if (in_array($attribute, array('discounted_bundle_item', 'bundle_item'))){
                $discounted = ($attribute == 'discounted_bundle_item') ? 'discounted' : null;
                return array('false'=>'FALSE (product IS NOT a '.$discounted.' bundle item)', 'true'=>'TRUE (product IS a '.$discounted.' bundle item)');
            }
			
			return parent::get_value_dropdown_options($host_obj, $controller);
		}
		
		public function prepare_reference_list_info($host_obj)
		{
			if (!is_null($this->reference_info))
				return $this->reference_info;
				
			$attribute = $host_obj->subcondition;

            if (in_array($attribute, array('discounted_bundle_item', 'bundle_item')))
				return null;
			
			return parent::prepare_reference_list_info($host_obj);
		}
		
		public function get_custom_text_value($parameters_host)
		{
			$attribute = $parameters_host->subcondition;
            if (in_array($attribute, array('discounted_bundle_item', 'bundle_item')))
				return $parameters_host->value == 'true' ? 'TRUE' : 'FALSE';
			
			return false;
		}
		
		public function get_operator_options($host_obj = null)
		{
			$options = array();
			$attribute = $host_obj->subcondition;

			$current_operator_value = $host_obj->operator;

			$model = $this->get_model_obj();
			$definitions = $model->get_column_definitions();
			
			if (in_array($attribute, array('discounted_bundle_item', 'bundle_item')))
				return array('is'=>'is');

			return parent::get_operator_options($host_obj);
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

			if ($attribute == 'price')
			{
				if (!array_key_exists('current_price', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart item attribute condition: the current_price element is not found in the condition parameters.');

				return parent::eval_is_true(null, $host_obj, $params['current_price']);
			}
			
			if ($attribute == 'row_total')
			{
				if (!array_key_exists('row_total', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart item attribute condition: the row_total element is not found in the condition parameters.');

				return parent::eval_is_true(null, $host_obj, $params['row_total']);
			}

			if ($attribute == 'quantity')
			{
				if (!array_key_exists('quantity_in_cart', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart item attribute condition: the quantity_in_cart element is not found in the condition parameters.');

				return parent::eval_is_true(null, $host_obj, $params['quantity_in_cart']);
			}
			
			if ($attribute == 'discount')
			{
				if (!array_key_exists('item_discount', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart item attribute condition: the item_discount element is not found in the condition parameters.');

				return parent::eval_is_true(null, $host_obj, $params['item_discount']);
			}
			
			if ($attribute == 'bundle_item')
			{
				if (!array_key_exists('item', $params))
					throw new Phpr_ApplicationException('Error evaluating the cart item attribute condition: the item element is not found in the condition parameters.');

				$is_bundle_item = $params['item']->is_bundle_item();

				if ($host_obj->value == 'false')
					return !$is_bundle_item;
				else
					return $is_bundle_item;
			}

            if ($attribute == 'discounted_bundle_item')
            {
                if (!array_key_exists('item', $params))
                    throw new Phpr_ApplicationException('Error evaluating the cart item attribute condition: the item element is not found in the condition parameters.');

                $is_discounted_bundle_item = false;
                if($params['item']->is_bundle_item()){
                    if($params['item']->get_bundle_item_discount()){
                        $is_discounted_bundle_item = true;
                    }
                }
                if ($host_obj->value == 'false')
                    return !$is_discounted_bundle_item;
                else
                    return $is_discounted_bundle_item;
            }

			return false;
		}
	}
	
	/**
	 * The discount engine uses this class for building the condition management user interface only.
	 */
	class Shop_CartItemAttributeContainer extends Db_ActiveRecord
	{
		public $table_name = 'shop_customer_cart_items';

		public $custom_columns = array(
			'price'=>db_float,
			'quantity'=>db_number,
			'row_total'=>db_float,
			'discount'=>db_float,
			'bundle_item'=>db_bool,
            'discounted_bundle_item'=>db_bool
		);
		
		public function define_columns($context = null)
		{
			$this->define_column('price', 'Price in the shopping cart');
			$this->define_column('quantity', 'Quantity in the shopping cart');
			$this->define_column('row_total', 'Row total in the shopping cart');
			$this->define_column('discount', 'Total line item discount');
			$this->define_column('bundle_item', 'Product is a bundle item');
            $this->define_column('discounted_bundle_item', 'Product is a discounted bundle item');
		}
		
		public function get_condition_attributes()
		{
			$fields = array_keys($this->custom_columns);

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