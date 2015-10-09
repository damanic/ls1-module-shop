<?php

	/**
	 * Represents a product record in a bundle item.
	 * Usually you don't need to create objects of this class directly, 
	 * because {@link Shop_BundleHelper Bundle Helper} class has methods 
	 * for manipulating with objects of this class.
	 * @documentable
	 * @property integer $id Specifies the bundle item product identifier.
	 * @property integer $product_id Identifier of a product ({@link Shop_Product}) object associated with this bundle item product record.
	 * @property integer $default_quantity Specifies the default product quantity.
	 * @property boolean $allow_manual_quantity Determines whether manual quantity input is allowed for this product.
	 * @property boolean $is_default Determines whether this product is a default product in the bundle item. 
	 * @property Shop_ProductBundleItem $bundle_item A reference to the parent bundle item object.
	 * @property Shop_Product $product A reference to the product associated with this bundle item product record.
	 * @see http://lemonstand.com/docs/managing_bundle_products/ Managing bundle products
	 * @see http://lemonstand.com/docs/displaying_product_bundle_items/ Displaying product bundle items
	 * @see Shop_BundleHelper
	 * @see Shop_ProductBundleItem
	 * @author LemonStand eCommerce Inc.
	 * @package shop.models
	 */
	class Shop_BundleItemProduct extends Db_ActiveRecord
	{
		public $table_name = 'shop_bundle_item_products';
		public $implement = 'Db_Sortable';
		
		const price_override_default = 'default';
		const price_override_fixed = 'fixed';
		const price_override_fixed_discount = 'fixed-discount';
		const price_override_percentage_discount = 'percentage-discount';
		
		protected static $cache = array();

		public static $price_override_options = array(
			'default'=>'Use default price',
			'fixed'=>'Fixed price',
			'fixed-discount'=>'Fixed discount',
			'percentage-discount'=>'Percentage discount'
		);
		
		public $belongs_to = array(
			'bundle_item'=>array('class_name'=>'Shop_ProductBundleItem', 'foreign_key'=>'item_id'),
			'product'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'product_id'),
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			if (!$front_end)
			{
				$this->define_relation_column('product_name', 'product', 'Product ', db_varchar, '@name');
				$this->define_relation_column('product_sku', 'product', 'Product ', db_varchar, '@sku');
				$this->define_column('price_or_discount', 'Price or discount')->validation()->fn('trim')->method('validate_price_or_discount');
				$this->define_column('default_quantity', 'Default quantity')->validation()->fn('trim')->required('Please specify default quantity.');
			}
		}

		public function define_form_fields($context = null)
		{
			
		}
		
		public function get_price_ovirride_mode_name($value)
		{
			if (isset(self::$price_override_options[$value]))
				return self::$price_override_options[$value];
				
			return null;
		}
		
		public function validate_price_or_discount($name, $value)
		{
			if (!strlen($value))
			{
				if ($this->price_override_mode == self::price_override_default)
					return true;

				if ($this->price_override_mode == self::price_override_fixed_discount || $this->price_override_mode == self::price_override_percentage_discount)
					$this->validation->setError('Please specify product discount in the Price or Discount field', $name, true);

				$this->validation->setError('Please specify product price in the Price or Discount field', $name, true);
			}
			
			return true;
		}
		
		/**
		 * Returns the bundle item product price.
		 * Takes into account the price mode settings applied to the product.
		 * Applies the tax if it is required by the configuration. The optional <em>$product</em> parameter represents a currently 
		 * selected product object. If no object provided, the product assigned to the item will be used. If you use 
		 * {@link http://lemonstand.com/docs/understanding_option_matrix/ Option Matrix} 
		 * feature, pass selected bundle item product's options to the second parameter. Use {@link Shop_BundleHelper::get_selected_options()} 
		 * method to load the selected options. Example: 
		 * <pre>
		 * $selected_options = Shop_BundleHelper::get_selected_options($item, $item_product);
		 * Price: <?= format_currency($item_product->get_price($product, $selected_options)) ?>
		 * </pre>
		 * @documentable
		 * @param Shop_Product Currently selected product object. If no object provided, the product assigned to the item will be used.
		 * @param array $product_options Specifies selected product options ({@link http://lemonstand.com/docs/understanding_option_matrix/ Option Matrix} support)
		 * @param boolean $apply_catalog_price_rules Determines whether catalog price rules should be applied to the result.
		 * Pass TRUE to this parameter to get the item sale price.
		 * @return float Returns the bundle item product price.
		 */
		public function get_price($product = null, $product_options = null, $apply_catalog_price_rules = false)
		{
			$product = $product ? $product : $this->product;
			$price = $this->get_price_no_tax($product, 1, null, $product_options, $apply_catalog_price_rules);

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($product->tax_class_id, $price) + $price;
		}

		/**
		 * Returns the bundle item product sale price.
		 * Takes into account the price mode settings applied to the product. Applies the tax if it is required by the configuration.
		 * The optional <em>$product</em> parameter represents a currently selected product object. If no object provided, 
		 * the product assigned to the item will be used. If you use {@link http://lemonstand.com/docs/understanding_option_matrix/ Option Matrix} 
		 * feature, pass selected bundle item product's options to the second parameter. Use {@link Shop_BundleHelper::get_selected_options()} 
		 * method to load the selected options. Example: 
		 * <pre>
		 * $selected_options = Shop_BundleHelper::get_selected_options($item, $item_product);
		 * Sale price: <?= format_currency($item_product->get_sale_price($product, $selected_options)) ?>
		 * </pre>
		 * @documentable
		 * @param Shop_Product Currently selected product object. If no object provided, the product assigned to the item will be used.
		 * @param array $product_options Specifies selected product options ({@link http://lemonstand.com/docs/understanding_option_matrix/ Option Matrix} support)
		 * @return float Returns the bundle item product sale price.
		 */
		public function get_sale_price($product = null, $product_options = null)
		{
			return $this->get_price($product, $product_options, true);
		}

		/**
		 * Returns the bundle item product price without any taxes applied.
		 * The optional <em>$product</em> parameter represents a currently selected product object. 
		 * If no object provided, the product assigned to the item will be used. Usually using of this method
		 * is not necessary in front-end code - use {@link Shop_BundleItemProduct::get_price() get_price()} method instead.
		 * @documentable
		 * @param Shop_Product Currently selected product object. If no object provided, the product assigned to the item will be used.
		 * @param integer $quantity Specifies the product quantity ordered.
		 * @param integer $customer_group_id Specifies an identifier of a {@link Shop_CustomerGroup customer group}.
		 * If no value provided, uses the current front-end customer's group identifier.
		 * @param array $product_options Specifies selected product options ({@link http://lemonstand.com/docs/understanding_option_matrix/ Option Matrix} support)
		 * @param boolean $apply_catalog_price_rules Determines whether catalog price rules should be applied to the result.
		 * Pass TRUE to this parameter to get the item sale price.
		 * @return float Returns the bundle item product price without any taxes applied.
		 */
		public function get_price_no_tax($product = null, $quantity = 1, $customer_group_id = null, $product_options = null, $apply_catalog_price_rules = false)
		{
			$product = $product ? $product : $this->product;

			if (is_array($product_options))
			{
				$om_record = Shop_OptionMatrixRecord::find_record($product_options, $product, true);
				if ($om_record)
					$price = $apply_catalog_price_rules ? $om_record->get_sale_price($product, $quantity, $customer_group_id, true) : $om_record->get_price($product, $quantity, $customer_group_id, true);
				else
					$price = $apply_catalog_price_rules ? $product->get_sale_price_no_tax($quantity, $customer_group_id) : $product->price_no_tax($quantity, $customer_group_id);
			} else
				$price = $apply_catalog_price_rules ? $product->get_sale_price_no_tax($quantity, $customer_group_id) : $product->price_no_tax($quantity, $customer_group_id);

			if ($this->price_override_mode == self::price_override_default)
				return $price;

			if ($this->price_override_mode == self::price_override_fixed)
				return $this->price_or_discount;

			if ($this->price_override_mode == self::price_override_fixed_discount)
				return max(0, $price - $this->price_or_discount);

			return $price - $price*$this->price_or_discount/100;
		}
		
		/** 
		 * Finds a bundle item product by its identifier.
		 * @documentable
		 * @param integer $id Specifies the bundle item product identifier. 
		 * @return Shop_BundleItemProduct Returns the bundle item product object. Returns NULL if the object is not found.
		 */
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}
	}

?>