<?php

	/**
	 * Provides methods which help in developing front-end pages and partials for displaying and managing product bundle items. 
	 * @documentable
	 * @see http://lemonstand.com/docs/managing_bundle_products/ Managing bundle products
	 * @see http://lemonstand.com/docs/displaying_product_bundle_items/ Displaying product bundle items
	 * @see Shop_BundleItemProduct
	 * @see Shop_ProductBundleItem
	 * @author LemonStand eCommerce Inc.
	 * @package shop.helpers
	 */
	class Shop_BundleHelper
	{
		protected static $normalized_bundle_product_data = null;
		
		/**
		 * Returns TRUE if a specified bundle item product is selected.
		 * Use this method to determine whether a bundle item product drop-down option, a radio button or a checkbox is selected.
		 * Pass a {@link Shop_ProductBundleItem bundle item object} to the first parameter and {@link Shop_BundleItemProduct bundle item product object} 
		 * to the second parameter. Example: 
		 * <pre>
		 * <select ...>
		 *   <? foreach ($item->item_products as $item_product): ?>
		 *     <option 
		 *       ...
		 *       <?= option_state(Shop_BundleHelper::is_item_product_selected($item, $item_product), true) ?>>
		 *         <?= h($item_product->product->name) ?>
		 *     </option>
		 *   <? endforeach ?>
		 * </select>
		 * </pre>
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object.
		 * @return boolean Returns TRUE if a specified bundle item product is selected. Returns FALSE otherwise.
		 */
		public static function is_item_product_selected($bundle_item, $bundle_item_product)
		{
			$result = self::is_item_product_selected_internal($bundle_item, $bundle_item_product);

			if ($result === null)
				return false;

			if ($result)
				return true;

			/*
			 * Return TRUE if the item is default
			 */
			
			foreach ($bundle_item->item_products_all as $item_product)
			{
				if ($item_product->is_default && $item_product->id == $bundle_item_product->id)
					return true;
			}

			/*
			 * Return FALSE if there is a default product for this bundle item but this product is not default
			 */
			
			foreach ($bundle_item->item_products_all as $item_product)
			{
				if ($item_product->is_default)
					return false;
			}
			
			/*
			 * Return TRUE if this item is the first in the list for drop-down and radio button controls
			 */

			if (($bundle_item->control_type == Shop_ProductBundleItem::control_dropdown ||  
				$bundle_item->control_type == Shop_ProductBundleItem::control_radio) && $bundle_item->is_required)
			{
				foreach ($bundle_item->item_products as $index=>$item_product)
				{
					if ($item_product->id == $bundle_item_product->id && $index == 0)
						return true;
				}
			}

			return false;
		}
		
		/**
		 * Returns Quantity field value for a specified bundle item product.
		 * Use this method to output value for the Quantity field <em>value</em> attribute. Pass a {@link Shop_ProductBundleItem bundle item object} 
		 * to the first parameter and {@link Shop_BundleItemProduct bundle item product object} to the second parameter. Example: 
		 * <pre>
		 * <input
		 *   class="text"
		 *   type="text" 
		 *   name="<?= Shop_BundleHelper::get_product_control_name($item, $item_product, 'quantity') ?>" 
		 *   value="<?= Shop_BundleHelper::get_product_quantity($item, $item_product) ?>"/>
		 * </pre>
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object, optional.
		 * @param integer $product_id Specifies Product identifier, optional.
		 * @return integer Returns Quantity field value.
		 */
		public static function get_product_quantity($bundle_item, $bundle_item_product = null, $product_id = null)
		{
			$result = self::find_bundle_data_element('quantity', $bundle_item, $bundle_item_product, $product_id);

			if (strlen($result))
				return $result;

			if ($bundle_item_product)
				return $bundle_item_product->default_quantity;
				
			foreach ($bundle_item->item_products_all as $bundle_item_product)
			{
				if ($bundle_item_product->is_default)
					return $bundle_item_product->default_quantity;
			}

			return null;
		}
		
		/**
		 * Returns TRUE if a specified bundle item product option is selected.
		 * Use this method in the bundle product options partial to determine whether a specific product option is selected. 
		 * Example: 
		 * <pre>
		 * <select ...>
		 *   <?
		 *     $values = $option->list_values();
		 *     foreach ($values as $value):      
		 *       $is_selected = Shop_BundleHelper::is_product_option_selected($option, $value, $item, $item_product);
		 *   ?>
		 *     <option <?= option_state($is_selected, true) ?> value="<?= h($value) ?>"><?= h($value) ?></option>
		 *   <? endforeach ?>
		 * </select>
		 * </pre>
		 * @documentable
		 * @param Shop_CustomAttribute $option Specifies the option object being checked.
		 * @param string $value Specifies the option value to check.
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object, optional.
		 * @return boolean Returns TRUE if a specified bundle item product option is selected. Returns FALSE otherwise.
		 */
		public static function is_product_option_selected($option, $value, $bundle_item, $bundle_item_product)
		{
			$result = self::find_bundle_data_element('options', $bundle_item, $bundle_item_product, null);

			if ($result === false || !is_array($result))
				return false;

			if (!array_key_exists($option->option_key, $result))
				return false;

			return (string)trim($result[$option->option_key]) == (string)trim($value);
		}
		
		/**
		 * Returns an array of selected bundle item product options.
		 * The method result is suitable for passing to the {@link Shop_Product::om()} and {@link Shop_BundleItemProduct::get_price()} methods.
		 * <pre>
		 * // Load bundle item product images
		 * $selected_options = Shop_BundleHelper::get_selected_options($item, $item_product);
		 * $images = $product->om('images', $selected_options);
		 * 
		 * // Load bundle item product price
		 * $selected_options = Shop_BundleHelper::get_selected_options($item, $item_product);
		 * Price: <?= format_currency($item_product->get_price($product, $selected_options)) ?>
		 * </pre>
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object, optional.
		 * @return array Returns a list of selected options in the following format: [option_key=>option value].
		 */
		public static function get_selected_options($bundle_item, $bundle_item_product)
		{
			$options = self::find_bundle_data_element('options', $bundle_item, $bundle_item_product, null);

			if (!$options)
				$options = array();

			if (!$bundle_item_product || !$bundle_item_product->product)
				return $options;

			return $bundle_item_product->product->normalize_posted_options($options);
		}
		
		/**
		 * Returns TRUE if a specified bundle item product extra option is selected.
		 * Use this method in the bundle product extra options partial to determine whether a specific product extra option is selected.
		 * Example: 
		 * <pre>
		 * <? foreach ($product->extra_options as $option):
		 *   $is_checked = Shop_BundleHelper::is_product_extra_option_selected($option, $item, $item_product);
		 * ?>
		 *   <input 
		 *     ...
		 *     <?= checkbox_state(Shop_BundleHelper::is_product_extra_option_selected($option, $item, $item_product)) ?> 
		 *     value="1" 
		 *     type="checkbox"/>
		 * <? endforeach ?>
		 * </pre>
		 * @documentable
		 * @param Shop_ExtraOption $option Specifies the exrta option object being checked.
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object, optional.
		 * @return boolean Returns TRUE if a specified bundle item product extra option is selected. Returns FALSE otherwise.
		 */
		public static function is_product_extra_option_selected($option, $bundle_item, $bundle_item_product = null)
		{
			$result = self::find_bundle_data_element('extra_options', $bundle_item, $bundle_item_product, null);
			
			if ($result === false || !is_array($result))
				return false;

			if (!array_key_exists($option->option_key, $result))
				return false;

			return true;
		}
		
		/**
		 * Returns a bundle item product selected by visitor.
		 * If the visitor has not selected any product yet, the method would return a default product for this
		 * bundle item (if any), or a first product in the list for drop-down and radio button type bundle items. 
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object, optional.
		 * @return Shop_Product Returns the selected product object.
		 */
		public static function get_bundle_item_product($bundle_item, $bundle_item_product = null)
		{
			if ($bundle_item->control_type == Shop_ProductBundleItem::control_dropdown)
				return self::get_dropdown_bundle_item_product($bundle_item, $bundle_item_product);
			
			$product_id = self::find_bundle_data_element('grouped_product_id', $bundle_item, $bundle_item_product, null);
			
			if (!$bundle_item_product)
			{
				foreach ($bundle_item->item_products as $item_product)
				{
					if (self::is_item_product_selected($bundle_item, $item_product))
					{
						$bundle_item_product = $item_product;
						break;
					}
				}
			}

			if ($product_id === false)
			{
				if (!$bundle_item_product)
					return null;
				
				$product = $bundle_item_product->product;
				if (!$product->grouped_products->count)
					return $product;
				
				return $product->grouped_products[0];
			}
			
			return Shop_Product::create()->find_by_id($product_id);
		}
		
		/**
		 * Returns a bundle item product object corresponding a product selected by visitor.
		 * This method is required only for drop-down type bundle items.
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @return Shop_BundleItemProduct Returns the bundle item product object or NULL.
		 */
		public static function get_bundle_item_product_item($bundle_item)
		{
			foreach ($bundle_item->item_products as $item_product)
			{
				if (self::is_item_product_selected($bundle_item, $item_product))
					return $item_product;
			}

			return null;
		}

		/**
		 * Returns a name for a bundle item product selector input element (drop-down menu, checkbox or radio button).
		 * Input element names are different for different bundle item control types and this method simplifies generating the element names.
		 * <pre>
		 * <? if ($item->control_type == 'dropdown'): ?>
		 *   <select 
		 *     ...
		 *     name="<?= Shop_BundleHelper::get_product_selector_name($item, $selected_item_product) ?>"
		 *   ...
		 * <? elseif ($item->control_type == 'checkbox'): ?>
		 *   <? foreach ($item->item_products as $item_product): ?>
		 *     <input 
		 *       type="checkbox" 
		 *       name="<?= Shop_BundleHelper::get_product_selector_name($item, $item_product) ?>" 
		 *       value="<?= Shop_BundleHelper::get_product_selector_value($item_product) ?>"
		 *       ...
		 *     />
		 *   ...
		 * <? else: ?> 
		 *   <? foreach ($item->item_products as $item_product): ?>
		 *     <input 
		 *       type="radio" 
		 *       name="<?= Shop_BundleHelper::get_product_selector_name($item, $selected_product) ?>" 
		 *       value="<?= Shop_BundleHelper::get_product_selector_value($item_product) ?>"
		 *       ...
		 *     />
		 *   ...
		 * <? endif ?>
		 * </pre>
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object, optional.
		 * @return string Returns the input element name.
		 */
		public static function get_product_selector_name($bundle_item, $bundle_item_product)
		{
			switch ($bundle_item->control_type)
			{
				case Shop_ProductBundleItem::control_dropdown : return 'bundle_data['.$bundle_item->id.'][product_id]';
				case Shop_ProductBundleItem::control_checkbox : return 'bundle_data['.$bundle_item->id.']['.$bundle_item_product->product_id.'][product_id]';
				default : return 'bundle_data['.$bundle_item->id.'][product_id]';
			}
		}
		
		/**
		 * Returns a value for a bundle item product selector input element (drop-down menu option, checkbox or radio button).
		 * <pre>
		 * <select ...>
		 *   <? foreach ($item->item_products as $item_product): ?>
		 *     <option 
		 *       value="<?= Shop_BundleHelper::get_product_selector_value($item_product) ?>" 
		 *       ...
		 *     >
		 *       <?= h($item_product->product->name) ?>
		 *     </option>
		 *   <? endforeach ?>
		 * </select>
		 * </pre>
		 * @documentable
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object.
		 * @return string Returns the input element value.
		 */
		public static function get_product_selector_value($bundle_item_product)
		{
			return $bundle_item_product->id.'|'.$bundle_item_product->product_id;
		}
		
		/**
		 * Returns a name for bundle item product configuration control (options, extra options or grouped product selector).
		 * This is an universal method which can be used for generating names for any supported bundle item product controls. 
		 * The <em>$control_name</em> parameter is a string representing the control name. Possible values for this parameter are: 
		 * <em>quantity</em>, <em>options</em>, <em>extra_options</em> or <em>grouped_product</em>. Example: 
		 * <pre>
		 * <input
		 *   class="text"
		 *   type="text" 
		 *   name="<?= Shop_BundleHelper::get_product_control_name($item, $item_product, 'quantity') ?>" 
		 *   value="<?= Shop_BundleHelper::get_product_quantity($item, $item_product) ?>"/>
		 * </pre>
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object.
		 * @param string $control_name Specifies the control name
		 * @return string Returns the the product configuration control name.
		 */
		public static function get_product_control_name($bundle_item, $bundle_item_product, $control_name)
		{
			if (!in_array($control_name, array('quantity', 'options', 'extra_options', 'grouped_product')) )
				throw new Phpr_ApplicationException('Invalid control name passed to Shop_BundleHelper::get_product_control_name(). Valid values are options, extra_options, grouped_product.');
				
			if ($control_name == 'grouped_product')
				$control_name = 'grouped_product_id';
			
			switch ($bundle_item->control_type)
			{
				case Shop_ProductBundleItem::control_dropdown : 
					return 'bundle_data['.$bundle_item->id.']['.$control_name.']';
				case Shop_ProductBundleItem::control_checkbox : 
					return 'bundle_data['.$bundle_item->id.']['.$bundle_item_product->product_id.']['.$control_name.']';
				default : 
					return 'bundle_data['.$bundle_item->id.']['.$control_name.']['.$bundle_item_product->product_id.']';
			}
		}
		
		/**
		 * Returns a string containing hidden field declarations for a bundle item.
		 * Hidden fields are required only for drop-down type bundle items.
		 * <pre><?= Shop_BundleHelper::get_item_hidden_fields($item, $selected_item_product) ?></pre>
		 * @documentable
		 * @param Shop_ProductBundleItem $bundle_item Specifies the bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Specifies the bundle item product object.
		 * @return string Returns HTML string containing the hidden field declarations.
		 */
		public static function get_item_hidden_fields($bundle_item, $bundle_item_product)
		{
			if ($bundle_item->control_type != Shop_ProductBundleItem::control_dropdown)
				return null;
				
			$product_id = $bundle_item_product ? $bundle_item_product->id : null;
				
			return '<input type="hidden" name="bundle_data['.$bundle_item->id.'][post_item_product_id]" value="'.$product_id.'"/>';
		}
		
		protected static function find_bundle_data_element($element_name, $bundle_item, $bundle_item_product, $product_id)
		{
			$data = post('bundle_data', array());

			if (!array_key_exists($bundle_item->id, $data))
				return false;

			if (!$product_id)
			{
				if ($bundle_item_product)
					$product_id = $bundle_item_product->product_id;
			}

			$data = $data[$bundle_item->id];
			if (!count($data))
				return false;

			if (array_key_exists($element_name, $data))
			{
				$element_data = $data[$element_name];
				if (!is_array($element_data))
				{
					if (!array_key_exists('post_item_product_id', $data) || !$bundle_item_product)
						return $element_data;

					if ($data['post_item_product_id'] != $bundle_item_product->id)
						return false;

					return $element_data;
				}

				if (!$product_id)
					return false;

				if (array_key_exists($product_id, $element_data))
					return $element_data[$product_id];
				else
				{
					$data_keys = array_keys($element_data);
					if (!count($data_keys))
						return false;
						
					if (is_int($data_keys[0]))
						return false;
					else
						return $element_data;
					
					return false;
				}
			}

			$data_keys = array_keys($data);
			if (!is_int($data_keys[0]))
				return false;

			if (!$product_id || !array_key_exists($product_id, $data))
				return false;
				
			$data = $data[$product_id];
			if (!array_key_exists($element_name, $data))
				return false;
				
			return $data[$element_name];
		}
		
		protected static function get_normalized_bundle_product_data()
		{
			if (self::$normalized_bundle_product_data !== null)
				return self::$normalized_bundle_product_data;
				
			return self::$normalized_bundle_product_data = Shop_Cart::normalize_bundle_data(post('bundle_data', array()));
		}
		
		protected static function is_item_product_selected_internal($bundle_item, $bundle_item_product)
		{
			$data = post('bundle_data', array());
			if (!array_key_exists($bundle_item->id, $data))
				return false;
				
			$data = $data[$bundle_item->id];
			if (array_key_exists('product_id', $data))
			{
				if (!strlen($data['product_id']))
					return null;

				$product_id = $bundle_item_product_id = null;
				self::parse_bundle_product_id($data['product_id'], $product_id, $bundle_item_product_id);
				
				if ($bundle_item_product_id == $bundle_item_product->id)
				{
					return true;
				}
				
				return null;
			}

			if (!count($data))
				return null;
			
			$keys = array_keys($data);
			if (is_int($keys[0]))
			{
				foreach ($data as $product_id => $product_data)
				{
					if (!array_key_exists('product_id', $product_data))
						continue;
						
					$product_id = $bundle_item_product_id = null;
					self::parse_bundle_product_id($product_data['product_id'], $product_id, $bundle_item_product_id);

					if ($bundle_item_product_id == $bundle_item_product->id)
						return true;
				}
			}
			
			if ($bundle_item->control_type == Shop_ProductBundleItem::control_checkbox && $data)
				return null;

			return false;
		}
		
		protected static function parse_bundle_product_id($product_id_data, &$product_id, &$bundle_item_product_id)
		{
			$parts = explode('|', $product_id_data);
			if (count($parts) < 2)
			{
				$product_id = trim($parts[0]);
				$bundle_item_product_id = null;
			} else
			{
				$bundle_item_product_id = trim($parts[0]);
				$product_id = trim($parts[1]);
			}
		}
		
		protected static function get_dropdown_bundle_item_product($bundle_item, $bundle_item_product)
		{
			$master_product_id = self::find_bundle_data_element('product_id', $bundle_item, $bundle_item_product, null);
			$grouped_product_id = self::find_bundle_data_element('grouped_product_id', $bundle_item, $bundle_item_product, null);

			if (!$bundle_item_product)
			{
				foreach ($bundle_item->item_products as $item_product)
				{
					if (self::is_item_product_selected($bundle_item, $item_product))
					{
						$bundle_item_product = $item_product;
						break;
					}
				}
			}

			if ($master_product_id === false)
			{
				if (!$bundle_item_product)
					return null;

				$product = $bundle_item_product->product;
				if (!$product->grouped_products->count)
					return $product;

				return $product->grouped_products[0];
			}

			$master_product_info = explode('|', $master_product_id);
			if (count($master_product_info) != 2)
				return null;

			$master_product = Shop_Product::create()->find_by_id($master_product_info[1]);
			if ($grouped_product_id === false)
				return $master_product;

			if (!$master_product->grouped_products->count)
				return $master_product;

			foreach ($master_product->grouped_products as $grouped_product)
			{
				if ($grouped_product->id == $grouped_product_id)
					return $grouped_product;
			}

			return $master_product;
		}
	}

?>