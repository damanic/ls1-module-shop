<?

	class Shop_CartAddShippingOption_Action extends Shop_CartRuleActionBase
	{
		public $adds_shipping_option = true;

		public function get_action_type()
		{
			return self::type_cart;
		}
		
		public function get_name()
		{
			return "Expose a hidden shipping option";
		}

		public function build_config_form($host_obj)
		{
			$host_obj->add_field('shipping_option', 'Shipping Option', 'full', db_number)->renderAs(frm_dropdown)->comment('The options listed are normally hidden from customers, selecting the one you would like to present to the customer', 'above')->validation()->required('Please specify shipping option');
		}

		public function get_shipping_option_options($host_obj)
		{
			$shipping_options = Shop_ShippingOption::create()->where('backend_enabled = 1 AND enabled IS NULL')->find_all();
			if(!$shipping_options->count){
				return array(
					null =>'No hidden shipping options found',
				);
			}
			$options = array();
			foreach($shipping_options as $option){
				$options[$option->id] = $option->name;
			}
			return $options;
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
		public function eval_discount(&$params, $host_obj, &$item_discount_map, &$item_discount_tax_incl_map, $product_conditions) {
			if($host_obj->shipping_option){

				//expose hidden shipping option
				$params['add_shipping_option'] = $host_obj->shipping_option;

				//mark hidden discount applied only if exposed shipping option is selected
				$discount_shipping_option_id = is_object($host_obj->shipping_option) ? $host_obj->shipping_option->id  : $host_obj->shipping_option;
				$selected_shipping_option = Shop_CheckoutData::get_shipping_method();
				if($selected_shipping_option && $selected_shipping_option->id == $discount_shipping_option_id){
					$this->set_applied(true);
				} else {
					$this->set_applied(false);
				}
			}
			return 0;
		}
	}

?>