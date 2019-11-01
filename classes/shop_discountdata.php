<?

	class Shop_DiscountData
	{
		public $free_shipping = false;
		public $item_price_map = array();
		public $applied_rules = array();  //rules that have applied an effect
		public $active_rules = array(); //rules that have qualified
		public $applied_rules_info = array();
		public $active_rules_info = array();
		public $cart_discount = 0;
		public $cart_discount_incl_tax = 0;
		public $free_shipping_options = array();
		public $shipping_discount = 0;
		public $add_shipping_options = array();
	}

?>