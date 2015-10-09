<?

	class Shop_UpsShipping extends Shop_ShippingType
	{
		private $host_obj;

		/**
		 * Returns information about the shipping type
		 * Must return array with key 'name': array('name'=>'FedEx')
		 * @return array
		 */
		public function get_info()
		{
			return array(
				'name'=>'UPS',
				'description'=>'This shipping method allows to request quotes and shipping options from the United Parcel Service of America (UPS). You must have a UPS account in order to use this method.'
			);
		}
		
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
		public function build_config_ui($host_obj, $context = null)
		{
			$this->host_obj = $host_obj;
			
			$host_obj->add_field('test_mode', 'Developer Mode')->tab('API Credentials')->renderAs(frm_onoffswitcher)->comment('Use the UPS Test Environment to try out API requests.', 'above', true);
			
			if ($context !== 'preview')
			{
				$host_obj->add_field('api_user', 'User Name', 'left')->tab('API Credentials')->comment('UPS API user name (case sensitive).', 'above')->renderAs(frm_text)->validation()->required('Please specify API user name');
				$host_obj->add_field('api_password', 'User Password', 'right')->tab('API Credentials')->comment('UPS API password.', 'above')->renderAs(frm_password)->validation();
				$host_obj->add_field('access_key', 'API Access Key')->tab('API Credentials')->comment('UPS API access key.', 'above')->renderAs(frm_text)->validation()->required('Please specify API access key');
			}

			$host_obj->add_field('container', 'Container Type', 'left')->tab('Shipping Parameters')->renderAs(frm_dropdown)->validation()->required('Please specify container type');
			$host_obj->add_field('pickup_type', 'Pickup Type', 'right')->tab('Shipping Parameters')->renderAs(frm_dropdown)->validation()->required('Please specify pickup type');
			$host_obj->add_field('allowed_methods', ' Allowed methods')->tab('Shipping Parameters')->renderAs(frm_checkboxlist)->validation();
			$host_obj->add_field('min_weight', 'Minimum Item Weight')->tab('Shipping Parameters')->comment('The minimum weight for one package you are going to deliver. Orders will be forced to have this minimum weight. UPS requires a minimum weight of 1 lb.', 'above')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a minimum weight.')->float();
			$host_obj->add_field('max_weight', 'Maximum Item Weight')->tab('Shipping Parameters')->comment('The maximum weight for one package you are going to deliver. Orders with weight exceeding this value will be split into smaller packages. UPS requires a maximum weight of 150 lb.', 'above')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a maximum weight')->float();	
			
			$host_obj->add_field('use_negotiated_rates','Use negotiated rates')->comment('Please specify your shipper number in the field below if you want to use the negotiated rates feature.')->tab('Shipping Parameters')->renderAs(frm_checkbox);
			$host_obj->add_field('shipper_number','Shipper number')->tab('Shipping Parameters')->renderAs(frm_text)->comment('The Shipper Number is required in order to receive negotiated rates from UPS. It is case sensitive and usually the same as user name.', 'above');
			$host_obj->add_field('add_insured_value','Add insured value')->tab('Shipping Parameters')->renderAs(frm_checkbox);
			
			$host_obj->add_field('free_shipping_enabled', ' Enable free shipping option')->tab('Free Shipping')->renderAs(frm_checkbox)->validation();
			$host_obj->add_field('free_shipping_option', ' Free shipping method')->tab('Free Shipping')->renderAs(frm_dropdown)->validation();
			$host_obj->add_field('free_shipping_min_amount', 'Minimum order amount for free shipping', 'full', $type = db_number)->tab('Free Shipping')->renderAs(frm_text)->validation();
		}

		public function get_container_options($current_key_value = -1)
		{
			$container_types = array(
				'00'=>'Unknown',
				'01'=>'UPS letter',
				'02'=>'Customer supplied package',
				'03'=>'Tube',
				'04'=>'PAK',
				'21'=>'UPS express box',
				'2a'=>'UPS small express box',
				'2b'=>'UPS medium express box',
				'2c'=>'UPS large express box',
				'24'=>'UPS 25KG box',
				'25'=>'UPS 10KG box', 
				'2a'=>'Small express box',
				'2b'=>'Medium express box',
				'2c'=>'Large express box',
				'30'=>'Pallet'
			);
			
			if ($current_key_value == -1)
				return $container_types;

			return array_key_exists($current_key_value, $container_types) ? $container_types[$current_key_value] : null;
		}
		
		protected function get_service_list()
		{
			$services = array(
				'USA'=>array(
					'01'=>'UPS Next Day Air®',
					'02'=>'UPS Second Day Air®',
					'03'=>'UPS Ground',
					'07'=>'UPS Worldwide Express',
					'08'=>'UPS Worldwide Expedited',
					'11'=>'UPS Standard',
					'12'=>'UPS Three-Day Select®',
					'13'=>'UPS Next Day Air Saver®',
					'14'=>'UPS Next Day Air® Early A.M.',
					'54'=>'UPS Worldwide Express Plus',
					'59'=>'UPS Second Day Air A.M.®',
					'65'=>'UPS Saver'
				),
				'PRI'=>array(
					'01'=>'UPS Next Day Air®',
					'02'=>'UPS Second Day Air®',
					'03'=>'UPS Ground',
					'07'=>'UPS Worldwide Express',
					'08'=>'UPS Worldwide Expedited',
					'14'=>'UPS Next Day Air® Early A.M.',
					'54'=>'UPS Worldwide Express Plus',
					'65'=>'UPS Saver'
				),
				'CAN'=>array(
					'01'=>'UPS Express',
					'02'=>'UPS Expedited',
					'07'=>'UPS Worldwide Express',
					'08'=>'UPS Worldwide Expedited',
					'11'=>'UPS Standard',
					'12'=>'UPS Three-Day Select®',
					'13'=>'UPS Express Saver',
					'14'=>'UPS Express Early A.M.',
					'65'=>'UPS Saver'
				),
				'MEX'=>array(
					'07'=>'UPS Express',
					'08'=>'UPS Expedited',
					'54'=>'UPS Express Plus',
					'65'=>'UPS Saver'
				),
				'POL'=>array(
					'07'=>'UPS Express',
					'08'=>'UPS Expedited',
					'11'=>'UPS Standard',
					'54'=>'UPS Worldwide Express Plus',
					'65'=>'UPS Saver',
					'82'=>'UPS Today Standard',
					'83'=>'UPS Today Dedicated Courier',
					'84'=>'UPS Today Intercity',
					'85'=>'UPS Today Express',
					'86'=>'UPS Today Express Saver'
				),
				'DEFAULT'=>array(
					'07'=>'UPS Express',
					'08'=>'UPS Expedited',
					'11'=>'UPS Standard',
					'54'=>'UPS Worldwide Express Plus',
					'65'=>'UPS Saver'
				)
			);
			
			$shipping_params = Shop_ShippingParams::get();
			$country_code = $shipping_params->country ? $shipping_params->country->code_3 : 'DEFAULT';
			if (!array_key_exists($country_code, $services))
				$country_code = 'DEFAULT';

			return $services[$country_code];
		}
		
		public function get_pickup_type_options($current_key_value = -1)
		{
			$types = array(
				'01'=>'Daily pickup',
				'03'=>'Customer counter',
				'06'=>'One time pickup',
				'07'=>'On call air',
				'19'=>'Letter center',
				'20'=>'Air service center'
			);
			
			if ($current_key_value == -1)
				return $types;

			return array_key_exists($current_key_value, $types) ? $types[$current_key_value] : null;
		}
		
		public function get_allowed_methods_option_state($value = 1)
		{
			return is_array($this->host_obj->allowed_methods) && in_array($value, $this->host_obj->allowed_methods);
		}

		public function get_free_shipping_option_options($current_key_value = -1)
		{
			$options = $this->get_service_list();
			
			if ($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}

		public function get_allowed_methods_options($current_key_value = -1)
		{
			$options = $this->get_service_list();

			if ($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}
		
		/**
		 * The Discount Engine uses this method for displaying a list of available shipping options on the 
		 * Free Shipping tab of the Cart Price Rule form. 
		 * 
		 * For multi-options shipping methods (like USPS or UPS) this method should return a list of
		 * enabled options in the following format:
		 * array(
		 * 	array('name'=>'First class', 'id'=>'first_class'),
		 * 	array('name'=>'Priority', 'id'=>'priority'),
		 * )
		 * The options identifiers must match the identifiers returned by the get_quote() method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @return array
		 */
		public function list_enabled_options($host_obj)
		{
			$result = array();
			
			$options = $this->get_allowed_methods_options();
			foreach ($options as $option_id=>$option_name)
			{
				if ($this->get_allowed_methods_option_state($option_id))
					$result[] = array('id'=>$option_id, 'name'=>$option_name);
			}

			return $result;
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord Object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
			if ($host_obj->free_shipping_enabled && !strlen($host_obj->free_shipping_min_amount))
				$host_obj->validation->setError('Please specify minimum order amount for free shipping or disable the free shipping option', 'free_shipping_min_amount', true);
				
			$hash_value = trim($host_obj->api_password); 
			if (!strlen($hash_value)) 
			{ 
				if (!isset($host_obj->fetched_data['api_password']) || !strlen($host_obj->fetched_data['api_password'])) 
					$host_obj->validation->setError('Please enter API password', 'api_password', true); 

				$host_obj->api_password = $host_obj->fetched_data['api_password']; 
			}
		}
		
		/**
		 * Initializes configuration data when the shipping method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host_obj) 
		{
			$this->host_obj->test_mode = true;
			$this->host_obj->min_weight = 1;
			$this->host_obj->max_weight = 150;
		}
		
		/**
		 * Validates configuration data after it is loaded from database
		 * Use host object to access fields previously added with build_config_ui method.
		 * You can alter field values if you need
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_load($host_obj)
		{
				if(!isset($host_obj->test_mode))
					$host_obj->test_mode = true;
					
				if(!isset($host_obj->min_weight))
					$host_obj->min_weight = 1;
					
				if(!isset($host_obj->max_weight))
					$host_obj->max_weight = 150;
		}
		
 		public function get_quote($parameters) 
		{
			extract($parameters);

			$shipping_params = Shop_ShippingParams::get();
			$currency = Shop_CurrencySettings::get();
			
			$country = Shop_Country::create()->find($country_id);
			if (!$country)
				return null;

			$state = null;
			if (strlen($state_id))
				$state = Shop_CountryState::create()->find($state_id);
			
			$allowed_methods = $host_obj->allowed_methods;
			$all_methods = $this->get_service_list();
			
			$access_doc = $this->format_xml_template('access_doc.xml', array('settings_obj'=>$host_obj));

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $host_obj->test_mode ? "https://wwwcie.ups.com/ups.app/xml/Rate" : "https://www.ups.com/ups.app/xml/Rate");
//			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 130);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			if(!isset($this->host_obj->min_weight))
				$this->host_obj->min_weight = 1;
			if(!isset($this->host_obj->max_weight))
				$this->host_obj->max_weight = 150;

			$services = array();
			
			if($total_weight < $this->host_obj->min_weight)
				$total_weight = $this->host_obj->min_weight;

			for($i = 0, $l = ceil($total_weight / $this->host_obj->max_weight); $i < $l; ++$i) {
				if($i == $l - 1) 
					$weight = ceil($total_weight % $this->host_obj->max_weight); // use the remainder weight, rather than the max weight
				else
					$weight = $this->host_obj->max_weight;
				
				if($weight < $this->host_obj->min_weight)
					$weight = $this->host_obj->min_weight;

				$request_doc = $this->format_xml_template('request_doc.xml', array(
					'settings_obj'=>$host_obj,
					'shipping_params'=>$shipping_params,
					'country'=>$country,
					'state'=>$state,
					'city'=>$city,
					'zip'=>$zip,
					'currency'=>$currency,
					'total_price'=>$total_price,
					'weight'=>$weight,
					'is_business'=>$is_business
				));

				curl_setopt($ch, CURLOPT_POSTFIELDS, $access_doc.$request_doc);
				
				$response = curl_exec($ch);
				if (curl_errno($ch))
					throw new Phpr_SystemException('An error occurred communicating with the UPS server.');

				$result = array();
	
				if ($host_obj->free_shipping_enabled 
					&& $total_price >= $host_obj->free_shipping_min_amount
					&& array_key_exists($host_obj->free_shipping_option, $all_methods)
				)
				{
					$free_option_name = $all_methods[$host_obj->free_shipping_option];
					$result[$free_option_name] = array('id'=>$host_obj->free_shipping_option, 'quote'=>0);
				}
			
				$currency_converter = Shop_CurrencyConverter::create();

				$doc = new DOMDocument('1.0');
				$doc->loadXML($response);
				$xPath = new DOMXPath($doc);

				$response_code = $xPath->query('//RatingServiceSelectionResponse/Response/ResponseStatusCode');
				if (!$response_code->length)
					continue;

				if ($response_code->item(0)->nodeValue != 1)
				{
					if ($response_code->item(0)->nodeValue == 0)
					{
						$error_text = $xPath->query('//RatingServiceSelectionResponse/Response/Error/ErrorDescription');
						if ($error_text->item(0)->nodeValue)
							throw new Phpr_ApplicationException($error_text->item(0)->nodeValue);
					}
					
					continue;
				}

				$rates = $xPath->query('//RatingServiceSelectionResponse/RatedShipment');

				foreach ($rates as $rate)
				{
					$id = $xPath->query('Service/Code', $rate)->item(0)->nodeValue;

					if (!in_array($id, $allowed_methods))
						continue;

					if (!array_key_exists($id, $all_methods))
						continue;
					
					if($host_obj->use_negotiated_rates && $xPath->evaluate('count(NegotiatedRates/NetSummaryCharges/GrandTotal/MonetaryValue)', $rate))
					{
						$total = $xPath->query('NegotiatedRates/NetSummaryCharges/GrandTotal/MonetaryValue', $rate)->item(0)->nodeValue;
						$currency_code = $xPath->query('NegotiatedRates/NetSummaryCharges/GrandTotal/CurrencyCode', $rate)->item(0)->nodeValue;
					}
					else
					{
						$total = $xPath->query('TotalCharges/MonetaryValue', $rate)->item(0)->nodeValue;
						$currency_code = $xPath->query('TotalCharges/CurrencyCode', $rate)->item(0)->nodeValue;
					}
					$option_name = $all_methods[$id];

					if (array_key_exists($option_name, $result))
						continue;

					$total = $currency_converter->convert_from($total, $currency_code);
					
					if(!isset($services[$id]))
						$services[$id] = array();
						
					if(!isset($services[$id]['total']))
						$services[$id]['total'] = 0;
					
					$services[$id]['total'] += $total;
				}
			}

			curl_close($ch);

			foreach($services as $id => $service) {
				$option_name = $all_methods[$id];

				if(!array_key_exists($option_name, $result))
					$result[$option_name] = array('id' => $id, 'quote' => $service['total']);
			}

			return $result;
		}
	}
