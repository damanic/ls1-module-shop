<?php
class Shop_OrderHelper{


	public static function recalc_order_discounts($order, $deferred_session_key=null, $save = false){
		$payment_method_obj = $order->payment_method_id ? Shop_PaymentMethod::create()->find($order->payment_method_id) : null;
		$shipping_method_obj = $order->shipping_method_id ? Shop_ShippingOption::create()->find($order->shipping_method_id) : null;

		$coupon = $order->coupon_id ? Shop_Coupon::create()->find($order->coupon_id) : null;
		$coupon_code = $coupon ? $coupon->code : null;

		$customer = $order->customer_id ? Shop_Customer::create()->find($order->customer_id) : null;

		Shop_CheckoutData::override_customer($customer);
		$items = self::evalOrderTotals($order, null, $deferred_session_key);
		$cart_items = self::items_to_cart_items_array($items);

		$subtotal = 0;
		foreach ($cart_items as $cart_item)
			$subtotal += $cart_item->total_price_no_tax(false);

		Shop_CartPriceRule::reset_rule_cache();

		$discount_info = Shop_CartPriceRule::evaluate_discount(
			$payment_method_obj,
			$shipping_method_obj,
			$cart_items,
			$order->get_shipping_address_info(),
			$coupon_code,
			$customer,
			$subtotal);

		$order->free_shipping = array_key_exists($order->internal_shipping_suboption_id, $discount_info->free_shipping_options);

		foreach ($cart_items as $cart_item)
		{
			$cart_item->order_item->discount = $cart_item->total_discount_no_tax();
			$applied_discounts_data[$cart_item->order_item->id] = $cart_item->order_item->discount;
		}

		$order->discount = $discount_info->cart_discount;

		if($save) {

			//before save
			$items = Shop_OrderHelper::evalOrderTotals($order,null,$deferred_session_key);
			Shop_OrderHelper::apply_item_discounts($items, $applied_discounts_data, $save);
			$order->save();

			//after save
			$order->set_applied_cart_rules($discount_info->applied_rules);
		}


		$results = array(
			'items' => $items,
			'cart_items' => $cart_items,
			'discount_info' => $discount_info,
			'coupon' => $coupon,
			'coupon_code' => $coupon_code,
			'customer' => $customer,
			'payment_method_obj' => $payment_method_obj,
			'shipping_method_obj' => $shipping_method_obj,
			'subtotal' => $subtotal,
			'order' => $order,
		);


		return $results;

	}

