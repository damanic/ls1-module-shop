<?

	class Shop_ProductByFixedAmount_Action extends Shop_RuleActionBase
	{
		public function get_action_type()
		{
			return self::type_product;
		}
		
		public function get_name()
		{
			return "By fixed amount";
		}
		
		public function build_config_form($host_obj)
		{
			$host_obj->add_field('discount_amount', 'Discount amount', 'full', db_float)->comment('Please specify an amount  to subtract from the product original price. ', 'above')->validation()->required('Please specify amount');
		}
		
		/**
		 * Evaluates the product price (for product-type actions) or discount amount (for cart-type actions)
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function eval_amount(&$params, $host_obj)
		{
			if (!array_key_exists('current_price', $params))
				throw new Phpr_ApplicationException('Error applying the "By fixed amount" price rule action: the current_price element is not found in the action parameters.');

			$current_price = $params['current_price'];

			return max($current_price - $host_obj->discount_amount, 0);
			
		}

	}

?>