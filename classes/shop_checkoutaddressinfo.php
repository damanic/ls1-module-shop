<?

/**
 * Represents a customer shipping or billing address which the customer provides during the checkout process.
 * Normally you don't need to create instances of that class. LemonStand provides the access to the class object
 * during the checkout process. Please refer to the {@link action@shop:checkout} action description for details.
 * @documentable
 * @see     action@shop:checkout
 * @author  LemonStand eCommerce Inc.
 * @package shop.classes
 */
class Shop_CheckoutAddressInfo extends Shop_AddressInfo {
	public $act_as_billing_info = true;

	/**
	 * Loads address information from a {@link Shop_Customer customer} object.
	 * @documentable
	 *
	 * @param Shop_Customer $customer Specifies a customer object to load address information from.
	 * @param boolean Determines use of billing address or shipping address.
	 */
	public function load_from_customer( $customer, $billing = null ) {
		$use_billing = empty($billing) ? $this->act_as_billing_info : $billing;
		if ( $use_billing ) {
			parent::load_from_customer( $customer, true );
		} else {
			parent::load_from_customer( $customer, false );
		}
	}

	/**
	 * Saves address information to a {@link Shop_Customer customer} object.
	 * The method doesn't save the customer object to the database.
	 * @documentable
	 *
	 * @param Shop_Customer $customer Specifies a customer object to save address information to.
	 * @param boolean Determines use of billing address or shipping address.
	 */
	public function save_to_customer( $customer, $billing = null ) {
		$use_billing = empty($billing) ? $this->act_as_billing_info : $billing;
		parent::save_to_customer($customer, $use_billing);
	}

	/**
	 * Saves address information to an {@link Shop_Order order} object.
	 * The method doesn't save the order object to the database.
	 * @documentable
	 *
	 * @param Shop_Order $order Specifies an order object to save address information to.
	 * @param boolean Determines use of billing address or shipping address.
	 */
	public function save_to_order( $order, $billing = null ) {
		$use_billing = empty($billing) ? $this->act_as_billing_info : $billing;
		parent::save_to_order($order, $use_billing);
	}


	/**
	 * Loads the object properties from POST parameters.
	 * The POST parameter names should match the class property names.
	 * @documentable
	 *
	 * @param Shop_Customer $customer Specifies the customer object if it is presented.
	 */
	public function set_from_post( $customer = null ) {

		$data = $_POST;
		if($customer){
			$data['first_name'] = post('first_name', $customer->first_name);
			$data['last_name'] = post('last_name', $customer->last_name);
			$data['email'] = post('email', $customer->email);
		}
		$validation = $this->validate($data);
		$this->first_name = $validation->fieldValues['first_name'];
		$this->last_name = $validation->fieldValues['last_name'];
		$this->email = $validation->fieldValues['email'];
		$this->company        = $validation->fieldValues['company'];
		$this->phone          = $validation->fieldValues['phone'];
		$this->street_address = $validation->fieldValues['street_address'];
		$this->city           = $validation->fieldValues['city'];
		$this->zip            = $validation->fieldValues['zip'];
		$this->country        = $validation->fieldValues['country'];
		$this->is_business    = post( 'is_business' );
		$this->state          = post( 'state' );
	}

	public function validate($data=null){
		$validation = new Phpr_Validation();
		$validation->add( 'first_name', 'First Name' )->fn( 'trim' )->required( "Please specify a first name." );
		$validation->add( 'last_name', 'Last Name' )->fn( 'trim' )->required( "Please specify a last name." );
		$validation->add( 'email', 'Email' )->fn( 'trim' )->fn( 'mb_strtolower' )->required( "Please specify an email address." )->email();
		$validation->add( 'company', 'Company' )->fn( 'trim' );
		$validation->add( 'phone', 'Phone' )->fn( 'trim' );
		$validation->add( 'street_address', 'Street Address' )->fn( 'trim' )->required( "Please specify a street address." );
		$validation->add( 'city', 'City' )->fn( 'trim' )->required( "Please specify a city." );
		$validation->add( 'zip', 'Zip/Postal Code' )->fn( 'trim' )->required( "Please specify a ZIP/postal code." );
		$validation->add( 'country', 'Country' )->required( "Please select a country." );

		$data = $data ? $data : $this;
		if ( !$validation->validate( $data ) ) {
			$validation->throwException();
		}
		return $validation;
	}

}

?>