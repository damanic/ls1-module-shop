<?

	class Shop_CartFixed_Action extends Shop_CartRuleActionBase
	{
		public function get_action_type()
		{
			return self::type_cart;
		}
		
		public function get_name()
		{
			return "Discount the shopping cart subtotal by fixed amount";
		}
		
		public function build_config_form($host_obj)
		{
			$host_obj->add_field('discount_amount', 'Discount amount', 'full', db_float)->comment('Please specify an amount  to subtract from the shopping cart subtotal. ', 'above')->validation()->required('Please specify discount amount');
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
				throw new Phpr_ApplicationException('Error applying the "Discount the shopping cart subtotal by fixed amount" price rule action: the cart_items element is not found in the action parameters.');

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (isset($params['no_tax_include']) && $params['no_tax_include'])
				$include_tax = false;

			/*
			 * This parameter is used only for the manual order discount setting feature
			 */
			$cart_items = $params['cart_items'];
			$total_discount = 0;
			$total_discount_incl_tax = 0;
			$remainder = $host_obj->discount_amount;

			foreach ($cart_items as $item)
			{
				$original_product_price = $item->total_single_price();
				$current_product_price = max($original_product_price - $item_discount_map[$item->key], 0);

				$rule_params['product'] = $item->product;
				$rule_params['item'] = $item;
				$rule_params['current_price'] = $item->single_price_no_tax(false) - $item->discount(false);
				$rule_params['quantity_in_cart'] = $item->quantity;
				$rule_params['row_total'] = $item->total_price_no_tax();

				if ($this->is_active_for_product($item->product, $product_conditions, $current_product_price, $rule_params))
				{
					$discount_value = $remainder/$item->quantity;

					if ($include_tax)
					{
						/*
						 * If taxes are included to discounts, we need to extract the real discount value from the discount total
						 */
						$discount_value = Shop_TaxClass::get_subtotal($item->product->tax_class_id, $discount_value);
					}

					if ($discount_value > $current_product_price)
						$total_discount_incl_tax = $discount_value = $current_product_price;

					if ($include_tax)
						$total_discount_incl_tax = Shop_TaxClass::get_total_tax($item->product->tax_class_id, $discount_value) + $discount_value;

					$total_discount += $discount_value*$item->quantity;
					$item_discount_map[$item->key] += $discount_value;
					$item_discount_tax_incl_map[$item->key] += $total_discount_incl_tax;

					if (!$include_tax)
						$remainder -= $discount_value*$item->quantity;
					else
						$remainder -= $total_discount_incl_tax*$item->quantity;
				}
				
				if ($remainder <= 0)
					break;
			}

			return $total_discount;
		}
	}

?>