	public static function evalOrderTotals($order, $items = null, $deferred_session_key=null, $discount_data=false, $options = array())
	{
		$default_options = array(
			'recalculate_shipping' => true,
		);
		$options = array_merge($default_options,$options);
		Shop_TaxClass::set_tax_exempt($order->tax_exempt);
		Shop_TaxClass::set_customer_context(self::find_customer($order, true));


		$order->goods_tax = 0;
		$total_cost = 0;
		$discount = 0;
		$subtotal = 0;
		$subtotal_before_discounts = 0;


		$shipping_info = array();
		$shipping_info['country'] = $order->shipping_country_id;
		$shipping_info['state'] = $order->shipping_state_id;
		$shipping_info['zip'] = $order->shipping_zip;
		$shipping_info['city'] = $order->shipping_city;
		$shipping_info['street_address'] = $order->shipping_street_addr;

		if ($order->has_shipping_quote_override()) {
			//manual quote is considered tax exclusive, any previous shipping tax considerations can persist.
			$order->manual_shipping_quote = round(trim($order->manual_shipping_quote), 2);
		}

		//recalculating quotes optional
		if ($options['recalculate_shipping'] && strlen($order->shipping_method_id) && strlen($order->shipping_country_id)) {

			//recalc all
			$order->shipping_quote = 0;
			$order->shipping_tax = 0;

			$methods = $order->list_available_shipping_options($deferred_session_key, false);

			$shipping_method_id = $order->shipping_method_id;
			$sub_option_hash = null;

			if (strpos($shipping_method_id, '_') !== false)
			{
				$parts = explode('_', $shipping_method_id);
				$shipping_method_id = $parts[0];
				$sub_option_hash = $parts[1];
			} else
				$sub_option_hash = self::getShippingSubOptionHash($order);

			$order->shipping_method_id = $shipping_method_id;

			if (array_key_exists($shipping_method_id, $methods))
			{
				$shipping_method = $methods[$shipping_method_id];
				$quote = $shipping_method->quote_no_tax;

				if (!$shipping_method->multi_option)
				{
					$order->shipping_quote = round($quote, 2);
					$order->shipping_discount = isset($shipping_method->discount) ? round($shipping_method->discount, 2) : 0;
					$order->shipping_sub_option = null;
					$order->internal_shipping_suboption_id = $shipping_method_id;
				}
				else
				{
					foreach ($shipping_method->sub_options as $sub_option)
					{
						if ($sub_option->id == $order->shipping_method_id.'_'.$sub_option_hash)
						{
							$order->shipping_quote = round($sub_option->quote_no_tax, 2);
							$order->shipping_discount = round($sub_option->discount, 2);
							$order->shipping_sub_option = $sub_option->name;
							$order->internal_shipping_suboption_id = $order->shipping_method_id.'_'.$sub_option->suboption_id;
							break;
						}
					}
				}

			}
		}


		if (!$items)
		{
			$items = empty($deferred_session_key) ? $order->items : $order->list_related_records_deferred('items', $deferred_session_key);
			if($discount_data) {
				self::apply_item_discounts( $items, $discount_data );
			}
		}

		foreach ($items as $item)
		{
			$discount += $item->discount*$item->quantity;
			$subtotal += $item->eval_total_price();
			$subtotal_before_discounts += $item->single_price*$item->quantity;
			$total_cost += $item->quantity*$item->cost;
		}


		//Sales Taxes
		if (strlen($order->shipping_country_id))
		{
			$tax_context = array(
				'backend_call' => true,
				'order' => $order
			);
			$tax_info = Shop_TaxClass::calculate_taxes($items, (object)$shipping_info, $tax_context);
			$order->goods_tax = $goods_tax = $tax_info->tax_total;
			$order->set_sales_taxes($tax_info->taxes);

			foreach ($items as $item_index=>$item)
			{
				$item_tax_array = array_key_exists($item_index, $tax_info->item_taxes) ? $tax_info->item_taxes[$item_index] : array();
				$item->apply_tax_array($item_tax_array);
			}
		}


		//Item totals
		$order->discount = $discount;
		$order->subtotal = $subtotal;
		$order->subtotal_before_discounts = $subtotal_before_discounts;
		$order->total_cost = $total_cost;

		//Free shipping override
		if ($order->free_shipping || !$order->shipping_method_id) {
			$order->shipping_quote = 0;
			$order->shipping_discount = 0;
			$order->shipping_tax = 0;
		}

		//Recalculate shipping taxes
		if($order->shipping_method_id) {
			$shipping_taxes = self::get_shipping_taxes($order, $deferred_session_key);
			$order->apply_shipping_tax_array($shipping_taxes);
			$order->shipping_tax = Shop_TaxClass::eval_total_tax($shipping_taxes);
		}

		//New total
		$order->total = $order->get_order_total();

		return $items;
	}

	public static function find_customer($order, $check_order_data = false)
	{
		$customer = null;

		if (!$check_order_data)
			$customer_id = post('customer_id');
		else
			$customer_id = post('customer_id', Phpr::$request->post_array_item('Shop_Order', 'customer_id'));

		if ($customer_id > 0)
		{
			$customer = Shop_Customer::create()->find($customer_id);
			if (!$customer)
				throw new Phpr_ApplicationException('Customer not found');
		} else
			$customer = $order->customer;

		return $customer;
	}

