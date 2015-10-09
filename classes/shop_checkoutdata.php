<?

	/**
	 * Contains information collected during the checkout process.
	 * The class acts as an internal checkout data storage. It has methods for setting and loading the checkout information,
	 * along with a method for placing a new order. It allows to implement custom checkout scenarios. The default {@link action@shop:checkout actions}
	 * use this class internally. 
	 * @documentable
	 * @see action@shop:checkout
	 * @author LemonStand eCommerce Inc.
	 * @package shop.classes
	 */
	class Shop_CheckoutData
	{
		protected static $_customer_override = null;
		
		/**
		 * Loads shipping and billing address information from a customer object.
		 * By default this method doesn't update the address information if it 
		 * has already been set in the checkout data object. Pass TRUE value
		 * to the <em>$force</em> parameter to override any existing data.
		 * @documentable
		 * @param Shop_Customer $customer Specifies the customer object to load data from.
		 * @param boolean $force Determines whether any existing data should be overridden. 
		 */
		public static function load_from_customer($customer, $force = false)
		{
			$checkout_data = self::load();
			if (array_key_exists('billing_info', $checkout_data) && !$force)
				return;
				
			/*
			 * Load billing info
			 */

			$billingInfo = new Shop_CheckoutAddressInfo();
			$billingInfo->load_from_customer($customer);
			$checkout_data['billing_info'] = $billingInfo;

			/*
			 * Load shipping info
			 */

			$shippingInfo = new Shop_CheckoutAddressInfo();
			$shippingInfo->act_as_billing_info = false;
			$shippingInfo->load_from_customer($customer);
			$checkout_data['shipping_info'] = $shippingInfo;

			self::save($checkout_data);
		}
		
		/*
		 * Billing info
		 */
		
		/**
		 * Sets billing address information from POST fields or from {@link Shop_CheckoutAddressInfo} object.
		 * If the <em>$info</em> parameter is empty, the address information is 
		 * loaded from POST data using {@link Shop_CheckoutAddressInfo::set_from_post()} method.
		 * If the <em>$info</em> parameter is not empty, the data is loaded from it.
		 * @documentable
		 * @param Shop_Customer $customer Specifies a customer object. 
		 * A currently logged in customer can be loaded with {@link Cms_Controller::get_customer()}.
		 * @param Shop_CheckoutAddressInfo $info Specifies an optional address information object to load data from.
		 */
		public static function set_billing_info($customer, $info = null)
		{
			if ($info === null)
			{
				$info = self::get_billing_info();
				$info->set_from_post($customer);
			} else
				$info->act_as_billing_info = true;

			$checkout_data = self::load();
			$checkout_data['billing_info'] = $info;
			
			self::save($checkout_data);
			self::save_custom_fields();
			
			self::set_customer_password();
		}
		
		public static function set_customer_password()
		{
			if (!post('register_customer'))
			{
				$checkout_data = self::load();
				$checkout_data['register_customer'] = false;

				self::save($checkout_data);
				return;
			}
				
			$validation = new Phpr_Validation();
			$validation->add('customer_password');
			$validation->add('email');

			$email = post('email');
			$existing_customer = Shop_Customer::find_registered_by_email($email);
			if ($existing_customer)
				$validation->setError( post('customer_exists_error', 'A customer with the specified email is already registered. Please log in or use another email.'), 'email', true );

			if (array_key_exists('customer_password', $_POST))
			{

				$allow_empty_password = trim(post('allow_empty_password'));
				$customer_password = trim(post('customer_password'));
				$confirmation = trim(post('customer_password_confirm'));

				if (!strlen($customer_password) && !$allow_empty_password)
					$validation->setError( post('no_password_error', 'Please enter your password.'), 'customer_password', true );
				
				if ($customer_password != $confirmation)
					$validation->setError( post('passwords_match_error', 'Password and confirmation password do not match.'), 'customer_password', true );

				$checkout_data = self::load();
				$checkout_data['customer_password'] = $customer_password;
				$checkout_data['register_customer'] = true;

				self::save($checkout_data);
			} else {
				$checkout_data = self::load();
				$checkout_data['customer_password'] = null;
				$checkout_data['register_customer'] = true;

				self::save($checkout_data);
			}
		}

		/**
		 * Returns the billing address information.
		 * If the billing address information is not set in the checkout data,
		 * it can be loaded from the {@link http://lemonstand.com/docs/configuring_the_shipping_parameters/ default shipping location}.
		 * @documentable
		 * @see http://lemonstand.com/docs/configuring_the_shipping_parameters/ Shipping Configuration
		 * @return Shop_CheckoutAddressInfo Returns the checkout address info object.
		 */
		public static function get_billing_info()
		{
			$checkout_data = self::load();

			if (!array_key_exists('billing_info', $checkout_data))
			{
				$obj = new Shop_CheckoutAddressInfo();
				$obj->set_from_default_shipping_location();
				return $obj;
			} else
			{
				$obj = $checkout_data['billing_info'];
				if ($obj && !$obj->country)
				{
					$obj->set_from_default_shipping_location();
					return $obj;
				}
			}
				
			return $checkout_data['billing_info'];
		}
		
		/**
		 * Copies the billing address information into the shipping address information.
		 * @documentable
		 */
		public static function copy_billing_to_shipping()
		{
			$billing_info = Shop_CheckoutData::get_billing_info();
			$shipping_info = Shop_CheckoutData::get_shipping_info();
			
			$shipping_info->copy_from($billing_info);
			Shop_CheckoutData::set_shipping_info($shipping_info);
		}

		/*
		 * Payment method
		 */
		
		/**
		 * Returns a payment method information previously set with {@link Shop_CheckoutData::set_payment_method() set_payment_method()} method.
		 * The method returns an object with the following fields:
		 * <ul>
		 *   <li><em>id</em> - specifies the payment method identifier.</li>
		 *   <li><em>name</em> - specifies the payment method name.</li>
		 *   <li><em>ls_api_code</em> - specifies the payment method API code.</li>
		 * </ul>
		 * If the payment method has not been set yet, the object's fields are empty.
		 * @documentable
		 * @return mixed Returns an object with <em>id</em>, <em>name</em> and <em>ls_api_code</em> fields.
		 */
		public static function get_payment_method()
		{
			$checkout_data = self::load();

			if (!array_key_exists('payment_method_obj', $checkout_data))
			{
				$method = array(
					'id'=>null,
					'name'=>null,
					'ls_api_code'=>null
				);
				return (object)$method;
			}
				
			return $checkout_data['payment_method_obj'];
		}

		/**
		 * Sets a payment method. 
		 * You can use the {@link Shop_PaymentMethod::find_by_api_code()} method for finding a specific payment method.
		 * <pre>Shop_CheckoutData::set_payment_method(Shop_PaymentMethod::find_by_api_code('card')->id);</pre>
		 * @documentable
		 * @param integer $payment_method_id Specifies the payment method identifier.
		 */
		public static function set_payment_method($payment_method_id = null)
		{
			$method = self::get_payment_method();
			$specific_option_id = $payment_method_id;

			$payment_method_id = $payment_method_id ? $payment_method_id : post('payment_method');
			
			if (!$payment_method_id)
				throw new Cms_Exception('Please select payment method.');
			
			$db_method = Shop_PaymentMethod::create();
			if(!$specific_option_id)
				$db_method->where('enabled=1');
			
			$db_method = $db_method->find($payment_method_id);
			if (!$db_method)
				throw new Cms_Exception('Payment method not found.');
			
			$db_method->define_form_fields();
			$method->id = $db_method->id;
			$method->name = $db_method->name;
			$method->ls_api_code = $db_method->ls_api_code;

			$checkout_data = self::load();
			$checkout_data['payment_method_obj'] = $method;
			self::save($checkout_data);
			self::save_custom_fields();
		}

		/*
		 * Shipping info
		 */

		/**
		 * Sets shipping address information from POST fields or from {@link Shop_CheckoutAddressInfo} object.
		 * If the <em>$info</em> parameter is empty, the address information is 
		 * loaded from POST data using {@link Shop_CheckoutAddressInfo::set_from_post()} method.
		 * If the <em>$info</em> parameter is not empty, the data is loaded from it.
		 * @documentable
		 * @param Shop_CheckoutAddressInfo $info Specifies an optional address information object to load data from.
		 */
		public static function set_shipping_info($info = null)
		{
			if ($info === null)
			{
				$info = self::get_shipping_info();
				$info->set_from_post();
			} else
				$info->act_as_billing_info = false;

			$checkout_data = self::load();
			$checkout_data['shipping_info'] = $info;
			self::save($checkout_data);
			self::save_custom_fields();
		}
		
		/**
		 * Sets shipping country, state and ZIP/postal code.
		 * This method allows to override the shipping
		 * country, state and ZIP/postal code components of the shipping address.
		 * @documentable
		 * @see Shop_CheckoutAddressInfo::set_location()
		 * @param integer $country_id Specifies the country identifier.
		 * @param integer $state_id Specifies the state identifier.
		 * @param string $zip Specifies the ZIP/postal code.
		 */
		public static function set_shipping_location($country_id, $state_id, $zip)
		{
			$info = self::get_shipping_info();
			$info->set_location($country_id, $state_id, $zip);

			$checkout_data = self::load();
			$checkout_data['shipping_info'] = $info;
			self::save($checkout_data);
			self::save_custom_fields();
		}

		/**
		 * Returns the shipping address information.
		 * If the shipping address information is not set in the checkout data,
		 * it can be loaded from the {@link http://lemonstand.com/docs/configuring_the_shipping_parameters/ default shipping location}.
		 * @documentable
		 * @see http://lemonstand.com/docs/configuring_the_shipping_parameters/ Shipping Configuration
		 * @return Shop_CheckoutAddressInfo Returns the checkout address info object.
		 */
		public static function get_shipping_info()
		{
			$checkout_data = self::load();

			if (!array_key_exists('shipping_info', $checkout_data) || !$checkout_data['shipping_info']->country)
			{
				$obj = new Shop_CheckoutAddressInfo();
				$obj->act_as_billing_info = false;
				$obj->set_from_default_shipping_location();
				return $obj;
			}

			return $checkout_data['shipping_info'];
		}
		
		/*
		 * Shipping method
		 */

		/**
		 * Sets a shipping method. 
		 * You can use the {@link Shop_ShippingOption::find_by_api_code()} method for finding a specific shipping method.
		 * <pre>Shop_CheckoutData::set_shipping_method(Shop_ShippingOption::find_by_api_code('default')->id);</pre>
		 * For multi-option shipping methods, like FedEx the <em>$shipping_method_id</em> parameter
		 * should contain both shipping method identifier and shipping method specific option identifier,
		 * separated with the underscore character, for example: <em>2_9b6fd8f11e836e9c3aceb8933d7a710b</em>
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_shipping_method_partial/ Creating the Shipping Method partial
		 * @param string $shipping_method_id Specifies the shipping method identifier.
		 * @param string $cart_name Specifies the shopping cart name. 
		 */
		public static function set_shipping_method($shipping_option_id = null, $cart_name = 'main')
		{
			$method = self::get_shipping_method();

			$specific_option_id = $shipping_option_id;
			
			$selected_shipping_option_id = $shipping_option_id ? $shipping_option_id : post('shipping_option');
			if (!$selected_shipping_option_id)
				throw new Cms_Exception('Please select shipping method.');

			$sub_option_id = null;
			if (strpos($selected_shipping_option_id, '_') !== false)
			{
				$parts = explode('_', $selected_shipping_option_id);
				$selected_shipping_option_id = $parts[0];
				$sub_option_id = $parts[1];
			}

			$option = Shop_ShippingOption::create();
			if (!$specific_option_id)
				$option->where('enabled=1');

			$option = $option->find($selected_shipping_option_id);
			if (!$option)
				throw new Cms_Exception('Shipping method not found.');
				
			$option->define_form_fields();

			$shipping_info = self::get_shipping_info();
			
			$total_price = Shop_Cart::total_price_no_tax($cart_name, false);
			$total_volume = Shop_Cart::total_volume($cart_name);
			$total_weight = Shop_Cart::total_weight($cart_name);
			$total_item_num = Shop_Cart::get_item_total_num($cart_name);
			
			$total_per_product_cost = self::get_total_per_product_cost($cart_name);

			try
			{
				$quote = $option->get_quote(
					$shipping_info->country,
					$shipping_info->state,
					$shipping_info->zip, 
					$shipping_info->city, 
					$total_price, 
					$total_volume, 
					$total_weight, 
					$total_item_num,
					Shop_Cart::list_active_items($cart_name),
					Cms_Controller::get_customer(),
					$shipping_info->is_business
				);
				if ($quote === null)
					throw new Cms_Exception('Shipping method is not applicable.');
			} catch (exception $ex)
			{
				// Rethrow system exception as CMS exception
				throw new Cms_Exception($ex->getMessage()); 
			}

			if (!is_array($quote))
			{
				$quote += $total_per_product_cost;
				
				$method->quote_no_tax = $quote;
				$method->quote = $quote;
				$method->sub_option_id = null;
				$method->sub_option_name = null;
				$method->internal_id = $option->id;
			} else {
				$sub_option_found = false;
				foreach ($quote as $sub_option_name=>$rate_obj)
				{
					if (md5($sub_option_name) == $sub_option_id)
					{
						$sub_option_found = true;

						$rate_obj['quote'] += $total_per_product_cost;

						$method->quote = $rate_obj['quote'];
						$method->quote_no_tax = $rate_obj['quote'];
						$method->sub_option_id = $option->id.'_'.$sub_option_id;
						$method->sub_option_name = $sub_option_name;
						$method->internal_id = $option->id.'_'.$rate_obj['id'];

						break;
					}
				}

				if (!$sub_option_found)
					throw new Cms_Exception('Selected shipping option is not applicable.');
			}

			$method->id = $option->id;
			$method->name = $option->name;
			$method->ls_api_code = $option->ls_api_code;

			$payment_method = Shop_CheckoutData::get_payment_method();
			$payment_method_obj = $payment_method->id ? Shop_PaymentMethod::create()->find($payment_method->id) : null;

			$discount_info = Shop_CartPriceRule::evaluate_discount(
				$payment_method_obj, 
				$option, 
				Shop_Cart::list_active_items($cart_name),
				$shipping_info,
				Shop_CheckoutData::get_coupon_code(), 
				Cms_Controller::get_customer(),
				Shop_Cart::total_price_no_tax($cart_name, false));

			$method->is_free = array_key_exists($method->internal_id, $discount_info->free_shipping_options);
			if ($method->is_free)
			{
				$method->quote = 0;
				$method->quote_no_tax = 0;
			}

			$checkout_data = self::load();
			$checkout_data['shipping_method_obj'] = $method;
			self::save($checkout_data);
			self::save_custom_fields();
		}
		
		protected static function get_total_per_product_cost($cart_name)
		{
			$cart_items = Shop_Cart::list_active_items($cart_name);
			$shipping_info = self::get_shipping_info();
			
			$total_per_product_cost = 0;
			foreach ($cart_items as $item)
			{
				$product = $item->product;
				if ($product)
					$total_per_product_cost += $product->get_shipping_cost($shipping_info->country, $shipping_info->state, $shipping_info->zip)*$item->quantity;
			}
			
			return $total_per_product_cost;
		}
		
		/**
		 * Returns a shipping method information previously set with {@link Shop_CheckoutData::set_shipping_method() set_shipping_method()} method.
		 * The method returns an object with the following fields:
		 * <ul>
		 *   <li><em>id</em> - specifies the shipping method identifier.</li>
		 *   <li><em>sub_option_id</em> - specifies the shipping method specific sub-option identifier (for multi-option shipping methods).</li>
		 *   <li><em>name</em> - specifies the shipping method name.</li>
		 *   <li><em>sub_option_name</em> - specifies the shipping sub-option name.</li>
		 *   <li><em>ls_api_code</em> - specifies the shipping method API code.</li>
		 *   <li><em>quote</em> - specifies the shipping quote.</li>
		 *   <li><em>quote_no_tax</em> - specifies the shipping quote without the shipping tax applied.</li>
		 *   <li><em>quote_tax_incl</em> - specifies the shipping quote with the shipping tax applied.</li>
		 *   <li><em>is_free</em> - determines the shipping option is free.</li>
		 *   <li><em>internal_id</em> - specifies the internal shipping method identifier, which includes both shipping method identifier
		 *       and shipping sub-option identifier, for example  <em>2_9b6fd8f11e836e9c3aceb8933d7a710b</em>.</li>
		 * </ul>
		 * If the shipping method has not been set yet, the object's fields are empty.
		 * @documentable
		 * @return mixed Returns an object.
		 */
		public static function get_shipping_method()
		{
			$checkout_data = self::load();

			if (!array_key_exists('shipping_method_obj', $checkout_data))
			{
				$method = array(
					'id'=>null,
					'sub_option_id'=>null,
					'quote'=>0,
					'quote_no_tax'=>0,
					'quote_tax_incl'=>0,
					'name'=>null,
					'sub_option_name'=>null,
					'is_free'=>false,
					'internal_id'=>null,
					'ls_api_code'=>null
				);
				return (object)$method;
			}

			return $checkout_data['shipping_method_obj'];
		}
		
		public static function reset_shiping_method() // deprecated
		{
			self::reset_shipping_method();
		}
		
		/**
		 * Deletes the shipping method information from the checkout data.
		 * @documentable
		 */
		public static function reset_shipping_method()
		{
			$checkout_data = self::load();
			if (array_key_exists('shipping_method_obj', $checkout_data))
				unset($checkout_data['shipping_method_obj']);

			self::save($checkout_data);
		}

		/**
		 * Returns a list of available shipping methods.
		 * The list of available shipping methods is based on the customer's shipping location
		 * and the cart contents. The method returns a list of {@link Shop_ShippingOption} objects. The {@link Shop_ShippingOption}
		 * class has the following properties which are required for displaying a list of available options:
		 * <ul>
		 *   <li><em>quote</em> - specifies the shipping quote.</li>
		 *   <li><em>quote_no_tax</em> - specifies the shipping quote without the shipping tax applied.</li>
		 *   <li><em>quote_tax_incl</em> - specifies the shipping quote with the shipping tax applied.</li>
		 *   <li><em>sub_options</em> - an array of the the shipping method specific sub-options.</li>
		 *   <li><em>multi_option</em> - indicates whether the option has sub-options.</li>
		 *   <li><em>error_hint</em> - an optional error message. This field is not empty in case if the shipping method
		 *       returned an error. The content of this field can be displayed in the list of shipping methods.</li>
		 * </ul>
		 * The <em>sub_options</em> array is not empty only for multi-option shipping methods (FedEx, USPS, etc.). Each element in the array 
		 * is an object with the following fields:
		 * <ul>
		 *   <li><em>id</em> - specifies the sub-option identifier. Identifiers are specific for each shipping method.</li>
		 *   <li><em>name</em> - specifies the sub-option name.</li>
		 *   <li><em>quote</em> - specifies the sub-option shipping quote.</li>
		 *   <li><em>is_free</em> - indicates whether the sub-option is free</li>
		 * </ul>
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_shipping_method_partial/ Creating the Shipping Method partial
		 * @param Shop_Customer $customer Specifies the customer object. 
		 * A currently logged in customer can be loaded with {@link Cms_Controller::get_customer()}.
		 * @param string $cart_name Specifies the shopping cart name.
		 * @return array Returns an array of {@link Shop_ShippingOption} objects.
		 */
		public static function list_available_shipping_options($customer, $cart_name = 'main')
		{
			global $activerecord_no_columns_info;

			$payment_method = Shop_CheckoutData::get_payment_method();
			$cart_items = Shop_Cart::list_active_items($cart_name);

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$total_price = Shop_Cart::total_price_no_tax($cart_name, false);
			$total_volume = Shop_Cart::total_volume($cart_name);
			$total_weight = Shop_Cart::total_weight($cart_name);
			$total_item_num = Shop_Cart::get_item_total_num($cart_name);

			$available_options = Shop_ShippingOption::list_applicable(
				$shipping_info->country,
				$shipping_info->state,
				$shipping_info->zip, 
				$shipping_info->city, 
				$total_price, 
				$total_volume, 
				$total_weight, 
				$total_item_num,
				1,
				false,
				$cart_items,
				Cms_Controller::get_customer_group_id(),
				$customer,
				null, 
				$shipping_info->is_business,
				false
			);

			if (!Shop_ShippingParams::get()->display_shipping_service_errors)
			{
				$options = array();
				foreach ($available_options as $key=>$option)
				{
					if (!strlen($option->error_hint))
						$options[$key] = $option;
				}
				
				$available_options = $options;
			}

			$multi_options = array();
			$options = array();
			foreach ($available_options as $key=>$option)
			{
				if ($option->multi_option)
					$multi_options[$key] = $option;
				else
					$options[$key] = $option;
			}

			return $multi_options + $options;
		}

		/**
		 * Converts multi-option shipping options to single-option options.
		 * Flat shipping option lists simplify front-end coding.
		 * @documentable
		 * @param array $shipping_options Specifies the shipping option list to flatten.
		 * @return array Returns an array of flat shipping options.
		 */
		public static function flatten_shipping_options($shipping_options)
		{
			$result = array();
			
			foreach ($shipping_options as $key=>$option)
			{
				if ($option->multi_option) 
				{
					foreach ($option->sub_options as $sub_option)
					{
						$sub_option->multi_option_name = $option->name;
						$sub_option->multi_option = true;
						$sub_option->multi_option_id = $option->id;
						$sub_option->description = $option->description;
						$sub_option->error_hint = $option->error_hint;
						$result[$sub_option->id] = $sub_option;
					}
				}
				else
					$result[$key] = $option;
			}
			
			return $result;
		}
		
		/*
		 * Coupon codes
		 */
		 
		public static function get_changed_coupon_code()
		{
			$coupon_code = self::get_coupon_code();
			$return = Backend::$events->fireEvent('shop:onBeforeDisplayCouponCode', $coupon_code);
			foreach($return as $changed_code)
			{
				if($changed_code)
					return $changed_code;
			}
			return $coupon_code;
		}

		/**
		 * Returns a coupon code previously set with {@link Shop_CheckoutData::set_coupon_code() set_coupon_code()} method.
		 * @documentable
		 * @return string Returns the coupon code.
		 */
		public static function get_coupon_code()
		{
			$checkout_data = self::load();

			if (!array_key_exists('coupon_code', $checkout_data))
				return null;
				
			return $checkout_data['coupon_code'];
		}
		
		/**
		 * Sets a specific coupon code. 
		 * This method doesn't checks whether the coupon code exists or valid.
		 * @documentable
		 * @param string $code Specifies the coupon code.
		 */
		public static function set_coupon_code($code)
		{
			$checkout_data = self::load();
			$checkout_data['coupon_code'] = $code;
			self::save($checkout_data);
			self::save_custom_fields();
		}
		
		/*
		 * Totals and discount calculations
		 */
		
		/**
		 * Returns checkout totals information.
		 * The information is calculated basing on the checkout data set with 
		 * {@link Shop_CheckoutData::set_billing_info() set_billing_info()},
		 * {@link Shop_CheckoutData::set_shipping_info() set_shipping_info()},
		 * {@link Shop_CheckoutData::set_shipping_method() set_shipping_method()},
		 * {@link Shop_CheckoutData::set_payment_method() set_payment_method()} methods or basing on default
		 * values if possible. 
		 * The method returns an object with the following fields:
		 *   <li><em>all_taxes</em> - an array of all taxes applied to products and shipping. Each element is an object with two fields: <em>name</em> and <em>total</em>.</li>
		 *   <li><em>discount</em> - the applied discount value.</li>
		 *   <li><em>discount_tax_incl</em> - the applied discount value with tax included.</li>
		 *   <li><em>free_shipping</em> - determines whether free shipping was applied by the Discount Engine.</li>
		 *   <li><em>goods_tax</em> - total amount of sales taxes applied to the products.</li>
		 *   <li><em>product_taxes</em> - an array of sales taxes applied to products. Each element is an object with two fields: <em>name</em> and <em>total</em>.</li>
		 *   <li><em>shipping_quote</em> - specifies the shipping quote.</li>
		 *   <li><em>shipping_quote_tax_incl</em> - specifies the shipping quote with the shipping tax applied.</li>
		 *   <li><em>shipping_tax</em> - specifies the shipping tax amount.</li>
		 *   <li><em>shipping_taxes</em> - an array of shipping. Each element is an object with two fields: <em>name</em> and <em>total</em>.</li>
		 *   <li><em>subtotal</em> - subtotal amount with no discounts applied.</li>
		 *   <li><em>subtotal_discounts</em> - subtotal amount with discounts applied.</li>
		 *   <li><em>subtotal_tax_incl</em> - subtotal amount with discounts and taxes applied.</li>
		 *   <li><em>total</em> - total amount.</li>
		 * @documentable
		 * @param string $cart_name Specifies the cart name.
		 * @return mixed Returns an object.
		 */
		public static function calculate_totals($cart_name = 'main')
		{
			$shipping_info = Shop_CheckoutData::get_shipping_info();

//			$product_taxes = Shop_Cart::list_taxes(Shop_CheckoutData::get_shipping_info(), null, $cart_name);
//			$goods_tax = Shop_Cart::eval_goods_tax(Shop_CheckoutData::get_shipping_info(), null, $cart_name);
			$subtotal = Shop_Cart::total_price_no_tax($cart_name, false);

			/**
			 * Apply discounts
			 */

			$shipping_method = Shop_CheckoutData::get_shipping_method();
			$payment_method = Shop_CheckoutData::get_payment_method();

			$payment_method_obj = $payment_method->id ? Shop_PaymentMethod::find_by_id($payment_method->id) : null;
			$shipping_method_obj = $shipping_method->id ? Shop_ShippingOption::find_by_id($shipping_method->id) : null;
			
			$cart_items = Shop_Cart::list_active_items($cart_name);

			$discount_info = Shop_CartPriceRule::evaluate_discount(
				$payment_method_obj, 
				$shipping_method_obj, 
				$cart_items,
				$shipping_info,
				Shop_CheckoutData::get_coupon_code(), 
				Cms_Controller::get_customer(),
				$subtotal);

			$tax_info = Shop_TaxClass::calculate_taxes($cart_items, $shipping_info);
			$goods_tax = $tax_info->tax_total;

			$subtotal = Shop_Cart::total_price_no_tax($cart_name, true, $cart_items);
			$subtotal_no_discounts = Shop_Cart::total_price_no_tax($cart_name, false, $cart_items);
			$subtotal_tax_incl = Shop_Cart::total_price($cart_name, true, $cart_items);
			$total = $subtotal + $goods_tax;

			$shipping_taxes = array();

			if (!array_key_exists($shipping_method->internal_id, $discount_info->free_shipping_options) && strlen($shipping_method->id))
			{
				$shipping_taxes = Shop_TaxClass::get_shipping_tax_rates($shipping_method->id, Shop_CheckoutData::get_shipping_info(), $shipping_method->quote_no_tax);
				$total += $shipping_tax = Shop_TaxClass::eval_total_tax($shipping_taxes);
				$total += $shipping_quote = $shipping_method->quote_no_tax;
			}
			else
			{
				$shipping_tax = 0;
				$shipping_quote = 0;
			}

			$result = array(
				'goods_tax'=>$goods_tax,
				'subtotal'=>$subtotal_no_discounts,
				'subtotal_discounts'=>$subtotal,
				'subtotal_tax_incl'=>$subtotal_tax_incl,
				'discount'=>$discount_info->cart_discount,
				'discount_tax_incl'=>$discount_info->cart_discount_incl_tax,
				'shipping_tax'=>$shipping_tax,
				'shipping_quote'=>$shipping_quote,
				'shipping_quote_tax_incl'=>$shipping_quote + $shipping_tax,
				'free_shipping'=>$discount_info->free_shipping,
				'total'=>$total,
				'product_taxes'=>$tax_info->taxes,
				'shipping_taxes'=>$shipping_taxes,
				'all_taxes'=>Shop_TaxClass::combine_taxes_by_name($tax_info->taxes, $shipping_taxes)
			);
			
			return (object)$result;
		}

		public static function eval_discounts($cart_name = 'main', $cart_items = null)
		{
			$shipping_method = Shop_CheckoutData::get_shipping_method();
			$payment_method = Shop_CheckoutData::get_payment_method();

			$payment_method_obj = $payment_method->id ? Shop_PaymentMethod::find_by_id($payment_method->id) : null;
			$shipping_method_obj = $shipping_method->id ? Shop_ShippingOption::find_by_id($shipping_method->id) : null;

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$subtotal = Shop_Cart::total_price_no_tax($cart_name, false);
			
			if ($cart_items === null)
				$cart_items = Shop_Cart::list_active_items($cart_name);

			$discount_info = Shop_CartPriceRule::evaluate_discount(
				$payment_method_obj, 
				$shipping_method_obj, 
				$cart_items,
				$shipping_info,
				Shop_CheckoutData::get_coupon_code(), 
				Cms_Controller::get_customer(),
				$subtotal);

			return $discount_info;
		}

		/*
		 * Cart identifier
		 */
		
		public static function set_cart_id($value)
		{
			$checkout_data = self::load();
			$checkout_data['cart_id'] = $value;
			self::save($checkout_data);
		}
		
		/**
		 * Returns the shopping cart content identifier saved in the beginning of the checkout process. 
		 * Comparing the result of this method with the result of the Shop_Cart::get_content_id() allows 
		 * to recognize whether the shopping cart content was changed during the checkout process.
		 * @documentable
		 * @see Shop_Cart::get_content_id()
		 * @return string Returns the cart content identifier.
		 */
		public static function get_cart_id()
		{
			$checkout_data = self::load();
			return array_key_exists('cart_id', $checkout_data) ? $checkout_data['cart_id'] : null;
		}

		/*
		 * Customer notes
		 */
		
		/**
		 * Sets customer notes string.
		 * Customer notes are saved to the {@link Shop_Order order} record.
		 * @documentable 
		 * @param string $notes Specifies the customer notes string.
		 */
		public static function set_customer_notes($notes)
		{
			$checkout_data = self::load();
			$checkout_data['customer_notes'] = $notes;
			self::save($checkout_data);
			self::save_custom_fields();
		}
		
		/**
		 * Returns the customer notes string previously set with {@link Shop_CheckoutData::set_customer_notes() set_customer_notes()} method.
		 * @documentable 
		 * @return string Returns the customer notes string
		 */
		public static function get_customer_notes()
		{
			$checkout_data = self::load();
			return array_key_exists('customer_notes', $checkout_data) ? $checkout_data['customer_notes'] : null;
		}


		/*
		 * Custom fields
		 */

		public static function save_custom_fields($data = null)
		{
			if ($data === null)
				$data = $_POST;
				
			$checkout_data = self::load();

			if (!array_key_exists('custom_fields', $checkout_data))
				$checkout_data['custom_fields'] = array();

			foreach ($data as $field=>$value)
				$checkout_data['custom_fields'][$field] = $value;

			self::save($checkout_data);
		}
		
		public static function get_custom_fields()
		{
			$checkout_data = self::load();
			if (!array_key_exists('custom_fields', $checkout_data))
				return array();
				
			return $checkout_data['custom_fields'];
		}
		
		public static function get_custom_field($name)
		{
			$fields = self::get_custom_fields();
			if (array_key_exists($name, $fields))
				return $fields[$name];
				
			return null;
		}
		
		/*
		 * Order registration
		 */

		/**
		 * Creates a new order order. 
		 * The checkout information must be prepared with 
		 * {@link Shop_CheckoutData::set_billing_info() set_billing_info()},
		 * {@link Shop_CheckoutData::set_shipping_info() set_shipping_info()},
		 * {@link Shop_CheckoutData::set_shipping_method() set_shipping_method()},
		 * {@link Shop_CheckoutData::set_payment_method() set_payment_method()} methods before this method is called. 
		 * @documentable
		 * @param Shop_Customer Specifies a currently logged in customer. 
		 * You can load a customer object from the CMS controller: {@link Cms_Controller::get_customer()}.
		 * @param boolean $register_customer Determines whether a guest customer should be automatically registered (converted to a registered customer).
		 * @param string $cart_name Specifies the shopping cart name to load the order item list from.
		 * @param boolean $empty_cart Specifies whether the shopping cart should be emptied after the order is placed.
		 * @return Shop_Order Returns the order object.
		 */
		public static function place_order($customer, $register_customer = false, $cart_name = 'main', $empty_cart = true)
		{
			$payment_method_info = Shop_CheckoutData::get_payment_method();
			$payment_method = Shop_PaymentMethod::create()->find($payment_method_info->id);
			if (!$payment_method)
				throw new Cms_Exception('The selected payment method is not found');

			$payment_method->define_form_fields();
			
			$checkout_data = self::load();
			$customer_password = array_key_exists('customer_password', $checkout_data) ? $checkout_data['customer_password'] : null;
			$register_customer_opt = array_key_exists('register_customer', $checkout_data) ? $checkout_data['register_customer'] : false;
			
			$register_customer = $register_customer || $register_customer_opt;

			$options = array();
			if ($register_customer)
				$options['customer_password'] = $customer_password;

			$order = Shop_Order::place_order($customer, $register_customer, $cart_name, $options);

			if ($empty_cart)
			{
				Shop_Cart::remove_active_items($cart_name);
    			Shop_CheckoutData::set_customer_notes('');
    			Shop_CheckoutData::set_coupon_code('');
			}
			
			if ($order && $register_customer && !$customer)
			{
				if (post('customer_auto_login'))
					Phpr::$frontend_security->customerLogin($order->customer_id);

				if (post('customer_registration_notification'))
					$order->customer->send_registration_confirmation();
			}
			
			return $order;
		}
		
		/*
		 * Include tax to price rule
		 */
		
		/**
		 * Determines whether prices should be displayed with taxes included.
		 * Use this method to determine whether prices should be displayed with tax included
		 * in {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ tax inclusive environments}.
		 * By default the method loads the customer's location from the currently logged in customer,
		 * but if the <em>$order</em> parameter is provided, the customer data is loaded from that object.
		 * @documentable
		 * @see http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Configuring LemonStand for tax inclusive environments
		 * @param Shop_Order $order Specifies an optional order object to load the customer information from.
		 * @return boolean Returns TRUE if prices should be displayed with taxes included. Returns FALSE otherwise.
		 */
		public static function display_prices_incl_tax($order = null)
		{
			if (self::$_customer_override && self::$_customer_override->group && (self::$_customer_override->group->disable_tax_included || self::$_customer_override->group->tax_exempt))
				return false;
			
			if (!$order)
			{
				$customer_group = Cms_Controller::get_customer_group();
				if ($customer_group && ($customer_group->disable_tax_included || $customer_group->tax_exempt))
					return false;
			} else
			{
				$customer = $order->customer;
				if ($customer && $customer->group && ($customer->group->disable_tax_included || $customer->group->tax_exempt))
					return false;
			}

			return Shop_ConfigurationRecord::get()->display_prices_incl_tax;
		}
		
		/*
		 * The following method is used by LemonStand internally
		 */
		
		public static function override_customer($customer)
		{
			self::$_customer_override = $customer;
		}
		
		/*
		 * Auto shipping required detection
		 */

		/**
		 * Determines whether the shopping cart contains any shippable items.
		 * By default shippable items are those items which belong to the Goods product type.
		 * If the cart contains only downloadable or service-type products the method returns FALSE. 
		 * @documentable
		 * @see http://lemonstand.com/docs/skipping_the_shipping_method_step_for_downloadable_products_or_services/ Skipping the Shipping Method step for downloadable products or services
		 * @param string $cart_name Specifies the shopping cart name
		 * @return boolean Returns TRUE if the cart contains any shippable items. Returns FALSE otherwise.
		 */
		public static function shipping_required($cart_name = 'main')
		{
			$items = Shop_Cart::list_active_items($cart_name);
			foreach ($items as $item)
			{
				if ($item->product->product_type->shipping)
					return true;
			}
			
			return false;
		}

		/*
		 * Save/load methods
		 */

		public static function reset_data()
		{
			$checkout_data = self::load();
			if (array_key_exists('register_customer', $checkout_data))
				unset($checkout_data['register_customer']);
			
			if (array_key_exists('customer_password', $checkout_data))
				unset($checkout_data['customer_password']);

			if (array_key_exists('shipping_method_obj', $checkout_data))
				unset($checkout_data['shipping_method_obj']);

			if (array_key_exists('custom_fields', $checkout_data))
				unset($checkout_data['custom_fields']);

			self::save($checkout_data);
		}
		
		/**
		 * Removes any checkout data from the session.
		 * @documentable
		 */
		public static function reset_all()
		{
			$checkout_data = array();
			self::save($checkout_data);
		}

		protected static function load()
		{
			return Phpr::$session->get('shop_checkout_data', array());
		}
		
		protected static function save(&$data)
		{
			Phpr::$session['shop_checkout_data'] = $data;
		}
	}

?>