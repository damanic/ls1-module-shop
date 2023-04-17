<?php

	/**
	 * Represents a shipping option record.
	 * Object of this class is available through the <em>$shipping_method</em> property of the {@link Shop_Order} class.
	 * Also, a collection of shipping option objects is available on the Shipping Method step of the Checkout process.
	 * @property string $name Specifies the shipping option name.
	 * @property string $description Specifies the shipping option description in plain text format.
	 * @property boolean $taxable Determines whether tax is applicable for the shipping option.
	 * @property string $ls_api_code Specifies the option API code.
	 * @property string $error_hint Contains an error message returned by the shipping service provider.
	 * By default, LemonStand does not return shipping options with errors. You can enable this feature on the System/Settings/Shipping
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
	class Shop_ShippingOption extends Shop_ActiveRecord
	{
		public $table_name = 'shop_shipping_options';
        public $custom_columns = array('shipping_type_name'=>db_text);
        public $has_and_belongs_to_many = array(
            'countries'=>array('class_name'=>'Shop_Country', 'join_table'=>'shop_shippingoptions_countries', 'order'=>'name'),
            'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'shop_shippingoptions_customer_groups', 'order'=>'name', 'foreign_key'=>'customer_group_id', 'primary_key'=>'shop_sh_option_id')
        );

		public $enabled = 1;
		public $backend_enabled = 1;
		public $taxable = 1;
		public $order;
        /**
        * The following field contains an error hint message - for example
         * "The postal code XXXXX is invalid for AL United States".
         * @var string
        */
        public $error_hint = null;
        public $api_added_columns = array();

		/*
		 * Additional config data extracted from config_data field (XML)
		 */
		public $fetched_data = array();

        protected $quotes = array();
		protected $shipping_type_obj = null;
        protected $added_fields = array();

		private static $customer_group_filter_cache = null;
		private static $is_taxable_cache = array();
        private static $executionCache = array();
        private static $eventParamCache = array();

		public static function create()
		{
			return new self();
		}

		/**
		 * Finds a shipping option by its API code.
		 * @documentable
		 * @param string $code Specifies the API code.
         * @return Shop_ShippingOption|null Returns the shipping option object. Returns NULL if the record with the specified API code is not found.
		 */
		public static function find_by_api_code($code)
		{
            if(!isset(self::$executionCache['find'])){
                self::$executionCache['find'] = array();
            }
			$code = mb_strtolower($code);
            if (!array_key_exists($code, self::$executionCache['find'])) {
                $obj = self::create()->where('ls_api_code=?', $code)->find();
                self::$executionCache['find'][$code] = $obj ?: null;
            }
            return self::$executionCache['find'][$code];
		}

		/**
		 * Finds a shipping option by its identifier code.
		 * This method uses internal memory caching.
		 * @documentable
		 * @param int $id Specifies the option ID.
		 * @return Shop_ShippingOption|null Returns the shipping option object. Returns NULL if the record with the specified API code is not found.
		 */
		public static function find_by_id($id)
		{
            if(!isset(self::$executionCache['find'])){
                self::$executionCache['find'] = array();
            }
			if (!array_key_exists($id, self::$executionCache['find'])){
                $obj = self::create()->find($id);
                self::$executionCache['find'][$id] = $obj ? $obj : null;
            }
			return self::$executionCache['find'][$id];
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
		 * @param string $field Specifies a field code (previously added with add_field method)
		 * @param string $message Specifies an error message text
		 * @param int $grid_row Specifies an index of grid row, for grid controls
		 * @param string $grid_column Specifies a name of column, for grid controls
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


		public function load_xml_data($force = false)
		{
			if (!strlen($this->config_data))
				return;

			if(!$force && !empty($this->fetched_data)) { //already loaded data
				return;
			}

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

		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}

		public function supports_shipping_labels()
		{
			return $this->get_shippingtype_object()->supportsLabels();
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





        /**
         * Returns quotes for a given order.
         *
         * Note: This function actions the free shipping item discount rule
         * by excluding items marked as free shipping from shipping rate consideration.
         *
         * @param Shop_Order $order
         * @param string $deferred_session_key
         * @return Shop_ShippingOptionQuote[]
         */
        public function getQuoteForOrder(Shop_Order $order, $deferred_session_key=null){

            $rates = array();

            $shippingProvider = $this->get_shippingtype_object();
            if(!$shippingProvider->supportsRates()){
                return $rates;
            }

            $items = $order->items;
            $deferred_items = empty($deferred_session_key) ? false : $order->list_related_records_deferred('items', $deferred_session_key);
            if($deferred_items && $deferred_items->count){
                $order->items = $deferred_items; //consider deferred assignments in these calculations
            }
            $shipping_info = new Shop_AddressInfo();
            $shipping_info->load_from_order($order, false);

            if(!count($order->items)){
                return array(); // no items, no quotes
            }

            $cartItems = $order->getCartItemsShipping($order->items);
            if(!count($cartItems)){
                /*
                 * LS orders require shipping option selection even if the order contains zero shippable items.
                 * If this shipping option is a table rate with the API code `no_shipping_required` we continue
                 * to pass the order items for rate calculation so that a free `No Shipping Required` option can be returned.
                 */
                if(is_a($shippingProvider,'Shop_TableRateShipping') && $this->ls_api_code == 'no_shipping_required'){
                    $cartItems = Shop_OrderHelper::items_to_cart_items_array($order->items);
                } else {
                    return array(); // no items to ship, no quotes to return
                }
            }

            $quotableCartItems = $this->filterFreeShippingItems($cartItems);
            if($quotableCartItems){
                //Exclude items marked for free shipping from shipping rate consideration.
                $cartItems = $quotableCartItems;
            } else {
                // all items are free!
                // continue to fetch rates for free items to determine services available
                // the services will be marked as free on buildQuotes()
            }

            $eventParams = $this->getEventParamsForOrder($order);
            $eventParams = $this->onBeforeShippingQuote($eventParams);

            try {
                $shippingProvider->setEventParameters($eventParams); //legacy support
                $rates = $shippingProvider->getItemRates($this, $cartItems, $shipping_info, null, 'getQuoteForOrder' );
            } catch (exception $ex) {
                $this->error_hint = $ex->getMessage();
            }

            if (empty($rates))
                return $rates;


            if($deferred_items){
                $order->items = $items; //restore items
            }

            $quotes = $this->buildQuotes($rates, $eventParams);

            //Allow event subscribers to update quotes
            $updatedQuotes = Backend::$events->fire_event(
                array(
                    'name' => 'shop:onUpdateShippingQuotesForOrder',
                    'type' => 'update_result'
                ),
                $quotes,
                $order
            );
            if(is_array($updatedQuotes) && $this->isArrayOfObjectType($updatedQuotes,'Shop_ShippingOptionQuote')){
                $quotes = $updatedQuotes;
            }
            return $quotes;
        }

        /**
         * Return quotes for checkout state
         *
         * Note: This function actions the free shipping item discount rule
         * by excluding items marked as free shipping from shipping rate consideration.
         *
         * @param string $cart_name
         * @return Shop_ShippingOptionQuote[]
         */
        public function getQuoteForCheckout($cart_name = 'main'){
            $rates = array();

            $shippingProvider = $this->get_shippingtype_object();
            if(!$shippingProvider->supportsRates()){
                return $rates;
            }

            $shippingInfo = Shop_CheckoutData::get_shipping_info();
            //run eval discounts on cart items to mark free shipping items, updates by reference
            $cartItems = Shop_Cart::list_active_items($cart_name);
            Shop_CheckoutData::eval_discounts($cart_name, $cartItems);
            if(!count($cartItems)){
                return array(); // no items to ship, no quotes to return
            }

            $quotableCartItems = $this->filterFreeShippingItems($cartItems);
            if($quotableCartItems){
                //Exclude items marked for free shipping from shipping rate consideration
                $cartItems = $quotableCartItems;
            } else {
                // all items are free!
                // continue to fetch rates for free items to determine services available
                // the services will be marked as free on buildQuotes()
            }

            $eventParams = array_merge(self::getEventParamsForCart($cart_name), $this->getEventParamsForShippingOption());
            $internalEventKeys = array_keys($eventParams);
            $eventParams = $this->onBeforeShippingQuote($eventParams);

            //
            // CHECK CACHE
            //
            $eventParamCacheKeys = array();
            $relevantInternalEventKeys = array('customer_id');
            foreach($eventParams as $epKey => $epValue){
                //allow customer_id and custom event params to break cache
                if(!in_array($epKey, $internalEventKeys) || in_array($epKey, $relevantInternalEventKeys)){
                    $eventParamCacheKeys[$epKey] = serialize($epValue);
                }
            }

            $cacheKey = self::generateShippingCacheKey($cartItems,$shippingInfo,$eventParamCacheKeys);
            $quotes = $this->getCheckoutQuoteCache($cacheKey);
            if($quotes !== null){
                return $quotes;
            }
            $rates = $this->getCheckoutRateCache($cacheKey);
            if($rates === null){
                try {
                    $shippingProvider->setEventParameters($eventParams); //legacy support
                    $rates = $shippingProvider->getItemRates($this, $cartItems, $shippingInfo, null, 'getQuoteForCheckout' );
                    $this->setCheckoutRateCache($cacheKey, $rates);
                } catch (Exception $ex) {
                    traceLog($ex->getMessage());
                    $this->error_hint = $ex->getMessage();
                }
            }

            $quotes = array();
            if (!empty($rates)){
                $quotes = $this->buildQuotes($rates, $eventParams);
            }
            $this->setCheckoutQuoteCache($cacheKey,$quotes);

            //Allow event subscribers to update quotes
            $updatedQuotes = Backend::$events->fire_event(
                array(
                    'name' => 'shop:onUpdateShippingQuotesForCheckout',
                    'type' => 'update_result'
                ),
                $quotes,
                $cart_name
            );
            if(is_array($updatedQuotes) && $this->isArrayOfObjectType($updatedQuotes,'Shop_ShippingOptionQuote')){
                $quotes = $updatedQuotes;
            }

            return $quotes;
        }



        /**
         * @return Shop_ShippingOptionQuote[] Array of shipping quotes attached to this shipping method
         */
        public function getQuotes(){
            return $this->quotes;
        }

		/**
		 * Fetches fully considered quote based on current checkout state,
		 * and applies the quote to this option model.
		 * @documentable
		 * @param string $cart_name Cart name to consider
		 * @return boolean Returns TRUE if quote applied.
		 */
		public function apply_checkout_quote($cart_name = 'main'){
            $this->define_form_fields();
            $eventParams = array_merge(self::getEventParamsForCart($cart_name), $this->getEventParamsForShippingOption());
			$quotes = $this->getQuoteForCheckout($cart_name);
            if($quotes){
                $this->applyQuotes($quotes, $eventParams);
                return true;
            }
			return false;
		}

		/**
		 * Fetches fully considered quote based on order details,
		 * and applies the quote to this option model
		 * @documentable
		 * @param Shop_Order $order Specifies the order to consider
		 * @return boolean Returns TRUE if quote applied.
		 */
		public function apply_order_quote($order,  $deferred_session_key = null){
            $this->define_form_fields();
			$quotes = $this->getQuoteForOrder($order, $deferred_session_key);
            $eventParams = $this->getEventParamsForOrder($order, $deferred_session_key);
            if($quotes){
                $this->applyQuotes($quotes, $eventParams);
                return true;
            }
           return false;
		}

        /**
         * Backwards compat
         * @param string $field
         * @return mixed
         */
        public function __get($field){


            $singleQuoteProps = array(
                'quote',
                'quote_no_tax',
                'quote_tax_incl',
                'quote_no_discount',
                'discount',
            );
            if(in_array($field, $singleQuoteProps)){
                $quotes = $this->getQuotes();
                if($quotes){
                    $value = $quotes[0]->$field;
                    return $value ? $value : 0;
                }
                return 0;
            }

            if($field == 'currency_code'){
                $quotes = $this->getQuotes();
                if($quotes){
                    $quotes[0]->getCurrencyCode();
                }
              return null;
            }

            if($field == 'sub_options'){
                return $this->getQuotes();
            }

            if($field == 'multi_option'){
                return $this->get_shippingtype_object()->supportsMultipleShippingServices($this);
            }

            return parent::__get($field);

        }



        public static function is_taxable($id)
        {
            if (array_key_exists($id, self::$is_taxable_cache))
                return self::$is_taxable_cache[$id];

            return self::$is_taxable_cache[$id] = Db_DbHelper::scalar('select taxable from shop_shipping_options where id=:id', array('id'=>$id));
        }


        /**
         * This method returns Shipping Options that qualify for users active checkout session.
         * Shipping options returned do not include quotes for this checkout.
         * You can check for shipping quotes using methods in the returned shipping options.
         *
         * @param string $cartName
         * @param Shop_Customer|null $customer
         * @return array|Shop_ShippingOption[]
         */
        public static function getShippingOptionsForCheckout($cartName = 'main', $customer = null)
        {
            $eventParams = self::getEventParamsForCart($cartName);
            $cartItems = Shop_Cart::list_active_items($cartName);
            $weight = Shop_Cart::total_items_weight($cartItems);
            $shippingAddress = Shop_CheckoutData::get_shipping_info();

            if($weight !== 0) {
                //Shipping option minimum and maximum weight restrictions are evaluated against ACTUAL item weight.
                //However, if a discount rule effectively reduces the item shipping weight to ZERO
                //this method will look shipping options that allow for ZERO weight.
                if (isset($eventParams['cart_items']) && !empty($eventParams['cart_items'])) {
                    $discountedWeight = Shop_Cart::total_items_weight($eventParams['cart_items']);
                    if ($discountedWeight === 0) {
                        $weight = 0;
                    }
                }
            }

            $cacheKey = self::generateShippingCacheKey(
                $cartItems,
                $shippingAddress,
                array(
                    'cartName' => $cartName,
                    'weight' => $weight,
                    'customerId' => $customer ? $customer->id : '',
                )
            );
            if(isset(self::$executionCache['shippingOptionsForCheckout'][$cacheKey])){
                //Get applicable shipping options from cache
                $shippingOptionsArray = self::$executionCache['shippingOptionsForCheckout'][$cacheKey];
            } else {
                //Fetch applicable shipping option records
                $shippingOptions = self::findByWeight($weight, true, false);
                $shippingOptionsArray = $shippingOptions ? $shippingOptions->as_array(null, 'id') : array();
                if($shippingOptionsArray) {
                    $shippingOptionsArray = self::applyFilterCountry($shippingOptionsArray, $shippingAddress);
                    $shippingOptionsArray = self::applyFilterCustomerGroup($shippingOptionsArray, $customer);
                    //Cache result
                    self::$executionCache['shippingOptionsForCheckout'] = array();
                    self::$executionCache['shippingOptionsForCheckout'][$cacheKey] = $shippingOptionsArray;
                }
            }

            //Apply legacy filters
            $shippingOptionsArray = self::applyLegacyFilterEvents($shippingOptionsArray, $eventParams);

            //Allow discount rules to add shipping options
            $discountAddedOptions = Shop_CheckoutData::get_discount_applied_shipping_options($cartName);
            if($discountAddedOptions){
                foreach($discountAddedOptions as $discountAddedOption){
                    $discountAddedOption->apply_checkout_quote($cartName);
                    $shippingOptionsArray[$discountAddedOption->id] = $discountAddedOption;
                }
            }

            //Allow event subscribers to update results
            $updatedShippingOptions = Backend::$events->fire_event(
                array('name' => 'shop:onUpdateShippingOptionsForCheckout', 'type' => 'update_result'),
                $shippingOptionsArray,
                $cartName,
                $customer
            );
            if(is_array($updatedShippingOptions) || $updatedShippingOptions instanceof \Traversable){
                foreach($updatedShippingOptions as $shippingOption){
                    if(!is_a($shippingOption,'Shop_ShippingOption')){
                        throw new Phpr_ApplicationException('Event shop:onUpdateShippingOptionsForCheckout expects an array of Shop_ShippingOption objects');
                    }
                }
                $shippingOptionsArray = $updatedShippingOptions;
            }

            //Required to load fields from shipping type extension.
            foreach($shippingOptionsArray as $shippingOption){
                $shippingOption->define_form_fields();
            }

            return $shippingOptionsArray;
        }

        /**
         * This method returns Shipping Options that qualify for an order.
         * Shipping options returned do not include quotes.
         * You can check for shipping quotes using methods in the returned shipping options.
         * @param Shop_Order $order
         * @param bool $frontendOnly
         * @param bool $includeDisabled
         * @return array|Shop_ShippingOption[]
         */
        public static function getShippingOptionsForOrder($order, $frontendOnly = false, $includeDisabled = false)
        {
            $weight = $order->get_total_weight();
            $includeZeroWeightOptions = false;
            if($weight !== 0) {
                //Shipping option minimum and maximum weight restrictions are evaluated against ACTUAL weight.
                //However, a discount rule can effectively reduce the order shipping weight to ZERO
                //by marking all order items as eligible for FREE SHIPPING. In this case we should include shipping options
                //that have their min and max weight limit set to ZERO.
                //TLDR: Expose free shipping options.
                $itemsShipping = $order->getCartItemsShipping($order->items);
                if ($itemsShipping) {
                    $discountedWeight = Shop_Cart::total_items_weight($itemsShipping);
                    if ($discountedWeight === 0) {
                        $includeZeroWeightOptions = true;
                    }
                }
            }

            //Look for applicable shipping option records
            $shippingOptions = self::findByWeight($weight, $frontendOnly, $includeDisabled, $includeZeroWeightOptions);
            $shippingOptionsArray = $shippingOptions ? $shippingOptions->as_array(null, 'id') : array();
            if($shippingOptionsArray) {
                $eventParams = $shippingOptions[0]->getEventParamsForOrder($order);
                $shippingOptionsArray = self::applyFilterCountry($shippingOptionsArray, $order->get_shipping_address_info());
                $shippingOptionsArray = self::applyFilterCustomerGroup($shippingOptionsArray, $order->customer);
                $shippingOptionsArray = self::applyLegacyFilterEvents($shippingOptionsArray, $eventParams);
            }

            //Look for event added shipping options
            $updatedShippingOptions = Backend::$events->fire_event(
                array('name' => 'shop:onUpdateShippingOptionsForOrder', 'type' => 'update_result'),
                $shippingOptionsArray,
                $order,
                $frontendOnly,
                $includeDisabled
            );
            if(is_array($updatedShippingOptions) || $updatedShippingOptions instanceof \Traversable){
                foreach($updatedShippingOptions as $shippingOption){
                    if(!is_a($shippingOption,'Shop_ShippingOption')){
                        throw new Phpr_ApplicationException('Event shop:onUpdateShippingOptionsForOrder expects an array of Shop_ShippingOption objects');
                    }
                }
                $shippingOptionsArray = $updatedShippingOptions;
            }

            //loads fields from shipping type extension.
            foreach($shippingOptionsArray as $shippingOption){
                $shippingOption->define_form_fields();
            }

            return $shippingOptionsArray;
        }

        public function __set($field, $value){


            $singleQuoteProps = array(
                'quote',
                'quote_no_tax',
                'quote_tax_incl',
                'quote_no_discount',
                'discount',
            );
            if(in_array($field, $singleQuoteProps)){
                $quotes = $this->getQuotes();
                if($quotes){
                    $quotes[0]->$field = $value;
                    return;
                }
            }

            if($field == 'currency_code'){
                $quotes = $this->getQuotes();
                if($quotes){
                    $quotes[0]->setCurrencyCode($value);
                    return;
                }
            }

            if($field == 'sub_options'){
                if(is_array($value)){
                    $quotes = array();
                    foreach($value as $subOption){
                        if(!is_a($subOption, 'Shop_ShippingOptionQuote')){
                            $subOption = self::convertLegacyQuoteObj($subOption);
                        }
                        $quotes[] = $subOption;
                    }
                    $this->quotes = $quotes;
                }
                return;
            }

            parent::__set($field, $value);

        }


        /**
         * Builds quotes ( Shop_ShippingOptionQuote ) for this shipping option
         * @param Shop_ShippingRate[] $rates The shipping rates from which quotes are determined
         * @param array $eventParams The event parameters provide cart/customer/order information
         * @return Shop_ShippingOptionQuote[]
         */
        protected function buildQuotes(array $rates, array $eventParams){

            $discount_info      = Shop_CartPriceRule::evaluate_discount(
                $eventParams['payment_method_obj'],
                $this,
                $eventParams['cart_items'],
                $eventParams['shipping_info'],
                $eventParams['coupon_code'],
                $eventParams['customer'],
                $eventParams['total_price']
            );

            $shippingInfo = $eventParams['shipping_info'];
            $handlingFee =  $eventParams['handling_fee'];

            $freeQuotes = false;
            $quotableItems = $this->filterFreeShippingItems($eventParams['cart_items']);
            if(!count($quotableItems)){
                $freeQuotes = true;
            }

            /*
             * Calculate per product fees
             */

            $total_per_product_cost = 0;
            $active_items = $eventParams['cart_items'];
            foreach ( $active_items as $item ) {
                $product = $item->product;
                if ( $product ) {
                    $total_per_product_cost += $product->get_shipping_cost( $shippingInfo->country, $shippingInfo->state, $shippingInfo->zip ) * $item->quantity;
                }
            }


            /*
             * Apply quote data
             */
            $quotes = array();
            $shop_currency_code = Shop_CurrencySettings::get()->code;
            foreach($rates as $rateInfo){
                $quoteInfo = Shop_ShippingOptionQuote::createFromShippingRate($rateInfo, $shop_currency_code);
                $quote = $quoteInfo->getPrice() + $total_per_product_cost;
                if(is_numeric($handlingFee)){
                    $quote += $handlingFee;
                }
                $quoteInfo->setPrice($quote);
                $discount =  $discount_info->shipping_discount;
                if($discount){
                    $quoteInfo->setDiscount($discount);
                }

                //@todo: display consideration should not need to be applied here
                if ( $eventParams['display_prices_including_tax'] ) {
                    $quoteInfo->setTaxInclMode(false);
                    $shipping_taxes = Shop_TaxClass::get_shipping_tax_rates( $this->id, $shippingInfo, $quoteInfo->getPrice() );
                    $shippingTax = Shop_TaxClass::eval_total_tax( $shipping_taxes );
                    $quoteTaxInclusive = $quoteInfo->getPrice();
                    if($shippingTax) {
                        $quoteTaxInclusive = $quoteTaxInclusive + $shipping_taxes;
                    }
                    $quoteInfo->quote_tax_incl = $quoteTaxInclusive;
                    $quoteInfo->setTaxInclMode(true);
                }
                $quoteInfo->setRateInfo($rateInfo);

                if (
                    $freeQuotes ||
                    array_key_exists( $quoteInfo->getShippingOptionId(), $discount_info->free_shipping_options )
                    || array_key_exists( $quoteInfo->getShippingQuoteId(), $discount_info->free_shipping_options )
                    || $quoteInfo->getPrice() == 0
                ) {
                    $quoteInfo->setIsFree(true);
                }

                $eventParams['quote'] = $quoteInfo->getPrice();
                $quoteUpdates = Backend::$events->fireEvent( 'shop:onUpdateShippingQuote', $this, $eventParams, $quoteInfo );
                foreach ( $quoteUpdates as $quoteUpdate ) {
                    if ( is_numeric( $quoteUpdate ) ) { //legacy mode
                        traceLog('Warning: Event shop:onUpdateShippingQuote should return an updated Shop_ShippingOptionQuote object');
                        $quote_diff = $quoteUpdate - $quoteInfo->getPrice();
                        if($quote_diff > 0 ){
                            $quoteInfo->setDiscount(0);
                            $quoteInfo->setPrice($quoteUpdate);
                        } else {
                            $existingDiscount = $quoteInfo->getDiscount();
                            $quoteInfo->setDiscount($existingDiscount + abs($quote_diff));
                        }
                        break;
                    }
                    if(is_a($quoteUpdate, 'Shop_ShippingOptionQuote')){
                        $quoteInfo = $quoteUpdate;
                        break;
                    }
                }

                $quotes[] = $quoteInfo;

            }

            return $quotes;

        }


        /**
         * This applies shipping quotes to this shipping record.
         * Legacy support
         * @param Shop_ShippingOptionQuote[] $quotes
         * @return void
         */
        protected function applyQuotes($quotes, $eventParams){

            //convert quotes to another currency if specified in event parameters
            $currency_converter  = Shop_CurrencyConverter::create();
            $shop_currency_code = Shop_CurrencySettings::get()->code;
            $active_currency_code = (strlen($eventParams['currency_code']) == 3) ? $eventParams['currency_code'] : $shop_currency_code;
            if($active_currency_code !== $shop_currency_code){
                foreach($quotes as $quote){
                    $quote->currencyConvert($active_currency_code);
                }
            }

            $this->quotes = $quotes;

            Backend::$events->fireEvent(
                'shop:onAfterShippingQuoteApplied',
                $this,
                array(
                    'context' => (isset($eventParams['order']) && $eventParams['order']) ? 'order' : 'cart',
                    'cart_name' => isset($eventParams['cart_name']) ? $eventParams['cart_name'] : null,
                    'order' => isset($eventParams['order']) ? $eventParams['order'] : null,
                    'customer' =>  isset($eventParams['customer']) ? $eventParams['customer'] : null,
                    'shipping_info' => isset($eventParams['shipping_info']) ? $eventParams['shipping_info'] : null,
                )
            );

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


        private function onBeforeShippingQuote($eventParams){
            $eventParamUpdates = Backend::$events->fireEvent( 'shop:onBeforeShippingQuote', $this, $eventParams );
            foreach ( $eventParamUpdates as $eventParamUpdate ) {
                if ( is_array( $eventParamUpdate ) ) {
                    $eventParams   = array_merge( $eventParams, $eventParamUpdate );
                }
            }
            return $eventParams;
        }


        /**
         * Check for free_shipping discount parameter
         * on retail items and remove item from array if
         * free_shipping is true
         *
         * @param Shop_RetailItem[] $items
         * @return Shop_RetailItem[]
         */
        private function filterFreeShippingItems($items){
            $result = array();
            foreach($items as $item){
                if(property_exists($item, 'free_shipping') && $item->free_shipping){
                    continue;
                }
                $result[] = $item;
            }
            return $result;
        }

        private function getEventParamsForShippingOption(){
            return array(
                'handling_fee' => $this->handling_fee
            );
        }

        /**
         * This method builds an array of contextual/arbitrary data
         * to support legacy events.
         *
         * @param $order
         * @param $deferred_session_key
         * @return array|mixed
         */
        private function getEventParamsForOrder($order, $deferred_session_key=null){
            $cacheKey = 'order_'.$order->id.'_'.$deferred_session_key;
            if(isset(self::$eventParamCache[$cacheKey])){
                $params = self::$eventParamCache[$cacheKey];
            } else {
                $items = $order->items;
                $deferred_items = empty($deferred_session_key) ? false : $order->list_related_records_deferred('items',
                    $deferred_session_key);
                if ($deferred_items && $deferred_items->count) {
                    $items = $deferred_items; //consider deferred assignments in these calculations
                }
                $shipping_info = new Shop_AddressInfo();
                $shipping_info->load_from_order($order, false);
                $cart_items = $order->getCartItemsShipping($items);
                $include_tax = false;
                $customer = $order->customer_id ? Shop_Customer::create()->find($order->customer_id) : null;

                $params = array(
                    'cart_items' => $cart_items,
                    'city' => $shipping_info->city,
                    'country_id' => $shipping_info->country,
                    'coupon_code' => null,
                    'currency_code' => $order->get_currency_code(),
                    'customer' => is_object($customer) ? $customer : null,
                    'customer_id' => is_object($customer) ? $customer->id : $customer,
                    'display_prices_including_tax' => $include_tax ? $include_tax : Shop_CheckoutData::display_prices_incl_tax(),
                    //out of scope
                    'is_business' => $shipping_info->is_business,
                    'order' => $order,
                    'order_items' => $items,
                    'payment_method_obj' => $order->get_payment_method(),
                    'shipping_info' => $shipping_info,
                    'state_id' => $shipping_info->state,
                    'total_item_num' => $order->get_item_count(),
                    'total_price' => $order->eval_subtotal_before_discounts(),
                    'total_volume' => $order->get_total_volume(),
                    'total_weight' => $order->get_total_weight(),
                    'zip' => $shipping_info->zip,
                );
                self::$eventParamCache[$cacheKey] = $params;
            }
            return array_merge($params, $this->getEventParamsForShippingOption());
        }

        /**
         * This method returns an array of contextual/arbitrary data
         * to support legacy events.
         *
         * @param  string $cart_name
         * @return  array
         */
        private static function getEventParamsForCart($cart_name = 'main'){
            $cacheKey = 'cart_'.$cart_name;
            if(isset(self::$eventParamCache[$cacheKey])){
                $params = self::$eventParamCache[$cacheKey];
            } else {
                $customer = Cms_Controller::get_customer();
                $customer_id = $customer ? $customer->id : null;
                $shipping_info = Shop_CheckoutData::get_shipping_info();
                //run eval discounts on cart items to mark free shipping items, updates by reference
                $cart_items = Shop_Cart::list_active_items($cart_name);
                Shop_CheckoutData::eval_discounts($cart_name, $cart_items);
                $payment_method = Shop_CheckoutData::get_payment_method();
                $payment_method_obj = $payment_method->id ? Shop_PaymentMethod::create()->find($payment_method->id) : null;

                $params = array(
                    'cart_items' => $cart_items,
                    'cart_name' => $cart_name,
                    'city' => $shipping_info->city,
                    'country_id' => $shipping_info->country,
                    'coupon_code' => Shop_CheckoutData::get_coupon_code(),
                    'currency_code' => Shop_CheckoutData::get_currency($as_object = false),
                    'customer' => is_object($customer) ? $customer : null,
                    'customer_id' => is_object($customer) ? $customer->id : $customer,
                    'display_prices_including_tax' => Shop_CheckoutData::display_prices_incl_tax(),
                    'is_business' => $shipping_info->is_business,
                    'payment_method_obj' => $payment_method_obj,
                    'shipping_info' => $shipping_info,
                    'state_id' => $shipping_info->state,
                    'total_item_num' => Shop_Cart::get_item_total_num($cart_name),
                    'total_price' => Shop_Cart::total_price_no_tax($cart_name, false),
                    'total_volume' => Shop_Cart::total_items_volume($cart_items),
                    'total_weight' => Shop_Cart::total_items_weight($cart_items),
                    'zip' => $shipping_info->zip,
                );
                self::$eventParamCache[$cacheKey] = $params;
            }

            return $params;
        }


        /**
         * This method will cache an array of rates for the execution cycle.
         *
         * If `CACHE_SHIPPING_METHODS` is enabled in the application config
         * the rates for this shipping method will also be cached against the
         * user session. The rates cached on user session will
         * be flushed everytime the cart changes (cache key changes).
         *
         * @param string $key Cache key
         * @param Shop_ShippingRate[] $rates Array of rates
         * @return void
         */
        private function setCheckoutRateCache($key, $rates)
        {
            if($this->isArrayOfObjectType($rates, 'Shop_ShippingRate')) {
                self::$executionCache['ratesForCheckout'][$this->id][$key] = $rates;
                if (Phpr::$config->get('CACHE_SHIPPING_METHODS', false)) {
                    $cache_data = Phpr::$session->get('checkoutShippingRates');
                    $cache_data = $cache_data ? $cache_data : array();
                    $cache_data[$this->id] = array();
                    $cache_data[$this->id][$key] = $rates;
                    Phpr::$session->set('checkoutShippingRates', $cache_data);
                }
            }
        }

        /**
         * This method return cached array of rates for the execution cycle.
         *
         * If `CACHE_SHIPPING_METHODS` is enabled in the application config
         * rates may be returned from user session.
         *
         * @param string $key CacheKey
         * @return Shop_ShippingRate[]|null
         */
        private function getCheckoutRateCache($key){
            if(isset(self::$executionCache['ratesForCheckout'][$this->id][$key])){
               return self::$executionCache['ratesForCheckout'][$this->id][$key];
            }
            if (Phpr::$config->get('CACHE_SHIPPING_METHODS', false)) {
                $cache_data = Phpr::$session->get( 'checkoutShippingRates' );
                if ( is_array( $cache_data ) && isset( $cache_data[$this->id][$key] ) ) {
                   return $cache_data[$this->id][$key];
                }
            }
            return null;
        }


        /**
         * This method will cache an array of quotes for the execution cycle.
         * @param string $key Cache key
         * @param Shop_ShippingOptionQuote[] $quotes Array of quotes
         * @return void
         */
        private function setCheckoutQuoteCache($key, $quotes){
            if($this->isArrayOfObjectType($quotes, 'Shop_ShippingOptionQuote')) {
                self::$executionCache['quotesForCheckout'][$this->id][$key] = $quotes;
            }
        }

        /**
         * This method returns cached array of rates for the execution cycle.
         * @param string $key CacheKey
         * @return Shop_ShippingOptionQuote[]|null
         */
        private function getCheckoutQuoteCache($key){
            if(isset(self::$executionCache['quotesForCheckout'][$this->id][$key])){
                return self::$executionCache['quotesForCheckout'][$this->id][$key];
            }
            return null;
        }


        private function isArrayOfObjectType($array, $className){
            foreach($array as $obj){
                if(!is_a($obj, $className)){
                    return false;
                }
            }
            return true;
        }




        /**
         * Returns a hash for the cart items provided.
         * This is used to check if cart items have changed in
         * shipping quote context.
         * @param Shop_ShippableItem[] $items
         * @return string Hash
         */
        private static function getShippingItemsCacheKey($items){
            $str = null;
            foreach ($items as $item)
            {
                $product = $item->product;
                $str .= '__'.$product->om('sku').'_'.$item->quantity.'_'.$item->volume();
                $freeShipFlag = property_exists($item,'free_shipping') ? $item->free_shipping : null;
                $str .= $freeShipFlag ? '_freeship' : '';
            }

            return md5($str);
        }


        /**
         * Generate a cache key from parameters presented.
         * @param Shop_ShippableItem[] $cartItems Array of items
         * @param Shop_AddressInfo $addressInfo Address info
         * @param array $customParams Optional array of values to include in hash.
         * @return string HASH representing current state of checkout state
         */
        private static function generateShippingCacheKey($cartItems, $addressInfo, $customParams = array()){
            $paramCache = array();
            foreach($customParams as $pKey => $pValue) {
                $paramCache[$pKey] = is_string( $pValue ) ? $pValue : serialize($pValue);
            }
            $cacheKey = $addressInfo->getHash();
            $cacheKey .= self::getShippingItemsCacheKey($cartItems);
            $cacheKey .= serialize($paramCache);
            return md5($cacheKey);
        }

        private static function convertLegacyQuoteObj($obj){
            $failMsg = 'A Shop_ShippingOptionQuote could not be created from legacy code.';

            try {
                $objId = $obj->id;
                if(!$objId){
                    throw new Phpr_ApplicationException('No ID found');
                }
                $shippingOptionId = is_numeric($objId) ? $objId : Shop_ShippingOptionQuote::getOptionIdFromQuoteId($objId);
                $quote = new Shop_ShippingOptionQuote($shippingOptionId);

                $requiredProps = array(
                    'quote',
                    'quote_no_tax',
                    'quote_tax_incl',
                    'quote_no_discount',
                    'discount',
                );
                $skipProps = array(
                    'id',
                    'name',
                    'quote_data',
                    'currency_code'
                );

                foreach($requiredProps as $prop){
                    if(!property_exists($obj, $prop)){
                        throw new Phpr_ApplicationException('Missing field: '.$prop);
                    }
                }

                $props   = array_keys(get_object_vars($obj));
                foreach($props as $prop){
                    if(in_array($prop, $skipProps)){
                        continue;
                    }
                    $quote->$prop = $obj->$prop;
                }

                if(property_exists($obj, 'currency_code') && $obj->currency_code) {
                    $quote->setCurrencyCode($obj->currency_code);
                }
                if(property_exists($obj, 'quote_data')){
                    $quote->setQuoteData($obj->quote_data);
                }
                if(property_exists($obj, 'name')){
                    $quote->setShippingServiceName($obj->name);
                }

                return $quote;

            } catch (Exception $e){
                throw new Phpr_ApplicationException($failMsg);
            }

        }


        /**
         * @param string $weight The qualifying weight for shipping options
         * @param bool $frontendOnly Only include options that are flagged for frontend
         * @param bool $includeDisabled Include options that have been disabled
         * @param bool $includeZeroWeightOptions Include shipping options that are explicitly set up for ZERO WEIGHT
         *             even if the given $weight is not zero.
         * @return mixed
         */
        private static function findByWeight($weight, $frontendOnly = false, $includeDisabled = false, $includeZeroWeightOptions = false){
            $bind = array(
                'weight' => $weight
            );
            $shipping_options = Shop_ShippingOption::create();
            if ( $frontendOnly && !$includeDisabled ) {
                $shipping_options->where( 'enabled = 1' );
            }
            if ( !$frontendOnly && !$includeDisabled ) {
                $shipping_options->where( 'backend_enabled = 1' );
            }

            $zeroWeightSql = null;
            if(($weight !== 0) && $includeZeroWeightOptions){
                $zeroWeightSql = 'OR ((min_weight_allowed = 0) AND (max_weight_allowed = 0))';
            }

            $shipping_options->where(
                '((min_weight_allowed is null or min_weight_allowed <= :weight) 
                 AND (max_weight_allowed is null or max_weight_allowed >= :weight)) '.$zeroWeightSql,
                $bind
            );
            return $shipping_options->find_all();
        }


        /**
         * @param Shop_ShippingOption[] $shippingOptions
         * @param Shop_Customer|null $customer
         * @return Shop_ShippingOption[]
         */
        private static function applyFilterCustomerGroup( $shippingOptions, $customer){
            $results = array();
            foreach($shippingOptions as $shippingOption) {
                if($shippingOption->customer_groups->count()) {
                    if (!$customer) {
                        continue;
                    }
                    if (!self::option_visible_for_customer_group(
                        $shippingOption->id,
                        $customer->customer_group_id
                    )) {
                        continue;
                    }
                }
                $results[] = $shippingOption;
            }
            return $results;
        }

        private static function applyFilterCountry($shippingOptions, Shop_AddressInfo $address){
            $results = array();
            foreach ($shippingOptions as $shippingOption) {
                $shippingType = $shippingOption->get_shippingtype_object();
                if ($shippingType->config_countries()) {
                    $countries = $shippingOption->countries;
                    if($countries->count() == 0) {
                        $results[] = $shippingOption;
                        continue;
                    }
                    $countryIds = $countries->as_array('id');
                    if ($countryIds && !in_array($address->country, $countryIds)) {
                        continue;
                    }
                    $results[] = $shippingOption;
                }
            }
            return $results;
        }

        /**
         * @deprecated
         * Events in this method have been deprecated. Subscribe to alternative events:
         *     - shop:onUpdateShippingOptionsForOrder
         *     - shop:onUpdateShippingOptionsForCheckout
         * @param $shippingOptions
         * @param $eventParams
         * @return mixed
         */
        private static function applyLegacyFilterEvents($shippingOptions, $eventParams = array()){

            $eventParams['options'] = $shippingOptions;
            $optionsUpdates = Backend::$events->fireEvent( 'shop:onFilterShippingOptions', $eventParams );
            foreach ( $optionsUpdates as $optionsUpdate ) {
                traceLog('WARNING: Use of deprecated event shop:onFilterShippingOptions. Use shop:onFilterApplicableShippingOptionsForOrder or shop:onFilterApplicableShippingOptionsForCheckout');
                $shippingOptions = $optionsUpdate;
                break;
            }

            $shippingOptions = Backend::$events->fire_event(array('name' => 'shop:onUpdateShippingOptions', 'type' => 'update_result'), $shippingOptions, $eventParams);

            return $shippingOptions;
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
		 * Allows to update a shipping quote.
         *
		 * The event handler should accept 3 parameters
         *     - the Shop_ShippingOption object
         *     - an array of event parameters.
         *     - the Shop_ShippingOptionQuote object
         *
         * The event handler should return an updated Shop_ShippingOptionQuote object
         * Legacy support: Can also return an updated shipping quote (price).
         *
         * The <em>$params</em> are listed in methods:
         * - getEventParamsForOrder()
         * - getEventParamsForCart()
         *
         * Params can be updated via event @see shop:onBeforeShippingQuote
         *
		 * Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onUpdateShippingQuote', $this, 'update_shipping_quote');
		 * }
		 *
		 * public function update_shipping_quote($shippingOption, $eventParams, $shippingQuote)
		 * {
         *   $shippingQuote->setDiscount(1.99);
		 *   return $shippingQuote;
		 * }
		 * </pre>
		 * @event shop:onUpdateShippingQuote
		 * @package shop.events
		 * @see shop:onBeforeShippingQuote
		 * @see shop:onFilterShippingOptions
         *
		 * @param Shop_ShippingOption $shipping_option
		 * @param array $eventParams
         * @param Shop_ShippingOptionQuote
		 * @return number|Shop_ShippingOptionQuote Returns updated quote
		 */
		private function event_onUpdateShippingQuote($shippingOption, $eventParams, $shippingQuote ) {}

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
		private function event_onFilterShippingOptions($eventParams, $shippingOptions) {}

		/**
         * @deprecated
         * Use: shop:onUpdateShippingOptionsForCheckout or shop:onUpdateShippingOptionsForOrder
		 *
		 * Allows to update the shipping option list before it is displayed on the checkout pages.
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
         * @event shop:onUpdateShippingOptionsForCheckout
         * @package shop.events
         * @param Shop_ShippingOption[] $options Array of shipping options
         * @param string $cartName Name of cart
         * @param Shop_Customer|null $customer Customer
         * @return Shop_ShippingOption[] Array of shipping options
         */
        private function event_onUpdateShippingOptionsForCheckout($options, $cartName, $customer){}

        /**
         * @event shop:onUpdateShippingOptionsForOrder
         * @package shop.events
         * @param Shop_ShippingOption[] $options Array of shipping options
         * @param Shop_Order $order Order object
         * @param bool $frontendOnly True if front end only context
         * @param bool $includeDisabled True if disabled options can be included
         * @return Shop_ShippingOption[] Array of shipping options
         */
        private function event_onUpdateShippingOptionsForOrder($options, $order, $frontendOnly, $includeDisabled){}



        /**
         * @event shop:onUpdateShippingQuotesForCheckout
         * @package shop.events
         * @param Shop_ShippingOptionQuote[] $quotes Array of shipping quotes
         * @param string $cartName Cart Name
         * @return Shop_ShippingOptionQuote[] Array of shipping quotes
         */
        private function event_onUpdateShippingQuotesForCheckout($quotes, $cartName){}

        /**
         * @event shop:onUpdateShippingQuotesForOrder
         * @package shop.events
         * @param Shop_ShippingOptionQuote[] $quotes Array of shipping quotes
         * @param Shop_Order $order Order
         * @return Shop_ShippingOptionQuote[] Array of shipping quotes
         */
        private function event_onUpdateShippingQuotesForOrder($quotes, $order){}

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



		/**
		 * Executed everytime a shipping option is updated with quotes
		 * The event handler should accept 2 parameters - the Shop_ShippingOption object and an array of parameters that provide execution context.
		 * @event shop:onAfterShippingQuoteApplied
		 * @package shop.events
		 *
		 * @param Shop_ShippingOption $shipping_option The Shop_ShippingOption that has been updated with quotes
		 * @param array $event_params Parameters that help identify the execution context
		 * @return void
		 */
		private function event_onAfterShippingQuoteApplied($shipping_option, $event_params ) {}



        /*
         * Deprecated Methods
         */

        /**
         * @deprecated
         * @param $params
         * @return mixed|null
         */
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
                'country_id' => $request_params['country_id'],
                'state_id' => $request_params['state_id'],
                'zip' => $request_params['zip'],
                'city' => $request_params['city'],
                'total_price' => $request_params['total_price'],
                'total_volume' => $request_params['total_volume'],
                'total_weight' => $request_params['total_weight'],
                'total_item_num' => $request_params['total_item_num'],
                'is_business' => $request_params['is_business'],
                'customer_id' => $request_params['customer_id'],
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
         * @deprecated
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

            $shipping_info = $request_params['shipping_info'];
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

        /**
         * @deprecated
         * @see getShippingOptionsForCheckout();
         * @see getShippingOptionsForOrder();
         * @param $params
         * @return array
         */
        public static function get_applicable_options($params){
            $defaults = array(
                'shipping_info' => null,
                'total_price' => null,
                'total_volume' => null,
                'total_weight' => null,
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


        /**
         * @deprecated
         * @param $shipping_options
         * @param $params
         * @return array|mixed
         */
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
            $result = is_array($updated_options) ? $updated_options : $result;

            return $result;

        }


        /**
         * @deprecated use the getCurrency() method on the Shop_ShippingOptionQuote object
         * @return mixed
         */
        public function get_quote_currency(){
            foreach($this->getQuotes() as $quote){
                return $quote->getCurrencyCode();
            }
        }


        /**
         * @deprecated use the currencyConvert() method on the Shop_ShippingOptionQuote object
         * @param $to_currency_code
         * @return false|void
         */
        public function convert_to_currency($to_currency_code){
            if(!strlen(trim($to_currency_code)) == 3){
                return false;
            }
            foreach($this->getQuotes() as $quote){
                if($quote->getCurrencyCode() !== $to_currency_code) {
                    $quote->currencyConvert($to_currency_code);
                }
            }
        }

        /**
         * @deprecated
         * Cache is not reliable.
         * For example, it does not consider free item exclusions.
         * Caching of rates should be handled by the ShippingProvider / ShippingType
         */
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

        /**
         * @deprecated
         * Cache is not reliable.
         * For example, it does not consider free item exclusions.
         * Caching of rates should be handled by the ShippingProvider / ShippingType
         */
        protected function set_quote_cache($key, $value){

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

        /**
         * @deprecated
         * Cache is not reliable.
         * For example, it does not consider free item exclusions.
         * Caching of rates should be handled by the ShippingProvider / ShippingType
         */
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


        /**
         * @deprecated
         *
         */
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


            /*
             * Apply quote data
             */

            $quotes = is_array($quote) ? $quote : array($quote);
            $multi_quote = (count($quotes) > 1);
            $quoteObjs = array();
            foreach($quotes as $index => $q){
                $quoteObj = (is_array($q) || is_object($q)) ?  (object) $q : new stdClass();
                    if($multi_quote){
                        $name = $index;
                        $quoteObj->name = $name;
                        $quoteObj->sub_option_id = $quoteObj->id;
                        $quoteObj->id = $this->id . '_' . md5( $name );
                        $quoteObj->quote_data = is_array($q) ? $q  : array($q);
                    } else {
                        $quoteObj->quote = is_numeric($q) ? $q : $q->quote;
                        $quoteObj->id = $this->id;
                    }
                    $quoteObj->is_free = false;
                    if ( is_numeric( $this->handling_fee ) ) {
                        $quoteObj->quote = $quoteObj->quote + $this->handling_fee;
                    }
                    $discounted_quote          = max( ( $quoteObj->quote - $discount_info->shipping_discount ), 0 );
                    $quoteObj->quote_no_discount = $quoteObj->quote;
                    $quoteObj->quote_no_tax      = $discounted_quote;
                    $quoteObj->quote             = $discounted_quote;
                    $quoteObj->quote_tax_incl    = $discounted_quote;
                    $quoteObj->discount          = min($discount_info->shipping_discount,$quoteObj->quote_no_discount);

                    $shipping_taxes = Shop_TaxClass::get_shipping_tax_rates( $this->id, $params['shipping_info'], $quoteObj->quote );
                    $shippingTax = Shop_TaxClass::eval_total_tax( $shipping_taxes );
                    $quoteObj->quote_tax_incl = $quoteObj->quote_tax_incl + $shippingTax;
                    if ( $params['display_prices_including_tax'] ) {
                        $quoteObj->quote =  $quoteObj->quote  + $shippingTax;
                    }

                    $quoteObjs[] = self::convertLegacyQuoteObj($quoteObj);
            }

            //apply free options
            foreach($quoteObjs as $quoteObj){
                $sub_option_id = $this->id . '_' . $quoteObj->sub_option_id;
                if (
                    array_key_exists( $quoteObj->getShippingOptionId(), $discount_info->free_shipping_options )
                    || array_key_exists( $quoteObj->getShippingQuoteId(), $discount_info->free_shipping_options )
                    || $quoteObj->quote == 0) {
                    $quoteObj->is_free = true;
                }
            }


            $this->quotes = $quoteObjs;
            //converts quotes to active currency if specified in request parameters
            $currency_converter  = Shop_CurrencyConverter::create();
            $shop_currency_code = Shop_CurrencySettings::get()->code;
            $active_currency_code = (strlen($params['currency_code']) == 3) ? $params['currency_code'] : $shop_currency_code;
            if($this->get_quote_currency() !== $active_currency_code){
                $this->convert_to_currency($active_currency_code);
            }


            $event_params = array(
                'context' => (isset($params['order']) && $params['order']) ? 'order' : 'cart',
                'cart_name' => isset($params['cart_name']) ? $params['cart_name'] : null,
                'order' => isset($params['order']) ? $params['order'] : null,
                'customer' =>  isset($params['customer']) ? $params['customer'] : null,
                'shipping_info' => isset($params['shipping_info']) ? $params['shipping_info'] : null,
            );
            Backend::$events->fireEvent( 'shop:onAfterShippingQuoteApplied', $this, $event_params );
        }


        /**
         * @deprecated
         * @var array
         */
        private static $quote_cache = array();

        /**
         * @deprecated Use obj Shop_ShippingOptionQuote
        * Supplementary data added by shipping quote provider
        */
        public $quote_data = null;


        /**
         * @deprecated Use obj Shop_ShippingOptionQuote
         */
        public $is_free = false;

    }

	function phpr_sort_order_shipping_options($a, $b)
	{
		if ($a->error_hint)
			return -1;

		return 1;
	}