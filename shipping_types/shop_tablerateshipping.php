<?php

class Shop_TableRateShipping extends Shop_ShippingType
{

    private $host_obj = null;

	/**
	 * Returns information about the shipping type
	 * Must return array with key 'name': array('name'=>'FedEx')
	 * Also the result can contain an optional 'description'
	 * @return array
	 */
	public function get_info()
	{
		return array(
			'name'=>'Table Rate',
			'description'=>'Allows to configure shipping quotes depending on customer location. You do not need any shipping service accounts in order to use this method.'
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

		if ($context == 'preview')
			return;

        $host_obj->add_field('carrier_name','Carrier Name','left')
            ->renderAs(frm_text)
            ->comment('The name of the shipping carrier these rates are for. Optional, used for backend reference')
            ->tab('Carrier Service');

        $host_obj->add_field('requires_receipient_phone_number','Service requires recipient phone number','right')
            ->renderAs(frm_checkbox)
            ->comment('Tick if this service requires a contact phone number for delivery')
            ->tab('Carrier Service');

        $host_obj->add_field('carrier_service_name','Carrier Service Name','left')
            ->renderAs(frm_text)
            ->comment('The name of the shipping service these rates are for. Optional, used for backend reference')
            ->tab('Carrier Service');

        $host_obj->add_field('provides_tracking','Service provides tracking','right')
            ->renderAs(frm_checkbox)
            ->comment('Tick if this service provides end to end tracking')
            ->tab('Carrier Service');

        $host_obj->add_field('provides_proofofdelivery','Service provides proof of delivery','right')
            ->renderAs(frm_checkbox)
            ->comment('Tick if this service provides proof of delivery')
            ->tab('Carrier Service');

        $host_obj->add_field('supported_incoterms', 'Supported incoterms', 'left')
            ->renderAs(frm_checkboxlist)
            ->comment('Codes as defined by International Chamber of Commerce (ICC)')
            ->tab('Carrier Service');


        $parcelTab = 'Shipping Boxes';
        $host_obj->add_field('enable_box_packer', 'Enable Box Packer','left')->renderAs(frm_onoffswitcher)->comment('Attempt to calculate parcel dimensions based on items shipping and shipping boxes compatible')->tab($parcelTab);
        $host_obj->add_field('enable_box_count_multiplier', 'Multiply Rate By Box Count','right')->renderAs(frm_onoffswitcher)->comment('Switch this on if the shipping rate should be multiplied when the box packer calculates multiple shipping boxes required')->tab($parcelTab);
        $host_obj->add_field('box_packer_failure_mode', 'Box Packer Failure Mode','left')->renderAs(frm_dropdown)->comment('Select the action to take should the box packer fail to find a packing solution','above')->tab($parcelTab);
        $host_obj->add_field('shipping_boxes', ' Compatible Shipping Boxes','right')->renderAs(frm_checkboxlist)->comment('Select carrier compatible boxes or none to allow all boxes. Shipping boxes can be configured from System -> Settings -> Shipping Settings','above')->tab($parcelTab)->validation();
        $host_obj->add_field('shipping_weight_mode', 'Shipping Weight Calculation Method', 'left')->renderAs(frm_dropdown)->comment('Select how the weight should be calculated', 'above')->tab($parcelTab);
        $host_obj->add_field('volumetric_divisor', 'Volumetric Divisor', 'left')->renderAs(frm_text)->comment('If volumetric weight calculations are required, enter the `divisor` or `dimensional factor` value ).')->tab($parcelTab);



        $host_obj->add_form_section(
            'If estimated box dimensions exceed maximum dimension limits set by the carrier, quotes will not be returned. 
            If the box packer is not enabled a box size will be estimated from total item volume, and largest item dimensions.',
            'Carrier Shipping Box Restrictions'
        )->tab($parcelTab);

        $host_obj->add_field('max_box_length', 'Maximum Box Length','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package dimension is more than the specified value. The longest packed box dimension will be evaluated for length')->tab($parcelTab);
        $host_obj->add_field('max_box_width', 'Maximum Box Width','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package dimension is more than the specified value. The second longest packed box dimension will be evaluated for width')->tab($parcelTab);
        $host_obj->add_field('max_box_height', 'Maximum Box Height','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package dimension is more than the specified value. The third longest packed box dimension will be evaluated for height')->tab($parcelTab);
        $host_obj->add_field('max_box_length_girth', 'Maximum Box Length + Girth', 'left')->renderAs(frm_text)->comment('The shipping option will be ignored if the calculated package length plus girth is more than the specified value.  (longest packed box dimension) plus girth [(2 x width) + (2 x height)] ')->tab($parcelTab);
        $host_obj->add_field('max_box_weight', 'Maximum Box Weight','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package weight is more than the specified value.')->tab($parcelTab);


        $host_obj->add_form_partial( PATH_APP . '/modules/shop/shipping_types/shop_tablerateshipping/_rates_description.htm' )->tab( 'Rates' );

        $host_obj->add_field('rates', 'Rates')->tab('Rates')->renderAs(frm_widget, array(
            'class'=>'Db_GridWidget',
            'sortable'=>true,
            'scrollable'=>true,
            'scrollable_viewport_class'=>'height-300',
            'csv_file_name'=>'table-rate-shipping',
            'columns'=>array(
                'country'=>array('title'=>'Country Code', 'type'=>'text', 'autocomplete'=>'remote', 'autocomplete_custom_values'=>true, 'minLength'=>0, 'width'=>100),
                'state'=>array('title'=>'State Code', 'type'=>'text', 'autocomplete'=>'remote', 'autocomplete_custom_values'=>true, 'minLength'=>0, 'width'=>100),
                'zip'=>array('title'=>'Postal Code (ZIP)', 'type'=>'text', 'width'=>200),
                'city'=>array('title'=>'City', 'type'=>'text'),

                'min_weight'=>array('title'=>'Min Weight', 'type'=>'text', 'align'=>'right', 'width'=>50),
                'max_weight'=>array('title'=>'Max Weight', 'type'=>'text', 'align'=>'right', 'width'=>50),
                'min_volume'=>array('title'=>'Min Volume', 'type'=>'text', 'align'=>'right', 'width'=>50),
                'max_volume'=>array('title'=>'Max Volume', 'type'=>'text', 'align'=>'right', 'width'=>50),
                'min_subtotal'=>array('title'=>'Min Subtotal', 'type'=>'text', 'align'=>'right', 'width'=>50),
                'max_subtotal'=>array('title'=>'Max Subtotal', 'type'=>'text', 'align'=>'right', 'width'=>50),
                'min_items'=>array('title'=>'Min Items', 'type'=>'text', 'align'=>'right', 'width'=>45),
                'max_items'=>array('title'=>'Max Items', 'type'=>'text', 'align'=>'right', 'width'=>45),
                'price'=>array('title'=>'Rate', 'type'=>'text', 'align'=>'right', 'width'=>50)
            )
        ))->noLabel();
    }

    public function get_supported_incoterms_options()
    {
        return [
            'EXW' => 'EXW',
            'FCA' => 'FCA',
            'CPT' => 'CPT',
            'CIP' => 'CIP',
            'DAT' => 'DAT',
            'DAP' => 'DAP',
            'DDP' => 'DDP',
            'FAS' => 'FAS',
            'FOB' => 'FOB',
            'CFR' => 'CFR',
            'CIF' => 'CIF',
        ];
    }

    public function get_supported_incoterms_option_state($value = 1)
    {
        return is_array($this->host_obj->supported_incoterms) && in_array($value, $this->host_obj->supported_incoterms);
    }

    public function get_shipping_boxes_options($current_key_value = -1)
    {
        $params = Shop_ShippingParams::get();
        $shipping_boxes = $params->shipping_boxes;
        $options = $shipping_boxes ? $shipping_boxes->as_array('name','id') : array();
        if ($current_key_value == -1)
            return $options;

        return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
    }

    public function get_shipping_boxes_option_state($value = 1)
    {
        return is_array($this->host_obj->shipping_boxes) && in_array($value, $this->host_obj->shipping_boxes);
    }


    public function get_box_packer_failure_mode_options($current_key_value = -1) {
        $options = array(
            'return_volume_box' => 'Estimate box size from total item volume',
            'fail' => 'Do not provide shipping quote',
        );

        if($current_key_value == -1)
            return $options;

        return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
    }

    public function get_shipping_weight_mode_options($current_key_value = -1)
    {
        $options = array(
            'actual' => 'Actual Weight (items, packed box)',
            'volumetric' => 'Volumetric Weight',
            'volumetric_if_greater_than_actual' => 'Volumetric Weight if greater than Actual Weight'
        );

        if ($current_key_value == -1) {
            return $options;
        }

        return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
    }

	public function get_grid_autocomplete_values($db_name, $column, $term, $row_data)
	{
		if ($column == 'country')
			return $this->get_country_list($term);

		if ($column == 'state')
		{
			$country_code = isset($row_data['country']) ? $row_data['country'] : null;
			return $this->get_state_list($country_code, $term);
		}
	}

	protected function get_country_list($term)
	{
		$countries = Db_DbHelper::objectArray('select code, name from shop_countries where name like :term', array('term'=>$term.'%'));
		$result = array();
		$result['*'] = '* - Any country';
		foreach ($countries as $country)
			$result[$country->code] = $country->code.' - '.$country->name;

		return $result;
	}

	protected function get_state_list($country_code, $term)
	{
		$result = array('*'=>'* - Any state');

		$states = Db_DbHelper::objectArray('select shop_states.code as state_code, shop_states.name
				from shop_states, shop_countries 
				where shop_states.country_id = shop_countries.id
				and shop_countries.code=:country_code
				and shop_states.name like :term
				order by shop_countries.code, shop_states.name', array(
			'country_code'=>$country_code,
			'term'=>$term.'%'
		));

		foreach ($states as $state)
			$result[$state->state_code] = $state->state_code.' - '.$state->name;

		return $result;
	}

