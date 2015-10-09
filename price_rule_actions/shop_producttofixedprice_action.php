<?

	class Shop_ProductToFixedPrice_Action extends Shop_RuleActionBase
	{
		public function get_action_type()
		{
			return self::type_product;
		}
		
		public function get_name()
		{
			return "To fixed price";
		}
		
		public function build_config_form($host_obj)
		{
			$host_obj->add_field('discount_amount', 'Price', 'full', db_float)->comment('Please specify a fixed price to apply to the product.', 'above')->validation()->required('Please specify amount');
		}
		
		/**
		 * Evaluates the product price (for product-type actions) or discount amount (for cart-type actions)
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function eval_amount(&$params, $host_obj)
		{
			return $host_obj->discount_amount;
		}
	}

?>