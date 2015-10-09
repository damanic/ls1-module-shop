<?

	class Shop_CanadaPostShipping extends Shop_ShippingType {
		private $host;

		/**
		 * Returns information about the shipping type
		 * Must return array with key 'name': array('name'=>'FedEx')
		 * @return array
		 */
		public function get_info() {
			return array(
				'name' => 'Canada Post',
				'description' => 'This shipping method allows to request quotes and shipping options from the Canada Post. You must have a Canada Post eParcel account in order to use this method.'
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
			
			if($context !== 'preview') {
				$host->add_field('cpcid', 'CPCID', 'left')->tab('General Settings')->comment('Your Canada Post <a onclick="return false" href="#" class="tooltip nolink" title="How to get your Canada Post Retailer ID<br/>1. Sign up for a VentureOne or Commercial account at https://www.canadapost.ca/cpid/apps/signup<br/>2. Email the Sell Online Help Desk at sellonline@canadapost.ca. Canada Post will send back a form to fill out your information<br/>3. Fill out and send back the form.<br/>4. When approved, use the retailer ID provided by Canada Post. Follow the instructions in the email to setup your shipping profile at Canada Post.">Retailer ID</a>. More information about Sell Online can be found on <a href="http://sellonline.canadapost.ca/FAQ.html" target="_blank">this page</a>.', 'above', true)->renderAs(frm_text)->validation()->required('Please specify your CPCID');
			}

			$host->add_field('turn_around_time', 'Turn Around Time', 'left', db_number)->tab('General Settings')->comment('The estimated hours between an order being placed and then being shipped. Basically, adds hours to and re-calculates the estimated delivery date. Overrides your default shipping profile setting at Canada Post. ', 'above')->renderAs(frm_text)->validation();
			
			$host->add_field('allowed_methods', 'Allowed methods')->tab('Shipping Parameters')->renderAs(frm_checkboxlist)->validation();

			$host->add_field('free_shipping_enabled', 'Enable free shipping option')->tab('Free Shipping')->renderAs(frm_checkbox)->validation();
			$host->add_field('free_shipping_option', 'Free shipping method')->tab('Free Shipping')->renderAs(frm_dropdown)->validation();
			$host->add_field('free_shipping_min_amount', 'Minimum order amount for free shipping', 'full', db_number)->tab('Free Shipping')->renderAs(frm_text)->validation();
		}
		
		public function get_allowed_methods_option_state($value = 1) {
			return is_array($this->host->allowed_methods) && in_array($value, $this->host->allowed_methods);
		}
		
		protected function get_service_list() {
			$services = array(
				'1010' => 'Domestic - Regular',
				'1020' => 'Domestic - Expedited',
				'1030' => 'Domestic - Xpresspost',
				'1040' => 'Domestic - Priority Courier',
				'2000' => 'USA - Tracked Packet',
				'2005' => 'USA - Small Packets Surface',
				'2015' => 'USA - Small Packets Air',
				'2020' => 'USA - Expedited US Business',
				'2030' => 'USA - Xpresspost USA',
				'2040' => 'USA - Priority Worldwide USA',
				'2050' => 'USA - Priority Worldwide PAK USA',
				'3000' => 'International - Tracked Packet',
				'3005' => 'International - Small Packets Surface',
				'3010' => 'International - Parcel Surface',
				'3015' => 'International - Small Packets Air',
				'3020' => 'International - Air International',
				'3025' => 'International - Xpresspost',
				'3040' => 'International - Priority Worldwide INTL',
				'3050' => 'International - Priority Worldwide PAK INTL'
			);
			
			return $services;
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
		 * 	array('name'=>'First class', 'id'=>'first_class'),
		 * 	array('name'=>'Priority', 'id'=>'priority'),
		 * )
		 * The options identifiers must match the identifiers returned by the get_quote() method.
		 * @param $host ActiveRecord object containing configuration fields values
		 * @return array
		 */
		public function list_enabled_options($host) {
			$result = array();
			
			$option_list = $this->get_allowed_methods_options();
			
			foreach($option_list as $option_id => $option_name) {
				if($this->get_allowed_methods_option_state($option_id))
					$result[] = array('id'=>$option_id, 'name'=>$option_name);
			}

			return $result;
		}
		
		/**
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host) {
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host) {
			if($host->free_shipping_enabled && !strlen($host->free_shipping_min_amount))
				$host->validation->setError('Please specify minimum order amount for free shipping or disable the free shipping option', 'free_shipping_min_amount', true);
		}
		
 		public function get_quote($parameters) {
			extract($parameters);
			
			$host = $host_obj;
	
			$shipping_params = Shop_ShippingParams::get();
			$currency = Shop_CurrencySettings::get();
			
			if(!$country = Shop_Country::create()->find($country_id))
				throw new Phpr_SystemException('Could not find the specified country.');

			$state = strlen($state_id) ? Shop_CountryState::create()->find($state_id) : null;
			
			$request = $this->format_xml_template('request.xml', array(
				'settings_obj' => $host,
				'shipping_params' => $shipping_params,
				'country' => $country,
				'state' => $state,
				'city' => $city,
				'zip' => $zip,
				'currency' => $currency,
				'weight' => $total_weight,
				'item_list' => $cart_items,
				'total_price' => $total_price
			));

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://sellonline.canadapost.ca:30000/");
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			$response = curl_exec($ch);
			if (curl_errno($ch))
				throw new Phpr_SystemException('An error occurred communicating with the Canada Post server.');
			
			curl_close($ch);

			$result = array();
			
			$allowed_services = $host->allowed_methods;
			$all_services = $this->get_service_list();

			if($host->free_shipping_enabled 
			&& $total_price >= $host->free_shipping_min_amount
			&& array_key_exists($this->host->free_shipping_option, $all_services)
			&& in_array($this->host->free_shipping_option, $allowed_services)) {
				$free_option_name = $all_services[$host->free_shipping_option];
				$result[$free_option_name] = array('id'=>$host->free_shipping_option, 'quote'=>0);
			}

			$currency_converter = Shop_CurrencyConverter::create();

			$doc = new DOMDocument('1.0');
			$doc->loadXML($response);
			$path = new DOMXPath($doc);
			
			$response_code = $path->query('//statusCode');

			if(!$response_code->length)
				throw new Phpr_SystemException('Invalid response received from Canada Post.');
				
			if($response_code->item(0)->nodeValue != 1) {
				$response_message = $path->query('//statusMessage');
				
				throw new Phpr_SystemException('Received error code ' . $response_code->item(0)->nodeValue . ' from Canada Post: ' . $response_message->item(0)->nodeValue);
			}
			
			$services = $path->query('/eparcel/ratesAndServicesResponse/product');
			
			foreach($services as $service) {
				$id = $service->getAttribute('id');
				
				if(!$id 
				|| !in_array($id, $allowed_services)
				|| !array_key_exists($id, $all_services))
					continue;
				
				$delivery_date = date('Y-m-d', strtotime($path->query('deliveryDate', $service)->item(0)->nodeValue));;
				$quote = $path->query('rate', $service)->item(0)->nodeValue;
				$currency_code = 'CAD';

				$quote = $currency_converter->convert_from($quote, $currency_code);

				$option_name = $all_services[$id];
				if (!array_key_exists($option_name, $result))
					$result[$option_name] = array('id' => $id, 'quote' => $quote, 'delivery_date' => $delivery_date);
				else
					$result[$option_name]['delivery_date'] = $delivery_date;
			}

			return $result;
		}
	}
