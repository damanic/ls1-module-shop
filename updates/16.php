<?
	$countries_and_states = array(
		'US'=>array('United States', array(
			'Alabama'=>'AL',
			'Alaska'=>'AK',
			'American Samoa'=>'AS',
			'Arizona'=>'AZ',
			'Arkansas'=>'AR',
			'California'=>'CA',
			'Colorado'=>'CO',
			'Connecticut'=>'CT',
			'Delaware'=>'DE',
			'Dist. of Columbia'=>'DC',
			'Florida'=>'FL',
			'Georgia'=>'GA',
			'Guam'=>'GU',
			'Hawaii'=>'HI',
			'Idaho'=>'ID',
			'Illinois'=>'IL',
			'Indiana'=>'IN',
			'Iowa'=>'IA',
			'Kansas'=>'KS',
			'Kentucky'=>'KY',
			'Louisiana'=>'LA',
			'Maine'=>'ME',
			'Maryland'=>'MD',
			'Marshall Islands'=>'MH',
			'Massachusetts'=>'MA',
			'Michigan'=>'MI',
			'Micronesia'=>'FM',
			'Minnesota'=>'MN',
			'Mississippi'=>'MS',
			'Missouri'=>'MO',
			'Montana'=>'MT',
			'Nebraska'=>'NE',
			'Nevada'=>'NV',
			'New Hampshire'=>'NH',
			'New Jersey'=>'NJ',
			'New Mexico'=>'NM',
			'New York'=>'NY',
			'North Carolina'=>'NC',
			'North Dakota'=>'ND',
			'Northern Marianas'=>'MP',
			'Ohio'=>'OH',
			'Oklahoma'=>'OK',
			'Oregon'=>'OR',
			'Palau'=>'PW',
			'Pennsylvania'=>'PA',
			'Puerto Rico'=>'PR',
			'Rhode Island'=>'RI',
			'South Carolina'=>'SC',
			'South Dakota'=>'SD',
			'Tennessee'=>'TN',
			'Texas'=>'TX',
			'Utah'=>'UT',
			'Vermont'=>'VT',
			'Virginia'=>'VA',
			'Virgin Islands'=>'VI',
			'Washington'=>'WA',
			'West Virginia'=>'WV',
			'Wisconsin'=>'WI',
			'Wyoming'=>'WY'
		)),
		'CA' => array('Canada', array(
			'Alberta'=>'AB',
			'British Columbia'=>'BC',
			'Manitoba'=>'MB',
			'New Brunswick'=>'NB',
			'Newfoundland and Labrador'=>'NL',
			'Northwest Territories'=>'NT',
			'Nova Scotia'=>'NS',
			'Nunavut'=>'NU',
			'Ontario'=>'ON',
			'Prince Edward Island'=>'PE',
			'Quebec'=>'QC',
			'Saskatchewan'=>'SK',
			'Yukon'=>'YT'
		))
	);

	foreach ($countries_and_states as $country_code=>$data)
	{
		$country_name = $data[0];
		Db_DbHelper::query('insert into shop_countries(code, name, enabled) values (:code, :name, 1)', array(
			'code'=>$country_code,
			'name'=>$country_name
		));
		
		$country_id = Db_DbHelper::driver()->get_last_insert_id();
		
		foreach ($data[1] as $state_name=>$state_code)
		{
			Db_DbHelper::query('insert into shop_states(country_id, code, name) values (:country_id, :code, :name)', array(
				'country_id'=>$country_id,
				'code'=>$state_code,
				'name'=>$state_name
			));
		}
	}

?>