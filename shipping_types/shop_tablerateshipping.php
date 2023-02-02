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

		$host_obj->add_field('rates', 'Rates')->tab('Rates')->renderAs(frm_widget, array(
			'class'=>'Db_GridWidget',
			'sortable'=>true,
			'scrollable'=>true,
			'scrollable_viewport_class'=>'height-300',
			'csv_file_name'=>'table-rate-shipping',
			'columns'=>array(
				'country'=>array('title'=>'Country Code', 'type'=>'text', 'autocomplete'=>'remote', 'autocomplete_custom_values'=>true, 'minLength'=>0, 'width'=>100),
				'state'=>array('title'=>'State Code', 'type'=>'text', 'autocomplete'=>'remote', 'autocomplete_custom_values'=>true, 'minLength'=>0, 'width'=>100),
				'zip'=>array('title'=>'ZIP', 'type'=>'text', 'width'=>100),
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

        $parcelTab = 'Shipping Boxes';
        $host_obj->add_field('shipping_boxes', ' Compatible Shipping Boxes','left')->renderAs(frm_checkboxlist)->comment('Select carrier compatible boxes or none to allow all boxes. Shipping boxes can be configured from System -> Settings -> Shipping Settings','above')->tab($parcelTab)->validation();
        $host_obj->add_field('enable_box_packer', 'Enable Box Packer','right')->renderAs(frm_onoffswitcher)->comment('Attempt to calculate parcel dimensions based on items shipping and shipping boxes compatible')->tab($parcelTab);
        $host_obj->add_field('enable_box_count_multiplier', 'Multiply Rate By Box Count','right')->renderAs(frm_onoffswitcher)->comment('Switch this on if the shipping rate should be multiplied when the box packer calculates multiple shipping boxes required')->tab($parcelTab);
        $host_obj->add_field('box_packer_failure_mode', 'Box Packer Failure Mode','right')->renderAs(frm_dropdown)->comment('Select the action to take should the box packer fail to find a packing solution','above')->tab($parcelTab);

        $host_obj->add_form_section(
            'If estimated box dimensions exceed maximum dimension limits set by the carrier, quotes will not be returned. 
            If the box packer is not enabled a box size will be estimated from total item volume, and largest item dimensions.',
            'Carrier Shipping Box Restrictions'
        )->tab($parcelTab);

        $host_obj->add_field('max_box_length', 'Maximum Box Length','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package dimension is more than the specified value. The longest packed box dimension will be evaluated for length')->tab($parcelTab);
        $host_obj->add_field('max_box_width', 'Maximum Box Width','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package dimension is more than the specified value. The second longest packed box dimension will be evaluated for width')->tab($parcelTab);
        $host_obj->add_field('max_box_height', 'Maximum Box Height','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package dimension is more than the specified value. The third longest packed box dimension will be evaluated for height')->tab($parcelTab);
        $host_obj->add_field('max_box_weight', 'Maximum Box Weight','left')->renderAs(frm_text)->comment('The shipping option will be ignored if a calculated package weight is more than the specified value.')->tab($parcelTab);

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

    /**
     * Returns a price
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

		$zip = trim(mb_strtoupper(str_replace(' ', '', $p['zip'])));

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

			if ($row['zip'] != '' && $row['zip'] != '*')
			{
				$row['zip'] = str_replace(' ', '', $row['zip']);

				if ($row['zip'] != $zip)
				{
					if (mb_substr($row['zip'], -1) != '*')
						continue;

					$len = mb_strlen($row['zip'])-1;

					if (mb_substr($zip, 0, $len) != mb_substr($row['zip'], 0, $len))
						continue;
				}
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
                    $boxes = $shippingBoxResult ? $shippingBoxResult : null;
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


}