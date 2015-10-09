<?

	class Shop_CartProductFreeShipping_Action extends Shop_CartRuleActionBase
	{
		public function get_action_type()
		{
			return self::type_cart;
		}
		
		public function get_name()
		{
			return "Apply free shipping to the cart products";
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
				throw new Phpr_ApplicationException('Apply free shipping to the cart products.');

			$cart_items = $params['cart_items'];

			foreach ($cart_items as $item)
			{
				$original_product_price = $item->total_single_price();
				$current_product_price = max($original_product_price - $item_discount_map[$item->key], 0);

				$rule_params = array();
				$rule_params['product'] = $item->product;
				$rule_params['item'] = $item;
				$rule_params['current_price'] = $item->single_price_no_tax(false) - $item->discount(false);
				$rule_params['quantity_in_cart'] = $item->quantity;
				$rule_params['row_total'] = $item->total_price_no_tax();
				
				$rule_params['item_discount'] = isset($item_discount_map[$item->key]) ? $item_discount_map[$item->key] : 0;

				if ($this->is_active_for_product($item->product, $product_conditions, $current_product_price, $rule_params))
				{
					$item->free_shipping = true;
				}
			}
			
			return 0;
		}
	}

?>