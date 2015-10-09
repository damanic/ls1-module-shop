<?

	/**
	 * This is a base class for currency converters.
	 * It provides automatic daily caching for currency
	 * exchange rates.
	 */
	abstract class Shop_CurrencyConverterBase
	{
		/**
		 * Returns information about the currency converter.
		 * @return array Returns array with two keys: name and description
		 * array('name'=>'My converter name', 'description'=>'My converter description')
		 */
		abstract public function get_info();
		
		/**
		 * Returns exchange rate for two currencies
		 * This method must be implemented in a specific currency converter.
		 * @param $parameters_base Active Record object to read parameters from
		 * @param string $from_currency Specifies 3 character ISO currency code (e.g. USD) to convert from.
		 * @param string $to_currency Specifies a currency code to convert to
		 * @return number 
		 */
		abstract public function get_exchange_rate($host_obj, $from_currency, $to_currency);
		
		/**
		 * Builds the converter configuration user interface 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 *
		 * Do not add tabs to the configuration form. All fields you add in the method
		 * will be placed to the Configuration tab.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 */
		public function build_config_ui($host_obj)
		{
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
		}
		
		/**
		 * Initializes configuration data when the converter object is created for the first time
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host_obj)
		{
		}
	}

?>