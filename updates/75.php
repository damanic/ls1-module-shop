<?

	Db_DbHelper::query('alter table shop_countries change column code_iso_numeric code_iso_numeric varchar(10)');

	$countries_and_states = array(
		array(
			'name'=>'Australia',
			'code_3'=>'AUS',
			'code_iso_numeric'=>'036',
			'code'=>'AU',
			'states'=>array(
				'AU-NSW'=>'New South Wales',
				'AU-QLD'=>'Queensland',
				'AU-SA'=>'South Australia',
				'AU-TAS'=>'Tasmania',
				'AU-VIC'=>'Victoria',
				'AU-WA'=>'Western Australia'
			)
		)
	);

	foreach ($countries_and_states as $info)
	{
		Db_DbHelper::query('insert into shop_countries(code, name, code_3, code_iso_numeric, enabled) values (:code, :name, :code_3, :code_iso_numeric, 1)', $info);

		$country_id = Db_DbHelper::driver()->get_last_insert_id();
		if (isset($info['states']))
		{
			foreach ($info['states'] as $state_code=>$state_name)
			{
				Db_DbHelper::query('insert into shop_states(country_id, code, name) values (:country_id, :code, :name)', array(
					'country_id'=>$country_id,
					'code'=>$state_code,
					'name'=>$state_name
				));
			}
		}
	}

?>