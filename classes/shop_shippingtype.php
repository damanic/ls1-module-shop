<?

	/**
	 * Represents the generic shipping type. 
	 * All other shipping types must be derived from this class
	 */
	abstract class Shop_ShippingType extends Core_XmlController
	{
		/**
		 * Returns information about the shipping type
		 * Must return array with key 'name': array('name'=>'FedEx')
		 * Also the result can contain an optional 'description'
		 * @return array
		 */
		abstract public function get_info();

		/**
		 * Builds the shipping type administration user interface 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param mixed $host_obj ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		abstract public function build_config_ui($host_obj, $context = null);
		
		/**
		 * Builds the user interface for printing the shipping labels.
		 * Implementing this method is not required if no special parameters
		 * are required for printing the shipping label.
		 * 
		 * @param mixed $host_obj ActiveRecord object to add fields to
		 * @param Shop_Order $order Order object.
		 */
		public function build_print_label_ui($host_obj, $order)
		{
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		abstract public function validate_config_on_save($host_obj);
		
		/**
		 * Validates configuration data after it is loaded from database
		 * Use host object to access fields previously added with build_config_ui method.
		 * You can alter field values if you need
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_load($host_obj)
		{
		}

		/**
		 * Initializes configuration data when the shipping option is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host_obj)
		{
		}
		
		/**
		 * Determines whether a list of countries should be displayed in the 
		 * configuration form. For most payment methods the country list should be displayed.
		 */
		public function config_countries()
		{
			return true;
		}
		
		/**
		 * The Discount Engine uses this method for displaying a list of available shipping options on the 
		 * Free Shipping tab of the Cart Price Rule form. 
		 * 
		 * For multi-options shipping methods (like USPS or UPS) this method should return a list of
		 * enabled options in the following format:
		 * array(
		 *   array('name'=>'First class', 'id'=>'first_class'),
		 *   array('name'=>'Priority', 'id'=>'priority'),
		 * )
		 * The options identifiers must match the identifiers returned by the get_quote() method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @return array
		 */
		public function list_enabled_options($host_obj)
		{
			return array();
		}
		
		/**
		 * Returns price of shipping. If shipping type if not applicable, returns null.
		 * If there is only one shipping method available for this shipping option,
		 * returns its quote.
		 * If there is more than one shipping method available, for example UPS Express,
		 * UPS Expedited, UPS Standard, the method must return an array of shipping method names,
		 * identifiers and quotes:
		 * array(
		 * 		'UPS Express'=>array('id'=>'express', 'quote'=>12.29), 
		 * 		'UPS Expedited'=>array('id'=>'expedited, 'quote'=>32.12)
		 * )
		 * The shipping method identifiers must match the identifiers returned by the list_enabled_options() method
		 * @param array $parameters Contains the method parameters. The array has the following keys:
		 *  - host_obj ActiveRecord object containing configuration fields values
		 *  - country_id Specifies shipping country id
		 *  - state_id Specifies shipping state id
		 *  - zip Specifies shipping zip/postal code
		 *  - city Specifies shipping city name
		 *  - total_price Specifies total price of items in the shopping cart
		 *  - total_volume Specifies total volume of items in the shopping cart
		 *  - total_weight Specifies total weight of items in the shopping cart
		 *  - total_item_num Specifies total number of items in the shopping cart
		 *  - cart_items a list of cart items (Shop_CartItem objects)
		 *  - is_business Determines whether the shipping address is a business address
		 *  - currency ISO currency code (three character alpha) for the returned shipping rate. If left blank, shop currency is assumed.
		 * @return mixed
		 */
		abstract public function get_quote($parameters);

		/**
		 * This method should return TRUE if the shipping module supports label printing.
		 * The shipping module must implement the generate_shipping_label() method if this method returns true.
		 */
		public function supports_shipping_labels()
		{
			return false;
		}
		
		/**
		 * Sends request to the server and returns the shipping label data.
		 * The method should also set the order shipping tracking number.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Order $order The order to generate the label form
		 * @param array $parameters Optional list of the shipping method specific parameters.
		 * @return array Returns an array of Shop_ShippingLabel objects representing the shipping labels.
		 */
		public function generate_shipping_labels($host_obj, $order, $parameters = array())
		{
			return null;
		}

		/**
		 * Initializes shipping label parameters for a new order.
		 * @param $host_obj ActiveRecord object containing configuration fields values.
		 * @param Shop_Order $order The order object.
		 */
		public function init_order_label_parameters($host_obj, $order)
		{
			
		}


	}	

