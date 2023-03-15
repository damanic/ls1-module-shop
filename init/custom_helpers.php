<?

	/**
	 * Returns a currency representation of a number.
	 * Returns a string containing a numeric value formatted as currency according the system currency settings. 
	 * You can change the system currency settings on System/Settings/Currency page. Default system currency 
	 * is USD and the default format is $10,000.00.
	 * 
	 * The following code outputs a product price.
	 * <pre>Price: <?= format_currency($product->price()) ?></pre>
	 * @documentable
	 * @package shop.helpers
	 * @author LemonStand eCommerce Inc.
	 * @param string $num specifies a value to format.
	 * @param integer $decimals specifies a number of decimal digits. Optional parameter, the default value is 2.
	 * @param string $currency_code specifies an alternative currency format to display, if null system currency format is used.
	 * @return string returns the formatted currency value.
	 */
	function format_currency($num, $decimals = 2, $currency_code=null)
	{
		if(empty($currency_code)){
			return Shop_CurrencySettings::format_currency($num, $decimals);
		} else {
			//not a conversion
			return Shop_CurrencyHelper::format_currency($num,$decimals,$currency_code);
		}
	}
	
	/**
	 * Closes a session of a current customer and optionally redirects browser to a specified address. 
	 * Use this function to create the Logout page.
	 * The following code represents contents of a simplest logout page.
	 * <pre><? customer_logout('/'); ?></pre>
	 * @documentable
	 * @package shop.helpers
	 * @see http://lemonstand.com/docs/customer_login_and_logout/ Customer login and logout pages
	 * @author LemonStand eCommerce Inc.
	 * @param string $redirect specifies an URL to redirect the customer to.
	 */
	function customer_logout($redirect = null)
	{
		Phpr::$frontend_security->logout($redirect);
	}
	
	/**
	 * Returns the tax included label text. 
	 * Use this function if product prices on the store pages include tax and you want to let visitors know about it.
	 * You can configure the label text and behavior on the System/Settings/eCommerce Settings page, please see 
	 * {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Configuring LemonStand for tax inclusive environments}
	 * for details.
	 * The function returns the text which you specify in the <em>Tax included label</em> text field of the eCommerce Settings form in case if the visitor's 
	 * location matches a country and state specified in the configuration form. If a visitor's location 
	 * is not known, the {@link http://lemonstand.com/docs/configuring_the_shipping_parameters/ default shipping location} is used.
	 * 
	 * The following code outputs a tax included label next to a product price on the product details page:
	 * <pre>Price:<?= format_currency($product->price()) ?> <?= tax_incl_label() ?></pre>
	 * @documentable
	 * @package shop.helpers
	 * @author LemonStand eCommerce Inc.
	 * @see http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Configuring LemonStand for tax inclusive environments
	 * @see http://lemonstand.com/docs/configuring_the_shipping_parameters/ Configuring the shipping parameters
	 * @see http://lemonstand.com/docs/payment_receipt_page/ Payment receipt page
	 * @see http://lemonstand.com/docs/order_details_page/ Order details page
	 * 
	 * @param Shop_Order $order - optional reference to the Shop_Order object. 
	 * Pass the order object into this parameter if an order is available, for example on the {@link http://lemonstand.com/docs/order_details_page/ Order Details} 
	 * or {@link http://lemonstand.com/docs/payment_receipt_page/ Receipt} pages.
	 * @return string Returns the tax included label text or NULL.
	 */
	function tax_incl_label($order = null)
	{
		$display_tax_included = Shop_CheckoutData::display_prices_incl_tax($order);
		if (!$display_tax_included)
			return null;
		
		$config = Shop_ConfigurationRecord::get();

		if (!$order)
		{
			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$shipping_country_id = $shipping_info->country;
			$shipping_state_id = $shipping_info->state;
		} else
		{
			$shipping_country_id = $order->shipping_country_id;
			$shipping_state_id = $order->shipping_state_id;
		}
		
		if (!$config->tax_inclusive_country_id)
			return $config->tax_inclusive_label;

		if ($config->tax_inclusive_country_id != $shipping_country_id)
			return null;
			
		if (!$config->tax_inclusive_state_id)
			return $config->tax_inclusive_label;
			
		if ($config->tax_inclusive_state_id != $shipping_state_id)
			return null;

		return $config->tax_inclusive_label;
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

?>