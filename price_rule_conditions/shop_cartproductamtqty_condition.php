<?

	class Shop_CartProductAmtQty_Condition extends Shop_RuleIfCondition
	{
		protected $operators = array(
			'is'=>'is',
			'is_not'=>'is not',
			'equals_or_greater'=>'equals or greater than',
			'equals_or_less'=>'equals or less than',
			'greater'=>'greater than',
			'less'=>'less than'
		);
		
		public function get_condition_type()
		{
			return Shop_RuleConditionBase::type_cart_root;
		}
		
		public function get_allowed_subtypes()
		{
			return array(Shop_RuleConditionBase::type_product, Shop_RuleConditionBase::type_cart_product_attribute);
		}

		/**
		 * Returns a condition name for displaying in the condition selection drop-down menu
		 */
		public function get_name()
		{
			return "Cart items total quantity or total amount";
		}
		
		public function get_title($host_obj)
		{
			return "Cart item quantity or total amount condition";
		}
		
		public function get_join_text($parameters_host)
		{
			return $parameters_host->condition_type == 0 ? 'AND' : 'OR';
		}
		
		public function get_text($parameters_host)
		{
			if ($parameters_host->parameter == 'quantity')
				$result = 'Total quantity ';
			else
				$result = 'Total amount ';
				
			if (array_key_exists($parameters_host->operator, $this->operators))
				$result .= $this->operators[$parameters_host->operator].' ';
			else
				$result .= ' is ';
				
			$result .= strlen($parameters_host->value) ? $parameters_host->value : 0;
			$result .= ' for cart items matching';
			
			if ($parameters_host->condition_type == 0)
				$result .= ' ALL ';
			else
				$result .= ' ANY ';
				
			$result .= ' of subconditions';
			
			return $result;
		}

		public function build_config_form($host_obj)
		{
			$host_obj->add_field('parameter', 'Parameter', 'left')->renderAs(frm_dropdown);
			$host_obj->add_field('operator', 'Operator', 'right')->renderAs(frm_dropdown);
			$host_obj->add_field('value', 'Value', 'full', db_float)->validation()->required("Please specify value");

			$host_obj->add_field('condition_type', 'Condition type')->renderAs(frm_dropdown);
		}
		
		public function init_fields_data($host_obj)
		{
			$host_obj->parameter = 'quantity';
			$host_obj->operator = 'is';
			$host_obj->condition_type = 0;
			$host_obj->value = 0;
		}

		public function get_operator_options($host_obj)
		{
			return $this->operators;
		}

		public function get_parameter_options($host_obj)
		{
			$options = array(
				'quantity'=>'Total quantity',
				'amount'=>'Total amount'
			);
			
			return $options;
		}
		
		public function get_condition_type_options($host_obj)
		{
			$options = array(
				'0'=>'Cart items should match ALL subconditions',
				'1'=>'Cart items should match ANY subconditions'
			);
			
			return $options;
		}
		
		/**
		 * Checks whether the condition is TRUE for specified parameters
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function is_true(&$params, $host_obj)
		{
			if (!array_key_exists('cart_items', $params))
				throw new Phpr_ApplicationException('Error evaluating the cart items total quantity or total amount condition: the cart_items element is not found in the condition parameters.');

			$required_condition_value = true;
			
			$total_quantity = 0;
			$total_amount = 0;

			foreach ($params['cart_items'] as $item)
			{
				$params['product'] = $item->product;
				$params['item'] = $item;
				
				$item_current_price = $item->get_de_cache_item('current_price');
				if ($item_current_price === false)
					$item_current_price = $item->set_de_cache_item('current_price', $item->single_price_no_tax() - $item->get_sale_reduction());
				
				$params['current_price'] = $item_current_price;
				$params['quantity_in_cart'] = $item->quantity;

				$item_row_total = $item->get_de_cache_item('row_total');
				if ($item_row_total === false)
					$item_row_total = $item->set_de_cache_item('row_total', $item->total_price_no_tax());
				
				$row_total = $params['row_total'] = $item_row_total;
				
				if (method_exists($item, 'get_om_record'))
					$params['om_record'] = $item->get_om_record();

				$subconditions_result = null;
				if (!$host_obj->children->count)
					$subconditions_result = true;

				foreach ($host_obj->children as $subcondition)
				{
					$subcondition_result = $subcondition->is_true($params)  ? true : false;
					
					if ($host_obj->condition_type == 0) // ALL
					{
						if ($subcondition_result !== $required_condition_value)
							continue 2;
					} else  // ANY
					{
						if ($subcondition_result === $required_condition_value)
						{
							$subconditions_result = true;
							break;
						}
					}
					
					if ($subcondition_result === $required_condition_value)
					{
						if ($subconditions_result !== null)
							$subconditions_result = $subconditions_result && true;
						else
							$subconditions_result = true;
					} else
						$subconditions_result = false;
				}
				
				if ($subconditions_result)
				{
					$total_quantity += $item->quantity;
					$total_amount += $row_total;
				}
			}

			$test_value = $host_obj->parameter == 'quantity' ? $total_quantity : $total_amount;
			$condition_value = $host_obj->value;
			$operator = $host_obj->operator;

			if ($operator == 'is')
				return $test_value == $condition_value;
				
			if ($operator == 'is_not')
				return $test_value != $condition_value;

			if ($operator == 'equals_or_greater')
				return $test_value >= $condition_value;
				
			if ($operator == 'equals_or_less')
				return $test_value <= $condition_value;

			if ($operator == 'greater')
				return $test_value > $condition_value;

			if ($operator == 'less')
				return $test_value < $condition_value;

			return false;
		}
	}