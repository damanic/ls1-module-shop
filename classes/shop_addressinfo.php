<?

	/**
	 * Represents a customer shipping or billing address.
	 * @documentable
	 * @author Matt Manning (github:damanic)
	 * @package shop.classes
	 */
	class Shop_AddressInfo
	{
		/**
		 * @var string Specifies the customer's first name.
		 * @documentable
		 */
		public $first_name;

		/**
		 * @var string Specifies the customer's last name.
		 * @documentable
		 */
		public $last_name;

		/**
		 * @var string Specifies the customer's email address.
		 * @documentable
		 */
		public $email;

		/**
		 * @var string Specifies the customer's company name.
		 * @documentable
		 */
		public $company;

		/**
		 * @var string Specifies the customer's phone number.
		 * @documentable
		 */
		public $phone;

		/**
		 * @var integer Specifies the country identifier.
		 * Note that this property contains a country identifier,
		 * not a reference to a country object. To load the country object and output its name use the following code:
		 * <pre>
		 *   $country = Shop_Country::find_by_id($address_info->country);
		 *   if ($country)
		 *     echo h($country->name);
		 * </pre>
		 * @documentable
		 */
		public $country;

		/**
		 * @var integer Specifies the state identifier.
		 * Note that this property contains a state identifier,
		 * not a reference to a state object. To load the state object and output its name use the following code:
		 * <pre>
		 *   $state = Shop_CountryState::find_by_id($address_info->state);
		 *   if ($state)
		 *     echo h($state->name);
		 * </pre>
		 * @documentable
		 */
		public $state;

		/**
		 * @var string Specifies the customer's street address.
		 * @documentable
		 */
		public $street_address;

		/**
		 * @var string Specifies the customer's city name.
		 * @documentable
		 */
		public $city;

		/**
		 * @var string Specifies the customer's ZIP or postal code.
		 * @documentable
		 */
		public $zip;

		/**
		 * @var boolean Indicates whether the object represents a business address.
		 * @documentable
		 */
		public $is_business;

		/**
		 * @var boolean When set to true, get methods will transliterate foreign characters to latin.
		 * @documentable
		 */
		public $transliterate = false;

		public $transliterate_fields = array(
			'first_name',
			'last_name',
			'company',
			'country',
			'state',
			'street_address',
			'city',
			'zip'
		);

		protected $loaded_relations = null;

		protected $serialized = null;



		/**
		 * Loads address information from a {@link Shop_Customer customer} object.
		 * @documentable
		 * @param Shop_Customer $customer Specifies a customer object to load address information from.
		 */
		public function load_from_customer($customer, $billing=true)
		{
			if ($billing)
			{
				$this->first_name = $customer->first_name;
				$this->last_name = $customer->last_name;
				$this->email = $customer->email;
				$this->company = $customer->company;
				$this->phone = $customer->phone;
				$this->country = $customer->billing_country_id;
				$this->state = $customer->billing_state_id;
				$this->street_address = $customer->billing_street_addr;
				$this->city = $customer->billing_city;
				$this->zip = $customer->billing_zip;
			} else {
				$this->first_name = $customer->shipping_first_name;
				$this->last_name = $customer->shipping_last_name;
				$this->company = $customer->shipping_company;
				$this->phone = $customer->shipping_phone;
				$this->country = $customer->shipping_country_id;
				$this->state = $customer->shipping_state_id;
				$this->street_address = $customer->shipping_street_addr;
				$this->city = $customer->shipping_city;
				$this->zip = $customer->shipping_zip;
				$this->is_business = $customer->shipping_addr_is_business;
			}
		}

		/**
		 * Saves address information to a {@link Shop_Customer customer} object.
		 * The method doesn't save the customer object to the database.
		 * @documentable
		 * @param Shop_Customer $customer Specifies a customer object to save address information to.
		 */
		public function save_to_customer($customer, $billing=true)
		{
			if ($billing)
			{
				$customer->first_name = $this->first_name;
				$customer->last_name = $this->last_name;
				$customer->email = $this->email;
				$customer->company = $this->company;
				$customer->phone = $this->phone;
				$customer->billing_country_id = $this->country;
				$customer->billing_state_id = $this->state;
				$customer->billing_street_addr = $this->street_address;
				$customer->billing_city = $this->city;
				$customer->billing_zip = $this->zip;
			} else {
				$customer->shipping_first_name = $this->first_name;
				$customer->shipping_last_name = $this->last_name;
				$customer->shipping_company = $this->company;
				$customer->shipping_phone = $this->phone;
				$customer->shipping_country_id = $this->country;
				$customer->shipping_state_id = $this->state;
				$customer->shipping_street_addr = $this->street_address;
				$customer->shipping_city = $this->city;
				$customer->shipping_zip = $this->zip;
				$customer->shipping_addr_is_business = $this->is_business;
			}
		}


		/**
		 * Loads address information from a {@link Shop_Order customer} object.
		 * @documentable
		 * @param Shop_Customer $customer Specifies a customer object to load address information from.
		 */
		public function load_from_order($order, $billing=true)
		{
			if ($billing)
			{
				$this->first_name = $order->billing_first_name;
				$this->last_name = $order->billing_last_name;
				$this->email = $order->billing_email;
				$this->company = $order->billing_company;
				$this->phone = $order->billing_phone;
				$this->country = $order->billing_country_id;
				$this->state = $order->billing_state_id;
				$this->street_address = $order->billing_street_addr;
				$this->city = $order->billing_city;
				$this->zip = $order->billing_zip;
			} else {
				$this->first_name = $order->shipping_first_name;
				$this->last_name = $order->shipping_last_name;
				$this->company = $order->shipping_company;
				$this->phone = $order->shipping_phone;
				$this->country = $order->shipping_country_id;
				$this->state = $order->shipping_state_id;
				$this->street_address = $order->shipping_street_addr;
				$this->city = $order->shipping_city;
				$this->zip = $order->shipping_zip;
				$this->is_business = $order->shipping_addr_is_business;
			}
		}

		/**
		 * Saves address information to an {@link Shop_Order order} object.
		 * The method doesn't save the order object to the database.
		 * @documentable
		 * @param Shop_Order $order Specifies an order object to save address information to.
		 */
		public function save_to_order($order, $billing=true)
		{
			if ($billing)
			{
				$order->billing_first_name = $this->first_name;
				$order->billing_last_name = $this->last_name;
				$order->billing_email = $this->email;
				$order->billing_company = $this->company;
				$order->billing_phone = $this->phone;
				$order->billing_country_id = $this->country;
				$order->billing_state_id = $this->state;
				$order->billing_street_addr = $this->street_address;
				$order->billing_city = $this->city;
				$order->billing_zip = $this->zip;
			} else {
				$order->shipping_first_name = $this->first_name;
				$order->shipping_last_name = $this->last_name;
				$order->shipping_company = $this->company;
				$order->shipping_phone = $this->phone;
				$order->shipping_country_id = $this->country;
				$order->shipping_state_id = $this->state;
				$order->shipping_street_addr = $this->street_address;
				$order->shipping_city = $this->city;
				$order->shipping_zip = $this->zip;
				$order->shipping_addr_is_business = $this->is_business;
			}
		}

		/**
		 * Checks whether the address represented with the object matches the address represented with another address information object.
		 * @documentable
		 * @param Shop_CheckoutAddressInfo $address_info Specifies another address information object to compare with.
		 * @return boolean Returns TRUE if the address matches. Returns FALSE otherwise.
		 */
		public function equals($address_info)
		{
			return
				$address_info->first_name == $this->first_name &&
				$address_info->last_name == $this->last_name &&
				$address_info->company == $this->company &&
				$address_info->phone == $this->phone &&
				$address_info->country == $this->country &&
				$address_info->state == $this->state &&
				$address_info->street_address == $this->street_address &&
				$address_info->city == $this->city &&
				$address_info->zip == $this->zip &&
				$address_info->is_business == $this->is_business;
		}

		public function get($field, $default = null){
			$value = null;

			$relation_fields = array(
				'country' => 'Shop_Country',
				'state' => 'Shop_CountryState'
			);
			if( isset($relation_fields[$field])){
				$relation = false;
				if(isset($this->loaded_relations[$field])){
					$relation = $this->loaded_relations[$field];
				} else if(is_numeric($this->{$field})){
					$relation = $relation_fields[$field];
					$id = $this->{$field};
					$relation = $relation::create()->find($id);
				}
				if($relation){
					$this->loaded_relations[$field] = $relation;
					$value = $relation->name;
				}

			}

			if(!$value && property_exists($this, $field)){
				$value = $this->{$field};
			}

			if($this->transliterate && in_array($field, $this->transliterate_fields)){
				$value = $this->transliterate($value);
			}

			return $value ? $value : $default;
		}

		/**
		 * Returns the address information as a formatted address string.
		 * @documentable
		 * @return string Returns the address as string.
		 */
		public function as_string()
		{
			if (!strlen($this->first_name))
				return null;

			$parts = array();

			$name = trim($this->get('first_name').' '.$this->get('last_name'));
			if(!empty($name)) {
				$parts[] = $name;
			}
			$company = $this->get('company');
			if ($company)
				$parts[] = $company;

			$parts[] = $this->get('zip');
			$parts[] = $this->get('street_address');
			$parts[] = $this->get('city');
			$parts[] = $this->get('country');
			$state = $this->get('state');
			if($state) {
				$parts[] = $this->get( 'state' );
			}
			$result = array();
			$result[] = implode(', ', $parts);

			$email = $this->get('email');
			if (strlen($email))
				$result[] = $email;

			$phone = $this->get('phone');
			if (strlen($phone))
				$result[] = $phone;

			return implode('. ', $result);
		}

		/**
		 * Displays the address information
		 * @documentable
		 * @return string Returns the address as string.
		 */
		public function display_address($vars=array(), $options = array()){
			$default_vars = array(
				'street_address',
				'city',
				'state',
				'zip',
				'country',
			);
			$vars = array_merge($default_vars,$vars);

			$default_options = array(
				'html' => true,
				'show_field_names' => false,
			);
			$options = array_merge($default_options, $options);


			$line_end = $options['html'] ? '<br/>' : "\r\n";

			$output = '';
			foreach($vars as $var){
				if(property_exists($this,$var)){
					$field_name = ucwords(str_replace('_',' ',$var));
					$field_value = $this->get($var);
					if($options['show_field_names']) {
						$output .= $options['html'] ? '<b>' . $field_name : $field_name;
						$output .= $options['html'] ? '</b>: ' : ": ";
					}
					$output .=  $options['html'] ? nl2br(h($field_value)) : h($field_value);
					$output .= $options['html'] ? '<br/> ' : "\r\n";
				}
			}

			echo $output;

		}

		/**
		 * Copies the address from another Shop_AddressInfo object.
		 * @documentable
		 * @param Shop_AddressInfo $address Specifies the address information object to copy the address from.
		 */
		public function copy_from($address)
		{
			$this->first_name = $address->first_name;
			$this->last_name = $address->last_name;
			$this->email = $address->email;
			$this->company = $address->company;
			$this->phone = $address->phone;
			$this->country = $address->country;
			$this->state = $address->state;
			$this->street_address = $address->street_address;
			$this->city = $address->city;
			$this->zip = $address->zip;
			$this->is_business = $address->is_business;
		}

		/**
		 * Sets country, state and ZIP codes.
		 * @documentable
		 * @param integer $country_id Specifies the country identifier.
		 * @param integer $state_id Specifies the state identifier.
		 * @param string $zip Specifies the ZIP or postal code.
		 */
		public function set_location($country_id, $state_id, $zip)
		{
			$this->country = $country_id;
			$this->state = $state_id;
			$this->zip = $zip;
		}

		/**
		 * Loads the object properties from default shipping location.
		 * The default shipping location can be configured on {@link http://lemonstand.com/docs/configuring_the_shipping_parameters/ Shipping Configuration} page.
		 * @documentable
		 * @see http://lemonstand.com/docs/configuring_the_shipping_parameters/ Configuring the shipping parameters
		 */
		public function set_from_default_shipping_location()
		{
			$shipping_params = Shop_ShippingParams::get();

			$this->city = $shipping_params->default_shipping_city;
			$this->zip = $shipping_params->default_shipping_zip;
			$this->country = $shipping_params->default_shipping_country_id;
			$this->state = $shipping_params->default_shipping_state_id;
		}


		protected function transliterate($value){
			if ( !method_exists( 'Core_String', 'transliterate' ) ) {
				traceLog( 'Warning: Update CORE version >= 1.13.26 to support transliteration' );
				return $value;
			}

			return Core_String::transliterate($value);

		}



		protected function get_public_properties(){
			$reflection = new ReflectionObject($this);
			$properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

			$property_array = array();
			foreach($properties as $property){
				$property_array[] = $property->getName();
			}
			return $property_array;
		}

		/*
		 * Magic functions facilitate storage of AddressInfo object in SESSION
		 */

		public function __sleep() {
			$record = array();
			$properties = $this->get_public_properties();

			foreach($properties as $property_name){
				$property_value = $this->{$property_name};

				if(is_object($property_value)){
					//objects are not expected in AddressInfo, attempt to save record ID instead
					$property_value = property_exists($property_value, 'id') ? $property_value->id : null;
				}

				$record[$property_name] = $this->{$property_name};
			}

			$this->serialized = $record;
			return array('serialized');
		}

		public function __wakeup() {
			if (isset($this->serialized)) {
				$properties = $this->get_public_properties();
				foreach($this->serialized as $key => $value){
					if (in_array($key, $properties))
						$this->{$key} = $value;
				}
				$this->serialized = null;
			}
		}


		/**
		 * @deprecated Set the $transliterate property to true, and use get() methods instead!
		 *
		 * Returns a new address info object with transliterated values
		 * Note: The returned object will replace country/state IDs with transliterated names.
		 *
		 * @return Shop_AddressInfo Returns an address info object with transliterated values
		 */
		public function get_transliterated_info(){

			$this->transliterate = true;

			if ( !method_exists( 'Core_String', 'transliterate' ) ) {
				traceLog( 'Warning: Update CORE version >= 1.13.26 to support transliteration' );
				return $this;
			}

			$properties = $this->get_public_properties();

			$info = new self();
			foreach($properties as $property_name){
				$info->{$property_name} = $this->get($property_name);
			}

			$this->transliterate = false;

			return $info;
		}

	}




?>