	/**
	 * Validates configuration data before it is saved to database
	 * Use host object field_error method to report about errors in data:
	 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
	 * @param $host_obj ActiveRecord object containing configuration fields values
	 */
	public function validate_config_on_save($host_obj)
	{
		if (!is_array($host_obj->rates) || !count($host_obj->rates))
			$host_obj->field_error('rates', 'Please specify shipping rates.');

		$numeric_fields = array(
			'min_weight'=>'Min Weight',
			'max_weight'=>'Max Weight',
			'min_volume'=>'Min Volume',
			'max_volume'=>'Max Volume',
			'min_subtotal'=>'Min Subtotal',
			'max_subtotal'=>'Max Subtotal',
			'min_items'=>'Min Items',
			'max_items'=>'Max Items',
		);

		/*
		 * Preload countries and states
		 */

		$db_country_codes = Db_DbHelper::objectArray('select * from shop_countries order by code');
		$countries = array();
		foreach ($db_country_codes as $country)
			$countries[$country->code] = $country;

		$country_codes = array_merge(array('*'), array_keys($countries));
		$db_states = Db_DbHelper::objectArray('select * from shop_states order by code');

		$states = array();
		foreach ($db_states as $state)
		{
			if (!array_key_exists($state->country_id, $states))
				$states[$state->country_id] = array('*'=>null);

			$states[$state->country_id][mb_strtoupper($state->code)] = $state;
		}

		foreach ($countries as $country)
		{
			if (!array_key_exists($country->id, $states))
				$states[$country->id] = array('*'=>null);
		}

		/*
		 * Validate table rows
		 */

		$processed_rates = array();
		$input_rates = $host_obj->rates;

		$is_manual_disabled = isset($input_rates['disabled']);
		if ($is_manual_disabled)
			$input_rates = unserialize($input_rates['serialized']);

		$line_number = 0;
		foreach ($input_rates as $row_index=>&$rates)
		{
			$line_number++;
			$empty = true;
			foreach ($rates as $value)
			{
				if (strlen(trim($value)))
				{
					$empty = false;
					break;
				}
			}

			if ($empty)
				continue;

			/*
			 * Validate country
			 */
			$country = $rates['country'] = trim(mb_strtoupper($rates['country']));
			if (!strlen($country))
				$host_obj->field_error('rates', 'Please specify country code. Available codes are: '.implode(', ', $country_codes).'. Line: '.$line_number, $row_index, 'country');

			if (!array_key_exists($country, $countries) && $country != '*')
				$host_obj->field_error('rates', 'Invalid country code. Available codes are: '.implode(', ', $country_codes).'. Line: '.$line_number, $row_index, 'country');

			/*
			 * Validate state
			 */
			if ($country != '*')
			{
				$country_obj = $countries[$country];
				$country_states = $states[$country_obj->id];
				$state_codes = array_keys($country_states);

				$state = $rates['state'] = trim(mb_strtoupper($rates['state']));
				if (!strlen($state))
					$host_obj->field_error('rates', 'Please specify state code. State codes, available for '.$country_obj->name.' are: '.implode(', ', $state_codes).'. Line: '.$line_number, $row_index, 'state');

				if (!in_array($state, $state_codes) && $state != '*')
					$host_obj->field_error('rates', 'Invalid state code. State codes, available for '.$country_obj->name.' are: '.implode(', ', $state_codes).'. Line: '.$line_number, $row_index, 'state');
			} else {
				$state = $rates['state'] = trim(mb_strtoupper($rates['state']));
				if (!strlen($state) || $state != '*')
					$host_obj->field_error('rates', 'Please specify state code as wildcard (*) to indicate "Any state in any country" condition. Line: '.$line_number, $row_index, 'state');
			}

			/*
			 * Process ZIP code
			 */

			$rates['zip'] = trim(mb_strtoupper($rates['zip']));


            /*
             * Validate numeric fields
             */

			$prev_value = null;
			$prev_code = null;
			$index = 0;
			foreach ($numeric_fields as $code=>$name)
			{
				$value = $rates[$code] = trim(mb_strtoupper($rates[$code]));

				$value_specified = strlen($value);
				if ($value_specified)
				{
					if (!Core_Number::is_valid($value))
						$host_obj->field_error('rates', 'Invalid numeric value in column '.$name.'. Line: '.$line_number, $row_index, $code);

					$rates[$code] = (float)$value;
				}

				if ($index % 2)
				{
					if ($value_specified && !strlen($prev_value))
						$host_obj->field_error('rates', 'Please specify both minimum and maximum value. Line: '.$line_number, $row_index, $prev_code);

					if (!$value_specified && strlen($prev_value))
						$host_obj->field_error('rates', 'Please specify both minimum and maximum value. Line: '.$line_number, $row_index, $code);

					if ($prev_value > $value)
						$host_obj->field_error('rates', 'Minimum value must be less than maximum value. Line: '.$line_number, $row_index, $code);
				}

				$prev_value = $rates[$code];
				$prev_code = $code;
				$index++;
			}

			/*
			 * Validate price
			 */

			$price = $rates['price'] = trim(mb_strtoupper($rates['price']));
			if (!strlen($price))
				$host_obj->field_error('rates', 'Please specify shipping rate. Line: '.$line_number, $row_index, 'price');

			if (!Core_Number::is_valid($price))
				$host_obj->field_error('rates', 'Invalid numeric value in column Rate. Line: '.$line_number, $row_index, 'price');

			$processed_rates[] = $rates;
		}

		if (!count($processed_rates))
			$host_obj->field_error('rates', 'Please specify shipping rates.');

		$host_obj->rates = $processed_rates;
	}

