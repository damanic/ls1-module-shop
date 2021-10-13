<?

	class Shop_CartBuyMGetNFree_Action extends Shop_CartRuleActionBase
	{
		public function get_action_type()
		{
			return self::type_cart;
		}
		
		public function get_name()
		{
			return "Buy M get N free";
		}
		
		public function build_config_form($host_obj)
		{
			$host_obj->add_field('m_value', 'M value (Buy)', 'left', db_number)->comment('How many units of the product should be added to the cart in order to activate the action. ', 'above')->validation()->required('Please specify the M value');
			$host_obj->add_field('n_value', 'N value (Get)', 'right', db_number)->comment('How many units of the product to discount in case if M products has been added to the shopping cart. ', 'above')->validation()->required('Please specify the N value');
			$host_obj->add_field('multiples', 'Work with multiples', 'full', db_float)->comment('Enable this feature if you want M*2 units in the cart to discount N*2 units.', 'above')->renderAs(frm_checkbox);
		}
		
		/**
		 * This method should return true if the action evaluates a 
		 * discount value per each product in the shopping cart
		 */
		public function is_per_product_action()
		{
			return true;
		}
		
		/**
		 * Evaluates the discount amount. This method should be implemented only for cart-type actions.
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 * @param array $item_discount_map A list of cart item identifiers and corresponding discounts.
		 * @param array $item_discount_tax_incl_map A list of cart item identifiers and corresponding discounts with tax included.
		 * @param Shop_RuleConditionBase $product_conditions Specifies product conditions to filter the products the discount should be applied to
		 * @return float Returns discount value (for cart-wide actions), or a sum of discounts applied to products (for per-product actions) without tax applied
		 */
		public function eval_discount(&$params, $host_obj, &$item_discount_map, &$item_discount_tax_incl_map, $product_conditions)
		{
			if (!array_key_exists('cart_items', $params))
				throw new Phpr_ApplicationException('Error applying the "Buy M get N free" price rule action: the cart_items element is not found in the action parameters.');

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (isset($params['no_tax_include']) && $params['no_tax_include'])
				$include_tax = false;

			/*
			 * This parameter is used only for the manual order discount setting feature
			 */
			$cart_items = $params['cart_items'];
			$total_discount = 0;
			$total_discount_incl_tax = 0;

			foreach ($cart_items as $item)
			{
				$original_product_price = $item->total_single_price();
				$current_product_price = max($original_product_price - $item_discount_map[$item->key], 0);
					
				$rule_params = array();
				$rule_params['product'] = $item->product;
				$rule_params['item'] = $item;
				$rule_params['current_price'] = $item->single_price_no_tax(false) - $item->get_sale_reduction() ;
				$rule_params['quantity_in_cart'] = $item->quantity;
				$rule_params['row_total'] = $item->total_price_no_tax();

				$rule_params['item_discount'] = isset($item_discount_map[$item->key]) ? $item_discount_map[$item->key] : 0;

				if ($this->is_active_for_product($item->product, $product_conditions, $current_product_price, $rule_params))
				{
					$m_value = $host_obj->m_value;
					$n_value = $host_obj->n_value;
					$use_multiples = $host_obj->multiples;

					$total_discount_incl_tax = $discount_value = 0;

					if (!$use_multiples)
					{
						
						if ($item->quantity >= $m_value) // if ($item->quantity >= ($m_value+1)) - if we need to activate the action on the M+1th items
							$total_discount_incl_tax = $discount_value = $current_product_price*$n_value/$item->quantity;
					} else
					{
						$factor = floor($item->quantity/$m_value); // floor(($item->quantity-1)/$m_value); - if we need to activate the action on the M+1th items
						$total_discount_incl_tax = $discount_value = $current_product_price*$n_value*$factor/$item->quantity;
					}

					if ($include_tax)
					{
						/*
						 * If taxes are included to discounts, we need to extract the real discount value from the discount total
						 */

						/* This operation is not needed for this action */
						/* $discount_value = Shop_TaxClass::get_subtotal($item->get_tax_class_id(), $discount_value); */
					}

					if ($discount_value > $current_product_price)
						$total_discount_incl_tax = $discount_value = $current_product_price;
						
					if ($include_tax)
						$total_discount_incl_tax = Shop_TaxClass::get_total_tax($item->get_tax_class_id(), $discount_value) + $discount_value;

					$total_discount += $discount_value*$item->quantity;
					$item_discount_map[$item->key] += $discount_value;
					$item_discount_tax_incl_map[$item->key] += $total_discount_incl_tax;
				}
			}

			$applied = $total_discount ? true : false;
			$this->set_applied($applied);
			return $total_discount;
		}
	}

?>