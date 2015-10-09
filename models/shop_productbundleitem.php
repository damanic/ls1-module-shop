<?php

	/**
	 * Represents a bundle item in a bundle product. 
	 * For example, it can represent CPU in a computer product. Bundle items contain bundle item products 
	 * (objects of {@link Shop_BundleItemProduct} class).
	 * @property string $name Specifies the bundle item name (e.g. CPU or RAM).
	 * @property string $description Specifies the bundle item description.
	 * @property boolean $is_required Determines whether the item is required.
	 * @property string $control_type Determines the control type to be used on the store pages.
	 * Possible values are: <em>dropdown</em>, <em>checkbox</em>, <em>radio</em>.
	 * @property Db_DataCollection $images A collection of images associated with the bundle item. 
	 * Each element in the collection is an object of the {@link Db_File} class.
	 * @property Shop_Product $product A reference to the master bundle product, which hosts this bundle item.
	 * @property Db_DataCollection $item_products A collection of item products. 
	 * Each element in the collection is an object of {@link Shop_BundleItemProduct} class.
	 * @documentable
	 * @see http://lemonstand.com/docs/managing_bundle_products/ Managing bundle products
	 * @see http://lemonstand.com/docs/displaying_product_bundle_items/ Displaying product bundle items
	 * @see Shop_BundleHelper
	 * @see Shop_BundleItemProduct
	 * @author LemonStand eCommerce Inc.
	 * @package shop.models
	 */
	class Shop_ProductBundleItem extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_bundle_items';
		public $implement = 'Db_Sortable';
		
		const control_dropdown = 'dropdown';
		const control_checkbox = 'checkbox';
		const control_radio = 'radio';
		
		protected static $cache = array();

		public $belongs_to = array(
			'product'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'product_id')
		);

		public $has_many = array(
			'item_products_all'=>array('class_name'=>'Shop_BundleItemProduct', 'delete'=>true, 'order'=>'sort_order', 'foreign_key'=>'item_id'),
			'item_products'=>array(
				'class_name'=>'Shop_BundleItemProduct', 
				'delete'=>true, 
				'order'=>'sort_order', 
				'foreign_key'=>'item_id', 
				'conditions'=>'
					(is_active is not null and is_active=1) and 
					(
						exists(select * from shop_products where shop_products.id=shop_bundle_item_products.product_id and

						((
							shop_products.enabled=1 and not (
								ifnull(shop_products.track_inventory, 0)=1 and
								ifnull(shop_products.hide_if_out_of_stock, 0)=1 and
								(
									(shop_products.stock_alert_threshold is not null and shop_products.in_stock <= shop_products.stock_alert_threshold) or
									(shop_products.stock_alert_threshold is null and shop_products.in_stock<=0)
								)
							)
						) or exists(
							select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and
								grouped_products.enabled=1 and not (
									ifnull(grouped_products.track_inventory,0)=1 and
									ifnull(grouped_products.hide_if_out_of_stock,0)=1 and
									(
										(grouped_products.stock_alert_threshold is not null and grouped_products.in_stock <= grouped_products.stock_alert_threshold) or
										(grouped_products.stock_alert_threshold is null and grouped_products.in_stock<=0)
									)
								)
							)
						) and ifnull(shop_products.disable_completely,0)=0 and ifnull(shop_products.grouped, 0)=0
					))
				'
			),
			'images'=>array(
				'class_name'=>'Db_File', 
				'foreign_key'=>'master_object_id', 
				'conditions'=>"master_object_class='Shop_ProductBundleItem' and field='images'", 
				'order'=>'sort_order, id', 'delete'=>true)
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			$this->define_column('name', 'Name')->validation()->fn('trim')->required('Please specify the bundle item name.');
			$this->define_column('description', 'Description')->validation()->fn('trim');
			
			$this->define_column('is_required', 'Required');
			$this->define_column('control_type', 'Control type');

			$this->define_multi_relation_column('images', 'images', 'Images', $front_end ? null : '@name')->invisible();
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name')->comment('Bundle item name will be displayed on the product page.', 'above')->tab('Name and description');
			$this->add_form_field('description')->size('small')->tab('Name and description');;
			
			$this->add_form_field('control_type')->renderAs(frm_dropdown)->tab('Name and description');;
			$this->add_form_field('is_required')->tab('Name and description');

			$this->add_form_field('images')->renderAs(frm_file_attachments)->renderFilesAs('image_list')->addDocumentLabel('Add image(s)')->tab('Images')->noAttachmentsLabel('There are no images uploaded')->fileDownloadBaseUrl(url('ls_backend/files/get/'));
		}
		
		public function get_control_type_options($key = -1)
		{
			$options = array(
				self::control_dropdown => 'Drop-down menu',
				self::control_checkbox => 'Checkbox list',
				self::control_radio => 'Radio buttons'
			);
			
			if ($key == -1)
				return $options;
				
			return isset($options[$key]) ? $options[$key] : null;
		}
		
		public function add_products($product_ids, $session_key)
		{
			foreach ($product_ids as $id)
			{
				$item_products = $this->list_related_records_deferred('item_products_all', $session_key);
				foreach ($item_products as $item_product)
				{
					if ($item_product->product_id == $id)
						continue 2;
				}
				
				$product = Shop_BundleItemProduct::create();
				$product->product_id = $id;
				$product->default_quantity = 1;
				$product->allow_manual_quantity = 1;
				$product->price_override_mode = Shop_BundleItemProduct::price_override_default;
				$product->is_active = 1;
				$product->save();
				
				$this->item_products_all->add($product, $session_key);
			}
			
			$this->save();
		}
		
		public function remove_products($product_ids, $session_key)
		{
			foreach ($product_ids as $product_id)
			{
				$bundle_product = Shop_BundleItemProduct::create()->find($product_id);
				if ($bundle_product)
					$this->item_products_all->delete($bundle_product, $session_key);
			}
		}
		
		/** 
		 * Finds a product bundle item by its identifier.
		 * @documentable
		 * @param integer $id Specifies the product bundle item identifier. 
		 * @return Shop_ProductBundleItem Returns the product bundle item object. Returns NULL if the object is not found.
		 */
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}
	}

?>