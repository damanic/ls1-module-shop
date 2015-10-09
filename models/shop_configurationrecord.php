<?php

	class Shop_ConfigurationRecord extends Db_ActiveRecord 
	{
		public $table_name = 'shop_configuration';
		public static $loadedInstance = null;
		
		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public static function get()
		{
			if (self::$loadedInstance)
				return self::$loadedInstance;
			
			return self::$loadedInstance = self::create()->order('id desc')->find();
		}

		public function define_columns($context = null)
		{
			$this->define_column('cart_login_behavior', 'Cart Login Behavior');
			
			$this->define_column('search_in_short_descriptions', 'Product short descriptions');
			$this->define_column('search_in_long_descriptions', 'Product long descriptions');
			$this->define_column('search_in_keywords', 'Product META keywords');
			$this->define_column('search_in_categories', 'Categories');
			$this->define_column('search_in_manufacturers', 'Manufacturers');
			$this->define_column('search_in_sku', 'Product SKU');
			$this->define_column('search_in_grouped_products', 'Grouped products');
			$this->define_column('search_in_option_matrix', 'Option Matrix records');
			
			$this->define_column('display_prices_incl_tax', 'Display catalog/cart prices including tax');
			$this->define_column('tax_inclusive_label', 'Tax included label text');
			$this->define_column('tax_inclusive_country_id', 'Tax inclusive label - country');
			$this->define_column('tax_inclusive_state_id', 'Tax inclusive label - state');
			$this->define_column('product_details_behavior', 'Product details page behavior');
			$this->define_column('strict_option_values', 'Strict option values for Option Matrix products');
			
			$this->define_column('nested_category_urls', 'Enable category URL nesting');
			$this->define_column('category_urls_prepend_parent', 'Prepend parent category URL');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('cart_login_behavior')->tab('Cart Settings')->comment('Select a behavior LemonStand should apply when a customer logs into the store, in case if some products have been added to the guest cart before the login.', 'above')->renderAs(frm_radio);

			$this->add_form_field('display_prices_incl_tax')->tab('Tax')->comment('Enable this option if you want LemonStand to apply tax to product prices before displaying them in the catalog, cart and invoices.');
			$this->add_form_field('tax_inclusive_label')->tab('Tax')->comment('For example: Including GST', 'above');
			$this->add_form_field('tax_inclusive_country_id', 'left')->tab('Tax')->comment('Please select a country you want to display the tax included label for', 'above')->renderAs(frm_dropdown)->emptyOption('<any country>');
			$this->add_form_field('tax_inclusive_state_id', 'right')->tab('Tax')->comment('Please select a state you want to display the tax included label for', 'above')->renderAs(frm_dropdown)->emptyOption('<any state>');

			$this->add_form_section('Here you can configure the product search feature. Please select the areas where the the search function should look.')->tab('Product Search');
			$this->add_form_field('search_in_short_descriptions')->tab('Product Search');
			$this->add_form_field('search_in_long_descriptions')->tab('Product Search');
			$this->add_form_field('search_in_categories')->tab('Product Search');
			$this->add_form_field('search_in_manufacturers')->tab('Product Search');
			$this->add_form_field('search_in_keywords')->tab('Product Search');
			$this->add_form_field('search_in_sku')->tab('Product Search');
			$this->add_form_field('search_in_grouped_products')->tab('Product Search')->comment('When this feature is enabled grouped products are displayed as individual products in the search result.', 'below');
			$this->add_form_field('search_in_option_matrix')->tab('Product Search')->comment('When this feature is enabled Option Matrix records are displayed as individual products in the search result.', 'below');
			
			$this->add_form_field('product_details_behavior')->tab('Product Details Page')->renderAs(frm_radio)->comment('Please select a behavior the Product Details page should use to load a default product.', 'above');
			$this->add_form_field('strict_option_values')->tab('Product Details Page')->comment('When this option is enabled, the option drop-down fields on the Product Details page display only option combinations that exist (and enabled) in the product\'s Option Matrix configuration.', 'above');

			$this->add_form_field('nested_category_urls')->tab('Categories')->comment('When the feature is enabled, you can use category URLs like "men/jumpers". Please note that this feature requires a minor update of the category pagination code, described in the <a target="_blank" href="http://lemonstand.com/docs/displaying_a_list_of_products/#nested-urls">documentation</a>.', 'below', true);
			$field = $this->add_form_field('category_urls_prepend_parent')->tab('Categories')->comment('Automatically prepend parent category URL to URLs of children categories.');
			
			if (!$this->nested_category_urls)
				$field->cssClassName('hidden');
		}
		public function get_product_details_behavior_options($key = -1)
		{
			$result = array(
				'first_available'=>array('First available grouped product (default)'=>'When a visitor opens the product details page, the page displays first available grouped product (if any).'),
				'exact'=>array('Specific product'=>'When a visitor opens the product details page, the page displays product specified in the page URL.'),
			);
			
			return $result;
		}
		
		public function get_cart_login_behavior_options($key = -1)
		{
			$result = array(
				'move_and_sum'=>array('Postpone and sum'=>'Move customer cart items to the postponed cart and sum quantities for items matching in the customer cart and in the guest cart'),
				'move_and_max'=>array('Postpone and use maximum quantity'=>'Move customer cart items to the postponed cart and use a maximum quantity for items matching in the customer cart and in the guest cart'),
				'no_move_sum'=>array('Sum'=>'Do not move any items to the postponed cart and sum quantity for items matching in the customer cart and in the guest cart'),
				'no_move_max'=>array('Maximum quantity'=>'Do not move any items to the postponed cart and use a maximum quantity for items matching in the customer cart and in the guest cart'),
				'override'=>array('Override'=>'Delete all items from a customer cart and move all items from the guest cart to the customer cart'),
				'ignore'=>array('Ignore'=>'Ignore items in the guest cart and do not modify the customer cart'),
			);
			
			return $result;
		}
		
		public function get_tax_inclusive_country_id_options($key_value=-1)
		{
			return Shop_Country::get_name_list();
		}

		public function get_tax_inclusive_state_id_options($key_value=-1)
		{
			return Shop_CountryState::get_name_list($this->tax_inclusive_country_id);
		}
		
		public function before_save($deferred_session_key = null)
		{
			if(!$this->nested_category_urls)
				$this->category_urls_prepend_parent = false;
		}
		
		public function after_save()
		{
			Shop_Module::update_catalog_version();
		}
	}

?>