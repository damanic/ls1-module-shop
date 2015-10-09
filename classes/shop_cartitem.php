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
	class Shop_CartItem
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
					$bundle_item_product = $this->get_bundle_item_product();
					if ($bundle_item_product)
					{
						$options = array();
						foreach ($this->options as $key=>$value)
						{
							if (!preg_match('/^[a-f0-9]{32}$/', $value))
								$options[md5($key)] = $value;
							else
								$options[$key] = $value;
						}

						$result = $bundle_item_product->get_price_no_tax($this->product, $effective_quantity, null, $options); 
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
			$discount = $this->price_preset === false ? $this->discount(false) : 0;
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
			$price = $this->single_price_no_tax($include_extras) - $this->discount(false);

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;
			
			return Shop_TaxClass::get_total_tax($this->product->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns total price (sum if all bundle items) of a single unit of the bundle product cart item. 
		 * If the cart item does not represent a bundle product, the method returns the {@link Shop_CartItem::single_price() single_price()} method result.
		 * @documentable
		 * @return float Returns the bundle item unit price.
		 */
		public function bundle_single_price()
		{
			if (!$this->native_cart_item)
				return $this->single_price();
				
			$bundle_items = $this->get_bundle_items();
			
			$result = $this->single_price();
			foreach ($bundle_items as $item)
				$result += $item->single_price()*$item->get_quantity();
				
			return $result;
		}
		
		/**
		 * Returns cart items which represent bundle items for this cart item. 
		 * @documentable
		 * @return Returns an array of {@link Shop_CartItem} objects.
		 */
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
		 * @return Returns the item quantity.
		 */
		public function get_quantity()
		{
			$master_item = $this->get_master_bundle_item();
			if (!$master_item)
				return $this->quantity;
				
			return round($this->quantity/$master_item->quantity);
		}

		/**
		 * Evaluates the item discount, based on the catalog price rules
		 */
		public function discount($total_discount = true)
		{
			if ($total_discount)
				return $this->total_discount_no_tax();

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
		 * Returns the total volume of the cart item.
		 * The total depth is <em>unit volume * quantity</em>.
		 * @documentable
		 * @return float Returns the item total volume.
		 */
		public function total_volume()
		{
			$result = $this->om('volume')*$this->quantity;
			
			foreach ($this->extra_options as $option)
				$result += $option->volume()*$this->quantity;
			
			return $result;
		}

		/**
		 * Returns the total weight of the cart item.
		 * The total depth is <em>unit weight * quantity</em>.
		 * @documentable
		 * @return float Returns the item total weight.
		 */		
		public function total_weight()
		{
			$result = $this->om('weight')*$this->quantity;

			foreach ($this->extra_options as $option)
				$result += $option->weight*$this->quantity;
			
			return $result;
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
		 * Returns the total depth of the cart item.
		 * The total depth is <em>unit depth * quantity</em>.
		 * @documentable
		 * @return float Returns the item total depth.
		 */
		public function total_depth()
		{
			$result = $this->om('depth')*$this->quantity;
			
			foreach ($this->extra_options as $option)
				$result += $option->depth*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Returns the total width of the cart item.
		 * The total depth is <em>unit depth * quantity</em>.
		 * @documentable
		 * @return float Returns the item total width.
		 */
		public function total_width()
		{
			$result = $this->om('width')*$this->quantity;
			
			foreach ($this->extra_options as $option)
				$result += $option->width*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Returns the total width of the cart item.
		 * The total height is <em>unit height * quantity</em>.
		 * @documentable
		 * @return float Returns the item total height.
		 */
		public function total_height()
		{
			$result = $this->om('height')*$this->quantity;
			
			foreach ($this->extra_options as $option)
				$result += $option->height*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Evaluates total price of the item
		 */
		public function total_price_no_tax($apply_cart_level_discount = true, $quantity = null)
		{
			$cart_level_discount = $apply_cart_level_discount ? $this->applied_discount : 0;
			$catalog_level_discount = ($this->price_preset === false) ? $this->discount(false) : 0;

			$quantity = $quantity === null ? $this->quantity : $quantity;

			return ($this->single_price_no_tax(true, $this->get_effective_quantity()) - $catalog_level_discount - $cart_level_discount)*$quantity;
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
			
			return Shop_TaxClass::get_total_tax($this->product->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns total bundle price. 
		 * The price includes the price of the base bundle products and all its bundle items. 
		 * If the cart item does not represent a bundle product, the method returns the {@link Shop_CartItem::total_price() total_price()} method result.
		 * @documentable
		 * @return float Returns the bundle total price.
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
		 * Returns total price of a bundle item cart item (total price of the bundle item in a single base product).
		 * If the cart item does not represent a bundle item product, the method returns the {@link Shop_CartItem::total_price() total_price()} method result.
		 * @documentable
		 * @return Returns the bundle item total price.
		 */
		public function bundle_item_total_price()
		{
			if (!$this->is_bundle_item())
				return $this->total_price();

			return $this->total_price(true, false, $this->get_quantity());
		}
		
		/**
		 * Returns the total value of a tax applied to the item.
		 * @return float
		 */
		public function total_tax()
		{
			$price = $this->total_price_no_tax(true);
			return Shop_TaxClass::get_total_tax($this->product->tax_class_id, $price);
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
			return Shop_TaxClass::get_tax_rates_static($this->product->tax_class_id, Shop_CheckoutData::get_shipping_info());
		}
		
		/**
		 * Returns the total value of a tax applied to a bundle cart item. If the cart item does not represent a bundle product returns
		 * the total_tax() method call result;
		 * @return float
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
				$applied_discount = Shop_TaxClass::get_total_tax($this->product->tax_class_id, $applied_discount) + $applied_discount;
			
			return $product_discount + $applied_discount;
		}
		
		/**
		 * Returns total discount of a single item of the bundle product cart item. 
		 * If the cart item does not represent a bundle product, the method returns the {@link Shop_CartItem::total_discount() total_discount()} method result.
		 * @documentable
		 * @return float Returns total discount of a single item of the bundle product cart item.
		 */
		public function bundle_total_discount()
		{
			if (!$this->native_cart_item)
				return $this->total_discount();
				
			$bundle_items = $this->get_bundle_items();
			
			$result = $this->total_discount();
			foreach ($bundle_items as $item)
				$result += $item->total_discount()*$item->get_quantity();
				
			return $result;
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
		
		/**
		 * Returns TRUE if the cart item represents a bundle item.
		 * @documentable
		 * @return boolean Returns TRUE if the cart item represents a cart item. Returns FALSE otherwise.
		 */
		public function is_bundle_item()
		{
			if ($this->order_item && $this->order_item->bundle_master_order_item_id)
				return true;
			
			$item = $this->get_bundle_item();
			return $item ? true : false;
		}
		
		/**
		 * Returns a {@link Shop_ProductBundleItem bundle item object} this cart item refers to. 
		 * If this cart item does not represent a bundle item product, returns NULL.
		 * @documentable
		 * @return Shop_ProductBundleItem Returns the bundle item object or NULL.
		 */
		public function get_bundle_item()
		{
			if (!$this->native_cart_item)
				return null;

			return $this->native_cart_item->get_bundle_item();
		}
		
		/**
		 * Returns a {@link Shop_BundleItemProduct bundle item product object} this cart item refers to. 
		 * If this item does not represent a bundle item product, returns NULL.
		 * @documentable
		 * @return Shop_BundleItemProduct Returns bundle item product object or NULL.
		 */
		public function get_bundle_item_product()
		{
			if (!$this->native_cart_item)
				return null;

			return $this->native_cart_item->get_bundle_item_product();
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
	}

?>