	/**
	 * Determines whether a list of countries should be displayed in the
	 * configuration form. For most payment methods the country list should be displayed.
	 * But for the table rate shipping countries are configured using the table content.
	 */
	public function config_countries()
	{
		return true;
	}

    public function getItemRates(Shop_ShippingOption $shippingOption, $items, Shop_AddressInfo $toAddress, Shop_AddressInfo $fromAddress = null, $context = ''){

        $rates = array();
        $itemInfo = array(
            'volume' => 0,
            'weight' => 0,
            'itemValue' => 0,
            'itemCount' => 0,
        );

        foreach($items as $cartItem){
            $itemInfo['volume'] += $cartItem->total_volume(false); //ignores items marked free_shipping
            $itemInfo['weight'] += $cartItem->total_weight(false); //ignores items marked free_shipping
            $itemInfo['itemValue'] += $cartItem->get_total_offer_price();
            $itemInfo['itemCount'] += $cartItem->quantity;
        }

        $itemInfo['weight'] = $this->getEffectiveShippingWeight($shippingOption, $itemInfo['weight'], $itemInfo['volume']);
        $price = $this->findShippingPrice($shippingOption,$toAddress,$itemInfo);

        if($price === null){

            return $rates;
        }

        /*
         * Shipping Box Considerations
         */
        $packed_boxes = null;
        $boxInfos = null;
        
        //
        // Run Box Packer
        //
        if($shippingOption->enable_box_packer && class_exists('Shop_BoxPacker')){
            $boxes = null;
            if($shippingOption->shipping_boxes){
                $shippingBoxes = Shop_ShippingBox::get_boxes();
                $shippingBoxes->where('id in (?)', array($shippingOption->shipping_boxes));
                $shippingBoxResult = $shippingBoxes->find_all();
                $boxes = $shippingBoxResult ? $shippingBoxResult : null;
            }

            $packed_boxes = Shop_PackedBox::calculate_item_packed_boxes($items, array(), $boxes);

            if(!$packed_boxes && $shippingOption->box_packer_failure_mode == 'fail'){
                //cannot return rate
                return $rates;
            }

            if($packed_boxes){
                //multiply rate
                $boxInfo = array();
                if($shippingOption->enable_box_count_multiplier){
                    $price = $price * count($packed_boxes);
                }
                // add dimensions for eval
                foreach($packed_boxes as $packed_box){
                    $dimensions = array(
                        $packed_box->get_length(),
                        $packed_box->get_width(),
                        $packed_box->get_depth(),
                    );
                    rsort($dimensions, SORT_NUMERIC);

                    $boxInfo = array(
                        'weight' => $packed_box->get_weight(),
                        'length' => $dimensions[0],
                        'width' => $dimensions[1],
                        'height' => $dimensions[2],
                    );
                    $boxInfos[] = $boxInfo;
                }
            }

        }

        if($this->hasShippingBoxRestrictions($shippingOption)) {
            if (!$boxInfos) {
                // Evaluate box dimensions based on item dimensions
                $dimensions = $this->get_box_dimensions_from_items($items);
                if ($dimensions) {
                    $boxInfos[] = array(
                        'weight' => $itemInfo['weight'],
                        'length' => $dimensions[0],
                        'width' => $dimensions[1],
                        'height' => $dimensions[2],
                    );
                }
            }

            //
            // Enforce Carrier Shipping Box Restrictions (Length, Width, Height, Weight)
            //
            if ($boxInfos) {
                foreach ($boxInfos as $boxInfo) {
                    if (!$this->passShippingBoxRestrictions($shippingOption, $boxInfo)) {
                        return $rates;
                    }
                }
            }
        }


        $rates[] = $this->buildShippingRate($shippingOption, $price);
        return $rates;

    }

