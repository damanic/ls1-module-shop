<?php

	/**
	 * Represents a product manufacturer.
	 * This class has fields and methods for accessing a manufacturer name, address and contact information. 
	 * Objects of this class are accessible through the {@link Shop_Product::manufacturer manufacturer} field of the {@link Shop_Product} class. 
	 * 
	 * You can use the following code for displaying a list of manufacturers on a page:
	 * <pre>
	 * <? $manufacturers = Shop_Manufacturer::create()->order('name')->find_all(); ?>
	 * <ul>
	 *   <? foreach ($manufacturers as $manufacturer): ?>
	 *     <li><?= h($manufacturer->name) ?></li>
	 *   <? endforeach ?>
	 * </ul>
	 * </pre>
	 * Alternatively you can use the {@link action@shop:manufacturers} action for creating the {@link http://lemonstand.com/docs/manufacturer_details_page Manufacturer List Page}.
	 * 
	 * @property integer $id Specifies the manufacturer record identifier.
	 * @property string $name Specifies the manufacturer name.
	 * @property string $description Specifies the manufacturer description.
	 * @property string $address Specifies the manufacturer address.
	 * @property string $city Specifies the manufacturer city.
	 * @property string $zip Specifies the manufacturer ZIP/postal code.
	 * @property string $phone Specifies the manufacturer phone number.
	 * @property string $fax Specifies the manufacturer fax number.
	 * @property string $email Specifies the manufacturer email address.
	 * @property string $url Specifies the manufacturer website URL.
	 * @property Shop_Country $country Specifies the manufacturer country. 
	 * @property Shop_CountryState $state Specifies the manufacturer country state. 
	 * @property boolean $is_disabled Determines whether the manufacturer is disabled. 
	 * Disabled manufacturers are not displayed by the {@link action@shop:manufacturers} and {@link action@shop:manufacturer} CMS actions.
	 * @property Db_DataCollection $logo A collection of the manufacturer logo images. 
	 * The collection always contains zero or one element - an object of {@link Db_File} class. See {@link Shop_Manufacturer::logo_url() logo_url()} method.
	 * @property string $url_name Specifies the manufacturer URL name.
	 * @documentable
	 * @see http://lemonstand.com/docs/displaying_product_manufacturer_information Displaying product manufacturer information
	 * @see http://lemonstand.com/docs/manufacturer_list_page Creating the manufacturer list page
	 * @see http://lemonstand.com/docs/manufacturer_details_page Manufacturer details page
	 * @see action@shop:manufacturers
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_Manufacturer extends Db_ActiveRecord
	{
		public $table_name = 'shop_manufacturers';
		
		public $belongs_to = array(
			'country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'country_id'),
			'state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'state_id')
		);

		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public $calculated_columns = array( 
			'product_num'=>array('sql'=>'select count(*) from shop_products where
				shop_products.manufacturer_id=shop_manufacturers.id and (grouped is null or grouped=0)', 'type'=>db_number)
		);
		
		public $has_many = array(
			'logo'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Manufacturer' and field='logo'", 'order'=>'id', 'delete'=>true),
			'products'=>array('class_name'=>'Shop_Product', 'order'=>'shop_products.name', 'conditions'=>'((shop_products.enabled=1 and (shop_products.grouped is null or shop_products.grouped=0) and not (
				shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock=0))
				)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
				and not (
					grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.total_in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.total_in_stock=0))
			)))', 'foreign_key'=>'manufacturer_id')
		);

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('description', 'Description')->invisible()->validation()->fn('trim');
			$this->define_multi_relation_column('logo', 'logo', 'Logo', '@name')->invisible();
			
			$this->define_column('address', 'Street Address')->validation()->fn('trim');
			$this->define_column('city', 'City')->validation()->fn('trim');
			$this->define_column('zip', 'ZIP/Postal Code')->validation()->fn('trim');
			$this->define_column('phone', 'Phone Number')->validation()->fn('trim');
			$this->define_column('fax', 'Fax Number')->validation()->fn('trim');
			$this->define_relation_column('country', 'country', 'Country ', db_varchar, '@name');
			$this->define_relation_column('state', 'state', 'State ', db_varchar, '@name');
			$this->define_column('email', 'Email')->validation()->fn('trim')->email(true);
			$this->define_column('url', 'Website URL')->validation()->fn('trim');
			$this->define_column('url_name', 'URL Name')->validation()->fn('trim')->fn('mb_strtolower')->regexp('/^[0-9a-z_-]*$/i', 'URL Name can contain only latin characters, numbers and signs -, _, -')->unique('The URL Name "%s" already in use. Please select another URL Name.')->required('Please specify the manufacturer URL name.');
			$this->define_column('product_num', 'Products');
			$this->define_column('is_disabled', 'Disabled');

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendManufacturerModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name', 'left')->tab('Name and Description');
			$this->add_form_field('url_name', 'right')->tab('Name and Description');
			$this->add_form_field('is_disabled')->tab('Name and Description')->comment('Disabled manufacturers are not displayed on the front-end store.');

			$field = $this->add_form_field('description')->tab('Name and Description')->renderAs(frm_html)->size('small');
			$editor_config = System_HtmlEditorConfig::get('shop', 'shop_manufacturers');
			$editor_config->apply_to_form_field($field);
			
			$this->add_form_field('address')->tab('Address and Contacts')->renderAs(frm_textarea)->size('small');
			$this->add_form_field('city', 'left')->tab('Address and Contacts');
			$this->add_form_field('zip', 'right')->tab('Address and Contacts');
			$this->add_form_field('country', 'left')->tab('Address and Contacts')->emptyOption('<select>');
			$this->add_form_field('state', 'right')->tab('Address and Contacts')->emptyOption('<select>');

			$this->add_form_field('phone', 'left')->tab('Address and Contacts');
			$this->add_form_field('fax', 'right')->tab('Address and Contacts');
			$this->add_form_field('email', 'left')->tab('Address and Contacts');
			$this->add_form_field('url', 'right')->tab('Address and Contacts');
			
			$this->add_form_field('logo')->renderAs(frm_file_attachments)->renderFilesAs('single_image')->addDocumentLabel('Upload logo')->tab('Logo')->noAttachmentsLabel('Logo is not uploaded')->noLabel()->imageThumbSize(150)->fileDownloadBaseUrl(url('ls_backend/files/get/'));
			Backend::$events->fireEvent('shop:onExtendManufacturerForm', $this, $context);
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
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetManufacturerFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetManufacturerFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public function get_country_options($key_value=-1)
		{
			return $this->list_countries($key_value);
		}
		
		protected function list_countries($key_value=-1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_Country::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			$records = Db_DbHelper::objectArray('select * from shop_countries order by name');
			$result = array();
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}
		
		public function get_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			return $this->list_states($this->country_id);
		}
		
		public function list_states($country_id)
		{
			if (!$country_id || !Shop_Country::create()->find($country_id))
			{
				$obj = Shop_Country::create()->order('name')->find();
				if ($obj)
					$country_id = $obj->id;
			}

			$states = Db_DbHelper::objectArray(
				'select * from shop_states where country_id=:country_id order by name',
				array('country_id'=>$country_id)
			);
			
			$result = array();
			foreach ($states as $state)
				$result[$state->id] = $state->name;
				
			return $result;
		}
		
		public function set_default_country()
		{
			$this->country_id = Db_UserParameters::get('manufacturer_def_country');
			$this->state_id = Db_UserParameters::get('manufacturer_def_state');
		}
		
		public function before_delete($id=null)
		{
			if ($this->product_num)
				throw new Phpr_ApplicationException("The manufacturer cannot be deleted because {$this->product_num} products(s) refer to it.");
		}

		/**
		 * Returns an URL of the manufacturer logo image thumbnail.
		 * The method returns NULL if the logo was not uploaded. Use this method for displaying a manufacturer logo. 
		 * You can use exact integer values for the <em>$width</em> and <em>$height</em> parameters, or word 'auto' for automatic image scaling.
		 * The <em>$as_jpeg</em> parameter allows you to generate PNG images with transparency support. 
		 * By default the parameter value is TRUE and the method generates a JPEG image. Pass the FALSE value to the parameter to generate a PNG image. 
		 * The $params array allows to pass parameters to image processing modules (which handle the {@link core:onProcessImage} event). 
		 * The following line of code outputs a thumbnail of the manufacturer logo. The thumbnail width is 100 pixels, 
		 * and thumbnail height is calculated by LemonStand to keep the original aspect ratio.
		 * <pre><img src="<?= h($product->manufacturer->logo_url(100, 'auto')) ?>"/></pre>
		 * @documentable
		 * @see Db_File::getThumbnailPath()
		 * @param mixed $width Specifies the thumbnail width. Use the 'auto' word to scale image width proportionally. 
		 * @param mixed $height Specifies the thumbnail height. Use the 'auto' word to scale height width proportionally. 
		 * @param boolean $as_jpeg Determines whether JPEG or PNG image will be created. 
		 * @param array $params A list of parameters. 
		 * @return string Returns the image URL relative to the website root.
		 */
		public function logo_url($width, $height, $returnJpeg = true, $params = array('mode' => 'keep_ratio'))
		{
			if (!$this->logo->count)
				return null;

			return $this->logo[0]->getThumbnailPath($width, $height, $returnJpeg, $params);
		}
		
		/**
		 * Returns a list of the manufacturer products.
		 * The result of this function is an object of the {@link Shop_Product} class. To obtain a collection of all 
		 * manufacturer products call the find_all() methods of the method result.
		 * <pre>$full_product_list = $manufacturer->list_products()->find_all();</pre>
		 * You can pass an array of options to the method parameter. The currently supported option is the <em>sorting</em>. 
		 * By default the product list is sorted by product name. You can sort product them by another field. 
		 * Also, you can sort the product list by multiple fields:
		 * <pre>
		 * $product_list = $manufacturer->list_products(array(
		 *   'sorting'=>array('price', 'name')
		 * ))
		 * </pre>
		 * The supported fields you can sort the products are:
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
		 * $product_list = $manufacturer->list_products(array(
		 *   'sorting'=>array('price desc')
		 * ))
		 * </pre>
		 * You can add custom sorting fields with {@link shop:onGetProductSortColumns} event.
		 * @documentable
		 * @param array $options Specifies the method options.
		 * @return Shop_Product Returns an object of the {@link Shop_Product} class. 
		 */
		public function list_products($options = array())
		{
			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array('name');

			if (!is_array($sorting))
				$sorting = array('name');
				
			$allowed_sorting_columns = Shop_Product::list_allowed_sort_columns();

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));
				
				if (!in_array($test_name, $allowed_sorting_columns))
					continue;

				if (strpos($sorting_column, 'price') !== false)
				{
					$sorting_column = str_replace('price', sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()), $sorting_column);
				}
				if (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
					$sorting_column = 'shop_products.'.$sorting_column;
			}

			$product_obj = $this->products_list;
			$product_obj->reset_order();
			$product_obj->apply_customer_group_visibility()->apply_catalog_visibility();

			$sort_str = implode(', ', $sorting);

			$product_obj->order($sort_str);

			return $product_obj;
		}
		
		/**
		 * Returns a list of product categories the manufacturer products belong to. 
		 * You can pass an array of options to the method. The only supported option is <em>sorting</em. 
		 * By default the category list is sorted by the category name. You can sort categories by another field. 
		 * Also, you can sort the list by multiple fields.
		 * <pre>
		 * $categories = $manufacturer->list_categories(array(
		 *   'sorting'=>array('name', 'code')
		 * ));
		 * </pre>
		 * The supported fields you can sort the categories by are: 
		 * <ul>
		 *   <li><em>name</em> - sort the category list by name</li>
		 *   <li><em>code</em> - sort the category list by the API code</li>
		 *   <li><em>title</em> - sort the category list by title</li>
		 *   <li><em>front_end_sort_order</em> - sort the category list by the sort order defined in the Administration Area manually</li>
		 *   <li><em>rand()</em> - sort categories randomly</li>
		 * </ul>
		 *
		 * You can add <em>desc</em> suffix to the sort field name to enable the descending sorting: 
		 * <pre>
		 * $categories = $manufacturer->list_categories(array(
		 *   'sorting'=>array('name desc')
		 * ));
		 * </pre>
		 * @documentable
		 * @param array $options Specifies the method options.
		 * @return Db_DataCollection Returns a collection of {@link Shop_Category} objects.
		 */
		public function list_categories($options = array())
		{
			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array('name');
		
			if (!is_array($sorting))
				$sorting = array('name');

			$allowed_sorting_columns = array('name', 'code', 'title', 'front_end_sort_order', 'rand()');

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));

				if (!in_array($test_name, $allowed_sorting_columns))
					continue;

				if (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
					$sorting_column = 'shop_categories.'.$sorting_column;
			}
			
			if (!$sorting)
				$sorting = array('name');

			$sort_str = implode(', ', $sorting);
			$obj = new Shop_Category();
			$obj->join('shop_products', 'shop_products.manufacturer_id = '.$this->id);
			$obj->join('shop_products_categories', 'shop_products_categories.shop_product_id=shop_products.id');
			$obj->group('shop_categories.id');
			$obj->order($sort_str);
			$obj->where('shop_products_categories.shop_category_id=shop_categories.id');

			return $obj->find_all();
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		/*
		 * Event descriptions
		 */

		/**
		 * Allows to define new columns in the product manufacturer model.
		 * The event handler should accept two parameters - the manufacturer object and the form 
		 * execution context string. To add new columns to the manufacturer model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the manufacturer object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_manufacturers</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendManufacturerModel', $this, 'extend_manufacturer_model');
		 *   Backend::$events->addEvent('shop:onExtendManufacturerForm', $this, 'extend_manufacturer_form');
		 * }
		 *  
		 * public function extend_manufacturer_model($manufacturer, $context)
		 * {
		 *   $manufacturer->define_column('x_extra_description', 'Extra description');
		 * }
		 *  
		 * public function extend_manufacturer_form($manufacturer, $context)
		 * {
		 *   $manufacturer->add_form_field('x_extra_description')->tab('Name and Description');
		 * }
		 * </pre>
		 * @event shop:onExtendManufacturerModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendManufacturerForm
		 * @see shop:onGetManufacturerFieldOptions
		 * @see shop:onGetManufacturerFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Manufacturer $manufacturer Specifies the manufacturer object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendManufacturerModel($manufacturer, $context) {}
		
		/**
		 * Allows to add new fields to the Create/Edit Manufacturer form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendManufacturerModel} event. 
		 * To add new fields to the manufacturer form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * manufacturer object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendManufacturerModel', $this, 'extend_manufacturer_model');
		 *   Backend::$events->addEvent('shop:onExtendManufacturerForm', $this, 'extend_manufacturer_form');
		 * }
		 *  
		 * public function extend_manufacturer_model($manufacturer, $context)
		 * {
		 *   $manufacturer->define_column('x_extra_description', 'Extra description');
		 * }
		 *  
		 * public function extend_manufacturer_form($manufacturer, $context)
		 * {
		 *   $manufacturer->add_form_field('x_extra_description')->tab('Name and Description');
		 * }
		 * </pre>
		 * @event shop:onExtendManufacturerForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendManufacturerModel
		 * @see shop:onGetManufacturerFieldOptions
		 * @see shop:onGetManufacturerFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Manufacturer $manufacturer Specifies the manufacturer object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendManufacturerForm($manufacturer, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendManufacturerForm} event.
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
		 *   Backend::$events->addEvent('shop:onExtendManufacturerModel', $this, 'extend_manufacturer_model');
		 *   Backend::$events->addEvent('shop:onExtendManufacturerForm', $this, 'extend_manufacturer_form');
		 *   Backend::$events->addEvent('shop:onGetManufacturerFieldOptions', $this, 'get_manufacturer_field_options');
		 * }
		 *  
		 * public function extend_manufacturer_model($manufacturer)
		 * {
		 *   $manufacturer->define_column('x_color', 'Color');
		 * }
		 *  
		 * public function extend_manufacturer_form($manufacturer, $context)
		 * {
		 *   $manufacturer->add_form_field('x_color')->tab('Product Type')->renderAs(frm_dropdown);
		 * }
		 *  
		 * public function get_manufacturere_field_options($field_name, $current_key_value)
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
		 * @event shop:onGetManufacturerFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendManufacturerModel
		 * @see shop:onExtendManufacturerForm
		 * @see shop:onGetManufacturerFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetManufacturerFieldOptions($db_name, $field_value) {}
			
		/**
		 * Determines whether a custom radio button or checkbox list option is checked.
		 * This event should be handled if you added custom radio-button and or checkbox list fields with {@link shop:onExtendManufacturerForm} event.
		 * @event shop:onGetManufacturerFieldState
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendManufacturerModel
		 * @see shop:onExtendManufacturerForm
		 * @see shop:onGetManufacturerFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @param Shop_Manufacturer $manufacturer Specifies the manufacturer object.
		 * @return boolean Returns TRUE if the field is checked. Returns FALSE otherwise.
		 */
		private function event_onGetManufacturerFieldState($db_name, $field_value, $manufacturer) {}
	}

?>