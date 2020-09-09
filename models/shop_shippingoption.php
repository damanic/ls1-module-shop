<?

	/**
	 * Represents a shipping option.
	 * Object of this class is available through the <em>$shipping_method</em> property of the {@link Shop_Order} class. 
	 * Also, a collection of shipping option objects is available on the Shipping Method step of the Checkout process.
	 * @property string $name Specifies the shipping option name.
	 * @property string $description Specifies the shipping option description in plain text format.
	 * @property boolean $taxable Determines whether tax is applicable for the shipping option.
	 * @property float $quote Specifies the shipping quote. 
	 * This property is applicable only during the Checkout process.
	 * @property boolean $is_free Determines whether the shipping option is free
	 * @property boolean $multi_option Indicates whether the option has sub-options. 
	 * Some shipping methods can have multiple options, for example <em>UPS Standard</em> and <em>UPS Express</em>.
	 * @property array $sub_options Contains a list of sub-options of a multi-option shipping method. 
	 * Each element of the array is an object with the following fields:
	 * <ul>
	 *   <li><em>id</em> - specifies the sub-option identifier. Identifiers are specific for each shipping method.</li>
	 *   <li><em>name</em> - specifies the sub-option name.</li>
	 *   <li><em>quote</em> - specifies the sub-option shipping quote.</li>
	 *   <li><em>is_free</em> - indicates whether the sub-option is free</li>
	 * </ul>
	 * @property boolean $multi_option_name Specifies the name of the parent option for sub-options. 
	 * This property is set in the flat shipping option lists. See {@link shop:on_evalShippingRate}, {@link action@shop:checkout}.
	 * @property string $ls_api_code Specifies the option API code. 
	 * @property string $error_hint Contains an error message returned by the shipping service provider. 
	 * By default LemonStand does not return shipping options with errors. You can enable this feature on the System/Settings/Shipping 
	 * Configuration page, on the Parameters tab. If this field is not empty, its content should be displayed 
	 * instead of the option price and radio button.
	 * @property integer $id Specifies the option identifier in the database.
	 * @documentable
	 * @see Shop_Order
	 * @see http://lemonstand.com/docs/checkout_page Checkout page
	 * @see http://lemonstand.com/docs/developing_shipping_modules Developing shipping modules
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ShippingOption extends Db_ActiveRecord
	{
		public $table_name = 'shop_shipping_options';
		public $enabled = 1;
		public $backend_enabled = 1;
		public $taxable = 1;
		public $order;
		
		/**
		 * These fields contains calculated quotes
		 */
		public $quote = 0;
		public $quote_no_tax = 0;
		public $quote_tax_incl = 0;
		public $quote_no_discount = 0;
		public $discount = 0;
		protected $currency_code = null;

		public $sub_options = array();
		public $multi_option = false;
		public $multi_option_id = null;
		public $multi_option_name = null;
		
		/*
		 * The following field can be set during the checkout process by cart price rules
		 */ 
		public $is_free = false;
		
		/*
		 * The following field contains an error hint message - for examlpe "The postal code XXXXX is invalid for AL United States".
		 */
		public $error_hint = null;
		
		protected $shipping_type_obj = null;
		protected $added_fields = array();

		public $fetched_data = array();
		protected $api_added_columns = array();
		protected static $cache = array();
		protected static $customer_group_filter_cache = null;
		protected static $is_taxable_cache = array();

		public $custom_columns = array('shipping_type_name'=>db_text);
		
		public $has_and_belongs_to_many = array(
			'countries'=>array('class_name'=>'Shop_Country', 'join_table'=>'shop_shippingoptions_countries', 'order'=>'name'),
			'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'shop_shippingoptions_customer_groups', 'order'=>'name', 'foreign_key'=>'customer_group_id', 'primary_key'=>'shop_sh_option_id')
		);
		
		protected static $quote_cache = array();

		public static function create()
		{
			return new self();
		}

		/**
		 * Finds a shipping option by its API code.
		 * @documentable
		 * @param string $code Specifies the API code.
		 * @return Shop_ShippingOption Returns the shipping option object. Returns NULL if the record with the specified API code is not found.
		 */
		public static function find_by_api_code($code)
		{
			$code = mb_strtolower($code);
			return self::create()->where('ls_api_code=?', $code)->find();
		}

		/**
		 * Finds a shipping option by its identifier code.
		 * This method uses internal memory caching.
		 * @documentable
		 * @param string $code Specifies the API code.
		 * @return Shop_ShippingOption Returns the shipping option object. Returns NULL if the record with the specified API code is not found.
		 */
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required('Please specify the shipping option name.');
			$this->define_column('shipping_type_name', 'Shipping Type');
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('enabled', 'Enabled on the front-end website');
			$this->define_column('backend_enabled', 'Enabled in the Administration Area');
			$this->define_column('taxable', 'Taxable');

			$this->define_column('handling_fee', 'Handling Fee')->currency(true)->validation();
			
			$this->define_column('ls_api_code', 'LemonStand API Code')->defaultInvisible()->validation()->fn('trim')->fn('mb_strtolower')->unique('Shipping option with the specified LemonStand API code already exists.');
			
			$this->define_column('min_weight_allowed', 'Minimum Weight')->validation();
			$this->define_column('max_weight_allowed', 'Maximum Weight')->validation();

			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			if (!$front_end)
			{
				$this->define_multi_relation_column('countries', 'countries', 'Countries', '@name')->defaultInvisible();
				$this->define_multi_relation_column('customer_groups', 'customer_groups', 'Customer Groups', '@name')->defaultInvisible();
			}
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendShippingOptionModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			if ($context != 'print_label')
			{
				$this->add_form_field('enabled', 'left')->tab('General Parameters')->comment('Make this shipping option available on the front-end website.');
				$this->add_form_field('taxable', 'right')->tab('General Parameters')->comment('Use tax class "Shipping" to specify tax rates for different locations.');
				$backend_enabled = $this->add_form_field('backend_enabled', 'left')->tab('General Parameters')->comment('Make this shipping option available in the Administration area.');
				if($this->enabled)
					$backend_enabled->disabled();

				$this->add_form_field('name')->comment('Name of the shipping option. It will be displayed on the front-end website.', 'above')->tab('General Parameters');
				$this->add_form_field('description')->comment('If provided, it will be displayed on the front-end website.', 'above')->tab('General Parameters')->size('small');

				$this->add_form_field('handling_fee', 'left')->tab('General Parameters')->comment('Please specify a handling fee for this shipping method. The handling fee will be added to the shipping quote.', 'above');
				$this->add_form_field('ls_api_code', 'right')->comment('You can use the API Code for identifying the shipping method in the API calls.', 'above')->tab('General Parameters');

				$this->add_form_field('min_weight_allowed', 'left')->comment('The shipping option will be ignored if the package weight is less than the specified value. Leave the field empty to cancel the minimum weight check.', 'above')->tab('General Parameters');
				$this->add_form_field('max_weight_allowed', 'right')->comment('The shipping option will be ignored if the package weight is more than the specified value. Leave the field empty to cancel the maximum weight check.', 'above')->tab('General Parameters');

				if (!$front_end)
					$this->add_form_field('customer_groups')->tab('Visibility')->comment('Please select customer groups the shipping options should be available for. If no groups are selected, the shipping option will be available for all customer groups.', 'above');

				$obj = $this->get_shippingtype_object();

				if ($obj->config_countries() && !$front_end)
					$this->add_form_field('countries')->tab('Countries')->comment('Countries the shipping method is applicable to. Uncheck all countries to make the shipping method applicable to any country.', 'above')->referenceSort('name');

				$obj->build_config_ui($this, $context);

				if (!$this->is_new_record())
					$this->load_xml_data();
				else
					$this->get_shippingtype_object()->init_config_data($this);
			} else {
				$obj = $this->get_shippingtype_object();
				$obj->build_print_label_ui($this, $this->order);
				$this->load_order_label_xml_data($this->order);
			}
			
			Backend::$events->fireEvent('shop:onExtendShippingOptionForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_custom_field_options');
			}
		}
		
		public function get_custom_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetShippingOptionFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_countries_options($key_value=1)
		{
			$records = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 order by name');
			$result = array();
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}
		
		/**
		 * Throws validation exception on a specified field
		 * @param $field Specifies a field code (previously added with add_field method)
		 * @param $message Specifies an error message text
		 * @param $grid_row Specifies an index of grid row, for grid controls
		 * @param $grid_column Specifies a name of column, for grid controls
		 */
		public function field_error($field, $message, $grid_row = null, $grid_column = null)
		{
			if ($grid_row != null)
			{
				// $rule = $this->validation->getRule($field);
				// if ($rule)
				// 	$rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
				$this->validation->setWidgetData(Db_GridWidget::get_cell_error_data($this, $field, $grid_column, $grid_row));
			}
			
			$this->validation->setError($message, $field, true);
		}
		
		public function before_save($deferred_session_key = null)
		{
			if ($this->enabled)
				$this->backend_enabled = 1;
			
			$this->get_shippingtype_object()->validate_config_on_save($this);
			
			$document = new SimpleXMLElement('<shipping_type_settings></shipping_type_settings>');
			foreach ($this->added_fields as $code=>$form_field)
			{
				$field_element = $document->addChild('field');
				$field_element->addChild('id', $code);
				$field_element->addChild('value', base64_encode(serialize($this->$code)));
			}

			$this->config_data = $document->asXML();
		}

		public function add_field($code, $title, $side = 'full', $type = db_text)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $title)->validation();
			$form_field = $this->add_form_field($code, $side)->tab('Configuration')->optionsMethod('get_added_field_options')->optionStateMethod('get_added_field_option_state');
			$this->added_fields[$code] = $form_field;

			return $form_field;
		}

		public function get_added_field_options($db_name)
		{
			$obj = $this->get_shippingtype_object();
			$method_name = "get_{$db_name}_options";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");

			return $obj->$method_name(-1, $this);
		}

		public function get_added_field_option_state($db_name, $key_value)
		{
			$obj = $this->get_shippingtype_object();
			$method_name = "get_{$db_name}_option_state";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");

			return $obj->$method_name($key_value);
		}

		public function get_shippingtype_object()
		{
			if ($this->shipping_type_obj !== null)
				return $this->shipping_type_obj;

			if (!Phpr::$classLoader->load($this->class_name))
				throw new Phpr_ApplicationException("Class {$this->class_name} not found.");

			$class_name = $this->class_name;

			return $this->shipping_type_obj = new $class_name();
		}

		public function eval_shipping_type_name()
		{
			$obj = $this->get_shippingtype_object();
			$info = $obj->get_info();
			if (array_key_exists('name', $info))
				return $info['name'];

			return null;
		}

		protected static function option_visible_for_customer_group($option_id, $customer_group_id)
		{
			if (self::$customer_group_filter_cache === null)
			{
				self::$customer_group_filter_cache = array();
				$filter_records = Db_DbHelper::objectArray('select * from shop_shippingoptions_customer_groups');
				foreach ($filter_records as $record)
				{
					if (!array_key_exists($record->shop_sh_option_id, self::$customer_group_filter_cache))
						self::$customer_group_filter_cache[$record->shop_sh_option_id] = array();

					self::$customer_group_filter_cache[$record->shop_sh_option_id][] = $record->customer_group_id;
				}
			}

			if (!array_key_exists($option_id, self::$customer_group_filter_cache))
				return true;

			return in_array($customer_group_id, self::$customer_group_filter_cache[$option_id]);
		}

		public function list_enabled_options()
		{
			$options = $this->get_shippingtype_object()->list_enabled_options($this);
			if (!$options)
			{
				$result = array('method_id'=>$this->id, 'method_name'=>$this->name, 'option_id'=>null, 'option_name'=>null);
				$result = (object)$result;

				return array($this->id=>$result);
			}

			$result = array();
			foreach ($options as $option)
			{
				if (!array_key_exists('name', $option) || !array_key_exists('id', $option))
					continue;

				$item = array('method_id'=>$this->id, 'method_name'=>$this->name, 'option_id'=>$option['id'], 'option_name'=>$option['name']);
				$item = (object)$item;

				$result[$this->id.'_'.$item->option_id] = $item;
			}

			return $result;
		}

		public function before_delete($id=null)
		{
			$bind = array(
				'id'=>$this->id
			);

			$count = Db_DbHelper::scalar('select count(*) from shop_orders where shipping_method_id=:id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete this shipping option because there are orders referring to it.');
		}

		public static function is_taxable($id)
		{
			if (array_key_exists($id, self::$is_taxable_cache))
				return self::$is_taxable_cache[$id];

			return self::$is_taxable_cache[$id] = Db_DbHelper::scalar('select taxable from shop_shipping_options where id=:id', array('id'=>$id));
		}

		protected function load_xml_data()
		{
			if (!strlen($this->config_data))
				return;

			$object = new SimpleXMLElement($this->config_data);
			foreach ($object->children() as $child)
			{
				$code = $child->id;
				$value = base64_decode($child->value, true);
				$this->$code = unserialize($value ? $value : $child->value);
				$code_array = (array)$code;
				$this->fetched_data[$code_array[0]] = $this->$code;
			}

			$this->get_shippingtype_object()->validate_config_on_load($this);
		}

		protected function load_order_label_xml_data($order)
		{
			$this->load_xml_data();

			$params = Shop_OrderShippingLabelParams::find_by_order_and_method($order, $this);
			if (!$params)
			{
				$this->get_shippingtype_object()->init_order_label_parameters($this, $order);
				return;
			}

			$parameter_list = $params->get_parameters();

			foreach ($parameter_list as $name=>$value)
				$this->$name = $value;
		}

		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}

		public function supports_shipping_labels()
		{
			return $this->get_shippingtype_object()->supports_shipping_labels();
		}

		public function generate_shipping_labels($order, $parameters)
		{
			/*
			 * Populate the shipping label parameters
			 */

			$obj = $this->get_shippingtype_object();
			$obj->build_print_label_ui($this, $this->order);
			$label_fields = array_keys($this->added_fields);

			/*
			 * Generate shipping labels
			 */

			$this->define_form_fields(null);
			$labels = $obj->generate_shipping_labels($this, $order, $parameters);

			/*
			 * Save order shipping label parameters
			 */

			$label_parameters = array();
			foreach ($label_fields as $label_field)
			{
				if (!isset($parameters[$label_field]))
					continue;

				$label_parameters[$label_field] = $parameters[$label_field];
			}

			Shop_OrderShippingLabelParams::save_parameters($order, $this, $label_parameters);

			/*
			 * Return labels
			 */

			return $labels;
		}

		public function get_grid_autocomplete_values($db_name, $column, $term, $row_data)
		{
			$obj = $this->get_shippingtype_object();
			if ($obj && method_exists($obj, 'get_grid_autocomplete_values'))
				return $obj->get_grid_autocomplete_values($db_name, $column, $term, $row_data);
		}

		public function get_widget_model_class()
		{
			return $this->class_name;
		}


		protected function create_cache_key($data=array()){

			// Add external considerations to cache key
			$event_data = $data;
			$event_data['host_obj'] = $this;
			$event_results = Backend::$events->fireEvent('shop:onAppendShippingQuoteCacheKey', $event_data);
			$append_key  = '';
			foreach($event_results as $string) {
				if(!empty($string) && is_string($string)) {
					$append_key .= $string;
				}
			}

			foreach($data as $key => $value){
				if(is_object($value)){
					if($value instanceof Countable){
						$data[$key] = count($value);
					} else if ($value->id) {
						$data[$key] = $value->id;
					} else {
						$data[$key] = get_class($value);
					}
				}
			}

			return $this->id.base64_encode(serialize($data)).$append_key;
		}
		protected function set_quote_cache($key, $value){
			self::$quote_cache[$key] = $value;

			if (Phpr::$config->get('CACHE_SHIPPING_METHODS', false)) {
				$cache_data = Phpr::$session->get( 'shipping_options_cache' );

				if ( !is_array( $cache_data ) ) {
					$cache_data = array();
				}
				$cache_entry = array('key'=>$key, 'options'=>$value);
				$cache_data[$this->id] = $cache_entry;
				Phpr::$session->set( 'shipping_options_cache', $cache_data );
			}
		}

		protected function get_quote_cache($key){
			if (isset(self::$quote_cache[$key])) {
				return self::$quote_cache[$key];
			}

			if (Phpr::$config->get('CACHE_SHIPPING_METHODS', false)) {
				$cache_data = Phpr::$session->get( 'shipping_options_cache' );
				if ( $cache_data && is_array( $cache_data ) && isset( $cache_data[$this->id] ) && $cache_data[$this->id]['key'] == $key ) {
					return self::$quote_cache[$key] = $cache_data[$this->id]['options'];
				}
			}

			return null;
		}

		protected function _get_quote($params){
			$default_request_params = array(
				'host_obj'       => $this,
				'shipping_info'  => null, //instance of Shop_AddressInfo
				'total_price'    => null,
				'total_volume'   => null,
				'total_weight'   => null,
				'total_item_num' => null,
				'cart_items'     => null,
				'customer_id' 	 => null
			);
			$deprecated_request_params = array(
				'country_id'     => null,
				'state_id'       => null,
				'zip'            => null,
				'city'           => null,
				'is_business'    => null
			);


			$request_params = array_merge($default_request_params,$params);
			$shipping_info = $request_params['shipping_info'];
			if($shipping_info) {
				$request_params['country_id'] = $shipping_info->country;
				$request_params['state_id']   = $shipping_info->state;
				$request_params['zip']        = $shipping_info->zip;
				$request_params['city']       = $shipping_info->city;
				$request_params['is_business']       = $shipping_info->is_business;
			}

			//This cache merely covers repeat requests on the same user session
			$cached_params = array(
				'country_id'=>$request_params['country_id'],
				'state_id'=>$request_params['state_id'],
				'zip'=>$request_params['zip'],
				'city'=>$request_params['city'],
				'total_price'=>$request_params['total_price'],
				'total_volume'=>$request_params['total_volume'],
				'total_weight'=>$request_params['total_weight'],
				'total_item_num'=>$request_params['total_item_num'],
				'is_business'=>$request_params['is_business'],
				'customer_id'=>$request_params['customer_id'],
			);

			$cache_key = $this->create_cache_key($cached_params);
			$cached = $this->get_quote_cache($cache_key);
			if($cached){
				return $cached;
			}

			$quote = $this->fetch_quote($request_params);
			$this->set_quote_cache($cache_key,$quote);
			return $quote;
		}


		/**
		 * Fetches the quote from host provider
		 * @ignore
		 */
		protected function fetch_quote($params){
			$default_request_params = array(
				'host_obj'       => $this,
				'shipping_info'  => null, //instance of Shop_AddressInfo
				'total_price'    => null,
				'total_volume'   => null,
				'total_weight'   => null,
				'total_item_num' => null,
				'cart_items'     => null,
				'customer_id' 	 => null
			);
			$deprecated_request_params = array(
				'country_id'     => null,
				'state_id'       => null,
				'zip'            => null,
				'city'           => null,
				'is_business'    => null
			);
			$request_params = array_merge($default_request_params, $deprecated_request_params);
			$request_params = array_merge( $default_request_params, $params );

			$shipping_info      = $request_params['shipping_info'];
			$cart_items      = $request_params['cart_items'];
			$shipping_type = $this->get_shippingtype_object();

			if ( $shipping_type->config_countries() ) {
				$country_ids = Db_DbHelper::scalarArray( 'select shop_country_id from shop_shippingoptions_countries where shop_shipping_option_id=:id', array( 'id' => $this->id ) );
				if ( $country_ids && !in_array( $request_params['country_id'], $country_ids ) ) {
					return;
				}
			}


			// Prepare event parameters
			$event_params = array(
				'option_id'       => null,
				'option_name'     => null,
				'shipping_option' => $this,
				'handling_fee'    => $this->handling_fee,
				'country_id'      => $request_params['country_id'],
				'state_id'        =>  $request_params['state_id'],
				'zip'             =>  $request_params['zip'],
				'city'            =>  $request_params['city'],
				'total_price'     =>  $request_params['total_price'],
				'total_volume'    =>  $request_params['total_volume'],
				'total_weight'    =>  $request_params['total_weight'],
				'total_item_num'  =>  $request_params['total_item_num'],
				'cart_items'      =>  $cart_items,
				'updated_items'   => $cart_items,
				'is_business'     => $request_params['is_business'],
			);

			$event_results = Backend::$events->fireEvent( 'shop:onBeforeShippingQuote', $this, $event_params );
			foreach ( $event_results as $event_result ) {
				if ( is_array( $event_result ) ) {
					//update the param arrays
					$request_params = array_merge( $request_params, $event_result );
					$event_params   = array_merge( $event_params, $event_result );
				}
			}
			// Overwrite local variables with updated results
			extract( $event_params ); // more info

			$result = $shipping_type->get_quote( $request_params );

			if ( $result === null ) {
				return null;
			}

			$updated_result = null;


			/*
			 * Trigger the shop:onUpdateShippingQuote event
			 */

			if ( !is_array( $result ) ) {
				//quote is assumed to be in shop currency
				$event_params['quote'] = $result;
				$updated_quote         = Backend::$events->fireEvent( 'shop:onUpdateShippingQuote', $this, $event_params );
				foreach ( $updated_quote as $updated_quote_value ) {
					if ( strlen( $updated_quote_value ) ) {
						$result = $updated_quote_value;
						break;
					}
				}
			} else {
				$shop_currency_code = Shop_CurrencySettings::get()->code;
				$currency_converter  = Shop_CurrencyConverter::create();
				foreach ( $result as $name => &$option_obj ) {
					$event_params['quote']       = $option_obj['quote'];
					$event_params['option_id']   = $option_obj['id'];
					$event_params['option_name'] = $name;

					//check if quote is returned in currency other than shop currency.
					if(isset($option_obj['currency']) && strlen($option_obj['currency']) == 3){
						if($option_obj['currency'] !== $shop_currency_code){ //convert to shop currency
							$option_obj['quote'] = $currency_converter->convert( $option_obj['quote'] , $option_obj['currency'], $shop_currency_code );
						}
					}

					$updated_quote               = Backend::$events->fireEvent( 'shop:onUpdateShippingQuote', $this, $event_params );
					foreach ( $updated_quote as $updated_quote_value ) {
						if ( strlen( $updated_quote_value ) ) {
							$option_obj['quote'] = $updated_quote_value;
							break;
						}
					}
				}
			}


			return $result;
		}


		protected function apply_quote($quote, $params){

			$discount_info      = Shop_CartPriceRule::evaluate_discount(
				$params['payment_method_obj'],
				$this,
				$params['cart_items'],
				$params['shipping_info'],
				$params['coupon_code'],
				$params['customer'],
				$params['total_price']
			);


			/*
			 * Calculate per product fees
			 */

			$total_per_product_cost = 0;
			$active_items = $params['cart_items'];
			foreach ( $active_items as $item ) {
				$product = $item->product;
				if ( $product ) {
					$total_per_product_cost += $product->get_shipping_cost( $params['country_id'], $params['state_id'], $params['zip'] ) * $item->quantity;
				}
			}


			//calculate quote
			if ( !is_array( $quote ) ) {
				$quote += $total_per_product_cost;
				if(is_numeric($this->handling_fee)){
					$quote += $this->handling_fee;
				}
				$discounted_quote          = max( ( $quote - $discount_info->shipping_discount ), 0 );
				$this->quote_no_discount = $quote;
				$this->quote_no_tax      = $discounted_quote;
				$this->quote             = $discounted_quote;
				$this->quote_tax_incl    = $discounted_quote;
				$this->discount          = min($discount_info->shipping_discount,$this->quote_no_discount);

				$shipping_taxes = Shop_TaxClass::get_shipping_tax_rates( $this->id, $params['shipping_info'], $quote );
				$this->quote_tax_incl += Shop_TaxClass::eval_total_tax( $shipping_taxes );
				if ( $params['display_prices_including_tax'] ) {
					$this->quote += Shop_TaxClass::eval_total_tax( $shipping_taxes );
				}

			} else {
				//quotes might be returned in foreign currency

				$this->multi_option = true;
				$this->sub_options  = array();

				foreach ( $quote as $name => $rate ) {
					$sub_option        = (object) $rate;
					$sub_option->is_free = false;
					$sub_option->name = $name;
					$sub_option->suboption_id = $sub_option->id;
					$sub_option->id = $this->id . '_' . md5( $name );

					$sub_option->quote += $total_per_product_cost;
					if ( is_numeric( $this->handling_fee ) ) {
						$sub_option->quote += $this->handling_fee;
					}
					$discounted_quote          = max( ( $sub_option->quote - $discount_info->shipping_discount ), 0 );
					$sub_option->quote_no_discount = $sub_option->quote;
					$sub_option->quote_no_tax      = $discounted_quote;
					$sub_option->quote             = $discounted_quote;
					$sub_option->quote_tax_incl    = $discounted_quote;
					$sub_option->discount          = min($discount_info->shipping_discount,$sub_option->quote_no_discount);

					$shipping_taxes = Shop_TaxClass::get_shipping_tax_rates( $this->id, $params['shipping_info'], $sub_option->quote );
					$sub_option->quote_tax_incl += Shop_TaxClass::eval_total_tax( $shipping_taxes );
					if ( $params['display_prices_including_tax'] ) {
						$sub_option->quote += Shop_TaxClass::eval_total_tax( $shipping_taxes );
					}

					$this->sub_options[] = $sub_option;
				}
			}

			//apply free options
			if ( $this->multi_option ) {
				foreach ( $this->sub_options as $sub_option ) {
					$sub_option_id = $this->id . '_' . $sub_option->suboption_id;

					if ( array_key_exists( $sub_option_id, $discount_info->free_shipping_options ) || $sub_option->quote == 0 ) {
						$sub_option->is_free = true;
					}
				}
			} else {
				if ( array_key_exists( $this->id, $discount_info->free_shipping_options ) || $this->quote == 0 ) {
					$this->is_free = true;
				}
			}

			//converts quotes to active currency if specified in request parameters
			$currency_converter  = Shop_CurrencyConverter::create();
			$shop_currency_code = Shop_CurrencySettings::get()->code;
			$active_currency_code = (strlen($params['currency_code']) == 3) ? $params['currency_code'] : $shop_currency_code;
			if($this->get_quote_currency() !== $active_currency_code){
				$this->convert_to_currency($active_currency_code);
			}
		}

		/**
		 * Fetches fully considered quote based on current checkout state,
		 * and applies the quote to this option model
		 * @documentable
		 * @param string $cart_name Cart name to consider
		 * @return boolean Returns TRUE if quote applied.
		 */
		public function apply_checkout_quote($cart_name = 'main'){
			$payment_method = Shop_CheckoutData::get_payment_method();
			$payment_method_obj = $payment_method->id ? Shop_PaymentMethod::create()->find($payment_method->id) : null;
			$shipping_info = Shop_CheckoutData::get_shipping_info();

			//run eval discounts on cart items to mark free shipping items, updates by reference
			$cart_items = Shop_Cart::list_active_items($cart_name);
			Shop_CheckoutData::eval_discounts($cart_name, $cart_items);

			$total_price = Shop_Cart::total_price_no_tax($cart_name, false);
			$total_volume = Shop_Cart::total_items_volume($cart_items);
			$total_weight = Shop_Cart::total_items_weight($cart_items);
			$total_item_num = Shop_Cart::get_item_total_num($cart_name);
			$currency_code = Shop_CheckoutData::get_currency($as_object=false);
			$customer = Cms_Controller::get_customer();


			$request_params = array(
				'display_prices_including_tax' =>  Shop_CheckoutData::display_prices_incl_tax(),
				'payment_method_obj' => $payment_method_obj,
				'shipping_info' => $shipping_info,
				'country_id'=>$shipping_info->country,
				'state_id'=>$shipping_info->state,
				'zip'=>$shipping_info->zip,
				'city'=>$shipping_info->city,
				'total_price'=>$total_price,
				'total_volume'=>$total_volume,
				'total_weight'=>$total_weight,
				'total_item_num'=>$total_item_num,
				'cart_items'=>$cart_items,
				'is_business'=>$shipping_info->is_business,
				'customer' => is_object($customer) ? $customer : null,
				'customer_id' => is_object($customer) ? $customer->id : $customer,
				'currency_code'=> $currency_code,
				'coupon_code' => Shop_CheckoutData::get_coupon_code()
			);
			$quote = $this->_get_quote($request_params);
			if ($quote === null)
				throw new Cms_Exception('Shipping method is not applicable.');

			$this->apply_quote($quote, $request_params);
			return true;
		}

		/**
		 * Fetches fully considered quote based on order details,
		 * and applies the quote to this option model
		 * @documentable
		 * @param Shop_Order $order Specifies the order to consider
		 * @return boolean Returns TRUE if quote applied.
		 */
		public function apply_order_quote($order,  $deferred_session_key = null){
			$items = $order->items;
			$deferred_items = empty($deferred_session_key) ? false : $order->list_related_records_deferred('items', $deferred_session_key);
			if($deferred_items && $deferred_items->count){
				$order->items = $deferred_items; //consider deferred assignments in these calculations
			}
			$include_tax= false;
			$payment_method_obj = $order->get_payment_method();
			$shipping_info = new Shop_AddressInfo();
			$shipping_info->load_from_order($order, false);
			$customer = $order->customer_id ? Shop_Customer::create()->find($order->customer_id) : null;

			$request_params = array(
				'shipping_info' => $shipping_info,
				'country_id'=>$shipping_info->country,
				'state_id'=>$shipping_info->state,
				'zip'=>$shipping_info->zip,
				'city'=>$shipping_info->city,
				'is_business'=>$shipping_info->is_business,
				'total_price' =>  $order->eval_subtotal_before_discounts(),
				'total_volume' => $order->get_total_volume(),
				'total_weight'=> $order->get_total_weight(),
				'total_item_num' => $order->get_item_count(),
				'display_prices_including_tax' => $include_tax ? $include_tax : Shop_CheckoutData::display_prices_incl_tax(), //out of scope
				'order_items' => $items,
				'cart_items' =>  Shop_OrderHelper::items_to_cart_items_array($items),
				'customer' => is_object($customer) ? $customer : null,
				'customer_id' => is_object($customer) ? $customer->id : $customer,
				'payment_method_obj' => $payment_method_obj,
				'currency_code' => $order->get_currency_code(),
			);

			if($deferred_items){
				$this->items = $items; //restore items
			}

			$quote = $this->_get_quote($request_params);
			if ($quote === null)
				throw new Cms_Exception('Shipping method is not applicable.');

			$this->apply_quote($quote, $request_params);
			return true;
		}

		public static function get_applicable_options($params){
			$defaults = array(
				'shipping_info' => null,
				'total_price' => null,
				'total_volume' => null,
				'total_item_num' => null,
				'include_tax' => null,
				'display_prices_including_tax' => null,
				'return_disabled' => null,
				'cart_items' => null,
				'customer_group_id' => null,
				'customer' => null,
				'customer_id' => null,
				'shipping_option_id' => null,
				'backend_only' => null,
				'payment_method' => null,
				'coupon_code' => null,
			);

			$params = array_merge($defaults, $params);

			$shipping_options = Shop_ShippingOption::create();
			if ( !$params['backend_only'] && !$params['return_disabled'] ) {
				$shipping_options->where( 'enabled = 1' );
			}
			if ( $params['backend_only'] && !$params['return_disabled'] ) {
				$shipping_options->where( 'backend_enabled = 1' );
			}
			if ( $params['shipping_option_id'] ) {
				$shipping_options->where( 'shop_shipping_options.id = ?', $params['shipping_option_id'] );
			}
			$apply_customer_group_filter = strlen( $params['customer_group_id'] );
			$shipping_options->where( '(min_weight_allowed is null or min_weight_allowed <= ?)', $params['total_weight'] );
			$shipping_options->where( '(max_weight_allowed is null or max_weight_allowed >= ?)', $params['total_weight']  );
			$shipping_options = $shipping_options->find_all();

			return self::filter_applicable_options($shipping_options, $params);
		}


		protected static function filter_applicable_options( $shipping_options, $params){
			$default_params = array(
				'shipping_info'=>null,
				'total_price'=>null,
				'total_volume'=>null,
				'total_weight'=>null,
				'total_item_num'=>null,
				'cart_items'=>null,
				'order_items'=>null,
				'customer'=>null,
				'customer_id'=>null,
				'customer_group_id' => null,
				'currency_code'=> null,
				'payment_method' => null,
				'payment_method_obj' => null,
				'coupon_code' => null,
				'display_prices_including_tax' => null,
			);

			$params = array_merge($default_params,$params);

			$payment_method = is_object($params['payment_method']) ? $params['payment_method'] : null;
			$params['payment_method'] = $payment_method_obj = $payment_method ? Shop_PaymentMethod::find_by_id( $payment_method->id ) : null;
			$shipping_info = $params['shipping_info'];
			$params['country_id'] = $shipping_info->country;
			$params['state_id'] = $shipping_info->state;
			$params['zip'] = $shipping_info->zip;
			$params['city'] = $shipping_info->city;
			$params['is_business'] = $shipping_info->is_business;


			$processed_options = array();
			$result = array();

			foreach ( $shipping_options as $option ) {

				if(isset($processed_options[$option->id])) {
					continue;
				}
				$processed_options[$option->id] = $option;

				$apply_customer_group_filter = strlen( $params['customer_group_id'] );
				if ( $apply_customer_group_filter && !self::option_visible_for_customer_group( $option->id, $params['customer_group_id'] ) ) {
					continue;
				}

				$option->define_form_fields();
				try {
					$quote = $option->_get_quote( $params );
					if($quote === null){
						continue;
					}
					$option->apply_quote($quote, $params);
					$result[$option->id] = $option;
				} catch ( exception $ex ) {
					$option->error_hint  = $ex->getMessage();
					$result[$option->id] = $option;
					continue;
				}
			}

			uasort( $result, 'phpr_sort_order_shipping_options' );

			/*
			 * Trigger api events
			 */
			$params['options'] = $result; //required for shop:onFilterShippingOptions
			if(empty($params['order_items'])){
				$params['order_items'] = $params['cart_items']; //backward compat
			}

			$updated_options = Backend::$events->fireEvent( 'shop:onFilterShippingOptions', $params );
			foreach ( $updated_options as $updated_option_list ) {
				$result = $updated_option_list;
				break;
			}

			$updated_options = Backend::$events->fire_event(array('name' => 'shop:onUpdateShippingOptions', 'type' => 'update_result'), $result, $params);
			if($updated_options){
				$result = $updated_options;
			}

			return $result;
		}


		public function get_quote_currency(){
			if(empty($this->currency_code)){
				$shop_currency_code = Shop_CurrencySettings::get()->code;
				$this->currency_code = $shop_currency_code;
			}
			return $this->currency_code;
		}

		public function convert_to_currency($to_currency_code){
			if(!strlen(trim($to_currency_code)) == 3){
				return false;
			}
			$to_currency_code = strtoupper(trim($to_currency_code));
			if($this->get_quote_currency() !== $to_currency_code){
				$currency_converter  = Shop_CurrencyConverter::create();
				$from_currency = $this->get_quote_currency();
				$this->quote = $currency_converter->convert( $this->quote, $from_currency, $to_currency_code );
				$this->quote_no_tax = $currency_converter->convert( $this->quote_no_tax, $from_currency, $to_currency_code );
				$this->quote_tax_incl = $currency_converter->convert( $this->quote_tax_incl, $from_currency, $to_currency_code );
				$this->quote_no_discount = $currency_converter->convert( $this->quote_no_discount, $from_currency, $to_currency_code );
				$this->discount = $currency_converter->convert( $this->discount, $from_currency, $to_currency_code );
				$this->currency_code = $to_currency_code;

				if($this->multi_option){
					foreach($this->sub_options as $sub_option){
						$sub_option->quote = $currency_converter->convert( $sub_option->quote, $from_currency, $to_currency_code );
						$sub_option->quote_no_tax = $currency_converter->convert( $sub_option->quote_no_tax, $from_currency, $to_currency_code );
						$sub_option->quote_tax_incl = $currency_converter->convert( $sub_option->quote_tax_incl, $from_currency, $to_currency_code );
						$sub_option->quote_no_discount = $currency_converter->convert( $sub_option->quote_no_discount, $from_currency, $to_currency_code );
						$sub_option->discount = $currency_converter->convert( $sub_option->discount, $from_currency, $to_currency_code );
						$sub_option->currency_code = $to_currency_code;
					}
				}
			}

		}

		/*
		 * Deprecated Methods
		 */

		/**
		 * Returns a fully considered quote
		 * @ignore
		 * @deprecated Use {@link Shop_ShippingOption::apply_checkout_quote()} and  {@link Shop_ShippingOption::apply_order_quote()} instead.
		 *
		 */
		public function get_quote($country_id, $state_id, $zip, $city, $total_price, $total_volume, $total_weight, $total_item_num, $cart_items, $customer = null, $is_business = false)
		{
			traceLog('Warning: use of deprecated function - Shop_ShippingOption::get_quote()');

			$request_params = array(
				'host_obj'=>$this,
				'country_id'=>$country_id,
				'state_id'=>$state_id,
				'zip'=>$zip,
				'city'=>$city,
				'total_price'=>$total_price,
				'total_volume'=>$total_volume,
				'total_weight'=>$total_weight,
				'total_item_num'=>$total_item_num,
				'cart_items'=>$cart_items,
				'is_business'=>$is_business,
				'customer_id'=>is_object($customer) ? $customer->id : $customer
			);

			$cached_params = array(
				'country_id'=>$request_params['country_id'],
				'state_id'=>$request_params['state_id'],
				'zip'=>$request_params['zip'],
				'city'=>$request_params['city'],
				'total_price'=>$request_params['total_price'],
				'total_volume'=>$request_params['total_volume'],
				'total_weight'=>$request_params['total_weight'],
				'total_item_num'=>$request_params['total_item_num'],
				'is_business'=>$request_params['is_business'],
				'customer_id'=>$request_params['customer_id'],
			);

			$cache_key = $this->create_cache_key($cached_params);
			$cached = $this->get_quote_cache($cache_key);
			if($cached){
				return $cached;
			}
			$quote = $this->eval_quote($country_id, $state_id, $zip, $city, $total_price, $total_volume, $total_weight, $total_item_num, $cart_items, $customer, $is_business);
			$this->set_quote_cache($cache_key,$quote);
			return $quote;
		}

		/**
		 * Fetches the quote from host provider
		 * @ignore
		 * @deprecated Use {@link Shop_ShippingOption::fetch_quote()} method instead.
		 */
		protected function eval_quote($country_id, $state_id, $zip, $city, $total_price, $total_volume, $total_weight, $total_item_num, $cart_items, $customer = null, $is_business = false)
		{
			traceLog('Warning: use of deprecated function - Shop_ShippingOption::eval_quote()');

			$obj = $this->get_shippingtype_object();
			if ($obj->config_countries())
			{
				$country_ids = Db_DbHelper::scalarArray('select shop_country_id from shop_shippingoptions_countries where shop_shipping_option_id=:id', array('id'=>$this->id));

				if ($country_ids && !in_array($country_id, $country_ids))
					return;
			}

			$request_params = array(
				'host_obj'=>$this,
				'country_id'=>$country_id,
				'state_id'=>$state_id,
				'zip'=>$zip,
				'city'=>$city,
				'total_price'=>$total_price,
				'total_volume'=>$total_volume,
				'total_weight'=>$total_weight,
				'total_item_num'=>$total_item_num,
				'cart_items'=>$cart_items,
				'is_business'=>$is_business
			);

			/*
			 * Apply per-product free shipping
			 */

			$payment_method = Shop_CheckoutData::get_payment_method();

			$payment_method_obj = $payment_method->id ? Shop_PaymentMethod::find_by_id($payment_method->id) : null;
			$shipping_info = Shop_CheckoutData::get_shipping_info();
			foreach ($cart_items as $key=>$cart_item) {
				$cart_item->free_shipping = false;
			}

			Shop_CartPriceRule::evaluate_discount(
				$payment_method_obj,
				$this,
				$cart_items,
				$shipping_info,
				Shop_CheckoutData::get_coupon_code(),
				$customer ? $customer : Cms_Controller::get_customer(),
				$total_price);

			$updated_items = array();
			$free_shipping_found = false;
			foreach ($cart_items as $key=>$cart_item)
			{
				if (!$cart_item->free_shipping)
					$updated_items[$key] = $cart_item;
				else
					$free_shipping_found = true;
			}

			$total_volume = $total_weight = $total_price = $total_item_num = 0;
			foreach ($updated_items as $item)
			{
				$total_volume += $item->total_volume();
				$total_weight += $item->total_weight();
				$total_price += $item->total_price_no_tax();
				$total_item_num += $item->quantity;
			}

			// Prepare event parameters
			$event_params = array(
				'option_id' => null,
				'option_name' => null,
				'shipping_option' => $this,
				'handling_fee' => $this->handling_fee,
				'country_id' => $country_id,
				'state_id' => $state_id,
				'zip' => $zip,
				'city' => $city,
				'total_price' => $total_price,
				'total_volume' => $total_volume,
				'total_weight' => $total_weight,
				'total_item_num' => $total_item_num,
				'cart_items' => $cart_items,
				'updated_items' => $updated_items,
				'is_business'=> $is_business
			);

			$event_results = Backend::$events->fireEvent('shop:onBeforeShippingQuote', $this, $event_params);
			foreach($event_results as $event_result)
			{
				if(is_array($event_result))
				{
					//update the param arrays
					$request_params = array_merge($request_params, $event_result);
					$event_params = array_merge($event_params, $event_result);
				}
			}
			// Overwrite local variables with updated results
			extract($event_params); // more info

			$result = $obj->get_quote($request_params);
			if ($result === null)
				return null;

			$updated_result = null;

			if ($free_shipping_found)
			{
				try
				{
					if ($total_weight > 0 || $total_price > 0 || $total_item_num > 0 || $total_item_num > 0)
					{
						$request_params = array(
							'host_obj'=>$this,
							'country_id'=>$country_id,
							'state_id'=>$state_id,
							'zip'=>$zip,
							'city'=>$city,
							'total_price'=>$total_price,
							'total_volume'=>$total_volume,
							'total_weight'=>$total_weight,
							'total_item_num'=>$total_item_num,
							'cart_items'=>$updated_items,
							'is_business'=>$is_business
						);
						$updated_result = $obj->get_quote($request_params);
					} else
					{
						if (!is_array($result))
							$updated_result = 0;
						else
							$updated_result = array();
					}
				} catch (exception $ex) {}

				if (!is_array($result))
				{
					$result = $updated_result ? $updated_result : 0;
				} else {
					foreach ($result as $name=>&$option_obj)
					{
						if (!$updated_result)
						{
							$option_obj['quote'] = 0;
						} else
						{
							if (array_key_exists($name, $updated_result))
								$option_obj['quote'] = $updated_result[$name]['quote'];
							else
								$option_obj['quote'] = 0;
						}
					}
				}
			}

			/*
			 * Trigger the shop:onUpdateShippingQuote event
			 */

			if (!is_array($result))
			{
				$event_params['quote'] = $result;
				$updated_quote = Backend::$events->fireEvent('shop:onUpdateShippingQuote', $this, $event_params);
				foreach ($updated_quote as $updated_quote_value)
				{
					if (strlen($updated_quote_value))
					{
						$result = $updated_quote_value;
						break;
					}
				}
			} else {
				foreach ($result as $name=>&$option_obj)
				{
					$event_params['quote'] = $option_obj['quote'];
					$event_params['option_id'] = $option_obj['id'];
					$event_params['option_name'] = $name;
					$updated_quote = Backend::$events->fireEvent('shop:onUpdateShippingQuote', $this, $event_params);
					foreach ($updated_quote as $updated_quote_value)
					{
						if (strlen($updated_quote_value))
						{
							$option_obj['quote'] = $updated_quote_value;
							break;
						}
					}
				}
			}

			/*
			 * Apply handling fee
			 */
			if(isset($handling_fee) && is_numeric($handling_fee)){
				if (!is_array($result))
					return $result + $handling_fee;

				foreach ($result as $name=>&$option_obj)
				{
					$option_obj['quote'] += $handling_fee;
				}
			}
			return $result;
		}

		/**
		 * Returns shipping options available for given parameters
		 * @documentable
		 * @deprecated Use {@link Shop_ShippingOption::get_applicable_options()} method instead.
		 */
		public static function list_applicable( $country_id, $state_id, $zip, $city, $total_price, $total_volume, $total_weight, $total_item_num, $include_tax = 1, $return_disabled = false, $cart_items = array(), $customer_group_id = null, $customer = null, $shipping_option_id = null, $is_business = false, $backend_only = false ) {
			traceLog('Warning: use of deprecated function - Shop_ShippingOption::list_applicable()');
			$shipping_info = new Shop_AddressInfo();
			$shipping_info->country = $country_id;
			$shipping_info->state = $state_id;
			$shipping_info->zip = $zip;
			$shipping_info->city = $city;

			$params = array(
				'shipping_info' => $shipping_info,
				'total_price' => $total_price,
				'total_volume' => $total_volume,
				'total_item_num' => $total_item_num,
				'include_tax' => $include_tax,
				'display_prices_including_tax' => $include_tax ? $include_tax : Shop_CheckoutData::display_prices_incl_tax(),
				'return_disabled' => $return_disabled,
				'cart_items' => $cart_items,
				'customer_group_id' => $customer_group_id,
				'customer' => is_object($customer) ? $customer : null,
				'customer_id' => is_object($customer) ? $customer->id : $customer,
				'shipping_option_id' => $shipping_option_id,
				'backend_only' => $backend_only,
				'payment_method' => Shop_CheckoutData::get_payment_method(), //out of scope
				'coupon_code' => Shop_CheckoutData::get_coupon_code(), //out of scope
			);

			return self::get_applicable_options($params);
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to define new columns in the shipping option model.
		 * The event handler should accept two parameters - the shipping option object and the form 
		 * execution context string. To add new columns to the shipping option model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the shipping option object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_shipping_options</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendShippingOptionModel', $this, 'extend_shipping_option_model');
		 *    Backend::$events->addEvent('shop:onExtendShippingOptionForm', $this, 'extend_shipping_option_form');
		 * }
		 * 
		 * public function extend_shipping_option_model($shipping_option, $context)
		 * {
		 *    $shipping_option->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_shipping_option_form($shipping_option, $context)
		 * {
		 *    $shipping_option->add_form_field('x_extra_description')->tab('General Parameters');
		 * }
		 * </pre>
		 * @event shop:onExtendShippingOptionModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendShippingOptionForm
		 * @see shop:onGetShippingOptionFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ShippingOption $shipping_option Specifies the shipping option object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendShippingOptionModel($shipping_option, $context) {}
			
		/**
		 * Allows to add new fields to the Create/Edit Shipping Option form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendShippingOptionModel} event. 
		 * To add new fields to the shipping option form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * shipping option object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendShippingOptionModel', $this, 'extend_shipping_option_model');
		 *    Backend::$events->addEvent('shop:onExtendShippingOptionForm', $this, 'extend_shipping_option_form');
		 * }
		 * 
		 * public function extend_shipping_option_model($shipping_option, $context)
		 * {
		 *    $shipping_option->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_shipping_option_form($shipping_option, $context)
		 * {
		 *    $shipping_option->add_form_field('x_extra_description')->tab('General Parameters');
		 * }
		 * </pre>
		 * @event shop:onExtendShippingOptionForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendShippingOptionModel
		 * @see shop:onGetShippingOptionFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ShippingOption $shipping_option Specifies the shipping option object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendShippingOptionForm($shipping_option, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendShippingOptionForm} event.
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
		 *    Backend::$events->addEvent('shop:onExtendShippingOptionModel', $this, 'extend_shipping_option_model');
		 *    Backend::$events->addEvent('shop:onExtendShippingOptionForm', $this, 'extend_shipping_option_form');
		 *    Backend::$events->addEvent('shop:onGetShippingOptionFieldOptions', $this, 'get_shipping_option_field_options');
		 * }
		 *
		 * public function extend_shipping_option_model($shipping_option, $context)
		 * {
		 *    $shipping_option->define_column('x_drop_down', 'Some drop-down menu');
		 * }
		 * 
		 * public function extend_shipping_option_form($shipping_option, $context)
		 * {
		 *    $shipping_option->add_form_field('x_drop_down')->tab('General Parameters')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_shipping_option_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_drop_down')
		 *   {
		 *     $options = array(
		 *       0 => 'Option 1',
		 *       1 => 'Option 2',
		 *       2 => 'Option 3'
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
		 * @event shop:onGetShippingOptionFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendShippingOptionModel
		 * @see shop:onExtendShippingOptionForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetShippingOptionFieldOptions($db_name, $field_value) {}

		/**
		 * Allows to update shipping parameters before they are sent to a shipping method. 
		 * The event handler should accept 2 parameters - the {@link Shop_ShippingOption} object and an array of shipping parameters. 
		 * The handler should return updated shipping params as an associative array. The <em>$params</em> array 
		 * contains the following elements: 
		 * <ul>
		 *   <li><em>quote</em> - the original shipping quote.</li>
		 *   <li><em>option_id</em> - for multi-option shipping methods only (like USPS) - service-specific identifier of the shipping option.</li>
		 *   <li><em>option_name</em> - for multi-option shipping methods only (like USPS) - service-specific name of the shipping option.</li>
		 *   <li><em>shipping_option</em> - the {@link Shop_ShippingOption} object which returned the original quote.</li>
		 *   <li><em>handling_fee</em> - the handling fee, defined in the shipping method.</li>
		 *   <li><em>country_id</em> - {@link Shop_Country shipping country} identifier.</li>
		 *   <li><em>state_id</em> - {@link Shop_CountryState shipping state} identifier.</li>
		 *   <li><em>zip</em> - shipping ZIP/Postal code.</li>
		 *   <li><em>city</em> - shipping city.</li>
		 *   <li><em>total_price</em> - total price of all order items.</li>
		 *   <li><em>total_volume</em> - total volume of all order items.</li>
		 *   <li><em>total_weight</em> - total weight of all order items.</li>
		 *   <li><em>total_item_num</em> - total number of order items.</li>
		 *   <li><em>cart_items</em> - a list of shopping cart items. An array of {@link Shop_CartItem} or {@link Shop_OrderItem} objects, depending on the caller context.</li>
		 * </ul>
		 * Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onBeforeShippingQuote', $this, 'before_shipping_quote');
		 * }
		 *  
		 * public function before_shipping_quote($shipping_option, $params)
		 * {
		 *   return array(
		 *     'zip' => '55155',
		 *     'city' => 'Hollywood'
		 *   );
		 * }
		 * </pre>
		 * @event shop:onBeforeShippingQuote
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onUpdateShippingQuote
		 * @see shop:onFilterShippingOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ShippingOption $shipping_option Specifies the shipping option object.
		 * @param array $params Specifies the method parameters.
		 * @return array Returns updated shipping parameters.
		 */
		private function event_onBeforeShippingQuote($shipping_option, $params) {}
		
		/**
		 * Allows to update a shipping quote calculated by a regular shipping method. 
		 * The event handler should accept 2 parameters - the Shop_ShippingOption object and an array of shipping parameters. 
		 * The event handler should return updated shipping quote. The <em>$params</em> array contains the following elements: 
		 * <ul>
		 *   <li><em>quote</em> - the original shipping quote.</li>
		 *   <li><em>option_id</em> - for multi-option shipping methods only (like USPS) - service-specific identifier of the shipping option.</li>
		 *   <li><em>option_name</em> - for multi-option shipping methods only (like USPS) - service-specific name of the shipping option.</li>
		 *   <li><em>shipping_option</em> - the {@link Shop_ShippingOption} object which returned the original quote.</li>
		 *   <li><em>handling_fee</em> - the handling fee, defined in the shipping method.</li>
		 *   <li><em>country_id</em> - {@link Shop_Country shipping country} identifier.</li>
		 *   <li><em>state_id</em> - {@link Shop_CountryState shipping state} identifier.</li>
		 *   <li><em>zip</em> - shipping ZIP/Postal code.</li>
		 *   <li><em>city</em> - shipping city.</li>
		 *   <li><em>total_price</em> - total price of all order items.</li>
		 *   <li><em>total_volume</em> - total volume of all order items.</li>
		 *   <li><em>total_weight</em> - total weight of all order items.</li>
		 *   <li><em>total_item_num</em> - total number of order items.</li>
		 *   <li><em>cart_items</em> - a list of shopping cart items. An array of {@link Shop_CartItem} or {@link Shop_OrderItem} objects, 
		*      depending on the caller context. When processing existing orders, LemonStand can automatically convert order items to cart 
		 *     items. In this case the reference to the original order item is stored in the {@link Shop_CartItem::$order_item $order_item} 
		 *     field of the cart item object.
		 *   </li>
		 * </ul>
		 * Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onUpdateShippingQuote', $this, 'update_shipping_quote');
		 * }
		 *  
		 * public function update_shipping_quote($shipping_option, $params)
		 * {
		 *   return $params['quote']*2;
		 * }
		 * </pre>
		 * @event shop:onUpdateShippingQuote
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onBeforeShippingQuote
		 * @see shop:onFilterShippingOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ShippingOption $shipping_option Specifies the shipping option object.
		 * @param array $params Specifies the method parameters.
		 * @return array Returns updated shipping quote.
		 */
		private function event_onUpdateShippingQuote($shipping_option, $params) {}
		
		/**
		 * Deprecated: 'shop:onFilterShippingOptions' is not a true filter event. It will only return a result from the first module to reply.
		 * Use `shop:onUpdateShippingOptions` instead
		 *
		 * Allows to filter the shipping option list before it is displayed on the checkout pages. 
		 * The event handler should accept a single parameter - the options array. The array contains the following fields:
		 * <ul>
		 *   <li><em>options</em> - a array of shipping options. Each element is the {@link Shop_ShippingOption} object.</li>
		 *   <li><em>country_id</em> - {@link Shop_Country shipping country} identifier.</li>
		 *   <li><em>state_id</em> - {@link Shop_CountryState shipping state} identifier.</li>
		 *   <li><em>zip</em> - shipping ZIP/Postal code.</li>
		 *   <li><em>city</em> - shipping city.</li>
		 *   <li><em>total_price</em> - total price of all order items.</li>
		 *   <li><em>total_volume</em> - total volume of all order items.</li>
		 *   <li><em>total_weight</em> - total weight of all order items.</li>
		 *   <li><em>total_item_num</em> - total number of order items.</li>
		 *   <li><em>order_items</em> - a list of order items ({@link Shop_OrderItem} or {@link Shop_CartItem} objects, depending on the caller context).</li>
		 *   <li><em>customer_group_id</em> - identifier of the {@link Shop_CustomerGroup customer group}.</li>
		 * </ul>
		 * The handler should return an updated options array. Note, that for multi-option shipping methods
		 * (like USPS) you may need to update the <em>{@link Shop_ShippingOption::$sub_options $sub_options}</em> property.
		 * 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onFilterShippingOptions', $this, 'filter_shipping_options');
		 * }
		 * 
		 * public function filter_shipping_options($params)
		 * {
		 *   // Remove option with the "post" API key
		 *   
		 *   $result = array();
		 *   foreach ($params['options'] as $option)
		 *   {
		 *     if ($option->ls_api_code != 'post')
		 *       $result[$option->id] = $option;
		 *   }
		 *   
		 *   return $result;
		 * }
		 * </pre>
		 * @event shop:onFilterShippingOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onBeforeShippingQuote
		 * @see shop:onUpdateShippingQuote
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param array $params Specifies the method parameters.
		 * @return array Returns updated list of shipping options.
		 */
		private function event_onFilterShippingOptions($params) {}

		/**
		 * NOTE: Requires latest version of 'core' module from github:damanic
		 *
		 * Allows to updatethe shipping option list before it is displayed on the checkout pages.
		 * The event handler should accept two parameters - the options array AND event parameters array.
		 *
		 * The options array contains an array of {@link Shop_ShippingOption} objects
		 *
		 * The event parameters array contains the following fields:
		 * <ul>
		 *   <li><em>country_id</em> - {@link Shop_Country shipping country} identifier.</li>
		 *   <li><em>state_id</em> - {@link Shop_CountryState shipping state} identifier.</li>
		 *   <li><em>zip</em> - shipping ZIP/Postal code.</li>
		 *   <li><em>city</em> - shipping city.</li>
		 *   <li><em>total_price</em> - total price of all order items.</li>
		 *   <li><em>total_volume</em> - total volume of all order items.</li>
		 *   <li><em>total_weight</em> - total weight of all order items.</li>
		 *   <li><em>total_item_num</em> - total number of order items.</li>
		 *   <li><em>order_items</em> - a list of order items ({@link Shop_OrderItem} or {@link Shop_CartItem} objects, depending on the caller context).</li>
		 *   <li><em>customer_group_id</em> - identifier of the {@link Shop_CustomerGroup customer group}.</li>
		 * </ul>
		 *
		 *
		 * The handler should return an updated options array. Note, that for multi-option shipping methods
		 * (like USPS) you may need to update the <em>{@link Shop_ShippingOption::$sub_options $sub_options}</em> property.
		 *
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onUpdateShippingOptions', $this, 'update_shipping_options');
		 * }
		 *
		 * public function update_shipping_options($shipping_options,$event_params)
		 * {
		 *   // Remove option with the "post" API key
		 *
		 *   $result = array();
		 *   foreach ($shipping_options as $option)
		 *   {
		 *     if ($option->ls_api_code != 'post')
		 *       $result[$option->id] = $option;
		 *   }
		 *
		 *   return $result;
		 * }
		 * </pre>
		 * @event shop:onUpdateShippingOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onBeforeShippingQuote
		 * @see shop:onUpdateShippingQuote
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param array $options An array of shipping options
		 * @param array $event_params An array of parameters used to determine the list of shipping options
		 * @return array Returns updated list of shipping options.
		 */
		private function event_onUpdateShippingOptions($options, $event_params) {}

		/**
		 * Allows to add to the CACHE_SHIPPING_METHODS cache key.
		 * The event handler should accept 2 parameters - the Shop_ShippingOption object and an array of shipping parameters.
		 * The event handler should return a string to append to the cache key.
		 * <ul>
		 *   <li><em>host_obj</em> - the shipping option {@link Shop_ShippingOption} object.</li>
		 *   <li><em>country_id</em> - {@link Shop_Country shipping country} identifier.</li>
		 *   <li><em>state_id</em> - {@link Shop_CountryState shipping state} identifier.</li>
		 *   <li><em>zip</em> - shipping ZIP/Postal code.</li>
		 *   <li><em>city</em> - shipping city.</li>
		 *   <li><em>total_price</em> - total price of all order items.</li>
		 *   <li><em>total_volume</em> - total volume of all order items.</li>
		 *   <li><em>total_weight</em> - total weight of all order items.</li>
		 *   <li><em>total_item_num</em> - total number of order items.</li>
		 *   <li><em>order_items</em> - a list of order items ({@link Shop_OrderItem} or {@link Shop_CartItem} objects, depending on the caller context).</li>
		 *   <li><em>customer_id</em> - identifier for customer {@link Shop_Customer customer}.</li>
		 *
		 * </ul>
		 * The handler can return a string to append to the cache key.
		 * This is useful when using the shop:onUpdateShippingQuote event to update a quote based on external considerations.
		 * example: a quote updated based on currency, customer account, session considerations would not work on
		 *          websites configured to CACHE_SHIPPING_METHODS, unless you append these considerations to the cache key.
		 *
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAppendShippingQuoteCacheKey', $this, 'update_shipping_quote_cache_key');
		 * }
		 *
		 * public function update_shipping_quote_cache_key($params)
		 * {
		 *   if(Phpr::$session->get('external_shipping_consideration'))
		 *   {
		 * 		return 'external_shipping_consideration';
		 *	 }
		 * }
		 * </pre>
		 * @event shop:onAppendShippingQuoteCacheKey
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onBeforeShippingQuote
		 * @see shop:onUpdateShippingQuote
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 *
		 * @param array $params Specifies the method parameters.
		 *
		 * @return string Returns string to append to the cache key
		 */
		private function event_onAppendShippingQuoteCacheKey($params) {}
	}
	
	function phpr_sort_order_shipping_options($a, $b)
	{
		if ($a->error_hint)
			return -1;

		return 1;
	}



?>