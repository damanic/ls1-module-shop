<?php

	/**
	 * Represents a product. 
	 * The class has fields and methods for accessing the product name, description, price and other parameters. 
	 *
	 * @property boolean $allow_pre_order Indicates whether the product inventory tracking settings allow pre-ordering. 
	 * You can use this field value for detecting whether the <em>Add to Cart</em> button should be visible on the 
	 * {@link http://lemonstand.com/docs/product_page product details page}. Usage example:
	 * <pre>
	 * <? if (!$product->is_out_of_stock() || $product->allow_pre_order): ?>
	 *    ... Add to Cart button ...      
	 * <? endif ?>
	 * </pre>
	 * @property Db_DataCollection $category_list A list of categories the product belongs to.
	 * Each element in the collection is an object of {@link Shop_Category} class.
	 * @property Phpr_DateTime $created_at Specifies a date and time when the product has been added to the database.
	 * @property float $depth Specifies the product depth.
	 * @property string $description Specifies the product description in HTML format.
	 * @property boolean $disable_completely Determines whether the product and all its grouped products are disabled (overrides the {@link Shop_Product::enabled enabled} property). Completely disabled products are displayed in the product list in the Administration Area, but not displayed in other lists (Discounts, etc.).
	 * @property boolean $enabled Determines whether the product is enabled. 
	 * See also {@link Shop_Product::disable_completely disable_completely} property.
	 * @property Phpr_DateTime $expected_availability_date Specifies the date of the product's expected availability.
	 * You can display this field on the website using the following simple code: 
	 * <pre>
	 * <? if ($product->is_out_of_stock()): ?>
	 *     <p>
	 *         <strong>This product is temporarily unavailable</strong>
	 *         <? if ($product->expected_availability_date): ?>  
	 *             <br/>The expected availability date is <?= $product->displayField('expected_availability_date') ?>
	 *         <? endif ?>
	 *     </p>
	 * <? endif ?>
	 * </pre>
	 * @property Db_DataCollection $extra_options A collection of the product extra options. 
	 * Each element in the collection is {@link Shop_ExtraOption} object.
	 * @property Db_DataCollection $files A list of files associated with the product. 
	 * Files can be associated only with downloadable products. Each element of the collection is an object of the {@link Shop_ProductFile} class.
	 * @property string $grouped_menu_label Specifies a label for the grouped products option list, for example <em>"Color"</em>. 
	 * A value of this field is specified in the <em>Attribute Name</em> field of the Create/Edit Product form in the Administration Area, on the Grouped tab.
	 * @property string $grouped_option_desc Specifies a description of the product in the list of grouped products, for example <em>"Green"</em>.
	 * @property Db_DataCollection $grouped_products A list of grouped products associated with the product. 
	 * Use this list for displaying a list of grouped products on the product page. Each element in the collection is {@link Shop_Product} object.
	 * @property float $height Specifies the product height.
	 * @property integer $id Specifies the product identifier in the database.
	 * @property Db_DataCollection $images A collection of the product images. 
	 * Each element in the collection is an object of the {@link Db_File} class.
	 * @property integer $in_stock Indicates how many units of the products are available in stock. 
	 * See also the {@link Shop_Product::in_stock_grouped() in_stock_grouped()} method.
	 * @property Shop_Manufacturer $manufacturer Specifies the product manufacturer.
	 * This field can be NULL if a manufacturer is not specified for the product.
	 * @property Shop_Product $master_grouped_product Specifies a reference to the parent grouped product.
	 * @property string $meta_description Specifies the product description for outputting in the HTML META element on the {@link http://lemonstand.com/docs/product_page product details page}.
	 * @property string $meta_keywords Specifies the product keywords for outputting in the HTML META element on the {@link http://lemonstand.com/docs/product_page product details page}.
	 * @property string $name Specifies the product name.
	 * @property Db_DataCollection $options Contains a list of product options. 
	 * Each element in the collection is an object of {@link Shop_CustomAttribute} class. 
	 * Please read the {@link http://lemonstand.com/docs/displaying_product_options Displaying Product Options} article for details and code examples.
	 * @property integer $product_id Specifies identifier of a parent product for a grouped product.
	 * @property float $rating_all Specifies a rating based on all reviews, included non-approved. 
	 * @property integer $rating_all_review_num Specifies a number of <em>all</em> reviews with a rating specified. 
	 * You can use this filed for displaying the "Based on 8 reviews" text near the product rating value.
	 * @property float $rating_approved Returns a rating based on approved reviews. 
	 * This and the <em>$rating_all</em> fields has a value in the <em>0-5</em> range. Value 0 means that there is no 
	 * rating information available for the product. Values have increment of 0.5 i.e.: 1, 1.5, 2, 2.5, 3, 3.5, 4. 4,5, 5. There is no 0.5 value, 
	 * because the minimal rating a visitor can set is 1.
	 * @property integer $rating_review_num Specifies a number of <em>approved</em> reviews with rating specified. 
	 * You can use this filed for displaying the "Based on 8 reviews" text near the product rating value.
	 * @property Db_DataCollection $related_products A list of related products. 
	 * Each element of the collection is an object of Shop_Product class. The collection contains all enabled and available related products, 
	 * but ignores product visibility filters (customer group filter, etc). If you want to obtain a list of related products with visibility 
	 * filters applied, please use the {@link Shop_Product::list_related_products() list_related_products()} method.
	 * @property string $short_description Specifies the product short description as plain text.
	 * @property string $sku Specifies the product SKU.
	 * @property Shop_TaxClass $tax_class Specifies a tax class the product belongs to.
	 * @property string $url_name Specifies the product URL name. 
	 * The {@link action@shop:product} action uses this field to load a product by an URL parameter. Usually you don't 
	 * need to access the field directly. Use the {@link Shop_Product::page_url() page_url()} method for creating links to 
	 * {@link http://lemonstand.com/docs/product_page product pages}.
	 * @property boolean $visibility_catalog Determines whether product should be visible in the catalog. 
	 * This field affects the {@link Shop_Category::list_products()} and {@link Shop_CustomGroup::list_products()} methods.
	 * @property boolean $visibility_search Determines whether product should be visible in search results. 
	 * This field affects the {@link Shop_Product::find_products() find_products()} method behavior.
	 * @property float $weight Specifies the product weight.
	 * @property float $width Specifies the product width.
	 * @property Shop_ProductType $product_type Specifies the product type.
	 * @documentable
	 * @see http://lemonstand.com/docs/product_page/ Product page
	 * @see http://lemonstand.com/docs/displaying_a_list_of_products/ Displaying a list of products
	 * @see http://lemonstand.com/docs/displaying_a_list_of_grouped_products/ Displaying a list of grouped products
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_Product extends Db_ActiveRecord
	{
		public $table_name = 'shop_products';

		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;

		public $enabled = true;
		public $visibility_search = true;
		public $visibility_catalog = true;
		public $perproduct_shipping_cost_use_parent = true;
		
		protected $api_added_columns = array();

		public static $price_sort_query = '(ifnull((select min_price from shop_product_price_index where pi_product_id=shop_products.id and pi_group_id=\'%s\'), shop_products.price)) ';
		public static $is_on_sale_query = '(ifnull((select is_on_sale from shop_product_price_index where pi_product_id=shop_products.id and pi_group_id=\'%s\'), 0)) ';
		public static $allowed_sorting_columns = array('name', 'title', 'price', 'sku', 'weight', 'width', 'height', 'depth', 'rand()', 'created_at', 'manufacturer', 'expected_availability_date');

		protected static $cache = array();
		protected $category_cache = null;
		protected $category_id_cache = null;
		protected $om_options_preset = null;
		protected $price_index_data = null;

		public $belongs_to = array(
			'page'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'page_id'),
			'master_grouped_product'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'product_id'),
			'tax_class'=>array('class_name'=>'Shop_TaxClass', 'foreign_key'=>'tax_class_id'),
			'product_type'=>array('class_name'=>'Shop_ProductType', 'foreign_key'=>'product_type_id'),
			'manufacturer_link'=>array('class_name'=>'Shop_Manufacturer', 'foreign_key'=>'manufacturer_id')
		);

		public $has_and_belongs_to_many = array(
			'categories'=>array('class_name'=>'Shop_Category', 'join_table'=>'shop_products_categories', 'order'=>'name'),
			'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'shop_products_customer_groups', 'order'=>'name', 'foreign_key'=>'customer_group_id', 'primary_key'=>'shop_product_id'),
			'related_products_all'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_related_products', 'primary_key'=>'master_product_id', 'foreign_key'=>'related_product_id'),
			
			// Interface related products list
			//
//			'related_product_list'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_related_products', 'primary_key'=>'master_product_id', 'foreign_key'=>'related_product_id', 'conditions'=>'shop_products.enabled=1')

			'related_product_list'=>array('class_name'=>'Shop_Product', 'order'=>'shop_products.name', 'join_table'=>'shop_related_products', 'primary_key'=>'master_product_id', 'foreign_key'=>'related_product_id', 'conditions'=>'((shop_products.enabled=1 and not (
				shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock<=0))
				)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
				and not (
				grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.total_in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.total_in_stock<=0))
				))) and (shop_products.disable_completely is null or shop_products.disable_completely = 0)'),
				
			'extra_option_sets'=>array('class_name'=>'Shop_ExtraOptionSet', 'order'=>'shop_extra_option_sets.name', 'join_table'=>'shop_products_extra_sets', 'primary_key'=>'extra_product_id', 'foreign_key'=>'extra_option_set_id')			
		);

		public $has_many = array(
			'grouped_products_all'=>array('class_name'=>'Shop_Product', 'delete'=>true, 'order'=>'grouped_sort_order', 'foreign_key'=>'product_id'),
			'images'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Product' and field='images'", 'order'=>'sort_order, id', 'delete'=>true),
			'options'=>array('class_name'=>'Shop_CustomAttribute', 'foreign_key'=>'product_id', 'order'=>'sort_order', 'delete'=>true),
			'product_extra_options'=>array('class_name'=>'Shop_ExtraOption', 'foreign_key'=>'product_id', 'order'=>'id', 'delete'=>true, 'order'=>'extra_option_sort_order', 'conditions'=>'(option_in_set is null or option_in_set=0)'),
			'properties'=>array('class_name'=>'Shop_ProductProperty', 'foreign_key'=>'product_id', 'order'=>'sort_order', 'delete'=>true),
			'price_tiers'=>array('class_name'=>'Shop_PriceTier', 'foreign_key'=>'product_id', 'order'=>'(select name from shop_customer_groups where id=customer_group_id), price desc', 'delete'=>true),
			'bundle_items_link'=>array('class_name'=>'Shop_ProductBundleItem', 'foreign_key'=>'product_id', 'order'=>'sort_order', 'delete'=>true),
			'option_matrix_records'=>array('class_name'=>'Shop_OptionMatrixRecord', 'foreign_key'=>'product_id', 'order'=>'shop_option_matrix_records.id', 'delete'=>true),

			// Interface grouped products list
			//
			'grouped_product_list'=>array('class_name'=>'Shop_Product', 'delete'=>true, 'order'=>'grouped_sort_order', 'foreign_key'=>'product_id', 'conditions'=>'shop_products.enabled=1 and not (
			shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock<=0)))'),

			'files'=>array('class_name'=>'Shop_ProductFile', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Product' and field='files'", 'order'=>'id', 'delete'=>true),

			'uploaded_files'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Product' and field='uploaded_files'", 'order'=>'id', 'delete'=>true)
		);

		public $calculated_columns = array(
			'page_url'=>array('sql'=>"pages.url", 'type'=>db_text, 'join'=>array('pages'=>'shop_products.page_id=pages.id')),
			'items_ordered'=>array('sql'=>'0', 'type'=>db_number),
			'grouped_name'=>array('sql'=>'if (shop_products.grouped = 1, concat(shop_products.name, " (", shop_products.grouped_option_desc,")"), shop_products.name)', 'type'=>db_text)
		);
		
		public $perproduct_shipping_cost = array(
			array(
				'country'=>'*',
				'state'=>'*',
				'zip'=>'*',
				'cost'=>'0'
			)
		);

		/**
		 * The current_price field is needed only for the price rule conditions user interface. 
		 * Please use the price() method for obtaining a current product price.
		 */
		public $custom_columns = array(
			'current_price'=>db_number,
			'csv_import_parent_sku'=>db_text,
			'csv_import_om_flag'=>db_bool,
			'csv_import_om_parent_sku'=>db_text,
			'csv_related_sku'=>db_text,
			'image'=>db_text
		);
		
		public $category_sort_order;
		protected $categories_column;
		protected $has_om_records = null;

		public static function create()
		{
			return new self();
		}
		
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}

		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('image', 'Image');
			
			$this->define_column('grouped_name', 'Product')->invisible();

			$this->define_column('url_name', 'URL Name')->validation()->fn('trim')->fn('mb_strtolower')->regexp('/^[0-9a-z_-]*$/i', 'URL Name can contain only latin characters, numbers and signs -, _, -')->method('validateUrl')->unique('The URL Name "%s" already in use. Please select another URL Name.', array($this, 'configure_unique_validator'));
			
			$this->define_column('title', 'Title')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('description', 'Long Description')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('short_description', 'Short Description')->defaultInvisible()->validation()->fn('trim');
			
			$this->define_relation_column('page', 'page', 'Custom Page ', db_varchar, $front_end ? null : '@title')->defaultInvisible()->listTitle('Page')->validation();
			$this->define_relation_column('product_type', 'product_type', 'Product Type ', db_varchar, $front_end ? null : '@name')->defaultInvisible()->listTitle('Type')->validation();
			$this->define_relation_column('manufacturer_link', 'manufacturer_link', 'Manufacturer ', db_varchar, '@name')->defaultInvisible()->validation();
			$this->define_multi_relation_column('images', 'images', 'Images', $front_end ? null : '@name')->invisible();
			
			$this->define_column('price', 'Base Price')->currency(true)->defaultInvisible()->validation()->fn('trim')->required();
			$this->define_column('cost', 'Cost')->currency(true)->defaultInvisible()->validation()->fn('trim');
			$this->define_column('enabled', 'Enabled')->defaultInvisible();
			$this->define_column('disable_completely', 'Disable Completely')->defaultInvisible();
			
			$this->define_relation_column('tax_class', 'tax_class', 'Tax Class ', db_varchar, $front_end ? null : '@name')->defaultInvisible()->validation()->required('Please select product tax class.');

			$this->define_column('tier_prices_per_customer', 'Take into account previous orders')->defaultInvisible();
			$this->define_column('on_sale', 'On Sale')->defaultInvisible();
			$this->define_column('sale_price_or_discount', 'Sale Price or Discount')->defaultInvisible()->validation()->fn('trim')->method('validate_sale_price_or_discount');

			$this->define_column('sku', 'SKU')->validation()->fn('trim')->required("Please enter the product SKU")->unique('The SKU "%s" is already in use.', array($this, 'configure_unique_validator'));
			
			$this->define_column('weight', 'Weight')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('width', 'Width')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('height', 'Height')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('depth', 'Depth')->defaultInvisible()->validation()->fn('trim');

			$this->define_column('enable_perproduct_shipping_cost', 'Enable per product shipping cost')->invisible();
			$this->define_column('perproduct_shipping_cost', 'Shipping cost')->invisible()->validation();
			$this->define_column('perproduct_shipping_cost_use_parent', 'Use parent product per product shipping cost settings')->invisible();
			
			
			$this->define_column('track_inventory', 'Track Inventory')->defaultInvisible();
			$this->define_column('in_stock', 'Units In Stock')->defaultInvisible()->validation()->fn('trim')->method('validate_in_stock');
			$this->define_column('total_in_stock', 'Total Units In Stock')->defaultInvisible();
			
			$this->define_column('allow_negative_stock_values', 'Allow Negative Stock Values')->defaultInvisible();
			$this->define_column('hide_if_out_of_stock', 'Hide if Out Of Stock')->defaultInvisible();
			$this->define_column('stock_alert_threshold', 'Out of Stock  Threshold')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('low_stock_threshold', 'Low Stock  Threshold')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('expected_availability_date', 'Expected Availability Date')->defaultInvisible()->validation();
			
			$this->define_column('allow_pre_order', 'Allow pre-order')->defaultInvisible();
			
			$this->define_column('meta_description', 'Meta Description')->defaultInvisible()->listTitle('Meta Description')->validation()->fn('trim');
			$this->define_column('meta_keywords', 'Meta Keywords')->defaultInvisible()->listTitle('Meta Keywords')->validation()->fn('trim');
			
			$this->categories_column = $this->define_multi_relation_column('categories', 'categories', 'Categories', $front_end ? null : '@name')->defaultInvisible()->validation();
			$this->define_multi_relation_column('grouped_products_all', 'grouped_products_all', 'Grouped Products', $front_end ? null : "@grouped_option_desc")->invisible();
			
			$this->define_column('grouped_attribute_name', 'Attribute Name')->invisible()->validation()->method('validate_grouped_options');
			$this->define_column('grouped_option_desc', 'This Product Description')->invisible()->validation()->method('validate_grouped_options');

			$this->define_multi_relation_column('options', 'options', 'Options', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('product_extra_options', 'product_extra_options', 'Extra Options', $front_end ? null : "@description")->invisible();
			$this->define_multi_relation_column('extra_option_sets', 'extra_option_sets', 'Global extra option sets', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('price_tiers', 'price_tiers', 'Price Tiers', $front_end ? null : "@id")->invisible();
			$this->define_multi_relation_column('related_products_all', 'related_products_all', 'Related Products', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('properties', 'properties', 'Properties', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('files', 'files', 'Files', $front_end ? null : '@name')->defaultInvisible();

			$this->define_column('xml_data', 'XML Data')->invisible()->validation()->fn('trim');
			
			$this->define_column('current_price', 'Price')->invisible();
			
			$this->define_column('csv_import_parent_sku', 'Grouped - Parent Product SKU')->invisible();
			$this->define_column('csv_related_sku', 'Related Products SKU')->invisible();

			$this->define_column('csv_import_om_flag', 'Option Matrix Record Flag')->invisible();
			$this->define_column('csv_import_om_parent_sku', 'Option Matrix - Parent Product SKU')->invisible();

			$this->define_column('grouped_sort_order', 'Grouped - Sort Order')->invisible();

			$this->define_column('items_ordered', 'Units ordered')->invisible();
			
			$this->define_multi_relation_column('customer_groups', 'customer_groups', 'Customer Groups', $front_end ? null : '@name')->defaultInvisible();

			$this->define_column('enable_customer_group_filter', 'Enable customer group filter')->defaultInvisible();
			$this->define_column('product_rating', 'Rating (Approved)')->defaultInvisible();
			$this->define_column('product_rating_all', 'Rating (All)')->defaultInvisible();
			
			$this->define_column('visibility_search', 'Visible in search results')->invisible();
			$this->define_column('visibility_catalog', 'Visible in the catalog')->invisible();
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendProductModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			if ($context == 'preview')
			{
				$user = Phpr::$security->getUser();
				$reports_access = $user->get_permission('shop', 'access_reports');

				$this->add_form_field('name', 'left')->tab('Product Summary');
				$this->add_form_field('sku', 'right')->tab('Product Summary');
				
				$this->add_form_field('product_type', 'left')->tab('Product Summary')->previewNoRelation();
				$this->add_form_field('manufacturer_link', 'right')->previewNoRelation()->previewNoOptionsMessage('Not assigned')->tab('Product Summary');
				
				if (!$this->page)
				{
					$this->add_form_field('url_name')->tab('Product Summary');
				} else
				{
					$this->add_form_field('url_name', 'left')->tab('Product Summary');
					$this->add_form_field('page', 'right')->tab('Product Summary')->previewNoRelation()->previewLink($this->page_url('/'));
				}
				
				if ($this->track_inventory)
					$this->add_form_field('in_stock')->tab('Product Summary');

				$this->add_form_field('price', 'left')->tab('Product Summary');
				$this->add_form_field('cost', 'right')->tab('Product Summary');
				
				if (!$reports_access)
				{
					$this->add_form_field('title')->tab('Description');
					$this->add_form_field('short_description')->tab('Description');
					$this->add_form_field('meta_description')->tab('Description');
					$this->add_form_field('meta_keywords')->tab('Description');
				}

				if ($this->grouped_products_all->count)
					$this->add_form_custom_area('grouped_list')->tab('Product Summary');

				if ($reports_access)
					$this->add_form_custom_area('statistics_data')->tab('Product Statistics');
			} elseif ($context == 'option-matrix') {
				// Option Matrix fields are configured by the Option Matrix behavior
			} else {
				$front_end = Db_ActiveRecord::$execution_context == 'front-end';

				if ($context == 'grouped')
				{
					$this->add_form_field('grouped_option_desc')->tab('Product')->comment('Please specify a description for a drop-down list option corresponding this product, e.g. "XXL size".', 'above');
					$column = $this->find_column_definition('grouped_option_desc');
					$column->validation()->required();
				}
			
				if ($context != 'grouped')
					$this->add_form_field('enabled', 'left')->tab('Product')->comment('Use this checkbox to show or hide the product from the website. This option does not affect grouped products.');
				else
					$this->add_form_field('enabled')->tab('Product')->comment('Use this checkbox to show or hide the product from the website.');

				if ($context != 'grouped')
					$this->add_form_field('disable_completely', 'right')->tab('Product')->comment('Use this checkbox to hide this product and all its grouped products from the website and Administration Area user interface.');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_field('product_type', 'left')->tab('Product');
					$this->add_form_field('manufacturer_link', 'right')->tab('Product');
				}
			
				$this->add_form_field('name', 'left')->tab('Product');
				$this->add_form_field('sku', 'right')->tab('Product');

				if ($context != 'grouped')
					$this->add_form_field('url_name', 'left')->tab('Product')->comment('Specify the product URL name (for example "cannon_printer") or leave this field empty if you want to provide a specially designed product page.', 'above');
				else
					$this->add_form_field('url_name')->tab('Product');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_field('page', 'right')->tab('Product')->emptyOption('<default product page>')->comment('You can customize the product landing page. Select a page, specially designed for this product or leave the default value.', 'above')->optionsHtmlEncode(false);
				
					$this->categories_column->required('Please select categories the product belongs to.');
				}

				$this->add_form_field('title')->comment('Use this field to customize the product page title. Leave this field empty to use the product name as the page title.', 'above')->tab('Product');
			
				if (!$front_end)
					$this->add_form_field('tax_class')->tab('Pricing')->emptyOption('<please select>');

				$this->add_form_field('price', 'left')->tab('Pricing')->comment('The product price will be visible on the front-end store. You can set different prices for different customer groups using the tier price section below.', 'above');
				$this->add_form_field('cost', 'right')->tab('Pricing')->comment('The product cost will be subtracted from the price to get the revenue value in reports. Leave this value empty if the revenue should match the product price.', 'above');
				$this->add_form_field('on_sale')->tab('Pricing')->comment('Select to override the catalog price rules for this product and enter the sale price or discount below directly.', 'above');
				$this->add_form_field('sale_price_or_discount')->tab('Pricing')->comment('Enter the sale price as a fixed sale price (e.g. 5.00), the discount amount (e.g. -5.00) or discount percentage (e.g. 25.00%). The discount amount and percentage will be subtracted from the regular price to calculate the sale price.', 'above');

				$this->add_form_section(null, 'Tier Price')->tab('Pricing');
				$this->add_form_field('tier_prices_per_customer')->tab('Pricing');

				if (!$front_end)
					$this->add_form_field('price_tiers')->tab('Pricing')->renderAs('price_tiers');

				$this->add_form_field('short_description')->tab('Product')->size('small');
				$field = $this->add_form_field('description')->tab('Product')->renderAs(frm_html)->size('small')->saveCallback('save_item');
				$field->htmlPlugins .= ',save';
				$field->htmlFullWidth = true;
				$editor_config = System_HtmlEditorConfig::get('shop', 'shop_products_categories');
				$editor_config->apply_to_form_field($field);
			
				if (!$front_end)
				{
					$this->add_form_field('images')->renderAs(frm_file_attachments)->renderFilesAs('image_list')->addDocumentLabel('Add image(s)')->tab('Images')->noAttachmentsLabel('There are no images uploaded')->fileDownloadBaseUrl(url('ls_backend/files/get/'));
					$this->add_form_field('files')->renderAs(frm_file_attachments)->tab('Files')->fileDownloadBaseUrl(url('ls_backend/files/get/'));
				}

				$this->add_form_section('Dimensions are used for evaluating shipping cost.')->tab('Shipping');
				$this->add_form_field('weight', 'left')->tab('Shipping');
				$this->add_form_field('width', 'right')->tab('Shipping');
				$this->add_form_field('height', 'left')->tab('Shipping');
				$this->add_form_field('depth', 'right')->tab('Shipping');

				if ($context == 'grouped')
					$this->add_form_field('perproduct_shipping_cost_use_parent')->tab('Shipping');
				
				$this->add_form_field('enable_perproduct_shipping_cost')->tab('Shipping');

				$this->add_form_field('perproduct_shipping_cost')->tab('Shipping')->renderAs(frm_grid)->gridColumns(array(
					'country'=>array('title'=>'Country Code', 'align'=>'left', 'width'=>'100', 'autocomplete'=>array('type'=>'local', 'tokens'=>$this->get_ppsc_country_list())), 
					'state'=>array('title'=>'State/County Code', 'align'=>'left', 'width'=>'120', 'autocomplete'=>array('type'=>'local', 'depends_on'=>'country', 'tokens'=>$this->get_ppsc_state_list())),
					'zip'=>array('title'=>'ZIP/Postal Code', 'align'=>'left'),
					'cost'=>array('title'=>'Cost', 'align'=>'right')
				))->comment('Specify a shipping cost for different locations. The shipping cost for this product will be added to the shipping quote, which is determined by the shipping method that the customer chooses.', 'above');
			
				$this->add_form_field('track_inventory', 'left')->tab('Inventory')->comment('Enable this checkbox if you have limited number of this product in stock.');
				$this->add_form_field('hide_if_out_of_stock', 'right')->tab('Inventory')->comment('Remove the product from the website if is out of stock.', 'below');
				$this->add_form_field('allow_negative_stock_values')->tab('Inventory');
			
				$this->add_form_field('in_stock', 'left')->tab('Inventory')->comment('Specify how many units of the product there are left in stock at the moment.', 'above');
				$this->add_form_field('stock_alert_threshold', 'right')->tab('Inventory')->comment('The low number of units to set the product status to Out of Stock.', 'above');
				$this->add_form_field('low_stock_threshold', 'left')->tab('Inventory')->comment('Number of units when a low stock notification should be sent to the administrators.', 'above');
				$this->add_form_field('expected_availability_date', 'left')->tab('Inventory');
			
				$this->add_form_field('allow_pre_order', 'left')->tab('Inventory')->comment('Allow customers to order the product even if it is out of stock.', 'below');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_field('categories')->tab('Categories')->comment('Select categories the product belongs to.', 'above')->referenceSort('name')->optionsHtmlEncode(false);
				}

				$this->add_form_field('meta_description')->tab('Meta');
				$this->add_form_field('meta_keywords')->tab('Meta');

				if ($context != 'grouped' && !Phpr::$config->get('DISABLE_GROUPED_PRODUCTS'))
				{
					$this->add_form_partial('grouped_description')->tab('Grouped');
					$this->add_form_field('grouped_attribute_name', 'left')->tab('Grouped')->comment('Provide a text label to be displayed near the grouped products drop-down menu, e.g. "Size".', 'above');
					$this->add_form_field('grouped_option_desc', 'right')->tab('Grouped')->comment('Please specify a description for a drop-down list option corresponding this product, e.g. "Small size".', 'above');
					if (!$front_end)
						$this->add_form_field('grouped_products_all')->tab('Grouped');
				}

				$this->add_form_partial('options_description')->tab('Options');

				if (!$front_end)
					$this->add_form_field('options')->tab('Options')->renderAs('options');
			
				$this->add_form_partial('extras_description')->tab('Extras');
				
				if (!$front_end)
				{
					$this->add_form_field('product_extra_options')->tab('Extras');
					$this->add_form_field('extra_option_sets')->tab('Extras')->noOptions('Global extra option sets are not defined. You can create option sets on the Shop/Products/Manage extra option sets page.')->comment('Select global extra option sets you want to include to this product.', 'above');

					$this->add_form_partial('attributes_description')->tab('Attributes');
					$this->add_form_field('properties')->tab('Attributes');
				}

				$this->add_form_field('xml_data')->tab('XML Data')->renderAs(frm_code_editor)->language('xml')->size('giant');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_partial('related_description')->tab('Related');
					$this->add_form_field('related_products_all')->tab('Related')->renderAs('related');

					$this->add_form_field('visibility_search', 'left')->tab('Visibility')->comment('Use this checkbox to make the product visible in search results.');
					$this->add_form_field('visibility_catalog', 'right')->tab('Visibility')->comment('Use this checkbox to make the product visible on the catalog pages.');

					$this->add_form_field('enable_customer_group_filter')->tab('Visibility');
					$this->add_form_field('customer_groups')->tab('Visibility')->comment('Please select customer groups the product should be visible for.', 'above');
			
					$this->form_tab_id('Files', 'tab_files');
					$this->form_tab_id('Inventory', 'tab_inventory');
					$this->form_tab_id('Shipping', 'tab_shipping');
					$this->form_tab_id('Grouped', 'tab_grouped');
					$this->form_tab_id('Options', 'tab_options');
					$this->form_tab_id('Extras', 'tab_extras');
					$this->form_tab_id('XML Data', 'tab_xml');
				}

				/*
				 * Init product type and setup tabs visibility
				 */

				if (!$this->product_type_id)
				{
					$this->product_type = Shop_ProductType::get_default_type();
					$this->product_type_id = $this->product_type->id;
				}

				$product_type = $this->product_type;
				
				$this->form_tab_visibility('Files', $product_type->files);
				$this->form_tab_visibility('Inventory', $product_type->inventory);
				$this->form_tab_visibility('Shipping', $product_type->shipping);
				$this->form_tab_visibility('Grouped', $product_type->grouped);
				$this->form_tab_visibility('Options', $product_type->options);
				$this->form_tab_visibility('Extras', $product_type->extras);
				$this->form_tab_visibility('XML Data', $product_type->xml);
			}
			
			if ($context != 'option-matrix')
			{
				Backend::$events->fireEvent('shop:onExtendProductForm', $this, $context);
				foreach ($this->api_added_columns as $column_name)
				{
					$form_field = $this->find_form_field($column_name);
					if ($form_field) {
						$form_field->optionsMethod('get_added_field_options');
						$form_field->optionStateMethod('get_added_field_option_state');
					}
				}
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetProductFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetProductFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public function validate_grouped_options($name, $value)
		{
			$value = trim($value);

			if (!$this->grouped_products_all->count)
				return true;
				
			if (strlen($value))
				return true;
				
			if ($name == 'grouped_attribute_name')
				$this->validation->setError('Please specify the grouped products attribute name value', $name, true);

			if ($name == 'grouped_option_desc')
				$this->validation->setError('Please specify the product option description', $name, true);
				
			return true;
		}
		

		public function get_categories_options($keyValue = -1)
		{
			$result = array();
			$obj = new self();

			if ($keyValue == -1)
				$this->list_categories_id_options(null, $result, 0, null);
			else 
			{
				if ($keyValue == null)
					return $result;
				
				$obj = Shop_Category::create();
				$obj = $obj->find($keyValue);

				if ($obj)
					return h($obj->name);
			}

			return $result;
		}

		private function list_categories_id_options($items, &$result, $level, $ignore)
		{
			if ($items === null)
				$items = Shop_Category::list_children_category_proxies(null);
			
			foreach ($items as $item)
			{
				if ($ignore !== null && $item->id == $ignore)
					continue;

				$result[$item->id] = array($item->name, null, $level, 'level'=>$level);
				$this->list_categories_id_options(Shop_Category::list_children_category_proxies($item->id), $result, $level+1, $ignore);
			}
		}
		
		public function get_page_options($key_value=-1)
		{
			return Cms_Page::create()->get_page_tree_options($key_value);
		}
		
		public function validateUrl($name, $value)
		{
			$urlName = trim($this->url_name);

			if (!strlen($urlName) && !$this->page)
				$this->validation->setError('Please specify either URL name or product custom page.', $name, true);
				
			return true;
		}

		public function validate_in_stock($name, $value)
		{
			if ($this->track_inventory && !strlen(trim($value)))
				$this->validation->setError('Please specify a number of products in stock.', $name, true);
				
			return true;
		}
		
		public function validate_sale_price_or_discount($name, $value)
		{
			if(!strlen($value) && $this->on_sale)
				$this->validation->setError('Please specify a sale price or discount or uncheck the "On Sale" checkbox.', $name, true);
			
			if($error = self::is_sale_price_or_discount_invalid($value, $this->price))
				$this->validation->setError($error, $name, true);
			
			return true;
		}
		
		public function configure_unique_validator($checker, $product, $deferred_session_key)
		{
			/*
			 * Exclude not commited deferred bindings
			 */
			
			$filter = 'not (exists(select * from db_deferred_bindings where detail_class_name=\'Shop_Product\' and master_relation_name=\'grouped_products_all\' and detail_key_value=shop_products.id) %s)';

			if ($deferred_session_key)
				$filter = sprintf($filter, ' or exists(select * 
					from 
						db_deferred_bindings as master_binding
					where 
						master_binding.detail_class_name=\'Shop_Product\' 
						and master_binding.master_relation_name=\'grouped_products_all\' 
						and master_binding.session_key=?
				)');
			else
				$filter = sprintf($filter, '');

			/*
			 * Include all commited grouped products of this master product
			 */

			if ($product->product_id) 
				$filter .= ' or (shop_products.product_id is not null and shop_products.product_id='.$product->product_id.')';
				
			$filter = '('.$filter.')';

			$checker->where($filter, $deferred_session_key);
		}

		public function before_delete($id=null)
		{
			$bind = array(
				'id'=>$this->id
			);

			$count = Db_DbHelper::scalar('select count(*) from shop_order_items, shop_orders where shop_product_id=:id and shop_orders.id = shop_order_items.shop_order_id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete product because there are orders referring to it.');
				
			$count = Db_DbHelper::scalar('select count(*) from shop_order_items, shop_products, shop_orders where shop_product_id=shop_products.id and shop_products.grouped is not null and shop_products.grouped=1 and shop_products.product_id=:id and shop_orders.id = shop_order_items.shop_order_id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete product because there are orders referring to its grouped products.');
		}
		
		public function after_save()
		{
			if (!$this->grouped)
			{
				Shop_CatalogPriceRule::apply_price_rules($this->id);

				$grouped_ids = Db_DbHelper::queryArray('select id from shop_products where grouped=1 and product_id=:id', array('id'=>$this->id));
				foreach ($grouped_ids as $grouped_id)
					Shop_CatalogPriceRule::apply_price_rules($this->id);
			}
			
			self::update_total_stock_value($this);
		}

		public function after_delete()
		{
		 	$files = Db_File::create()->where('master_object_class=?', get_class($this))->where('master_object_id=?', $this->id)->find_all();
		 	foreach ($files as $file)
		 		$file->delete();
		
			Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_products_customgroups where shop_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_related_products where master_product_id=:id or related_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_customer_cart_items where product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_product_price_index where pi_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_product_reviews where prv_product_id=:id', array('id'=>$this->id));
		}
		
		public function after_create() 
		{
			if ($this->grouped_sort_order != -1)
			{
				Db_DbHelper::query('update shop_products set grouped_sort_order=:grouped_sort_order where id=:id', array(
					'grouped_sort_order'=>$this->id,
					'id'=>$this->id
				));

				$this->grouped_sort_order = $this->id;
			}
		}

		public function get_manufacturer_link_options($key = -1)
		{
			if ($key != -1)
			{
				if (strlen($key))
				{
					$manufacturer = Shop_Manufacturer::create()->find($key);
					if ($manufacturer)
						return $manufacturer->name;
				} 
				
				return null;
			}
			
			$options = array();
			$options[0] = '<select>';
			$options[-1] = '<create new manufacturer>';

			$manufacturers = Db_DbHelper::objectArray('select * from shop_manufacturers order by name');
			foreach ($manufacturers as $manufacturer)
				$options[$manufacturer->id] = $manufacturer->name;

			return $options;
		}

		public function copy_properties($obj, $session_key, $this_session_key)
		{
			$images = $this->list_related_records_deferred('images', $this_session_key);
			foreach ($images as $image)
			{
				$image_copy = $image->copy();
				$image_copy->master_object_class = get_class($obj);
				$image_copy->field = $image->field;
				$image_copy->save();
				$obj->images->add($image_copy, $session_key);
			}

			$files = $this->list_related_records_deferred('files', $this_session_key);
			foreach ($files as $file)
			{
				$file_copy = $file->copy();
				$file_copy->master_object_class = get_class($obj);
				$file_copy->field = $file->field;
				$file_copy->save();
				$obj->files->add($file_copy, $session_key);
			}

			/*
			 * Copy options
			 */
			
			$options = $this->list_related_records_deferred('options', $this_session_key);
			foreach ($options as $attribute)
			{
				$attribute_copy = $attribute->copy();
				$attribute_copy->save();
				$obj->options->add($attribute_copy, $session_key);
			}
			
			/*
			 * Copy properties
			 */
			
			$properties = $this->list_related_records_deferred('properties', $this_session_key);
			foreach ($properties as $property)
			{
				$property_copy = $property->copy();
				$property_copy->save();
				$obj->properties->add($property_copy, $session_key);
			}
			
			/*
			 * Copy price tiers
			 */

			$tiers = $this->list_related_records_deferred('price_tiers', $this_session_key);
			foreach ($tiers as $tier)
			{
				$tier_copy = $tier->copy();
				$tier_copy->save();
				$obj->price_tiers->add($tier_copy, $session_key);
			}
			
			/*
			 * Copy extra options
			 */
			
			$extras = $this->list_related_records_deferred('product_extra_options', $this_session_key);
			foreach ($extras as $extra)
			{
				$extra_copy = $extra->copy();
				$extra_copy->save();
				$obj->product_extra_options->add($extra_copy, $session_key);
			}
			
			$exta_options_sets = $this->list_related_records_deferred('extra_option_sets', $this_session_key);
			foreach ($exta_options_sets as $extra_option_set)
			{
				if (is_object($obj->extra_option_sets))
					$obj->extra_option_sets->add($extra_option_set, $session_key);
				else
					$obj->extra_option_sets[] = $extra_option_set;
			}
			
			// $extra_sets = $this->list_related_records_deferred('extra_option_sets', $this_session_key);
			// foreach ($extra_sets as $set)
			// 	$obj->bind('extra_option_sets', $set, $session_key);
			
			return $obj;
		}
		
		public function list_copy_properties()
		{
			return array(
				'name'=>'Name',
				'title'=>'Title',
				'enabled'=>'Enabled',
				'short_description'=>'Short Description',
				'description'=>'Long Description',
				'shipping_dimensions'=>'Dimensions and weight',
				'meta'=>'META information',
				'images'=>'Images',
				'files'=>'Downloadable Files',
				'price'=>'Base Price',
				'cost'=>'Cost',
				'tier_price'=>'Tier price',
				'on_sale' => 'On Sale',
				'sale_price_or_discount' => 'Sale Price or Discount',
				'in_stock'=>'Units in stock',
				'inventory_settings'=>'Inventory Tracking Settings',
				'expected_availability_date'=>'Expected Availability Date',
				'options'=>'Product options',
				'extras'=>'Product extras',
				'attributes'=>'Product attributes'
			);
		}
		
		public function copy_properties_to_grouped($edit_session_key, $product_ids, $properties, $post_data = null)
		{
			foreach ($product_ids as $product_id)
			{
				if (!strlen($product_id))
					continue;

				$product = Shop_Product::create()->find($product_id);
				if (!$product)
					continue;

				$product->define_form_fields('grouped');

				foreach ($properties as $property_id)
				{
					switch ($property_id)
					{
						case 'name' :
						case 'title' :
						case 'enabled' :
						case 'short_description' :
						case 'description' :
						case 'price' :
						case 'on_sale' :
						case 'sale_price_or_discount' :
						case 'cost' :
						case 'in_stock' :
							$product->$property_id = $this->$property_id;
						break;
						case 'meta' :
							$product->meta_description = $this->meta_description;
							$product->meta_keywords = $this->meta_keywords;
						break;
						case 'images' :
							$images = $this->list_related_records_deferred('images', $edit_session_key);
							foreach ($product->images as $image)
								$image->delete();

							foreach ($images as $image)
							{
								$image_copy = $image->copy();
								$image_copy->master_object_class = get_class($product);
								$image_copy->field = $image->field;
								$image_copy->save();
								$product->images->add($image_copy);
							}
						break;
						case 'shipping_dimensions' :
							$product->weight = $this->weight;
							$product->width = $this->width;
							$product->height = $this->height;
							$product->depth = $this->depth;
						break;
						case 'files' :
							$files = $this->list_related_records_deferred('files', $edit_session_key);
							foreach ($product->files as $file)
								$file->delete();

							foreach ($files as $file)
							{
								$file_copy = $file->copy();
								$file_copy->master_object_class = get_class($product);
								$file_copy->field = $file->field;
								$file_copy->save();
								$product->files->add($file_copy);
							}
						break;
						case 'tier_price' :
							$product->tier_prices_per_customer = $this->tier_prices_per_customer;
							$product->tier_price_compiled = $this->tier_price_compiled;
							
							$tiers = $this->list_related_records_deferred('price_tiers', $edit_session_key);
							foreach ($product->price_tiers as $tier)
								$tier->delete();
								
							foreach ($tiers as $tier)
							{
								$tier_copy = $tier->copy();
								$tier_copy->save();
								$product->price_tiers->add($tier_copy);
							}
						break;
						case 'inventory_settings' :
							$product->track_inventory = $this->track_inventory;
							$product->hide_if_out_of_stock = $this->hide_if_out_of_stock;
							$product->stock_alert_threshold = $this->stock_alert_threshold;
							$product->low_stock_threshold = $this->low_stock_threshold;
							$product->allow_pre_order = $this->allow_pre_order;
							$product->allow_negative_stock_values = $this->allow_negative_stock_values;
							
							if (!strlen($product->in_stock))
								$product->in_stock = 0;
						break;
						case 'expected_availability_date' :
							$product->expected_availability_date = $this->expected_availability_date;
						break;
						case 'options' :
							$options = $this->list_related_records_deferred('options', $edit_session_key);
							foreach ($product->options as $option)
								$option->delete();

							foreach ($options as $attribute)
							{
								$attribute_copy = $attribute->copy();
								$attribute_copy->save();
								$product->options->add($attribute_copy);
							}
						break;
						case 'extras' :
							$extras = $this->list_related_records_deferred('product_extra_options', $edit_session_key);
							foreach ($product->product_extra_options as $extra_option)
								$extra_option->delete();

							foreach ($extras as $extra)
							{
								$extra_copy = $extra->copy();
								$extra_copy->save();
								$product->product_extra_options->add($extra_copy);
							}

							$extras_sets = array();
							if ($post_data && is_array($post_data))
							{
								if (array_key_exists('Shop_Product', $post_data) && array_key_exists('extra_option_sets', $post_data['Shop_Product']))
									$extras_sets = $post_data['Shop_Product']['extra_option_sets'];
							} else
							{
								$extras_set_collection = $this->list_related_records_deferred('extra_option_sets', $edit_session_key);
								$extras_sets = $extras_set_collection->as_array('id');
							}
							
							$product->copy_extra_option_sets($extras_sets);
						break;
						case 'attributes' :
							$attributes = $this->list_related_records_deferred('properties', $edit_session_key);
							foreach ($product->properties as $property)
								$property->delete();

							foreach ($attributes as $property)
							{
								$property_copy = $property->copy();
								$property_copy->save();
								$product->properties->add($property_copy);
							}

						break;
					}
				}
				
				$product->save();
			}
		}
		
		public function ungroup($parent_product, $session_key = null, $categories = null)
		{
			Db_DbHelper::query('update shop_products set grouped_attribute_name=:grouped_attribute_name, grouped=null, product_id=null where id=:id', array('id'=>$this->id, 'grouped_attribute_name'=>$parent_product->grouped_attribute_name));
			
			if ($categories)
			{
				foreach ($categories as $category_id)
				{
				    $bind = array('shop_product_id'=>$this->id, 'shop_category_id'=>$category_id);
				    
				    Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:shop_product_id and shop_category_id=:shop_category_id', $bind);
					Db_DbHelper::query('insert into shop_products_categories(shop_product_id, shop_category_id) values (:shop_product_id, :shop_category_id)', $bind);
				}
			}

			if ($session_key)
				$obj = Db_DeferredBinding::reset_object_field_bindings($parent_product, $this, 'grouped_products_all', $session_key);

			Shop_Module::update_catalog_version();
		}
		
		public function duplicate_product($grouped_parent = null)
		{
			$copy = new self();
			$fields = $this->fields();
			
			$exclude_columns = array(
				'id',
				'created_user_id',
				'updated_user_id',
				'created_at',
				'updated_at'
			);
			
			$unique_columns = array(
				'url_name',
				'name',
				'sku'
			);

			/*
			 * Copy plain fields
			 */

			foreach ($fields as $column_name=>$column_desc)
			{
				if (in_array($column_name, $exclude_columns))
					continue;
					
				if (array_key_exists($column_name, $this->has_models))
					continue;
					
				$column_value = $this->$column_name;
				if ($column_name == 'enabled')
					$column_value = 0;
					
				if (in_array($column_name, $unique_columns))
					$column_value = Db_DbHelper::getUniqueColumnValue($copy, $column_name, $column_value);

				$copy->$column_name = $column_value;
			}

			/*
			 * Copy relations
			 */

			$context = $grouped_parent ? 'grouped' : null;
			$copy->define_columns($context);
			$copy->define_form_fields($context);

			$this->copy_properties($copy, null, null);

			/*
			 * Copy categories
			 */

			if (!$this->grouped)
				$copy->categories = $this->categories->as_array('id', 'id');

			$copy->save();

			/*
			 * Copy grouped products
			 */

			if (!$grouped_parent)
			{
				foreach ($this->grouped_products_all as $grouped_product)
					$grouped_product->duplicate_product($copy);
			} else
			{
				Db_DbHelper::query('update shop_products set product_id = :parent_id where id= :id', array('parent_id' => $grouped_parent->id, 'id' => $copy->id));
			}
			
			/*
			 * Copy Option Matrix products
			 */
			
			Shop_OptionMatrixRecord::copy_records_to_product($this, $copy);
			
			Backend::$events->fireEvent('shop:onProductDuplicated', $this, $copy->id);
			
			return $copy;
		}

		public function __get($name)
		{
			/*
			 * Process properties of grouped products
			 */
		
			if ($name == 'grouped_products')
				return $this->eval_grouped_product_list();
			
			if ($name == 'grouped_menu_label')
			{
				if ($this->grouped)
					return $this->master_grouped_product->grouped_attribute_name;
					
				return $this->grouped_attribute_name;
			}
			
			if ($name == 'manufacturer')
			{
				if ($this->grouped)
					return $this->master_grouped_product->manufacturer_link;
				else
					return $this->manufacturer_link;
					
				return $this->grouped_attribute_name;
			}
			
			if ($name == 'bundle_items')
			{
				if ($this->grouped)
					return $this->master_grouped_product->bundle_items_link;
				else
					return $this->bundle_items_link;
			}

			if ($name == 'category_list')
			{
				if ($this->grouped)
					return $this->master_grouped_product->categories;
					
				return $this->categories;
			}

			if ($name == 'master_grouped_product_id')
			{
				if ($this->grouped)
					return $this->product_id;
					
				return $this->id;
			}
			
			if ($name == 'related_products')
			{
				if ($this->grouped)
					return $this->master_grouped_product->related_product_list;

				return $this->related_product_list;
			}
			
			if ($name == 'master_grouped_option_desc')
			{
				if ($this->grouped)
					return $this->master_grouped_product->grouped_option_desc;

				return $this->grouped_option_desc;
			}
			
			if ($name == 'rating_approved')
			{
				$result = 0;
				
				if ($this->grouped)
					$result = $this->master_grouped_product->product_rating;
				else
					$result = $this->product_rating;
					
				return round(($result*2), 0)/2;
			}
			
			if ($name == 'rating_review_num')
			{
				$result = 0;
				
				if ($this->grouped)
					return $this->master_grouped_product->product_rating_review_num;

				return $this->product_rating_review_num;
			}
			
			if ($name == 'rating_all')
			{
				$result = 0;
				
				if ($this->grouped)
					$result = $this->master_grouped_product->product_rating_all;
				else
					$result = $this->product_rating_all;
					
				return round(($result*2), 0)/2;
			}
			
			if ($name == 'rating_all_review_num')
			{
				$result = 0;
				
				if ($this->grouped)
					return $this->master_grouped_product->product_rating_all_review_num;

				return $this->product_rating_all_review_num;
			}
			
			if ($name == 'options')
			{
				$options = parent::__get($name);
				foreach ($options as $option)
					$option->parent_product = $this;

				return $options;
			}
			
			if ($name == 'extra_options')
				return $this->get_extra_options_merged();
			
			return parent::__get($name);
		}

		public static function set_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			$result = -1;

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				if ($id == -1)
					$result = $order;

				Db_DbHelper::query('update shop_products set grouped_sort_order=:grouped_sort_order where id=:id', array(
					'grouped_sort_order'=>$order,
					'id'=>$id
				));
			}

			return $result;
		}
		
		public static function update_page_reference($parent_product)
		{
			Db_DbHelper::query('update shop_products set page_id=:page_id where product_id is not null and product_id=:product_id', array(
				'page_id'=>$parent_product->page_id,
				'product_id'=>$parent_product->id
			));
		}
		
		public static function set_product_units_in_stock($product_id, $value)
		{
			Db_DbHelper::query('update shop_products set in_stock=:value where id=:id', array(
				'value'=>$value,
				'id'=>$product_id
			));
			
			self::update_total_stock_value($product_id);
		}

		/**
		 * Hides disabled products. Call this method before you call the find() or find_all() methods
		 */
		
		/**
		 * Hides disabled products from a product list. 
		 * Call this method before calling the {@link Db_ActiveRecord::find() find()} or 
		 * {@link Db_ActiveRecord::find_all() find_all()} method. This method allows to
		 * fetch custom product lists from the database, safely that disabled products are not displayed. 
		 * Usage example: 
		 * <pre>
		 * <?
		 *   $products = Shop_Product::create()->apply_visibility();
		 * 
		 *   $this->render_partial('shop:product_list', array(
		 *   'products'=>$products,
		 *   'paginate'=>false
		 *   ));
		 * ?>
		 * </pre>
		 * @documentable
		 * @see Shop_Product::apply_catalog_visibility() apply_catalog_visibility()
		 * @see Shop_Product::apply_customer_group_visibility() apply_customer_group_visibility()
		 * @see Shop_Product::apply_availability() apply_availability()
		 * @see Shop_Product::apply_filters() apply_filters()
		 * @return Shop_Product Returns the {@link Shop_Product} object.
		 */
		public function apply_visibility()
		{
			$this->where('enabled=1 and (disable_completely is null or disable_completely=0)');
			return $this;
		}

		/**
		 * Applied customer group filter.
		 * Customer group filter can be configured on the Create/Edit Product form in the Administration Area.
		 * Call this method before calling the {@link Db_ActiveRecord::find() find()} or 
		 * {@link Db_ActiveRecord::find_all() find_all()} method. 
		 * Usage example: 
		 * <pre>
		 * <?
		 *   $products = Shop_Product::create()->apply_customer_group_visibility();
		 * 
		 *   $this->render_partial('shop:product_list', array(
		 *   'products'=>$products,
		 *   'paginate'=>false
		 *   ));
		 * ?>
		 * </pre>
		 * @documentable
		 * @see Shop_Product::apply_visibility() apply_visibility()
		 * @see Shop_Product::apply_catalog_visibility() apply_catalog_visibility()
		 * @see Shop_Product::apply_availability() apply_availability()
		 * @see Shop_Product::apply_filters() apply_filters()
		 * @return Shop_Product Returns the {@link Shop_Product} object.
		 */
		public function apply_customer_group_visibility()
		{
			$customer_group_id = Cms_Controller::get_customer_group_id();
			$this->where('
				((shop_products.enable_customer_group_filter is null or shop_products.enable_customer_group_filter=0) or (
					shop_products.enable_customer_group_filter = 1 and
					exists(select * from shop_products_customer_groups where shop_product_id=shop_products.id and customer_group_id=?)
				))
			', $customer_group_id);
			return $this;
		}

		/**
		 * Hides products which should not be visible in the catalog.
		 * Call this method before you call the find() or find_all() methods.
		 */
		
		/**
		 * Applied customer group filter.
		 * Product catalog visibility can be configured on the Create/Edit Product form in the Administration Area.
		 * Call this method before calling the {@link Db_ActiveRecord::find() find()} or 
		 * {@link Db_ActiveRecord::find_all() find_all()} method. 
		 * Usage example: 
		 * <pre>
		 * <?
		 *   $products = Shop_Product::create()->apply_catalog_visibility();
		 * 
		 *   $this->render_partial('shop:product_list', array(
		 *   'products'=>$products,
		 *   'paginate'=>false
		 *   ));
		 * ?>
		 * </pre>
		 * @documentable
		 * @see Shop_Product::apply_visibility() apply_visibility()
		 * @see Shop_Product::apply_customer_group_visibility() apply_customer_group_visibility()
		 * @see Shop_Product::apply_availability() apply_availability()
		 * @see Shop_Product::apply_filters() apply_filters()
		 * @return Shop_Product Returns the {@link Shop_Product} object.
		 */
		public function apply_catalog_visibility()
		{
			$this->where('shop_products.visibility_catalog is not null and shop_products.visibility_catalog=1');
			return $this;
		}
		
		/**
		 * Applies product stock availability filter to a product list. 
		 * Call this method before calling the {@link Db_ActiveRecord::find() find()} or 
		 * {@link Db_ActiveRecord::find_all() find_all()} method. This method allows to
		 * fetch custom product lists from the database, safely that out of stock products are not displayed. 
		 * Usage example: 
		 * <pre>
		 * <?
		 *   $products = Shop_Product::create()->apply_availability();
		 * 
		 *   $this->render_partial('shop:product_list', array(
		 *   'products'=>$products,
		 *   'paginate'=>false
		 *   ));
		 * ?>
		 * </pre>
		 * @documentable
		 * @see Shop_Product::apply_visibility() apply_visibility()
		 * @see Shop_Product::apply_catalog_visibility() apply_catalog_visibility()
		 * @see Shop_Product::apply_customer_group_visibility() apply_customer_group_visibility()
		 * @see Shop_Product::apply_filters() apply_filters()
		 * @param boolean $group_products Determines whether grouped product should be returned separately, or resented with the base product.
		 * The default value is TRUE - return only base products.
		 * @return Shop_Product Returns the {@link Shop_Product} object.
		 */
		public function apply_availability($group_products = true)
		{
			if ($group_products)
			{
				$this->where('
					((shop_products.enabled=1 and (shop_products.grouped is null or shop_products.grouped=0) and not (
						shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock<=0))
						)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
						and not (
							grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.total_in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.total_in_stock<=0))
					)))');
			}
			else 
			{
				$this->where('
					((shop_products.enabled=1 and not (
						shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock<=0))
				)))');
			}
			
			return $this;
		}
		
		/**
		 * Applies product stock availability filter to a product list. 
		 * @documentable
		 * @deprecated Use {@link Shop_Product::apply_availability() apply_availability()} method instead.
		 * @return Shop_Product Returns the {@link Shop_Product} object.
		 */
		public function apply_avaliability()
		{
			return $this->apply_availability();
		}

		/**
		 * Applies visibility, availability and customer group filters to a product list. 
		 * Call this method before calling the {@link Db_ActiveRecord::find() find()} or 
		 * {@link Db_ActiveRecord::find_all() find_all()} method. This method allows to
		 * fetch custom product lists from the database, safely that disabled and hidden product are not displayed. 
		 * The example below fetches all active products from the database: 
		 * <pre>
		 * <?
		 *   $products = Shop_Product::create()->apply_filters();
		 * 
		 *   $this->render_partial('shop:product_list', array(
		 *   'products'=>$products,
		 *   'paginate'=>false
		 *   ));
		 * ?>
		 * </pre>
		 * @documentable
		 * @see Shop_Product::apply_visibility() apply_visibility()
		 * @see Shop_Product::apply_catalog_visibility() apply_catalog_visibility()
		 * @see Shop_Product::apply_customer_group_visibility() apply_customer_group_visibility()
		 * @see Shop_Product::apply_availability() apply_availability()
		 * @param boolean $group_products Determines whether grouped product should be returned separately, or resented with the base product.
		 * The default value is TRUE - return only base products.
		 * @return Shop_Product Returns the {@link Shop_Product} object.
		 */
		public function apply_filters($group_products = true)
		{
			return $this->apply_visibility()->apply_catalog_visibility()->apply_customer_group_visibility()->apply_availability($group_products);
		}

		/**
		 * Simplifies ordering products by their current price. 
		 * The following code example outputs all products sorted by price in reverse order: 
		 * <pre>
		 * <?
		 *   $products = Shop_Product::create()->apply_filters()->order_by_price('desc');
		 * 
		 *   $this->render_partial('shop:product_list', array(
		 *   'products'=>$products,
		 *   'paginate'=>false
		 *   ));
		 * ?>
		 * </pre>
		 * @documentable
		 * @param string $direction Specifies the ordering direction - <em>asc</em> or <em>desc</em>.
		 * @return Shop_Product Returns {@link Shop_Product} object.
		 */
		public function order_by_price($direction = 'asc')
		{
			if ($direction !== 'asc' && $direction != 'desc')
				$direction = 'asc';
			
			return $this->order(sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()).' '.$direction);
		}

		public function compile_tier_prices($session_key)
		{
			$tiers = $this->list_related_records_deferred('price_tiers', $session_key);
			$result = array();
			foreach ($tiers as $tier)
			{
				$tier_array = array();
				$tier_array['customer_group_id'] = $tier->customer_group_id;
				$tier_array['quantity'] = $tier->quantity;
				$tier_array['price'] = $tier->price;
				$tier_array['tier_id'] = $tier->id;
				$result[] = (object)$tier_array;
			}
			
			$this->tier_price_compiled = serialize($result);
		}
		
		public function list_tier_prices()
		{
			return Shop_TierPrice::list_tier_prices_from_string($this->tier_price_compiled, $this->name);
		}

		public function list_group_price_tiers($group_id)
		{
			return Shop_TierPrice::list_group_price_tiers($this->tier_price_compiled, $group_id, $this->name, $this->price);
		}
		
		/**
		 * Returns the product price, taking into account the tier price settings
		 * @param int $group_id Customer group identifier
		 * @param int $quantity Product quantity
		 */
		public function eval_tier_price($group_id, $quantity)
		{
			return Shop_TierPrice::eval_tier_price($this->tier_price_compiled, $group_id, $quantity, $this->name, $this->price);
		}
		
		/**
		 * This method used by the discount engine internally
		 */
		public function set_compiled_price_rules($price_rules, $rule_map)
		{
			$this->price_rules_compiled = serialize($price_rules);
			$this->price_rule_map_compiled = serialize($rule_map);
			Db_DbHelper::query('update shop_products set price_rules_compiled=:price_rules_compiled, price_rule_map_compiled=:price_rule_map_compiled where id=:id', array(
				'price_rules_compiled'=>$this->price_rules_compiled,
				'price_rule_map_compiled'=>$this->price_rule_map_compiled,
				'id'=>$this->id
			));
			
			$this->update_price_index();
		}
		
		/**
		 * This method used by the discount engine internally
		 */
		public function update_price_index()
		{
			Db_DbHelper::query('delete from shop_product_price_index where pi_product_id=:product_id', array('product_id'=>$this->id));

			$groups = Shop_CustomerGroup::list_groups();
			$test_om_record = Shop_OptionMatrixRecord::create();
			
			$om_records = Db_DbHelper::objectArray('
				select 
					on_sale,
					sale_price_or_discount,
					price_rules_compiled,
					tier_price_compiled,
					base_price
				from 
					shop_option_matrix_records
				where
					product_id=:product_id
					and (disabled is null or disabled=0)
			', array('product_id'=>$this->id));

			$index_values = array();
			foreach ($groups as $group_id=>$group)
			{
				$price = $this->get_discounted_price_no_tax(1, $group_id);
				$min_price = null;
				$max_price = null;
				$is_on_sale = $price < $this->price_no_tax(1, $group_id);

				foreach ($om_records as $record_data)
				{
					$om_price = Shop_OptionMatrixRecord::get_sale_price_static($this, $test_om_record, $record_data, $group_id, true);
					
					if ($min_price === null)
						$min_price = $om_price;

					if ($max_price === null)
						$max_price = $om_price;
					
					$min_price = min($min_price, $om_price);
					$max_price = max($max_price, $om_price);
					
					if (!$is_on_sale)
						$is_on_sale = $om_price < Shop_OptionMatrixRecord::get_price_static($this, $test_om_record, $record_data, $group_id, true);
				}
				
				if (!strlen($min_price))
					$min_price = $price;

				if (!strlen($max_price))
					$max_price = $price;

				$index_values[] = array('group_id'=>$group_id, 'price'=>$price, 'min_price'=>$min_price, 'max_price'=>$max_price, 'is_on_sale'=>$is_on_sale);
			}
			
			if ($cnt = count($index_values))
			{
				$query = 'insert into shop_product_price_index(pi_product_id, pi_group_id, price, min_price, max_price, is_on_sale) values';
				foreach ($index_values as $index=>$values)
				{
					$query .= '('.
						$this->id.','.
						Db_DbHelper::escape($values['group_id']).','.
						Db_DbHelper::escape($values['price']).','.
						Db_DbHelper::escape($values['min_price']).','.
						Db_DbHelper::escape($values['max_price']).','.
						($values['is_on_sale'] ? 1 : 0).
					')';
					if ($index < $cnt-1)
						$query .= ',';
				}
				
				Db_DbHelper::query($query);
			}

			$this->price_index_data = null;
			$this->price_index_compiled = serialize($index_values);

			Db_DbHelper::query('update shop_products set price_index_compiled=:price_index_compiled where id=:id', array(
				'id'=>$this->id,
				'price_index_compiled'=>$this->price_index_compiled
			));
		}
		
		/** 
		 * Updates product rating fields. This method is called by the rating system internally.
		 */
		public static function update_rating_fields($product_id)
		{
			Db_DbHelper::query("update shop_products set 
				product_rating=(select avg(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id and prv_moderation_status=:approved_status),
				product_rating_all=(select avg(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id),
				product_rating_review_num=ifnull((select count(*) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id and prv_moderation_status=:approved_status), 0),
				product_rating_all_review_num=ifnull((select count(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id), 0)
				where shop_products.id = :product_id
			", array(
				'product_id'=>$product_id,
				'approved_status'=>Shop_ProductReview::status_approved
			));
		}

		/**
		 * Returns the product discounted price for the specified cart item quantity.
		 * If there are no price rules defined for the product and no sale price or discount specified, returns the product original price
		 * (taking into account tier prices)
		 */
		public function get_sale_price_no_tax($quantity, $customer_group_id = null)
		{
			if ($customer_group_id === null )
				$customer_group_id = Cms_Controller::get_customer_group_id();
				
			$api_result = Backend::$events->fireEvent('shop:onGetProductSalePrice', $this, $quantity, $customer_group_id);
			if (is_array($api_result))
			{
				foreach ($api_result as $value)
				{
					if ($value !== false)
						return $value;
				}
			}

			if($this->on_sale && strlen($this->sale_price_or_discount))
			{
				$price = $this->price_no_tax($quantity, $customer_group_id);
				return round(self::get_set_sale_price($price, $this->sale_price_or_discount), 4);
			}

			if (!strlen($this->price_rules_compiled))
				return $this->price_no_tax($quantity, $customer_group_id);

			$price_rules = array();
			try
			{
				$price_rules = unserialize($this->price_rules_compiled);
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error loading price rules for the "'.$this->name.'" product');
			}

			if (!array_key_exists($customer_group_id, $price_rules))
				return $this->price_no_tax($quantity, $customer_group_id);

			$price_tiers = $price_rules[$customer_group_id];
			$price_tiers = array_reverse($price_tiers, true);

			foreach ($price_tiers as $tier_quantity=>$price)
			{
				if ($tier_quantity <= $quantity)
					return round($price, 4);
			}

			return $this->price_no_tax($quantity, $customer_group_id);
		}
		
		/**
		* @deprecated use get_sale_price_no_tax instead
		*/
		public function get_discounted_price_no_tax($quantity, $customer_group_id = null)
		{
			return $this->get_sale_price_no_tax($quantity, $customer_group_id);
		}
		
		/**
		 * Returns the product discounted price for the specified cart item quantity, with taxes included
		 * If there are no price rules defined for the product, returns the product original price 
		 * (taking into account tier prices).
		 * Includes tax if the "Display catalog/cart prices including tax" option is enabled
		 */
		
		/**
		 * Returns the product's sale price. 
		 * If there are no {@link http://lemonstand.com/docs/catalog_level_price_rules catalog price rules} applied to the product and there
		 * is no sale price specified for the product, the method returns the product base price. 
		 * The price can include tax if the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} 
		 * option is enabled
		 * 
		 * If you need to get the base product price, without any catalog price rules applied, use the {@link Shop_Product::price() price()} method.
		 *
		 * The optional parameters can be used for evaluating a price for specific quantity and customer group. 
		 * If the <em>$customer_group_id</em> parameter is not specified, a currently logged in customer will be used.
		 * 
		 * The following example outputs a product's base and sale prices:
		 * <pre>
		 * Price: <?= format_currency($product->price()) ?><br/>
		 * Sale Price: <?= format_currency($product->get_sale_price(1)) ?>
		 * </pre>
		 * @documentable
		 * @see format_currency()
		 * @see Shop_Product::price() price()
		 * @param integer $quantity Specifies the product quantity. Quantity can affect the price in case if product uses tier pricing.
		 * @param integer $customer_group_id Specifies an identifier of a {@link Shop_CustomerGroup customer group}.
		 * @return float Returns the product sale price.
		 */
		public function get_sale_price($quantity = 1, $customer_group_id = null)
		{
			$price = $this->get_sale_price_no_tax($quantity, $customer_group_id);
			
			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($this->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns the product's sale price.
		 * @documentable
		 * @deprecated Use the {@link Shop_Product::get_sale_price() get_sale_price()} method instead
		 * @param integer $quantity Specifies the product quantity. Quantity can affect the price in case if product uses tier pricing.
		 * @param integer $customer_group_id Specifies an identifier of a {@link Shop_CustomerGroup customer group}.
		 * @return float Returns the product sale price.
		 */
		public function get_discounted_price($quantity = 1, $customer_group_id = null)
		{
			return $this->get_sale_price($quantity, $customer_group_id);
		}
		
		/**
		 * Determines whether the product is on sale.
		 * Products considered on sale if there are any active catalog price rules affecting the product price,
		 * or if product's On Sale checkbox is checked.
		 * @documentable
		 * @return boolean Returns TRUE if the product is on sale. Returns FALSE otherwise.
		 */
		public function is_on_sale()
		{
			return $this->price_no_tax() <> $this->get_sale_price_no_tax(1);
		}
		
		/**
		 * Determines whether the product is on sale.
		 * Products considered on sale if there are any active {@link http://lemonstand.com/docs/catalog_level_price_rules catalog price rules} 
		 * affecting the product price, or if the product's On Sale checkbox is checked.
		 * @documentable
		 * @deprecated Use the {@link Shop_Product::is_on_sale() is_on_sale()} method instead.
		 * @return boolean Returns TRUE if the product is on sale. Returns FALSE otherwise.
		 */
		public function is_discounted()
		{
			return $this->is_on_sale();
		}
		
		/**
		 * Returns the difference between the product's regular price and sale price. 
		 * If there are no price rules applied to the product and no sale price or discount set, the method returns 0.
		 * @documentable
		 * @see Shop_Product::get_sale_price() get_sale_price()
		 * @see Shop_Product::price() price()
		 * @param integer $quantity Specifies the product quantity. Quantity can affect the price in case if product uses tier pricing.
		 * @param integer $customer_group_id Specifies an identifier of a {@link Shop_CustomerGroup customer group}.
		 * @return float Returns the sale price reduction. 
		 */
		public function get_sale_reduction($quantity, $customer_group_id = null)
		{
			$sale_price = $this->get_sale_price_no_tax($quantity, $customer_group_id);
			$original_price = $this->price_no_tax($quantity, $customer_group_id);

			return $original_price - $sale_price;
		}
		
		/**
		* @deprecated use get_sale_reduction instead
		*/
		public function get_discount($quantity, $customer_group_id = null)
		{
			return $this->get_sale_reduction($quantity, $customer_group_id);
		}

		public function before_save($deferred_session_key = null) 
		{
			$this->validate_shipping_cost();
			
			$this->compile_tier_prices($deferred_session_key);
			$this->pt_description = html_entity_decode(strip_tags($this->description), ENT_QUOTES, 'UTF-8');
			
			$this->perproduct_shipping_cost = serialize($this->perproduct_shipping_cost);
		}
		
		protected function after_fetch()
		{
			if(is_string($this->perproduct_shipping_cost) && strlen($this->perproduct_shipping_cost))
				$this->perproduct_shipping_cost = unserialize($this->perproduct_shipping_cost);
			Backend::$events->fireEvent('shop:onAfterProductRecordFetch', $this);
		}
		
		protected function custom_relation_save()
		{
			/*
			 * Preserve the Top Products sort orders
			 */

			$preserved_sort_orders = array();

			$has_bind = isset($this->changed_relations['bind']['categories']);
			$has_unbind = isset($this->changed_relations['unbind']['categories']);

			if ($has_unbind && $has_bind)
			{
				$unbind_categories = $this->changed_relations['unbind']['categories'];
				$unbind_keys = $unbind_categories['values'];
				
				$bind_data = array('product_id'=>$this->id, 'unbind_keys'=>$unbind_keys);

				if (count($unbind_keys))
				{
					$existing_records = Db_DbHelper::objectArray('select * from shop_products_categories where shop_product_id=:product_id and shop_category_id in (:unbind_keys)', $bind_data);

					foreach ($existing_records as $record)
						$preserved_sort_orders[$record->shop_category_id] = $record->product_category_sort_order;
						
					Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:product_id and shop_category_id in (:unbind_keys)', $bind_data);
				}

				unset($this->changed_relations['unbind']['categories']);
				
				$bind_categories = $this->changed_relations['bind']['categories'];
				$bind_keys = $bind_categories['values'];
				
				if (count($bind_keys))
				{
					foreach ($bind_keys as $category_id)
					{
						$sort_order = array_key_exists($category_id, $preserved_sort_orders) ? $preserved_sort_orders[$category_id] : null;
						
						$bind_data = array(
							'shop_product_id' => $this->id,
							'shop_category_id'=> $category_id, 
							'product_category_sort_order'=>$sort_order
						);
						Db_DbHelper::query('insert into shop_products_categories(shop_product_id, shop_category_id, product_category_sort_order) values (:shop_product_id, :shop_category_id, :product_category_sort_order)', $bind_data);
					}
				}

				unset($this->changed_relations['bind']['categories']);
			}
		}
		
		public function get_extra_options_merged()
		{
			$options = $this->product_extra_options->as_array();
			foreach ($this->extra_option_sets as $option_set)
			{
				foreach ($option_set->extra_options as $option)
				{
					$option->product_id = $this->id;
					$option->__lock();
					$options[] = $option;
				}
			}
			
			return new Db_DataCollection($options);
		}
		
		public function copy_extra_option_sets($sets)
		{
			Db_DbHelper::query('delete from shop_products_extra_sets where extra_product_id=:id', array('id'=>$this->id));
			
			foreach ($sets as $set_id)
			{
				Db_DbHelper::query('insert into shop_products_extra_sets(extra_product_id, extra_option_set_id) values (:extra_product_id, :extra_option_set_id)', array(
					'extra_product_id'=>$this->id,
					'extra_option_set_id'=>$set_id
				));
			}
		}

		/**
		 * Returns a list of attributes which can be used in price rule conditions
		 */
		public function get_condition_attributes()
		{
			$fields = array(
				'name',
				'description',
				'short_description',
				'price',
				'tax_class',
				'sku',
				'weight',
				'width',
				'height',
				'depth',
				'categories',
				'current_price',
				'manufacturer_link',
				'product_type'
			);

			$result = array();
			$definitions = $this->get_column_definitions();
			foreach ($fields as $field)
			{
				if (isset($definitions[$field]))
					$result[$field] = $definitions[$field]->displayName;
			}

			return $result;
		}

		/**
		 * Returns a list of grouped products, including the master product
		 */
		public function eval_grouped_product_list()
		{
			if ($this->grouped)
				$list = $this->master_grouped_product->grouped_product_list;
			else
				$list = $this->grouped_product_list;
				
			$master_product = $this->grouped ? $this->master_grouped_product : $this;
			if (!$master_product->enabled || ($master_product->is_out_of_stock() && $master_product->hide_if_out_of_stock))
				return $list;

			if (!strlen($master_product->grouped_attribute_name) || !strlen($master_product->grouped_option_desc))
				return $list;

			$array = $list->as_array();
			$result = array($master_product);
			foreach ($array as $obj)
				$result[] = $obj;

			usort($result, array('Shop_Product', 'sort_grouped_products'));

			return new Db_DataCollection($result);
		}
		
		public static function list_enabled_products()
		{
			$obj = Shop_Product::create();
			$obj->where('(shop_products.enabled=1) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1)');
			return $obj->where('grouped is null');
		}
		
		public static function sort_grouped_products($product_1, $product_2)
		{
			if ($product_1->grouped_sort_order == $product_2->grouped_sort_order)
				return 0;
				
			if ($product_1->grouped_sort_order > $product_2->grouped_sort_order)
				return 1;
				
			return -1;
		}
		
		public static function list_allowed_sort_columns()
		{
			$result = self::$allowed_sorting_columns;
			$custom_field_sets = Backend::$events->fireEvent('shop:onGetProductSortColumns');
			foreach ($custom_field_sets as $fields) 
			{
				foreach ($fields as $field)
					$result[] = $field;
			}
			
			return $result;
		}

		/**
		 * Inventory tracking
		 */
		
		public function decrease_stock($quantity, $om_record = null)
		{
			if (!$this->track_inventory)
				return;
				
			$use_om_record = $om_record && strlen($om_record->in_stock);
			
			if (!$use_om_record)
			{
				$in_stock = $this->in_stock - $quantity;
				if ($in_stock < 0 && !$this->allow_negative_stock_values)
					$in_stock = 0;

				$this->in_stock = $in_stock;
				
				Db_DbHelper::query('update shop_products set in_stock=:in_stock where id=:id', array(
					'in_stock'=>$this->in_stock,
					'id'=>$this->id
				));
			} else {
				$in_stock = $om_record->in_stock - $quantity;
				if ($in_stock < 0 && !$this->allow_negative_stock_values)
					$in_stock = 0;

				$om_record->in_stock = $in_stock;

				Db_DbHelper::query('update shop_option_matrix_records set in_stock=:in_stock where id=:id', array(
					'in_stock'=>$om_record->in_stock,
					'id'=>$om_record->id
				));
			}
			
			self::update_total_stock_value($this);
			$is_out_of_stock = $use_om_record ? $om_record->is_out_of_stock($this) : $this->is_out_of_stock();
			
			if ($is_out_of_stock)
			{
				Backend::$events->fireEvent('shop:onProductOutOfStock', $this, $om_record);
				
				$users = Users_User::create()->from('users', 'distinct users.*');
				$users->join('shop_roles', 'shop_roles.id=users.shop_role_id');
				$users->where('shop_roles.notified_on_out_of_stock is not null and shop_roles.notified_on_out_of_stock=1');
				$users->where('(users.status is null or users.status = 0)');
				$users = $users->find_all();
				
				$template = System_EmailTemplate::create()->find_by_code('shop:out_of_stock_internal');
				if (!$template)
					return;

				$product_url = Phpr::$request->getRootUrl().url('shop/products/edit/'.$this->master_grouped_product_id.'?'.uniqid());

				$message = $this->set_email_variables($template->content, $product_url, $om_record);
				$template->subject = $this->set_email_variables($template->subject, $product_url, $om_record);

				$template->send_to_team($users, $message);
			}
			else
			{
				$is_low_stock = $use_om_record ? $om_record->is_low_stock($this) : $this->is_low_stock();
				if($is_low_stock)
				{
					$users = Users_User::create()->from('users', 'distinct users.*');
					$users->join('shop_roles', 'shop_roles.id=users.shop_role_id');
					$users->where('shop_roles.notified_on_out_of_stock is not null and shop_roles.notified_on_out_of_stock=1');
					$users->where('(users.status is null or users.status = 0)');
					$users = $users->find_all();
					
					$template = System_EmailTemplate::create()->find_by_code('shop:low_stock_internal');
					if (!$template)
						return;

					$product_url = Phpr::$request->getRootUrl().url('shop/products/edit/'.$this->master_grouped_product_id.'?'.uniqid());

					$message = $this->set_email_variables($template->content, $product_url, $om_record);
					$template->subject = $this->set_email_variables($template->subject, $product_url, $om_record);

					$template->send_to_team($users, $message);
				}
			}
		}
		
		public function set_email_variables($message, $product_url, $om_record)
		{
			if (!$om_record)
			{
				$message = str_replace('{out_of_stock_product}', h($this->name), $message);
				$message = str_replace('{out_of_stock_sku}', h($this->sku), $message);
				$message = str_replace('{out_of_stock_count}', h($this->in_stock), $message);

				$message = str_replace('{low_stock_product}', h($this->name), $message);
				$message = str_replace('{low_stock_sku}', h($this->sku), $message);
				$message = str_replace('{low_stock_count}', h($this->in_stock), $message);

			} else {
				$message = str_replace('{out_of_stock_product}', h($this->name).' ('.h($om_record->options_as_string()).')', $message);
				$message = str_replace('{out_of_stock_sku}', h($this->om('sku', $om_record)), $message);
				$message = str_replace('{out_of_stock_count}', h($this->om('in_stock', $om_record)), $message);

				$message = str_replace('{low_stock_product}', h($this->name).' ('.h($om_record->options_as_string()).')', $message);
				$message = str_replace('{low_stock_sku}', h($this->om('sku', $om_record)), $message);
				$message = str_replace('{low_stock_count}', h($this->om('in_stock', $om_record)), $message);
			}
			
			$message = str_replace('{out_of_stock_url}', $product_url, $message);
			$message = str_replace('{low_stock_url}', $product_url, $message);
			
			return $message;
		}

		/**
		 * Checks whether the product is out of stock.
		 * For products which use {@link http://lemonstand.com/docs/integrating_option_matrix Option Matrix} the base product
		 * is considered out of stock in case if all Option Matrix products are out of stock.
		 * @documentable
		 * @return boolean Returns TRUE if the product is out of stock. Returns FALSE otherwise.
		 */
		public function is_out_of_stock()
		{
			if (!$this->track_inventory)
				return false;

			if ($this->stock_alert_threshold !== null)
				return $this->total_in_stock <= $this->stock_alert_threshold;

			if ($this->total_in_stock <= 0)
			 	return true;

			return false;
		}

		/**
		 * Checks whether the product has reached its low stock threshold.
		 * For products which use {@link http://lemonstand.com/docs/integrating_option_matrix Option Matrix} the base product
		 * is considered to have reached the threshold when the sum of all Option Matrix stock is equal or lower
		 * than the low stock threshold.
		 * @documentable
		 * @return boolean Returns TRUE if the product is out of stock. Returns FALSE otherwise.
		 */
		public function is_low_stock()
		{
			if (!$this->track_inventory)
				return false;

			if ($this->low_stock_threshold !== null)
				return $this->total_in_stock <= $this->low_stock_threshold;

			return false;
		}
		
		/**
		 * Returns the total number of items in stock for the product and all its grouped products.
		 * @documentable
		 * @return integer Returns the total number of items in stock.
		 */
		public function in_stock_grouped()
		{
			$master_product_id = $this->product_id ? $this->product_id : $this->id;
				
			return Db_DbHelper::scalar(
				'select sum(ifnull(total_in_stock, 0)) from shop_products where id=:id or (product_id is not null and product_id=:id)', 
				array('id'=>$master_product_id));
		}
		
		public static function update_total_stock_value($product)
		{
			if (is_object($product))
			{
				$product->total_in_stock = $product->in_stock;
				$product->total_in_stock += Db_DbHelper::scalar('select sum(ifnull(in_stock, 0)) from shop_option_matrix_records where product_id=:product_id and (disabled is null or disabled=0)', array('product_id'=>$product->id));

				Db_DbHelper::query('update shop_products set total_in_stock=:total_in_stock where id=:id', array(
					'total_in_stock'=>$product->total_in_stock,
					'id'=>$product->id
				));
			} else {
				Db_DbHelper::query('update 
					shop_products 
						set total_in_stock=ifnull(in_stock, 0) + 
							ifnull((select sum(ifnull(in_stock, 0)) 
								from 
							shop_option_matrix_records 
								where product_id=shop_products.id
								and (shop_option_matrix_records.disabled is null 
									or shop_option_matrix_records.disabled=0)
							), 0)
						where shop_products.id=:id', array(
					'id'=>$product
				));
			}
		}

		/*
		 * Product CSV import/export functions
		 */
		
		public function get_csv_import_columns($import = true)
		{
			$columns = $this->get_column_definitions();
			
			$columns['price']->displayName = 'Price';
			$columns['product_type']->listTitle = 'Product Type';
			
			if (!Phpr::$config->get('DISABLE_GROUPED_PRODUCTS'))
			{
				$columns['grouped_attribute_name']->displayName = 'Grouped - Attribute Name';
				$columns['grouped_option_desc']->displayName = 'Grouped - Product Description';
			}
			
			$columns['tier_prices_per_customer']->displayName = 'Price Tiers - Take into account previous orders';

			unset(
				$columns['image'],
				$columns['grouped_name'],
				$columns['page'],
				$columns['grouped_products_all'],
				$columns['related_products_all'],
				$columns['properties'],
				$columns['created_at'],
				$columns['created_user_name'],
				$columns['updated_at'],
				$columns['updated_user_name'],
				$columns['current_price'],
				$columns['customer_groups'],
				$columns['enable_customer_group_filter'],
				$columns['items_ordered'],
				$columns['product_rating'],
				$columns['product_rating_all'],
				$columns['total_in_stock']
			);
			
			if (Phpr::$config->get('DISABLE_GROUPED_PRODUCTS'))
			{
				unset($columns['csv_import_parent_sku']);
				unset($columns['grouped_sort_order']);
			}

			$rules = $this->validation->getRule('categories');
			if ($rules)
				$rules->required = false;
			$rules1 = $this->validation->getRule('name');
			if ($rules1)
				$rules1->required = false;
			$rules2 = $this->validation->getRule('price');
			if ($rules2)
				$rules2->required = false;
			
			/*
			 * Add product attribute columns
			 */

			if ($import)
				$attributes = Shop_PropertySetProperty::create()->order('name')->find_all();
			else
				$attributes = Shop_ProductProperty::create()->order('name')->find_all();

			foreach ($attributes as $attribute)
			{
				$column_display_name = 'ATTR: '.$attribute->name;
				$column_info = array(
					'dbName'=>$column_display_name, 
					'displayName'=>$column_display_name,
					'listTitle'=>$column_display_name,
					'type'=>db_text
				);
				$columns[$column_display_name] = (object)$column_info;
			}

			$column_info = array(
				'dbName'=>'product_groups', 
				'displayName'=>'Product groups',
				'listTitle'=>'Product groups',
				'type'=>db_text
			);
			$columns['product_groups'] = (object)$column_info;
			
			/*
			 * Add custom Option Matrix columns
			 */

			$record = Shop_OptionMatrixRecord::create();
			$record->define_columns();
			$record->define_form_fields();
			
			foreach ($record->api_columns as $id=>$config)
			{
				$column_title = isset($config['title']) ? $config['title'] : 'Untitled column';
				$column_title = 'Option Matrix - '.$column_title;
				
				$column_info = array(
					'dbName'=>$id, 
					'displayName'=>$column_title,
					'listTitle'=>$column_title,
					'type'=>db_text
				);
				
				$columns[$id] = (object)$column_info;
			}

			return $columns;
		}
		
		public function get_csv_import_columns_ls2()
		{
			$columns = $this->get_column_definitions();
			
			$column_info = array(
				'dbName'=>'product_variant_flag', 
				'displayName'=>'product_variant_flag',
				'listTitle'=>'product_variant_flag',
				'type'=>db_bool
			);
			$columns['product_variant_flag'] = (object)$column_info;
			
			// Taken from: http://docs.lemonstand.com/article/26-product-csv-import
			// 
			$allowed_columns = array(
				'name',
				'sku',
				'description',
				'short_description',
				'manufacturer_link' => 'manufacturer',
				'categories',
				'tax_class',
				'url_name',
				'price' => 'base_price',
				'cost',
				'depth',
				'width',
				'height',
				'weight',
				'track_inventory',
				'allow_pre_order' => 'allow_preorder',
				'in_stock' => 'in_stock_amount',
				'hide_if_out_of_stock' => 'hide_out_of_stock',
				'low_stock_threshold' => 'out_of_stock_threshold',
				'allow_negative_stock_values' => 'allow_negative_stock',
				'product_type',
				'images',
				'options',
				'csv_import_parent_sku' => 'product_variant_parent_sku',
				'sale_price_or_discount',
				'on_sale' => 'is_on_sale'
			);
			
			if (!Phpr::$config->get('DISABLE_GROUPED_PRODUCTS'))
			{
				$allowed_columns[] = 'product_variant_flag';
			}
			
			$dont_unset = array();
			
			foreach ($allowed_columns as $idx=>$col)
			{
				$name = $col;
				$new_name = $name;
				if (!is_numeric($idx))
				{
					$name = $idx;
					$new_name = $col;
				}
				
				$dont_unset[] = $name;
				if (!isset($columns[$name]))
					throw new Exception("Column '{$name}' does not exist");
				
				$columns[$name]->listTitle = $new_name;
			}
			
			foreach ($columns as $idx=>$col)
			{
				if (!in_array($idx, $dont_unset))
					unset($columns[$idx]);
			}
			
			$record = Shop_OptionMatrixRecord::create();
			$record->define_columns();
			$record->define_form_fields();
			
			return $columns;
		}
		
		public static function generate_unique_url_name($name)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			
			$url_name = preg_replace('/[^a-z0-9]/i', $separator, $name);
			$url_name = str_replace($separator.$separator, $separator, $url_name);
			if (substr($url_name, -1) == $separator)
				$url_name = substr($url_name, 0, -1);
				
			$url_name = trim(mb_strtolower($url_name));

			$orig_url_name = $url_name;
			$counter = 1;
			while (Db_DbHelper::scalar('select count(*) from shop_products where url_name=:url_name', array('url_name'=>$url_name)))
			{
				$url_name = $orig_url_name.$separator.$counter;
				$counter++;
			}
			
			return $url_name;
		}
		
		/*
		 * Per-product shipping cost
		 */
		
		protected function get_ppsc_country_list()
		{
			$countries = Shop_Country::get_object_list();
			$result = array();
			$result[] = '* - Any country||*';
			foreach ($countries as $country)
				$result[] = $country->code.' - '.$country->name.'||'.$country->code;

			return $result;
		}
		
		protected function get_ppsc_state_list()
		{
			$result = array(
				'*'=>array('* - Any state||*')
			);

			$states = Db_DbHelper::objectArray('select shop_states.code as state_code, shop_states.name, shop_countries.code as country_code
				from shop_states, shop_countries 
				where shop_states.country_id = shop_countries.id
				order by shop_countries.code, shop_states.name');

			foreach ($states as $state)
			{
				if (!array_key_exists($state->country_code, $result))
					$result[$state->country_code] = array('* - Any state||*');

				$result[$state->country_code][] = $state->state_code.' - '.$state->name.'||'.$state->state_code;
			}

			$countries = Shop_Country::get_object_list();
			foreach ($countries as $country)
			{
				if (!array_key_exists($country->code, $result))
					$result[$country->code] = array('* - Any state||*');
			}

			return $result;
		}
		
		protected function validate_shipping_cost()
		{
			if (!$this->enable_perproduct_shipping_cost)
				return;
			
			if (!is_array($this->perproduct_shipping_cost) || !count($this->perproduct_shipping_cost))
				$this->field_error('perproduct_shipping_cost', 'Please specify shipping cost or disable the Per-Product Shipping Cost feature.');

			/*
			 * Preload countries and states
			 */

			$db_country_codes = Db_DbHelper::objectArray('select * from shop_countries order by code');
			$countries = array();
			foreach ($db_country_codes as $country)
				$countries[$country->code] = $country;
			
			$country_codes = array_merge(array('*'), array_keys($countries));
			$db_states = Db_DbHelper::objectArray('select * from shop_states order by code');
			
			$states = array();
			foreach ($db_states as $state)
			{
				if (!array_key_exists($state->country_id, $states))
					$states[$state->country_id] = array('*'=>null);

				$states[$state->country_id][mb_strtoupper($state->code)] = $state;
			}
			
			foreach ($countries as $country)
			{
				if (!array_key_exists($country->id, $states))
					$states[$country->id] = array('*'=>null);
			}

			/*
			 * Validate table rows
			 */

			$processed_locations = array();
			foreach ($this->perproduct_shipping_cost as $row_index=>&$locations)
			{
				$empty = true;
				foreach ($locations as $value)
				{
					if (strlen(trim($value)))
					{
						$empty = false;
						break;
					}
				}

				if ($empty)
					continue;

				/*
				 * Validate country
				 */
				$country = $locations['country'] = trim(mb_strtoupper($locations['country']));

				if (!strlen($country))
					$this->field_error('perproduct_shipping_cost', 'Please specify country code. Valid codes are: '.implode(', ', $country_codes).'.', $row_index, 'country');
				
				if (!array_key_exists($country, $countries) && $country != '*')
					$this->field_error('perproduct_shipping_cost', 'Invalid country code. Valid codes are: '.implode(', ', $country_codes).'.', $row_index, 'country');
					
				/*
				 * Validate state
				 */
				if ($country != '*')
				{
					$country_obj = $countries[$country];
					$country_states = $states[$country_obj->id];
					$state_codes = array_keys($country_states);

					$state = $locations['state'] = trim(mb_strtoupper($locations['state']));
					if (!strlen($state))
						$this->field_error('perproduct_shipping_cost', 'Please specify state code. State codes, valid for '.$country_obj->name.' are: '.implode(', ', $state_codes).'.', $row_index, 'state');

					if (!in_array($state, $state_codes) && $state != '*')
						$this->field_error('perproduct_shipping_cost', 'Invalid state code. State codes, valid for '.$country_obj->name.' are: '.implode(', ', $state_codes).'.', $row_index, 'state');
				} else {
					$state = $locations['state'] = trim(mb_strtoupper($locations['state']));
					if (!strlen($state) || $state != '*')
						$this->field_error('perproduct_shipping_cost', 'Please specify state code as wildcard (*) to indicate "Any state" condition.', $row_index, 'state');
				}
				
				/*
				 * Process ZIP code
				 */
				
				$locations['zip'] = trim(mb_strtoupper($locations['zip']));

				$price = $locations['cost'] = trim(mb_strtoupper($locations['cost']));
				if (!strlen($price))
					$this->field_error('perproduct_shipping_cost', 'Please specify shipping cost', $row_index, 'cost');

			 	if (!Core_Number::is_valid($price))
					$this->field_error('perproduct_shipping_cost', 'Invalid numeric value in column Cost', $row_index, 'cost');

				$processed_locations[] = $locations;
			}

			if (!count($processed_locations))
				$this->field_error('perproduct_shipping_cost', 'Please specify shipping cost or disable the Per-Product Shipping Cost option.');
				
			$this->perproduct_shipping_cost = $processed_locations;
		}
		
		public function get_shipping_cost($country_id, $state_id, $zip)
		{
			if ($this->grouped)
			{
				if ($this->perproduct_shipping_cost_use_parent)
				{
					$enable_perproduct_shipping_cost = $this->master_grouped_product->enable_perproduct_shipping_cost;
					$perproduct_shipping_cost = $this->master_grouped_product->perproduct_shipping_cost;
				}
				else
				{
					$enable_perproduct_shipping_cost = $this->enable_perproduct_shipping_cost;
					$perproduct_shipping_cost = $this->perproduct_shipping_cost;
				}
			} else
			{
				$enable_perproduct_shipping_cost = $this->enable_perproduct_shipping_cost;
				$perproduct_shipping_cost = $this->perproduct_shipping_cost;
			}
				
			if (!$enable_perproduct_shipping_cost)
				return 0;
				
			if (!is_array($perproduct_shipping_cost) || !count($perproduct_shipping_cost))
				return 0;
			
			$country = Shop_Country::find_by_id($country_id);
			if (!$country)
				return 0;

			$state = null;
			if (strlen($state_id))
				$state = Shop_CountryState::find_by_id($state_id);
				
			$country_code = $country->code;
			$state_code = $state ? mb_strtoupper($state->code) : '*';

			/*
			 * Find shipping rate
			 */

			$rate = 0;

			foreach ($perproduct_shipping_cost as $row)
			{
				if ($row['country'] != $country_code && $row['country'] != '*')
					continue;
					
				if (mb_strtoupper($row['state']) != $state_code && $row['state'] != '*')
					continue;

				if ($row['zip'] != '' && $row['zip'] != '*')
				{
					$row['zip'] = str_replace(' ', '', $row['zip']);
					
					if ($row['zip'] != $zip)
					{
						if (mb_substr($row['zip'], -1) != '*')
							continue;
							
						$len = mb_strlen($row['zip'])-1;
							
						if (mb_substr($zip, 0, $len) != mb_substr($row['zip'], 0, $len))
							continue;
					}
				}
				
				$rate = $row['cost'];
				break;
			}

			return $rate;
		}

		protected function field_error($field, $message, $grid_row = null, $grid_column = null)
		{
			if ($grid_row != null)
			{
				$rule = $this->validation->getRule($field);
				if ($rule)
					$rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
			}
			
			$this->validation->setError($message, $field, true);
		}
		
		protected function decompile_price_index()
		{
			if ($this->price_index_data !== null)
				return $this->price_index_data;

			if (!strlen($this->price_index_compiled))
				return $this->price_index_data = array();

			try
			{
				return $this->price_index_data = unserialize($this->price_index_compiled);
			} catch (exception $ex)
			{
				return $this->price_index_data = array();
			}
		}
		
		public function get_min_max_price($price_type, $customer_group_id = null)
		{
			$index_data = $this->decompile_price_index();

			if (!$index_data)
				return $this->price($customer_group_id);
				
			if ($customer_group_id === null)
				$customer_group_id = Cms_Controller::get_customer_group_id();

			foreach ($index_data as $index_record)
			{
				if ($index_record['group_id'] == $customer_group_id)
				{
					$price = $index_record[$price_type];
					if (!strlen($price))
						return $this->price($customer_group_id);
					
					$include_tax = Shop_CheckoutData::display_prices_incl_tax();
					if (!$include_tax)
						return $price;

					return Shop_TaxClass::get_total_tax($this->tax_class_id, $price) + $price;
				}
			}
		
			return $this->price($customer_group_id);
		}

		/*
		 * Interface methods
		 */

		/*
		 * Returns product price. Use this method instead of accessing the price field directly
		 */
		public function price_no_tax($quantity = 1, $customer_group_id = null)
		{
			if ($customer_group_id === null)
				$customer_group_id = Cms_Controller::get_customer_group_id();

			$price = $this->eval_tier_price($customer_group_id, $quantity);
			$price_adjusted = Backend::$events->fire_event(array('name' => 'shop:onGetProductPriceNoTax', 'type'=>'filter'), array(
				'product' => $this,
				'price' => $price,
				'quantity' => $quantity,
				'customer_group_id' => $customer_group_id
				));
			return ($price_adjusted['price']) ? $price_adjusted['price'] : $price;
		}

		/**
		 * Returns the product's base price. 
		 * The price depends on price tiers and does not depend on {@link http://lemonstand.com/docs/catalog_level_price_rules catalog price rules}. 
		 * If you need to get a price calculated with catalog price rules applied, use the {@link Shop_Product::get_sale_price() get_sale_price()} method.
		 * The price can include tax if the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} 
		 * option is enabled
		 *
		 * The optional parameters can be used for evaluating a base price for specific quantity and customer group. 
		 * If the <em>$customer_group_id</em> parameter is not specified, a currently logged in customer will be used.
		 * 
		 * The following example outputs a product's base and sale prices:
		 * <pre>
		 * Price: <?= format_currency($product->price()) ?><br/>
		 * Sale Price: <?= format_currency($product->get_sale_price(1)) ?>
		 * </pre>
		 * The product price can be affected by  {@link shop:onGetProductPriceNoTax} event handlers.
		 * @documentable
		 * @see format_currency()
		 * @see Shop_Product::get_sale_price() get_sale_price()
		 * @param integer $quantity Specifies the product quantity. Quantity can affect the price in case if product uses tier pricing.
		 * @param integer $customer_group_id Specifies an identifier of a {@link Shop_CustomerGroup customer group}.
		 * @return float Returns the product base price.
		 */
		public function price($quantity = 1, $customer_group_id = null)
		{
			$price = $this->price_no_tax($quantity, $customer_group_id);

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($this->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns minimum product's sale price.
		 * The method returns the minimum price for a specific or current customer group, optionally taking into account 
		 * grouped products and always taking into account Option Matrix records. Pass TRUE to the <em>$include_grouped_products</em> parameter 
		* to include grouped product prices into the calculations. Pass a specific customer group identifier to the <em>$customer_group_id</em> parameter 
		 * if you want to get the maximum price for a specific customer group.
		 * @documentable
		 * @param boolean $include_grouped_products Determines whether grouped product prices
		 * should be considered.
		 * @param integer $customer_group_id Specifies a {@link Shop_CustomerGroup customer group} identifier.
		 * @return float Returns product's minimum price.
		 */
		public function min_price($include_grouped_products = false, $customer_group_id = null)
		{
			if (!$include_grouped_products)
				return $this->get_min_max_price('min_price', $customer_group_id);

			$products = $this->eval_grouped_product_list();
			$price = null;
			foreach ($products as $product)
			{
				$product_price = $product->get_min_max_price('min_price', $customer_group_id);
				if ($price === null)
					$price = $product_price;
					
				$price = min($price, $product_price);
			}
			
			if ($price === null)
				return $this->get_min_max_price('min_price', $customer_group_id);
				
			return $price;
		}

		/**
		 * Returns maximum product's sale price.
		 * The method returns the maximum price for a specific or current customer group, optionally taking into account 
		 * grouped products and always taking into account Option Matrix records. Pass TRUE to the <em>$include_grouped_products</em> parameter 
		* to include grouped product prices into the calculations. Pass a specific customer group identifier to the <em>$customer_group_id</em> parameter 
		 * if you want to get the maximum price for a specific customer group.
		 * @documentable
		 * @param boolean $include_grouped_products Determines whether grouped product prices
		 * should be considered.
		 * @param integer $customer_group_id Specifies a {@link Shop_CustomerGroup customer group} identifier.
		 * @return float Returns product's maximum price.
		 */
		public function max_price($include_grouped_products = false, $customer_group_id = null)
		{
			if (!$include_grouped_products)
				return $this->get_min_max_price('max_price', $customer_group_id);
				
			$products = $this->eval_grouped_product_list();
			$price = null;
			foreach ($products as $product)
			{
				$product_price = $product->get_min_max_price('max_price', $customer_group_id);
				if ($price === null)
					$price = $product_price;
					
				$price = max($price, $product_price);
			}
			
			if ($price === null)
				return $this->get_min_max_price('max_price', $customer_group_id);
				
			return $price;
		}

		/**
		 * Returns the product volume.
		 * @documentable
		 * @return float Returns the product volume.
		 */
		public function volume()
		{
			return $this->width*$this->height*$this->depth;
		}

		/**
		 * Returns a {@link http://lemonstand.com/docs/product_page/ product page} URL, based on the URL passed in the parameter. 
		 * Use this method to create links to products. If there is no custom page assigned to a product, the method just
		 * adds the product URL name to the base URL. So, if you passed the <em>product</em> string to the method, 
		 * it would return strings like <em>/product/red_mug</em> or <em>/product/apple_keyboard</em>. For products which have
		 * a custom page assigned and no URL Name assigned, the method returns the custom page URL. For products with both a
		 * custom page and URL Name specified, the method returns the URL of the custom page plus the value of the URL 
		 * Name parameter: <em>/custom_page/url_name</em>. See the 
		 * {@link http://lemonstand.com/docs/displaying_a_list_of_products Displaying a list of products} article for the usage example.
		 * @documentable
		 * @see http://lemonstand.com/docs/product_page/ Product page
		 * @see http://lemonstand.com/docs/displaying_a_list_of_products Displaying a list of products
		 * @param string $base_url Specifies the base product page URL.
		 * @return string Returns the page page URL.
		 */
		public function page_url($default)
		{
			$page_url = Cms_PageReference::get_page_url($this, 'page_id', $this->page_url);
			
			$product_options = null;
			if ($this->om_options_preset && is_object($this->om_options_preset))
			{
				$options = $this->om_options_preset->get_options(false);
				$option_array = array();
				if ($options)
				{
					foreach ($options as $option_name=>$option_value)
						$option_array[] = 'product_options['.urlencode($option_name).']'.'='.urlencode($option_value);
						
					$product_options = '?'.implode('&', $option_array);
				}
			}

			if (!strlen($page_url))
				return root_url($default.'/'.$this->url_name).$product_options;
				
			if (!strlen($this->url_name))
				return root_url($page_url).$product_options;
				
			return root_url($page_url.'/'.$this->url_name).$product_options;
		}

		/**
		 * Returns an URL of a product image thumbnail.
		 * Use this method for displaying product images. The <em>$width</em> and <em>$height</em> parameters are thumbnail width and height correspondingly. 
		 * You can use exact integer values, or word <em>'auto'</em> for automatic image resizing. The <em>$as_jpeg</em> parameter allows you to generate 
		 * PNG images with transparency support. By default the parameter value is TRUE and the method generates a JPEG image. The <em>$params</em> 
		 * array allows to pass parameters to image processing modules (which handle the {@link core:onProcessImage} event). This method is proxiable.
		 * The following line of code outputs a thumbnail of the first product image. The thumbnail width is 100 pixels, and thumbnail height is calculated 
		 * by LemonStand to keep the original aspect ratio. 
		 * <pre><img src="<?= $product->image_url(0, 100, 'auto') ?>"/></pre>
		 * @documentable
		 * @see Db_File::getThumbnailPath()
		 * @param integer $index Specifies the zero-based image index.
		 * @param mixed $width Specifies the thumbnail width. Use the 'auto' word to scale image width proportionally. 
		 * @param mixed $height Specifies the thumbnail height. Use the 'auto' word to scale height width proportionally. 
		 * @param boolean $as_jpeg Determines whether JPEG or PNG image will be created. 
		 * @param array $params A list of parameters. 
		 * @return string Returns the image URL relative to the website root.
		 */
		public function image_url($index, $width, $height, $returnJpeg = true, $params = array('mode' => 'keep_ratio'))
		{
			if ($index < 0 || $index > $this->images->count-1)
				return null;

			return $this->images[$index]->getThumbnailPath($width, $height, $returnJpeg, $params);
		}

		/**
		 * Returns content of RSS feed representing a list of recently added products. 
		 * The <em>$default_product_url</em> parameter should contain an URL of a default {@link http://lemonstand.com/docs/product_page/ product page}
		 * (see the {@link Shop_Product::page_url() page_url()} method description). 
		 * @documentable
		 * @see http://lemonstand.com/docs/product_page/ Product page
		 * @param string $feed_name Specifies the feed name.
		 * @param string $feed_description Specifies the feed description.
		 * @param string $default_product_url Specifies the default product page ULR.
		 * @param integer $record_number Returns a number of items to return in the feed.
		 * @return string Returns the feed content string.
		 */
		public static function get_rss($feed_name, $feed_description, $default_product_url, $record_number = 20)
		{
			$products = Shop_Product::create();
			$products->order('created_at desc');
			$products->where('shop_products.enabled = 1');
			$products->where('(shop_products.grouped is null or shop_products.grouped=0)');
			$products = $products->limit($record_number)->find_all();
			
			$root_url = Phpr::$request->getRootUrl();
			$rss = new Core_Rss( $feed_name, $root_url, $feed_description, Phpr::$request->getCurrentUrl() );

			foreach ( $products as $product )
			{
				$product_url = $product->page_url($default_product_url);
				if(substr($product_url, 0, 1) != '/')
					$product_url = '/'.$product_url;

				$link = $root_url.$product_url;
				if(substr($link, -1) != '/')
					$link .= '/';

				$image = $product->image_url(0, 100, 'auto');
				if (strlen($image))
					$image = $root_url.$image;
					
				$body = $product->description;
				if ($image)
					$body .= '<p><img alt="" src="'.$image.'"/></p>';

				$rss->add_entry( $product->name,
					$link,
					$product->id,
					$product->created_at,
					$product->short_description,
					$product->created_at,
					'LemonStand',
					$body);
			}

			return $rss->to_xml();
		}

		/**
		 * Finds products by a search term and/or other options.
		 * The <em>$options</em> array can have the following elements: 
		 * <ul>
		 * <li><em>category_ids</em> - an array of category identifiers to limit the result with specific categories.</li>
		 * <li><em>manufacturer_ids</em> - an array of {@link Shop_Manufacturer manufacturer} identifiers to limit the result 
		 *   with specific manufacturers.</li>
		 * <li><em>options</em> - an array of product options as a name-value list. Example: <em>array('color'=>'black')</em>. 
		 *   You can use the <em>wildcard</em> character as an option value if you want to find all products with a specific option having any value.</li>
		 * <li><em>attributes</em> - an array of product attributes as a name-value list. Example: <em>array('paper format'=>'A4')</em>. 
		 *   If your products can have multiple attributes with a same name and you want to search by multiple attribute values, you 
		 *   can specify the attribute value as array: <em>array('paper format'=>array('A4', 'A5'))</em> - the method will find products 
		 *   with the <em>paper format</em> attribute having values of <em>A4</em> or <em>A5</em>. You can use the <em>wildcard</em> 
		 *  character for the attribute value if you want to find all products with a specific attribute having any value.</li>
		 * <li><em>custom_groups</em> - an array of custom product groups to limit the result with specific groups. </li>
		 * <li><em>min_price</em> - minimum product price.</li>
		 * <li><em>max_price</em> - maximum product price.</li>
		 * <li><em>sorting</em> - the product sorting expression, string. The following values are supported: <em>relevance</em>, 
		 *   <em>name</em>, <em>price</em>, <em>created_at</em>, <em>product_rating</em>, <em>product_rating_all</em>. 
		 *   The <em>relevance</em> value is the default sorting option. The product_rating value corresponds to the approved 
		 *   product rating, and the product_rating_all value corresponds to the full product rating (approved and not approved). 
		 *   All options (except the relevance) support the sorting direction expression - <em>asc</em> and <em>desc</em>, so you 
		 *   can use values like "price desc". You can extend the list of allowed sorting columns with the {@link shop:onGetProductSearchSortColumns} event.</li>
		 * </ul>
		 * Usage example: 
		 * <pre>
		 * // Create the pagination object, 10 records per page
		 * $pagination = new Phpr_Pagination(10); 
		 * 
		 * // Load the current page index from the URL
		 * $current_page = $this->request_param(0, 1);
		 * 
		 * $query = 'laptop';
		 * $options = array();
		 * $options['attributes'] = array('CPU'=>'2.33');
		 * $options['options'] = array('color'=>'black');
		 * $options['custom_groups'] = array('featured_products');
		 * 
		 * $products = Shop_Product::find_products($query, $pagination, $current_page, $options);
		 * </pre>
		 * By default the find_products() method uses soft comparison in the options and attributes search. This means that if you specify 
		 * an option or attribute value <em>large</em> and if there are products which have the option value <em>large frame</em>, those 
		 * product will be returned. You can enable strict search by adding the <em>exclamation</em> sign before the option or attribute value:
		 * <pre>
		 * $options['attributes'] = array('CPU'=>'!2.33');
		 * $options['options'] = array('color'=>'!black');
		 * </pre>
		 * Please note that the strict attribute search is more efficient and reliable than the strict option search. 
		 * The strict option search could work incorrectly if a product option value contains commas. 
		 * @documentable
		 * @see shop:onRegisterProductSearchEvent
		 * @see shop:onGetProductSearchSortColumns
		 * @see http://lemonstand.com/docs/creating_the_search_page Creating the Search page
		 * @param string $query Specifies the search query string. 
		 * @param Phpr_Pagination $pagination Specifies the pagination object.
		 * @param integer $page Specifies a current page index (1-based).
		 * @param array $options Specifies search options.
		 * @return Db_DataCollection Returns a collection of Shop_Product objects.
		 */
		public static function find_products($query, $pagination, $page=1, $options = array())
		{
			return Shop_ProductSearch::find_products($query, $pagination, $page, $options);
		}
		
		public static function eval_static_product_price($test_product, $product)
		{
			$test_product->price = $product->price;
			$test_product->price_rules_compiled = $product->price_rules_compiled;
			$test_product->tier_price_compiled = $product->tier_price_compiled;
			$test_product->tax_class_id = $product->tax_class_id;
			$test_product->on_sale = $product->on_sale;
			$test_product->sale_price_or_discount = $product->sale_price_or_discount;
			
			return $test_product->get_discounted_price();
		}
		
		public static function check_price_range($test_product, $product, $min_price, $max_price, $test_om_record)
		{
			$product_price = self::eval_static_product_price($test_product, $product);

			if ($product->om_data_fields)
				$product_price = Shop_OptionMatrixRecord::get_sale_price_static($test_product, $test_om_record, $product->om_data_fields);

			if (strlen($min_price) && $product_price < $min_price)
				return false;

			if (strlen($max_price) && $product_price > $max_price)
				return false;

			return true;
		}
		
		/**
		 * Returns a list of products on sale.
		 * @deprecated Use {@link Shop_Product::list_on_sale() list_on_sale()} method instead.
		 * @documentable
		 * @param array $options Specifies the method options.
		 * @return Shop_Product Returns an object of the {@link Shop_Product} class. 
		 */
		public static function list_discounted($options = array())
		{
			return self::list_on_sale($options);
		}

		/**
		 * Returns a list of products on sale.
		 * Products on sale include products with any {@link http://lemonstand.com/docs/catalog_level_price_rules catalog price rules} applied 
		 * or with a sale price or discount specified directly on the product configuration page. 
		 * The method returns an instance of the {@link Shop_Product} class. To obtain a collection of products call the 
		 * {@link Db_ActiveRecord::find_all() find_all()} method of the returned object. 
		 * The <em>$options</em> parameter allows to specify the sorting mode with the <em>sorting</em> element. This element should contain an array 
		 * of column names. The supported fields you can sort the products by are: 
		 * <ul>
		 *   <li><em>name</em> - sort the product list by name</li>
		 *   <li><em>price</em> - sort the product list by the base price</li>
		 *   <li><em>sku</em> - sort the product list by SKU</li>
		 *   <li><em>weight</em> - sort the product list by weight</li>
		 *   <li><em>width</em> - sort the product list by width</li>
		 *   <li><em>height</em> - sort the product list by height</li>
		 *   <li><em>depth</em> - sort the product list by depth</li>
		 *   <li><em>created_at</em> - sort the product list by the product creation date</li>
		 *   <li><em>rand()</em> - sort products randomly</li>
		 *   <li><em>manufacturer</em> -  sort products by the manufacturer name</li>
		 *   <li><em>expected_availability_date</em> - sort products by the availability date</li>
		 * </ul>
		 * You can add <em>desc</em> suffix to the sort column name to enable the descending sorting. For example, to sort the product list 
		 * by price in descending order. Example:
		 * <pre>$products = Shop_Product::list_on_sale(array('sorting'=>array('price desc')))->limit(10)->find_all();</pre>
		 * Another supported option is the <em>group_products</em>. It default value is TRUE and the method returns only base products.
		 * Pass FALSE to the option to include grouped products to the result.
		 * <pre>$products = Shop_Product::list_on_sale(array('group_products'=>false))->limit(10)->find_all();</pre>
		 * @documentable
		 * @see http://lemonstand.com/docs/displaying_products_on_sale Displaying products on sale
		 * @param array $options Specifies the method options.
		 * @return Shop_Product Returns an object of the {@link Shop_Product} class. 
		 */
		public static function list_on_sale($options = array())
		{
			$obj = self::create();
			
			$group_products = array_key_exists('group_products', $options) ? $options['group_products'] : true;
			
			$obj->apply_filters($group_products);
			$obj->where(sprintf(Shop_Product::$is_on_sale_query, Cms_Controller::get_customer_group_id()).' = 1');
			
			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array('name');

			if (!is_array($sorting))
				$sorting = array('name');

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));
				
				if (!in_array($test_name, Shop_Product::$allowed_sorting_columns))
					continue;
				
				if (strpos($sorting_column, 'price') !== false)
					$sorting_column = str_replace('price', sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()), $sorting_column);
				elseif(strpos($sorting_column, 'manufacturer') !== false)
					$sorting_column = str_replace('manufacturer', 'manufacturer_link_calculated', $sorting_column);
				elseif (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
					$sorting_column = 'shop_products.'.$sorting_column;
			}

			if (!$sorting)
				$sorting = array('name');

			$obj->reset_order();
			$sort_str = implode(', ', $sorting);
			$obj->order($sort_str);

			return $obj;
		}
		
		public static function set_search_query_params($template, $tables, $filter)
		{
			$result = str_replace('%TABLES%', $tables, $template);
			$result = str_replace('%FILTER%', $filter, $result);
			return $result;
		}
		
		public function visible_for_customer_group($group_id)
		{
			if (!$this->enable_customer_group_filter)
				return true;

			return Db_DbHelper::scalar('select count(*) from shop_products_customer_groups where shop_product_id=:product_id and customer_group_id=:group_id', array(
				'product_id'=>$this->id,
				'group_id'=>$group_id
			));
		}

		/**
		 * Returns a list of enabled, visible and available related products.
		 * The method returns the configured Shop_Product class. Call the {@link Db_ActiveRecord::find_all() find_all()} method of 
		 * the return object in order to obtain a {@link Db_DataCollection collection} of related products. 
		 * Usage example: 
		 * <pre>
		 * <?
		 *   $related_products = $product->list_related_products()->find_all();
		 *   if ($related_products->count):
		 * ?>
		 *   <h3>Related products</h3>
		 *   <? $this->render_partial('shop:product_list', array('products'=>$related_products)); ?>
		 * <? endif ?>
		 * </pre>
		 * @documentable
		 * @see http://lemonstand.com/docs/displaying_related_products/ Displaying related products
		 * @return Shop_Product Returns an object of the {@link Shop_Product} class.
		 */
		public function list_related_products()
		{
			if ($this->grouped)
				$product_obj = $this->master_grouped_product->related_product_list_list;
			else
				$product_obj = $this->related_product_list_list;
			
			$product_obj->apply_customer_group_visibility()->apply_catalog_visibility();

			return $product_obj;
		}

		/**
		 * Returns the full list of grouped products, including this product.
		 * The method ignores products stock availability and any visibility filters. 
		 * @documentable
		 * @return array Returns an array of Shop_Product objects.
		 */
		public function list_grouped_products()
		{
			$master_product = ($this->grouped) ? $this->master_grouped_product : $this;

			$all_grouped_products = array($master_product);
			foreach($master_product->grouped_products_all as $grouped_product)
			    $all_grouped_products[] = $grouped_product;

			usort($all_grouped_products, array('Shop_Product', 'sort_grouped_products'));

			return $all_grouped_products;
		}
		
		/**
		 * Returns a list of product extra options grouped by matching group names.
		 * The method returns a nested array. Indexes in the array represent group names. Values represent {@link Shop_ExtraOption extra option objects} 
		 * belonging to the group. Result example: <em>array('Group name 1'=>array(Shop_ExtraOption, Shop_ExtraOption), 'Group name 2'=>array(Shop_ExtraOption))</em>.
		 * @documentable 
		 * @see http://lemonstand.com/docs/displaying_product_extra_options/ Displaying product extra options
		 * @return array Returns an array of extra option group names and extra option objects ({@link Shop_ExtraOption}).
		 */
		public function list_extra_option_groups()
		{
			$extras = $this->extra_options;
			$groups = array();
			foreach ($extras as $extra)
			{
				if (!array_key_exists($extra->group_name, $groups))
					$groups[$extra->group_name] = array();
					
				$groups[$extra->group_name][] = $extra;
			}
			
			return $groups;
		}

		/**
		 * Returns a product attribute value by the attribute name. 
		 * @documentable
		 * @param string $name Specifies the attribute name.
		 * @return string Returns the attribute value. Returns NULL if the attribute is not found.
		 */
		public function get_attribute($name)
		{
			$name = mb_strtolower($name);
			$attribtues = $this->properties;
			foreach ($attribtues as $attribute)
			{
				if (mb_strtolower($attribute->name) == $name)
					return $attribute->value;
			}
			
			return null;
		}
		
		/**
		 * Returns a list of catalog price rules applied to the product. 
		 * Usage example: 
		 * <pre>
		 * <ul>
		 *   <? foreach ($product->list_applied_catalog_rules() as $rule): ?>
		 *     <li>
		 *       <?= h($rule->name) ?>
		 *       <?= h($rule->description) ?>
		 *     </li>
		 *   <? endforeach ?>
		 * </ul>
		 * </pre>
		 * @documentable
		 * @param integer $customer_group_id Specifies the {@link Shop_CustomerGroup customer group} identifier. 
		 * Omit this parameter to use a group of the currently logged in customer.
		 * @return array Returns an array of Shop_CatalogPriceRule objects.
		 */
		public function list_applied_catalog_rules($group_id = null)
		{
			if (!strlen($this->price_rule_map_compiled))
				return array();
			
			try
			{
				$rule_map = unserialize($this->price_rule_map_compiled);
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error loading price rule list for the "'.$this->name.'" product');
			}
			
			if ($group_id === null)
				$group_id = Cms_Controller::get_customer_group_id();
				
			if (!array_key_exists($group_id, $rule_map))
				return array();

			$result = array();
			foreach ($rule_map[$group_id] as $rule_id)
				$result[] = Shop_CatalogPriceRule::find_rule_by_id($rule_id);
				
			return $result;
		}
		
		/**
		 * Returns a list of categories the product belongs to.
		 * This method is more effective in terms of memory usage 
		 * than the Shop_Product::$categories and Shop_Product::$category_list fields.
		 * Use it when you need to load category lists for multiple products a time.
		 * @return Db_DataCollection
		 */
		public function list_categories()
		{
			if ($this->category_cache !== null)
				return $this->category_cache;

			$master_product_id = $this->grouped ? $this->product_id : $this->id;
			$category_ids = Db_DbHelper::scalarArray('select shop_category_id from shop_products_categories where shop_product_id=:id', array('id'=>$master_product_id));

			$this->category_cache = array();
			foreach ($category_ids as $category_id)
			{
				$category = Shop_Category::find_category($category_id, false);
				if ($category)
					$this->category_cache[] = $category;
			}
			
			$this->category_cache = new Db_DataCollection($this->category_cache);
			return $this->category_cache;
		}
		
		/**
		 * Returns a list of category identifiers the product belongs to.
		 * @return array
		 */
		public function list_category_ids()
		{
			if ($this->category_id_cache !== null)
				return $this->category_id_cache;

			$master_product_id = $this->grouped ? $this->product_id : $this->id;
			$category_ids = Db_DbHelper::scalarArray('select shop_category_id from shop_products_categories where shop_product_id=:id', array('id'=>$master_product_id));

			$this->category_id_cache = $category_ids;
			return $this->category_id_cache;
		}

		/**
		 * Returns a list of <em>all</em> reviews, including non-approved. 
		 * The returned collection includes reviews of all grouped products.
		 * @documentable
		 * @see Shop_Product::list_reviews() list_reviews()
		 * @return Db_DataCollection Returns a collection of {@link Shop_ProductReview} objects.
		 */
		public function list_all_reviews()
		{
			$product_id = $this->grouped ? $this->product_id : $this->id;
			
			return Shop_ProductReview::create()->where('prv_product_id=?', $product_id)->order('created_at')->find_all();
		}

		/**
		 * Returns a list of <em>approved</em> reviews. 
		 * The returned collection includes reviews of all grouped products.
		 * @documentable
		 * @see Shop_Product::list_all_reviews() list_all_reviews()
		 * @return Db_DataCollection Returns a collection of {@link Shop_ProductReview} objects.
		 */
		public function list_reviews()
		{
			$product_id = $this->grouped ? $this->product_id : $this->id;

			$obj = Shop_ProductReview::create()->where('prv_product_id=?', $product_id);
			$obj->where('prv_moderation_status=?', Shop_ProductReview::status_approved);
			return $obj->order('created_at')->find_all();
		}

		/**
		 * Returns a list of files uploaded by a customer. 
		 * This method is applicable only on the {@link http://lemonstand.com/docs/product_page Product Details} page. Please see
		 * {@link http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ Supporting file uploads on the product page} article for details.
		 * @documentable
		 * @see http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ Supporting file uploads on the product page
		 * @param string $session_key Specifies the form session key. If the key is not provided, the method uses a POSTed key value.
		 * @return Db_DataCollection Returns a collection of {@link Db_File} objects.
		 */
		public function list_uploaded_files($session_key = null)
		{
			if (!$session_key)
				$session_key = post('ls_session_key');
				
			return $this->list_related_records_deferred('uploaded_files', $session_key);
		}
		
		public function add_file_from_post($file_info, $session_key = null)
		{
			if (!$session_key)
				$session_key = post('ls_session_key');
				
			if (!array_key_exists('error', $file_info) || $file_info['error'] == UPLOAD_ERR_NO_FILE)
				return;
				
			Phpr_Files::validateUploadedFile($file_info);

			$file = Db_File::create();
			$file->is_public = false;

			$file->fromPost($file_info);
			$file->master_object_class = get_class($this);
			$file->field = 'uploaded_files';
			$file->save();
			
			Backend::$events->fireEvent('shop:onBeforeProductFileAdded', $this, $file);
			$this->uploaded_files->add($file, $session_key);
			Backend::$events->fireEvent('shop:onAfterProductFileAdded', $this, $file);
		}
		
		public function delete_uploaded_file($file_id, $session_key = null)
		{
			if (!$session_key)
				$session_key = post('ls_session_key');

			if (!strlen($file_id))
				return;
				
			if ($file = Db_File::create()->find($file_id))
				$this->uploaded_files->delete($file, $session_key);
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		public static function get_set_sale_price($original_price, $sale_price_or_discount)
		{
			if(!isset($sale_price_or_discount) || !strlen($sale_price_or_discount) || $error = self::is_sale_price_or_discount_invalid($sale_price_or_discount))
				return $original_price;
			
			$price = $original_price;
			$percentage_sign = strpos($sale_price_or_discount, '%');
			if($percentage_sign !== false)
			{
				if($percentage_sign == 0)
					$sale_discount = substr($sale_price_or_discount, 1);
				else
					$sale_discount = substr($sale_price_or_discount, 0, strlen($sale_price_or_discount)-1);
				$price = $original_price - $sale_discount*$original_price/100;
			}
			elseif(Core_Number::is_valid($sale_price_or_discount))
			{
				$price = min($sale_price_or_discount, $original_price);
			}
			elseif(preg_match('/^\-[0-9]*?\.?[0-9]*$/', $sale_price_or_discount))
			{
				$price = $original_price + $sale_price_or_discount;
			}
			
			return $price > 0 ? $price : 0;
		}
		
		/**
		* Checks the sale price or discount value and returns false when it's valid
		* Valid values are numbers (as the set sale price e.g. 5.5, 6, 12.54), negative numbers (as the set sale discount e.g. -5, -12.22) 
		* or percentages (as the discount percentage, values between 0% and 100%).
		* @param string sale price or discount to check
		* @param numeric product price, optional (when this parameter is supplied, function checks that the sale price or discount is not greater than the original price)
		* @return mixed, returns false when sale price or discount is valid or the error string when it's not.
		*/
		public static function is_sale_price_or_discount_invalid($value, $price=null)
		{
			$percentage_sign = strpos($value, '%');
			if($percentage_sign !== false)
			{
				if($percentage_sign == 0)
					$sale_discount = substr($value, 1);
				else
					$sale_discount = substr($value, 0, strlen($value)-1);
				//should be a number and less or equal to 100%
				if(!Core_Number::is_valid($sale_discount) || $sale_discount > 100)
					return 'Sale discount should be a valid number between 0% and 100%.';
			}
			else
			{
				if(!Core_Number::is_valid($value))
				{
					//if it's a negative number, it could be valid
					if(!preg_match('/^\-[0-9]*?\.?[0-9]*$/', $value))
						return 'Sale price or discount amount should be a valid number or percentage.';
					//if negative value, should be greater than price
					elseif($price && (-1*$value > $price))
						return 'Sale discount is greater than the product price.';
				}
				elseif($price && $value > $price)
					return 'Sale price is greater than the product price.';
			}
			return false;
		}
		
		/*
		 * Product options functions
		 */
		
		/**
		 * Checks whether an option with specified key and value exists in the product.
		 * @param string $option_key Specifies option key
		 * @param string $option_value Specifies option value
		 * @return boolean Returns TRUE if the specified option exists.
		 */
		public function option_value_exists($option_key, $option_value)
		{
			$option = $this->get_option_by_key($option_key);
			if (!$option)
				return false;
				
			return $option->value_exists($option_value);
		}
		
		/**
		 * Returns product option by option key.
		 * @param string $option_key Specifies option key.
		 * @return Shop_CustomAttribute Returns option object or NULL if 
		 * option with the specified key does not exist.
		 */
		public function get_option_by_key($option_key)
		{
			foreach ($this->options as $option)
			{
				if ($option->option_key == $option_key)
					return $option;
			}
			
			return null;
		}
		
		/*
		 * Option Matrix methods
		 */
		
		/**
		 * Sets Option Matrix options for subsequent om() method calls.
		 * This method used internally.
		 * @param mixed $options Specifies product option values or Shop_OptionMatrixRecord object.
		 * Options should be specified in the following format: 
		 * ['option_key_1'=>'option value 1', 'option_key_2'=>'option value 2']
		 * Option keys and values are case sensitive.
		 */
		public function set_om_options($options)
		{
			$this->om_options_preset = $options;
		}
		
		/**
		 * Returns true if the product has Option Matrix records.
		 * @documentable
		 * @return boolean
		 */
		public function has_om_records()
		{
			if ($this->has_om_records !== null)
				return $this->has_om_records;

			return $this->has_om_records = 
				Db_DbHelper::scalar('select count(*) from shop_option_matrix_records where product_id=:product_id', array('product_id'=>$this->id)) ? true : false;
		}
		
		/**
		 * Processes posted product options by removing non-existing options
		 * from the list, replacing non-existing option values with the first
		 * available option value and added product options which have not been
		 * posted.
		 * @param array $options Specifies posted options.
		 * @return array Returns updated product options.
		 */
		public function normalize_posted_options($options)
		{
			/*
			 * Process posted options. 
			 * 1. If there are options which do not exist in the product - 
			 * remove them from the list. 
			 * 2. If there are option values which do not exist in the 
			 * corresponding product options, replace them with first 
			 * available option value.
			 * 3. If there are product options which do not exist in the posted
			 * options, add their first available values to the posted list.
			 */

			$posted_options = array();

			if (Shop_ConfigurationRecord::get()->strict_option_values && $this->has_om_records())
			{
				$option_record = Shop_OptionMatrixRecord::find_record($options, $this, true);
				if (!$option_record || $option_record->disabled)
				{
					$posted_options = $options;

					if (Cms_Controller::get_instance())
					{
						foreach ($this->options as $option)
						{
							$updated_values = Shop_OptionMatrixRecord::get_first_available_value_set($this, $option, $posted_options);
							if ($updated_values) {
								$posted_options = $updated_values;
								break;
							}
						}
					}
				} else
					$posted_options = $options;
			} else {
				foreach ($options as $option_key=>$option_value)
				{
					$product_option = $this->get_option_by_key($option_key);

					if ($product_option)
					{
						if ($product_option->value_exists($option_value))
							$posted_options[$option_key] = $option_value;
						else
							$posted_options[$option_key] = $product_option->get_first_value();
					}
				}
			}

			foreach ($this->options as $product_option)
			{
				if (!array_key_exists($product_option->option_key, $posted_options))
					$posted_options[$product_option->option_key] = $product_option->get_first_value(false);
			}

			return $posted_options;
		}
		
		/**
		 * Returns a set of key/value pairs for the first
		 * option set enabled in the Option Matrix. If the product doesn't 
		 * have any Option Matrix records, returns first values for all product options.
		 * If all OM records are disabled, options for the first existing OM record.
		 * @param boolean $return_first_enabled_om_record Determines whether the function should
		 * return first available (enabled) Option Matrix record options instead of returning the first
		 * existing record options.
		 * @param array Returns option key/value pairs in the following format:
		 * [option_key_1=>option_value_1, option_key_2=>option_value_2, ...]
		 */
		public function get_first_available_om_value_set($return_first_enabled_om_record = true)
		{
			/*
			 * If the product has no options, return empty array.
			 */

			if (!$this->options->count)
				return array();
				
			/*
			 * If the product has no Option Matrix records, returns first existing
			 * option set.
			 */
			
			if (!$this->has_om_records())
			{
				$result = array();
				
				foreach ($this->options as $option)
					$result[$option->option_key] = $option->get_first_value();
					
				return $result;
			}
			
			/*
			 * Return options for the first enabled Option Matrix record.
			 */

			if ($return_first_enabled_om_record)
			{
				foreach ($this->option_matrix_records as $om_record)
				{
					if (!$om_record->disabled)
						return $om_record->get_options();
				}
			}
			
			/*
			 * Return options for the first existing Option Matrix record.
			 */

			return $this->option_matrix_records->first->get_options();
		}
		
		/**
		 * Returns {@link Shop_OptionMatrixRecord Option Matrix} product property. 
		 * Specify the property name in the <em>$property_name</em> parameter. 
		 * It supports all product class properties. If the method cannot find the specified property in Option Matrix product 
		 * or if Option Matrix property value is empty, the method automatically falls back to the base product 
		 * property value. The method supports a list of special property names (which exist as methods in the Shop_Product class): 
		 * <ul>
		 *   <li><em>price</em> - returns the base price, taking into account tier price configuration. If Option Matrix product has no its own price value, 
		 *     it falls back to the base product price.</li>
		 *   <li><em>sale_price</em> - returns the sale price (after applying Catalog Price Rules).</li>
		 *   <li><em>disabled</em> - returns Option Matrix product's Disabled flag value. The value is TRUE if Option Matrix product corresponding to the 
		 *     options passed to the second parameter of om() method does not exist.</li>
		 *   <li><em>is_out_of_stock</em> - returns TRUE if Option Matrix product is out of stock. Option Matrix products follow the base product's inventory 
		 *     tracking configuration.</li>
		 *   <li><em>is_on_sale</em> - returns TRUE if there are any catalog price rules applied to the Option Matrix or base product (i.e. if its sale 
		 *     price doesn't match the base price).</li>
		 *   <li><em>expected_availability_date</em> - returns the expected availability date as {@link Phpr_DateTime} object. If the expected availability 
		*      date is not set, returns null. You can format the date using the format() method. Example:
		 *   <pre>
		 *   if ($expected_date = $product->om('expected_availability_date', $posted_options))
		 *     echo $expected_date->format('%x');</pre>
		 *   </li>
		 *   <li><em>images</em> - returns a collection of Option Matrix product images (see {@link Shop_Product::images images} property of 
		 *     {@link Shop_Product} class). If there are no images in the Option Matrix product, falls back to the base product's images.</li>
		 * </ul>
		 * A set of <em>$options</em> identifies Option Matrix product which value 
		 * you want to load. It accepts an array of options and their values in the following format: 
		 * <em>['option_key_1'=>'option value 1', 'option_key_2'=>'option value 2']</em>. Almost always you should use {@link Shop_ProductHelper} 
		 * class to load current product's options. Usage example: 
		 * <pre>
		 * $posted_options = Shop_ProductHelper::get_default_options($product);
		 * $in_stock = $product->om('in_stock', $posted_options);
		 * </pre>
		 * @documentable
		 * @see http://lemonstand.com/docs/integrating_option_matrix Integrating Option Matrix
		 * @see http://lemonstand.com/docs/understanding_option_matrix Understanding Option Matrix
		 * @param string $property_name Specifies the property name.
		 * @param mixed $options Specifies product option values or {@link Shop_OptionMatrixRecord} object.
		 * @return mixed Returns the property value.
		 */
		public function om($property_name, $options = null)
		{
			/*
			 * Try to load product options from POST or the options preset.
			 * If the data is not found in POST and the preset, use
			 * default (first) values of the product options.
			 */

			if ($options === null)
			{
				if ($this->om_options_preset)
					$options = $this->om_options_preset;
				else
					$options = $this->normalize_posted_options(post('product_options', array()));
			}

			if (!$options)
			{
				$options = array();
				foreach ($this->options as $option)
				{
					$values = $option->list_values(false);
					if (count($values))
						$options[$option->option_key] = $values[0];
				}
			}
			
			return Shop_OptionMatrix::get_property($options, $property_name, $this, true);
		}
		
		/**
		 * Returns Option Matrix record associated with the product.
		 * Option Matrix records can be associated with products by the product search function.
		 */
		public function get_om_record()
		{
			return $this->om_options_preset;
		}
		
		/**
		 * Returns a list of product options as a string. 
		 * The returned value has the following format: <em>Color: green, Size: large</em>.
		 * This is applicable only for Option Matrix products and only when displaying the search results. 
		 * In all other cases the method returns NULL. 
		 * @documentable
		 * @param string Returns product options as string
		 * @see http://lemonstand.com/docs/integrating_option_matrix Integrating Option Matrix
		 * @see http://lemonstand.com/docs/understanding_option_matrix Understanding Option Matrix
		 */
		public function options_as_string()
		{
			if (!$this->om_options_preset)
				return null;

			return $this->om_options_preset->options_as_string();
		}
		
		/*
		 * Event descriptions
		 */

		/**
		 * Allows to define new columns in the product model.
		 * You can download a module template extending the product model in {@link http://lemonstand.com/docs/lemonstand_module_templates Module Templates} 
		 * documentation section.
		 * The event handler should accept a single parameter - the product object. To add new columns to the product model, 
		 * call the {@link Db_ActiveRecord::define_column() define_column()} method of the product object. Before you add new columns to the model, 
		 * you should add them to the database (the <em>shop_products</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductModel', $this, 'extend_product_model');
		 *   Backend::$events->addEvent('shop:onExtendProductForm', $this, 'extend_product_form');
		 * }
		 * 
		 * public function extend_product_model($product)
		 * {
		 *   $product->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_product_form($product, $context)
		 * {
		 *   $product->add_form_field('x_extra_description')->tab('Product');
		 * }
		 * </pre>
		 * @event shop:onExtendProductModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductForm
		 * @see shop:onGetProductFieldOptions
		 * @see shop:onGetProductFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables.
		 * @param Shop_Product $product Specifies the product object.
		 */
		private function event_onExtendProductModel($product) {}
			
		/**
		 * Allows to add new fields to the Create/Edit Product form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendProductModel} event. 
		 * To add new fields to the product form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * product object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductModel', $this, 'extend_product_model');
		 *   Backend::$events->addEvent('shop:onExtendProductForm', $this, 'extend_product_form');
		 * }
		 * 
		 * public function extend_product_model($product)
		 * {
		 *   $product->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_product_form($product, $context)
		 * {
		 *   $product->add_form_field('x_extra_description')->tab('Product');
		 * }
		 * </pre>
		 * @event shop:onExtendProductForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductModel
		 * @see shop:onGetProductFieldOptions
		 * @see shop:onGetProductFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Product $product Specifies the product object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendProductForm($product, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendProductForm} event.
		 * Usually you do not need to use this event for fields which represent 
		 * {@link http://lemonstand.com/docs/extending_models_with_related_columns data relations}. But if you want a standard 
		 * field (corresponding an integer-typed database column, for example), to be rendered as a drop-down list, you should 
		 * handle this event.
		 *
		 * The event handler should accept 2 parameters - the field name and a current field value. If the current
		 * field value is -1, the handler should return an array containing a list of options. If the current 
		 * field value is not -1, the handler should return a string (label), corresponding the value.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductModel', $this, 'extend_product_model');
		 *   Backend::$events->addEvent('shop:onExtendProductForm', $this, 'extend_product_form');
		 *   Backend::$events->addEvent('shop:onGetProductFieldOptions', $this, 'get_product_field_options');
		 * }
		 * 
		 * public function extend_product_model($product)
		 * {
		 *   $product->define_column('x_color', 'Color');
		 * }
		 * 
		 * public function extend_product_form($product, $context)
		 * {
		 *   $product->add_form_field('x_color')->tab('Product')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_product_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_color')
		 *   {
		 *     $options = array(
		 *       0 => 'Red',
		 *       1 => 'Green',
		 *       2 => 'Blue'
		 *     );
		 *     
		 *     if ($current_key_value == -1)
		 *       return $options;
		 *     
		 *     if (array_key_exists($current_key_value, $options))
		 *       return $options[$current_key_value];
		 *   }
		 * }
		 * </pre>
		 * @event shop:onGetProductFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductModel
		 * @see shop:onExtendProductForm
		 * @see shop:onGetProductFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetProductFieldOptions($db_name, $field_value) {}
			
		/**
		 * Determines whether a custom radio button or checkbox list option is checked.
		 * This event should be handled if you added custom radio-button and or checkbox list fields with {@link shop:onExtendProductForm} event.
		 * @event shop:onGetProductFieldState
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductModel
		 * @see shop:onExtendProductForm
		 * @see shop:onGetProductFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @param Shop_Product $product Specifies the product object.
		 * @return boolean Returns TRUE if the field is checked. Returns FALSE otherwise.
		 */
		private function event_onGetProductFieldState($db_name, $field_value, $product) {}

		/**
		 * Allows to add new sorting columns to list_products() methods.
		 * This events affects the following methods: {@link Shop_Category::list_products()}, {@link Shop_CustomGroup::list_products()}, 
		 * {@link Shop_Manufacturer::list_products()}.
		 * The handler should return an array of column names of the shop_products table, including custom fields. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onGetProductSortColumns', $this, 'get_sorting_columns');
		 * }
		 * 
		 * public function get_sorting_columns()
		 * {
		 *   return array('x_newfield');
		 * }
		 * </pre>
		 * @event shop:onGetProductSortColumns
		 * @package shop.events
		 * @see shop:onGetProductSearchSortColumns
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $product Specifies the product object.
		 * @return array Returns a list of column names.
		 */
		private function event_onGetProductSortColumns($product) {}

		/**
		 * Triggered when a product stock reaches the out of stock threshold (goes out of stock). 
		 * Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onProductOutOfStock', $this, 'process_out_of_stock');
		 * }
		 *  
		 * public function process_out_of_stock($product, $om_record)
		 * {
		 *   // Do something 
		 *   //
		 * }
		 * </pre>
		 * @event shop:onProductOutOfStock
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $product Specifies the product object.
		 * @param Shop_OptionMatrixRecord $om_record Specifies the Option Matrix record, if applicable.
		 */
		private function event_onProductOutOfStock($product, $om_record) {}

		/**
		 * Allows to override product's base price.
		 * The event handler should accept a single parameter, an array containing the following elements:
		 * Example:
		 * <ul>
		 *   <li><em>price</em> - the calculated product price.</li>
		 *   <li><em>product</em> - a {@link Shop_Product} object, a product the price is being calculated for.</li>
		 *   <li><em>quantity</em> - number of products to calculate the price for (if tier pricing is used).</li>
		 *   <li><em>customer_group_id</em> - identifier of a {@link Shop_CustomerGroup customer group} the price is being calculated for.</li>
		 * </ul>
		 * The event handler should return an array with the <em>price</em> element. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onGetProductPriceNoTax', $this, 'fix_price');
		 * }
		 * 
		 * public function fix_price($data)
		 * {
		 *   //increase price by 10% for customers from a specific customer group
		 *   if($data['customer_group_id'] == 1)
		 *   {
		 *     $data['price'] = $data['price'] * 1.1;
		 *     return $data;
		 *   }
		 *   
		 *   //return set price for specific product
		 *   if($data['product']->sku == 'testproduct')
		 *   {
		 *     $data['price'] = 200;
		 *     return $data;
		 *   }
		 *   
		 *   //increase the discount with quantity of the product
		 *   if($data['quantity'] > 1)
		 *   {
		 *     if($data['quantity'] >= 5)
		 *       $discount = 0.5;
		 *     else
		 *       $discount = $data['quantity'] * 0.1;
		 *     
		 *     $data['price'] = $data['price'] * (1 - $discount);
		 *     return $data;
		 *   }
		 * }
		 * </pre>
		 * @event shop:onGetProductPriceNoTax
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $params An array of parameters.
		 * @return array Returns an array with <em>price</em> element.
		 */
		private function event_onGetProductPriceNoTax($params) {}

		/**
		 * Triggered before a file is added to a product on the {@link http://lemonstand.com/docs/product_page Product Details} page. 
		 * Inside the event handler you can validate the uploaded file and trigger an exception if it is needed. 
		 * Please read {@link http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ this article} to learn about 
		 * implementing the file upload support on the product details page. 
		 * Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAfterProductFileAdded', $this, 'before_file_added');
		 * }
		 *  
		 * public function before_file_added($product, $new_file)
		 * {
		 *   if ($product->list_uploaded_files()->count >= 3)
		 *     throw new Phpr_ApplicationException('You cannot add more then 3 files');
		 * }
		 * </pre>
		 * @event shop:onBeforeProductFileAdded
		 * @see http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ Supporting file uploads on the product page
		 * @see onAfterProductFileAdded
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $product Specifies the product object.
		 * @param Db_File $file Specifies the added file object.
		 */
		private function event_onBeforeProductFileAdded($product, $file) {}

		/**
		 * Triggered after a file is added to a product on the {@link http://lemonstand.com/docs/product_page Product Details} page. 
		 * Please read {@link http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ this article} to learn about 
		 * implementing the file upload support on the product details page. 
		 * Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAfterProductFileAdded', $this, 'after_file_added');
		 * }
		 *  
		 * public function after_file_added($product, $new_file)
		 * {
		 *   // Replace existing files with the new one
		 *   $files = $product->list_uploaded_files();
		 *   foreach ($files as $file)
		 *   {
		 *     if ($file->id != $new_file->id)
		 *       $file->delete();
		 *   }
		 * }
		 * </pre>
		 * @event shop:onAfterProductFileAdded
		 * @see http://lemonstand.com/docs/supporting_file_uploads_on_the_product_page/ Supporting file uploads on the product page
		 * @see shop:onBeforeProductFileAdded
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $product Specifies the product object.
		 * @param Db_File $file Specifies the added file object.
		 */
		private function event_onAfterProductFileAdded($product, $file) {}
			
		/**
		 * Allows to configure the Administration Area product pages before they are displayed.
		 * In the event handler you can update the back-end controller properties.
		 * @event shop:onConfigureProductsPage
		 * @triggered /modules/shop/controllers/shop_products.php
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 */
		private function event_onConfigureProductsPage($controller) {}

		/**
		 * Allows to load extra CSS or JavaScript files on the Create/Edit Product page. 
		 * The event handler should accept a single parameter - the controller object reference. 
		 * Call addJavaScript() and addCss() methods of the controller object to add references to JavaScript and CSS files. 
		 * Use paths relative to LemonStand installation URL for your resource files. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onDisplayProductForm', $this, 'load_product_form_resources');
		 * }
		 *  
		 * public function load_product_form_resources($controller)
		 * {
		 *   $controller->addJavaScript('/modules/mymodule/resources/javascript/my.js');
		 *   $controller->addCss('/modules/mymodule/resources/css/my.css');  
		 * }
		 * </pre>
		 * @event shop:onDisplayProductForm
		 * @triggered /modules/shop/controllers/shop_products.php
		 * @see shop:onDisplayProductList
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 */
		private function event_onDisplayProductForm($controller) {}
		
		/**
		 * Allows to load extra CSS or JavaScript files on the Product List page. 
		 * The event handler should accept a single parameter - the controller object reference. 
		 * Call addJavaScript() and addCss() methods of the controller object to add references to JavaScript and CSS files. 
		 * Use paths relative to LemonStand installation URL for your resource files. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onDisplayProductList', $this, 'load_product_list_resources');
		 * }
		 *  
		 * public function load_product_list_resources($controller)
		 * {
		 *   $controller->addJavaScript('/modules/mymodule/resources/javascript/my.js');
		 *   $controller->addCss('/modules/mymodule/resources/css/my.css'); 
		 * }
		 * </pre>
		 * @event shop:onDisplayProductList
		 * @triggered /modules/shop/controllers/shop_products.php
		 * @see shop:onDisplayProductForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 */
		private function event_onDisplayProductList($controller) {}

		/**
		 * Allows to load extra CSS or JavaScript files on the Product List, Product Preview, Create/Edit Product and other pages related to product.
		 * The event handler should accept a single parameter - the controller object reference. 
		 * Call addJavaScript() and addCss() methods of the controller object to add references to JavaScript and CSS files. 
		 * Use paths relative to LemonStand installation URL for your resource files. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onConfigureProductsController', $this, 'load_resources');
		 * }
		 *  
		 * public function load_resources($controller)
		 * {
		 *   $controller->addJavaScript('/modules/mymodule/resources/javascript/my.js');
		 *   $controller->addCss('/modules/mymodule/resources/css/my.css');
		 * }
		 * </pre>
		 * @event shop:onConfigureProductsController
		 * @triggered /modules/shop/controllers/shop_products.php
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 */
		private function event_onConfigureProductsController($controller) {}
			
		/**
		 * Allows to alter the list of products on the Shop/Products page in the Administration Area. 
		 * The event handler accepts the back-end controller object and should return a configured {@link Shop_Product} object. 
		 * The following example modifies the product list in such a way that only enabled products are displayed.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onPrepareProductListData', $this, 'prepare_products_data');
		 * }
		 *  
		 * public function prepare_products_data($controller)
		 * {
		 *   $product = Shop_Product::create()->apply_visibility(); // Hide disabled products
		 *   $controller->filterApplyToModel($product, 'product_list'); // Apply list filters
		 *   return $product->where('grouped is null'); // Hide grouped products
		 * }
		 * </pre>
		 * Note that the Product list page has filters, and the controller's filterApplyToModel() method should be called in 
		 * order to apply them to the product model. Also, the list should not display grouped products.
		 * @event shop:onPrepareProductListData
		 * @triggered /modules/shop/controllers/shop_products.php
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 */
		private function event_onPrepareProductListData($controller) {}

		/**
		 * Allows third-party modules to register custom product search extension functions.
		 * The event handler should accept two parameters - the $options array passed to the {@link Shop_Product::find_products()} 
		 * method the search query string. The handler should return a name of a search event handled in the custom module. Example: 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onRegisterProductSearchEvent', $this, 'register_search_event');
		 *   Backend::$events->addEvent('colorsearch:findExtraOptions', $this, 'find_extra_options');
		 * }
		 * 
		 * public function register_search_event($options, $query)
		 * {
		 *   return "colorsearch:findExtraOptions";
		 * }
		 * </pre>
		 * The event which name is returned by the handler should be handled in the same module. This handler should accept 
		 * three parameters - the search options array, the search query template string and the search query string. 
		 * The handler should extend the search query template string and return the updated template. The following method 
		 * finds all products which have an extra option named "Red" in the extra option group "Color". For simplicity we 
		 * hardcoded the option and group name, but in real conditions you would pass these values via the search options array. 
		 * <pre>
		 * public function find_extra_options($options, $template, $query)
		 * {
		 *   $extra_group = 'Color';
		 *   $value = 'Red';
		 *   
		 *   $extra_group = Db_DbHelper::escape($extra_group);
		 *   $value = Db_DbHelper::escape($value);
		 * 
		 *   $group_query = "(
		 *     exists(
		 *       select 
		 *         id 
		 *       from 
		 *         shop_extra_options 
		 *       where 
		 *         group_name='".$extra_group."' 
		 *         and shop_extra_options.description like '%".$value."%' 
		 *         and product_id=shop_products.id and option_in_set is null
		 *     )
		 *     or 
		 *     exists(
		 *       select
		 *         shop_extra_options.id 
		 *       from
		 *         shop_extra_options,
		 *         shop_extra_option_sets,
		 *         shop_products_extra_sets
		 *       where
		 *         shop_products_extra_sets.extra_product_id = shop_products.id
		 *         and shop_products_extra_sets.extra_option_set_id = shop_extra_option_sets.id
		 *         and shop_extra_options.option_in_set = 1
		 *         and shop_extra_options.product_id = shop_extra_option_sets.id
		 *         and group_name='".$extra_group."' and shop_extra_options.description like '%".$value."%'
		 *     )
		 *   )";
		 * 
		 *   return Shop_Product::set_search_query_params($template, '%TABLES%', $group_query.' and %FILTER%');
		 * }
		 * </pre>
		 * Note that the code uses the Shop_Product::set_search_query_params() method. This method formats the product search query 
		 * template by adding new items to the table list and to the filters clause. 
		 * This event affects the {@link http://lemonstand.com/docs/creating_the_search_page Search Page} and 
		 * {@link Shop_Product::find_products()} method.
		 * @event shop:onRegisterProductSearchEvent
		 * @see http://lemonstand.com/docs/creating_the_search_page Search Page
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $options Specifies the search options.
		 * @param string $query Specifies the search query string.
		 * @return string Returns a name of a custom event which should be triggered by the search function.
		 */
		private function event_onRegisterProductSearchEvent($options, $query) {}

		/**
		 * Allows to display custom partials in the header of the Product Details page in the Administration Area. 
		 * The event handler should accept two parameters - the controller object and the product object. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductPreviewHeader', $this, 'extend_product_preview_header');
		 * }
		 *
		 * public function extend_product_preview_header($controller, $product)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/mymodule/partials/_product_preview_header.htm');
		 * }
		 * </pre>
		 * @event shop:onExtendProductPreviewHeader
		 * @triggered /modules/shop/controllers/shop_products/preview.htm
		 * @see shop:onExtendProductPreviewToolbar
		 * @see shop:onExtendProductPreviewTabs
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 * @param Shop_Product $product Specifies the product object.
		 */	
		private function event_onExtendProductPreviewHeader($controller, $product) {}
			
		/**
		 * Allows to add new buttons to the toolbar above the Product Details form. 
		 * The event handler accepts two parameter - the controller object, which you can use for 
		 * rendering a partial containing new buttons and the product object. Example:
		 * 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductPreviewToolbar', $this, 'extend_product_preview_toolbar');
		 * }
		 *  
		 * public function extend_product_preview_toolbar($controller, $product)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/mymodule/partials/_product_preview_toolbar.htm',
		 *     array('product'=>$product));
		 * }
		 * 
		 * // Example of the _product_preview_toolbar.htm partial
		 * 
		 * <div class="separator">&nbsp;</div>
		 * <?= backend_ctr_button('Custom button', 'wand', url('mymodule/manage/product/'.$product->id)) ?>
		 * </pre>
		 * @event shop:onExtendProductPreviewToolbar
		 * @triggered /modules/shop/controllers/shop_products/preview.htm
		 * @see shop:onExtendProductPreviewHeader
		 * @see shop:onExtendProductPreviewTabs
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 * @param Shop_Product $product Specifies the product object.
		 */
		private function event_onExtendProductPreviewToolbar($controller, $product) {}

		/**
		 * Allows to display custom tabs on the Product Details page in the Administration Area. 
		 * The event handler should accept two parameters - the controller object and the product object. 
		 * The handler should return an associative array of tab titles and corresponding tab partials.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductPreviewTabs', $this, 'extend_product_details_tabs');
		 * }
		 *
		 * public function extend_product_details_tabs($controller, $product)
		 * {
		 *   return array(
		 *     'My tab caption' => 'modules/my_module/partials/_my_partial.htm',
		 *     'Second custom tab' => 'modules/my_module/partials/_another_partial.htm'
		 *   );
		 * }
		 * </pre>
		 * @event shop:onExtendProductPreviewTabs
		 * @triggered /modules/shop/controllers/shop_products/preview.htm
		 * @package shop.events
		 * @see shop:onExtendProductPreviewHeader
		 * @see shop:onExtendProductPreviewToolbar
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 * @param Shop_Product $product Specifies the product object.
		 * @return array Returns an array of tab names and tab partial paths.
		 */
		private function event_onExtendProductPreviewTabs($controller, $product) {}
			
		/**
		 * Allows to add new buttons to the toolbar above the product list in the Administration Area. 
		 * The event handler should accept a single parameter - the controller object, which you can use to render a 
		 * partial containing additional buttons. Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductsToolbar', $this, 'extend_products_toolbar');
		 * }
		 *  
		 * public function extend_products_toolbar($controller)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/mymodule/partials/_products_toolbar.htm');
		 * }
		 * 
		 * // Example of the _products_toolbar.htm partial
		 * 
		 * <div class="separator">&nbsp;</div>
		 * <?= backend_ctr_button('My button', 'my_button_css_class', url('mymodule/manage/products')) ?>
		 * </pre>
		 * @event shop:onExtendProductsToolbar
		 * @triggered /modules/shop/controllers/shop_products/_products_control_panel.htm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Products $controller Specifies the controller object.
		 */
		private function event_onExtendProductsToolbar($controller) {}
			
		/**
		 * Triggered after a new product record has been imported from a CSV file. 
		 * The event handler should accept three parameters - the array of imported fields, the product identifier 
		 * and the array of column values loaded from the CSV file. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAfterCsvProductCreated', $this, 'csv_product_created');
		 * }
		 *  
		 * public function csv_product_created($fields, $id, $csv_row)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onAfterCsvProductCreated
		 * @triggered /modules/shop/models/shop_productcsvimportmodel.php
		 * @see shop:onAfterCsvProductUpdated
		 * @see shop:onBeforeCsvCustomerCreated
		 * @see shop:onAfterCsvCustomerCreated
		 * @see shop:onBeforeCsvCustomerUpdated
		 * @see shop:onAfterCsvCustomerUpdated
		 * @see shop:onOverrideProductCategoryCsvImportData
		 * @see shop:onAfterCsvOptionMatrixImport
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $fields Specifies a list if imported product fields.
		 * @param int $id Specifies the new product identifier.
		 * @param array $csv_row Specifies a list of columns loaded from the CSV file.
		 * The column values go in the same order as they are defined in the CSV file.
		 */
		private function event_onAfterCsvProductCreated($fields, $id, $csv_row) {}

		/**
		 * Triggered after an existing product record has been updated from a CSV file. 
		 * The event handler should accept three parameters - the array of imported fields, the product identifier 
		 * and the array of column values loaded from the CSV file.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAfterCsvProductUpdated', $this, 'csv_product_updated');
		 * }
		 *  
		 * public function csv_product_updated($fields, $id, $csv_row)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onAfterCsvProductUpdated
		 * @triggered /modules/shop/models/shop_productcsvimportmodel.php
		 * @see shop:onAfterCsvProductCreated
		 * @see shop:onBeforeCsvCustomerCreated
		 * @see shop:onAfterCsvCustomerCreated
		 * @see shop:onBeforeCsvCustomerUpdated
		 * @see shop:onAfterCsvCustomerUpdated
		 * @see shop:onOverrideProductCategoryCsvImportData
		 * @see shop:onAfterCsvOptionMatrixImport
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $fields Specifies a list if imported product fields.
		 * @param int $id Specifies the product identifier.
		 * @param array $csv_row Specifies a list of columns loaded from the CSV file.
		 * The column values go in the same order as they are defined in the CSV file.
		 */
		private function event_onAfterCsvProductUpdated($fields, $id, $csv_row) {}
		
		/**
		 * Triggered after a new or existing Option Matrix record has been updated from a CSV file.
		 * Example
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAfterCsvOptionMatrixImport', $this, 'after_om_import');
		 * }
		 *  
		 * public function after_om_import($result, $fields, $product_id, $record_options, $csv_row)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onAfterCsvOptionMatrixImport
		 * @triggered /modules/shop/models/shop_productcsvimportmodel.php
		 * @see shop:onAfterCsvProductCreated
		 * @see shop:onAfterCsvCustomerCreated
		 * @see shop:onAfterCsvCustomerUpdated
		 * @see shop:onOverrideProductCategoryCsvImportData
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param mixed $result The object containing information about the operation status. 
		 * The object has the following fields: 
		 * <ul>
		 *   <li><em>status</em> - <em>OK</em>, <em>SKIPPED</em> or <em>OK-WITH-WARNINGS</em> string value.</li>
		 *   <li><em>operation</em> - <em>ADD</em> or <em>UPDATE</em> string value.</li>
		 *   <li><em>warnings</em> - an array of warnings.</li>
		 *   <li><em>id</em> - identifier of the added/updated record.</li>
		 * </ul>
		 * @param array $fields Specifies a list if imported product fields.
		 * @param int $product_id Specifies the product identifier.
		 * @param array $record_options Specifies the set of options identifying the imported record.
		 * Data format: ['Color'=>'Red', 'Size'=>'M'].
		 * @param array $csv_row Specifies a list of columns loaded from the CSV file.
		 * The column values go in the same order as they are defined in the CSV file.
		 * @param array $image_directories A list of directories containing uploaded images on the server 
		 * @param array $image_root_dir Path to the images directory in the ZIP file or in the images directory (if specified in the import configuration)
		 */
		private function event_onAfterCsvOptionMatrixImport($result, $fields, $product_id, $record_options, $csv_row, $image_directories, $image_root_dir) {}

		/**
		 * Triggered after a product record is fetched from the database.
		 * @event shop:onAfterProductRecordFetch
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $product Specifies the product object.
		 */
		private function event_onAfterProductRecordFetch($product) {}
			
			
		/**
		 * Allows to override a value in the Category column of a products CSV file before the data is imported. 
		 * The event handler should accept a single parameter - the Category column value. The handler should
		 * either return the updated column value or FALSE if the value should not be updated.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onOverrideProductCategoryCsvImportData', $this, 'override_csv_product_category');
		 * }
		 *  
		 * public function override_csv_product_category($value)
		 * {
		 *   return str_replace('^', '|', $value);
		 * }
		 * </pre>
		 * @event shop:onOverrideProductCategoryCsvImportData
		 * @triggered /modules/shop/models/shop_productcsvimportmodel.php
		 * @see shop:onAfterCsvProductCreated
		 * @see shop:onAfterCsvProductUpdated
		 * @see shop:onAfterCsvCustomerCreated
		 * @see shop:onAfterCsvCustomerUpdated
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param mixed $value Specifies the Category column value.
		 * @return mixed Returns the updated value or FALSE.
		 */
		private function event_onOverrideProductCategoryCsvImportData($value) {}
			
		/**
		 * Allows to override product's sale price. 
		 * The event handler should return either the product sale price or FALSE if the price should not be overridden.
		 * This event overrides sale prices defined with Catalog Price Rules and with the Sale Price or Discount field in the
		 * Shop/Edit Product form. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onGetProductSalePrice', $this, 'get_sale_price');
		 * }
		 *  
		 * public function get_sale_price($product, $quantity, $customer_group_id)
		 * {
		 *   if ($product->sku == 'laptop')
		 *     return 999.99;
		 * 
		 *   return null;
		 * }
		 * </pre>
		 * @event shop:onGetProductSalePrice
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $product Specifies the product object.
		 * @param int $quantity Specifies the product quantity in the shopping cart.
		 * @param int $customer_group_id Specifies a {@link Shop_CustomerGroup customer group} identifier.
		 */
		private function event_onGetProductSalePrice($product, $quantity, $customer_group_id) {}
		
		/**
		 * Triggered when a product is duplicated. 
		 * @event shop:onProductDuplicated
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $src_product Specifies the original product object.
		 * @param int $new_product_id Specifies the new product identifier.
		 */
		private function event_onProductDuplicated($src_product, $new_product_id) {}

		/**
		 * Allows to add new sorting columns to the  {@link Shop_Product::find_products find_products} method.
		 * The handler should return an array of column names of the shop_products table, including custom fields. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onGetProductSearchSortColumns', $this, 'get_search_sorting_columns');
		 * }
		 * 
		 * public function get_search_sorting_columns()
		 * {
		 *   return array('x_newfield');
		 * }
		 * </pre>
		 * Note that the columns should be defined in the shop_products table.
		 * @event shop:onGetProductSearchSortColumns
		 * @see shop:onGetProductSortColumns
		 * @see Shop_Product::find_products
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Product $product Specifies the product object.
		 * @return array Returns a list of column names.
		 */
		private function event_onGetProductSearchSortColumns() {}
	}

?>
