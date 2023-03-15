<?php

	/**
	 * This class adds some Shop functions and filters to Twig engine.
	 * @has_documentable_methods
	 */
	class Shop_TwigExtension extends Twig_Extension
	{
		public static function create()
		{
			return new self();
		}
		
		public function getName()
		{
			return 'Shop extension';
		}
		
		public function getFilters()
		{
			return array(
				'categories' => new Twig_Filter_Method($this, 'category_filter')
			);
		}
		
		public function getFunctions()
		{
			return array(
				'total_cart_items',
				'total_cart_price',
				'cart_items',
				'shipping_method_selected',
				'company_information'
			);
		}
		
		public function total_cart_items($cart_name = 'main')
		{
			return Shop_Cart::get_item_total_num($cart_name);
		}

		public function total_cart_price($apply_discounts = true, $cart_name = 'main')
		{
			if ($apply_discounts)
				Shop_CheckoutData::eval_discounts($cart_name);
			
			return Shop_Cart::total_price($cart_name, $apply_discounts);
		}
		
		public function company_information()
		{
			return Shop_CompanyInformation::get();
		}
		
		public function cart_items($cart_name = 'main')
		{
			return Shop_Cart::list_active_items($cart_name);
		}

        /**
         * @deprecated
         * This method should no longer be used, it persists only to support legacy code.
         * Compare selected shipping quote IDs, not shipping methods!
         */
        function shipping_method_selected($shippingQuote1, $shippingQuote2)
        {
            traceLog('Use of deprecated helper method `shipping_method_selected`.');
            if(is_a($shippingQuote1, 'Shop_ShippingOptionQuote') && is_a($shippingQuote2, 'Shop_ShippingOptionQuote') ){
                if($shippingQuote1->getShippingQuoteId() == $shippingQuote2->getShippingQuoteId()){
                    return true;
                }
                return false;
            }

            if ($shippingQuote2->multi_option)
                return $shippingQuote2->multi_option_id == $shippingQuote1->id && $shippingQuote2->id == $shippingQuote1->sub_option_id;

            return $shippingQuote2->id == $shippingQuote1->id;
        }

		public function category_filter($source, $sort_order = 'front_end_sort_order')
		{
			if (is_object($source) && ($source instanceof Shop_Category || $source instanceof Db_ActiverecordProxy))
				return $source->list_children($sort_order);
				
			if ($source === null)
				return new Db_DataCollection();
			
			return Shop_Category::create()->list_root_children($sort_order);
		}
		
		/**
		 * Returns root categories or subcategories of a specific category.
		 * Use this filter to fetch categories and display a category hierarchy. Example:
		 * <pre twig>{% set categories = parent_category is defined ? parent_category|categories : 'root'|categories %}* </pre>
		 * @package shop.twig filters
		 * @name categories
		 * @twigtype filter
		 * @author LemonStand eCommerce Inc.
		 * @param mixed $source specifies a parent category. If a string or any other value specified, returns root categories.
		 * If the value is null, returns an empty collection.
		 * @param string $sort_order specifies the sorting order for categories. 
		 * With the default value the filter returns categories in the order specified in the back-end.
		 * @return Db_DataCollection Returns a collection of categories.
		 */
		private function function_categories($source, $sort_order = 'front_end_sort_order') {}
			
		/**
		 * Returns total number of items in the cart.
		 * Usage example: 
		 * <pre twig>{{ total_cart_items() }} items in the cart</pre>
		 * @package shop.twig functions
		 * @name total_cart_items
		 * @twigtype function
		 * @see Shop_Cart::get_item_total_num()
		 * @author LemonStand eCommerce Inc.
		 * @param string $cart_name Specifies the cart name
		 * @return integer Returns the total item number.
		 */
		private function function_total_cart_items($cart_name = 'main') {}

		/**
		 * Returns total price of all items in the cart. 
		 * Discounts are not applied to the function result. Usage example: 
		 * <pre twig>Cart total: {{ total_cart_price()|currency }}</pre>
		 * @package shop.twig functions
		 * @name total_cart_price
		 * @twigtype function
		 * @see Shop_Cart::total_price()
		 * @author LemonStand eCommerce Inc.
		 * @param boolean $apply_discounts Indicates if the cart-level discounts should be applied to the result.
		 * Calculating discounts on every page could affect the front-end performance. Pass the false value
		 * to the parameter to display the cart total before discounts.
		 * @param string $cart_name Specifies the cart name.
		 * @return float Returns the total price.
		 */
		private function function_total_cart_price($apply_discounts = true, $cart_name = 'main') {}

		/**
		 * Returns a list of active cart items. 
		 * @package shop.twig functions
		 * @name cart_items
		 * @twigtype function
		 * @see Shop_Cart::list_active_items()
		 * @author LemonStand eCommerce Inc.
		 * @param string $cart_name Specifies the cart name
		 * @return array Returns an array of {@link Shop_CartItem} objects.
		 */
		private function function_cart_items($cart_name = 'main') {}

		/**
		 * Returns true if a specific shipping option is selected in the checkout.
		 * This helper works only with flat shipping option lists.
		 * @package shop.twig functions
		 * @name shipping_method_selected
		 * @twigtype function
		 * @author LemonStand eCommerce Inc.
		 * @param mixed $shipping_method Specifies the currently selected shipping option.
		 * Use the <em>$shipping_method</em> variable generated by the shop:checkout action.
		 * @param shipping_option $shipping_option Shop_ShippingOption Shipping option object to check.
		 * @return boolean Returns true if the shipping option is selected.
		 */
		private function function_shipping_method_selected($shipping_method, $shipping_option) {}

		/**
		 * Returns the {@link Shop_CompanyInformation Company Information} object.
		 * @package shop.twig functions
		 * @name company_information
		 * @twigtype function
		 * @author LemonStand eCommerce Inc.
		 * @return Shop_CompanyInformation Returns the company information object.
		 */
		private function function_company_information() {}
	}
	
?>