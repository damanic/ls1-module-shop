<?php

	/**
	 * Represents the store shipping configuration. 
	 * This class contains the shipping configuration information that you can edit on the System/Shipping Configuration page.
	 * 
	 * @property Shop_Country $country A reference to the shipping origin country.
	 * @property Shop_CountryState $state A reference to the shipping origin state.
	 * @property string $street_addr Specifies the shipping origin street address.
	 * @property string $zip_code Specifies the shipping origin ZIP/postal code.
	 * @property string $city Specifies the shipping origin city name.
	 * @property string $sender_first_name Specifies the sender first name.
	 * @property string $sender_last_name Specifies the sender last name.
	 * @property string $sender_company Specifies the sender company name.
	 * @property string $sender_phone Specifies the sender phone number.
	 * @property string $weight_unit Specifies the weight unit to use in the shipping cost calculations.
	 * Allowed values are: <ul><li>LBS</li><li>KGS</li></ul>
	 * @property string $dimension_unit Specifies the dimensions unit to use in the shipping cost calculations.
	 * Allowed values are: <ul><li>IN</li><li>CM</li></ul>
	 * @property Shop_Country $default_country A reference to the default shipping country.
	 * @property Shop_CountryState $default_state A reference to the default shipping state.
	 * @property string $default_shipping_zip Specifies the default shipping ZIP/postal code.
	 * @property string $default_shipping_city Specifies the default shipping city name.
	 * @property boolean $display_shipping_service_errors Determines if shipping service errors should be displayed to visitors.
	 * 
	 * @documentable
	 * @see http://lemonstand.com/docs/configuring_the_shipping_parameters/ Configuring the shipping parameters
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ShippingParams extends Db_ActiveRecord 
	{
		public $table_name = 'shop_shipping_params';
		public static $loadedInstance = null;

		public $belongs_to = array(
			'country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'country_id'),
			'state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'state_id'),
			'default_country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'default_shipping_country_id'),
			'default_state'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'default_shipping_state_id')
		);

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		/**
		 * Returns the class instance.
		 * Use this method instead of the constructor to create instances of the class.
		 * @documentable
		 * @param boolean $edit_mode This parameter is used by the system internally.
		 * @return Shop_ShippingParams Returns the Shop_ShippingParams object.
		 */
		public static function get($edit_mode = false)
		{
			if (self::$loadedInstance)
				$result = self::$loadedInstance;
			else
				$result = self::$loadedInstance = self::create()->order('id desc')->find();
			
			if (!$edit_mode)
			{
				$updated_objects = Backend::$events->fireEvent('shop:onGetShippingParams', $result);
				foreach ($updated_objects as $obj)
				{
					if (is_object($obj))
						$result = $obj;
				}
			}
				
			
			return $result;
		}

		public static function isConfigured()
		{
			$obj = self::get();
			if (!$obj)
				return false;

			return strlen($obj->zip_code);
		}

		public function define_columns($context = null)
		{
			$this->define_relation_column('country', 'country', 'Country ', db_varchar, '@name')->listTitle('Country')->defaultInvisible()->validation()->required();
			$this->define_relation_column('state', 'state', 'State ', db_varchar, '@name')->listTitle('State')->defaultInvisible();
			
			$this->define_column('street_addr', 'Street Address')->validation()->fn('trim')->required('Please specify the origin street address');

			$this->define_column('zip_code', 'Zip Code')->validation()->fn('trim')->required('Please specify ZIP or postal code');
			$this->define_column('city', 'City')->validation()->fn('trim');

			$this->define_column('sender_first_name', 'Sender First Name')->validation()->fn('trim')->required('Please enter the sender first name');
			$this->define_column('sender_last_name', 'Sender Last Name')->validation()->fn('trim')->required('Please enter the sender last name');
			$this->define_column('sender_company', 'Company')->validation()->fn('trim');
			$this->define_column('sender_phone', 'Phone')->validation()->fn('trim');
			
			$this->define_column('weight_unit', 'Weight Unit')->validation()->fn('trim')->required('Please select weight unit');
			$this->define_column('dimension_unit', 'Dimension Unit')->validation()->fn('trim')->required('Please select weight unit');
			
			$this->define_relation_column('default_country', 'default_country', 'Country ', db_varchar, '@name');
			$this->define_relation_column('default_state', 'default_state', 'State ', db_varchar, '@name');
			
			$this->define_column('default_shipping_zip', 'Zip Code')->validation()->fn('trim');
			$this->define_column('default_shipping_city', 'City')->validation()->fn('trim');
			
			$this->define_column('display_shipping_service_errors', 'Display shipping service errors')->validation()->fn('trim');
			
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('country')->tab('Origin')->comment('The country list displays only enabled countries. You can manage countries on the Settings/Countries and States page.', 'above');
			$this->add_form_field('state')->tab('Origin');
			$this->add_form_field('street_addr')->tab('Origin')->renderAs(frm_textarea)->size('small');
			$this->add_form_field('zip_code', 'left')->tab('Origin');
			$this->add_form_field('city', 'right')->tab('Origin');
			
			$this->add_form_section('The following parameters are required for the shipping label printing feature.')->tab('Sender');
			$this->add_form_field('sender_first_name', 'left')->tab('Sender');
			$this->add_form_field('sender_last_name', 'right')->tab('Sender');
			$this->add_form_field('sender_company')->tab('Sender');
			$this->add_form_field('sender_phone')->tab('Sender')->comment('10 digits required (including area code), with no punctuation. Use format: 2125551234.', 'above');

			$this->add_form_field('weight_unit')->tab('Units')->renderAs(frm_dropdown)->emptyOption('<please select>');
			$this->add_form_field('dimension_unit')->tab('Units')->renderAs(frm_dropdown)->emptyOption('<please select>');
			
			$this->add_form_custom_area('copy_origin')->tab('Default Shipping Location');
			$this->add_form_field('default_country', 'left')->tab('Default Shipping Location')->emptyOption('<not assigned>');
			$this->add_form_field('default_state', 'right')->tab('Default Shipping Location')->emptyOption('<not assigned>');
			$this->add_form_field('default_shipping_zip', 'left')->tab('Default Shipping Location');
			$this->add_form_field('default_shipping_city', 'right')->tab('Default Shipping Location');
			
			$this->add_form_field('display_shipping_service_errors')->tab('Parameters')->comment('Display shipping service errors like "Please specify a valid ZIP code" on the front-end website. This feature should be implemented in the front-end partials. Please refer to the <a href="http://lemonstand.com/docs/creating_shipping_method_partial/" target="_blank">documentation</a> for details.', 'above', true);
		}
		
		public function get_country_options($key_value=-1)
		{
			$countries = Shop_Country::get_object_list($this->country_id);
			$result = array();
			foreach ($countries as $country)
				$result[$country->id] = $country->name;
				
			return $result;
		}

		public function get_default_country_options($key_value=-1)
		{
			$countries = Shop_Country::get_object_list($this->default_shipping_country_id);
			$result = array();
			foreach ($countries as $country)
				$result[$country->id] = $country->name;
				
			return $result;
		}

		public function get_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}

			$country_id = $this->country_id;
			if (!strlen($this->country_id))
			{
				$countries = $this->get_default_country_options();
				if ($countries)
				{
					$country_ids = array_keys($countries);
					$country_id = $country_ids[0];
				}
			}

			$states = Shop_CountryState::get_object_list($country_id);
			$result = array();
			foreach ($states as $state)
				$result[$state->id] = $state->name;
				
			return $result;
		}
		
		public function get_default_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}

			$states = Shop_CountryState::get_object_list($this->default_shipping_country_id);
			$result = array();
			foreach ($states as $state)
				$result[$state->id] = $state->name;
				
			return $result;
		}

		public function get_weight_unit_options($key_value = -1)
		{
			$units = array(
				'LBS'=>'Pounds',
				'KGS'=>'Kilograms'
			);
			
			if ($key_value != -1)
				return array_key_exists($key_value, $units) ? $units[$key_value] : null;
			
			return $units;
		}
		
		public function get_dimension_unit_options($key_value = -1)
		{
			$units = array(
				'IN'=>'Inches',
				'CM'=>'Centimeters'
			);

			if ($key_value != -1)
				return array_key_exists($key_value, $units) ? $units[$key_value] : null;
			
			return $units;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		/*
		 * Event descriptions
		 */

		/**
		 * Allows to override the store shipping parameters.
		 * The event handler should accept a single argument - the shipping parameters object loaded from the database.
		 * To override the parameters the method should return the updated object. Note that the updated parameters will
		 * be used in all subsequent calls. If you want the original parameters to be unchanged you may want to clone the
		 * shipping parameters object inside the handler and return the updated clone.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onGetShippingParams', $this, 'get_shipping_params');
		 * }
		 *  
		 * public function get_shipping_params($params)
		 * {
		 *   $result = clone $params;
		 *   $result->sender_first_name = 'John';
		 *   
		 *   return $result;
		 * }
		 * </pre>
		 * @event shop:onGetShippingParams
		 * @see http://lemonstand.com/docs/configuring_the_shipping_parameters/ Configuring the shipping parameters
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_ShippingParams $params A reference to the {@link Shop_ShippingParams} object loaded from the database.
		 */
		private function event_onGetShippingParams($params) {}
	}
?>