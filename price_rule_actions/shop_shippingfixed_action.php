<?

	class Shop_ShippingFixed_Action extends Shop_CartRuleActionBase
	{
		public $shipping_discount = true;

		public function get_action_type()
		{
			return self::type_cart;
		}
		
		public function get_name()
		{
			return "Discount the shipping cost by fixed amount";
		}
		
		public function build_config_form($host_obj)
		{
			$host_obj->add_field('discount_amount', 'Discount amount', 'full', db_float)->comment('Please specify an amount  to subtract from the shipping cost. ', 'above')->validation()->required('Please specify discount amount');
		}
		
		/**
		 * This method should return true if the action evaluates a 
		 * discount value per each product in the shopping cart
		 */
		public function is_per_product_action()
		{
			return false;
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
			$this->set_applied(true);
			return $host_obj->discount_amount;
		}
	}

?>