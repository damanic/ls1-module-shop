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

	public static function evalOrderTotals($order, $items = null, $deferred_session_key=null, $discount_data=false)
	{
		Shop_TaxClass::set_tax_exempt($order->tax_exempt);
		Shop_TaxClass::set_customer_context(self::find_customer($order, true));


		$order->goods_tax = 0;
		$total_cost = 0;

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

		if (strlen($order->shipping_method_id) && strlen($order->shipping_country_id)) {

			//recalc all
			$order->shipping_quote = 0;
			$order->shipping_tax = 0;

			$methods = self::getAvailableShippingMethods($order, $deferred_session_key);

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
					$order->shipping_discount = round($shipping_method->discount, 2);
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

				$shipping_taxes = Shop_TaxClass::get_shipping_tax_rates($shipping_method->id, (object)$shipping_info, $order->get_shipping_quote());
				$order->apply_shipping_tax_array($shipping_taxes);
				$order->shipping_tax = Shop_TaxClass::eval_total_tax($shipping_taxes);
			}
		}

		$discount = 0;
		$subtotal = 0;
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
			$total_cost += $item->quantity*$item->cost;
		}

		if (strlen($order->shipping_country_id))
		{
			$tax_info = Shop_TaxClass::calculate_taxes($items, (object)$shipping_info, true);
			$order->goods_tax = $goods_tax = $tax_info->tax_total;
			$order->set_sales_taxes($tax_info->taxes);

			foreach ($items as $item_index=>$item)
			{
				if (array_key_exists($item_index, $tax_info->item_taxes))
					$item->apply_tax_array($tax_info->item_taxes[$item_index]);
			}
		}

		if ($order->free_shipping)
		{
			$order->shipping_quote = 0;
			$order->shipping_discount = 0;
			$order->shipping_tax = 0;
		}

		$order->discount = $discount;
		$order->subtotal = $subtotal;
		$order->total_cost = $total_cost;
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


	public static function getAvailableShippingMethods($order, $deferred_session_key=null)
	{

		$items = empty($deferred_session_key) ? $order->items : $order->list_related_records_deferred('items', $deferred_session_key);

		$total_price = 0;
		$total_volume = 0;
		$total_weight = 0;
		$total_item_num = 0;
		foreach ($items as $item)
		{
			$total_price += $item->single_price*$item->quantity;

			$total_volume += $item->om('volume')*$item->quantity;
			$total_weight += $item->om('weight')*$item->quantity;

			$total_item_num += $item->quantity;

			$product_extras = $item->get_extra_options();
			foreach ($product_extras as $extra_info)
			{
				$extra_key = md5($extra_info[1]);
				$option = Shop_ExtraOption::find_product_extra_option($item->product, $extra_key);
				if ($option)
				{
					$total_volume += $option->volume()*$item->quantity;
					$total_weight += $option->weight*$item->quantity;
				}
			}
		}

		$cart_items = self::items_to_cart_items_array($items);

		$coupon = $order->coupon_id ? Shop_Coupon::create()->find($order->coupon_id) : null;
		$coupon_code = $coupon ? $coupon->code : null;

		Shop_CheckoutData::set_coupon_code($coupon_code);

		$shipping_info = new Shop_CheckoutAddressInfo();
		$shipping_info->country = $order->shipping_country_id;
		$shipping_info->state = $order->shipping_state_id;
		$shipping_info->city = $order->shipping_city;
		$shipping_info->zip = $order->shipping_zip;
		$shipping_info->is_business = $order->shipping_addr_is_business;

		Shop_CheckoutData::set_shipping_info($shipping_info);
		if ($order->payment_method_id)
			Shop_CheckoutData::set_payment_method($order->payment_method_id);

		$customer = $order->customer_id ? Shop_Customer::create()->find($order->customer_id) : null;

		$result = Shop_ShippingOption::list_applicable(
			$order->shipping_country_id,
			$order->shipping_state_id,
			$order->shipping_zip,
			$order->shipping_city,
			$total_price,
			$total_volume,
			$total_weight,
			$total_item_num,
			false,
			false,
			$cart_items,
			null,
			$customer,
			null,
			$order->shipping_addr_is_business,
			true
		);

		return $result;
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

	public static function getAvailablePaymentMethods($order, $deferred_session_key=null)
	{
		$items = $order->list_related_records_deferred('items', $deferred_session_key);
		$cart_items = self::items_to_cart_items_array($items);
		self::evalOrderTotals($order,null);
		return Shop_PaymentMethod::list_applicable($order->billing_country_id, $order->total, true, true, false, $cart_items)->as_array();
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

}