    public function getPackedBoxRates(Shop_ShippingOption $shippingOption, Shop_AddressInfo $toAddress, array $packedBoxes, Shop_AddressInfo $fromAddress = null, $context = ''){
        $rates = array();
        $totalPrice = null;
        foreach($packedBoxes as $packedBox){

            $items = $packedBox->get_items();
            $weight = $this->getEffectiveShippingWeight($shippingOption, $packedBox->get_weight(), $packedBox->get_volume());
            $boxInfo = array(
                'weight' => $weight,
                'width' => $packedBox->get_width(),
                'height' => $packedBox->get_depth(),
                'length' => $packedBox->get_length(),
                'volume' => $packedBox->get_volume(),
                'itemValue' => 0,
                'itemCount' => $packedBox->get_items_count(),
            );
            foreach($items as $item){
                $boxInfo['itemValue'] += $item->get_total_offer_price();
            }

            if(!$this->passShippingBoxRestrictions($shippingOption, $boxInfo)){
                return $rates;
            }

            $price = $this->findShippingPrice($shippingOption,$toAddress,$boxInfo);
            if($price === null){
                return $rates;
            }
            $totalPrice+=$price;
        }

        if($totalPrice !== null){
            $rates[] = $this->buildShippingRate($shippingOption, $totalPrice);
        }
        return $rates;


    }