	public static function find_customer_group_id($order)
	{
		$customer = self::find_customer($order);

		if ($customer)
			return $customer->customer_group_id;
		else
			return Shop_CustomerGroup::get_guest_group()->id;
	}


	/**
	 * Gets shipping methods available for order
	 * @documentable
	 * @deprecated Use {@link Shop_Order::list_available_shipping_options()} method instead.
	 */
	public static function getAvailableShippingMethods($order, $deferred_session_key=null) {
		return $order->list_available_shipping_options($deferred_session_key, false);
	}

	public static function items_to_cart_items_array($items){
		$cart_items = array();
		foreach ($items as $item)
		{
			$cart_item = $item->convert_to_cart_item();
			$cart_item->key .= $item->id;
			$cart_items[$cart_item->key] = $cart_item;
		}
		return $cart_items;
	}

	public static function getAvailablePaymentMethods($order, $deferred_session_key=null) {
		$params = array(
			'deferred_session_key' => $deferred_session_key,
			'backend_only' => true,
			'ignore_customer_group_filter' => true
		);
		return Shop_PaymentMethod::list_order_applicable($order, $params)->as_array();
	}

	public static function apply_item_discounts(&$items, $applied_discounts_data, $save=false)
	{
			try
			{
				$data = is_array($applied_discounts_data) ? $applied_discounts_data : unserialize($applied_discounts_data);

				if (is_array($data)) {

					foreach ( $data as $item_id => $discount ) {
						foreach ( $items as $item ) {
							if ( $item->id == $item_id ) {
								$item->discount = $discount;
								if($save) {
									$item->save();
								}
								break;
							}
						}
					}
				}
			}
			catch (Exception $ex)
			{
			}
	}


	public static function apply_single_item_discount($item, $applied_discounts_data, $save=false)
	{
		try
		{
			$data = is_array($applied_discounts_data) ? $applied_discounts_data : unserialize($applied_discounts_data);
			if (is_array($data))
			{
				foreach ($data as $item_id=>$discount)
				{
					if ($item->id == $item_id)
					{
						$item->discount = $discount;
						if($save){
							$item->save();
						}
						break;
					}
				}
			}
		}
		catch (Exception $ex)
		{

		}
	}

	public static function findLastOrder()
	{
		$obj = Shop_Order::create();
		return $obj->where('parent_order_id is null')->order('id desc')->find();
	}

	public static function getShippingSubOptionHash($order){
		$string = (isset($order->shipping_sub_option_id) && !empty($order->shipping_sub_option_id)) ? $order->shipping_sub_option_id : $order->shipping_sub_option;

		if (strpos($string, '_') !== false) {
			$parts = explode('_', $string);
			$string = $parts[1];
		}


		if(strlen($string) == 32 && ctype_xdigit($string)){
			$hash = $string;
		} else {
			$hash =  md5($string);
		}
		return $hash;
	}

	/**
	 * Returns shipping taxes applicable to the given order
	 * @param Shop_Order $order
	 *
	 * @return array|mixed
	 */
	public static function get_shipping_taxes($order, $deferred_session_key=null){
		$shipping_info = array();
		$shipping_info['country'] = $order->shipping_country_id;
		$shipping_info['state'] = $order->shipping_state_id;
		$shipping_info['zip'] = $order->shipping_zip;
		$shipping_info['city'] = $order->shipping_city;
		$shipping_info['street_address'] = $order->shipping_street_addr;
		$shipping_taxes = Shop_TaxClass::get_shipping_tax_rates( $order->shipping_method_id, (object) $shipping_info, $order->get_shipping_quote() );

		$eval_order = clone $order;
		$eval_order->items = empty($deferred_session_key) ? $order->items : $order->list_related_records_deferred('items', $deferred_session_key);
		$return = Backend::$events->fireEvent('shop:onOrderGetShippingTaxes', $shipping_taxes, $eval_order);
		foreach($return as $updated_shipping_taxes) {
			if($updated_shipping_taxes)
				return $updated_shipping_taxes;
		}

		return $shipping_taxes;
	}

}