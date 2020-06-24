<?

	class Shop_CartRuleActionBase extends Shop_RuleActionBase
	{
		protected $action_applied = null;
		/**
		 * This method should return true if the action evaluates a 
		 * discount value per each product in the shopping cart
		 */
		public function is_per_product_action()
		{
			return false;
		}

		/**
		 * Checks whether action should be applied to a specified product. This method should be used
		 * by inherited actions for filtering products basing on the conditions specified on the Action tab
		 * of a Cart Price Rule
		 */
		protected function is_active_for_product($product, $product_conditions, $current_product_price, $rule_params = array(), $item = null)
		{
			if ($product_conditions === null)
				return true;
			
			$params = array('product'=>$product, 'current_price'=>$current_product_price);
			
			if (array_key_exists('item', $rule_params) && method_exists($rule_params['item'], 'get_om_record'))
				$params['om_record'] = $rule_params['item']->get_om_record();
				
			foreach ($rule_params as $key=>$value)
				$params[$key] = $value;

			$result = $product_conditions->is_true($params);
			return $result;
		}

		/**
		 * Evaluates the discount amount. This method should be implemented only for cart-type actions.
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 * @param array $item_discount_map A list of cart item identifiers and corresponding discounts.
		 * The row totals can be changed by per-product actions.
		 * @param array $item_discount_tax_incl_map A list of cart item identifiers and corresponding discounts with tax included.
		 * @param Shop_RuleConditionBase $product_conditions Specifies product conditions to filter the products the discount should be applied to
		 * @return float Returns discount value (for cart-wide actions), or a sum of discounts applied to products (for per-product actions) without tax applied
		 */
		public function eval_discount(&$params, $host_obj, &$item_discount_map, &$item_discount_tax_incl_map, $product_conditions)
		{
			return null;
		}


		/**
		 * All discount actions should set this to true of false depending on the actions outcome
		 * @param boolean $params Specifies if the actions eval_discount affected the cart in any way.
		 * @return void
		 */
		public function set_applied($boolean){
			$this->action_applied = $boolean ? true : false;
		}

		/**
		 * Used after discounts are applied to determine if the action affected the cart in any way
		 * @return boolean
		 */
		public function has_applied(){
			if($this->action_applied === null){
				return true; //maintains expected behavior from unsupported discount actions
			}
			return $this->action_applied ? true : false;
		}
	}

?>