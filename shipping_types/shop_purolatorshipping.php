<?

	class Shop_PurolatorShipping extends Shop_ShippingType {
		const TEST_URL = "https://devwebservices.purolator.com/PWS/V1/Estimating/EstimatingService.asmx";
		const LIVE_URL = "https://webservices.purolator.com/PWS/V1/Estimating/EstimatingService.asmx";
		
		private $host;

		/**
		 * Returns information about the shipping type
		 * Must return array with key 'name': array('name'=>'FedEx')
		 * @return array
		 */
		public function get_info() {
			return array(
				'name' => 'Purolator',
				'description' => 'This shipping method allows to request quotes and shipping options from the Purolator (E-Ship). You must have a Purolator account in order to use this method.'
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
		 * @param mixed $host ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host, $context = null) {
			$this->host = $host;
			
			$this->host->add_field('test_mode', 'Developer Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Use the Purolator E-Ship Development Environment to try out API requests.', 'above', true);

			$this->host->add_field('billing_account_number', 'Billing Account Number', 'left')->tab('API Credentials')->comment('Required by Purolator, but no billing methods are used yet.', 'above')->renderAs(frm_text)->validation()->required('Please specify your Billing Account Number');
			
			if($context !== 'preview') {
				$this->host->add_field('api_key', 'Key', 'left')->tab('API Credentials')->comment('Purolator key.', 'above')->renderAs(frm_text)->validation()->required('Please specify API user name');
				$this->host->add_field('api_key_password', 'Key Password', 'right')->tab('API Credentials')->comment('Purolator key password.', 'above')->renderAs(frm_password)->validation();
			}

			$this->host->add_field('container', 'Container Type', 'left')->tab('Shipping Parameters')->renderAs(frm_dropdown)->validation()->required('Please specify container type');
			$this->host->add_field('pickup_type', 'Pickup Type', 'right')->tab('Shipping Parameters')->renderAs(frm_dropdown)->validation()->required('Please specify pickup type');
			$this->host->add_field('allowed_methods', 'Allowed methods')->tab('Shipping Parameters')->renderAs(frm_checkboxlist)->validation();
			$this->host->add_field('min_weight', 'Minimum Item Weight')->tab('Shipping Parameters')->comment('Minimum weight for one package. Purolator requires a minimum weight of 1 lb per item.', 'above')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a minimum weight.')->float();
			$this->host->add_field('max_weight', 'Maximum Item Weight')->tab('Shipping Parameters')->comment('Maximum weight for one package. Purolator requires a maximum weight of 150 lb per item.', 'above')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a maximum weight')->float();	
			
			$this->host->add_field('free_shipping_enabled', 'Enable free shipping option')->tab('Free Shipping')->renderAs(frm_checkbox)->validation();
			$this->host->add_field('free_shipping_option', 'Free shipping method')->tab('Free Shipping')->renderAs(frm_dropdown)->validation();
			$this->host->add_field('free_shipping_min_amount', 'Minimum order amount for free shipping', 'full', $type = db_number)->tab('Free Shipping')->renderAs(frm_text)->validation();
		}

		public function get_container_options($current_key_value = -1) {
			$container_types = array(
				'CustomerPackaging' => 'Customer Packaging',
				'ExpressBox' => 'Express Box',
				'ExpressEnvelope' => 'Express Envelope', 
				'ExpressPack' => 'Express Pack'
			);
			
			if($current_key_value == -1)
				return $container_types;

			return array_key_exists($current_key_value, $container_types) ? $container_types[$current_key_value] : null;
		}
		
		protected function get_service_list() {
			$services = array(
				'PurolatorExpress' => 'Purolator Express',
				'PurolatorExpress9AM' => 'Purolator Express 9AM',
				'PurolatorExpress10:30AM' => 'Purolator Express 10:30AM',
				'PurolatorExpressEvening' => 'Purolator Express Evening',
				'PurolatorExpressEnvelope' => 'Purolator Express Envelope',
				'PurolatorExpressEnvelope9AM' => 'Purolator Express Envelope 9AM',
				'PurolatorExpressEnvelope10:30AM' => 'Purolator Express Envelope 10:30AM',
				'PurolatorExpressEnvelopeEvening' => 'Purolator Express Envelope Evening',
				'PurolatorExpressPack' => 'Purolator Express Pack',
				'PurolatorExpressPack9AM' => 'Purolator Express Pack 9AM',
				'PurolatorExpressPack10:30AM' => 'Purolator Express Pack 10:30AM',
				'PurolatorExpressPackEvening' => 'Purolator Express Pack Evening',
				'PurolatorExpressBox' => 'Purolator Express Box',
				'PurolatorExpressBox9AM' => 'Purolator Express Box 9AM',
				'PurolatorExpressBox10:30AM' => 'Purolator Express Box 10:30AM',
				'PurolatorExpressBoxEvening' => 'Purolator Express Box Evening',
				'PurolatorExpressU.S.' => 'Purolator Express U.S.',
				'PurolatorExpressU.S.Envelope' => 'Purolator Express U.S. Envelope',
				'PurolatorExpressU.S.Pack' => 'Purolator Express U.S. Pack',
				'PurolatorExpressU.S.Box' => 'Purolator Express U.S. Box',
				'PurolatorExpressInternational' => 'Purolator Express International',
				'PurolatorExpressInternationalEnvelope' => 'Purolator Express International Envelope',
				'PurolatorExpressInternationalPack' => 'Purolator Express International Pack',
				'PurolatorExpressInternationalBox' => 'Purolator Express International Box',
				'PurolatorGround' => 'Purolator Ground',
				'PurolatorGround9AM' => 'Purolator Ground 9AM',
				'PurolatorGround10:30AM' => 'Purolator Ground 10:30AM',
				'PurolatorGroundEvening' => 'Purolator Ground Evening',
				'PurolatorGroundU.S.' => 'Purolator Ground U.S.'
			);

			return $services;
		}
		
		public function get_pickup_type_options($current_key_value = -1) {
			$types = array(
				'DropOff' => 'Drop off',
				'HoldForPickup' => 'Hold For Pickup',
				'PreScheduled' => 'Pre-Scheduled'
			);
			
			if($current_key_value == -1)
				return $types;

			return array_key_exists($current_key_value, $types) ? $types[$current_key_value] : null;
		}
		
		public function get_allowed_methods_option_state($value = 1) {
			return is_array($this->host->allowed_methods) && in_array($value, $this->host->allowed_methods);
		}

		public function get_free_shipping_option_options($current_key_value = -1) {
			$options = $this->get_service_list();
			
			if($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}

		public function get_allowed_methods_options($current_key_value = -1) {
			$options = $this->get_service_list();

			if($current_key_value == -1)
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
		 *   array('name'=>'First class', 'id'=>'first_class'),
		 *   array('name'=>'Priority', 'id'=>'priority'),
		 * )
		 * The options identifiers must match the identifiers returned by the get_quote() method.
		 * @param $host ActiveRecord object containing configuration fields values
		 * @return array
		 */
		public function list_enabled_options($host) {
			$result = array();
			
			$options = $this->get_allowed_methods_options();
			
			foreach($options as $option_id => $option_name) {
				if($this->get_allowed_methods_option_state($option_id))
					$result[] = array('id' => $option_id, 'name' => $option_name);
			}

			return $result;
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host ActiveRecord Object containing configuration fields values
		 */
		public function validate_config_on_save($host) {
			if($host->free_shipping_enabled && !strlen($host->free_shipping_min_amount))
				$host->validation->setError('Please specify minimum order amount for free shipping or disable the free shipping option', 'free_shipping_min_amount', true);
				
			$hash_value = trim($host->api_key_password); 
			
			if(!strlen($hash_value)) { 
				if(!isset($host->fetched_data['api_key_password']) || !strlen($host->fetched_data['api_key_password'])) 
					$host->validation->setError('Please enter your API key password', 'api_key_password', true); 

				$host->api_key_password = $host->fetched_data['api_key_password']; 
			}
		}
		
		/**
		 * Initializes configuration data when the shipping method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host) {
			$this->host->test_mode = true;
			$this->host->min_weight = 1;
			$this->host->max_weight = 150;
		}
		
 		public function get_quote($parameters) {
			extract($parameters);
			
			$host = $host_obj;

			$shipping_params = Shop_ShippingParams::get();
			$currency = Shop_CurrencySettings::get();
			
			if(!$country = Shop_Country::create()->find($country_id))
				throw new Phpr_SystemException('Could not find the specified country.');

			$state = $state_id ? Shop_CountryState::create()->find($state_id) : null;

			$result = array();
			
			$allowed_services = $host->allowed_methods;
			$all_services = $this->get_service_list();

			if($this->host->free_shipping_enabled 
			&& $total_price >= $this->host->free_shipping_min_amount
			&& array_key_exists($this->host->free_shipping_option, $all_services)
			&& in_array($this->host->free_shipping_option, $allowed_services)) {
				$free_option_name = $all_services[$this->host->free_shipping_option];
				$result[$free_option_name] = array('id' => $this->host->free_shipping_option, 'quote' => 0);
			}

			$currency_converter = Shop_CurrencyConverter::create();

			$client = new SoapClient(PATH_APP . "/modules/shop/shipping_types/shop_purolatorshipping/wsdl/Development/EstimatingService.wsdl", array(
				'trace' => true,
				'location' => $this->host->test_mode ? self::TEST_URL : self::LIVE_URL,
				'uri' => "http://purolator.com/pws/datatypes/v1",
				'login' => $this->host->api_key,
				'password' => $this->host->api_key_password
			));
			
			$headers[] = new SoapHeader("http://purolator.com/pws/datatypes/v1", 'RequestContext', array(
				'Version' => '1.3',
				'Language' => 'en',
				'GroupID' => 'xxx',
				'RequestReference' => 'Rating Example'
			)); 
			
			$client->__setSoapHeaders($headers);
			
			$total_weight = $total_weight > $this->host->min_weight ? $total_weight : $this->host->min_weight;
			
			$units = array(
				'KGS' => 'kg',
				'LBS' => 'lb'
			);
			
			$weight_unit = $units[h($shipping_params->weight_unit)];
				
			$request = (object)array();

			$request->BillingAccountNumber = $this->host->billing_account_number;

			$request->SenderPostalCode = h($shipping_params->zip_code);

			$request->ReceiverAddress = (object)array();
			$request->ReceiverAddress->City = h($city);
			
			if($state)
				$request->ReceiverAddress->Province = h($state->code);
			
			$request->ReceiverAddress->Country = h($country->code);
			$request->ReceiverAddress->PostalCode = h($zip);  

			$request->PackageType = $this->host->container;
			
			$services = array();

			for($i = 0, $l = ceil($total_weight / $this->host->max_weight); $i < $l; ++$i) {
				if($i == $l - 1) 
					$weight = $total_weight % $this->host->max_weight; // use the remainder weight, rather than the max weight
				else
					$weight = $this->host->max_weight;
				
				if($weight < $this->host->min_weight)
					$weight = $this->host->min_weight;

				$request->TotalWeight = (object)array();
				$request->TotalWeight->Value = $weight;
				$request->TotalWeight->WeightUnit = $weight_unit;

				$response = $client->GetQuickEstimate($request);

				if(!$response)
					throw new Phpr_SystemException('Invalid response received from Purolator.');
				
				if(isset($response->ResponseInformation->Errors) && isset($response->ResponseInformation->Errors->Error)) {
					if(is_array($response->ResponseInformation->Errors->Error)) {
						$codes = array();
						$messages = array();
						
						foreach($response->ResponseInformation->Errors->Error as $error) {
							$codes[] = $error->Code;
							$messages[] = $error->Description;
						}
						
						throw new Phpr_SystemException('Received error codes from Purolator: ' . implode('/', $codes) . '. Message: ' . implode(' Message: ', $messages));
					}
					else {
						throw new Phpr_SystemException('Received error code ' . $response->ResponseInformation->Errors->Error->Code . ' from Purolator: ' . $response->ResponseInformation->Errors->Error->Description);
					}
				}
				
				foreach(is_array($response->ShipmentEstimates->ShipmentEstimate) ? $response->ShipmentEstimates->ShipmentEstimate : $response->ShipmentEstimates as $service) {
					$id = $service->ServiceID;
					$total = $service->TotalPrice;
					$currency_code = 'CAD';
					$delivery_date = $service->ExpectedDeliveryDate;

					$total = $currency_converter->convert_from($total, $currency_code);
						
					if(!isset($services[$id]))
						$services[$id] = array();
						
					if(!isset($services[$id]['total']))
						$services[$id]['total'] = 0;
					
					$services[$id]['total'] += $total;
					$services[$id]['delivery_date'] = date('Y-m-d', strtotime($delivery_date));
				}
			}

			foreach($services as $id => $service) {
				if(!in_array($id, $allowed_services))
					continue;
				
				$option_name = $all_services[$id];

				if(!array_key_exists($option_name, $result))
					$result[$option_name] = array('id' => $id, 'quote' => $service['total'], 'delivery_date' => $service['delivery_date']);
				else
					$result[$option_name]['delivery_date'] = $service['delivery_date'];
			}

			return $result;
		}
	}