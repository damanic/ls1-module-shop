<?

/**
 * Class Shop_ProductProperty_Condition
 * Matches against product fields
 */
	class Shop_ProductAttribute_Condition extends Shop_ModelAttributesConditionBase
	{
		protected $model_class = 'shop_product';
		
		protected function get_condition_text_prefix($parameters_host, $attributes)
		{
			if ($parameters_host->subcondition != 'product')
				return 'Product.'.$attributes[$parameters_host->subcondition];

 			return 'Product';
		}

		public function get_grouping_title()
		{
			return 'Product or product attribute';
		}

		
		public function get_title($host_obj)
		{
			return "Product attribute condition";
		}

		public function get_value_control_type($host_obj)
		{
			$attribute = $host_obj->subcondition;
			$operator = $host_obj->operator;
			$multi_value_attributes = array('product');
			$dropdown_attributes = array('on_sale', 'bulky_shipping_item');

			if (in_array($attribute , $multi_value_attributes)) {
				return 'multi_value';
			}

			if (in_array($attribute , $dropdown_attributes)) {
				return 'dropdown';
			}
			
			return parent::get_value_control_type($host_obj);
		}
		
		public function get_custom_text_value($parameters_host)
		{
			$attribute = $parameters_host->subcondition;
			$dropdown_attributes = array('on_sale', 'bulky_shipping_item');

			if (in_array($attribute , $dropdown_attributes)) {
				return $parameters_host->value == 'true' ? 'TRUE' : 'FALSE';
			}
			
			return false;
		}

		public function get_operator_options($host_obj = null)
		{
			$options = array('none'=>'Unknown attribute selected');
			$attribute = $host_obj->subcondition;
			$current_operator_value = $host_obj->operator;

			$model = $this->get_model_obj();
			$definitions = $model->get_column_definitions();
			$is_attributes = array('on_sale', 'bulky_shipping_item');
			$one_of_attributes = array('product');
			$custom_option_attributes = array_merge($is_attributes, $one_of_attributes);

			if (!isset($definitions[$attribute]) || in_array($attribute,$custom_option_attributes)) {
				if (in_array($attribute, $one_of_attributes)) {
					$options = array(
						'one_of'=>'is one of',
						'not_one_of'=>'is not one of'
					);
				} elseif (in_array($attribute , $is_attributes)) {
					$options = array(
						'is'=>'is'
					);
				}
				return $options;
			}
			else
				return parent::get_operator_options($host_obj);
		}

		public function get_condition_type()
		{
			return Shop_RuleConditionBase::type_product;
		}

		public function get_value_dropdown_options( $host_obj, $controller ) {
			$attribute = $host_obj->subcondition;

			if ( $attribute == 'product' ) {
				$products = Db_DbHelper::objectArray( 'select id, name from shop_products where grouped is null and (disable_completely is null or disable_completely=0) order by name' );

				$result = array();
				foreach ( $products as $product ) {
					$result[$product->id] = h( $product->name );
				}

				return $result;
			} elseif ( $attribute == 'on_sale' ) {
				return array( 'false' => 'FALSE (there are NO Catalog Price Rules defined for the product)', 'true' => 'TRUE (there are Catalog Price Rules defined for the product)' );
			} else {
				if ( $attribute == 'bulky_shipping_item' ) {
					return array( 'false' => 'FALSE', 'true' => 'TRUE' );
				}
			}

			return parent::get_value_dropdown_options( $host_obj, $controller );
		}
		
		public function prepare_reference_list_info($host_obj)
		{
			if (!is_null($this->reference_info))
				return $this->reference_info;
				
			$attribute = $host_obj->subcondition;
			$exclude_attributes = array('on_sale', 'bulky_shipping_item');

			if ($attribute == 'product') {
				$this->reference_info = array();
				$this->reference_info['reference_model'] = new Shop_Product();
				$this->reference_info['columns'] = array('name');
				return $this->reference_info = (object)$this->reference_info;
			} elseif (in_array($attribute,$exclude_attributes)) {
				return null;
			}
			
			return parent::prepare_reference_list_info($host_obj);
		}
		
		protected function list_model_attributes()
		{
			if ($this->model_attributes)
				return $this->model_attributes;
			
			$attributes = $this->get_model_obj()->get_condition_attributes();
			$attributes['product'] = 'Product';
			$attributes['on_sale'] = 'Product is on Sale';
			asort($attributes);

			return $this->model_attributes = $attributes;
		} 

		public function prepare_filter_model($host_obj, $model, $options)
		{
			if (get_class($model) == 'Shop_Product')
				$model->where('grouped is null and (disable_completely is null or disable_completely=0)');

			return $model;
		}

		/**
		 * Checks whether the condition is TRUE for specified parameters
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function is_true(&$params, $host_obj)
		{
			if (!array_key_exists('product', $params))
				throw new Phpr_ApplicationException('Error evaluating the product attribute condition: the product element is not found in the condition parameters.');
				
			$attribute = $host_obj->subcondition;

			$extended_attributes = array(
				'current_price',
				'product',
				'categories',
				'on_sale',
				'manufacturer_link',
				'bulky_shipping_item'
			);

			if (!in_array($attribute, $extended_attributes))
			{
				/*
				 * If om_record (Option Matrix record) key exists in the parameters, try to load the attribute value from it,
				 * instead of loading it from the product.
				 */
				$attribute_value = '__no_value_provided__';
				if (
						isset($params['om_record']) && 
						$params['om_record'] instanceof Shop_OptionMatrixRecord 
						&& $params['om_record']->is_property_supported($attribute) === true)
					{
						if ($attribute == 'price')
							$attribute = 'base_price'; // Condition attribute 'price' actually refers to base price.
						
						$attribute_value = $params['product']->om($attribute, $params['om_record']);
					}

				return parent::eval_is_true($params['product'], $host_obj, $attribute_value);
			}

			if($attribute == 'bulky_shipping_item'){
				if($params['product']->bulky_shipping_item){
					return true;
				}
				return false;
			}

			if ($attribute == 'on_sale')
			{
				$om_record = (isset($params['om_record']) && $params['om_record'] instanceof Shop_OptionMatrixRecord) ? $params['om_record'] : null;
				$test_object = $om_record ? $om_record : $params['product'];

				
				if($test_object->on_sale && !Shop_Product::is_sale_price_or_discount_invalid($test_object->sale_price_or_discount) && strlen($test_object->sale_price_or_discount))
				{
					if($host_obj->value == 'false')
						return false;
					else
						return true;
				}

				$has_price_rules = false;
				if (strlen($test_object->price_rules_compiled))
				{
					try
					{
						$price_rules = unserialize($test_object->price_rules_compiled);
						if ($price_rules)
							$has_price_rules = true;
					} catch (Exception $ex) {}
				}

				if ($host_obj->value == 'false')
					return !$has_price_rules;
				else
					return $has_price_rules;
			}

			if ($attribute == 'manufacturer_link')
			{
				return parent::eval_is_true($params['product'], $host_obj, $params['product']->manufacturer);
			}
				
			if ($attribute == 'current_price')
			{
				if (!array_key_exists('current_price', $params))
					throw new Phpr_ApplicationException('Error evaluating the product attribute condition: the current_price element is not found in the condition parameters.');

				$current_price = $params['current_price'];
				return parent::eval_is_true($params['product'], $host_obj, $current_price);
			}
			
			if ($attribute == 'categories')
			{
				return parent::eval_is_true($params['product'], $host_obj, $params['product']->list_category_ids());
			}

			if ($attribute == 'product')
			{
				$test_product = new Shop_Product(null, array('no_column_init'=>true, 'no_validation'=>true));
				$test_product->id = $params['product']->grouped ? $params['product']->product_id : $params['product']->id;

				return parent::eval_is_true($params['product'], $host_obj, $test_product);
			}

			return false;
		}
	}

?>