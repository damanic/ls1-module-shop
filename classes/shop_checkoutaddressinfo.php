<?

	/**
	 * Represents a customer shipping or billing address which the customer provides during the checkout process.
	 * Normally you don't need to create instances of that class. LemonStand provides the access to the class object
	 * during the checkout process. Please refer to the {@link action@shop:checkout} action description for details.
	 * @documentable
	 * @see action@shop:checkout
	 * @author LemonStand eCommerce Inc.
	 * @package shop.classes
	 */
	class Shop_CheckoutAddressInfo
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
		
		public $act_as_billing_info = true;

		/**
		 * Loads address information from a {@link Shop_Customer customer} object.
		 * @documentable
		 * @param Shop_Customer $customer Specifies a customer object to load address information from.
		 */
		public function load_from_customer($customer)
		{
			if ($this->act_as_billing_info)
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
		public function save_to_customer($customer)
		{
			if ($this->act_as_billing_info)
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
		 * Saves address information to an {@link Shop_Order order} object.
		 * The method doesn't save the order object to the database.
		 * @documentable
		 * @param Shop_Order $order Specifies an order object to save address information to.
		 */
		public function save_to_order($order)
		{
			if ($this->act_as_billing_info)
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

		/**
		 * Returns the address information as a formatted address string.
		 * @documentable
		 * @return string Returns the address as string.
		 */ 
		public function as_string()
		{
			if (!strlen($this->first_name))
				return null;

			if (strlen($this->state))
			{
				$state = Shop_CountryState::create()->find($this->state);
				if (!$state)
					throw new Exception('State not found');
			}

			$country = Shop_Country::create()->find($this->country);
			if (!$country)
				throw new Exception('Country not found');

			$parts = array();
			$parts[] = $this->first_name.' '.$this->last_name;
			if (strlen($this->company))
				$parts[] = $this->company;
			
			$parts[] = $this->zip;
			$parts[] = $this->street_address;
			$parts[] = $this->city;
			$parts[] = $country->name;
			if (strlen($this->state))
				$parts[] = $state->name;
			
			$result = array();
			$result[] = implode(', ', $parts);
			
			if (strlen($this->email))
				$result[] = $this->email;

			if (strlen($this->phone))
				$result[] = 'Phone: '.$this->phone;
				
			return implode('. ', $result);
		}
		
		/**
		 * Copies the address from another Shop_CheckoutAddressInfo object.
		 * @documentable
		 * @param Shop_CheckoutAddressInfo $address Specifies the address information object to copy the address from.
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
		 * Loads the object properties from POST parameters.
		 * The POST parameter names should match the class property names.
		 * @documentable
		 * @param Shop_Customer $customer Specifies the customer object if it is presented.
		 */
		public function set_from_post($customer = null)
		{
			$validation = new Phpr_Validation();
			
			if(!$customer || isset($_POST['first_name']))
				$validation->add('first_name', 'First Name')->fn('trim')->required("Please specify a first name.");
			
			if(!$customer || isset($_POST['last_name']))
				$validation->add('last_name', 'Last Name')->fn('trim')->required("Please specify a last name.");
			
			if (!$customer)
			{
				if ($this->act_as_billing_info)
					$validation->add('email', 'Email')->fn('trim')->fn('mb_strtolower')->required("Please specify an email address.")->email();
			}
			
			$validation->add('company', 'Company')->fn('trim');
			$validation->add('phone', 'Phone')->fn('trim');
			$validation->add('street_address', 'Street Address')->fn('trim')->required("Please specify a street address.");
			$validation->add('city', 'City')->fn('trim')->required("Please specify a city.");
			$validation->add('zip', 'Zip/Postal Code')->fn('trim')->required("Please specify a ZIP/postal code.");
			$validation->add('country', 'Country')->required("Please select a country.");
			
			if (!$validation->validate($_POST))
				$validation->throwException();

			if(!$customer || isset($_POST['first_name']))
				$this->first_name = $validation->fieldValues['first_name'];
			
			if(!$customer || isset($_POST['last_name']))
				$this->last_name = $validation->fieldValues['last_name'];
			
			if (!$customer)
			{
				if ($this->act_as_billing_info)
					$this->email = $validation->fieldValues['email'];
			}

			$this->company = $validation->fieldValues['company'];
			$this->phone = $validation->fieldValues['phone'];
			$this->street_address = $validation->fieldValues['street_address'];
			$this->city = $validation->fieldValues['city'];
			$this->zip = $validation->fieldValues['zip'];
			$this->country = $validation->fieldValues['country'];
			$this->is_business = post('is_business');
			$this->state = post('state');
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
	}

?>