    protected function findShippingPrice(Shop_ShippingOption $shippingOption, Shop_AddressInfo $addressInfo, $info=array()){


        $expectedInfo = array(
          'volume' => 0,
          'weight' => 0,
          'itemValue' => 0,
          'itemCount' => 0,
        );
        $info = array_merge($expectedInfo, $info);

        $country = $addressInfo->get_relation_obj('country');
        if (!$country){
            return null;
        }


        $country_code = $country->code;
        $state = $addressInfo->get_relation_obj('state');
        $state_code = $state ? mb_strtoupper($state->code) : '*';
        $zip = $addressInfo->get('zip','*');
        $city = str_replace('-', '', str_replace(' ', '', trim(mb_strtoupper($addressInfo->get('city')))));
        if (!strlen($city))
            $city = '*';

        /*
         * Find shipping rate
         */

        $rate = null;

        foreach ($shippingOption->rates as $row)
        {
            if ($row['country'] != $country_code && $row['country'] != '*'){
                continue;
            }

            if (mb_strtoupper($row['state']) != $state_code && $row['state'] != '*'){
                continue;
            }

            if(!$this->is_zip_code_match($zip, $row['zip'])){
                continue;
            }

            $row_city = isset($row['city']) && strlen($row['city']) ? str_replace('-', '', str_replace(' ', '', mb_strtoupper($row['city']))) : '*';
            if ($row_city != $city && $row_city != '*'){
                continue;
            }

            if (strlen($row['min_weight']) && strlen($row['max_weight']))
            {
                if (!(Core_Number::compare_float($row['min_weight'], $info['weight']) <= 0
                    && Core_Number::compare_float($row['max_weight'], $info['weight']) >= 0)){
                    continue;
                }
            }

            if (strlen($row['min_volume']) && strlen($row['max_volume']))
            {
                if (!(Core_Number::compare_float($row['min_volume'], $info['volume']) <= 0 &&
                    Core_Number::compare_float($row['max_volume'], $info['volume']) >= 0)){
                    continue;
                }
            }

            if (strlen($row['min_subtotal']) && strlen($row['max_subtotal']))
            {
                if (!(Core_Number::compare_float($row['min_subtotal'], $info['itemValue']) <= 0 &&
                    Core_Number::compare_float($row['max_subtotal'], $info['itemValue']) >= 0)){
                    continue;
                }

            }

            if (strlen($row['min_items']) && strlen($row['max_items']))
            {
                if (!($row['min_items'] <= $info['itemCount'] && $row['max_items'] >= $info['itemCount'])) {
                    continue;
                }
            }

            $rate = $row['price'];
            break;
        }

        return $rate;

    }

    protected function buildShippingRate($shippingOption, $price){
        $serviceInfo = new Shop_ShippingServiceInfo();
        $serviceInfo->serviceName = $shippingOption->carrier_service_name;
        $serviceInfo->carrierName = $shippingOption->carrier_name;
        $serviceInfo->providesTracking = (bool)$shippingOption->provides_tracking;
        $serviceInfo->providesProofOfDelivery = (bool)$shippingOption->provides_proofofdelivery;
        $serviceInfo->requiresRecipientPhoneNumber = (bool)$shippingOption->requires_receipient_phone_number;
        $supportedIncoterms = $this->host_obj->supported_incoterms;
        if (is_array($supportedIncoterms)) {
            $serviceInfo->supportedIncoterms = $supportedIncoterms;
        }

        $rate = new Shop_ShippingRate();
        $rate->setShippingOptionId($shippingOption->id);
        $rate->setShippingProviderClassName('Shop_TableRateShipping');
        $rate->setShippingServiceName($shippingOption->name);
        $rate->setRate($price);
        $rate->setCarrierServiceInfo($serviceInfo);
        return $rate;
    }
    
    protected function hasShippingBoxRestrictions(Shop_ShippingOption $shippingOption){
        if(is_numeric($shippingOption->max_box_weight)){
          return true;
        }
        if(is_numeric($shippingOption->max_box_length)){
            return true;
        }
        if(is_numeric($shippingOption->max_box_width)){
            return true;
        }
        if(is_numeric($shippingOption->max_box_height)){
            return true;
        }
        return false;
    }
    
