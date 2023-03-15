<?php

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
         * This method checks to see if a Shop_AddressInfo object is stored in the checkout session.
         * This method does not check to see if the stored Address object is considered complete.
         * @param bool $requireShippingAddress Set to true if checking for both billing and shipping address objects.
         * @return bool True if Shop_AddressInfo object exists in session, otherwise false.
         */
        public static function hasAddressInfo($requireShippingAddress = false){
            $checkout_data = self::load();
            if (!array_key_exists('billing_info', $checkout_data)){
                return false;
                if(!is_a($checkout_data['billing_info'], 'Shop_AddressInfo')){
                    return false;
                }
            }
            if($requireShippingAddress){
                if (!array_key_exists('shipping_info', $checkout_data)){
                    return false;
                }
                if(!is_a($checkout_data['billing_info'], 'Shop_AddressInfo')){
                    return false;
                }
            }
            return true;
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
				$obj->set_from_default_shipping_location(array('country'));
				return $obj;
			} else
			{
				$obj = $checkout_data['billing_info'];
				if ($obj && !$obj->country)
				{
					$obj->set_from_default_shipping_location(array('country'));
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
				$obj->set_from_default_shipping_location(array('country'));
				return $obj;
			}

			return $checkout_data['shipping_info'];
		}
		
		/*
		 * Shipping method
		 */


        /**
         * Returns the user selected shipping quote or null
         * If the shipping quote saved in session is in a different
         * currency to active checkout currency, it is no longer
         * considered valid, and will not be returned.
         *
         * @return Shop_ShippingOptionQuote|null
         */
        public static function getSelectedShippingQuote(){
            $checkout_data = self::load();
            if (!array_key_exists('selected_shipping_quote', $checkout_data))
            {
                return null;
            }
            $quote = $checkout_data['selected_shipping_quote'];
            $activeCurrencyCode = self::get_currency(false);
            if($activeCurrencyCode && ($activeCurrencyCode !== $quote->getCurrencyCode())){
                return null;
            }
            return $quote;
        }


        /**
         * Saves the selected shipping quote to the cart.
         * @documentable
         * @param string $shippingQuoteId Specifies the selected shipping quote identifier
         * @param string $cart_name Specifies the shopping cart name.
         */
        public static function setSelectedShippingQuote($shippingQuoteId = null, $cart_name = 'main')
        {



            $shippingQuoteId = $shippingQuoteId ? $shippingQuoteId : post('shipping_option');


            if (!$shippingQuoteId)
                throw new Cms_Exception('Please select shipping method.');



            $selectedShippingQuote = self::getSelectedShippingQuote();
            if($selectedShippingQuote && $selectedShippingQuote->getShippingQuoteId() == $shippingQuoteId){
                return; //already set
            }

            $selectedShippingOptionId = $shippingQuoteId;
            if(!is_numeric($shippingQuoteId)){
                $selectedShippingOptionId = Shop_ShippingOptionQuote::getOptionIdFromQuoteId($shippingQuoteId);
            }

            $shippingOption = Shop_ShippingOption::create();
            $shippingOption = $shippingOption->find($selectedShippingOptionId);
            if (!$shippingOption)
                throw new Cms_Exception('Shipping method not found.');

            self::applySelectedShippingQuote($shippingOption, $shippingQuoteId, $cart_name);
            self::save_custom_fields();
        }

        /**
         * This allows a shipping option to be assigned to the cart without specifying
         * a quote ID.  This can be used when quote ID is irrelevant
         * (e.g. a shipping option that always returns a single quote).
         *
         * @param Shop_ShippingOption $shippingOption The shipping option selected
         * @param string $cartName The cart name to apply to
         * @return void
         */
        public static function setSelectedShippingOption(Shop_ShippingOption $shippingOption, $cartName = 'main'){
            self::applySelectedShippingQuote($shippingOption, null, $cartName);
            self::save_custom_fields();
        }

        /**
         * Recalculates the shipping cost for selected shipping option
         * @param string $cartName
         * @return void
         */
        public static function refreshActiveShippingQuote($cartName = 'main'){
            $selectedShippingQuote = self::getSelectedShippingQuote();
            if($selectedShippingQuote){
                self::resetSelectedShippingQuote();
                self::applySelectedShippingQuote($selectedShippingQuote->getShippingOption(),$selectedShippingQuote->getShippingOptionId(), $cartName);
            }
        }

        /**
         * Removes the customers shipping quote selection.
         * @return void
         */
        public static function resetSelectedShippingQuote(){

            $checkout_data = self::load();
            if (array_key_exists('selected_shipping_quote', $checkout_data))
                unset($checkout_data['selected_shipping_quote']);

            self::save($checkout_data);
        }

        /**
         * This method returns Shipping Options that qualify for this checkout session.
         * Shipping options returned do not include quotes for this checkout.
         * You can check for shipping quotes using methods in the returned shipping options.
         * @param string $cartName
         * @param Shop_Customer|null $customer
         * @return array|Shop_ShippingOption[]
         */
        public static function getApplicableShippingOptions($cartName='main', $customer){
            return Shop_ShippingOption::getShippingOptionsForCheckout($cartName, $customer);
        }



        /**
         * Get an array of Shop_ShippingOptions that have been exposed by discount rules
         * @param string $cart_name
         * @return array|Shop_ShippingOption[] Array of shipping options
         */
		public static function get_discount_applied_shipping_options($cart_name = 'main'){
            $shipping_options = array();

			//run eval discounts on cart items to mark free shipping items, updates by reference
			$cart_items = Shop_Cart::list_active_items($cart_name);
			self::eval_discounts($cart_name, $cart_items);
            $payment_method = Shop_CheckoutData::get_payment_method();
            $payment_method_obj = $payment_method ? Shop_PaymentMethod::find_by_id( $payment_method->id ) : null;

            $discount_info = Shop_CartPriceRule::evaluate_discount(
                $payment_method_obj,
                null,
                $cart_items,
                Shop_CheckoutData::get_shipping_info(),
                Shop_CheckoutData::get_coupon_code(),
                Cms_Controller::get_customer(),
                Shop_Cart::total_price_no_tax($cart_name, false)
            );
            if ( isset( $discount_info->add_shipping_options ) && count( $discount_info->add_shipping_options ) ) {
                foreach ( $discount_info->add_shipping_options as $option_id ) {
                    $option = Shop_ShippingOption::create()->find( $option_id );
                    if ( $option ) {
                        $shipping_options[$option->id] = $option;
                    }
                }
            }
            return $shipping_options;

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
		 * {@link Shop_CheckoutData::setSelectedShippingQuote() setSelectedShippingQuote()},
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

            $shippingMethodObj = null;
			$selectedShippingQuote = Shop_CheckoutData::getSelectedShippingQuote();
            if($selectedShippingQuote) {
                $shippingMethodObj = $selectedShippingQuote->getShippingOption();
            }

			$payment_method = Shop_CheckoutData::get_payment_method();
			$payment_method_obj = $payment_method->id ? Shop_PaymentMethod::find_by_id($payment_method->id) : null;


			$cart_items = Shop_Cart::list_active_items($cart_name);

			$discount_info = Shop_CartPriceRule::evaluate_discount(
				$payment_method_obj, 
				$shippingMethodObj,
				$cart_items,
				$shipping_info,
				Shop_CheckoutData::get_coupon_code(), 
				Cms_Controller::get_customer(),
				$subtotal);

			$tax_context = array(
				'cart_name' => $cart_name
			);
			$tax_info = Shop_TaxClass::calculate_taxes($cart_items, $shipping_info, $tax_context);
			$goods_tax = $tax_info->tax_total;

			$subtotal = Shop_Cart::total_price_no_tax($cart_name, true, $cart_items);
			$subtotal_no_discounts = Shop_Cart::total_price_no_tax($cart_name, false, $cart_items);
			$subtotal_tax_incl = Shop_Cart::total_price($cart_name, true, $cart_items);
			$total = $subtotal + $goods_tax;

			$shipping_taxes = array();
            $shipping_tax = 0;
            $shipping_quote = 0;
            if($selectedShippingQuote) {
                if (!array_key_exists( $selectedShippingQuote->getShippingOptionId(), $discount_info->free_shipping_options )
                && !array_key_exists( $selectedShippingQuote->getShippingQuoteId(), $discount_info->free_shipping_options )
                ) {
                    $shipping_taxes = self::getShippingTaxes($cart_name, $selectedShippingQuote);
                    $total += $shipping_tax = Shop_TaxClass::eval_total_tax($shipping_taxes);
                    $total += $shipping_quote = $selectedShippingQuote->getPrice();
                }
            }

			$result = array(
				'goods_tax'=>$goods_tax,
				'subtotal'=>$subtotal_no_discounts,
				'subtotal_discounts'=>$subtotal,
				'subtotal_tax_incl'=>$subtotal_tax_incl,
				'discount'=>$discount_info->cart_discount,
				'discount_tax_incl'=>$discount_info->cart_discount_incl_tax,
				'discount_info'=>$discount_info,
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
            $shippingOptionObj = null;
			$selectedShippingQuote = Shop_CheckoutData::getSelectedShippingQuote();
            if($selectedShippingQuote){
                $shippingOptionObj = $selectedShippingQuote->getShippingOption();
            }

            $payment_method = Shop_CheckoutData::get_payment_method();
			$payment_method_obj = $payment_method->id ? Shop_PaymentMethod::find_by_id($payment_method->id) : null;


			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$subtotal = Shop_Cart::total_price_no_tax($cart_name, false);

			if ($cart_items === null)
				$cart_items = Shop_Cart::list_active_items($cart_name);

			$discount_info = Shop_CartPriceRule::evaluate_discount(
				$payment_method_obj,
				$shippingOptionObj,
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
		 * Creates a new order.
		 * The checkout information must be prepared with 
		 * {@link Shop_CheckoutData::set_billing_info() set_billing_info()},
		 * {@link Shop_CheckoutData::set_shipping_info() set_shipping_info()},
		 * {@link Shop_CheckoutData::setSelectedShippingQuote() setSelectedShippingQuote()},
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

		public static function is_currency_set(){
			$checkout_data = self::load();
			if (!array_key_exists('currency_code', $checkout_data) || !$checkout_data['currency_code'] ){
				return false;
			}
			return true;
		}

		public static function set_currency($currency=null){
			$checkout_data = self::load();
			$currency_code = null;

			if(is_a($currency,'Shop_CurrencySettings')){
				$currency_code = $currency->code;
			} else {
				$valid_currency_code = Db_DbHelper::scalar('SELECT shop_currency_settings.code FROM shop_currency_settings WHERE shop_currency_settings.code = ?', $currency);
				if($valid_currency_code){
					$currency_code = $valid_currency_code;
				}
			}

			$checkout_data['currency_code'] = $currency_code;
			self::save($checkout_data);
		}

		public static function get_currency($object=true){
			$checkout_data = self::load();
			if (!self::is_currency_set() ){
				$currency = Shop_CurrencySettings::get();
				return $object ? $currency : $currency->code;
			}
			if($object){
                $currency = Shop_CurrencyHelper::get_currency_setting($checkout_data['currency_code']);
				if($currency){
					return $currency;
				}

			}
			return $checkout_data['currency_code'];
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

			if (array_key_exists('selected_shipping_quote', $checkout_data))
				unset($checkout_data['selected_shipping_quote']);

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


        /**
         * Returns a set of shipping tax rates based on customers cart data
         * @param string $cartName
         * @param Shop_ShippingOptionQuote|null $shippingQuote
         * @return array Array of tax info
         */
        public static function getShippingTaxes($cartName='main', $shippingQuote = null){
            $shippingTaxes = array();
            if(!$shippingQuote){
                $shippingQuote = self::getSelectedShippingQuote();
            }
            if($shippingQuote) {
                $shippingOptionId = $shippingQuote->getShippingOptionId();
                $shippingTaxes = Shop_TaxClass::get_shipping_tax_rates(
                    $shippingOptionId,
                    Shop_CheckoutData::get_shipping_info(),
                    $shippingQuote->getPrice()
                );
                $return = Backend::$events->fireEvent(
                    'shop:onCheckoutGetShippingTaxes',
                    $shippingTaxes,
                    $shippingQuote,
                    $cartName
                );
                foreach ($return as $updated_shipping_taxes) {
                    if ($updated_shipping_taxes) {
                        return $updated_shipping_taxes;
                    }
                }
            }
            return $shippingTaxes;
        }


        protected static function applySelectedShippingQuote($shippingOption=null, $shippingQuoteId=null, $cart_name = 'main'){
            $quotes = array();
            try {
                if(!$shippingOption){
                    self::resetSelectedShippingQuote();
                    throw new ApplicationException('Shipping option is not valid');
                }
                $shippingOption->apply_checkout_quote($cart_name);
                $quotes = $shippingOption->getQuotes();
            } catch (exception $ex) {
                // Rethrow system exception as CMS exception
                throw new Cms_Exception($ex->getMessage());
            }

            if(!$quotes){
                throw new Cms_Exception('Selected shipping option is not applicable.');
            }

            $selectedQuote = null;
            if(count($quotes) === 1 && !$shippingQuoteId){
                $selectedQuote = $quotes[0];
            } else {
                foreach($quotes as $quote){
                    if($quote->getShippingQuoteId() == $shippingQuoteId) {
                        $selectedQuote = $quote;
                        break;
                    } else if (is_numeric($shippingQuoteId) && ($quote->getShippingOptionId() == $shippingQuoteId)){
                        $selectedQuote = $quote;
                        break;
                    }
                }
            }
            if (!$selectedQuote)
                throw new Cms_Exception('Selected shipping option is not applicable 2.');

            $checkout_data = self::load();
            $checkout_data['selected_shipping_quote'] = $selectedQuote;
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


		//
		// Deprecated methods
		//


        /**
         * @deprecated
         * @use self::getApplicableShippingOptions();
         *
         * This method returns available shipping options AND calls for shipping quotes as
         * part of the availability requirement. You can now use the method getApplicableShippingOptions()
         * and call for shipping quotes on the returned shipping options IF required.
         *
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
         * @param array $options Specifies options for filtering.
         * @return array Returns an array of {@link Shop_ShippingOption} objects.
         */
        public static function list_available_shipping_options($customer, $cart_name = 'main', $options=array())
        {
            global $activerecord_no_columns_info;

            $default_options = array(
                'cart_name' => $cart_name,
                'include_tax'=>1,
                'customer_group_id' => Cms_Controller::get_customer_group_id(),
            );

            $options = array_merge($default_options,$options);
            $customer = Cms_Controller::get_customer();

            $shipping_info = Shop_CheckoutData::get_shipping_info();

            //run eval discounts on cart items to mark free shipping items, updates by reference
            $cart_items = Shop_Cart::list_active_items($cart_name);
            self::eval_discounts($cart_name, $cart_items);

            $params = array(
                'display_prices_including_tax' => $options['include_tax'] ? $options['include_tax'] : Shop_CheckoutData::display_prices_incl_tax(),
                'shipping_info' => $shipping_info,
                'total_price'=>Shop_Cart::total_price_no_tax($cart_name, false),
                'total_volume'=>Shop_Cart::total_items_volume($cart_items),
                'total_weight'=>Shop_Cart::total_items_weight($cart_items),
                'total_item_num'=>Shop_Cart::get_item_total_num($cart_name),
                'cart_items'=>$cart_items,
                'customer' => is_object($customer) ? $customer : null,
                'customer_id' => is_object($customer) ? $customer->id : $customer,
                'customer_group_id' => Cms_Controller::get_customer_group_id(),
                'currency_code'=> self::get_currency($as_object=false),
                'payment_method' => Shop_CheckoutData::get_payment_method(),
                'coupon_code' => Shop_CheckoutData::get_coupon_code(),
            );

            $params = array_merge($params, $options);

            $available_options = Shop_ShippingOption::get_applicable_options($params);
            $discountOptions = self::add_discount_applied_shipping_options($available_options,$params);
            foreach($discountOptions as $option){
                $option->apply_checkout_quote($cart_name);
                $available_options[$option->id]  = $option;
            }

            if (!Shop_ShippingParams::get()->display_shipping_service_errors) {
                foreach ($available_options as $key=>$option)
                {
                    if (strlen($option->error_hint)) {
                        traceLog('ERROR HINT ' . $option->error_hint);
                        unset($available_options[$key]);
                    }
                }
            }
            return  $available_options;
        }

        /**
         * @deprecated
         * @use self::getSelectedShippingQuote()
         *
         * Returns a shipping method information previously set with {@link Shop_CheckoutData::set_shipping_method() setSelectedShippingQuote()} method.
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

            if (!array_key_exists('selected_shipping_quote', $checkout_data))
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
                    'ls_api_code'=>null,
                    'quote_data' => array()
                );
                return (object)$method;
            }

            return $checkout_data['selected_shipping_quote'];
        }

        /**
         * @deprecated
         * @use self::setSelectedShippingQuote()
         * Sets a shipping method.
         * You can use the {@link Shop_ShippingOption::find_by_api_code()} method for finding a specific shipping method.
         * <pre>Shop_CheckoutData::set_shipping_method(Shop_ShippingOption::find_by_api_code('default')->id);</pre>
         * For multi-option shipping methods, like FedEx the <em>$shipping_method_id</em> parameter
         * should contain both shipping method identifier and shipping method specific option identifier,
         * separated with the underscore character, for example: <em>2_9b6fd8f11e836e9c3aceb8933d7a710b</em>
         *
         * @param string $shippingOptionId Specifies a Shop_ShippingOption identifier.
         * @param string $cartName Specifies the shopping cart name.
         */
        public static function set_shipping_method($shippingOptionId = null, $cartName = 'main')
        {


            $selectedShippingOptionId = $shippingOptionId ? $shippingOptionId : post('shipping_option');

            if (strpos($shippingOptionId, '_') !== false)
            {

                //Legacy: Not a shippingOptionId, process as shippingQuoteId
                self::setSelectedShippingQuote($selectedShippingOptionId, $cartName);
                return;
            }

            $shippingOption = Shop_ShippingOption::create();
            $shippingOption = $shippingOption->find($selectedShippingOptionId);
            if(!$shippingOption){
                throw new Cms_Exception('Shipping method not found.');
            }

            self::applySelectedShippingQuote($shippingOption, null, $cartName);
            self::save_custom_fields();
        }

        /**
         * @deprecated
         * @use self::resetSelectedShippingQuote()
         * Deletes the shipping method information from the checkout data.
         * @documentable
         */
        public static function reset_shipping_method()
        {
            self::resetSelectedShippingQuote();
        }

        /**
         * @deprecated
         * @use self::refreshActiveShippingQuote()
         * Refreshes selected shipping quote and re-saves.
         * @documentable
         * @param string $cart_name Specifies the shopping cart name.
         */

        public static function refresh_active_shipping_quote($cart_name = 'main'){
            self::refreshActiveShippingQuote($cart_name);
        }

        /**
         * @deprecated
         * @use self::getShippingTaxes
         * Returns a set of shipping tax rates based on customers cart data
         *
         * @param Shop_ShippingOption $shippingOption
         * @param string $cart_name
         * @return array
         */
        public static function get_shipping_taxes($shippingOption, $cart_name='main'){
            $shipping_taxes = Shop_TaxClass::get_shipping_tax_rates($shippingOption->id, Shop_CheckoutData::get_shipping_info(), $shippingOption->quote_no_tax);
            $return = Backend::$events->fireEvent('shop:onCheckoutGetShippingTaxes', $shipping_taxes, $shippingOption, $cart_name);
            foreach($return as $updated_shipping_taxes) {
                if($updated_shipping_taxes)
                    return $updated_shipping_taxes;
            }

            return $shipping_taxes;
        }

        /**
         * @deprecated
         * Use the getQuotes() method on Shop_ShippingOption
         */
        public static function flatten_shipping_options($shipping_options)
        {
            $result = array();

            foreach ($shipping_options as $option) {
                if (is_a($option, 'Shop_ShippingOptionQuote')) {
                    $result[$option->id] = $option;
                } else if (is_a($option, 'Shop_ShippingOption')) {
                    $quotes = $option->getQuotes();
                    if($quotes){
                        foreach ($quotes as $quote) {
                            $result[$quote->id] = $quote;
                        }
                    }
                    else if($option->is_free) {
                        //Return for legacy code expecting empty quote (zero value, free).
                        $quote = new Shop_ShippingOptionQuote($option->id);
                        $quote->setPrice(0);
                        $result[$quote->id] = $quote;
                    }

                }
            }
            return $result;
        }

        /**
         * @deprecated
         * Not used
         */
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
         * @deprecated Use get_discount_applied_shipping_options()
         * Allows discount rules to expose hidden shipping options
         * @ignore
         * @param array $shipping_options Specifies the shipping option list to flatten.
         * @param array $params Specifies the shipping calculation parameters.
         * @return array Returns an updated array of shipping options.
         */
        protected static function add_discount_applied_shipping_options($shipping_options, $params=array()){

            $payment_method = is_object($params['payment_method']) ? $params['payment_method'] : null;
            $payment_method_obj = $payment_method ? Shop_PaymentMethod::find_by_id( $payment_method->id ) : null;
            $cart_name = isset($params['cart_name'])? $params['cart_name'] : 'main';
            $customer_id = isset($params['customer_id']) ? $params['customer_id'] : Cms_Controller::get_customer();

            $discount_info = Shop_CartPriceRule::evaluate_discount(
                $payment_method_obj,
                null,
                isset($params['cart_items']) ? $params['cart_items'] : Shop_Cart::list_active_items($cart_name),
                isset($params['shipping_info']) ? $params['shipping_info'] : Shop_CheckoutData::get_shipping_info(),
                isset($params['coupon_code']) ? $params['coupon_code'] : Shop_CheckoutData::get_coupon_code(),
                $customer_id,
                isset($params['total_price']) ? $params['total_price'] : Shop_Cart::total_price_no_tax($cart_name, false)
            );
            if ( isset( $discount_info->add_shipping_options ) && count( $discount_info->add_shipping_options ) ) {
                foreach ( $discount_info->add_shipping_options as $option_id ) {
                    $option = Shop_ShippingOption::create()->find( $option_id );
                    if ( $option ) {
                        $shipping_options[$option->id] = $option;
                    }
                }
            }
            return $shipping_options;
        }


    }

