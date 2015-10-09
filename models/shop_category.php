<?php

	/**
	 * Represents a product category. 
	 * The class has fields and methods for accessing the category name and description 
	 * as well as a list of the category products. The category class is {@link http://lemonstand.com/docs/performance_tuning/ proxiable}.
	 *
	 * This class is extended with {@link Db_Act_As_Tree} class which implements tree functionality. It allows categories to be accessed
	 * as a hierarchy with methods {@link Db_Act_As_Tree::get_parent() get_parent()}, {@link Db_Act_As_Tree::get_parents() get_parents()},
	 * {@link Db_Act_As_Tree::list_root_children() list_root_children()} and {@link Db_Act_As_Tree::list_children() list_children()}.
	 * By default {@link Db_Act_As_Tree::list_root_children() list_root_children()} and {@link Db_Act_As_Tree::list_children() list_children()} methods 
	 * sort items by name. To sort the category lists accordingly the order 
	 * you set in the Administration Area, pass the <em>front_end_sort_order</em> value to the methods: 
	 * <pre>
	 * $root_categories = Shop_Category::create()->list_root_children('front_end_sort_order');
	 * $subcategories = $category->list_children('front_end_sort_order');
	 * </pre>
	 *
	 * @property integer $id Specifies the category database identifier.
	 * @property string $name Specifies the category name. Proxiable.
	 * @property string $description Specifies the category description in HTML format. Proxiable.
	 * @property string $short_description Specifies the short description of the category as a plain text. 
	 * To output the short description use {@link h()} function to escape HTML-sensitive characters. Proxiable.
	 * @property string $url_name Specifies the category URL name. 
	 * The {@link action@shop:category} action uses this field to load a category by an URL. Usually you don't need 
	 * to access the field directly. Use the {@link Shop_Category::page_url() page_url()} method for creating links to category pages.
	 * @property Shop_Category $parent A reference to a parent category. 
	 * This field is NULL for the root-level categories. Proxiable.
	 * @property Db_DataCollection $images A collection of images assigned to the category. 
	 * Each element in the collection is an object of the {@link Db_File} class. You can use this property directly to output 
	 * category images, or use the {@link Shop_Category::image_url() image_url()} method. Not proxiable.
	 * @property string $code The category API code. 
	 * You can use this field for finding a specific category in your API calls. Proxiable. 
	 * Example: <pre>$category = Shop_Category::create()->find_by_code('computers');</pre>
	 * @documentable
	 * @see http://lemonstand.com/docs/displaying_a_list_of_categories Displaying a list of categories
	 * @see http://lemonstand.com/docs/category_page Category page
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_Category extends Db_ActiveRecord
	{
		public $table_name = 'shop_categories';
		
		public $implement = 'Db_AutoFootprints, Db_Act_As_Tree, Db_ModelAttachments';
		public $act_as_tree_parent_key = 'category_id';
		public $act_as_tree_sql_filter = null;
		public $auto_footprints_visible = true;
		public $act_as_tree_name_field = 'name';

		protected static $cache = array();
		protected $api_added_columns = array();
		protected static $product_count_cache = null;
		protected static $url_cache = null;
		public static $url_id_cache = null;
		protected static $children_ids = array();

		public $belongs_to = array(
			'parent'=>array('class_name'=>'Shop_Category', 'foreign_key'=>'category_id'),
			'page'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'page_id')
		);

		public $calculated_columns = array(
			'page_url'=>array('sql'=>"pages.url", 'type'=>db_text, 'join'=>array('pages'=>'shop_categories.page_id=pages.id'))
		);
		
		public $custom_columns = array('num_of_products'=>db_number);

		public $has_and_belongs_to_many = array(
			'products'=>array('class_name'=>'Shop_Product', 'use_straight_join'=>true, 'join_table'=>'shop_products_categories', 'order'=>'shop_products.name', 'conditions'=>'((shop_products.enabled=1 and not (
			shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock<=0))
		)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
			and not (
			grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.total_in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.total_in_stock<=0))
		))) and (shop_products.disable_completely is null or shop_products.disable_completely = 0) and (shop_products.grouped is null or shop_products.grouped=0)'),
		
			'top_products'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_products_categories', 'order'=>'product_category_sort_order', 'conditions'=>' shop_products_categories.product_category_sort_order is not null', 'primary_key'=>'shop_category_id', 'foreign_key'=>'shop_product_id'),
			'all_master_products'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_products_categories', 'conditions'=>'product_id is null', 'primary_key'=>'shop_category_id', 'foreign_key'=>'shop_product_id')
		);
		
		public $has_many = array(
			'images'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Category' and field='images'", 'order'=>'sort_order, id', 'delete'=>true)
		);
		
		protected function initialize()
		{
			if (Cms_Controller::get_instance())
				$this->act_as_tree_sql_filter = '(shop_categories.category_is_hidden is null or shop_categories.category_is_hidden=0)';
				
			parent::initialize();
		}

		public static function create($init_columns = false)
		{
			if ($init_columns)
				return new self();
			else 
				return new self(null, array('no_column_init'=>true, 'no_validation'=>true));
		}
		
		public static function find_category($category_id, $throw_exception = true)
		{
			$category_id = trim($category_id);
			if (!$category_id)
			{
				if ($throw_exception)
					throw new Phpr_ApplicationException('Category not found');
				else
					return null;
			}

			if (!array_key_exists($category_id, self::$cache))
				self::$cache[$category_id] = self::create()->find($category_id);

			if (!self::$cache[$category_id] && $throw_exception)
				throw new Phpr_ApplicationException('Category not found');
				
			return self::$cache[$category_id];
		}
		
		public static function find_by_url($url, &$params)
		{
			if (!Shop_ConfigurationRecord::get()->nested_category_urls) 
				return Shop_Category::create()->find_by_url_name($url);
			else {
				self::init_url_cache();

				if ($category = Cms_Router::find_object_by_url($url, self::$url_cache, $params))
					return Shop_Category::create()->where('id=?', $category->id)->find();
			}
			
			return null;
		}
		
		public static function init_url_cache()
		{
			if (self::$url_cache == null)
			{
				$recache = false;
				$key = Core_CacheBase::create_key('shop_category_url_cache', $recache, array(), array('catalog'));

				$cache = Core_CacheBase::create()->get($key);
				$load_from_db = true;
				if ($cache && !$recache)
				{
					try
					{
						self::$url_cache = unserialize($cache);
						$load_from_db = self::$url_cache === false;
					} catch (exception $ex) {}
				}

				if ($load_from_db)
				{
					self::$url_cache = array();

					$categories = Db_DbHelper::objectArray('select id, url_name as url, url_name as url_orig, category_id from shop_categories order by id');

					if (Shop_ConfigurationRecord::get()->category_urls_prepend_parent && Shop_ConfigurationRecord::get()->nested_category_urls)
					{
						$category_id_map = array();
						foreach ($categories as $category)
							$category_id_map[$category->id] = $category;

						foreach ($categories as $category) 
						{
							$parent_id = $category->category_id;
							while (array_key_exists($parent_id, $category_id_map))
							{
								$category->url = $category_id_map[$parent_id]->url_orig.'/'.$category->url;
								$parent_id = $category_id_map[$parent_id]->category_id;
							}
						}
					}

					foreach ($categories as $category) 
					{
						$category->url = '/'.$category->url;
						self::$url_cache[$category->url] = $category;
					}

					uasort(self::$url_cache, array('Cms_Router', 'sort_objects'));
					Core_CacheBase::create()->set($key, serialize(self::$url_cache), 3600);
				}
				
				self::$url_id_cache = array();
				foreach (self::$url_cache as $url=>$category)
					self::$url_id_cache[$category->id] = $url;
			}
		}

		public function define_columns($context = null)
		{
			$this->define_relation_column('parent', 'parent', 'Parent category', db_varchar, '@name')->invisible();
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('title', 'Title')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('category_is_hidden', 'Hide');
			$this->define_column('description', 'Long Description')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('short_description', 'Short Description')->defaultInvisible()->validation()->fn('trim');
			
			$this->define_column('url_name', 'URL Name')->validation()->fn('trim')->fn('mb_strtolower')->method('validateUrl')->unique('The URL Name "%s" already in use. Please select another URL Name.', array($this, 'configure_unique_url_validator'));
			$this->define_relation_column('page', 'page', 'Custom Page ', db_varchar, '@title')->defaultInvisible()->listTitle('Page')->validation();
			
			$this->define_column('code', 'API Code')->defaultInvisible()->validation()->fn('trim')->fn('mb_strtolower')->unique('Category with the specified  API code already exists.');
			
			$this->define_column('meta_description', 'Description')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('meta_keywords', 'Keywords')->defaultInvisible()->validation()->fn('trim');
			$this->define_multi_relation_column('images', 'images', 'Images', '@name')->invisible();

			// $this->define_multi_relation_column('top_products', 'top_products', 'Top Products', '@name')->invisible()->validation();
			$this->define_column('front_end_sort_order', 'Sort Order');
			$this->define_column('num_of_products', 'Number of Products')->defaultInvisible();
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendCategoryModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('parent')->tab('Category')->emptyOption('<none>')->optionsHtmlEncode(false);
			$this->add_form_field('name')->tab('Category');
			$this->add_form_field('category_is_hidden')->tab('Category')->comment('Hide category from category lists. Hiding a category does not disable category products.');
			$this->add_form_field('url_name', 'left')->tab('Category')->comment('Specify the category URL name (for example "printers") or leave this field empty if you want to provide a specially designed category page.', 'above');
			$this->add_form_field('page', 'right')->tab('Category')->emptyOption('<default category page>')->comment('You can customize the category landing page. Select a page, specially designed for this category or leave the default value.', 'above')->optionsHtmlEncode(false);
			$this->add_form_field('title')->comment('Use this field to customize the category page title. Leave this field empty to use the category name as the page title.', 'above')->tab('Category');
			$this->add_form_field('short_description')->tab('Category')->size('small');
			
			$editor_config = System_HtmlEditorConfig::get('shop', 'shop_products_categories');
			$field = $this->add_form_field('description');
			$field->tab('Category')->renderAs(frm_html)->size('large')->saveCallback('save_item');
			$field->htmlPlugins .= ',save';
			$editor_config->apply_to_form_field($field);

			$this->add_form_field('code')->comment('You can use the API Code for accessing the category in the API calls.', 'above')->tab('Category');
			$this->add_form_field('images')->renderAs(frm_file_attachments)->renderFilesAs('image_list')->addDocumentLabel('Add image(s)')->tab('Images')->noAttachmentsLabel('There are no images uploaded');
			$this->add_form_field('meta_description')->tab('Meta');
			$this->add_form_field('meta_keywords')->tab('Meta');
			
			Backend::$events->fireEvent('shop:onExtendCategoryForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
				{
					$form_field->optionsMethod('get_added_field_options');
					$form_field->optionStateMethod('get_added_field_option_state');
				}
			}
		}
		
		public function get_page_options($key_value=-1)
		{
			return Cms_Page::create()->get_page_tree_options($key_value);
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetCategoryFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}

		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetCategoryFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}

		public function get_parent_options($keyValue = -1, $maxLevel = 100)
		{
			$result = array();
			$obj = new self();

			if ($keyValue == -1)
			{
				$this->listParentIdOptions(null, $result, 0, $this->id, $maxLevel);
			}
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

		private function listParentIdOptions($items, &$result, $level, $ignore, $maxLevel)
		{
			if ($level > $maxLevel)
				return;
				
			if ($items === null)
				$items = self::list_children_category_proxies(null);
			
			foreach ($items as $item)
			{
				if ($ignore !== null && $item->id == $ignore)
					continue;

				$result[$item->id] = str_repeat("&nbsp;", $level*3).h($item->name);
				$this->listParentIdOptions(self::list_children_category_proxies($item->id), $result, $level+1, $ignore, $maxLevel);
			}
		}
		
		public static function list_children_category_proxies($parent_id, $order_by = 'name')
		{
			if ($parent_id)
				$sql = 'select * from shop_categories where category_id=:parent_id order by '.$order_by;
			else
				$sql = 'select * from shop_categories where category_id is null order by '.$order_by;
				
			$data = Db_DbHelper::queryArray($sql, array('parent_id'=>$parent_id));
			$result = array();

			foreach ($data as $item)
				$result[] = new Db_ActiverecordProxy($item['id'], 'Shop_Category', $item);
				
			return $result;
		}

		public function validateUrl($name, $value)
		{
			$urlName = trim($this->url_name);
		
			if (Shop_ConfigurationRecord::get()->nested_category_urls) 
			{
				if (!preg_match(',^[/0-9a-z_-]*$,i', $urlName))
					$this->validation->setError('URL Name can contain only latin characters, numbers and signs /,-, _, -', $name, true);

				if (substr($urlName, 0, 1) == '/')
					$this->validation->setError('The first character in the url should not be the forward slash.', $name, true);
			} else 
			{
				if (!preg_match('/^[0-9a-z_-]*$/i', $urlName)) {
					$message = 'URL Name can contain only latin characters, numbers and signs -, _, -.';
					
					if (strpos($urlName, '/') !== false)
						$message .= sprintf(' If you want to use nested category URLs, please enable <strong>Enable category URL nesting</strong> feature on <a target="_blank" href="%s">System/Settings/eCommerce Settings</a> page.', url('shop/configuration'));
					
					$this->validation->setError($message, $name, true);
				}
			}

			if (!strlen($urlName) && !$this->page)
				$this->validation->setError('Please specify either URL name or category custom page.', $name, true);
				
			return true;
		}
		
		public function configure_unique_url_validator($checker, $product, $deferred_session_key)
		{
			if (!Shop_ConfigurationRecord::get()->category_urls_prepend_parent)
				return;
			elseif(Shop_ConfigurationRecord::get()->nested_category_urls)
			{
				if ($this->category_id)
					$checker->where('category_id=?', $this->category_id);
				else
					$checker->where('category_id is null');
			}
		}
		
		public static function get_children_ids($parent_id)
		{
			if (array_key_exists($parent_id, self::$children_ids))
				return self::$children_ids[$parent_id];
			
			$result = Db_DbHelper::scalarArray('select id from shop_categories where category_id=:id', array('id'=>$parent_id));
			foreach ($result as $child_id)
				$result = array_merge($result, self::get_children_ids($child_id));

			return self::$children_ids[$parent_id] = $result;
		}

		public function before_delete($id=null)
		{
			$hasChildren = Db_DbHelper::scalar(
				'select count(*) from shop_categories where category_id=:id', 
				array('id'=>$this->id)
			);
			$childrenProducts = 0;
			$children_ids = false;
			
			if ($hasChildren)
			{
				$children_ids = self::get_children_ids($this->id);
				foreach($children_ids as $child_id)
				{
					$childrenProducts = $childrenProducts + Db_DbHelper::scalar(
						'select count(*) from shop_products_categories, shop_products where shop_products.id=shop_products_categories.shop_product_id and shop_products_categories.shop_category_id=:id and shop_products.product_id is null', 
						array('id'=>$child_id)
					);
				}		
				if($childrenProducts)
					throw new Phpr_ApplicationException("Unable to delete category because one or more of its subcategories contain products ($childrenProducts).");
			}
				
			$isInUse = Db_DbHelper::scalar(
				'select count(*) from shop_products_categories, shop_products where shop_products.id=shop_products_categories.shop_product_id and shop_products_categories.shop_category_id=:id and shop_products.product_id is null', 
				array('id'=>$this->id)
			);
			if ($isInUse)
				throw new Phpr_ApplicationException("Unable to delete category because it contains products ($isInUse).");
			elseif (!$childrenProducts && $children_ids)
			{
				foreach(array_reverse($children_ids) as $child_id)
				{
					$child = Shop_Category::create()->find($child_id);
					if ($child)
						$child->delete();
				}
			}
		}
		
		public function after_create() 
		{
			Db_DbHelper::query('update shop_categories set front_end_sort_order=:front_end_sort_order where id=:id', array(
				'front_end_sort_order'=>$this->id,
				'id'=>$this->id
			));

			$this->front_end_sort_order = $this->id;
		}
		
		public static function set_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				Db_DbHelper::query('update shop_categories set front_end_sort_order=:front_end_sort_order where id=:id', array(
					'front_end_sort_order'=>$order,
					'id'=>$id
				));
			}
			
			Shop_Module::update_catalog_version();
		}
		
		/**
		 * Returns number of product in the category not taking
		 * into account grouped products
		 */
		protected function get_top_level_product_num()
		{
			if (self::$product_count_cache === null)
			{
				self::$product_count_cache = array();

				$data = Db_DbHelper::objectArray('select shop_category_id, count(*) as cnt from shop_products_categories, shop_products 
				where shop_products_categories.shop_product_id = shop_products.id
				and shop_products.product_id is null
				group by shop_products_categories.shop_category_id');

				foreach ($data as $category_product_num)
					self::$product_count_cache[$category_product_num->shop_category_id] = $category_product_num->cnt;
			}
			
			if (array_key_exists($this->id, self::$product_count_cache))
				return self::$product_count_cache[$this->id];
				
			return 0;
		}
		
		public function eval_num_of_products()
		{
			return $this->get_top_level_product_num();
		}

		/*
		 * Top products management methods.
		 */
		
		public function add_top_product($product_id)
		{
			Db_DbHelper::query(
				'update shop_products_categories set product_category_sort_order=:product_id where shop_product_id=:product_id and shop_category_id=:category_id',
				array('product_id'=>$product_id, 'category_id'=>$this->id)
			);
		}
		
		public function remove_top_product($product_id)
		{
			Db_DbHelper::query(
				'update shop_products_categories set product_category_sort_order=null where shop_product_id=:product_id and shop_category_id=:category_id',
				array('product_id'=>$product_id, 'category_id'=>$this->id)
			);
		}
		
		public static function get_top_products_orders($category_id)
		{
			$orders = Db_DbHelper::objectArray('select product_category_sort_order, shop_product_id from shop_products_categories where product_category_sort_order is not null and shop_category_id=:category_id', 
			array('category_id'=>$category_id));
			
			$result = array();
			foreach ($orders as $order_item)
				$result[$order_item->shop_product_id] = $order_item->product_category_sort_order;

			return $result;
		}
		
		public static function set_top_orders($category_id, $item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				Db_DbHelper::query('update shop_products_categories set product_category_sort_order=:product_category_sort_order where shop_product_id=:product_id and shop_category_id=:category_id', array(
					'product_category_sort_order'=>$order,
					'product_id'=>$id,
					'category_id'=>$category_id
				));
			}
		}

		/*
		 * Interface methods
		 */

		/**
		 * Returns the category page URL, based on the URL passed in the parameter. 
		 * Use this method to create links to categories. If there is no custom page assigned to a category, 
		 * this method just adds the category URL name to the base URL specified in the parameter. So, if you passed the <em>category</em> string 
		 * to the method, it would return strings like <em>/category/computers</em> or <em>/category/monitors</em>. For categories which have a 
		 * custom page assigned and no URL Name assigned, the method returns the custom page URL. For categories with both a custom page and URL Name 
		 * specified, the method returns an URL of the custom page plus the value of the URL Name parameter: <em>/custom_page/url_name</em> . 
		 * This method is proxiable. Usage example:
		 * <pre><a href="<?= $category->page_url('/category') ?>"><?= $category->name ?></a></pre>
		 * @documentable
		 * @see http://lemonstand.com/docs/displaying_a_list_of_categories Displaying a list of categories
		 * @param string $base_url Specifies the base category page URL.
		 * @return string Returns the category page URL.
		 */
		public function page_url($default)
		{
			return self::page_url_proxiable($this, $default);
		}
		
		public static function page_url_proxiable($proxy, $default)
		{
			$page_url = Cms_PageReference::get_page_url($proxy, 'page_id', $proxy->page_url);
			
			$url_name = $proxy->get_url_name();
			if (substr($url_name, 0, 1) == '/')
				$url_name = substr($url_name, 1);

			if (!strlen($page_url))
				return root_url($default.'/'.$url_name);
				
			if (!strlen($url_name))
				return root_url($page_url);

			return root_url($page_url.'/'.$url_name);
		}

		/**
		 * Returns an URL of a category image thumbnail.
		 * Use this method for displaying category images. The <em>$width</em> and <em>$height</em> parameters are thumbnail width and height correspondingly. 
		 * You can use exact integer values, or word <em>'auto'</em> for automatic image resizing. The <em>$as_jpeg</em> parameter allows you to generate 
		 * PNG images with transparency support. By default the parameter value is TRUE and the method generates a JPEG image. The <em>$params</em> 
		 * array allows to pass parameters to image processing modules (which handle the {@link core:onProcessImage} event). This method is proxiable.
		 * The following line of code outputs a thumbnail of the first category image. The thumbnail width is 100 pixels, and thumbnail height is calculated 
		 * by LemonStand to keep the original aspect ratio. 
		 * <pre><img src="<?= $category->image_url(0, 100, 'auto') ?>"/></pre>
		 * @documentable
		 * @see Db_File::getThumbnailPath()
		 * @param integer $index Specifies the zero-based image index.
		 * @param mixed $width Specifies the thumbnail width. Use the 'auto' word to scale image width proportionally. 
		 * @param mixed $height Specifies the thumbnail height. Use the 'auto' word to scale height width proportionally. 
		 * @param boolean $as_jpeg Determines whether JPEG or PNG image will be created. 
		 * @param array $params A list of parameters. 
		 * @return string Returns the image URL relative to the website root.
		 */
		public function image_url($index = 0, $width = 'auto', $height = 'auto', $returnJpeg = true, $params = array('mode' => 'keep_ratio'))
		{
			if ($index < 0 || $index > $this->images->count-1)
				return null;
				
			return $this->images[$index]->getThumbnailPath($width, $height, $returnJpeg, $params);
		}
		
		/**
		 * Returns a list of the category products. 
		 * This method is not proxiable. The result of this function is an object of the {@link Shop_Product} class. 
		 * To obtain a collection of all category products call the {@link Db_ActiveRecord::find_all() find_all()} method of the returned object. 
		 * <pre>$full_product_list = $category->list_products()->find_all();</pre>
		 * The find_all() methods returns an object of the {@link Db_DataCollection} class, which you can use as a usual array.
		 * You can use the result of the method for further processing, for example for paginating the category products list. 
		 * Please read the {@link http://lemonstand.com/docs/displaying_a_list_of_products Displaying a list of products} article for the examples.
		 * 
		 * You can pass an array of options to the method. The supported options are  <em>sorting</em>, <em>manufacturer_url_name</em> and
		 * <em>apply_top_products</em>. 
		 * By default the product list is sorted by product name. You can sort products by another field. Also, you can sort the product list by
		 * multiple fields.
		 * <pre>
		 * $product_list = $category->list_products(array(
		 *   'sorting'=>array('price', 'name')
		 * ));
		 * </pre>
		 * The supported fields you can sort the products by are: 
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
		 * You can add <em>desc</em> suffix to the sort field name to enable the descending sorting. For example, to sort the product list 
		 * by price in descending order, you can use the following code: 
		 * <pre>
		 * $product_list = $category->list_products(array(
		 *   'sorting'=>array('price desc')
		 * ));
		 * </pre>
		 * You can add custom sorting fields with {@link shop:onGetCategoryProductSortingQuery} event.
		 *
		 * Note that if there are top products defined for the category, they are always returned in the beginning of the list, and their 
		 * order is independent on the sorting parameters you specify in the method call. You can disable the top products feature and 
		 * display them on their normal positions by passing FALSE value to the <em>apply_top_products</em> option. 
		 * <pre>
		 * $product_list = $category->list_products(array(
		 *   'sorting'=>array('price desc'),
		 *   'apply_top_products'=>false
		 * ));
		 * </pre>
		 * You can filter category products with a specific manufacturer by passing the manufacturer's URL name to the manufacturer_url_name element: 
		 * <pre>
		 * $product_list = $category->list_products(array(
		 *   'manufacturer_url_name'=>'lemonstand_ecommerce_inc'
		 * ));
		 * </pre>
		 * If you need to obtain a number of products in a category you can use the following code: 
		 * <pre>$product_num = $category->list_products()->requestRowCount(); </pre>
		 * @documentable
		 * @see http://lemonstand.com/docs/displaying_a_list_of_products Displaying a list of products
		 * @see shop:onGetCategoryProductSortingQuery
		 * @param array $options Specifies the method options.
		 * @return Shop_Product Returns an object of the {@link Shop_Product} class. 
		 */
		public function list_products($options = array())
		{
			$apply_top_products = array_key_exists('apply_top_products', $options) ? 
				$options['apply_top_products'] : 
				true;

			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array('name');
				
			$manufacturer_filter = array_key_exists('manufacturer_url_name', $options) ? 
				trim($options['manufacturer_url_name']) :
				null;

			if (!is_array($sorting))
				$sorting = array('name');

			$allowed_sorting_columns = Shop_Product::list_allowed_sort_columns();

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));
				
				if (!in_array($test_name, $allowed_sorting_columns))
					continue;
				
				$custom_sorting_query = false;
				$queries = Backend::$events->fireEvent('shop:onGetCategoryProductSortingQuery', $sorting_column, $this);
				foreach ($queries as $query)
				{
					if (strlen($query))
						$custom_sorting_query = $query;
				}
				
				if (!$custom_sorting_query)
				{
					if (strpos($sorting_column, 'price') !== false)
						$sorting_column = str_replace('price', sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()), $sorting_column);
					elseif(strpos($sorting_column, 'manufacturer') !== false)
						$sorting_column = str_replace('manufacturer', 'manufacturer_link_calculated', $sorting_column);
					elseif (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
						$sorting_column = 'shop_products.'.$sorting_column;
				} else
					$sorting_column = $custom_sorting_query;
			}
			
			if (!$sorting)
				$sorting = array('name');

			$product_obj = $this->products_list;
			$product_obj->reset_order();
			$product_obj->apply_customer_group_visibility()->apply_catalog_visibility();

			$sort_str = implode(', ', $sorting);
			
			if ($apply_top_products)
				$sort_str = "ifnull(shop_products_categories.product_category_sort_order+100000000, '_'), ".$sort_str;
				
			if (strlen($manufacturer_filter))
			{                 
				$product_obj->join('shop_manufacturers as manufacturer_filter', 'shop_products.manufacturer_id=manufacturer_filter.id');
				$product_obj->where('manufacturer_filter.url_name=?', $manufacturer_filter);
			}

			$product_obj->order($sort_str);

			return $product_obj;
		}
		
		/**
		 * Returns a list of manufacturers of products belonging to the category.
		 * This method is not  proxiable. The method returns an instance of the {@link Db_DataCollection} class. 
		 * Each element in the collection is an object of {@link Shop_Manufacturer} class. You can output a list of 
		 * a category manufacturers with the following code: 
		 * <pre>
		 * <ul>
		 *   <?
		 *     $manufacturers = $category->list_manufacturers();
		 *     foreach ($manufacturers as $manufacturer):
		 *   ?>
		 *     <li><?= h($manufacturer->name) ?></li>
		 *   <? endforeach ?>
		 * </ul>
		 * </pre>
		 * @documentable
		 * @return Db_DataCollection Returns a collection of {@link Shop_Manufacturer} objects.
		 */
		public function list_manufacturers()
		{
			$obj = new Shop_Manufacturer();
			$obj->join('shop_products', 'shop_products.manufacturer_id = shop_manufacturers.id');
			$obj->join('shop_products_categories', 'shop_products_categories.shop_product_id=shop_products.id');
			$obj->group('shop_manufacturers.id');
			$obj->order('shop_manufacturers.name');
			$obj->where('shop_products_categories.shop_category_id=?', $this->id);

			return $obj->find_all();
		}
		
		/**
		 * A helper function which returns TRUE in case if the category URL name matches a value passed to the first parameter. 
		 * If the second parameter is TRUE, the function also examines all subcategories.
		 * It allows to mark a category as current in the category list, if the category itself is not current, but one of its subcategories is current. 
		 * This method is proxiable.
		 * @documentable
		 * @param string $current_category_url_name Specifies URL name of a current category.
		 * @param boolean $look_in_subcategories Determines whether the method should also examine subcategories.
		 * @return boolean Returns TRUE if the category is current. Returns FALSE otherwise.
		 */
		public function is_current($current_category_url_name, $look_in_subcategories = false)
		{
			return self::is_current_proxiable($this, $current_category_url_name, $look_in_subcategories);
		}
		
		/**
		 * Returns TRUE if any categories exist in the database.
		 * @return boolean
		 */
		public static function categories_exist()
		{
			return Db_DbHelper::scalar('select count(*) from shop_categories') > 0;
		}
		
		public static function is_current_proxiable($proxy, $current_category_url_name, $look_in_subcategories = false)
		{
			$result = $current_category_url_name == $proxy->get_url_name();
			if (!$look_in_subcategories || $result)
				return $result;
				
			$subcategories = $proxy->list_all_children('front_end_sort_order');
			foreach ($subcategories as $subcategory)
			{
				if ($current_category_url_name == $subcategory->get_url_name())
				{
					$result = true;
					break;
				}
			}
			
			return $result;
		}
		
		protected function list_category_options($level, &$result, $current_id)
		{
			$selected = $current_id == $this->id ? 'selected="selected"' : null;
			$result .= '<option '.$selected.' value="'.h($this->id).'">'.str_repeat('&nbsp;', $level*3).h($this->name).'</option>'."\n";
			$subcategories = $this->list_children('front_end_sort_order');
			foreach ($subcategories as $category)
				$category->list_category_options($level+1, $result, $current_id);
		}
		
		/**
		 * Returns an HTML string containing a set of OPTION elements corresponding to all product categories. 
		 * Nested categories are indented. The method does not take into account category visibility and always return all categories. 
		 * You can use this method for creating a drop-down category selector. The optional parameter allows you to specify a category 
		 * identifier to be selected by default. This method is not proxiable. Example: 
		 * <pre>
		 * <select name="category_id">
		 *   <?= Shop_Category::as_options() ?>
		 * </select>
		 * </pre>
		 * @documentable
		 * @param integer $current_id Specifies an identifier of current category.
		 * @return string Returns HTML string.
		 */
		public static function as_options($current_id = null)
		{
 			$result = '';
			$obj = new self();
			$categories = $obj->list_root_children('shop_categories.front_end_sort_order');
			foreach ($categories as $category)
				$category->list_category_options(0, $result, $current_id);

			return $result;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		/**
		 * Returns the category URL name. 
		 * If <em>nested category URLs</em> and <em>Prepend parent category URL</em> options are enabled on System/Settings/eCommerce Settings page, 
		 * the method returns category URL name with the parent category URLs prepended. This method is proxiable.
		 * @documentable
		 * @return string Returns the category URL name.
		 */
		public function get_url_name()
		{
			return self::get_url_name_proxiable($this);
		}
		
		public static function get_url_name_proxiable($proxy)
		{
			if (Shop_ConfigurationRecord::get()->nested_category_urls && Shop_ConfigurationRecord::get()->category_urls_prepend_parent)
			{
				Shop_Category::init_url_cache();
				if (isset(Shop_Category::$url_id_cache[$proxy->id]))
					return Shop_Category::$url_id_cache[$proxy->id];
			}
			
			return $proxy->url_name;
		}
		
		public static function get_primary_key_value_proxiable($proxy)
		{
			return $proxy->id;
		}

		protected function after_fetch()
		{
			Backend::$events->fireEvent('shop:onAfterCategoryRecordFetch', $this);
		}
		
		/*
		 * Event descriptions
		 */

		/**
		 * Allows to define new columns in the product category model.
		 * The event handler should accept two parameters - the category object and the form 
		 * execution context string. To add new columns to the category model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the category object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_categories</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendCategoryModel', $this, 'extend_category_model');
		 *    Backend::$events->addEvent('shop:onExtendCategoryForm', $this, 'extend_category_form');
		 * }
		 * 
		 * public function extend_category_model($category, $context)
		 * {
		 *    $category->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_category_form($category, $context)
		 * {
		 *    $category->add_form_field('x_extra_description')->tab('Category');
		 * }
		 * </pre>
		 * @event shop:onExtendCategoryModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCategoryForm
		 * @see shop:onGetCategoryFieldOptions
		 * @see shop:onGetCategoryFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Category $category Specifies the category object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendCategoryModel($category, $context) {}
		
		/**
		 * Allows to add new fields to the Create/Edit Category form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendCategoryModel} event. 
		 * To add new fields to the category form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * category object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendCategoryModel', $this, 'extend_category_model');
		 *    Backend::$events->addEvent('shop:onExtendCategoryForm', $this, 'extend_category_form');
		 * }
		 * 
		 * public function extend_category_model($category, $context)
		 * {
		 *    $category->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_category_form($category, $context)
		 * {
		 *    $category->add_form_field('x_extra_description')->tab('Category');
		 * }
		 * </pre>
		 * @event shop:onExtendCategoryForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCategoryModel
		 * @see shop:onGetCategoryFieldOptions
		 * @see shop:onGetCategoryFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Category $category Specifies the category object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendCategoryForm($category, $context) {}

		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendCategoryForm} event.
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
		 *   Backend::$events->addEvent('shop:onExtendCategoryModel', $this, 'extend_category_model');
		 *   Backend::$events->addEvent('shop:onExtendCategoryForm', $this, 'extend_category_form');
		 *   Backend::$events->addEvent('shop:onGetCategoryFieldOptions', $this, 'get_category_field_options');
		 * }
		 * 
		 * public function extend_category_model($category, $context)
		 * {
		 *   $category->define_column('x_color', 'Color');
		 * }
		 * 
		 * public function extend_category_form($category, $context)
		 * {
		 *   $category->add_form_field('x_color')->tab('Product')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_category_field_options($field_name, $current_key_value)
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
		 * @event shop:onGetCategoryFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCategoryModel
		 * @see shop:onExtendCategoryForm
		 * @see shop:onGetCategoryFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetCategoryFieldOptions($db_name, $field_value) {}
		
		/**
		 * Determines whether a custom radio button or checkbox list option is checked.
		 * This event should be handled if you added custom radio-button and or checkbox list fields with {@link shop:onExtendCategoryForm} event.
		 * @event shop:onGetCategoryFieldState
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCategoryModel
		 * @see shop:onExtendCategoryForm
		 * @see shop:onGetCategoryFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @param Shop_Category $category Specifies the category object
		 * @return boolean Returns TRUE if the field is checked. Returns FALSE otherwise.
		 */
		private function event_onGetCategoryFieldState($db_name, $field_value, $category) {}
			
		/**
		 * Allows you to add a custom sorting field support to the {@link Shop_Category::list_products()} method.
		  *The event handler should accept two parameters: the sorting column name and the product category object. 
		 * It should return the sorting query. This event is often used with {@link shop:onGetProductSortColumns} 
		 * to add a custom sorting query for a custom sort field: 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onGetProductSortColumns', $this, 'get_sorting_columns');
		 *   Backend::$events->addEvent('shop:onGetCategoryProductSortingQuery', $this, 'get_sorting_query');
		 * }
		 * 
		 * public function get_sorting_columns()
		 * {
		 *   return array('x_newfield');
		 * }
		 *  
		 * public function get_sorting_query($column, $category)
		 * {
		 *   if ($column != 'x_newfield')
		 *     return null;
		 * 
		 *   $sort_str = ' custom sorting clause ';
		 * 
		 *   return $sort_str;
		 * }
		 * </pre>
		 * @event shop:onGetCategoryProductSortingQuery
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param string $sorting_column Specifies the sorting column name.
		 * @param Shop_Category $category Specifies the category object
		 * @return string Returns the sorting query string.
		 */
		private function event_onGetCategoryProductSortingQuery($sorting_column, $category) {}

		/**
		 * Triggered after a category record is fetched from the database.
		 * @event shop:onAfterCategoryRecordFetch
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Category $category Specifies the category object.
		 */
		private function event_onAfterCategoryRecordFetch($category) {}
	}
?>