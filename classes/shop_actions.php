<?php

	/**
	 * @has_documentable_methods
	 */
	class Shop_Actions extends Cms_ActionScope
	{
		/*
		 * Category functions
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/category_pag Product Category} page.
		 * The action loads a category object by a category URL name specified in the page URL.
		 *
		 * @action shop:category
		 *
		 * @output Shop_Category $category A category object.
		 * Use this object to display a list of category products and the category name and description.
		 * This variable can be NULL if a category, specified in the URL, was not found. Always check
		 * whether the variable is not NULL and display the "Category not found" message
		 * instead of the normal category page if it is NULL.
		 *
		 * @output string $category_url_name Specifies a requested category URL name.
		 * This variable exists only if the requested category was found.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/category_page Creating the Category Page
		 */
		public function category()
		{
			if (!Shop_ConfigurationRecord::get()->nested_category_urls)
				$category_url_name = $this->request_param(0);
			else
				$category_url_name = Cms_Router::remove_page_segments(strtolower(Phpr::$request->getCurrentUri()));

			if (!strlen($category_url_name))
			{
				$this->data['category'] = null;
				return;
			}

			$params = array();
			$category = Shop_Category::find_by_url($category_url_name, $params);
			if (!$category || $category->category_is_hidden)
			{
				$this->data['category'] = null;
				return;
			}

			$this->data['category'] = $category;
			$this->data['category_url_name'] = $category->get_url_name();

			/*
			 * Override meta
			 */

			$this->page->title = strlen($category->title) ? $category->title : $category->name;
			$this->page->description = strlen($category->meta_description) ? $category->meta_description : $this->page->description;
			$this->page->keywords = strlen($category->meta_keywords) ? $category->meta_keywords : $this->page->meta_keywords;
		}

		/*
		 * Product functions
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/product_page Product Details} page.
		 * The action loads a product by its URL Name specified in the page URL and creates all required PHP variables.
		 *
		 * @input integer $product_cart_quantity Optional field, specifying the number of items to add to the cart.
		 * @input string $add_to_cart The name of the <em>Add To Cart</em> SUBMIT element.
		 * The button name is required only if you use the regular POST (non AJAX) method. Alternatively you can use the
		 * {@link ajax@shop:on_addToCart} handler.
		 * @input string $add_review THe name of the <em>Add Review</em> SUBMIT element.
		 * The button name is required only if you use the regular POST (non AJAX) method. Alternatively you can use the
		 * {@link ajax@shop:on_addProductReview} handler.
		 *
		 * @output Shop_Product $product A product object.
		 * This variable can be NULL if a requested product was not found. Always check whether the variable is NULL and display
		 * the "product not found" message instead of a normal product page if the variable is NULL.
		 * @output boolean $product_unavailable Indicates whether the product is out of stock or disabled.
		 * Check this variable to generate corresponding messages.
		 *
		 * @action shop:product
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/product_page Product Details page
		 * @see ajax@shop:on_addToCart
		 */
		public function product()
		{
			$this->data['product_unavailable'] = false;

			$product_url_name = $this->request_param(0);
			if (!strlen($product_url_name))
			{
				$this->data['product'] = null;
				return;
			}

			$this->data['product'] = null;

			$product_id = post('product_id');
			$specific_product = false;
			if (!strlen($product_id))
			{
				$product = Shop_Product::create()->where('(shop_products.grouped is null or (shop_products.grouped is not null and shop_products.product_id is not null))')->find_by_url_name($product_url_name);
				if ($product && $product->grouped && !$product->product_id)
				{
					$this->data['product'] = null;
					return null;
				}

				$configuration = Shop_ConfigurationRecord::get();
				if ($product && $configuration->product_details_behavior == 'exact')
				{
					$specific_product = true;
					$_POST['product_id'] = $product->id;
				}
			}
			else
			{
				$product = Shop_Product::create()->find_by_id($product_id);
				$specific_product = true;
			}

			if (!$product || $product->disable_completely)
			{
				$this->data['product'] = null;
				return null;
			}

			if ($product && strlen($product->product_id) && $product->master_grouped_product && $product->master_grouped_product->disable_completely)
			{
				$this->data['product'] = null;
				return null;
			}

			if (!$product->visible_for_customer()) {
				$this->data['product'] = null;
				return null;
			}

			/*
			 * Find the first available product in the grouped product list
			 */

			if (!$specific_product)
			{
				if (!Phpr::$config->get('DISABLE_GROUPED_PRODUCTS'))
				{
					$grouped_products = $product->grouped_products;
					if ($grouped_products->count)
						$product = $grouped_products[0];
				}

				if (!$product->enabled || ($product->is_out_of_stock() && $product->hide_if_out_of_stock))
				{
					$this->data['product_unavailable'] = true;
					return;
				}
			}

			if (!$product)
			{
				return;
			}

			$this->data['product'] = $product;
			$this->data['product_unavailable'] = false;

			/*
			 * Override meta
			 */

			$this->page->title = strlen($product->title) ? $product->title : $product->name;
			$this->page->description = strlen($product->meta_description) ? $product->meta_description : $this->page->description;
			$this->page->keywords = strlen($product->meta_keywords) ? $product->meta_keywords : $this->page->meta_keywords;

			/*
			 * Process file uploads
			 */

			if (array_key_exists('product_file', $_FILES))
			{
				$file_data = Phpr_Files::extract_mutli_file_info($_FILES['product_file']);

				foreach ($file_data as $file)
					$product->add_file_from_post($file);
			}

			/*
			 * Handle events
			 */

			if (post('add_to_cart') && !$this->ajax_mode)
				$this->on_addToCart(false);

			if (post('add_review') && !$this->ajax_mode)
				$this->on_addProductReview(false);
		}

		public function on_deleteProductFile()
		{
			$this->action();

			if (isset($this->data['product']))
				$this->data['product']->delete_uploaded_file(post('file_id'));
		}

		/**
		 * Adds a product to the shopping cart.
		 * This AJAX handler can be used on the {@link http://lemonstand.com/docs/product_page Product Details} page or on any other page.
		 * The following example demonstrates the simplest use case, when the handler used on the Product Page. In this case
		 * the product identifier (<em>product_id</em>) field element is not required.
		 * <pre>
		 * <input onclick="return $(this).getForm().sendRequest(
		 *    'shop:on_addToCart',
		 *    {update: {'product_page': 'product_partial'}})"
		 *  type="button" value="Add to cart"/>
		 * </pre>
		 *
		 * @ajax shop:on_addToCart
		 *
		 * @input integer $product_id Specifies the {@link Shop_Product product} identifier.
		 * This field is not required if the handler used on the {@link http://lemonstand.com/docs/product_page Product Details} page.
		 * Example:
		 * <pre>
		 * <input onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_addToCart',
		 *    {
		 *      extraFields: {
		 *        'product_id': <?= $product->id ?>
		 *      },
		 *      onSuccess: function(){ alert('The product has been added to the cart'); }
		 *    })" type="button" value="Add to cart"/>
		 * </pre>
		 * @input boolean $no_flash Disables the "The item has been added to your cart" message.
		 * Possible values: 0, 1. By default the handler puts the message to the flash storage. The message can be displayed with {@link flash_message()} function.
		 * @input string $message The confirmation message string.
		 * Note that you should use the <em>%s</em> symbol in the message, to indicate a place where the product quantity should be inserted. Example:
		 * <pre>
		 * <input onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_addToCart',
		 *   {
		 *       extraFields: {message: 'Number of products added to the cart: %s'},
		 *       update: {'mini_cart': 'shop:mini_cart', 'product_page': 'product_partial'}})"
		 *  type="button" value="Add to cart"/>
		 * </pre>
		 * @input integer $product_cart_quantity Specifies the number of products to add to the cart.
		 * The default value is 1.
		 * @input string $cart_name Specifies the shopping cart name.
		 * LemonStand support multiple shopping cart. By default the cart named <em>main</em> used.
		 * Example:
		 * <pre>
		 * <input onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_addToCart',
		 *    {
		 *      extraFields: {
		 *        'cart_name': 'second_cart'
		 *      },
		 *      onSuccess: function(){ alert('The product has been added to the cart'); }
		 *    })" type="button" value="Add to cart"/>
		 * </pre>
		 * @input string $redirect Optional URL to redirect the browser to.
		 * Example:
		 * <pre>
		 * <input onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_addToCart',
		 *    {
		 *      extraFields: {
		 *        'redirect': '/checkout'
		 *      },
		 *      onSuccess: function(){ alert('The product has been added to the cart'); }
		 *    })" type="button" value="Add to cart"/>
		 * </pre>
		 *
		 * @see http://lemonstand.com/docs/product_page Product page
		 * @see http://lemonstand.com/docs/cart_page Creating the Cart page
		 * @see action@shop:product
		 * @see action@shop:cart
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_addToCart($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->action();

			$quantity = trim(post('product_cart_quantity', 1));

			if (!strlen($quantity) || !preg_match('/^[0-9]+$/', $quantity))
				throw new Cms_Exception('Invalid quantity value.');

			if (!isset($this->data['product']))
			{
				$product_id = post('product_id');
				if (!$product_id)
					throw new Cms_Exception('Product not found.');

				$product = Shop_Product::create()->find_by_id($product_id);
				if (!$product)
					throw new Cms_Exception('Product not found.');

				$this->data['product'] = $product;
			}

			Shop_Cart::add_cart_item($this->data['product'], array(
				'quantity'=>$quantity,
				'cart_name'=>post('cart_name', 'main'),
				'extra_options'=>post('product_extra_options', array()),
				'options'=>post('product_options', array()),
				'custom_data'=>post('item_data', array()),
				'bundle_data'=>post('bundle_data', array())
			));

			if (!post('no_flash'))
			{
				$message = post('message', '%s item(s) added to your cart.');
				Phpr::$session->flash['success'] = sprintf($message, $quantity);
			}

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Adds a product review.
		 * This AJAX handler processes a product review form. Please read
		 * {@link http://lemonstand.com/docs/displaying_product_rating_and_reviews Displaying product rating and reviews}
		 * article to learn how to implement the Write a Review form.
		 *
		 * @ajax shop:on_addProductReview
		 *
		 * @input integer $rating Specifies a product rating.
		 * The rating should be a number (1-5) or empty value. If you do not need to implement the rating feature, you can skip
		 * this field. You can disable the ratings on the System/Settings/Ratings & Reviews Settings page.
		 * @input string $review_title Specifies a review title.
		 * @input string $review_author_name Specifies the review author name.
		 * You can hide this field for logged in customers, because if the customer is logged in,
		 * the review author name will match the customer's name.
		 * @input string $review_author_email Specifies the review author email address.
		 * You can hide this field for logged in customers, because if the customer is logged in,
		 * the review author name will match the customer's email address.
		 * @input string $review_text Specifies the review text.
		 * @input string $product_id Specifies the {@link Shop_Product product} identifier.
		 * This field is optional if the review form is implemented on the {@link http://lemonstand.com/docs/product_page Product Details}
		 * page.
		 * @input string $redirect An optional URL to redirect the browser to.
		 * @input boolean $no_flash Disables the "Your review has been successfully posted." message.
		 * Possible values: 0, 1. By default the handler puts the message to the flash storage. The message can be displayed with
		 * the {@link flash_message()} function.
		 * @input string $message An optional flash message text.
		 * Example:
		 * <pre>
		 * <input type="button" value="Submit" onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_addProductReview', {
		 *     extraFields: {message: 'Your review has been succesfuly posted'},
		 *     update:{'product_page': 'product_partial'}
		 *   })"/>
		 * </pre>
		 *
		 * @output boolean $review_posted This variable is set after successful review post.
		 * This variable is set only if the browser was not redirected to another page.
		 *
		 * @see http://lemonstand.com/docs/displaying_product_rating_and_reviews Displaying product rating and reviews
		 * @see http://lemonstand.com/docs/product_page Product Details page
		 * @see action@shop:product
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_addProductReview($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->action();

			if (!isset($this->data['product']))
			{
				$product_id = post('product_id');
				if (!$product_id)
					throw new Cms_Exception('Product not found.');

				$product = Shop_Product::create()->find_by_id($product_id);
				if (!$product)
					throw new Cms_Exception('Product not found.');

				$this->data['product'] = $product;
			}

			Shop_ProductReview::create_review($this->data['product'], $this->customer, $_POST);
			$this->data['product'] = Shop_Product::create()->find_by_id($this->data['product']->id);

			if (!post('no_flash'))
			{
				$message = post('message', 'Your review has been successfully posted. Thank you!');
				Phpr::$session->flash['success'] = $message;
			}

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);

			$this->data['review_posted'] = true;
		}

		/*
		 * Cart functions
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/cart_page Cart} page.
		 * This action can handle the shopping cart content management requests like removing items from the cart, moving items
		 * to the Postponed list and changing item quantity.
		 *
		 * <h3>Managing content of a specific shopping cart</h3>
		 * LemonStand supports multiple shopping carts. Using the shop:cart action you can manage a content of any specific
		 * shopping cart. All handlers and actions which work with shopping cart accept the <em>$cart_name</em> parameter,
		 * which specifies a name of a cart. By default LemonStand works with a shopping cart named <em>main</em>.
		 *
		 * In order to display and manage a content of a specific shopping cart, need:
		 * <ol>
		 *   <li>Pass the <em>cart_name</em> parameter to the shop:cart action before the action is executed.
		 *     You can do it using the following code on the Pre Action Code field of the Create/Edit Page form:
		 *     <pre>$_POST['cart_name'] = 'my_second_cart';</pre>
		 *   </li>
		 *   <li>Pass the <em>cart_name</em> parameter to all AJAX handlers which you invoke on the Cart page.
		 *     The simplest way to do it is to add a hidden field to the form which wraps your cart content:
		 *     <pre><input type="hidden" name="cart_name" value="my_second_cart"/></pre>
		 *   </li>
		 * </ol>
		 * Also the <em>cart_name</em> parameter should be passed to the checkout actions if you want LemonStand to
		 * create an order from a specific shopping cart items.
		 *
		 * @action shop:cart
		 *
		 * @output Db_DataCollection $countries A collection of countries for populating the country list for the Estimate Shipping Cost feature.
		 * Each element in the collection is an object of the {@link Shop_Country} class.
		 * @output Db_DataCollection $states A list of states for a currently selected country.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class.
		 * @output Shop_CheckoutAddressInfo $shipping_info A customer's shipping location.
		 * For new customers all fields in the object are empty.
		 * @output float $discount A discount value, calculated using the price rules, defined on the {@link http://lemonstand.com/docs/shopping_cart_price_rules/ Shop/Discounts page} in the Administration Area.
		 * The discount value can be not accurate on the Cart page, because price rules can refer to customer details like the shipping
		 * location which are not known on the Cart page and will become available only during the checkout process.
		 * @output array $applied_discount_rules A list of discount rules applied to the cart products.
		 * Each element in the array is an object with two fields: <em>rule</em> ({@link Shop_CartPriceRule} object) and <em>discount</em>.
		 * You can use this variable for displaying a list of applied discounts. Example:
		 * <pre>
		 * <h3>Applied discounts</h3>
		 * <? foreach ($applied_discount_rules as $rule_info): ?>
		 *   <p>
		 *     <?= $rule_info->rule->name ?>
		 *     <?= $rule_info->rule->description ?> -
		 *     <?= format_currency($rule_info->discount) ?><br/>
		 *   </p>
		 * <? endforeach ?>
		 * </pre>
		 * @output float $cart_total A sum of all cart items.
		 * @output float $subtotal A sum of all cart items. Matches the $cart_total variable value.
		 * @output float $cart_total_tax_incl Cart total, including tax.
		 * If the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} feature
		 * is enabled, this variable value will match the <em>$cart_total</em> variable value.
		 * @output float $cart_tax A total tax amount for all cart items.
		 * @output array $cart_taxes A list of all taxes applied to the cart items.
		 * Each element in the array is an object with two fields: <em>name</em>, <em>total</em>.
		 * @output string $coupon_code A coupon code provided by the customer.
		 *
		 * @input array $item_postponed A checkbox-type type INPUT element, responsible for moving an item to or from the postponed items list.
		 * Example:
		 * <pre>
		 * <input type="hidden" name="item_postponed[<?= $item->key ?>]" value="0"/>
		 * <input type="checkbox" <?= checkbox_state($item->postponed) ?> name="item_postponed[<?= $item->key ?>]" value="1"/>
		 * </pre>
		 * The hidden element used here to provide the default value 0 for cases when the checkbox is not checked. By default browsers
		 * do not send checkbox values if they are not checked. Please always use this method with the item_postponed checkbox.
		 * @input array $item_quantity Item quantity value. Allows users to manage quantities of items in the cart.
		 * Example: <pre><input type="text" name="item_quantity[<?= $item->key ?>]" value="<?= $item->quantity ?>"/></pre>
		 * @input string $coupon A coupon code.
		 * If specified, LemonStand will apply the coupon code and re-evaluate the <em>$discount</em> variable
		 * @ajax on_action Applies any changes on the Cart page.
		 * The handler applies item quantities, removes items marked for removing, applies a coupon code, updates items postpone status.
		 * This handler is equal to the regular form POST method, but works through AJAX. Use this request to create the <em>Apply Changes</em>
		 * button on the Cart page. Example:
		 * <pre>
		 * <input onclick="return $(this).getForm().sendRequest(
		 *   'on_action',
		 *   {update: {'cart_page': 'cart_partial'}})"
		 * type="image" src="/resources/images/btn_apply.gif" />
		 * </pre>
		 * @input string $cart_name An optional shopping cart name.
		 * By default all Shop module actions and AJAX handlers work with the cart named <em>main</em>.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/cart_page Creating the Cart page
		 * @see action@shop:checkout
		 * @see ajax@shop:on_deleteCartItem
		 * @see ajax@shop:on_evalShippingRate
		 * @see ajax@shop:on_setCouponCode
		 */
		public function cart()
		{
			$cart_name = post('cart_name', 'main');

			$delete_items = post('delete_item', array());
			foreach ($delete_items as $key)
				Shop_Cart::remove_item($key, $cart_name);

			$postpone_items = post('item_postponed', array());
			Shop_Cart::change_postpone_status($postpone_items, $cart_name);

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$countries = $this->data['countries'] = Shop_Country::get_list($shipping_info->country);
			$shipping_country = $shipping_info->country ? $shipping_info->country : $countries[0]->id;

			$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $shipping_country)->order('name')->find_all();
			$this->data['shipping_info'] = $shipping_info;

			$cart_exception = null;
			try
			{
				if (post('set_coupon_code'))
				{
					$this->on_setCouponCode();
				}
				else
					$this->cart_applyQuantity($cart_name);
			} catch (exception $ex)
			{
				$cart_exception = $ex;
			}

			$this->cart_apply_custom_data($cart_name);
			$this->eval_cart_variables($cart_name);

			if ($cart_exception)
				throw $cart_exception;
		}

		protected function eval_cart_variables($cart_name)
		{
			$discount_info = Shop_CheckoutData::eval_discounts($cart_name);

			if (!Shop_CheckoutData::display_prices_incl_tax())
				$this->data['discount'] = $discount_info->cart_discount;
			else
				$this->data['discount'] = $discount_info->cart_discount_incl_tax;

			$this->data['subtotal'] = Shop_Cart::total_price($cart_name, true);
			$this->data['subtotal_no_discounts'] = Shop_Cart::total_price($cart_name, false);
			$this->data['cart_total'] = $cart_total = $this->data['subtotal'];
			$this->data['applied_discount_rules'] = $discount_info->applied_rules_info;
			$this->data['cart_total_tax_incl'] = Shop_Cart::total_price($cart_name, true, null, true);
			$this->data['cart_tax'] = Shop_Cart::total_tax($cart_name);
			$cart_taxes_details = Shop_TaxClass::calculate_taxes(Shop_Cart::list_active_items($cart_name), Shop_CheckoutData::get_shipping_info());
			$this->data['cart_taxes'] = $cart_taxes_details->taxes;
			$this->data['estimated_total'] = max(0, $cart_total);
			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();
		}

		/**
		 * Allows to add the <em>Estimate shipping cost</em> feature to the {@link http://lemonstand.com/docs/cart_page Cart page}.
		 * Usually this AJAX handler used on the {@link http://lemonstand.com/docs/cart_page Cart page} together with {@link action@shop:cart} action.
		 *
		 * Please read the {@link http://lemonstand.com/docs/implementing_the_shipping_cost_estimator_feature Implementing the Shipping Cost Estimator Feature article}
		 * for more information.
		 *
		 * @input integer $country Specifies a {@link Shop_Country country} identifier.
		 * @input integer $state Specifies a {@link Shop_CountryState state} identifier.
		 * @input string $zip Specifies the ZIP/postal code.
		 * @input boolean $is_business Determines whether the shipping location is a business address. Optional.
		 * Accepted values are 0, 1.
		 *
		 * @output array $shipping_options A list of shipping options.
		 * Each element in the array is an object of the {@link Shop_ShippingOption} class.
		 * @output array $shipping_options_flat A flat list of shipping options.
		 * In the flat list all options, including options returned by multi-option shipping methods, like FedEx, are presented in a single list.
		 * Each element in the array is an object of the {@link Shop_ShippingOption} class.
		 *
		 * @ajax shop:on_evalShippingRate
		 * @see http://lemonstand.com/docs/implementing_the_shipping_cost_estimator_feature Implementing the Shipping Cost Estimator Feature
		 * @see http://lemonstand.com/docs/cart_page Cart page
		 * @see action@shop:cart
		 * @package shop.ajax handlers
		 * @author LemonStand eCommerce Inc.
		 */
		public function on_evalShippingRate()
		{
			$cart_name = post('cart_name', 'main');

			$zip = trim(post('zip'));
			if (!strlen($zip))
				throw new Cms_Exception('Please specify a ZIP code.');

			$total_price = Shop_Cart::total_price_no_tax($cart_name);
			$total_volume = Shop_Cart::total_volume($cart_name);
			$total_weight = Shop_Cart::total_weight($cart_name);
			$total_item_num = Shop_Cart::get_item_total_num($cart_name);

			Shop_CheckoutData::set_shipping_location(post('country'), post('state', null), $zip);
			$info = Shop_CheckoutData::get_shipping_info();
			$info->is_business = post('is_business');
			Shop_CheckoutData::set_shipping_info($info);

			$available_options = Shop_CheckoutData::list_available_shipping_options($this->customer, $cart_name);

			$this->data['shipping_options'] = $available_options;
			$this->data['shipping_options_flat'] = Shop_CheckoutData::flatten_shipping_options($available_options);
		}

		/**
		 * Removes an item from the cart.
		 * Usually this AJAX handler used on the {@link http://lemonstand.com/docs/cart_page Cart page} together with {@link action@shop:cart} action.
		 * You can use this handler for creating the <em>Remove Item</em> links for individual cart items. The following example creates the <em>Remove Item</em>
		 * link which sends the shop:on_deleteCartItem AJAX request to LemonStand and updates the cart page content on success.
		 * <pre>
		 * <a onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_deleteCartItem',
		 *   {update: {'cart_page': 'cart_partial'},
		 *   confirm: 'Do you really want to remove this item from the cart?',
		 *   extraFields: {key: '<?= $item->key ?>'}})"
		 * href="#">Remove Item</a>
		 * </pre>
		 *
		 * @output Db_DataCollection $countries A collection of countries for populating the country list for the Estimate Shipping Cost feature.
		 * Each element in the collection is an object of the {@link Shop_Country} class.
		 * @output Db_DataCollection $states A list of states for a currently selected country.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class.
		 * @output Shop_CheckoutAddressInfo $shipping_info A customer's shipping location.
		 * For new customers all fields in the object are empty.
		 * @output float $discount A discount value, calculated using the price rules, defined on the {@link http://lemonstand.com/docs/shopping_cart_price_rules/ Shop/Discounts page} in the Administration Area.
		 * The discount value can be not accurate on the Cart page, because price rules can refer to customer details like the shipping
		 * location which are not known on the Cart page and will become available only during the checkout process.
		 * @output array $applied_discount_rules A list of discount rules applied to the cart products.
		 * Each element in the array is an object with two fields: <em>rule</em> ({@link Shop_CartPriceRule} object) and <em>discount</em>.
		 * You can use this variable for displaying a list of applied discounts. Example:
		 * <pre>
		 * <h3>Applied discounts</h3>
		 * <? foreach ($applied_discount_rules as $rule_info): ?>
		 *   <p>
		 *     <?= $rule_info->rule->name ?>
		 *     <?= $rule_info->rule->description ?> -
		 *     <?= format_currency($rule_info->discount) ?><br/>
		 *   </p>
		 * <? endforeach ?>
		 * </pre>
		 * @output float $cart_total A sum of all cart items.
		 * @output float $subtotal A sum of all cart items. Matches the $cart_total variable value.
		 * @output float $cart_total_tax_incl Cart total, including tax.
		 * If the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} feature
		 * is enabled, this variable value will match the <em>$cart_total</em> variable value.
		 * @output float $cart_tax A total tax amount for all cart items.
		 * @output array $cart_taxes A list of all taxes applied to the cart items.
		 * Each element in the array is an object with two fields: <em>name</em>, <em>total</em>.
		 * @output string $coupon_code A coupon code provided by the customer.
		 *
		 * @input string $key Specifies a key of a {@link Shop_CartItem shopping cart item} to remove, required.
		 * @input string $cart_name Specifies the shopping cart name, the default value is <em>main</em>.
		 *
		 * @ajax shop:on_deleteCartItem
		 * @see http://lemonstand.com/docs/cart_page Cart page
		 * @see action@shop:cart
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_deleteCartItem()
		{
			$cart_name = post('cart_name', 'main');

			$this->data['countries'] = Shop_Country::get_list();
			Shop_Cart::remove_item(post('key'), $cart_name);

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$countries = $this->data['countries'] = Shop_Country::get_list($shipping_info->country);
			$shipping_country = $shipping_info->country ? $shipping_info->country : $countries[0]->id;

			$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $shipping_country)->order('name')->find_all();
			$this->data['shipping_info'] = $shipping_info;

			$this->eval_cart_variables($cart_name);
		}

		private function cart_applyQuantity($cart_name)
		{
			$this->data['countries'] = Shop_Country::get_list();
			$quantity = post('item_quantity', array());

			$filtered_list = array();
			foreach ($quantity as $key=>$quantity)
			{
				$quantity = trim($quantity);

				if (!preg_match('/^[0-9]+$/', $quantity))
				{
					$item = Shop_Cart::find_item($key, $cart_name);
					if ($item)
						throw new Cms_Exception('Invalid quantity value for '.$item->product->name.' product.');
				}

				$item = Shop_Cart::find_item($key, $cart_name);

				if (($item && $item->get_quantity() == $quantity) || !$item)
					continue;

				$filtered_list[$key] = $quantity;
			}

			foreach ($filtered_list as $key=>$quantity)
				Shop_Cart::set_quantity($key, $quantity, $cart_name);

			if (array_key_exists('coupon', $_POST))
				$this->on_setCouponCode(false);
		}

		private function cart_apply_custom_data($cart_name)
		{
			$custom_data = post('item_data', array());
			foreach ($custom_data as $key=>$data)
				Shop_Cart::set_custom_data($key, $data, $cart_name);
		}

		/*
		 * Session functions
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/customer_login_and_logout Login} page.
		 * Also, the action allows to create combined pages with both the login and signup forms.
		 * @action shop:login
		 *
		 * @input string $email {Login form} Specifies a customer email address. Required.
		 * @input string $password {Login form} Specifies a customer password. Required.
		 * @input string $login {Login form} Specifies a name of a submit button element for the <em>POST</em> (non AJAX) form submit method.
		 * @input string $redirect {Login and Sign up forms} An optional URL string for redirecting a visitor's browser after the successful logging in.
		 * Use {@link root_url()} function if your copy of LemonStand is installed in a subdirectory. To redirect to the originally
		 * requested page you can use the {@link Cms_Controller::redirect_url()} method. Example:
		 * <pre><input type="hidden" value="<?= $this->redirect_url('/') ?>" name="redirect"/></pre>
		 *
		 * @input string $first_name {Sign up form} Specifies the customer first name. Required.
		 * @input string $last_name {Sign up form} Specifies the customer last name.Required.
		 * @input string $email {Sign up form} Specifies the customer email.Required.
		 * @input string $password {Sign up form} Specifies the customer password. Optional.
		 * @input string $password_confirm {Sign up form} Specifies the customer password confirmation.
		 * This filed is required if the <em>password</em> field is presented on the form.
		 * @input string $signup {Sign up form} Specifies a name of a submit button element for the <em>POST</em> (non AJAX) form submit method.
		 * @input string $flash {Sign up form} Specifies a message to display on the target redirection page.
		 * Use the {@link flash_message()} function on the target page to display the message.
		 * @input boolean $customer_auto_login {Sign up form} Enables the automatic customer login feature.
		 * Accepted values are: 0, 1.
		 *
		 * @ajax shop:on_login Processes the customer login form.
		 * Use this handler for creating the Login link or button on the Login form. Example:
		 * <pre><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_login')">Login</a></pre>
		 *
		 * @ajax shop:on_signup Processes the customer signup form.
		 * Use this handler for creating the Signup link or button on the Signup form. Example:
		 * <pre><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_signup')">Signup</a></pre>
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/customer_login_and_logout Customer Login and Logout
		 * @see http://lemonstand.com/docs/customer_registration_page Customer registration page
		 */
		public function login()
		{
			if (post('login'))
				$this->on_login();
			elseif (post('signup'))
				$this->on_signup();
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/customer_registration_page Customer Signup} page.
		 * The action processes the signup form data, creates a customer account and sends an email notification to the customer.
		 *
		 * @action shop:signup
		 *
		 * @input string $first_name Specifies the customer first name. Required.
		 * @input string $last_name Specifies the customer last name. Required.
		 * @input string $email Specifies the customer email. Required.
		 * @input string $signup A name of the SUBMIT button element for the POST (non AJAX) form submit method.
		 * The submit button should have name <em>signup</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_signup} handler.
		 * @input string $company Specifies the customer's company name.
		 * @input string $phone Specifies the phone number.
		 * @input integer $billing_state_id An identifier of the customer billing {@link Shop_CountryState state}.
		 * @input integer $billing_country_id An identifier of the customer billing {@link Shop_Country country}.
		 * @input string $billing_street_addr Specifies the billing street address.
		 * @input string $billing_city Specifies the billing city name.
		 * @input string $billing_zip Specifies the billing ZIP/Postal code.
		 * @input string $redirect An optional URL to redirect the visitor's browser after the signup.
		 * Note that if your copy of LemonStand is installed in a subdirectory, you need to use the {@link root_url()} function
		 * in the <em>redirect</em> field value.
		 * @input string $flash A message to display on the target redirection page.
		 * Use the {@link flash_message()} function on the target page to display the message.
		 *
		 * @ajax shop:on_signup Processes the customer signup form.
		 * Use this handler for creating the Signup link or button on the Signup form. Example:
		 * <pre><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_signup')">Signup</a></pre>
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/customer_registration_page Customer Signup page
		 * @see action@shop:login
		 */
		public function signup()
		{
			if (post('signup'))
				$this->on_signup();
		}

		public function on_login()
		{
			if (post('flash'))
				Phpr::$session->flash['success'] = post('flash');

			$redirect = post('redirect');
			$validation = new Phpr_Validation();

			$customer = Shop_Customer::create()
				->where('email=?', post('email'))
				->where('shop_customers.password=?', Phpr_SecurityFramework::create()->salted_hash(post('password')))
				->where('(shop_customers.guest is null or shop_customers.guest=0)')->find();

			if ($customer && $customer->deleted_at) {
				$validation->add('email')->focusId('login_email');
				$validation->setError( "Your customer account was deleted.", 'email', true );
			}

			if (!Phpr::$frontend_security->login($validation, $redirect, post('email'), post('password')))
			{
				$validation->add('email')->focusId('login_email');
				$validation->setError( "Invalid email or password.", 'email', true );
			}
		}

		public function on_signup()
		{
			$customer = new Shop_Customer();
			$customer->disable_column_cache('front_end', false);

			$customer->init_columns_info('front_end');
			$customer->validation->focusPrefix = null;
			$customer->validation->getRule('email')->focusId('signup_email');

			if (!array_key_exists('password', $_POST))
				$customer->generate_password();

			$shipping_params = Shop_ShippingParams::get();

			if (!post('shipping_country_id'))
			{
				$customer->shipping_country_id = $shipping_params->default_shipping_country_id;
				$customer->shipping_state_id = $shipping_params->default_shipping_state_id;
			}

			if (!post('shipping_zip'))
				$customer->shipping_zip = $shipping_params->default_shipping_zip;

			if (!post('shipping_city'))
				$customer->shipping_city = $shipping_params->default_shipping_city;

			$customer->save($_POST);
			$customer->send_registration_confirmation();

			if (post('flash'))
				Phpr::$session->flash['success'] = post('flash');

			if (post('customer_auto_login'))
				Phpr::$frontend_security->customerLogin($customer->id);

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Allows to create a text field for entering a coupon code and a button for processing the code and redirecting the browser to a specific page.
		 * Usually this AJAX handler used on the {@link http://lemonstand.com/docs/cart_page Cart page} together with {@link action@shop:cart} action.
		 * You can use this handler for creating the AJAX driven Checkout button on the Cart  page. Example:
		 * <pre>
		 *   <label for="coupon_code">Do you have a coupon?</label> <input id="coupon_code" type="text" name="coupon"/>
		 *   <input type="button" value="Checkout!" onclick="return $(this).getForm().sendRequest('shop:on_setCouponCode')"/>
		 *   <input type="hidden" name="redirect" value="/checkout_start"/>
		 * </pre>
		 *
		 * @input string $coupon Specifies the coupon code, required.
		 * @input string $redirect Specifies an URL to redirect the browser to, optional.
		 * Note that if your copy of LemonStand is installed in a subdirectory, you need to use the {@link root_url()} function
		 * in the <em>redirect</em> field value.
		 * @ajax shop:on_setCouponCode
		 * @see http://lemonstand.com/docs/cart_page Cart page
		 * @see action@shop:cart
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_setCouponCode($allow_redirect = true)
		{
			$coupon_code = trim(post('coupon'));
			$return = Backend::$events->fireEvent('shop:onBeforeSetCouponCode', $coupon_code);

			foreach($return as $changed_code)
			{
				if($changed_code === false)
					throw new Cms_Exception('The entered coupon cannot be used.');
				elseif($changed_code)
					$coupon_code = $changed_code;
			}

			if (strlen($coupon_code))
			{
				$coupon = Shop_Coupon::find_coupon($coupon_code);
				if (!$coupon)
					throw new Cms_Exception('A coupon with the specified code is not found');
				$validation_result = Shop_CartPriceRule::validate_coupon_code($coupon_code, $this->customer);
				if($validation_result !== true)
					throw new Cms_Exception($validation_result);
			}

			Shop_CheckoutData::set_coupon_code($coupon_code);

			$redirect = post('redirect');
			if ($allow_redirect && $redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/password_restore_page Password Restore} page.
		 * The action generates a new password for a customer with the specified email address, and sends an
		 * email notification to the customer.
		 *
		 * @action shop:password_restore
		 *
		 * @input string $email Specifies the customer's email address. Required.
		 * @input string $password_restore Optional SUBMIT input element name.
		 * The submit button should have name <em>password_restore</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_passwordRestore} handler.
		 * @input string $redirect An optional field containing an URL for redirecting the browser after the successful password reset.
		 * Use {@link root_url()} function if your copy of LemonStand is installed in a subdirectory.
		 * @input string $flash An optional message to display after the redirection.
		 * Use the {@link flash_message()} function the target page to display the message.
		 *
		 * @ajax shop:on_passwordRestore Processes the password restore form.
		 * Use this handler for creating a Submit link or button on the password restore page. Example:
		 * <pre><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_passwordRestore')">Submit</a></pre>
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/password_restore_page Password Restore page
		 */
		public function password_restore()
		{
			if (post('password_restore'))
				$this->on_passwordRestore();
		}

		public function on_passwordRestore()
		{
			$validation = new Phpr_Validation();
			$validation->add('email', 'Email')->fn('trim')->required('Please specify your email address')->email()->fn('mb_strtolower');
			if (!$validation->validate($_POST))
				$validation->throwException();

			try
			{
				Shop_Customer::reset_password($validation->fieldValues['email']);

				if (post('flash'))
					Phpr::$session->flash['success'] = post('flash');

				$redirect = post('redirect');
				if ($redirect)
					Phpr::$response->redirect($redirect);
			}
			catch (Exception $ex)
			{
				throw new Cms_Exception($ex->getMessage());
			}
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/change_password_page Change Password} page.
		 * On this page a customer can change a password used for logging into the store. Note that the action requires a logged in customer,
		 * so a page, the action is assigned to, must has the <em>Customers Only</em> security mode enabled.
		 *
		 * The {@link ajax@shop:on_changePassword} AJAX handler allows to implement AJAX version of the page.
		 *
		 * @action shop:change_password
		 *
		 * @input string $old_password Specifies the old customer's password. Required.
		 * @input string $password Specifies the new password. Required.
		 * @input string $password_confirm Specifies the password confirmation. Required.
		 * @input string $redirect An optional field containing an URL for redirecting a visitor's browser after
		 * the successful password update. Note that you should use the {@link root_url()} function if your copy of LemonStand is installed in a subdirectory.
		 * @input string $flash An optional message to display after the redirection.
		 * Use the {@link flash_message()} function the target page to display the message.
		 * @input string $change_password Optional SUBMIT input element name.
		 * The submit button should have name <em>change_password</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link ajax@shop:on_changePassword} handler.
		 *
		 * @output Shop_Customer $customer A customer object, representing a currently logged in customer.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/change_password_page Creating the Change Password page
		 * @see ajax@shop:on_changePassword
		 */
		public function change_password()
		{
			$this->data['customer'] = $this->customer;

			if (post('change_password'))
				$this->on_changePassword();
		}

		/**
		 * Handles the change customer password request.
		 * Usually this handler used on the {@link http://lemonstand.com/docs/change_password_page Change Password} page,
		 * together with the {@link action:@shop:change_password} action.
		 * The following code example creates a Change Password link, which triggers the AJAX request:
		 * <pre><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_changePassword')">Submit</a></pre>
		 *
		 * @input string $old_password Specifies the old customer's password. Required.
		 * @input string $password Specifies the new password. Required.
		 * @input string $password_confirm Specifies the password confirmation. Required.
		 * @input string $redirect An optional field containing an URL for redirecting a visitor's browser after
		 * the successful password update. Note that you should use the {@link root_url()} function if your copy of LemonStand is installed in a subdirectory.
		 * @input string $flash An optional message to display after the redirection.
		 * Use the {@link flash_message()} function the target page to display the message.
		 *
		 * @ajax shop:on_changePassword
		 * @see http://lemonstand.com/docs/change_password_page Creating the Change Password page
		 * @see action@shop:change_password
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_changePassword()
		{
			$validation = new Phpr_Validation();
			$validation->add('old_password', 'Old Password')->fn('trim')->required("Please specify the old password");
			$validation->add('password', 'Password')->fn('trim')->required("Please specify new password");
			$validation->add('password_confirm', 'Password Confirmation')->fn('trim')->matches('password', 'Password and confirmation password do not match.');

			if (!$validation->validate($_POST))
				$validation->throwException();

			if (Phpr_SecurityFramework::create()->salted_hash($validation->fieldValues['old_password']) != $this->customer->password)
				$validation->setError('Invalid old password.', 'old_password', true);

			try
			{
				$customer = Shop_Customer::create()->where('id=?', $this->customer->id)->find(null, array(), 'front_end');
				$customer->disable_column_cache('front_end', true);
				$customer->password = $validation->fieldValues['password'];
				$customer->password_confirm = $validation->fieldValues['password_confirm'];
				$customer->save();

				if (post('flash'))
					Phpr::$session->flash['success'] = post('flash');

				$redirect = post('redirect');
				if ($redirect)
					Phpr::$response->redirect($redirect);
			}
			catch (Exception $ex)
			{
				throw new Cms_Exception($ex->getMessage());
			}
		}

		/*
		 * Checkout functions
		 */

		/**
		 * Base action for the {@link Checkout} page.
		 * The checkout process is split to several steps. A current step name is always available through the <em>$checkout_step</em> field.
		 * The field can have the following values:
		 * <ul>
		 *   <li><em>billing_info</em> - corresponds to the Billing Information checkout step.</li>
		 *   <li><em>shipping_info</em> - corresponds to the Shipping Information checkout step.</li>
		 *   <li><em>shipping_method</em> - corresponds to the Shipping Method checkout step.</li>
		 *   <li><em>payment_method</em> - corresponds to the Payment Method checkout step.</li>
		 *   <li><em>review</em> - corresponds to the Order Review checkout step.</li>
		 * </ul>
		 * A set of form fields required and supported by the action depends on a current checkout step. The <em>$checkout_step</em> field is required on each step.
		 *
		 * @input string $checkout_step {Any checkout step} Specifies a current checkout step. Required.
		 * Depending on a value of this field, LemonStand validates corresponding form fields and switches the checkout
		 * process to a next step, when a customer clicks the Next button.
		 * @input string $skip_to {Any checkout step} Allows to switch the checkout process to a specific step.
		 * @input string $auto_skip_shipping {Any checkout step} Allows to skip the Shipping Method step if the shopping cart contains only non-shippable items.
		 * Please read {@link http://lemonstand.com/docs/skipping_the_shipping_method_step_for_downloadable_products_or_services Skipping the Shipping Method step for downloadable products or services} article
		 * to learn how to configure LemonStand to skip the shipping method checkout step.
		 * @input string $empty_cart {Any checkout step} Allows to leave the cart content after the order is placed.
		 * This allows your customers to return to previous checkout step even if they already placed the order. The default value is 0 (false).
		 * @input string $cart_name {Any checkout step} Optional field, the shopping cart name. Use it if you want to the checkout to work with a specific shopping cart.
		 * Please refer to the {@link action@shop:cart} action documentation for the multiple shopping cart feature description.
		 *
		 * @input string $first_name {Billing and Shipping information steps} Specifies the customer billing first name. Required.
		 * @input string $last_name {Billing and Shipping information steps} Specifies the customer billing last name. Required.
		 * @input string $email {Billing and Shipping information steps} Specifies the customer email address. Required.
		 * @input string $company {Billing and Shipping information steps} Specifies the customer company name. Optional.
		 * @input string $phone {Billing and Shipping information steps} Specifies the customer phone number. Optional.
		 * @input string $street_address {Billing and Shipping information steps} Specifies the customer billing street address. Required.
		 * @input string $city {Billing and Shipping information steps} Specifies the customer billing city. Required.
		 * @input string $zip {Billing and Shipping information steps} Specifies the customer billing ZIP code. Required.
		 * @input string $country {Billing and Shipping information steps} Specifies the customer {@link Shop_Country billing country} identifier. Required.
		 * @input string $state {Billing and Shipping information steps} Specifies the customer {@link Shop_CountryState billing state} identifier. Required.
		 * @input boolean $is_business {Shipping information step} Determines whether the shipping location is a business address. Optional.
		 * Accepted values are 0, 1.
		 *
		 * @input boolean $register_customer {Billing information step} Use this field if you want a customer to be automatically registered on checkout.
		 * Accepted values are: 0, 1. If this field is not declared or its value is 0 (default), the following fields have no effect:
		 * <ul>
		 *   <li>allow_empty_password</li>
		 *   <li>customer_password</li>
		 *   <li>customer_password_confirm</li>
		 *   <li>no_password_error</li>
		 *   <li>passwords_match_error</li>
		 *   <li>customer_exists_error</li>
		 * </ul>
		 * Please refer to the {@link http://lemonstand.com/docs/automatic_customer_registration Automatic customer registration} article for the implementation details.
		 * @input boolean $allow_empty_password {Billing information step} Determines whether customers should enter a password to register.
		 * Accepted values are: 0, 1. If the field value is 0, the password is not registered. In this case LemonStand will generate a random password.
		 * The default value is 0. You can set the field value to 1 and omit the customer password fields (<em>customer_password</em>, <em>customer_password_confirm</em>).
		 * The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 * @input string $customer_password {Billing and Shipping information steps} Specifies the customer password.
		 * This field is optional if the <em>allow_empty_password</em> field has value of 1.
		 * The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 * @input string $customer_password_confirm {Billing and Shipping information steps} Specifies the customer password confirmation.
		 * Use this field along with the <em>customer_password</em> field.
		 * The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 * @input string $no_password_error {Billing information step} Specifies an error message to display in case if the customer did not specify the password.
		 * Default value is "Please enter your password.".
		 * The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 * @input string $passwords_match_error {Billing information step} Specifies an error message to display in case if the password and its confirmation do not match.
		 * Default value is "Password and confirmation password do not match.".
		 * The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 * @input string $customer_exists_error {Billing information step} Specifies an error to display in case if a customer with the specified email address already exists.
		 * Default value is "A customer with the specified email is already registered. Please log in or use another email.".
		 * The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 * @input boolean $customer_auto_login {Review step} Determines whether the new customer should be automatically logged in customer in after placing the order.
		 * Accepted values are: 0, 1. The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 * @input boolean $customer_registration_notification {Review step} Determines whether a registration notification email message should be sent to the new customer.
		 * LemonStand uses an email template with the <em>shop:registration_confirmation</em> code for the registration notification. Accepted values are: 0, 1.
		 * The field has no effect if the <em>register_customer</em> field value is 0 (default).
		 *
		 * @input mixed $shipping_option {Shipping method step} Specifies an identifier of selected {@link Shop_ShippingOption shipping method}.
		 * @input mixed $payment_method {Payment method step} Specifies an identifier of selected {@link Shop_PaymentMethod payment method}.
		 *
		 * @output string $checkout_step {Any checkout step} Specifies a current checkout step.
		 * @output Shop_CheckoutAddressInfo $billing_info {Any checkout step} An object of the {@link Shop_CheckoutAddressInfo} class containing the customer's billing name and address.
		 * @output Shop_CheckoutAddressInfo $shipping_info {Any checkout step} An object of the {@link Shop_CheckoutAddressInfo} class containing the customer's shipping name and address.
		 * @output object $shipping_method {Any checkout step} A PHP object representing a selected shipping method.
		 * The object has the following fields:
		 * <ul>
		 *   <li><em>id</em> - an identifier of the shipping method in LemonStand database.</li>
		 *   <li><em>quote</em> - specifies the shipping quote.</li>
		 *   <li><em>name</em> - specifies the shipping method name.</li>
		 * </ul>
		 * @output object $payment_method {Any checkout step} A PHP object representing a selected payment method.
		 * The object has the following fields:
		 * <ul>
		 *   <li><em>id</em> - an identifier of the payment method in LemonStand database.</li>
		 *   <li><em>name</em> - specifies the payment method name.</li>
		 * </ul>
		 * @output string $coupon_code {Any checkout step} Specifies a coupon code entered by a visitor.
		 * @output float $discount {Any checkout step} Specifies an estimated cart discount.
		 * You can display this value during the checkout process.
		 * @output array $applied_discount_rules {Any checkout step} Contains a list of discount rules applied to the cart products.
		 * Each element in the array is an object with two fields:
		 * <ul>
		 *   <li><em>rule</em> - {@link Shop_CartPriceRule} Discount rule object.</li>
		 *   <li><em>discount</em> - specifies the discount amount.</li>
		 * </ul>
		 * You can use this variable for displaying a list of applied discounts. Example:
		 * <pre>
		 * <h3>Applied discounts</h3>
		 * <? foreach ($applied_discount_rules as $rule_info): ?>
		 *   <p>
		 *     <?= $rule_info->rule->name ?>
		 *     <?= $rule_info->rule->description ?> -
		 *     <?= format_currency($rule_info->discount) ?>
		 *   </p>
		 * <? endforeach ?>
		 * </pre>
		 * @output float $cart_total {Any checkout step} Specifies the cart total value (subtotal).
		 * @output float $estimated_total {Any checkout step} Specifies an estimated total value.
		 * This value is calculated on each step of the checkout process, taking into account price rules and known information about the customer.
		 * @output float $estimated_tax {Any checkout step} Specifies an estimated tax value.
		 * This value is calculated on each step of the checkout process, taking into account price rules (defined on the
		 * {@link http://lemonstand.com/docs/shopping_cart_price_rules/ Shop/Discounts page} page) and
		 * known information about the customer. The tax value includes the goods tax and shipping tax.
		 * @output boolean $shipping_required {Any checkout step} Determines whether the shopping cart contains only non-shippable items.
		 * This variable is FALSE if the shopping cart contains only non-shippable items (downloadable  products or services).		 *
		 *
		 * @output Db_DataCollection $countries {Billing and Shipping information steps} A collection of countries for populating the Country list.
		 * Each element in the collection is an object of the {@link Shop_Country} class.
		 * @output Db_DataCollection $states {Billing and Shipping information steps} A collection of states for populating the State list.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class
		 * @output Db_DataCollection $shipping_states {Billing information step} A collection of states for populating the Shipping State list.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class
		 * @output array $shipping_options {Shipping method step} A list of available shipping options.
		 * Each element in the array is an object of the {@link Shop_ShippingOption} class.
		 * @output array $shipping_options_flat {Shipping method step} A flat list of shipping options.
		 * In the flat list all options, including options returned by multi-option shipping methods, like FedEx, are presented in a single list.
 		 * Each element in the array is an object of the {@link Shop_ShippingOption} class.
		 * @output float $goods_tax {Payment method step} Specifies a value of a goods tax.
		 * @output float $subtotal {Payment method step} Specifies the order subtotal amount.
		 * @output float $shipping_quote {Payment and Shipping method steps} Specifies a shipping quote value.
		 * @output float $shipping_tax {Payment method step} Specifies a shipping tax value.
		 * @output array $payment_methods {Payment method step} A list of applicable payment methods.
		 * Each element in the array is an object of the {@link Shop_PaymentMethod} class.
		 *
		 * @output float $goods_tax {Review step} Specifies an amount of the sales tax.
		 * @output float $subtotal {Review step} Specifies the order subtotal amount.
		 * @output float $shipping_quote {Review step} Specifies a shipping quote value.
		 * @output float $shipping_tax {Review step} Specifies a shipping tax value.
		 * @output float $total {Review step} Specifies the order total amount.
		 * @output float $discount {Review step} Specifies a total discount value.
		 * @output array $product_taxes {Review step} A list of sales taxes applied to all order items.
		 * Each element in the array is an object containing the following fields:
		 * <ul>
		 *   <li><em>name</em> - the tax name, for example GST.</li>
		 *   <li><em>total</em> - total tax amount.</li>
		 * </ul>
		 * You can output tax names and values with the following code:
		 * <pre>
		 * <? foreach ($product_taxes as $tax): ?>
		 *   <?= h($tax->name) ?>: <?= format_currency($tax->total) ?>
		 * <? endforeach ?>
		 * </pre>
		 * @output array $shipping_taxes {Review step} A list of taxes applied to the shipping service.
		 * Each element in the collection is an object containing the following fields:
		 * <ul>
		 *   <li><em>name</em> - the tax name, for example GST.</li>
		 *   <li><em>total</em> - total tax amount.</li>
		 * </ul>
		 * @output array $taxes {Review step} A list of all taxes applied to products and shipping.
		 * Taxes are combined by names. Each element in the array is an object containing the following fields:
		 * <ul>
		 *   <li><em>name</em> - the tax name, for example GST.</li>
		 *   <li><em>total</em> - total tax amount.</li>
		 * </ul>
		 *
		 * @ajax on_action Posts a current step form data to the server, validates form data and switches the checkout process to a next step.
		 * Use this hander for creating the <em>Next</em> button on the checkout page. Example:
		 * <pre>
		 * <input
		 *   type="image"
		 *   src="/resources/images/btn_next.gif"
		 *   alt="Next"
		 *   onclick="return $(this).getForm().sendRequest('on_action', {update:{'checkout_page': 'checkout_partial'}})"/>
		 * </pre>
		 * @ajax shop:on_copyBillingInfo Copies billing information (a customer name and address) to the shipping information checkout step.
		 * Use this handler for creating a link <em>Copy billing information</em> on the shipping information step. Example:
		 * <pre>
		 * <a
		 *   href="#"
		 *   onclick="return $(this).getForm().sendRequest(
		 *     'shop:on_copyBillingInfo',
		 *     {update:{'checkout_page': 'checkout_partial'}})">
		 *   billing information</a>
		 * </pre>
		 *
		 * @action shop:checkout
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/checkout_page Creating the Checkout Page
		 * @see ajax@shop:on_updateStateList
		 */
		public function checkout()
		{
			global $activerecord_no_columns_info;

			$checkout_step = post('checkout_step');
			$skip_to = post('skip_to');

			$shipping_required = $this->data['shipping_required'] = Shop_CheckoutData::shipping_required();
			$skip_shipping_step = false;

			if (!$shipping_required)
			{
					$no_shipping_option = Shop_ShippingOption::find_by_api_code('no_shipping_required');
					if ($no_shipping_option)
					{
						Shop_CheckoutData::set_shipping_method($no_shipping_option->id, post('cart_name', 'main'));
						if (post('auto_skip_shipping') && $checkout_step == 'shipping_info')
						{
							$skip_shipping_step = true;
							$skip_to = post('auto_skip_to', 'payment_method');
						}
					}
			} else
			{
				$shipping_method = Shop_CheckoutData::get_shipping_method();
				$no_shipping_option = Shop_ShippingOption::find_by_api_code('no_shipping_required');

				if ($no_shipping_option && $shipping_method && $shipping_method->id == $no_shipping_option->id)
					Shop_CheckoutData::reset_shiping_method();
			}

			/*
			 * Process return to previous steps
			 */

			$skip_data = false;
			if ($move_to = post('move_to'))
			{
				$skip_data = true;
				switch ($move_to)
				{
					case 'billing_info' : $checkout_step = null; break;
					case 'shipping_info' : $checkout_step = 'billing_info'; break;
					case 'shipping_method' : $checkout_step = 'shipping_info'; break;
					case 'payment_method' : $checkout_step = 'shipping_method'; break;
				}
			}

			if ($skip_to && !$move_to)
			{
				$skip_data = true;

				switch ($checkout_step)
				{
					case 'billing_info' : Shop_CheckoutData::set_billing_info($this->customer); break;
					case 'shipping_info' : Shop_CheckoutData::set_shipping_info(); break;
					case 'shipping_method' :
						if (!$skip_shipping_step)
							Shop_CheckoutData::set_shipping_method(null, post('cart_name', 'main'));
					break;
					case 'payment_method' : Shop_CheckoutData::set_payment_method(); break;
				}

				switch ($skip_to)
				{
					case 'billing_info' : $checkout_step = null; break;
					case 'shipping_info' : $checkout_step = 'billing_info'; break;
					case 'shipping_method' : $checkout_step = 'shipping_info'; break;
					case 'payment_method' : $checkout_step = 'shipping_method'; break;
					case 'review' : $checkout_step = 'payment_method'; break;
				}
			}

			/*
			 * Reset the checkout data if the cart content has been changed
			 */

			$activerecord_no_columns_info = true;

			$cart_name = post('cart_name', 'main');

			if (post('checkout_step'))
			{
				$cart_content_id = Shop_CheckoutData::get_cart_id();
				$new_content_id = Shop_Cart::get_content_id($cart_name);

				if ($new_content_id != $cart_content_id)
				{
					Shop_CheckoutData::reset_data();
					Phpr::$response->redirect(root_url($this->page->url).'/?'.uniqid());
				}
			} else
			{
				Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id($cart_name));
			}

			$activerecord_no_columns_info = false;

			/*
			 * Set customer notes - on any step
			 */

			if (array_key_exists('customer_notes', $_POST))
				Shop_CheckoutData::set_customer_notes(post('customer_notes'));

			/*
			 * Set coupon code - on any step, as well
			 */

			if (array_key_exists('coupon', $_POST))
				$this->on_setCouponCode(false);

			/*
			 * Handle the Next button click
			 */

			$billing_info = Shop_CheckoutData::get_billing_info();
			$this->data['billing_info'] = $billing_info;

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$this->data['shipping_info'] = $shipping_info;

			$shipping_method = Shop_CheckoutData::get_shipping_method();
			$this->data['shipping_method'] = $shipping_method;

			$payment_method = Shop_CheckoutData::get_payment_method();
			$this->data['payment_method'] = $payment_method;

			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();

			if (!$checkout_step)
			{
				$this->data['checkout_step'] = 'billing_info';

				$billing_countries = Shop_Country::get_list($billing_info->country);
				$this->data['countries'] = $billing_countries;

				$billing_country = $billing_info->country ? $billing_info->country : $billing_countries[0]->id;
				$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $billing_country)->order('name')->find_all();

				$shipping_country = $shipping_info->country ? $shipping_info->country : $billing_countries[0]->id;
				$this->data['shipping_states'] = Shop_CountryState::create(true)->where('country_id=?', $shipping_country)->order('name')->find_all();
			}
			elseif ($checkout_step == 'billing_info')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_billing_info($this->customer);

				$shipping_countries = Shop_Country::get_list($shipping_info->country);
				$this->data['countries'] = $shipping_countries;

				$shipping_country = $shipping_info->country ? $shipping_info->country : $shipping_countries[0]->id;
				$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $shipping_country)->order('name')->find_all();

				$this->data['checkout_step'] = 'shipping_info';
			}
			elseif ($checkout_step == 'shipping_info')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_shipping_info();

				$available_options = $this->data['shipping_options'] = Shop_CheckoutData::list_available_shipping_options($this->customer, $cart_name);
				$this->data['shipping_options_flat'] = Shop_CheckoutData::flatten_shipping_options($available_options);
				$this->data['checkout_step'] = 'shipping_method';
				$this->data['shipping_quote'] = $shipping_method->is_free ? 0 : $shipping_method->quote_no_tax;
			}
			elseif ($checkout_step == 'shipping_method')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_shipping_method(null, post('cart_name', 'main'));

				$discount_info = Shop_CheckoutData::eval_discounts($cart_name);

				$tax_info = Shop_TaxClass::calculate_taxes(Shop_Cart::list_active_items($cart_name), Shop_CheckoutData::get_shipping_info());

				$total_product_tax = $tax_info->tax_total;
				$this->data['total_product_tax'] = $total_product_tax;

				$total = $this->data['goods_tax'] = $total_product_tax;
				$total += $this->data['subtotal'] = Shop_Cart::total_price_no_tax($cart_name);

				$shipping_method = Shop_CheckoutData::get_shipping_method();
				$total += $this->data['shipping_quote'] = $shipping_method->is_free ? 0 : $shipping_method->quote_no_tax;

				$shiping_taxes = $this->data['shipping_taxes'] = Shop_TaxClass::get_shipping_tax_rates($shipping_method->id, Shop_CheckoutData::get_shipping_info(), $shipping_method->quote_no_tax);
				$total += $this->data['shipping_tax'] = Shop_TaxClass::eval_total_tax($shiping_taxes);

				$payment_methods = Shop_PaymentMethod::list_checkout_applicable($cart_name,$total)->as_array();
				$this->data['payment_methods'] = $payment_methods;
				$this->data['checkout_step'] = 'payment_method';
			} elseif ($checkout_step == 'payment_method')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_payment_method();

				$display_prices_including_tax = Shop_CheckoutData::display_prices_incl_tax();

				$totals = Shop_CheckoutData::calculate_totals($cart_name);

				$this->data['discount'] = $display_prices_including_tax ? $totals->discount_tax_incl : $totals->discount;
				$this->data['goods_tax'] = $totals->goods_tax;
				$this->data['subtotal_no_discounts'] = $totals->subtotal;
				$this->data['subtotal'] = $display_prices_including_tax ? $totals->subtotal_tax_incl : $totals->subtotal_discounts;
				$this->data['shipping_taxes'] = $totals->shipping_taxes;
				$this->data['shipping_tax'] = $totals->shipping_tax;
				$this->data['shipping_quote'] = $display_prices_including_tax ? $totals->shipping_quote_tax_incl : $totals->shipping_quote;
				$this->data['shipping_tax_incl'] = $totals->shipping_quote_tax_incl;
				$this->data['total'] = $totals->total;
				$this->data['product_taxes'] = $totals->product_taxes;
				$this->data['taxes'] = $totals->all_taxes;

				$this->data['checkout_step'] = 'review';
			} elseif ($checkout_step == 'review')
			{
				$payment_method_info = Shop_CheckoutData::get_payment_method();
				$payment_method = Shop_PaymentMethod::create()->find($payment_method_info->id);
				if (!$payment_method)
					throw new Cms_Exception('The selected payment method is not found');

				$payment_method->define_form_fields();

				$order = Shop_CheckoutData::place_order($this->customer, post('register_customer', false), post('cart_name', 'main'), post('empty_cart', true));

				Backend::$events->fireEvent('shop:onBeforeCheckoutStepPay', $order);

				$this->data['checkout_step'] = 'pay';

				$custom_pay_page = $payment_method->get_paymenttype_object()->get_custom_payment_page($payment_method);
				$pay_page = $custom_pay_page ? $custom_pay_page : Cms_Page::create()->find_by_action_reference('shop:pay');
				if (!$pay_page)
					throw new Cms_Exception('The Pay page is not found.');

				Phpr::$response->redirect(root_url($pay_page->url.'/'.$order->order_hash));
			}

			/*
			 * Reload updated checkout data
			 */

			$billing_info = Shop_CheckoutData::get_billing_info();
			$this->data['billing_info'] = $billing_info;

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$this->data['shipping_info'] = $shipping_info;

			$shipping_method = Shop_CheckoutData::get_shipping_method();
			$this->data['shipping_method'] = $shipping_method;

			$payment_method = Shop_CheckoutData::get_payment_method();
			$this->data['payment_method'] = $payment_method;

			$this->load_checkout_estimated_data($cart_name);
		}

		protected function load_checkout_estimated_data($cart_name)
		{
			$display_prices_including_tax = Shop_CheckoutData::display_prices_incl_tax();
			$discount_info = Shop_CheckoutData::eval_discounts($cart_name);

			if (!array_key_exists('discount', $this->data))
			{
				if (!$display_prices_including_tax)
					$this->data['discount'] = $discount_info->cart_discount;
				else
					$this->data['discount'] = $discount_info->cart_discount_incl_tax;
			}
			else
				$discount = $this->data['discount'];

			$this->data['cart_total'] = $cart_total = Shop_Cart::total_price($cart_name, true);
			$shipping_tax = 0;

			if (!array_key_exists('total', $this->data))
			{
				$totals = Shop_CheckoutData::calculate_totals($cart_name);
				$this->data['estimated_total'] = $totals->total;
				$shipping_tax = $totals->shipping_tax;
				$this->data['estimated_tax'] = $totals->goods_tax + $totals->shipping_tax;
			} else
			{
				$this->data['estimated_total'] = $this->data['total'];
				$shipping_tax = $this->data['shipping_tax'];
				$this->data['estimated_tax'] = $this->data['goods_tax'] + $this->data['shipping_tax'];
			}

			if ($display_prices_including_tax && isset($this->data['shipping_method']))
			{
				$shipping_method = $this->data['shipping_method'];
	  			if ($shipping_method->id)
				{
					$shipping_method->quote = $shipping_method->quote_no_tax + $shipping_tax;
				}
			}

			$this->data['applied_discount_rules'] = $discount_info->applied_rules_info;
		}

		public function on_copyBillingInfo()
		{
			$billing_info = Shop_CheckoutData::get_billing_info();

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$shipping_info->copy_from($billing_info);
			Shop_CheckoutData::set_shipping_info($shipping_info);
			$_POST['move_to'] = 'shipping_info';
			$this->checkout();
		}

		/**
		 * Allows to update a list of states when a customer select some country in the country list.
		 * Usually this AJAX handler used on the {@link http://lemonstand.com/docs/checkout_page Checkout page} together with {@link action@shop:checkout} action,
		 * but it can be used on any other page for updating the state list. Usage example:
		 * <pre>
		 * <select id="country" name="country" onchange="return this.getForm().sendRequest(
		 *   'shop:on_updateStateList',
		 *   {extraFields: {
		 *     'country': $(this).get('value'),
		 *     'control_name': 'state',
		 *     'control_id': 'state',
		 *     'current_state': '<?= $shipping_info->state ?>'},
		 *   update: {'shipping_states': 'shop:state_selector'}
		 * })">
		 * </pre>
		 * To make the state list updatable, place it into a separate partial. In the code example above the partial is called <em>shop:state_selector</em>.
		 * The parameters control_name, control_id, current_state are passed into the state list partial by LemonStand when it handles
		 * the shop:on_updateStateList request. You can use these parameters in the state list partial highlighting a currently selected state.
		 *
		 * @input integer $country Specifies the selected {@link Shop_Country country} identifier. Required.
		 * @input string $control_name Specifies the state selector element name. Required.
		 * @input string $control_id Specifies the state selector element identifier. Required.
		 * @input integer $current_state Specifies the selected {@link Shop_CountryState state} identifier.
 		 *
		 * @output string $control_name Specifies the state selector element name.
		 * @output string $control_id Specifies the state selector element identifier.
		 * @output integer $current_state Specifies the selected {@link Shop_CountryState state} identifier.
 		 *
		 * @ajax shop:on_updateStateList
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 * @link action@shop:checkout
		 * @link http://lemonstand.com/docs/checkout_page Creating the Checkout page
		 */
		public function on_updateStateList()
		{
			$this->data['states'] = Shop_CountryState::create()->where('country_id=?', post('country'))->order('name')->find_all();
			$this->data['control_name'] = post('control_name');
			$this->data['control_id'] = post('control_id');
			$this->data['current_state'] = post('current_state');
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/payment_receipt_page Payment Receipt} page.
		 * The action loads an order by an order hash string, specified in the page URL and prepares all required
		 * PHP variables.
		 *
		 * @action shop:payment_receipt
		 *
		 * @output Shop_Order $order An order object, loaded from the database.
		 * This variable can be NULL of the order is not found.
		 * @output boolean $payment_processed Indicates whether a payment has already been processed for the order.
		 * You can use this variable to display the "Order not paid" message instead of the normal receipt page.
		 * @output Db_DataCollection $items A list of order items.
		 * Each element of the collection is an object of the {@link Shop_OrderItem} class.
		 * This variable exists only if the requested order was found in the database.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/payment_receipt_page Payment Receipt page
		 */
		public function payment_receipt()
		{
			$this->data['order'] = null;
			$this->data['payment_processed'] = false;

			$order_hash = trim($this->request_param(0));
			if (!strlen($order_hash))
				return;

			$order = Shop_Order::create()->find_by_order_hash($order_hash);
			if (!$order)
				return;

			$this->data['order'] = $order;
			$this->data['items'] = $order->items;

			if (!$order->payment_processed())
				return;

			$this->data['payment_processed'] = true;

			/*
			 * Add Google Analytics E-Commerce transaction tracking
			 */
			$gaSettings = Cms_Stats_Settings::get();
			if ($gaSettings->ga_enabled)
				$this->add_tracking_code($this->get_ga_ec_tracking_code($gaSettings, $order));
		}

		private function get_ga_ec_tracking_code($gaSettings, $order)
		{
			return $gaSettings->get_ga_ec_tracking_code($order);
		}

		/*
		 * Step-by step checkout
		 */

		/**
		 * Base action for the Billing Information checkout page.
		 * This action is a part of the {@link http://lemonstand.com/docs/implementing_the_step_by_step_checkout step-by-step checkout process}.
		 * The Billing Information page is the first step of the conventional step-by-step checkout process.
		 *
		 * {@include docs/step-by-step-checkout}
		 *
		 * <h3>Automatic customer registration</h3>
		 * Besides the supported form fields described below this action supports fields for the automatic customer registration.
		 * Please refer to the {@link http://lemonstand.com/docs/automatic_customer_registration/ Automatic Customer Registration}
		 * article for details.
		 *
		 * @action shop:checkout_billing_info
		 *
		 * @input string $first_name Specifies the customer billing first name. Required.
		 * @input string $last_name Specifies the customer billing last name. Required.
		 * @input string $email Specifies the customer email address. Required.
		 * @input string $company Specifies the customer company name.
		 * @input string $phone Specifies the customer phone number.
		 * @input string $street_address Specifies the customer bulling street address. Required.
		 * @input string $city Specifies the customer billing city. Required.
		 * @input string $zip Specifies the customer billing ZIP/postal code. Required.
		 * @input integer $country Specifies the customer billing {@link Shop_Country country} identifier. Required.
		 * @input integer $state Specifies the customer billing {@link Shop_CountryState state} identifier. Required.
		 * @input string $redirect Specifies an ULR of a page to redirect the browser after processing the form data.
		 * Use a hidden field for this value. If you are implementing a conventional checkout process, a value of
		 * this field is an URL of the checkout {@link action@shop:checkout_shipping_info Shipping Information page}.
		 * Note that if your copy of LemonStand is installed in a subdirectory, you need to use the {@link root_url()} function
		 * in the <em>redirect</em> field value.
		 * @input string $customer_notes Specifies optional customer order notes.
		 * @input string $coupon_code Specifies a coupon code.
		 * @input string $submit A name of the SUBMIT input element.
		 * The submit button should have name <em>submit</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_checkoutSetBillingInfo} handler.
		 * @input string $cart_name Specifies the shopping cart name.
		 * LemonStand support multiple shopping cart. By default the cart named <em>main</em> used.
		 *
		 * @output Shop_CheckoutAddressInfo $billing_info An address information object representing a customer's billing name and address.
		 * @output Db_DataCollection $countries A collection of countries for populating the Country list.
		 * Each element in the collection is an object of the {@link Shop_Country} class.
		 * @output Db_DataCollection $states A list of states for populating the State list.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class.
		 * The state list can be updated with the {@link ajax@shop:on_updateStateList}.
		 * @output float $discount Estimated cart discount.
		 * You can display this value during the checkout process.
		 * @output float $cart_total Cart total amount (subtotal).
		 * @output float $estimated_total An estimated total value.
		 * This value is calculated on each step of the checkout process, taking into account
		 * {@link http://lemonstand.com/docs/shopping_cart_price_rules/ price rules}
		 * and known information about the customer.
		 * @output float $estimated_tax An estimated tax value.
		 * The value includes the sales tax and shipping tax.
		 *
		 * @ajax shop:on_checkoutSetBillingInfo Processes the form in AJAX mode.
		 * Use this handler for creating a button, or link, for sending the form data to the server and redirecting the browser
		 * to a next checkout step. Example:
		 * <pre>
		 * <input
		 *   type="image"
		 *   src="/resources/images/btn_next.gif"
		 *   alt="Next"
		 *   onclick="return $(this).getForm().sendRequest('shop:on_checkoutSetBillingInfo')"/>
		 * </pre>
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/implementing_the_step_by_step_checkout Implementing the Step-By-Step Checkout
		 * @see  http://lemonstand.com/docs/automatic_customer_registration/ Automatic customer registration
		 * @see action@shop:checkout
		 * @see action@shop:checkout_shipping_info shop:checkout_shipping_info action
		 * @see action@shop:checkout_shipping_method shop:checkout_shipping_method action
		 * @see action@shop:checkout_payment_method shop:checkout_payment_method action
		 * @see action@shop:checkout_order_review shop:checkout_order_review action
		 */
		public function checkout_billing_info()
		{
			Shop_CheckoutData::reset_data();
			Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id(post('cart_name', 'main')));

			$this->loadCheckoutBillingStepData();

			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetBillingInfo(false);

			$this->loadCheckoutBillingStepData(true);
		}

		public function on_checkoutSetBillingInfo($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_billing_info($this->customer);

			if ($ajax_mode)
				$this->loadCheckoutBillingStepData(true);

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Base action for the Shipping Information checkout page.
		 * This action is a part of the {@link http://lemonstand.com/docs/implementing_the_step_by_step_checkout step-by-step checkout process}.
		 * The Shipping Information page is the second step of the conventional step-by-step checkout process.
		 *
		 * {@include docs/step-by-step-checkout}
		 *
		 * @action shop:checkout_shipping_info
		 *
		 * @input string $first_name Specifies the customer shipping first name. Required.
		 * @input string $last_name Specifies the customer shipping last name. Required.
		 * @input string $company Specifies the customer shipping company name.
		 * @input string $phone Specifies the phone number.
		 * @input string $street_address Specifies the customer shipping street address. Required.
		 * @input string $city Specifies the customer shipping city. Required.
		 * @input string $zip Specifies the customer shipping ZIP/postal code. Required.
		 * @input integer $country Specifies the customer shipping {@link Shop_Country country} identifier. Required.
		 * @input integer $state Specifies the customer shipping {@link Shop_CountryState state} identifier. Required.
		 * @input string $redirect Specifies an ULR of a page to redirect the browser after processing the form data.
		 * Use a hidden field for this value. If you are implementing a conventional checkout process, a value of
		 * this field is an URL of the checkout {@link action@shop:checkout_shipping_method Shipping Method page}.
		 * Note that if your copy of LemonStand is installed in a subdirectory, you need to use the {@link root_url()} function
		 * in the <em>redirect</em> field value.
		 * @input string $customer_notes Optional field for entering the customer order notes.
		 * @input string $coupon_code Specifies a coupon code.
		 * @input boolean $is_business Determines whether the shipping location is a business address.
		 * Supported values are 0, 1.
		 * @input string $submit A name of the SUBMIT input element.
		 * The submit button should have name <em>submit</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_checkoutSetShippingInfo} handler.
		 *
		 * @output Shop_CheckoutAddressInfo $shipping_info An object representing a customer's shipping name and address.
		 * @output Db_DataCollection $countries A collection of countries for populating the Country list.
		 * Each element in the collection is an object of the {@link Shop_Country} class.
		 * @output Db_DataCollection $states A list of states for populating the State list.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class.
		 * The state list can be updated with the {@link ajax@shop:on_updateStateList}.
		 * @output float $discount Estimated cart discount.
		 * You can display this value during the checkout process.
		 * @output float $cart_total Cart total amount (subtotal).
		 * @output float $estimated_total An estimated total value.
		 * This value is calculated on each step of the checkout process, taking into account
		 * {@link http://lemonstand.com/docs/shopping_cart_price_rules/ price rules}
		 * and known information about the customer.
		 * @output float $estimated_tax An estimated tax value.
		 * The value includes the sales tax and shipping tax.
		 * @input string $cart_name Specifies the shopping cart name.
		 * LemonStand support multiple shopping cart. By default the cart named <em>main</em> used.
		 *
		 * @ajax shop:on_checkoutSetShippingInfo Processes the form in AJAX mode.
		 * Use this handler for creating a button, or link, for sending the form data to the server and redirecting the
		 * browser to a next checkout step. Example:
		 * <pre>
		 * <input
		 *   type="image"
		 *   src="/resources/images/btn_next.gif"
		 *   alt="Next"
		 *   onclick="return $(this).getForm().sendRequest('shop:on_checkoutSetShippingInfo')"/>
		 * </pre>
		 * @ajax shop:on_copyBillingInfo Copies billing information (a customer name and address) to the shipping information checkout step.
		 * Use this for creating a link “<em>Copy billing information</em>” on the shipping information checkout page. Example:
		 * <pre>
		 * <a
		 *   href="#"
		 *   onclick="return $(this).getForm().sendRequest(
		 *     'shop:on_copyBillingInfo',
		 *   {update:{'checkout_page': 'checkout_partial'}})"
		 * >Copy billing information</a>
		 * </pre>
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/implementing_the_step_by_step_checkout Implementing the Step-By-Step Checkout
		 * @see action@shop:checkout
		 * @see action@shop:checkout_billing_info shop:checkout_billing_info action
		 * @see action@shop:checkout_shipping_method shop:checkout_shipping_method action
		 * @see action@shop:checkout_payment_method shop:checkout_payment_method action
		 * @see action@shop:checkout_order_review shop:checkout_order_review action
		 */
		public function checkout_shipping_info()
		{
			Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id(post('cart_name', 'main')));

			$this->loadCheckoutShippingStepData();

			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetShippingInfo(false);

			$this->loadCheckoutShippingStepData(true);
		}

		public function on_checkoutSetShippingInfo($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_shipping_info();

			if ($ajax_mode)
				$this->loadCheckoutShippingStepData(true);

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Base action for the Shipping Method checkout page.
		 * This action is a part of the {@link http://lemonstand.com/docs/implementing_the_step_by_step_checkout step-by-step checkout process}.
		 * The Shipping Method page is the third step of the conventional step-by-step checkout process.
		 *
		 * {@include docs/step-by-step-checkout}
		 *
		 * @input string $shipping_option An identifier of selected shipping method. Required.
		 * You can use either radio button set or a SELECT element for representing a list of available shipping methods.
		 * @input string $redirect Specifies an ULR of a page to redirect the browser after processing the form data.
		 * Use a hidden field for this value. If you are implementing a conventional checkout process, a value of
		 * this field is an URL of the checkout {@link action@shop:checkout_payment_method Payment Method page}.
		 * Note that if your copy of LemonStand is installed in a subdirectory, you need to use the {@link root_url()} function
		 * in the <em>redirect</em> field value.
		 * @input string $submit A name of the SUBMIT input element.
		 * The submit button should have name <em>submit</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_checkoutSetShippingMethod} handler.
		 * @input string $cart_name Specifies the shopping cart name.
		 * LemonStand support multiple shopping cart. By default the cart named <em>main</em> used.
		 *
		 * @output array $shipping_options A list of available shipping options.
		 * Each element in the array is an object of the {@link Shop_ShippingOption} class.
		 * @output object $shipping_method A PHP object representing a shipping method previously selected by the customer, if any.
		 * The object has the following fields:
		 * <ul>
		 *   <li><em>id</em> - an identifier of the shipping method in LemonStand database.</li>
		 *   <li><em>quote</em> - specifies the shipping quote.</li>
		 *   <li><em>name</em> - specifies the shipping method name.</li>
		 * </ul>
		 * You can use this variable for pre-selecting an item in the shipping method list.
		 * @output float $discount Estimated cart discount.
		 * You can display this value during the checkout process.
		 * @output float $cart_total Cart total amount (subtotal).
		 * @output float $estimated_total An estimated total value.
		 * This value is calculated on each step of the checkout process, taking into account
		 * {@link http://lemonstand.com/docs/shopping_cart_price_rules/ price rules}
		 * and known information about the customer.
		 * @output float $estimated_tax An estimated tax value.
		 * The value includes the sales tax and shipping tax.
		 *
		 * @ajax shop:on_checkoutSetShippingMethod Processes the form in AJAX mode.
		 * Use this handler for creating a button, or link, for sending the form data to the server and redirecting the browser to a next
		 * checkout step. Example:
		 * <pre>
		 * <input
		 *   type="image"
		 *   src="/resources/images/btn_next.gif"
		 *   alt="Next"
		 *   onclick="return $(this).getForm().sendRequest('shop:on_checkoutSetShippingMethod')"/>
		 * </pre>
		 *
		 * @action shop:checkout_shipping_method
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/implementing_the_step_by_step_checkout Implementing the Step-By-Step Checkout
		 * @see action@shop:checkout
		 * @see action@shop:checkout_billing_info shop:checkout_billing_info action
		 * @see action@shop:checkout_shipping_info shop:checkout_shipping_info action
		 * @see action@shop:checkout_payment_method shop:checkout_payment_method action
		 * @see action@shop:checkout_order_review shop:checkout_order_review action
		 */
		public function checkout_shipping_method()
		{
			$cart_name = post('cart_name', 'main');
			Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id($cart_name));

			$this->data['shipping_options'] = Shop_CheckoutData::list_available_shipping_options($this->customer, $cart_name);
			$this->data['shipping_method'] = Shop_CheckoutData::get_shipping_method();

			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetShippingMethod(false);

			$this->load_checkout_estimated_data($cart_name);
			$this->data['shipping_method'] = Shop_CheckoutData::get_shipping_method();
		}

		public function on_checkoutSetShippingMethod($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_shipping_method(null, post('cart_name', 'main'));

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Base action for the Payment Method checkout page.
		 * This action is a part of the {@link http://lemonstand.com/docs/implementing_the_step_by_step_checkout step-by-step checkout process}.
		 * The Payment Method page is the fourth step of the conventional step-by-step checkout process.
		 *
		 * {@include docs/step-by-step-checkout}
		 *
		 * @input integer $payment_method An identifier of selected {@link Shop_PaymentMethod payment method}. Required.
		 * You can use either radio button set or a SELECT element for representing a list of available payment methods.
		 * @input string $redirect Specifies an ULR of a page to redirect the browser after processing the form data.
		 * Use a hidden field for this value. If you are implementing a conventional checkout process, a value of
		 * this field is an URL of the checkout {@link action@shop:checkout_order_review Order Review page}.
		 * Note that if your copy of LemonStand is installed in a subdirectory, you need to use the {@link root_url()} function
		 * in the <em>redirect</em> field value.
		 * @input string $submit A name of the SUBMIT input element.
		 * The submit button should have name <em>submit</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_checkoutSetPaymentMethod} handler.
		 * @input string $cart_name Specifies the shopping cart name.
		 * LemonStand support multiple shopping cart. By default the cart named <em>main</em> used.
		 *
		 * @output array $payment_methods A list of applicable payment methods.
		 * Each element in the array is an object of the {@link Shop_PaymentMethod} class.
		 * @output object $payment_method A PHP object representing a selected payment method.
		 * The object has the following fields:
		 * <ul>
		 *   <li><em>id</em> - an identifier of the payment method in LemonStand database.</li>
		 *   <li><em>name</em> - specifies the payment method name.</li>
		 * </ul>
		 * You can use this variable for pre-selecting an item in the payment method list.
		 * @output float $goods_tax Sales tax amount.
		 * @output float $discount Estimated cart discount.
		 * You can display this value during the checkout process.
		 * @output float $cart_total Cart total amount (subtotal).
		 * @output float $estimated_total An estimated total value.
		 * This value is calculated on each step of the checkout process, taking into account
		 * {@link http://lemonstand.com/docs/shopping_cart_price_rules/ price rules}
		 * and known information about the customer.
		 * @output float $estimated_tax An estimated tax value.
		 * The value includes the sales tax and shipping tax.
		 *
		 * @ajax shop:on_checkoutSetPaymentMethod Processes the form in AJAX mode.
		 * Use this handler for creating a button, or link, for sending the form data to the server and redirecting the browser to
		 * a next checkout step. Example:
		 * <pre>
		 * <input
		 *   type="image"
		 *   src="/resources/images/btn_next.gif"
		 *   alt="Next"
		 *   onclick="return $(this).getForm().sendRequest('shop:on_checkoutSetPaymentMethod')"/>
		 * </pre>
		 *
		 * @action shop:checkout_payment_method
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/implementing_the_step_by_step_checkout Implementing the Step-By-Step Checkout
		 * @see action@shop:checkout
		 * @see action@shop:checkout_billing_info shop:checkout_billing_info action
		 * @see action@shop:checkout_shipping_info shop:checkout_shipping_info action
		 * @see action@shop:checkout_shipping_method shop:checkout_shipping_method action
		 * @see action@shop:checkout_order_review shop:checkout_order_review action
		 */
		public function checkout_payment_method()
		{
			$shipping_method = Shop_CheckoutData::get_shipping_method();

			$cart_name = post('cart_name', 'main');

			$discount_info = Shop_CheckoutData::eval_discounts($cart_name);

			$tax_info = Shop_TaxClass::calculate_taxes(Shop_Cart::list_active_items($cart_name), Shop_CheckoutData::get_shipping_info());
			$total_product_tax = $tax_info->tax_total;
			$this->data['total_product_tax'] = $total_product_tax;
			$total = $this->data['goods_tax'] = $total_product_tax;

			$total += $this->data['subtotal'] = Shop_Cart::total_price_no_tax($cart_name);
			$total += $this->data['shipping_quote'] = $shipping_method->quote_no_tax;

			$shiping_taxes = $this->data['shipping_taxes'] = Shop_TaxClass::get_shipping_tax_rates($shipping_method->id, Shop_CheckoutData::get_shipping_info(), $shipping_method->quote_no_tax);
			$total += $this->data['shipping_tax'] = Shop_TaxClass::eval_total_tax($shiping_taxes);

			$payment_methods = Shop_PaymentMethod::list_checkout_applicable($cart_name,$total)->as_array();

			$this->data['payment_methods'] = $payment_methods;
			$this->data['payment_method'] = Shop_CheckoutData::get_payment_method();
			$this->data['applied_discount_rules'] = $discount_info->applied_rules_info;

			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetPaymentMethod(false);

			$this->data['payment_method'] = Shop_CheckoutData::get_payment_method();
			$this->load_checkout_estimated_data($cart_name);
		}

		public function on_checkoutSetPaymentMethod($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_payment_method();

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Base action for the Order Review checkout page.
		 * This action is a part of the {@link http://lemonstand.com/docs/implementing_the_step_by_step_checkout step-by-step checkout process}.
		 * The Order Review page is the last step of the conventional step-by-step checkout process.
		 *
		 * {@include docs/step-by-step-checkout}
		 *
		 * @input boolean $customer_auto_login Determines whether the customer should be automatically logged in after placing the order.
		 * Accepted values are: 0, 1. See also the {@link http://lemonstand.com/docs/automatic_customer_registration Automatic customer registration} article.
		 * @input boolean $customer_registration_notification Determines whether the registration notification email message should be sent to the customer.
		 * LemonStand uses an email template with the <em>shop:registration_confirmation</em> code for the registration notification. Accepted values are: 0, 1.
		 * @input boolean $empty_cart Allows to leave the cart content after the order is placed.
		 * This feature allows your customers to return to previous checkout step even if they already placed the order.
		 * Accepted values are: 0, 1. The default value is 0 (false).
		 * @input string $submit A name of the SUBMIT input element.
		 * The submit button should have name <em>submit</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_checkoutPlaceOrder} handler.
		 * @input boolean $no_redirect Cancels the automatic browser redirection.
		 * By default the browser is automatically redirected to the {@link http://lemonstand.com/docs/pay_page payment page} after the order is placed.
		 * Accepted values are: 0, 1.
		 * @input string $cart_name Specifies the shopping cart name.
		 * LemonStand support multiple shopping cart. By default the cart named <em>main</em> used.
		 *
		 * @output float $goods_tax Specifies the sales tax amount.
		 * @output float $discount Specifies the discount amount.
		 * @output float $subtotal Specifies the order subtotal amount.
		 * @output float $shipping_quote Specifies a shipping quote.
		 * @output float $shipping_tax Specifies a shipping tax value.
		 * @output float $total Specifies the total order amount.
		 * @output array $product_taxes {Review step} A list of sales taxes applied to all order items.
		 * Each element in the array is an object containing the following fields:
		 * <ul>
		 *   <li><em>name</em> - the tax name, for example GST.</li>
		 *   <li><em>total</em> - total tax amount.</li>
		 * </ul>
		 * You can output tax names and values with the following code:
		 * <pre>
		 * <? foreach ($product_taxes as $tax): ?>
		 *   <?= h($tax->name) ?>: <?= format_currency($tax->total) ?>
		 * <? endforeach ?>
		 * </pre>
		 * @output array $shipping_taxes {Review step} A list of taxes applied to the shipping service.
		 * Each element in the collection is an object containing the following fields:
		 * <ul>
		 *   <li><em>name</em> - the tax name, for example GST.</li>
		 *   <li><em>total</em> - total tax amount.</li>
		 * </ul>
		 * @output Shop_CheckoutAddressInfo $billing_info An object of the {@link Shop_CheckoutAddressInfo} class containing the customer's billing name and address.
		 * @output Shop_CheckoutAddressInfo $shipping_info An object of the {@link Shop_CheckoutAddressInfo} class containing the customer's shipping name and address.
		 * @output object $shipping_method A PHP object representing a selected shipping method.
		 * The object has the following fields:
		 * <ul>
		 *   <li><em>id</em> - an identifier of the shipping method in LemonStand database.</li>
		 *   <li><em>quote</em> - specifies the shipping quote.</li>
		 *   <li><em>name</em> - specifies the shipping method name.</li>
		 * </ul>
		 * @output object $payment_method A PHP object representing a selected payment method.
		 * The object has the following fields:
		 * <ul>
		 *   <li><em>id</em> - an identifier of the payment method in LemonStand database.</li>
		 *   <li><em>name</em> - specifies the payment method name.</li>
		 * </ul>
		 *
		 * @ajax shop:on_checkoutPlaceOrder Processes the form in AJAX mode.
		 * The handler creates an order basing on the checkout information gathered during the preceding checkout steps
		 * and redirects the browser to the {@link http://lemonstand.com/docs/pay_page payment page}.
		 * Use this handler for creating a button, or link, for placing the order. Example:
		 * <pre>
		 * <input
		 *   type="image"
		 *   src="/resources/images/btn_place_order.gif"
		 *   alt="Place Order and Pay"
		 *   onclick="return $(this).getForm().sendRequest('shop:on_checkoutPlaceOrder')"/>
		 * </pre>
		 *
		 * @action shop:checkout_order_review
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/implementing_the_step_by_step_checkout Implementing the Step-By-Step Checkout
		 * @see action@shop:checkout
		 * @see action@shop:checkout_billing_info shop:checkout_billing_info action
		 * @see action@shop:checkout_shipping_info shop:checkout_shipping_info action
		 * @see action@shop:checkout_shipping_method shop:checkout_shipping_method action
		 * @see action@shop:checkout_payment_method shop:checkout_payment_method action
		 */
		public function checkout_order_review()
		{
			$display_prices_including_tax = Shop_CheckoutData::display_prices_incl_tax();

			$totals = Shop_CheckoutData::calculate_totals(post('cart_name', 'main'));
			$this->data['discount'] = $display_prices_including_tax ? $totals->discount_tax_incl : $totals->discount;
			$this->data['goods_tax'] = $totals->goods_tax;
			$this->data['subtotal_no_discounts'] = $totals->subtotal;
			$this->data['subtotal'] = $display_prices_including_tax ? $totals->subtotal_tax_incl : $totals->subtotal_discounts;
			$this->data['shipping_taxes'] = $totals->shipping_taxes;
			$this->data['shipping_tax'] = $totals->shipping_tax;
			$this->data['shipping_quote'] = $display_prices_including_tax ? $totals->shipping_quote_tax_incl : $totals->shipping_quote;
			$this->data['total'] = $totals->total;
			$this->data['product_taxes'] = $totals->product_taxes;

			$this->data['billing_info'] = Shop_CheckoutData::get_billing_info();
			$this->data['shipping_info'] = Shop_CheckoutData::get_shipping_info();
			$this->data['shipping_method'] = Shop_CheckoutData::get_shipping_method();
			$this->data['payment_method'] = Shop_CheckoutData::get_payment_method();

			$this->setCheckoutFollowUpInfo();
			$this->load_checkout_estimated_data(post('cart_name', 'main'));

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutPlaceOrder(false);
		}

		public function on_checkoutPlaceOrder($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			$payment_method_info = Shop_CheckoutData::get_payment_method();
			$payment_method = Shop_PaymentMethod::create()->find($payment_method_info->id);
			if (!$payment_method)
				throw new Cms_Exception('The selected payment method is not found');

			$payment_method->define_form_fields();

			$order = Shop_CheckoutData::place_order($this->customer, post('register_customer', false), post('cart_name', 'main'), post('empty_cart', true));

			if (!post('no_redirect'))
			{
				$custom_pay_page = $payment_method->get_paymenttype_object()->get_custom_payment_page($payment_method);
				$pay_page = $custom_pay_page ? $custom_pay_page : Cms_Page::create()->find_by_action_reference('shop:pay');
				if (!$pay_page)
					throw new Cms_Exception('The Pay page is not found.');

				Phpr::$response->redirect(root_url($pay_page->url.'/'.$order->order_hash));
			}

			return $order;
		}

		protected function setCheckoutFollowUpInfo()
		{
			if (array_key_exists('customer_notes', $_POST))
				Shop_CheckoutData::set_customer_notes(post('customer_notes'));

			if (array_key_exists('coupon', $_POST))
				$this->on_setCouponCode(false);
		}

		protected function loadCheckoutBillingStepData($data_updated = false)
		{
			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();

			$billing_info = Shop_CheckoutData::get_billing_info();
			$billing_countries = Shop_Country::get_list($billing_info->country);
			$this->data['countries'] = $billing_countries;

			if ($data_updated)
				$billing_country = $billing_info->country ? $billing_info->country : $billing_countries[0]->id;
			else
			{
				$posted_country = post('country', $billing_info->country);
				$billing_country = $posted_country ? $posted_country : $billing_countries[0]->id;
			}

			$this->data['states'] = Shop_CountryState::create()->where('country_id=?', $billing_country)->order('name')->find_all();

			$this->data['billing_info'] = $billing_info;
			$this->load_checkout_estimated_data(post('cart_name', 'main'));
		}

		protected function loadCheckoutShippingStepData($data_updated = false)
		{
			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$shipping_countries = Shop_Country::get_list($shipping_info->country);
			$this->data['countries'] = $shipping_countries;

			if ($data_updated)
				$shipping_country = $shipping_info->country ? $shipping_info->country : $shipping_countries[0]->id;
			else
			{
				$posted_country = post('country', $shipping_info->country);
				$shipping_country = $posted_country ? $posted_country : $shipping_countries[0]->id;
			}

			$this->data['states'] = Shop_CountryState::create()->where('country_id=?', $shipping_country)->order('name')->find_all();

			$this->data['shipping_info'] = $shipping_info;
			$this->load_checkout_estimated_data(post('cart_name', 'main'));
		}

		/*
		 * Payment functions
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/pay_page Payment} page.
		 * The action loads an order by an order hash string, specified in the page URL and prepares all required
		 * PHP variables.
		 *
		 * @action shop:pay
		 *
		 * @output Shop_Order $order An order object, loaded from the database.
		 * This variable can be NULL of the order is not found.
		 * @output Shop_PaymentMethod $payment_method A payment method, selected by the customer.
		 * @output Shop_PaymentType $payment_method_obj An instance of a specific payment type class, for example the Shop_PayPal_Pro_Payment.
		 * @output $Db_DataCollection $payment_methods A list of applicable payment methods.
		 * Each element of the collection is an object of the {@link Shop_PaymentMethod} class.
		 *
		 * @input string $submit_payment A name of the SUBMIT button element.
		 * The submit button should have name <em>submit_payment</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_pay} handler.
		 *
		 * @ajax shop:on_pay Processes the payment form.
		 * Use this handler for creating the Submit button on the Pay page. Example:
		 * <pre><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_pay')">Pay</a></pre>
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/pay_page Payment page
		 */
		public function pay()
		{
			$this->data['order'] = $order = $this->pay_find_order();
			if (!$this->data['order'])
				return;

			$this->data['payment_method'] = $order->payment_method;
			$order->payment_method->define_form_fields();
			$this->data['payment_method_obj'] = $order->payment_method->get_paymenttype_object();

			$params = array(
				'backend_only'                 => false,
				'ignore_customer_group_filter' => false,
			);
			$this->data['payment_methods'] = Shop_PaymentMethod::list_order_applicable($order, $params)->as_array();

			if (post('submit_payment'))
				$this->on_pay($order);
		}

		public function on_updatePaymentMethod()
		{
			$this->data['order'] = $order = $this->pay_find_order();
			if (!$this->data['order'])
				return;

			$method_id = (int)post('payment_method');
			if (!strlen($method_id))
				throw new Exception('Payment method not found');

			$payment_method = Shop_PaymentMethod::create()->find($method_id);
			if ( !$payment_method || !$payment_method->enabled)
				throw new Exception('Payment method not found');

			$order->payment_method_id = $payment_method->id;
			$order->save();

			$order->payment_method = $payment_method;
			$order->payment_method->define_form_fields();
			$this->data['payment_method'] = $order->payment_method;
			$this->data['payment_method_obj'] = $order->payment_method->get_paymenttype_object();
		}

		/**
		 * Base action for custom payment pages.
		 * This action is similar to the {@link action@shop:pay} action, with only one difference.
		 * The {@link action@shop:pay} action should be used for all standard payment methods which have a payment form.
		 * The shop:payment_information action should be used for custom payment pages, for example for the
		 * {@link http://lemonstand.com/docs/creating_the_bank_transfer_and_other_similar_payment_methods bank transfer payment method}.
		 *
		 * @action shop:payment_information
		 *
		 * @output Shop_Order $order An order object, loaded from the database.
		 * This variable can be NULL of the order is not found.
		 * @output Shop_PaymentMethod $payment_method A payment method, selected by the customer.
		 * @output Shop_PaymentType $payment_method_obj An instance of a specific payment type class, for example the Shop_PayPal_Pro_Payment.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/creating_the_bank_transfer_and_other_similar_payment_methods Bank transfer payment method
		 * @see action@shop:pay
		 */
		public function payment_information()
		{
			$this->pay();
		}

		public function on_pay($order = null)
		{
			if (!$order)
				$order = $this->pay_find_order();

			if (!$order)
				return;

			$order->payment_method->define_form_fields();
			$payment_method_obj = $order->payment_method->get_paymenttype_object();

			if (!post('pay_from_profile') || post('pay_from_profile') != 1)
				$redirect = $payment_method_obj->process_payment_form($_POST, $order->payment_method, $order);
			else
			{
				$redirect = true;
				if (!$this->customer)
					throw new Phpr_ApplicationException('Please log in to pay using the stored credit card.');

				if ($this->customer->id != $order->customer_id)
					throw new Phpr_ApplicationException('The order does not belong to your customer account.');

				$payment_method_obj->pay_from_profile($order->payment_method, $order);
			}

			$return_page = $order->payment_method->receipt_page;
			if ($return_page && ($redirect !== false))
				Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash));
		}

		private function pay_find_order()
		{
			$order_hash = trim($this->request_param(0));
			if (!strlen($order_hash))
				return null;

			$order = Shop_Order::create()->find_by_order_hash($order_hash);
			if (!$order)
				return null;

			if (!$order->payment_method)
				return null;

			$order->payment_method->define_form_fields();

			return $order;
		}

		/*
		 * Payment profile functions
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/implementing_customer_payment_profiles Payment Profile} page.
		 * The payment profile page expects the payment method identifier to be specified in the first URL segment.
		 *
		 * @action shop:payment_profile
		 *
		 * @input string $submit_profile A name for the submit button for saving the payment profile.
		 * The <em>Save</em> submit button should have name <em>submit_profile</em> if you are implementing a regular POST form.
		 * Alternatively you can use the {@link ajax@shop:on_updatePaymentProfile} handler.
		 * @input string $delete_profile A name for the submit button for deleting the payment profile.
		 * The <em>Delete</em> submit button should have name <em>delete_profile</em> if you are implementing a regular POST form.
		 * Alternatively you can use the {@link ajax@shop:on_deletePaymentProfile} handler.
		 *
		 * @output Shop_PaymentMethod $payment_method The payment method object.
		 * This variable can be empty if the payment method not found.
		 * @output Shop_PaymentType $payment_method_obj An instance of a specific payment method class.
		 * Call the <em>render_payment_profile_form()</em> method of this object to render the payment profile form.
		 * @output Shop_CustomerPaymentProfile $payment_profile A payment profile object
		 * If the profile does not exist, this variable has NULL value.
		 *
		 * @ajax shop:on_updatePaymentProfile Updates (or creates) the payment profile.
	     * @ajax shop:on_deletePaymentProfile Deletes the payment profile.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/implementing_customer_payment_profiles Payment Profiles
		 * @see action@shop:payment_profiles
		 */
		public function payment_profile()
		{
			$this->data['payment_method'] = null;

			$this->data['payment_method'] = $this->payment_profile_find_method();
			if (!$this->data['payment_method'])
				return;

			$this->data['payment_method_obj'] = $this->data['payment_method']->get_paymenttype_object();
			$this->data['payment_profile'] = $this->data['payment_method']->find_customer_profile($this->customer);

			if (post('submit_profile'))
				$this->on_updatePaymentProfile($this->data['payment_method']);

			if (post('delete_profile'))
				$this->on_deletePaymentProfile($this->data['payment_method']);
		}

		public function on_updatePaymentProfile($payment_method = null)
		{
			if (!$payment_method)
				$payment_method = $this->payment_profile_find_method();

			if (!$payment_method)
				throw new Phpr_ApplicationException('Payment method not found.');

			if (!$this->customer)
				throw new Phpr_ApplicationException('Please log in to manage payment profiles.');

			$payment_method_obj = $payment_method->get_paymenttype_object();
			$payment_method_obj->update_customer_profile($payment_method, $this->customer, $_POST);

			Phpr::$session->flash['success'] = 'The payment profile has been successfully updated.';
			$return_page = Cms_Page::create()->find_by_action_reference('shop:payment_profiles');
			if (!$return_page)
				throw new Cms_Exception('The Payment Profiles page is not found.');

			Phpr::$response->redirect(root_url($return_page->url));
		}

		public function on_deletePaymentProfile($payment_method = null)
		{
			if (!$payment_method)
				$payment_method = $this->payment_profile_find_method();

			if (!$payment_method)
				throw new Phpr_ApplicationException('Payment method not found.');

			if (!$this->customer)
				throw new Phpr_ApplicationException('Please log in to manage payment profiles.');

			$payment_method_obj = $payment_method->get_paymenttype_object();
			$payment_method->delete_customer_profile($this->customer);

			if (!post('no_flash'))
				Phpr::$session->flash['success'] = post('message', 'The payment profile has been successfully deleted.');

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/implementing_customer_payment_profiles Payment Profiles} page.
		 *
		 * @action shop:payment_profiles
		 *
		 * @output array $payment_methods A list of payment methods which support payment profiles.
		 * Each element in the collection is an object of {@link Shop_PaymentMethod} class.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/implementing_customer_payment_profiles Payment Profiles
		 * @see action@shop:payment_profile
		 */
		public function payment_profiles()
		{
			$this->data['payment_methods'] = array();

			if (!$this->customer)
				return;

			$methods = Shop_PaymentMethod::list_applicable($this->customer->billing_country_id, 1);
			$payment_profile_methods = array();
			foreach ($methods as $method)
			{
				if ($method->supports_payment_profiles())
					$payment_profile_methods[] = $method;
			}

			$this->data['payment_methods'] = $payment_profile_methods;
		}

		protected function payment_profile_find_method()
		{
			$method_id = trim($this->request_param(0));
			if (!strlen($method_id))
				return null;

			$obj = Shop_PaymentMethod::create()->find($method_id);
			if ($obj)
				$obj->define_form_fields();

			return $obj;
		}

		/*
		 * Customer orders functions
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/customer_orders_page customer order list} page.
		 * The action loads a list of a currently logged in customer orders and prepares a corresponding PHP variable.
		 * Please note that the action requires a logged in customer, so a page, the action is assigned to, must has
		 * the <em>Customers Only</em> security mode enabled.
		 *
		 * @action shop:orders
		 * @output Db_DataCollection $orders A collection of customer orders.
		 * Each element in the collection is an object of the {@link Shop_Order} class. This variable can be NULL
		 * in case if there is no currently logged in customer. Always check whether the variable is not empty before displaying a list of orders.
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/customer_orders_page Creating the Customer Orders page
		 * @see action@shop:order
		 */
		public function orders()
		{
			$this->data['orders'] = null;

			$customer = $this->customer;
			if (!$customer)
				return;

			$orders = Shop_Order::create()->where('deleted_at is null')->where('customer_id=?', $customer->id)->order('order_datetime desc')->find_all();
			$this->data['orders'] = $orders;
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/order_details_page Order Details} page.
		 * The action loads an order object with an identifier specified in the page URL. Please note that the action requires a logged in
		 * customer, so a page, the action is assigned to, must has the <em>Customers Only</em> security mode enabled.
		 *
		 * @action shop:order
		 * @output Shop_Order $order An order object, loaded from the database.
		 * The variable value can be NULL if the order, specified in the URL, was not found.
		 * @output Db_DataCollection $items A list of the order items.
		 * Each element of the collection is an object of the {@link Shop_OrderItem} class.
		 * This variable exists only in case if the requested order was found in the database.
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/order_details_page Creating the Order Details page
		 * @see action@shop:orders
		 */
		public function order()
		{
			$this->data['order'] = null;

			$order_id = $this->request_param(0);
			if (!strlen($order_id))
				return;

			$order = Shop_Order::create()->find($order_id);
			if (!$order)
				return;

			if (!$this->customer)
				return;

			if ($order->customer_id != $this->customer->id)
				return;

			$this->data['order'] = $order;
			$this->data['items'] = $order->items;
		}

		/*
		 * Product search
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/creating_the_search_page Search} page.
		 * The action finds products in the database, according to a search query and other search parameters passed to the search
		 * page in the URL. Please read the {@link http://lemonstand.com/docs/creating_the_search_page Creating the Search page} article
		 * for action usage examples.
		 *
		 * The action automatically loads GET parameters from the URL and performs the search. Example of a correct search URL:
		 * <em>/search/query=laptop&records=6</em>. Example of a simple search form:
		 * <pre>
		 * <form method="get" action="/search">
		 *   <input name="query" type="text" value="<?= isset($query) ? $query : null ?>"/>
		 *   <input type="submit" value="Find Products"/>
		 *   <input type="hidden" name="records" value="6"/>
		 * </form>
		 * </pre>
		 * The <em>option_names</em> and <em>option_values</em> parameters described below work in the following way.
		 * You can define any number of the <em>option_names</em> and <em>option_values</em> fields. On the form you should have an equal number of
		 * the <em>option_names[]</em> and <em>option_values[]</em> fields. LemonStand matches names and values specified in fields
		 * with equal index. The first value from the <em>option_values[]</em> field will correspond the first <em>option_names[]</em> field.
		 * For example, if you want to organize a search in the Color option (and only), you can define two fields (one of them is hidden):
		 * <pre>
		 * <form method="get" action="/search">
		 *   <input name="query" type="text" value="<?= isset($query) ? $query : null ?>"/>
		 *   <input type="submit" value="Find Products"/>
		 *   <input type="hidden" name="records" value="6"/>
		 *
		 *   <input type="hidden" name="option_names[]" value="Color"/>
		 *   Product color:
		 *   <input type="text" name="option_values[]" value=""/>
		 * </form>
		 * </pre>
		 * The <em>attribute_names</em> and <em>attribute_values</em> fields work in the same way. You can find more examples in
		 * the {@link http://lemonstand.com/docs/creating_the_search_page Creating the Search page} article.
		 *
		 * @output boolean $no_query Indicates whether no query was provided by a visitor.
		 * The value is TRUE is the search query string is empty and no other parameters were specified.
		 * @output string $query A search query string passed to the search page through the page URL.
		 * @output float $min_price The <em>min_price</em> value specified in the search form.
		 * @output float $max_price The <em>max_price</em> value specified in the search form.
		 * @output string $sorting The sorting value specified in the search form.
		 * @output array $selected_categories A list of category identifiers specified in the search form.
		 * @output array $selected_manufacturers A list of manufacturer identifiers specified in the search form.
		 * @output array $selected_options A list of product options specified in the search form.
		 * @output array $selected_attributes A list of product attributes specified in the search form.
		 * @output string $search_params_str A string, containing all search parameters in a URL format.
		 * You can pass this string between pagination pages. In the default store implementation this value can be passed to the
		 * suffix parameter of the pagination partial.
		 * @output integer $records A number of records per page to output on the search page.
		 * The default parameter value is 20.
		 * @output Db_DataCollection $products A collection of found products.
		 * Each element in the collection is an object of the {@link Shop_Product} class.
		 * @output Phpr_Pagination $pagination A pagination object.
		 *
		 * @action shop:search
		 *
		 * @input string $query Specifies the search query string.
		 * @input integer $records Specifies a number of products to output on a single page.
		 * @input float $min_price Specifies the minimum product price.
		 * You can use this and the <em>max_price</em> parameters to limit products with a price range.
		 * @input float $max_price Specifies the maximum product price.
		 * @input array $categories A list of category identifiers the products should belong to.
		 * This parameter should be an array, so you need to use the <em>categories[]</em> name for your form controls.
		 * You can use either checkboxes or a SELECT element for specifying category identifiers.
		 * @input array $custom_groups A list of {@link Shop_CustomGroup custom product group} identifiers the products should belong to.
		 * This parameter should be an array, so you need to use the <em>custom_groups[]</em> name for your form controls.
		 * You can use either checkboxes or a SELECT element for specifying custom group identifiers.
		 * @input string $sorting Manages the search result sorting.
		 * Supported values are:
		 * <ul>
		 *   <li><em>relevance</em> (default)</li>
		 *   <li><em>name</em></li>
		 *   <li><em>name desc</em></li>
		 *   <li><em>price</em></li>
		 *   <li><em>price desc</em></li>
		 *   <li><em>created_at</em></li>
		 *   <li><em>created_at desc</em></li>
		 *   <li><em>product_rating</em></li>
		 *   <li><em>product_rating desc</em></li>
		 *   <li><em>product_rating_all</em></li>
		 *   <li><em>product_rating_all desc</em></li>
		 * </ul>
		 * @input array $manufacturers A list of manufacturer identifiers the products should belong to.
		 * This parameter should be an array, so you need to use the <em>manufacturers[]</em> name for your form controls.
		 * You can use either checkboxes or a SELECT element for specifying manufacturer identifiers.
		 * @input array $option_names A list of product option names.
		 * Use this and the <em>option_values</em> parameters to search in product options.
		 * This parameter should be an array, so you need to use the <em>option_names[]</em> name for your form controls.
		 * @input array $option_values A list of product option values.
		 * Use this parameter in combination with the <em>option_names</em> parameter to specify values you want to search in product options.
		 * This parameter should be an array, so you need to use the <em>option_values[]</em> name for your form controls.
		 * @input array $attribute_names A list of product attribute names.
		 * Use this and the <em>attribute_values</em> parameters to search in product attributes.
		 * This parameter should be an array, so you need to use the <em>attribute_names[]</em> name for your form controls.
		 * @input array $attribute_values A list of product attribute values.
		 * Use this parameter in combination with the <em>attribute_names</em> parameter to specify values you
		 * want to search in product attributes. This parameter should be an array, so you need to use the <em>attribute_values[]</em> name for your form controls.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/creating_the_search_page Creating the Search page
		 * @see Shop_Product::find_products()
		 */
		public function search()
		{
			$request = trim(Phpr::$request->getField('query'));
			$request = urldecode($request);
			$this->data['query'] = Phpr_Html::encode($request);

			/*
			 * Load categories
			 */

			$categories = Phpr::$request->get_value_array('categories');
			if (!is_array($categories))
				$categories = array();

			$categories_specified = false;
			foreach ($categories as $category)
			{
				if (strlen($category))
				{
					$categories_specified = true;
					break;
				}
			}

			/*
			 * Load manufacturers
			 */

			$manufacturers = Phpr::$request->get_value_array('manufacturers');
			if (!is_array($manufacturers))
				$manufacturers = array();

			$manufacturers_specified = false;
			foreach ($manufacturers as $manufacturer)
			{
				if (strlen($manufacturer))
				{
					$manufacturers_specified = true;
					break;
				}
			}

			/*
			 * Load options
			 */

			$option_names = Phpr::$request->get_value_array('option_names');
			if (!is_array($option_names))
				$option_names = array();

			$option_values = Phpr::$request->get_value_array('option_values');
			if (!is_array($option_values))
				$option_values = array();

			$selected_options = array();
			$options_specified = false;
			foreach ($option_names as $index=>$name)
			{
				if (array_key_exists($index, $option_values))
				{
					if ( !is_array( $option_values[ $index ] ) )
					{
						$selected_options[ $name ] = urldecode( $option_values[ $index ] );

						if ( strlen( trim( $option_values[ $index ] ) ) )
							$options_specified = true;
					}
					else
					{
						$option_values_array = array();

						foreach ( $option_values[ $index ] as $att_value_index => $att_value )
							 $option_values_array[] = trim( urldecode( $att_value ) );

						$selected_options[ $name ] = $option_values_array;

						 if ( !empty( $selected_options[ $name ] )  )
							$options_specified = true;
					}
				}
			}

			/*
			 * Load attributes
			 */

			$attribute_names = Phpr::$request->get_value_array('attribute_names');
			if (!is_array($attribute_names))
				$attribute_names = array();

			$attribute_values = Phpr::$request->get_value_array('attribute_values');
			if (!is_array($attribute_values))
				$attribute_values = array();

			$selected_attributes = array();
			$attributes_specified = false;
			foreach ($attribute_names as $index=>$name)
			{
				if (array_key_exists($index, $attribute_values))
				{
					if ( !is_array( $attribute_values[ $index ] ) )
					{
						$selected_attributes[ $name ] = urldecode( $attribute_values[ $index ] );

						if ( strlen( trim( $attribute_values[ $index ] ) ) )
							$attributes_specified = true;
					}
					else
					{
						$attribute_values_array = array();

						foreach ( $attribute_values[ $index ] as $att_value_index => $att_value )
							 $attribute_values_array[] = trim( urldecode( $att_value ) );

						$selected_attributes[ $name ] = $attribute_values_array;

						 if ( !empty( $selected_attributes[ $name ] )  )
							$attributes_specified = true;
					}
				}
			}

			/*
			 * Load custom groups
			 */

			$custom_groups = Phpr::$request->get_value_array('custom_groups');
			if (!is_array($custom_groups))
				$custom_groups = array();

			$custom_groups_specified = false;
			foreach ($custom_groups as &$custom_group)
			{
				$custom_group = urldecode($custom_group);
				if (strlen($custom_group))
					$custom_groups_specified = true;
			}

			/*
			 * Load price range
			 */

			$min_price = urldecode(trim(Phpr::$request->getField('min_price')));
			$max_price = urldecode(trim(Phpr::$request->getField('max_price')));

			if (!strlen($min_price))
				$min_price = null;

			if (!strlen($max_price))
				$max_price = null;

			/*
			 * Run the search request
			 */

			$page = $this->request_param(0, 1);
			$records = trim(Phpr::$request->getField('records', 20));
			if ($records < 1)
				$records = 1;

			$max_records = Phpr::$config->get('SEARCH_MAX_RECORDS');
			if (strlen($max_records) && $records > $max_records)
				$records = $max_records;

			$sorting = Phpr::$request->getField('sorting');

			$this->data['sorting'] = $sorting;
			$this->data['records'] = $records;
			$this->data['selected_categories'] = $categories;
			$this->data['selected_custom_groups'] = $custom_groups;
			$this->data['selected_options'] = $selected_options;
			$this->data['selected_attributes'] = $selected_attributes;
			$this->data['selected_manufacturers'] = $manufacturers;
			$this->data['min_price'] = $min_price;
			$this->data['max_price'] = $max_price;

			$no_query = $this->data['no_query'] = !(
				strlen($request)
				|| $options_specified
				|| $categories_specified
				|| $custom_groups_specified
				|| $attributes_specified
				|| $manufacturers_specified
				|| strlen($min_price)
				|| strlen($max_price)
			);

			$pagination = new Phpr_Pagination($records);
			if (!$no_query)
			{
				$options = array();
				$options['category_ids'] = $categories;
				$options['manufacturer_ids'] = $manufacturers;
				$options['options'] = $selected_options;
				$options['attributes'] = $selected_attributes;
				$options['min_price'] = $min_price;
				$options['max_price'] = $max_price;
				$options['sorting'] = $sorting;
				$options['custom_groups'] = $custom_groups;

				$this->data['products'] = Shop_Product::find_products($request, $pagination, $page, $options);
			} else
				$this->data['products'] = Shop_Product::create()->where('shop_products.id<>shop_products.id');

			/*
			 * Format search parameters
			 */

			$search_params = array();
			$search_params[] = 'query='.urlencode($request);
			$search_params[] = 'records='.urlencode($records);
			$search_params[] = 'min_price='.urlencode($min_price);
			$search_params[] = 'max_price='.urlencode($max_price);
			$search_params[] = 'sorting='.urlencode($sorting);

			$this->format_search_array_value($categories, 'categories[]', $search_params);
			$this->format_search_array_value($custom_groups, 'custom_groups[]', $search_params);
			$this->format_search_array_value($manufacturers, 'manufacturers[]', $search_params);
			$this->format_search_array_value($option_names, 'option_names[]', $search_params);
			$this->format_search_array_value($option_values, 'option_values[]', $search_params);
			$this->format_search_array_value($attribute_names, 'attribute_names[]', $search_params);
			$this->format_search_array_value($attribute_values, 'attribute_values[]', $search_params);

			$search_params_str = implode('&amp;', $search_params);
			$this->data['search_params_str'] = '?'.$search_params_str;

			$this->data['pagination'] = $pagination;
		}

		private function format_search_array_value($values, $name, &$search_params)
		{
			foreach ( $values as $value )
			{
				if ( !is_array( $value ) )
			 		$search_params[] = $name . '=' . urlencode( $value );
			 	else
			 	{
			 		$values_array = array();

			 		foreach ( $value as $att_value_index => $att_value )
			 			$values_array[] = urlencode( $att_value );

			 		$search_params[] = $name . '=' . implode( '&' . $name . '=', $values_array );
			 	}
			}
		}

		/*
		 * Compare list functions
		 */

		/**
		 * Adds a product to the product comparison list.
		 * Read the {@link http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products Page} article for the
		 * implementation details. Usage example:
		 * <pre>
		 * <a href="#" onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_addToCompare', {
		 *      onSuccess: function(){alert('The product has been added to the compare list')},
		 *      extraFields: {product_id: '<?= $product->id ?>'},
		 *      update: {compare_list: 'shop:compare_list'}
		 * });">Add to compare</a>
		 * </pre>
		 * @input integer $product_id Specifies the {@link Shop_Product product} identifier. Required.
		 * @ajax shop:on_addToCompare
		 * @see http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products Page
		 * @see action@shop:compare
		 * @see ajax@shop:on_removeFromCompare
		 * @see ajax@shop:on_clearCompareList
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_addToCompare()
		{
			$product_id = trim(post('product_id'));

			if (!strlen($product_id) || !preg_match('/^[0-9]+$/', $product_id))
				throw new Cms_Exception('Product not found.');

			Shop_ComparisonList::add_product($product_id);
		}

		/**
		 * Removes a product from the product comparison list.
		 * Usually this handler used in a comparison list partial.
		 * Read the {@link http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products Page} article for the
		 * implementation details. Usage example:
		 * <pre>
		 * <a href="#" onclick="$(this).getForm().sendRequest(
		 *   'shop:on_removeFromCompare', {
		 *     extraFields: {product_id: '<?= $product->id ?>'},
		 *     update: {compare_list: 'shop:compare_list'}
		 * }); return false">Remove</a>
		 * </pre>
		 * @input integer $product_id Specifies the {@link Shop_Product product} identifier. Required.
		 * @ajax shop:on_removeFromCompare
		 * @see http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products Page
		 * @see action@shop:compare
		 * @see ajax@shop:on_addToCompare
		 * @see ajax@shop:on_clearCompareList
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_removeFromCompare()
		{
			$product_id = trim(post('product_id'));

			if (!strlen($product_id) || !preg_match('/^[0-9]+$/', $product_id))
				return;

			Shop_ComparisonList::remove_product($product_id);
			$this->compare();
		}

		/**
		 * Removes all products from the product comparison list.
		 * Read the {@link http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products Page} article for the
		 * implementation details. Usage example:
		 * <pre>
		 * <a href="#" onclick="return $(this).getForm().sendRequest(
		 *   'shop:on_clearCompareList', {
		 *     confirm: 'Do you really want to remove all products from the compare list?',
		 *     update: {compare_list: 'shop:compare_list'}
		 *   });">clear list</a>
		 * </pre>
		 * @ajax shop:on_clearCompareList
		 * @see http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products Page
		 * @see action@shop:compare
		 * @see ajax@shop:on_addToCompare
		 * @see ajax@shop:on_removeFromCompare
		 * @author LemonStand eCommerce Inc.
		 * @package shop.ajax handlers
		 */
		public function on_clearCompareList()
		{
			Shop_ComparisonList::clear();
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products} page.
		 * The action loads a list of products, added to the product comparison list, and generates corresponding PHP variables.
		 *
		 * @action shop:compare
		 * @output Db_DataCollection $products A list of products added to the comparison list.
		 * Each element in the collection is an object of the {@link Shop_Product} class.
		 * @output array $attributes - A list of product attributes of all products in the comparison list.
		 * You can manage product attributes on the Attributes tab of the Create/Edit Product page.
		 * To output an attribute value by its name, you can use the following code:
		 * <pre><?= h($product->get_attribute($attribute)) ?></pre>
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/category_page Creating the Category Page
		 * @see http://lemonstand.com/docs/implementing_the_compare_products_feature Compare Products Page
		 * @see ajax@shop:on_addToCompare
		 * @see ajax@shop:on_removeFromCompare
		 * @see ajax@shop:on_clearCompareList
		 */
		public function compare()
		{
			$products = $this->data['products'] = Shop_ComparisonList::list_products();

			$all_attribute_names = array();
			foreach ($products as $product)
			{
				foreach ($product->properties as $attribute)
				{
					$key = mb_strtolower($attribute->name);
					if (!array_key_exists($key, $all_attribute_names))
						$all_attribute_names[$key] = $attribute->name;
				}
			}

			$this->data['attributes'] = $all_attribute_names;
		}

		/*
		 * Manufacturers
		 */

		/**
		 * Base action for the {@link http://lemonstand.com/docs/manufacturer_list_page/ Creating the Manufacturer List} page.
		 * The action doesn't return disabled manufacturers.
		 *
		 * @action shop:manufacturers
		 * @output Db_DataCollection $manufacturers A collection of manufacturers.
		 * Each element in the collection is an instance of the {@link Shop_Manufacturer} class.
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/manufacturer_list_page/ Creating the Manufacturer List Page
		 * @see action@shop:manufacturer
		 */
		public function manufacturers()
		{
			$this->data['manufacturers'] = Shop_Manufacturer::create()->order('name')->where('(is_disabled is null or is_disabled=0)')->find_all();
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/manufacturer_list_page/ Manufacturer Details} page.
		 * The action loads a manufacturer object by its URL Name specified in the page URL. If the requested manufacturer
		 * is disabled, it won't be returned by the action.
		 *
		 * @action shop:manufacturer
		 * @output Shop_Manufacturer $manufacturer A manufacturer object loaded from the database.
		 * The variable value can be NULL if the manufacturer specified in the page URL is not found in the database.
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 * @see http://lemonstand.com/docs/manufacturer_details_page/ Creating the Manufacturer Page
		 * @see action@shop:manufacturers
		 */
		public function manufacturer()
		{
			$this->data['manufacturer'] = null;

			$url_name = trim($this->request_param(0));
			if (!strlen($url_name))
				return;

			$manufacturer = Shop_Manufacturer::create()->where('(is_disabled is null or is_disabled=0)')->find_by_url_name($url_name);
			if (!$manufacturer)
				return;

			$this->data['manufacturer'] = $manufacturer;
		}

		/**
		 * Base action for the {@link http://lemonstand.com/docs/customer_profile_page/ Customer Profile} page.
		 *
		 * @action shop:customer_profile
		 *
		 * @input string $submit_profile A name for the submit button for saving the payment profile.
		 * The <em>Save</em> submit button should have name <em>submit_profile</em> if you are implementing a regular POST form.
		 * Alternatively you can use the {@link ajax@shop:on_updateCustomerProfile} handler.
		 *
		 * @output Db_DataCollection $countries A collection of countries for populating the Billing Country and Shipping Country lists.
		 * Each element in the collection is an object of the {@link Shop_Country} class.
		 * @output Db_DataCollection $billing_states A collection of states for populating the Billing State list.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class
		 * @output Db_DataCollection $shipping_states A collection of states for populating the Shipping State list.
		 * Each element in the collection is an object of the {@link Shop_CountryState} class
		 *
		 * @input string $first_name Specifies the customer first name. Required.
		 * @input string $last_name Specifies the customer last name. Required.
		 * @input string $email Specifies the customer email. Required.
		 * @input string $company Specifies the customer's company name.
		 * @input string $phone Specifies the phone number.
		 * @input integer $billing_state_id An identifier of the customer billing {@link Shop_CountryState state}.
		 * @input integer $billing_country_id An identifier of the customer billing {@link Shop_Country country}.
		 * @input string $billing_street_addr Specifies the billing street address.
		 * @input string $billing_city Specifies the billing city name.
		 * @input string $billing_zip Specifies the billing ZIP/Postal code.
		 *
		 * @input string $shipping_first_name Specifies the customer shipping first name. Required.
		 * @input string $shipping_last_name Specifies the customer shipping last name. Required.
		 * @input string $shipping_company Specifies the customer's sipping company name.
		 * @input string $shipping_phone Specifies the phone shipping number.
		 * @input integer $shipping_state_id An identifier of the customer shipping {@link Shop_CountryState state}.
		 * @input integer $shipping_country_id An identifier of the customer shipping {@link Shop_Country country}.
		 * @input string $shipping_street_addr Specifies the shipping street address.
		 * @input string $shipping_city Specifies the shipping city name.
		 * @input string $shipping_zip Specifies the shipping ZIP/Postal code.
		 *
		 * @input string $redirect An optional URL to redirect the visitor's browser after updating the profile.
		 * Note that if your copy of LemonStand is installed in a subdirectory, you need to use the {@link root_url()} function
		 * in the <em>redirect</em> field value.
		 * @input string $flash A message to display on the target redirection page.
		 * Use the {@link flash_message()} function on the target page to display the message.
		 *
		 * @ajax shop:on_updateCustomerProfile Updates the customer profile.
		 *
		 * @author LemonStand eCommerce Inc.
		 * @see http://lemonstand.com/docs/customer_profile_page/ Creating the Customer Profile page
		 * @package shop.actions
		 */
		public function customer_profile()
		{
			$billing_countries = Shop_Country::get_list();
			$this->data['countries'] = $billing_countries;

			$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', post('billing_country_id', $this->customer->billing_country_id))->order('name')->find_all();
			$this->data['shipping_states'] = Shop_CountryState::create(true)->where('country_id=?', post('shipping_country_id', $this->customer->shipping_country_id))->order('name')->find_all();

			if (post('submit_profile'))
				$this->on_updateCustomerProfile();
		}

		public function on_updateCustomerProfile()
		{
			$customer = Shop_Customer::create()->where('id=?', $this->customer->id)->find(null, array(), 'front_end');
			$customer->disable_column_cache('front_end', true);
			$customer->init_columns_info('front_end');
			$customer->validation->focusPrefix = null;
			$customer->password = null;
			$customer->save($_POST);

			Shop_CheckoutData::load_from_customer($customer, true);

			if (post('flash'))
				Phpr::$session->flash['success'] = post('flash');

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		/**
		 * The action generates a password restore hash for a customer with the specified email address and sends an
		 * email notification to the customer (using shop:password_restore email template).
		 * Usage example:
		 * <pre>
		 * <?= flash_message() ?>
		 *
		 * <? if ($action_mode == 'restore_request' || isset($invalid_hash)): ?>
		 *   <? if (!isset($invalid_hash)): ?>
		 *     <?= open_form() ?>
		 *   <? else: ?>
		 *     <?= open_form(array('action' => root_url($this->page->url, true))) ?>
		 *   <? endif ?>
		 *       Please enter your email below, we will send you an email with a link to enter a new password for your account.<br>
		 *       Email: <input type="text" value="" name="customer_email"><br>
		 *       <input type="hidden" name="success_message" value="Password restore email was sent.">
		 *       <input type="submit" value="Request Password Reset" name="password_restore_submit">
		 *     </form>
		 * <? else: ?>
		 *   <?= open_form() ?>
		 *     Enter a new password for your account below and confirm the change.<br>
		 *     Password: <input type="password" name="new_password" value=""><br>
		 *     Confirm Password: <input type="password" name="confirm_password" value=""><br>
		 *     <input type="hidden" name="redirect" value="<?= root_url('login', true) ?>">
		 *     <input type="hidden" name="success_message" value="Your password was successfully changed, you may now login with your new password.">
		 *     <input type="submit" value="Set new password" name="password_restore_submit">
		 *   </form>
		 * <? endif ?>
		 * </pre>
		 *
		 * @action shop:password_restore_request
		 *
		 * @input string $customer_email Specifies the customer's email address. Required when requesting a password restore.
		 * @input string $password_restore_submit Optional SUBMIT input element name.
		 * The submit button should have name <em>password_restore_submit</em> if you are implementing a regular POST form.
		 * Alternatively you can create an AJAX form with {@link handler@shop:on_passwordRestoreRequest} handler.
		 * @input string $redirect An optional field containing an URL for redirecting the browser after password restore request has been submitted.
		 * Use {@link root_url()} function if your copy of LemonStand is installed in a subdirectory.
		 * @input string $success_message An optional message to display after the redirection.
		 * Use the {@link flash_message()} function on the target page to display the message.
		 * @output string $action_mode Set to 'password_update' when the page url contains the password restore hash (customer is expected to enter a new password) and 'restore_request' when it does not (customer will enter their email to request a password restore).
		 * @output boolean $success Set to true if a password was successfully reset or the password restore email was sent.
		 * @output boolean $invalid_hash Set to true if the password restore link is invalid (invalid hash), was previously used or is expired (hash was generated more than 24 hours ago).
		 *
		 * @ajax shop:on_passwordRestore Processes the password restore form.
		 * Use this handler for creating a Submit link or button on the password restore page. Example:
		 * <pre><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_passwordRestoreRequest')">Submit</a></pre>
		 *
		 * @author LemonStand eCommerce Inc.
		 * @package shop.actions
		 */
		public function password_restore_request()
		{
			$hash = trim($this->request_param(0));
			if(strlen($hash))
			{
				$this->data['action_mode'] = 'password_update';
			}
			else $this->data['action_mode'] = 'restore_request';

			if(strlen(post('password_restore_submit')))
				$this->on_passwordRestoreRequest();
			elseif(strlen($hash) && !Shop_Customer::get_from_password_reset_hash($hash))
			{
				$this->data['invalid_hash'] = true;
				Phpr::$session->flash['error'] = 'Invalid or expired link.';
			}
		}

		public function on_passwordRestoreRequest()
		{
			$hash = trim($this->request_param(0));
			if(strlen($hash))
			{
				$this->data['action_mode'] = 'password_update';
				$validation = new Phpr_Validation();
				$validation->add('new_password', 'Password')->fn('trim')->required("Please specify new password");
				$validation->add('confirm_password', 'Password Confirmation')->fn('trim')->matches('new_password', 'Password and confirmation password do not match.');
				$customer = Shop_Customer::get_from_password_reset_hash($hash);
				if(!$customer)
				{
					$this->data['invalid_hash'] = true;
					$validation->setError('Invalid or expired link.', null, true);
					$validation->throwException();
				}
				elseif (!$validation->validate($_POST))
					$validation->throwException();

				try
				{
					$customer->disable_column_cache('front_end', true);
					$customer->password = $validation->fieldValues['new_password'];
					$customer->password_confirm = $validation->fieldValues['confirm_password'];
					$customer->password_restore_hash = null;
					$customer->password_restore_time = null;
					$customer->save();
					$this->data['success'] = true;

					if (post('success_message'))
						Phpr::$session->flash['success'] = post('success_message');

					$redirect = post('redirect');
					if ($redirect)
						Phpr::$response->redirect($redirect);
				}
				catch (Exception $ex)
				{
					throw new Cms_Exception($ex->getMessage());
				}
			}
			else
			{
				$this->data['action_mode'] = 'restore_request';
				$validation = new Phpr_Validation();
				$validation->add('customer_email', 'Email')->fn('trim')->required('Please specify your email address')->email()->fn('mb_strtolower');
				if (!$validation->validate($_POST))
					$validation->throwException();

				try
				{
					if(Shop_Customer::send_password_restore($validation->fieldValues['customer_email']))
					{
						if (post('success_message'))
							Phpr::$session->flash['success'] = post('success_message');
						$this->data['success'] = true;

						$redirect = post('redirect');
						if ($redirect)
							Phpr::$response->redirect($redirect);
					}
					else throw new Cms_Exception('Email not sent.');
				}
				catch (Exception $ex)
				{
					throw new Cms_Exception($ex->getMessage());
				}
			}
		}
	}
?>