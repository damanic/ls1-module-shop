<?php

	/**
	 * Provides methods which help in developing front-end pages and partials for displaying product details information.
	 * @documentable
	 * @package shop.helpers
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ProductHelper
	{
		/**
		 * Returns a list of selected product options suitable for passing to {@link Shop_Product::om()} method. 
		 * The <em>$return_first_enabled_om_record</em> parameter determines whether the method should return an option 
		 * set corresponding to the first <em>enabled</em> {@link http://lemonstand.com/docs/integrating_option_matrix Option Matrix} record. 
		 * If the parameter value is FALSE, the method returns an option set corresponding to the first <em>existing</em> Option Matrix product.
		 *
		 * Product options can be selected in 2 ways - passed through the page URL (product search feature uses this method) 
		 * or POSTed when a visitor selects another option on the {@link http://lemonstand.com/docs/product_page/ product details} page.
		 * @documentable
		 * @see http://lemonstand.com/docs/integrating_option_matrix Integrating Option Matrix
		 * @see http://lemonstand.com/docs/understanding_option_matrix Understanding Option Matrix
		 * @param Shop_Product $product Specifies a product to return options for.
		 * @param boolean $return_first_enabled_om_record Determines whether the function should
		 * return first enabled Option Matrix record options.
		 * @return array Returns an array of option names and values.
		 */
		public static function get_default_options($product, $return_first_enabled_om_record = true)
		{
			$posted_options = post('product_options', array());
			if ($posted_options)
				return $product->normalize_posted_options($posted_options);

			$get_options = Phpr::$request->getField('product_options', array());
			if ($get_options)
			{
				$result = array();
				foreach ($get_options as $name=>$value)
					$result[md5($name)] = $value;

				return $product->normalize_posted_options($result);
			}

			return $product->get_first_available_om_value_set($return_first_enabled_om_record);
		}
	}
	
?>