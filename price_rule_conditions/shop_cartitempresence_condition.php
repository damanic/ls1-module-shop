<?

	class Shop_CartItemPresence_Condition extends Shop_RuleIfCondition
	{
		public function get_condition_type()
		{
			return Shop_RuleConditionBase::type_cart_root;
		}
		
		public function get_allowed_subtypes()
		{
			return array(Shop_RuleConditionBase::type_product, Shop_RuleConditionBase::type_cart_product_attribute);
		}

		public function get_title($host_obj)
		{
			return "Shopping cart item presence condition";
		}
		
		/**
		 * Returns a condition name for displaying in the condition selection drop-down menu
		 */
		public function get_name()
		{
			return "Item is present/not present in the shopping cart";
		}
		
		public function get_join_text($parameters_host)
		{
			return $parameters_host->condition_type == 0 ? 'AND' : 'OR';
		}
		
		public function get_text($parameters_host)
		{
			$result = 'Item with ';

			if ($parameters_host->condition_type == 0)
				$result .= ' ALL ';
			else
				$result .= ' ANY ';

			$result .= ' of subconditions ';
			
			if ($parameters_host->presence == 'found')
				$result .= 'SHOULD BE';
			else
				$result .= 'SHOULD NOT BE';
				
			$result .= ' presented in the shopping cart';
				
			return $result;
		}

		public function build_config_form($host_obj)
		{
			$host_obj->add_field('presence', 'Presence')->renderAs(frm_dropdown);

			$host_obj->add_field('condition_type', 'Condition type')->renderAs(frm_dropdown);
		}
		
		public function init_fields_data($host_obj)
		{
			$host_obj->presence = 'found';
			$host_obj->condition_type = 0;
		}

		public function get_presence_options($host_obj)
		{
			return array(
				'found'=>'Item is FOUND in the shopping cart',
				'not_found'=>'Item is NOT FOUND in the shopping cart',
			);
		}

		public function get_condition_type_options($host_obj)
		{
			$options = array(
				'0'=>'ALL of subconditions should be TRUE',
				'1'=>'ANY of subconditions should be TRUE'
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
				throw new Phpr_ApplicationException('Error evaluating the cart item presence condition: the cart_items element is not found in the condition parameters.');

			$required_condition_value = true;
			$matches_found = null;
			$local_match = false;

			foreach ($params['cart_items'] as $item)
			{
				$params['product'] = $item->product;
				$params['item'] = $item;
				$params['current_price'] = $item->single_price_no_tax(false) - $item->get_sale_reduction();
				$params['quantity_in_cart'] = $item->quantity;
				$params['row_total'] = $item->total_price_no_tax();
				
				if (method_exists($item, 'get_om_record'))
					$params['om_record'] = $item->get_om_record();

				$subconditions_result = null;
				$result_found = false;

				foreach ($host_obj->children as $subcondition)
				{
					$subcondition_result = $subcondition->is_true($params)  ? true : false;

					if ($host_obj->condition_type == 0) // ALL
					{
						if ($subcondition_result !== $required_condition_value)
						{
							$subconditions_result = false;
							break;
						} 
					} else  // ANY
					{
						if ($subcondition_result === $required_condition_value && $host_obj->presence == 'found')
							return true;
					}

					if ($subcondition_result === $required_condition_value)
					{
						$local_match = true;

						if ($subconditions_result !== null)
							$subconditions_result = $subconditions_result && true;
						else
							$subconditions_result = true;
					} else
						$subconditions_result = false;
				}

				if ($host_obj->condition_type == 0 && $host_obj->presence == 'found' && $subconditions_result)
					return true;

				if ($subconditions_result)
					$matches_found = true;
			}

			if ($host_obj->condition_type == 0 && $host_obj->presence == 'not_found' && !$matches_found)
				return true;

			if ($host_obj->condition_type == 1 && $host_obj->presence == 'not_found' && !$local_match)
				return true;

			return false;
		}
	}