    protected function passShippingBoxRestrictions(Shop_ShippingOption $shippingOption, $boxInfo){

        if(!$this->hasShippingBoxRestrictions($shippingOption)){
            return true;
        }

        $expectedBoxInfoParams = array(
            'weight' => 0,
            'width' => 0,
            'height' => 0,
            'length' => 0
        );
        $boxInfo = array_merge($expectedBoxInfoParams, $boxInfo);
        $dimensions = [
            $boxInfo['length'],
            $boxInfo['width'],
            $boxInfo['height']
        ];
        rsort($dimensions);
        $length = $dimensions[0]; //longest side
        $width = $dimensions[1]; //second-longest side
        $height = $dimensions[2]; //third-longest side

        if($shippingOption->max_box_weight && is_numeric($shippingOption->max_box_weight)){
            if(Core_Number::compare_float($boxInfo['weight'], $shippingOption->max_box_weight) >= 0){
                //too heavy
                return false;
            }
        }
        if($shippingOption->max_box_length && is_numeric($shippingOption->max_box_length)){
            if(Core_Number::compare_float($length, $shippingOption->max_box_length) >= 0){
                //too long
                return false;
            }
        }
        if($shippingOption->max_box_width && is_numeric($shippingOption->max_box_width)){
            if(Core_Number::compare_float($width, $shippingOption->max_box_width) >= 0){
                //too wide
                return false;
            }
        }
        if($shippingOption->max_box_height && is_numeric($shippingOption->max_box_height)){
            if(Core_Number::compare_float($height, $shippingOption->max_box_height) >= 0){
                //too tall
                return false;
            }
        }
        if ($shippingOption->max_box_length_girth !== null) {
            $girth = ($width * 2) + ($height * 2);
            $lg = $girth + $length;
            if (Core_Number::compare_float($lg, $shippingOption->max_box_length_girth) >= 0) {
                //too big
                return false;
            }
        }

        return true;

    }

    /**
     * Returns the largest length , width , height dimension across all items.
     * @param $items mixed Collection of Shop_ShippableItems
     * @return array Return an array of dimensions from largest to smallest
     */
    protected function get_largest_dimensions_from_items($items){
        $db_collection = (is_object($items) && get_class($items) == 'Db_DataCollection') ? true : false;
        $cart_items = (is_array($items) || $db_collection) ? $items : array($items) ;
        $largest_dimensions = array(
            'length' => 0,
            'width'  => 0,
            'height' => 0,
        );
        foreach($cart_items as $item){
            $length = $item->depth();
            $width = $item->width();
            $height = $item->height();
            if($length > $largest_dimensions['length']){
                $largest_dimensions['length'] = $length;
            }
            if($width > $largest_dimensions['width']){
                $largest_dimensions['width'] = $width;
            }
            if($height > $largest_dimensions['height']){
                $largest_dimensions['height'] = $height;
            }
        }
        $dimensions = array_values($largest_dimensions);
        rsort($dimensions, SORT_NUMERIC);
        return $dimensions;
    }

    /**
     * A crude calculation of box dimensions required to fit item dimensions.
     *    - Smallest carrier compatible shipping box with volume >= than total item volume and with dimensions no smaller than the largest item dimensions.
     *    - When no compatible shipping box found dimensions are determined from total item volume and largest item dimensions.
     *
     * @param $items mixed Collection of Shop_ShippableItems
     * @return array|false Return an array of dimensions from largest to smallest, or false if dimensions could not be determined
     */
    protected function get_box_dimensions_from_items( $items ) {

        $dimensions = array();

        $db_collection = (is_object($items) && get_class($items) == 'Db_DataCollection') ? true : false;
        $items = (is_array($items) || $db_collection) ? $items : array($items) ;

        $total_volume = Shop_BoxPacker::get_items_total_volume( $items );
        $largest_item_dimensions = $this->get_largest_dimensions_from_items($items);
        if ( $total_volume > 0 ) {
            $boxes = Shop_ShippingBox::create()->where('volume >= ?', $total_volume)->order('volume ASC')->find_all();
            if($boxes){
                foreach($boxes as $box){
                    $box_compatible = $this->host_obj->shipping_boxes ? isset($this->host_obj->shipping_boxes[$box->id]) : true;
                    if($box_compatible){
                        $box_dimensions = array(
                            $box->width,
                            $box->length,
                            $box->depth
                        );
                        rsort($box_dimensions, SORT_NUMERIC);
                        foreach($box_dimensions as $key => $dimension){
                            if($largest_item_dimensions[$key] && $dimension < $largest_item_dimensions[$key] ){
                                continue; // box too small
                            }
                            $dimensions = $box_dimensions;
                            break;
                        }
                    }
                }
            }
        }


        if(count($dimensions) !== 3){
            $divided_volume = pow( $total_volume, 1 / 3 );
            $average_dimension_length = ceil( $divided_volume );
            $dimensions = array(
                isset($largest_item_dimensions[0]) ? max($average_dimension_length, $largest_item_dimensions[0]) :  $average_dimension_length,
                isset($largest_item_dimensions[1]) ? max($average_dimension_length, $largest_item_dimensions[1]) :  $average_dimension_length,
                isset($largest_item_dimensions[2]) ? max($average_dimension_length, $largest_item_dimensions[2]) :  $average_dimension_length,
            );
        }

        $valid_dimensions_count = 0;
        foreach($dimensions as $dimension){
            if($dimension && $dimension > 0){
                $valid_dimensions_count++;
            }
        }

        if ( $valid_dimensions_count === 3 ) {
            rsort($dimensions, SORT_NUMERIC);
            return $dimensions;
        }
        return false;
    }


