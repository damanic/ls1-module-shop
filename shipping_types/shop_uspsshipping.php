<?

	class Shop_UspsShipping extends Shop_ShippingType
	{
		private $host_obj;
		
		/*
		* Array of domestic services used in LS
		* (third parameter should be true to indicate a service that only returns commercial rates)
		*/
		protected $domestic_service_list = array(
			'FIRST CLASS'=>array('First class', 0, false),
			'PRIORITY'=>array('Priority', 1, false),
			'PARCEL'=>array('Parcel', 4, false),
			'MEDIA'=>array('Media', 6, false),
			'LIBRARY'=>array('Library', 7, false),
			'PRIORITY_SMALL_FLAT'=>array('Priority Mail Small Flat Rate Box', 28, false),
			'PRIORITY_MED_FLAT'=>array('Priority Mail Regular Flat Rate Box', 17, false),
			'PRIORITY_LARGE_FLAT'=>array('Priority Mail Large Flat Rate Box', 22, false),
			'EXPRESS_HOLD_FOR_PICKUP'=>array('Express Mail Hold for Pickup', 2, false),
			'EXPRESS_PO_ADDRESSEE'=>array('Express Mail PO to Addressee', 3, false),
			'EXPRESS_FLAT_ENV'=>array('Express Mail Flat-Rate Envelope', 13, false),
			'EXPRESS_SUN_HOLIDAY'=>array('Express Mail Sunday/Holiday', 23, false),
			'EXPRESS_FLAT_ENV_SUN_HOLIDAY'=>array('Express Mail Flat-Rate Envelope Sunday/Holiday', 25, false),
			'EXPRESS_FLAT_ENV_PICKUP'=>array('Express Mail Flat-Rate Envelope Hold For Pickup', 27, false),
			'REGIONAL_BOX_A'=>array('Priority Mail Regional Rate Box A', 47, true),
			'REGIONAL_BOX_B'=>array('Priority Mail Regional Rate Box B', 49, true),
			'REGIONAL_BOX_C'=>array('Priority Mail Regional Rate Box C', 58, true),
			'REGIONAL_BOX_C_PICKUP'=>array('Priority Mail Regional Rate Box C Hold For Pickup', 59, true),
			'FIRST_CLASS_PACKAGE'=>array('First-Class Package Service', 61, true),
			'PRIORITY_EXPRESS_PADDED_FLAT'=>array('Priority Mail Express Padded Flat Rate Envelope', 62, true),
			'PRIORITY_EXPRESS_PADDED_FLAT_HOLD'=>array('Priority Mail Express Padded Flat Rate Envelope Hold For Pickup', 63, true),
			'PRIORITY_EXPRESS_SUNDAY_HOLIDAY'=>array('Priority Mail Express Sunday/Holiday Delivery Padded Flat Rate Envelope', 64, true)
		);

		/**
		 * Returns information about the shipping type
		 * Must return array with key 'name': array('name'=>'FedEx')
		 * Also the result can contain an optional 'description'
		 * @return array
		 */
		public function get_info()
		{
			return array(
				'name'=>'USPS',
				'description'=>'This shipping method allows to request shipping options and quotes from USPS - The United States Postal Service (U.S. Postal Service). You must have a USPS account in order to use this method.'
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

			$host_obj->add_field('use_test_server', 'Use Test Server')->tab('General Settings')->renderAs(frm_onoffswitcher)->comment('Connect to USPS test server. Use this option of you have test USPS account.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_field('api_user_id', 'USPS User Id')->tab('General Settings')->comment('This attribute specifies your Web Tools ID.', 'above')->renderAs(frm_text)->validation()->required('Please specify USPS User Id');
			}
			
			$host_obj->add_field('machinable', 'All packages are machinable')->tab('General Settings')->renderAs(frm_checkbox);
			$host_obj->add_field('commercial_rates', 'Use commercial rates')->tab('General Settings')->renderAs(frm_checkbox);

			$host_obj->add_field('domestic_allowed_services', 'Allowed domestic shipping services')->tab('Domestic Shipping')->renderAs(frm_checkboxlist)->validation();
			$host_obj->add_field('package_size', 'Package size')->tab('Domestic Shipping')->renderAs(frm_radio)->validation()->required('Please specify the package size');
			
			$host_obj->add_form_section('Please specify the package dimensions, in inches. Dimensions are optional for the Regular package size.', 'Package dimensions')->tab('Domestic Shipping');

			$host_obj->add_field('package_width', 'Width', 'left')->tab('Domestic Shipping')->renderAs(frm_text)->validation()->float();
			$host_obj->add_field('package_height', 'Height', 'right')->tab('Domestic Shipping')->renderAs(frm_text)->validation()->float();
			$host_obj->add_field('package_length', 'Length', 'left')->tab('Domestic Shipping')->renderAs(frm_text)->validation()->float();
			$host_obj->add_field('package_girth', 'Girth', 'right')->tab('Domestic Shipping')->renderAs(frm_text)->validation()->float();
			
			$host_obj->add_field('intl_display_transit_time', 'Display transit time')->tab('International Shipping')->renderAs(frm_checkbox)->comment('Use this checkbox if you want the transit times returned by the USPS web service to be displayed on the front-end store.');

			$host_obj->add_field('international_allowed_services', 'Allowed international shipping services')->tab('International Shipping')->renderAs(frm_checkboxlist)->validation();
			$host_obj->add_field('intl_container', 'Container shape', 'left')->tab('International Shipping')->renderAs(frm_radio);
		}
		
		public function get_domestic_allowed_services_option_state($value = 1)
		{
			return is_array($this->host_obj->domestic_allowed_services) && in_array($value, $this->host_obj->domestic_allowed_services);
		}
		
		public function get_domestic_allowed_services_options($current_key_value = -1)
		{
			$options = array();
			foreach ($this->domestic_service_list as $id=>$option)
				$options[$id] = $option[0];
			
			if ($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}
		
		public function get_package_size_options($current_key_value = -1)
		{
			$options = array(
				'REGULAR'=>array('Regular'=>'Package length plus girth is 84 inches or less'),
				'LARGE'=>array('Large'=>'Length plus girth measure more than 84 inches but not more than 108 inches')
			);
			
			if ($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}
		
		public function get_intl_container_options($current_key_value = -1)
		{
			$options = array(
				'RECTANGULAR'=>array('Rectangular'=>'Package is rectangular in shape'),
				'NONRECTANGULAR'=>array('Nonrectangular'=>'Package is not rectangular in shape')
			);
			
			if ($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}
		
		public function get_international_allowed_services_option_state($value = 1)
		{
			return is_array($this->host_obj->international_allowed_services) && in_array($value, $this->host_obj->international_allowed_services);
		}
		
		public function get_international_allowed_services_options($current_key_value = -1)
		{
			$options = array(
				1=>'Express Mail International',
				2=> 'Priority Mail International',
				4=>'Global Express Guaranteed (Document and Non-document)',
				5=>'Global Express Guaranteed Document used',
				6=>'Global Express Guaranteed Non-Document Rectangular shape',
				7=>'Global Express Guaranteed Non-Document Non-Rectangular',
				9=>'Priority Mail Flat Rate Box',
				11=>'Priority Mail Large Flat Rate Box',
				14=>'First Class Mail International Flats',
				15=>'First Class Mail International Parcels',
				16=>'Priority Mail Small Flat Rate Box',
				18=>'Priority Mail International Gift Card Flat Rate Envelope',
				19=>'Priority Mail International Window Flat Rate Envelope',
				20=>'Priority Mail International Small Flat Rate Envelope',
				21=>'First-Class Mail International Postcard',
				22=>'Priority Mail International Legal Flat Rate Envelope',
				23=>'Priority Mail International Padded Flat Rate Envelope',
				24=>'Priority Mail International DVD Flat Rate priced box',
				25=>'Priority Mail International Large Video Flat Rate priced box',
				26=>'Priority Mail International Flat Rate Boxes',
				27=>'Priority Mail International Padded Flat Rate Envelope'
			);
			
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
		 *   array('name'=>'First class', 'id'=>'first_class'),
		 *   array('name'=>'Priority', 'id'=>'priority'),
		 * )
		 * The options identifiers must match the identifiers returned by the get_quote() method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @return array
		 */
		public function list_enabled_options($host_obj)
		{
			$result = array();
			
			/*
			 * Domestic shipping options
			 */
			
			$domestic_options = $this->get_domestic_allowed_services_options();
			foreach ($domestic_options as $option_id=>$option_name)
			{
				if ($this->get_domestic_allowed_services_option_state($option_id))
					$result[] = array('id'=>$option_id, 'name'=>$option_name.' (domestic)');
			}
			
			/*
			 * International shippiong options
			 */
			
			$intl_options = $this->get_international_allowed_services_options();
			foreach ($intl_options as $option_id=>$option_name)
			{
				if ($this->get_international_allowed_services_option_state($option_id))
					$result[] = array('id'=>$option_id, 'name'=>$option_name.' (intl)');
			}
			
			return $result;
		}

		public function validate_config_on_save($host_obj)
		{
		}
		
 		public function get_quote($parameters) 
		{
			extract($parameters);

			$shipping_params = Shop_ShippingParams::get();
			$currency = Shop_CurrencySettings::get();
			
			$country = Shop_Country::create()->find($country_id);
			if (!$country)
				return null;
			
			if ($shipping_params->weight_unit == 'KGS')
				$total_weight = Core_Number::kg_to_lb($total_weight);
				
			$pounds = floor($total_weight);
			$ounces = round(($total_weight - $pounds)*16, 2);

			$use_domestic = Shop_CountryLookup::get_usps_domestic($country->code);
			if ($country->code == 'US' || $use_domestic)
			{
				/*
				 * Process domestic shipping
				 */
				
				$zip = substr($zip, 0, 5);
				
				$request_doc = $this->format_xml_template('domestic.xml', array(
					'user_id'=>$host_obj->api_user_id,
					'origination_zip'=>$shipping_params->zip_code,
					'destination_zip'=>$zip,
					'pounds'=>$pounds,
					'ounces'=>$ounces,
					'size'=>$host_obj->package_size,
					'machinable'=>$host_obj->machinable,
					'width'=>$host_obj->package_width,
					'length'=>$host_obj->package_length,
					'height'=>$host_obj->package_height,
					'girth'=>$host_obj->package_girth
				));

				if (!$host_obj->use_test_server)
					$service_url = "http://production.shippingapis.com/ShippingAPI.dll?API=RateV4&XML=".urlencode($request_doc);
				else
					$service_url = "http://stg-production.shippingapis.com/ShippingAPI.dll?API=RateV4&XML=".urlencode($request_doc);
			} else
			{
				/*
				 * Process international shipping
				 */
				
				$usps_country_name = Shop_CountryLookup::get_usps_name($country->code);
				if (!$usps_country_name)
					return null;
				
				$total_volume = 0;
				foreach($cart_items as $cart_item) {
					$total_volume += $cart_item->total_volume(false); //excluded free shipping items
				}
				$dimension = $total_volume / 3;

				$request_doc = $this->format_xml_template('international.xml', array(
					'user_id'=>$host_obj->api_user_id,
					'pounds'=>$pounds,
					'ounces'=>$ounces,
					'machinable'=>$host_obj->machinable,
					'container'=>$host_obj->intl_container,
					'total_price' => $total_price,
					'dimension' => $dimension,
					'country'=>mb_strtoupper($usps_country_name)
				));

				if (!$host_obj->use_test_server)
					$service_url = "http://production.shippingapis.com/ShippingAPI.dll?API=IntlRateV2&XML=".urlencode($request_doc);
				else
					$service_url = "http://stg-production.shippingapis.com/ShippingAPI.dll?API=IntlRateV2&XML=".urlencode($request_doc);
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $service_url);
//			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 130);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			$response = curl_exec($ch);
			if (curl_errno($ch))
				throw new Phpr_SystemException('An error occurred communicating with the USPS server.');
			else
				curl_close($ch);

			$result = array();

			$currency_converter = Shop_CurrencyConverter::create();

			if ($country->code == 'US'  || $use_domestic)
			{
				/*
				 * Process domestic shipping response
				 */
				
				$allowed_services = is_array($this->host_obj->domestic_allowed_services) ? $this->host_obj->domestic_allowed_services : array();
				$allowed_services_classes = array();
				$allowed_service_ids = array();
				foreach ($allowed_services as $allowed_service_id)
				{
					if (array_key_exists($allowed_service_id, $this->domestic_service_list))
					{
						//some services only return commercial rates, only include them if commercial rates are used
						if($host_obj->commercial_rates || (!$host_obj->commercial_rates && !$this->domestic_service_list[$allowed_service_id][2]))
						{
							$class_id = $allowed_services_classes[] = $this->domestic_service_list[$allowed_service_id][1];
							$allowed_service_ids[$class_id] = $allowed_service_id;
						}
					}
				}

				$doc = new DOMDocument('1.0');
				$doc->loadXML($response);

				$xPath = new DOMXPath($doc);

				$is_error = $xPath->query('//Error');
				if ($is_error->length)
				{
					$error_text = $xPath->query('Description', $is_error->item(0))->item(0)->nodeValue;
					throw new Phpr_SystemException('Error requesting USPS rates. '.$error_text);
				}

				$is_error = $xPath->query('//RateV4Response/Package/Error');
				if ($is_error->length)
				{
					$error_text = $xPath->query('Description', $is_error->item(0))->item(0)->nodeValue;
					throw new Phpr_SystemException('Error requesting USPS rates. '.$error_text);
				}

				$postages = $xPath->query('//RateV4Response/Package/Postage');
				
				$loaded_ids = array();
				foreach ($postages as $postage)
				{
					$class_id = $postage->getAttribute('CLASSID');
					if (!in_array($class_id, $allowed_services_classes))
						continue;

					if (in_array($class_id, $loaded_ids))
						continue;

					$loaded_ids[] = $class_id;

					$option_name = $xPath->query('MailService', $postage)->item(0)->nodeValue;
					
					if ($class_id == 0)
						$option_name = 'First class';

					if ($option_name == 'First class' && ($pounds > 0 || $ounces > 13))
						continue;
					
					$option_name = str_replace('&lt;sup&gt;&#8482;&lt;/sup&gt;', '', $option_name);
					$option_name = str_replace('&lt;sup&gt;&#174;&lt;/sup&gt;', '', $option_name);
					$option_name = str_replace('&amp;lt;sup&amp;gt;&amp;#8482;&amp;lt;/sup&amp;gt;', '', $option_name);
					$option_name = str_replace('&lt;sup&gt;&amp;reg;&lt;/sup&gt;', '', $option_name);
					$option_name = str_replace('&lt;sup&gt;&amp;trade;&lt;/sup&gt;', '', $option_name);

					if(!$host_obj->commercial_rates)
						$total = $xPath->query('Rate', $postage)->item(0)->nodeValue;
					else
					{
						$commercial_rate = $xPath->query('CommercialRate', $postage)->item(0);
						if($commercial_rate)
							$total = $commercial_rate->nodeValue;
						else
							$total = $xPath->query('Rate', $postage)->item(0)->nodeValue;
					}

					$total = $currency_converter->convert_from($total, 'USD');
					$result[$option_name] = array('id'=>$allowed_service_ids[$class_id], 'quote'=>$total);
				}

			} else 
			{
				/*
				 * Process international shipping response
				 */
				
				$allowed_services_classes = is_array($this->host_obj->international_allowed_services) ? $this->host_obj->international_allowed_services : array();

				$doc = new DOMDocument('1.0');
				$doc->loadXML($response);
				$xPath = new DOMXPath($doc);

				$is_error = $xPath->query('//Error');
				if ($is_error->length)
				{
					$error_text = $xPath->query('Description', $is_error->item(0))->item(0)->nodeValue;
					throw new Phpr_SystemException('Error requesting USPS rates. '.$error_text);
				}

				$postages = $xPath->query('//IntlRateV2Response/Package/Service');
				foreach ($postages as $postage)
				{
					if ( $xPath->query('SvcDescription', $postage)->length == 0 ) {
						continue;
					}
					
					$class_id = $postage->getAttribute('ID');
					if (!in_array($class_id, $allowed_services_classes))
						continue;

					$option_name = $xPath->query('SvcDescription', $postage)->item(0)->nodeValue;
					$option_name = str_replace('&amp;lt;sup&amp;gt;&amp;#8482;&amp;lt;/sup&amp;gt;', '', $option_name);
					$option_name = str_replace('&amp;lt;sup&amp;gt;&amp;#174;&amp;lt;/sup&amp;gt;', '', $option_name);
					$option_name = str_replace('&lt;sup&gt;&#174;&lt;/sup&gt;', '', $option_name);
					
					$option_name = str_replace('&lt;sup&gt;&#8482;&lt;/sup&gt;', '', $option_name);
					$option_name = str_replace('&lt;sup&gt;&amp;reg;&lt;/sup&gt;', '', $option_name);
					$option_name = str_replace('&lt;sup&gt;&amp;trade;&lt;/sup&gt;**', '', $option_name);
					$option_name = str_replace('&lt;sup&gt;&amp;trade;&lt;/sup&gt;', '', $option_name);

					if($host_obj->commercial_rates)
						$total = $xPath->query('CommercialPostage', $postage)->item(0)->nodeValue;
					
					if(!$host_obj->commercial_rates || !strlen($total))
						$total = $xPath->query('Postage', $postage)->item(0)->nodeValue;
					
					if ($host_obj->intl_display_transit_time)
					{
						$time = $xPath->query('SvcCommitments', $postage)->item(0)->nodeValue;
						$option_name .= ' ('.$time.')';
					}

					$total = $currency_converter->convert_from($total, 'USD');
					$result[$option_name] = array('id'=>$class_id, 'quote'=>$total);
				}
			}

			return $result;
		}
		
		/*
		 * Shipping labels
		 */
		
		/**
		 * This method should return TRUE if the shipping module supports label printing.
		 * The shipping module must implement the generate_shipping_label() method if this method returns true.
		 */
		public function supports_shipping_labels()
		{
			return true;
		}
		
		protected function post_label_request($api, $document_name, $parameters)
		{
			if(!$this->host_obj->use_test_server)
				$url = 'https://secure.shippingapis.com/ShippingAPI.dll?API='.$api;
			else $url = 'https://stg-secure.shippingapis.com/ShippingAPI.dll?API='.$api;
			
			$request_doc = $this->format_xml_template($document_name, $parameters, false);

			$service_url = $url."&XML=".urlencode($request_doc);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $service_url);
//			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 130);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

			$response = curl_exec($ch);
			if (curl_errno($ch))
				throw new Phpr_SystemException('Error connecting to the USPS server: '.curl_error($ch));
			else
				curl_close($ch);

			if (!strlen($response))
				throw new Phpr_SystemException('Error connecting to the USPS server.');

			try
			{
				$doc = new DOMDocument('1.0');
				$doc->loadXML($response);
			} catch (exception $ex)
			{
				throw new Phpr_SystemException('Invalid server response.');
			}
			
			$xPath = new DOMXPath($doc);
				
			$is_error = $xPath->query('//Error');
			if ($is_error->length)
			{
				$error_text = $xPath->query('Description', $is_error->item(0))->item(0)->nodeValue;
				throw new Phpr_SystemException('USPS server returned error. '.$error_text);
			}
			return $doc;
		}
		
		protected function split_address($address, &$line1, &$line2)
		{
			$address_1 = null;
			$address_2 = null;
			$address = str_replace("\r\n", "\n", trim($address));
			$address = explode("\n", $address);
			$cnt = count($address);

			if ($cnt > 1)
			{
				$line1 = $address[0];
				$line2 = $address[1];
				for ($i = 2; $i < $cnt; $i++)
					$line2 .= ' '.$address[$i];
			} else
				$line2 = $address[0];
		}
		
		protected function get_mail_type($shipping_option)
		{
			$shipping_option = strtolower($shipping_option);
			if (strpos($shipping_option, 'flat') !== false)
				return 'FLAT';

			if (strpos($shipping_option, 'letter') !== false)
				return 'LETTER';
			
			return 'PARCEL';
		}
		
		protected function get_domestic_service_type($shipping_option)
		{
			$shipping_option = strtolower($shipping_option);
			if (strpos($shipping_option, 'priority') !== false)
				return 'Priority';

			if (strpos($shipping_option, 'first') !== false)
				return 'First Class';

			if (strpos($shipping_option, 'media') !== false)
				return 'Media Mail';

			if (strpos($shipping_option, 'library') !== false)
				return 'Library Mail';

			return 'Parcel Post';
		}
		
		protected function get_shipping_option_type($order)
		{
			if (strpos(strtolower($order->shipping_sub_option), 'first') !== false)
				return 'first';
			
			if (strpos(strtolower($order->shipping_sub_option), 'express') !== false)
				return 'express';

			return 'priority';
		}
		
		/**
		 * Sends request to the server and returns the shipping label data.
		 * The method should also set the order shipping tracking number.
		 * @param $host_obj ActiveRecord object containing configuration fields values.
		 * @param Shop_Order $order The order to generate the label form.
		 * @param array $parameters Optional list of the shipping method specific parameters.
		 * @return array Returns an array of Shop_ShippingLabel objects representing the shipping labels.
		 */
		public function generate_shipping_labels($host_obj, $order, $parameters = array())
		{
			/*
			 * Validate parameters
			 */

			$total_weight = array_key_exists('label_weight', $parameters) ? trim($parameters['label_weight']) : null;
			if (strlen($total_weight))
			{
				 if (!Core_Number::is_valid($total_weight))
					$host_obj->validation->setError('Invalid weight value', 'label_weight', true);

				$total_weight = round($total_weight, 2);
			} else
				$total_weight = $order->get_total_weight();

			$postage = array_key_exists('label_postage', $parameters) ? trim($parameters['label_postage']) : null;
			if (strlen($postage))
			{
				 if (!Core_Number::is_valid($postage))
					$host_obj->validation->setError('Invalid postage value', 'label_postage', true);

				$postage = round($postage, 2);
			}

			$insured_amount = array_key_exists('label_insured_amount', $parameters) ? trim($parameters['label_insured_amount']) : null;
			if (strlen($insured_amount))
			{
				 if (!Core_Number::is_valid($insured_amount))
					$host_obj->validation->setError('Invalid insured amount value', 'label_insured_amount', true);

				$insured_amount = round($insured_amount, 2);
			}

			$label_date = array_key_exists('label_date', $parameters) ? trim($parameters['label_date']) : null;
			if (strlen($label_date))
			{
				if (!($label_date = Phpr_DateTime::parse($label_date, '%x')))
					$host_obj->validation->setError('Invalid label date', 'label_date', true);
					
				$label_date = $label_date->format('%m/%d/%Y');
			}

			$image_type = array_key_exists('label_image_type', $parameters) ? trim($parameters['label_image_type']) : 'TIF';
			$image_layout = array_key_exists('label_image_layout', $parameters) ? trim($parameters['label_image_layout']) : 'ONEPERFILE';
			$container_type = array_key_exists('label_container_type', $parameters) ? trim($parameters['label_container_type']) : 'RECTANGULAR';
			$comments = array_key_exists('label_comments', $parameters) ? trim($parameters['label_comments']) : '';
			$insured = array_key_exists('label_insured', $parameters) ? trim($parameters['label_insured']) : false;
			$insured = $insured ? 'Y' : 'N';
			
			$is_machinable = array_key_exists('label_is_machinable', $parameters) ? trim($parameters['label_is_machinable']) : $host_obj->machinable;
			$is_machinable = $is_machinable ? 'true' : 'false';

			$separate_receipt = array_key_exists('separate_receipt', $parameters) ? trim($parameters['separate_receipt']) : false;
			
			/*
			 * Prepare the request data
			 */
			
			$shipping_params = Shop_ShippingParams::get();
			$company_info = Shop_CompanyInformation::get();
			$use_domestic = Shop_CountryLookup::get_usps_domestic($order->shipping_country->code);
			
			$from_state = null;
			if ($shipping_params->state)
				$from_state = $shipping_params->state->code;
				
			$to_state = null;
			if ($order->shipping_state)
				$to_state = $order->shipping_state->code;
			if($use_domestic)
				$to_state = $order->shipping_country->code;

			$address_1 = null;
			$address_2 = null;
			$this->split_address($order->shipping_street_addr, $address_1, $address_2);

			$from_address_1 = null;
			$from_address_2 = null;
			$this->split_address($shipping_params->street_addr, $from_address_1, $from_address_2);
				
			$zip_code = $order->shipping_zip;
			$zip_code = str_replace(' ', '', $zip_code);
			if (!preg_match('/^[0-9]+$/', $zip_code))
				$zip_code = null;
				
			if ($shipping_params->weight_unit == 'KGS')
				$total_weight = Core_Number::kg_to_lb($total_weight);
				
			$total_pounds = floor($total_weight);
			$total_ounces = round(($total_weight - $total_pounds)*16, 2);
				
			$weight_in_ounces = round($total_weight*16);
			
			$request_params = array(
				'order'=>$order,
				'user_id'=>$host_obj->api_user_id,
				'shipping_params'=>$shipping_params,
				'company_info'=>$company_info,
				'from_state'=>$from_state,
				'address_1'=>$address_1,
				'address_2'=>$address_2,
				'to_state'=>$to_state,
				'weight_in_ounces'=>$weight_in_ounces,
				'zip_code'=>$zip_code,
				'total_pounds'=>$total_pounds,
				'total_ounces'=>$total_ounces,
				'from_address_1'=>$from_address_1,
				'from_address_2'=>$from_address_2,
				'mail_type'=>$this->get_mail_type($order->shipping_sub_option),
				'service_type'=>$this->get_domestic_service_type($order->shipping_sub_option),
				'image_type'=>$image_type,
				'image_layout'=>$image_layout,
				'label_date'=>$label_date,
				'machinable'=>$is_machinable,
				'container_type'=>$container_type,
				'comments'=>$comments,
				'insured'=>$insured,
				'insured_amount'=>$insured_amount,
				'postage'=>$postage,
				'separate_receipt' => $separate_receipt
			);
			
			/*
			 * Post domestic label request
			 */
			if ($order->shipping_country->code_iso_numeric == 840 || $use_domestic)
			{
				$doc = $this->post_label_request('DeliveryConfirmationV3', 'delivery_confirmation.xml', $request_params);
					
				$response_fields = Core_Xml::to_plain_array($doc, true);
				$tracking_num = $response_fields['DeliveryConfirmationNumber'];
				Shop_OrderTrackingCode::set_code($order, $host_obj, $tracking_num);

				$image_data = base64_decode($response_fields['DeliveryConfirmationLabel']);
				$labels[] = new Shop_ShippingLabel($image_data, strtolower($image_type), $order, $host_obj);
				if($separate_receipt)
				{
					$receipt_data = base64_decode($response_fields['DeliveryConfirmationReceipt']);
					$labels[] = new Shop_ShippingLabel($receipt_data, strtolower($image_type), $order, $host_obj, 'Receipt');
				}
				return $labels;
			}

			/*
			 * Post international label request
			 */
			
			$type = $this->get_shipping_option_type($order);
			
			if ($type == 'first')
				$doc = $this->post_label_request('FirstClassMailIntl', 'firstclassintl_label.xml', $request_params);
			elseif ($type == 'express')
				$doc = $this->post_label_request('ExpressMailIntl', 'expressintl_label.xml', $request_params);
			else
				$doc = $this->post_label_request('PriorityMailIntl', 'priorityintl_label.xml', $request_params);
			
			$response_fields = Core_Xml::to_plain_array($doc, true);
			$tracking_num = $response_fields['BarcodeNumber'];
			Shop_OrderTrackingCode::set_code($order, $host_obj, $tracking_num);

			$result = array();
			$image_fields = array('LabelImage', 'Page2Image', 'Page3Image', 'Page4Image', 'Page5Image', 'Page6Image');
			foreach ($image_fields as $image_field)
			{
				if (isset($response_fields[$image_field]) && strlen($response_fields[$image_field]))
				{
					$image_data = base64_decode($response_fields[$image_field]);
					$result[] = new Shop_ShippingLabel($image_data, strtolower($image_type), $order, $host_obj);
				}
			}

			return $result;
		}
		
		/**
		 * Initializes shipping label parameters for a new order.
		 * @param $host_obj ActiveRecord object containing configuration fields values.
		 * @param Shop_Order $order The order object.
		 */
		public function init_order_label_parameters($host_obj, $order)
		{
			/*
			 * Load the machinable parameter from the shipping method configuration
			 */

			$host_obj->label_is_machinable = $host_obj->machinable;
			$host_obj->label_weight = $order->get_total_weight();
			$host_obj->label_postage = $order->shipping_quote;
			
			/*
			 * Load other parameters from the latest processed order
			 */
			
			$params = Shop_OrderShippingLabelParams::get_recent_order_params($host_obj);
			if ($params)
			{
				$host_obj->label_container_type = $params->get_parameter('label_container_type');
				$host_obj->label_image_type = $params->get_parameter('label_image_type');
				$host_obj->label_image_layout = $params->get_parameter('label_image_layout');
			}
		}
		
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
			$domestic = $order->shipping_country->code_iso_numeric == 840;
			$intl_type = $this->get_shipping_option_type($order);

			$host_obj->add_field('label_date', 'Label date')->renderAs(frm_date)->tab('Label')->comment('Date the mail will enter the mail stream. No more than 3 days in the future. Leave the field empty to use the current date.', 'above');
			
			$host_obj->add_field('label_image_type', 'Image type')->renderAs(frm_dropdown)->tab('Label');
			if (!$domestic)
			{
				$host_obj->add_field('label_image_layout', 'Image layout')->renderAs(frm_dropdown)->tab('Label');
			}
			
			if (!$domestic)
			{
				$field = $host_obj->add_field('label_comments', 'Comments')->renderAs(frm_text)->tab('Label')->comment('Ignored when Container specified is a flat rate envelope. Maximum length is 76 symbols.', 'above');
				$columnDefinition = $field->getColDefinition();
				$columnInfo = $columnDefinition->getColumnInfo();
				$columnInfo->length = 76;
			}
			
			if ($intl_type == 'first')
				$host_obj->add_field('label_is_machinable', 'Package is machinable')->renderAs(frm_checkbox)->tab('Package');

			$shipping_params = Shop_ShippingParams::get();
			$weight = $order->get_total_weight();
			if ($shipping_params->weight_unit == 'KGS')
				$weight_str = $weight.' kilogram(s)';
			else
				$weight_str = $weight.' pound(s)';

			$host_obj->add_field('label_weight', 'Weight')->renderAs(frm_text)->tab('Package')->comment('Use this field to override the package weight. The calculated package weight for this order is <strong>'.$weight_str.'</strong>.', 'above', true);
			
			if (!strlen($host_obj->label_weight))
				$host_obj->label_weight = $weight;

			if (!$domestic)
			{
				$host_obj->add_field('label_postage', 'Postage')->renderAs(frm_text)->tab('Package')->comment('Use this field for entering a postage amount, if known. If the field is empty, the postage will be automatically calculated. The calculated shipping cost for this order is <strong>'.$order->format_currency($order->shipping_quote).'</strong>.', 'above', true);
				
				$host_obj->add_field('label_container_type', 'Container type')->renderAs(frm_dropdown)->tab('Package');
			}
			
			if (!$domestic && ($intl_type == 'priority' || $intl_type == 'express'))
			{
				if ($intl_type == 'priority')
					$host_obj->add_field('label_insured', 'Insured')->renderAs(frm_checkbox)->tab('Insurance');

				$host_obj->add_field('label_insured_amount', 'Insured amount')->renderAs(frm_text)->tab('Insurance')->
					comment('Use this tag for entering an insurance amount, if known. The value is ignored when container type 
					specified is a flat rate envelope or small flat rate box variation.', 'above');
			}

			if($domestic)
				$host_obj->add_field('separate_receipt', 'Receipt in separate file')->renderAs(frm_checkbox)->tab('Label');
		}
		
		public function get_label_image_type_options($current_key_value = -1)
		{
			return array(
				'PDF'=>'PDF document',
				'TIF'=>'TIFF image'
			);
		}

		public function get_label_image_layout_options($current_key_value = -1)
		{
			return array(
				'ONEPERFILE'=>'One label per file',
				'ALLINONEFILE'=>'All labels in one file'
			);
		}
		
		public function get_label_container_type_options($current_key_value = -1, $host_obj)
		{
			$intl_type = $this->get_shipping_option_type($host_obj->order);
			if ($intl_type == 'express')
			{
				$result = array(
					'VARIABLE'=>'Variable',
					'FLATRATEENV'=>'Flat rate envelope',
					'RECTANGULAR'=>'Rectangular',
					'NONRECTANGULAR'=>'Non rectangular'
				);
			} elseif ($intl_type == 'priority')
			{
				$result = array(
					'VARIABLE'=>'Variable',
					'RECTANGULAR'=>'Rectangular',
					'NONRECTANGULAR'=>'Non rectangular',
					'LGFLATRATEBOX'=>'Large flat rate box',
					'MDFLATRATEBOX'=>'Medium flat rate box',
					'SMFLATRATEBOX'=>'Small flat rate box',
					'FLATRATEBOX'=>'Flat rate box',
					'LGVIDEOBOX'=>'Large video box',
					'DVDBOX'=>'DVD box',
					'FLATRATEENV'=>'Flat rate envelope',
					'LEGALFLATRATEENV'=>'Legal flat rate envelope',
					'PADDEDFLATRATEENV'=>'Padded flat rate envelope',
					'SMFLATRATEENV'=>'Small flat rate envelope',
					'GIFTCARDFLATRATEENV'=>'Gift card flat rate envelope'
				);
			} else {
				$result = array(
					'RECTANGULAR'=>'Rectangular',
					'NONRECTANGULAR'=>'Non rectangular'
				);
			}
			
			return $result;
		}
	}
?>