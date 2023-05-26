<?
	/**
	 * Represents an item in the shopping cart. 
	 * Normally you don't need to create objects of this class. To access the shopping cart items use the 
	 * {@link Shop_Cart} class. Please read the {@link http://lemonstand.com/docs/cart_page/ Creating the Cart page} 
	 * article for examples of the class usage.
	 * @documentable
	 * @see http://lemonstand.com/docs/cart_page/ Creating the Cart page
	 * @author LemonStand eCommerce Inc.
	 * @package shop.classes
	 */
	class Shop_CartItem implements Shop_RetailItem, Shop_BundleItem, Shop_ShippableItem
	{
		/**
		 * @var string Specifies the item key.
		 * Item keys are used for identifying items in the cart.
		 * @documentable
		 */
		public $key;
		
		/**
		 * @var Shop_Product A product object corresponding to the cart item.
		 * @documentable
		 */
		public $product;
		
		/**
		 * @var array An array of selected product options. 
		 * Each element of the array is another array with keys corresponding the option name 
		 * and values corresponding the option value: array('Color'=>'Blue')
		 * @documentable
		 */
		public $options = array();
		
		/**
		 * @var array An array of extra paid options, selected by a customer. 
		 * Each element in the array is a PHP object with two fields: <em>$price</em> and <em>$description</em>.
		 * @documentable
		 */
		public $extra_options = array();
		
		/**
		 * @var integer Specifies the product quantity.
		 * @documentable
		 */
		public $quantity = 0;
		
		/**
		 * @var boolean Determines whether the cart item is postponed.
		 * @documentable
		 */
		public $postponed = false;
		
		/**
		 * @var string Specifies a name of the cart the item belongs to.
		 * @documentable
		 */
		public $cart_name = 'main';

		public $free_shipping = false;
		public $native_cart_item = null;
		public $price_preset = false;
		public $applied_discount = 0;
		
		/**
		 * This field used in the manual discount management on the Edit Order page
		 */
		public $ignore_product_discount = false;

		/**
		 * @var Shop_OrderItem Specifies a reference to the original order item object.
		 * This property is not empty in case if the cart item was created by converting an order item to the cart item.
		 * @see Shop_OrderItem::convert_to_cart_item()
		 * @documentable
		 */
		public $order_item = null;

        protected $currencyCode = null;

		protected $de_cache = array();
		
		/**
		 * Returns a string, describing selected product options. 
		 * The returned string has the following format: <em>Color: white; Size: M</em>. 
		 * Use this method to simplify the code for displaying the shopping cart content.
		 * @documentable
		 * @param string $delimiter Specifies a delimiter string. 
		 * @param boolean $lowercase_values Convert option values to lower case.
		 * @return string Returns the item options description.
		 */
		public function options_str($delimiter = '; ', $lowercase_values = false)
		{
			$result = array();
			
			if (!Phpr::$config->get('DISABLE_GROUPED_PRODUCTS') && $this->product->grouped_option_desc)
				$result[] = $this->product->grouped_menu_label.': '.$this->product->grouped_option_desc;
			
			foreach ($this->options as $name=>$value) 
			{
				if ($lowercase_values)
					$value = mb_strtolower($value);
				
				$result[] = $name.': '.$value;
			}
				
			return implode($delimiter, $result);
		}
		
		protected function get_effective_quantity()
		{
			$effective_quantity = $this->quantity;
			
			$controller = Cms_Controller::get_instance();
			if ($controller && $controller->customer && $this->product->tier_prices_per_customer)
				$effective_quantity += $controller->customer->get_purchased_item_quantity($this->product);
				
			return $effective_quantity;
		}

		/**
		 * Evaluates price of a single product unit, taking into account extra paid options
		 */
		public function single_price_no_tax($include_extras = true, $effective_quantity = null)
		{
			if ($this->price_preset === false)
			{
				$external_price = Backend::$events->fireEvent('shop:onGetCartItemPrice', $this);
				$external_price_found = false;
				foreach ($external_price as $price) 
				{
					if (strlen($price))
					{
						$result = $price;
						$external_price_found = true;
						break;
					}
				}

				if (!$external_price_found)
				{
					$bundle_offer_item = $this->get_bundle_offer_item();
					if ($bundle_offer_item)
					{

						$options = array();
						foreach ($this->options as $key=>$value)
						{
							if (!preg_match('/^[a-f0-9]{32}$/', $value))
								$options[md5($key)] = $value;
							else
								$options[$key] = $value;
						}

						$price = $bundle_offer_item->get_price_no_tax($this->product, $effective_quantity, null, $options);
                        /*
                         * NOTE
                         * Price overrides on bundle items are not factored into cart totals as a discount,
                         * therefore in this context the price override is applied to find the list price.
                         */
                        $result = $bundle_offer_item->apply_price_override($price);
					} else 
					{
						$effective_quantity = $effective_quantity ? $effective_quantity : $this->get_effective_quantity();
						$om_record = $this->get_om_record();
						if (!$om_record)
							$result = $this->product->price_no_tax($effective_quantity);
						else
							$result = $om_record->get_price($this->product, $effective_quantity, null, true);
					}
				}
				
				$updated_price = Backend::$events->fireEvent('shop:onUpdateCartItemPrice', $this, $result);
				foreach ($updated_price as $price) 
				{
					if (strlen($price))
					{
						$result = $price;
						break;
					}
				}
			}
			else
				$result = $this->price_preset;

			if ($include_extras)
			{
				foreach ($this->extra_options as $option)
					$result += $option->get_price_no_tax($this->product);
			}

			return $result;
		}
		
		public function total_single_price()
		{
			$discount = $this->price_preset === false ? $this->get_sale_reduction() : 0;
			return $this->single_price_no_tax(true) - $discount;
		}
		
		/**
		 * Returns price of a single unit of the cart item, taking into account extra paid options. 
		 * The discount value is not subtracted from the single price.
		 * Behavior of this method can be altered by {@link shop:onGetCartItemPrice} and {@link shop:onUpdateCartItemPrice} event handlers.
		 * @documentable
		 * @param boolean $include_extras Specifies whether extra options price should be included to the result.
		 * @return float Returns the item price.
		 */
		public function single_price($include_extras = true)
		{
			$price = $this->single_price_no_tax($include_extras) - $this->get_sale_reduction() ;

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;
			
			return Shop_TaxClass::get_total_tax($this->get_tax_class_id(), $price) + $price;
		}


        public function get_extras_cost()
		{
			$result = 0;
			
			foreach ($this->extra_options as $option)
				$result += $option->get_price_no_tax($this->product);
			
			return $result;
		}
		
		/**
		 * Returns item quantity for displaying on pages. 
		 * For regular items returns the total quantity of the item in the cart. For bundle items returns the quantity 
		 * of the item in the parent bundle. For example, if there was a computer bundle product in the cart and its
		 * quantity was 2 and it had a bundle item CPU with quantity 2, the actual quantity
		 * for CPU would be 4, while the visible quantity, returned by this method, would be 2.
		 * @documentable
		 * @return int the item quantity.
		 */
		public function get_quantity()
		{
            return $this->get_bundle_item_quantity();
		}

        /**
         * Returns the quantity of stock available for this item.
         * If stock level is not tracked , returns true.
         * If no stock level can be determined, returns false.
         * @return int|bool The quantity available or the status of availability
         */
        public function getStockAvailable(){
            $inventoryTrackingEnabled = $this->product && $this->product->inventory_tracking_enabled();
            if(!$inventoryTrackingEnabled){
                return true; //stock is available because inventory tracking is disabled
            }
            $in_stock = $this->om('in_stock');
            if(is_numeric($in_stock)){
                return max(0, $in_stock); //actual quantity available
            }
            return false; // stock level could not be determined
        }

		/**
		 * Sale reductions can be applied through catalog price rules
		 * This method returns the amount discounted from the list price by a catalog price rule
		 */
		public function get_sale_reduction(){
			$effective_quantity = $this->get_effective_quantity();

			if (!$this->price_is_overridden($effective_quantity))
			{
				$om_record = $this->get_om_record();
				if (!$om_record)
					return $this->product->get_sale_reduction($effective_quantity);
				else
					return $om_record->get_sale_reduction($this->product, $effective_quantity, null, true);
			}

			return 0;
		}


		/**
		 * Returns the volume of one cart item.
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @documentable
		 * @return float Returns the item volume.
		 */
		public function volume($include_free_shipping = true){
			if($this->free_shipping && !$include_free_shipping){
				return 0;
			}
			$volume = $this->om('volume');
            $volume = is_numeric($volume) ? $volume : 0;
			foreach ($this->extra_options as $option)
				$volume += $option->volume();
			return $volume;
		}

		/**
		 * Returns the total volume of the cart item.
		 * The total volume is <em>unit volume * quantity</em>.
		 * @documentable
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @return float Returns the item total volume.
		 */
		public function total_volume($include_free_shipping = true)
		{
			$volume = $this->volume($include_free_shipping);
			if(!$volume){
				return 0;
			}
			return $volume*$this->quantity;
		}

		/**
		 * Returns the weight of one cart item.
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @documentable
		 * @return float Returns the item weight.
		 */
		public function weight($include_free_shipping = true){
			if($this->free_shipping && !$include_free_shipping){
				return 0;
			}
			$weight = $this->om('weight');
            $weight = is_numeric($weight) ? $weight : 0;
			foreach ($this->extra_options as $option)
				$weight += $option->weight;
			return $weight;
		}

		/**
		 * Returns the total weight of the cart item.
		 * The total weight is <em>unit weight * quantity</em>.
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @documentable
		 * @return float Returns the item total weight.
		 */		
		public function total_weight($include_free_shipping = true)
		{
			$weight = $this->weight($include_free_shipping);
			if(!$weight){
				return 0;
			}
			return $weight*$this->quantity;
		}


		/**
		 * Returns the depth of one cart item.
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @documentable
		 * @return float Returns the item depth.
		 */
		public function depth($include_free_shipping = true){
			if($this->free_shipping && !$include_free_shipping){
				return 0;
			}
			$depth = $this->om('depth');
            $depth = is_numeric($depth) ? $depth : 0;
			foreach ($this->extra_options as $option)
				$depth += $option->depth;
			return $depth;
		}
		/**
		 * Returns the total depth of the cart item.
		 * The total depth is <em>unit depth * quantity</em>.
		 * @documentable
	     * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @return float Returns the item total depth.
		 */
		public function total_depth($include_free_shipping = true)
		{
			$depth = $this->depth($include_free_shipping);
			if(!$depth){
				return 0;
			}
			return $depth*$this->quantity;
		}


		/**
		 * Returns the width of one cart item.
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @documentable
		 * @return float Returns the item width.
		 */
		public function width($include_free_shipping = true){
			if($this->free_shipping && !$include_free_shipping){
				return 0;
			}
			$width = $this->om('width');
            $width = is_numeric($width) ? $width : 0;
			foreach ($this->extra_options as $option)
				$width += $option->width;
			return $width;
		}

		/**
		 * Returns the total width of the cart item.
		 * The total width is <em>unit width * quantity</em>.
		 * @documentable
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @return float Returns the item total width.
		 */
		public function total_width($include_free_shipping = true)
		{
			$width = $this->width($include_free_shipping);
			if(!$width){
				return 0;
			}
			return $width*$this->quantity;
		}

		/**
		 * Returns the height of one cart item.
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @documentable
		 * @return float Returns the item height.
		 */
		public function height($include_free_shipping = true){
			if($this->free_shipping && !$include_free_shipping){
				return 0;
			}
			$height = $this->om('height');
            $height = is_numeric($height) ? $height : 0;
			foreach ($this->extra_options as $option)
				$height += $option->height;
			return $height;
		}


		/**
		 * Returns the total height of the cart item.
		 * The total height is <em>unit height * quantity</em>.
		 * @documentable
		 * @param boolean $include_free_shipping Set to false if free shipping items should be excluded from calculations
		 * @return float Returns the item total height.
		 */
		public function total_height($include_free_shipping = true)
		{
			$height = $this->height($include_free_shipping);
			if(!$height){
				return 0;
			}
			return $height*$this->quantity;
		}


		/**
		 * Returns TRUE of the item price has been overridden by a custom module
		 */
		public function price_is_overridden($effective_quantity)
		{
			if ($this->price_preset === false)
			{
				$external_price = Backend::$events->fireEvent('shop:onGetCartItemPrice', $this);
				foreach ($external_price as $price)
				{
					if (strlen($price))
						return true;
				}

				$effective_quantity = $effective_quantity ? $effective_quantity : $this->get_effective_quantity();
				$result = $this->product->price_no_tax($effective_quantity);

				$updated_price = Backend::$events->fireEvent('shop:onUpdateCartItemPrice', $this, $result);
				foreach ($updated_price as $price)
				{
					if (strlen($price))
						return true;
				}
			}

			return false;
		}

		/**
		 * Evaluates total price of the item
		 */
		public function total_price_no_tax($apply_cart_level_discount = true, $quantity = null)
		{
			$cart_level_discount = $apply_cart_level_discount ? $this->applied_discount : 0;
			$catalog_level_discount = ($this->price_preset === false) ? $this->get_sale_reduction()  : 0;

			$quantity = $quantity === null ? $this->quantity : $quantity;

			$total_price_no_tax = ($this->single_price_no_tax(true, $this->get_effective_quantity()) - $catalog_level_discount - $cart_level_discount)*$quantity;

			return  number_format($total_price_no_tax,2, '.', '');
		}
		
		/**
		 * Returns total price of the item.
		 * The method takes into account the item quantity, extra options and discounts value.
		 * Adds tax to the result if the 
		 * {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} 
		 * option is enabled or <em>$force_tax</em> parameter is TRUE.
		 * @documentable
		 * @param boolean $apply_catalog_price_rules Determines whether catalog price rules should be applied to the result, true by default.
		 * @param boolean $force_tax Determines whether taxes should be added to the result, overriding {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} option status.
		 * @param integer $quantity Specifies the item quantity. If this parameter is omitted, the internal value is used.
		 * @return float Returns the item total price.
		 */
		public function total_price($apply_cart_level_discount = true, $force_tax = false, $quantity = null)
		{
			$price = $this->total_price_no_tax($apply_cart_level_discount, $quantity);

			if (!$force_tax)
			{
				$include_tax = Shop_CheckoutData::display_prices_incl_tax();
				if (!$include_tax)
					return $price;
			}
			
			return Shop_TaxClass::get_total_tax($this->get_tax_class_id(), $price) + $price;
		}

		
		/**
		 * Returns the total value of a tax applied to the item.
		 * @return float
		 */
		public function total_tax()
		{
			$price = $this->total_price_no_tax(true);
			return Shop_TaxClass::get_total_tax($this->get_tax_class_id(), $price);
		}
		
		/**
		 * Returns a list of taxes applied to the cart item. 
		 * The method returns an array, containing objects with the following fields: <em>name</em>, <em>rate</em>. 
		 * You can use this method to output a list of applied taxes in the cart item table. Example: 
		 * <pre>
		 * <? foreach ($item->get_tax_rates() as $tax_info): ?>
		 *   <?= h($tax_info->name) ?>
		 * <? endforeach ?>
		 * </pre>
		 * @documentable
		 * @return array Returns an array of objects with <em>name</em> and <em>rate</em> fields.
		 */
		public function get_tax_rates()
		{
			return Shop_TaxClass::get_tax_rates_static($this->get_tax_class_id(), Shop_CheckoutData::get_shipping_info());
		}

		
		public function total_discount_no_tax()
		{
			return $this->applied_discount;
		}

		/**
		 * Returns the cart item discount.
		 * @documentable
		 * @return float Returns the item discount.
		 */
		public function total_discount()
		{
			$product_discount = 0;
			
			$applied_discount = $this->applied_discount;
			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if ($include_tax)
				$applied_discount = Shop_TaxClass::get_total_tax($this->get_tax_class_id(), $applied_discount) + $applied_discount;
			
			return $product_discount + $applied_discount;
		}
		

		/**
		 * Extracts a custom data field value by the field name. 
		 * Use this field for displaying custom data fields previously assigned to the cart items. 
		 * Controls for custom data fields should have names in the following format: item_data[item_key][field_name]. 
		 * You can read about creating custom per-item input fields on the Cart page in 
		 * {@link http://lemonstand.com/docs/allowing_customers_to_provide_order_item_specific_information/ this article}. 
		 * Example: 
		 * <pre>
		 * <textarea 
		 *   name="item_data[<?= $item->key ?>][x_engraving_text]"><?= h($item->get_data_field('x_engraving_text')) ?></textarea>
		 * </pre>
		 * @documentable
		 * @see http://lemonstand.com/docs/allowing_customers_to_provide_order_item_specific_information/ Allowing customers to provide order item specific information
		 * @param string $field_name Specifies the field name. Field names should start with <em>x_</em> prefix.
		 * @param mixed $default Specifies a default field value to return if the field is not found.
		 * @return Returns the custom data field value or the default value.
		 */
		public function get_data_field($field_name, $default_value = null)
		{
			if (!$this->native_cart_item)
				return $default_value;
			
			return $this->native_cart_item->get_data_field($field_name, $default_value);
		}

		/**
		 * Returns all custom data field values assigned with the cart item.
		 * @documentable
		 * @see http://lemonstand.com/docs/allowing_customers_to_provide_order_item_specific_information/ Allowing customers to provide order item specific information
		 * @return array Returns an associative array of custom field names and values.
		 */
		public function get_data_fields()
		{
			if (!$this->native_cart_item)
				return array();
			
			return $this->native_cart_item->get_data_fields();
		}

		/**
		 * Returns a list of files uploaded by the customer on the {@link http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ Product Details page}. 
		 * Use this method to display files assigned with items on the {@link http://lemonstand.com/docs/cart_page Cart page}. 
		 * The method returns an array of arrays with the following keys: <em>name</em>, <em>size</em>, <em>path</em>.
		 * @documentable
		 * @see http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ Supporting file uploads on the product page
		 * @return array Returns an array of objects with <em>name</em>, <em>size</em> and <em>path</em> fields.
		 */
		public function list_uploaded_files()
		{
			if (!$this->native_cart_item)
				return array();
			
			return $this->native_cart_item->list_uploaded_files();
		}
		
		public function copy_files_to_order_item($order_item)
		{
			$files = $this->list_uploaded_files();
			foreach ($files as $file_info)
			{
				$file = new Db_File();
				$file->fromFile(PATH_APP.$file_info['path']);
				$file->name = $file_info['name'];
				$file->is_public = false;
				$file->master_object_class = get_class($order_item);
				$file->field = 'uploaded_files';
				$file->save();

				$order_item->uploaded_files->add($file);
			}
		}
		
		/**
		 * Returns the item options and extra options description.
		 * This method simplifies front-end coding.
		 * @documentable
		 * @param string $options_delimiter Specifies a delimiter string for options. 
		 * @param boolean $lowercase_options Convert options values to lower case.
		 * @param boolean $as_html Determines whether the result string should be converted to HTML.
		 * @return Returns HTML string describing the item options and extra options.
		 */
		public function item_description($options_delimiter = '; ', $lowercase_options = true, $as_html = true)
		{
			$result = $this->options_str($options_delimiter, $lowercase_options);
			if ($as_html)
				$result = h($result);
			
			foreach ($this->extra_options as $extra_option)
			{
				$group = $extra_option->group_name;
				
				if ($group)
				{
					if ($as_html)
						$group = '<strong>'.h($group).'</strong> - ';
					else
						$group = $group.' - ';
				}
				
				$result .= $as_html ? "<br>" : "\n";
				$result .= '+ ';
				$result .= $as_html ? $group.h($extra_option->description) : $group.$extra_option->description;
				$result .= ': ';
				$result .= format_currency($extra_option->get_price($this->product));
			}

			return $result;
		}
		

		/*
		 * Discount Engine caching
		 */
		
		public function get_de_cache_item($name)
		{
			if (array_key_exists($name, $this->de_cache))
				return $this->de_cache[$name];
				
			return false;
		}

		public function set_de_cache_item($name, $value)
		{
			return $this->de_cache[$name] = $value;
		}
		
		public function reset_de_cache()
		{
			$this->de_cache = array();
		}
		
		/*
		 * Option Matrix functions
		 */
		
		/**
		 * Returns Option Matrix record basing on selected product options.
		 * @return Shop_OptionMatrixRecord Returns the Option Matrix record object or null.
		 */
		public function get_om_record()
		{
			if (!$this->options)
				return null;
			
			return Shop_OptionMatrixRecord::find_record($this->options, $this->product);
		}
		
		/**
		 * Returns {@link Shop_OptionMatrixRecord Option Matrix} product property. 
		 * If Option Matrix product is not associated with the cart item, returns property value of 
		 * the base product. Specify the property name in the first parameter. Use the method on the Cart page to output 
		 * Option Matrix specific parameters, for example product images. The following example outputs a product 
		 * image in the cart item list.
		 * <pre>
		 * <?
		 *   $images = $item->om('images');
		 *   $image_url = $images->count ? $images->first->getThumbnailPath(60, 'auto') : null;
		 * ?>
		 * 
		 * <? if ($image_url): ?>
		 *   <img class="product_image" src="<?= $image_url ?>" alt="<?= h($item->product->name) ?>"/>
		 * <? endif ?>
		 * </pre>
		 * @documentable
		 * @see http://lemonstand.com/docs/integrating_option_matrix Integrating Option Matrix
		 * @see http://lemonstand.com/docs/understanding_option_matrix Understanding Option Matrix
		 * @param string $property_name Specifies the property name.
		 * @return mixed Returns the property value or NULL.
		 */
		public function om($property_name)
		{
			return Shop_OptionMatrix::get_property($this->options, $property_name, $this->product);
		}


		//
		// Shop_RetailItem Interface methods
		//

		public function get_list_price() {
            return $this->single_price_no_tax();
		}

		public function get_offer_price() {
			return ($this->get_list_price() - $this->get_sale_reduction()) - $this->total_discount_no_tax();
		}

		public function get_total_list_price( $quantity = null) {
			$quantity = $quantity ? $quantity : $this->quantity;
			$price = $this->get_list_price();
			if($quantity){
				$price = $price * $quantity;
			}
			return  number_format($price,2, '.', '');
		}

		public function get_total_offer_price( $quantity = null) {
			$quantity = $quantity ? $quantity : $this->quantity;
			$price = $this->get_offer_price();
			if($quantity){
				$price = $price * $quantity;
			}
			return  number_format($price,2, '.', '');
		}


        public function get_tax_class_id(){
            if($this->product){
                return $this->product->tax_class_id;
            }
            return null;
        }



        /**
         * Returns a {@link Shop_CartItem cart item object} representing a master bundle product for this item.
         * The result value could be NULL in case if the item is not a bundle item or if the master cart item cannot be found.
         * @documentable
         * @return Shop_CartItem Returns the cart item object or NULL.
         */
        public function get_master_bundle_item()
        {
            if (!$this->native_cart_item)
                return null;

            $key = $this->native_cart_item->bundle_master_cart_key;
            if (!$key)
                return null;

            return Shop_Cart::find_item($key, $this->cart_name);
        }


        /**
         * Shop_BundleItem Interface Methods
         * See Interface for doc comments
         */
        public function is_bundle_item()
        {
            if ($this->order_item && $this->order_item->bundle_master_order_item_id)
                return true;

            $bundle_item = $this->get_bundle_offer_item();
            return $bundle_item ? true : false;
        }

        public function has_bundle_items()
        {
            return $this->get_bundle_items() ? true : false;
        }

        public function get_bundle_items()
        {
            $items = Shop_Cart::list_items($this->cart_name);
            $bundle_items = array();
            foreach ($items as $item)
            {
                if ($item->key == $this->key)
                    continue;

                if (!$item->native_cart_item)
                    continue;

                if ($item->native_cart_item->bundle_master_cart_key == $this->key)
                    $bundle_items[] = $item;
            }

            return $bundle_items;
        }

        public function get_bundle_discount(){
            if (!$this->native_cart_item)
                return $this->total_discount();

            $bundle_items = $this->get_bundle_items();

            $result = $this->total_discount();
            foreach ($bundle_items as $item)
                $result += $item->total_discount()*$item->get_bundle_item_quantity();

            return $result;
        }

        public function get_bundle_list_price(){
            $price = $this->get_list_price();
            if ($this->native_cart_item){
                $bundle_cart_items = $this->get_bundle_items();
                foreach ($bundle_cart_items as $cart_item) {
                    $bundle_offer_item = $cart_item->get_bundle_offer_item();
                    $offer_price = $bundle_offer_item->get_list_price();
                    $price += ($offer_price * $cart_item->get_bundle_item_quantity());
                }
            }
            return $price;
        }

        public function get_bundle_single_price()
        {
            if (!$this->native_cart_item)
                return $this->single_price();

            $bundle_items = $this->get_bundle_items();

            $result = $this->single_price();
            foreach ($bundle_items as $item)
                $result += $item->single_price()*$item->get_bundle_item_quantity();

            return $result;
        }

        public function get_bundle_offer_price(){
            $price = $this->get_offer_price();
            if ($this->native_cart_item){
                $bundle_cart_items = $this->get_bundle_items();
                foreach ($bundle_cart_items as $cart_item) {
                    $bundle_offer_item = $cart_item->get_bundle_offer_item();
                    $offer_price = $bundle_offer_item->get_offer_price();
                    $price += ($offer_price * $cart_item->get_bundle_item_quantity());
                }
            }
            return $price;
        }

        public function get_bundle_total_list_price($quantity = null) {
            $quantity = $quantity ? $quantity : $this->quantity;
            $price = $this->get_bundle_list_price();
            if($quantity){
                $price = $price * $quantity;
            }
            return  number_format($price,2, '.', '');
        }

        public function get_bundle_total_price($quantity = null) {
            $quantity = $quantity ? $quantity : $this->quantity;
            $price = $this->get_bundle_single_price();
            if($quantity){
                $price = $price * $quantity;
            }
            return  number_format($price,2, '.', '');
        }

        public function get_bundle_total_offer_price($quantity = null) {
            $quantity = $quantity ? $quantity : $this->quantity;
            $price = $this->get_bundle_offer_price();
            if($quantity){
                $price = $price * $quantity;
            }
            return  number_format($price,2, '.', '');
        }

        public function get_bundle_offer(){
            if (!$this->native_cart_item)
                return null;

            return $this->native_cart_item->get_bundle_offer();
        }

        public function get_bundle_offer_item(){
            if (!$this->native_cart_item)
                return null;

            $item = $this->native_cart_item->get_bundle_offer_item();
            $currency_context =  Shop_CheckoutData::get_currency(false);
            if($item && $currency_context){
                $item->set_currency_context($currency_context);
            }
            return $item;
        }

        /**
         * Returns quantity of the bundle item product in each bundle.
         * If the item does not represent a bundle item, returns the $quantity property value.
         * @documentable
         * @return integer Returns quantity of the bundle item product in each bundle.
         */
        public function get_bundle_item_quantity()
        {
            $master_bundle_item = $this->get_master_bundle_item();
            if (!$master_bundle_item)
                return $this->quantity;

            $total_quantity_bundled = $this->quantity;
            $total_bundles = $master_bundle_item->quantity;
            if(!$total_bundles || $total_bundles == 1){
                return $total_quantity_bundled;
            }

            $quantity_per_bundle = max(1, $total_quantity_bundled/$total_bundles );
            return round($quantity_per_bundle);
        }

        /**
         * Returns amount the item was discounted by the bundle.
         * If the item does not represent a bundle item, returns null.
         * @documentable
         * @return float Returns the amount discounted
         */
        public function get_bundle_item_discount(){
            if(!$this->is_bundle_item()){
                return null;
            }
            $bundle_offer_item = $this->get_bundle_offer_item();
            if($bundle_offer_item) {
                $list_price = $bundle_offer_item->get_list_price();
                $price = $this->single_price();
                if ($price < $list_price) {
                    return $list_price - $price;
                }
            }
            return 0;
        }

        /**
         * Events
         */

		/**
		 * Allows to override a price of an item in the shopping cart. 
		 * The overridden price will be correctly processed by the discount and tax engines.
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onGetCartItemPrice', $this, 'get_cart_price');
		 * }
		 *  
		 * public function get_cart_price($cart_item)
		 * {
		 *   if ($cart_item->product->sku == '26268880')
		 *     return 10;
		 * }
		 * </pre>
		 * @event shop:onGetCartItemPrice
		 * @see shop:onUpdateCartItemPrice
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_CartItem $item Specifies the cart item object.
		 * @return float Returns the updated cart item price.
		 */
		private function event_onGetCartItemPrice($item) {}
			
		/**
		 * Allows to update a default shopping cart item price, or price returned by the {@link shop:onGetCartItemPrice} event. 
		 * This event is triggered after the {@link shop:onGetCartItemPrice event}. 
		 * The updated price will be correctly processed by the discount and tax engines.
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onUpdateCartItemPrice', $this, 'update_cart_price');
		 * }
		 *  
		 * public function update_cart_price($cart_item, $price)
		 * {
		 *   if ($price > 1000)
		 *     return 999;
		 * }
		 * </pre>
		 * @event shop:onUpdateCartItemPrice
		 * @see shop:onGetCartItemPrice
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_CartItem $item Specifies the cart item object.
		 * @param float $price The cart item price.
		 * @return float Returns the updated cart item price.
		 */
		private function event_onUpdateCartItemPrice($item, $price) {}


        /**
         * Deprecated methods
         */

		/**
		 * @deprecated
		 * Use: get_sale_reduction() or total_discount_no_tax()
		 */
		public function discount($total_discount = true)
		{
			if ($total_discount)
				return $this->total_discount_no_tax();

			return $this->get_sale_reduction();
		}

        /**
         * @deprecated
         * @see Shop_CartItem::get_bundle_offer_item()
         */
        public function get_bundle_item()
        {
            return $this->get_bundle_offer();
        }

        /**
         * @deprecated
         * @see get_bundle_discount()
         */
        public function bundle_total_discount()
        {
            $this->get_bundle_discount();
        }

        /**
         * @deprecated
         */
        public function bundle_total_tax()
        {
            if (!$this->native_cart_item)
                return $this->total_tax();

            $bundle_items = $this->get_bundle_items();
            $result = $this->total_tax();
            foreach ($bundle_items as $item)
                $result += $item->total_tax();

            return $result;
        }

        /**
         * @deprecated
         * see get_bundle_list_price() , get_bundle_offer_price()
         */
        public function bundle_item_total_price()
        {
            if (!$this->is_bundle_item())
                return $this->total_price();

            return $this->total_price(true, false, $this->get_bundle_item_quantity());
        }

        /**
         * @deprecated
         * see get_bundle_single_price(),  get_bundle_list_price() , get_bundle_offer_price()
         */
        public function bundle_total_price()
        {
            if (!$this->native_cart_item)
                return $this->total_price();

            $bundle_items = $this->get_bundle_items();

            $result = $this->total_price();
            foreach ($bundle_items as $item)
                $result += $item->total_price();

            return $result;
        }

        /**
         * @deprecated
         * see get_bundle_single_price(),  get_bundle_list_price() , get_bundle_offer_price()
         */
        public function bundle_single_price()
        {
            return $this->get_bundle_single_price();
        }

        public function getCurrencyCode()
        {
            if($this->currencyCode){
                return $this->currencyCode;
            }

            //return active checkout currency
            return Shop_CheckoutData::get_currency(false);
        }

        public function setCurrencyCode($code){
            if(is_string($code) && strlen($code) === 3){
                $this->currencyCode = $code;
            }
        }
    }