    /**
     * Check to see if the shipping address ZIP matches the
     * rate field expression.
     *
     * Valid Expressions:
     *    - Wild card asterisk at end of string. Eg. `CODE-A*`
     *    - Number range in brackets. Eg. `CODE-[1-3]`
     *    - Comma seperated list. Eg. `CODE-[1-3], CODE-B, CODE-A`
     *
     * @param $addressZip string The shipping address ZIP to match
     * @param $rateZipField string The rate zip code expression to check against
     * @return bool True if address ZIP matches rate expression
     */
    protected function is_zip_code_match($addressZip, $rateZipField){
        if(trim($rateZipField) == '*'){
            return true;
        }
        if(empty($rateZipField)){
            return false;
        }

        $addressZip = trim(mb_strtoupper(str_replace(' ', '', $addressZip)));
        $rateZipField = trim(mb_strtoupper(str_replace(' ', '', $rateZipField)));

        if($addressZip == $rateZipField){
            return true;
        }

        //Check for expressions
        $rateZipArray = $this->get_zip_code_array($rateZipField);
        foreacH($rateZipArray as $zip){
            if(!strpos($zip, '*')){
                if($zip == $addressZip){
                    return true;
                }
            } else {
                $zip = str_replace('*', '.*', $zip);
                if ( preg_match('/'. $zip .'/', $addressZip, $matches) === 1 ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Takes a ZIP code or ZIP code expression from the rate table and converts into
     * an array of ZIP codes.
     *
     * Multiple ZIP codes can be presented in comma delimited format
     * - EG: `CODE-1, CODE-2, CODE-3` will return array containing three postcodes
     *
     * A run of numbered postcodes can be presented with a sequential number range presented in brackets
     * - EG. `CODE-[1-3]` will return array containing three postcodes
     *
     * These methods can be combined
     * - EG. `CODE-[1-3], CODE-[22-29], CODE-128`
     *
     * @param $zipFieldVal string A ZIP field value from the rate table
     * @return array Array of ZIP CODES
     */
    protected function get_zip_code_array( $zipFieldVal ){
        $array = array();
        if($zipFieldVal) {
            $rateZips = explode( ',', $zipFieldVal );
            if ( $rateZips ) {
                foreach ( $rateZips as $zip ) {
                    $zip = trim(mb_strtoupper(str_replace(' ', '', $zip)));
                    if ( preg_match( '/\[(.*?)\]/', $zip, $match ) == 1 ) {
                        if ( isset( $match[1] ) ) {
                            $number_range = $match[1];
                            if ( stristr( $number_range, '-' ) ) {
                                $numbers    = explode( '-', $number_range );
                                $min_number = isset( $numbers[0] ) ? $numbers[0] : false;
                                $max_number = isset( $numbers[1] ) ? $numbers[1] : false;
                                if ( is_numeric( $min_number ) && is_numeric( $max_number ) ) {
                                    $current_number = $min_number;
                                    while ( $current_number <= $max_number ) {
                                        $array[] = str_replace( "[$min_number-$max_number]", $current_number, $zip );
                                        $current_number++;
                                    }
                                }
                            }
                        }
                    } else {
                        $array[] = $zip;
                    }
                }
            }
        }
        return $array;
    }




    /**
     * @deprecated use guoteItems
     * @param $parameters
     * @return float|null Float if quote determine , otherwise NULL
     */
    public function get_quote($parameters)
    {
        $expected_params = array(
            'country_id' => null,
            'state_id' => null,
            'zip' => null,
            'city' => null,
            'host_obj' => null,
            'cart_items' => null,
            'total_volume' => null,
            'total_weight' => null,
            'total_price' => null,
            'total_item_num' => null
        );
        $p = array_merge($expected_params, $parameters);
        //extract($parameters);

        $country = $p['country_id'] ? Shop_Country::find_by_id($p['country_id']) : null;
        if (!$country)
            return null;

        $country_code = $country->code;

        $state = strlen($p['state_id']) ? Shop_CountryState::find_by_id($p['state_id']) : null;
        $state_code = $state ? mb_strtoupper($state->code) : '*';

        $zip = empty($p['zip']) ? '*' : $p['zip'];

        $city = str_replace('-', '', str_replace(' ', '', trim(mb_strtoupper($p['city']))));
        if (!strlen($city))
            $city = '*';

        /*
         * Find shipping rate
         */

        $rate = null;
        foreach ($p['host_obj']->rates as $row)
        {
            if ($row['country'] != $country_code && $row['country'] != '*')
                continue;

            if (mb_strtoupper($row['state']) != $state_code && $row['state'] != '*')
                continue;

            if(!$this->is_zip_code_match($zip, $row['zip'])){
                continue;
            }

            $row_city = isset($row['city']) && strlen($row['city']) ? str_replace('-', '', str_replace(' ', '', mb_strtoupper($row['city']))) : '*';
            if ($row_city != $city && $row_city != '*')
                continue;

            if (strlen($row['min_weight']) && strlen($row['max_weight']))
            {
                if (!(Core_Number::compare_float($row['min_weight'], $p['total_weight']) <= 0
                    && Core_Number::compare_float($row['max_weight'], $p['total_weight']) >= 0))
                    continue;
            }

            if (strlen($row['min_volume']) && strlen($row['max_volume']))
            {
                if (!(Core_Number::compare_float($row['min_volume'], $p['total_volume']) <= 0 &&
                    Core_Number::compare_float($row['max_volume'], $p['total_volume']) >= 0))
                    continue;
            }

            if (strlen($row['min_subtotal']) && strlen($row['max_subtotal']))
            {
                if (!(Core_Number::compare_float($row['min_subtotal'], $p['total_price']) <= 0 &&
                    Core_Number::compare_float($row['max_subtotal'], $p['total_price']) >= 0))
                    continue;
            }

            if (strlen($row['min_items']) && strlen($row['max_items']))
            {
                if (!($row['min_items'] <= $p['total_item_num'] && $row['max_items'] >= $p['total_item_num']))
                    continue;
            }

            $rate = $row['price'];
            break;
        }

        if($rate !== null){

            /*
             * Shipping Box Considerations
             */

            $packed_boxes = null;
            $eval_dimensions = null;
            $max_dimensions = array(
                $p['host_obj']->max_box_length,
                $p['host_obj']->max_box_width,
                $p['host_obj']->max_box_depth
            );
            $carrier_has_dimension_limit = false;
            foreach($max_dimensions as $dimension){
                if($dimension){
                    $carrier_has_dimension_limit = true;
                    break;
                }
            }

            //
            // Run Box Packer
            //
            if($p['host_obj']->enable_box_packer && class_exists('Shop_BoxPacker')){
                $boxes = null;
                if($p['host_obj']->shipping_boxes){
                    $shippingBoxes = Shop_ShippingBox::get_boxes();
                    $shippingBoxes->where('id in (?)', array($p['host_obj']->shipping_boxes));
                    $shippingBoxResult = $shippingBoxes->find_all();
                    $boxes = $shippingBoxResult ?: null;
                }

                $packed_boxes = Shop_PackedBox::calculate_item_packed_boxes($p['cart_items'], array(), $boxes);

                if(!$packed_boxes && $p['host_obj']->box_packer_failure_mode == 'fail'){
                    //cannot return rate
                    return null;
                }

                if($packed_boxes){
                    //multiply rate
                    if($p['host_obj']->enable_box_count_multiplier){
                        $rate = $rate * count($packed_boxes);
                    }
                    // add dimensions for eval
                    foreach($packed_boxes as $packed_box){
                        $dimensions = array(
                            $packed_box->get_length(),
                            $packed_box->get_width(),
                            $packed_box->get_depth(),
                        );
                        rsort($dimensions, SORT_NUMERIC);
                        $eval_dimensions[] = $dimensions;
                    }
                }

            }

            if(!$eval_dimensions){
                // Evaluate box dimensions based on item dimensions
                $dimensions = $this->get_box_dimensions_from_items($p['cart_items']);
                if($dimensions){
                    $eval_dimensions[] = $dimensions;
                }
            }

            //
            // Enforce Carrier Shipping Box Restrictions (Length, Width, Height)
            //
            if($carrier_has_dimension_limit) {
                if (count($eval_dimensions)) {
                    foreach ($eval_dimensions as $dimensions) {
                        $cnt = 0;
                        foreach ($dimensions as $key => $dimension) {
                            if ($max_dimensions[$cnt]) {
                                if (Core_Number::compare_float( $dimension, $max_dimensions[$cnt]) >= 0) {
                                    //Box too big, cannot return rate
                                    return null;
                                }
                            }
                            $cnt++;
                        }
                    }
                } else {
                    // a max dimension limit was set but no box dimension could be determined
                    // rate cannot be returned
                    return null;
                }
            }

            if($p['host_obj']->max_box_weight && count($packed_boxes)){
                foreach($packed_boxes as $packed_box){
                    if(Core_Number::compare_float($packed_box->get_weight(), $p['host_obj']->max_box_weight) >= 0){
                        //too heavy, cannot return rate
                        return null;
                    }
                }
            }

        }

        return $rate;
    }

    private function getEffectiveShippingWeight($shippingOption, $weight, $volume)
    {
        if (in_array($shippingOption->shipping_weight_mode, ['volumetric', 'volumetric_if_greater_than_actual'])) {
            $divisor = $shippingOption->volumetric_divisor;
            if ($weight && $divisor) {
                $volumetricWeight = $volume / $divisor;
                if ($shippingOption->shipping_weight_mode == 'volumetric_if_greater_than_actual') {
                    $weight = max($weight, $volumetricWeight);
                } else {
                    $weight = $volumetricWeight;
                }
            }
        }
        return $weight;
    }


}