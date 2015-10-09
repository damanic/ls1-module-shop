<?
	Db_DbHelper::query('alter table shop_country_lookup add column usps_domestic tinyint(4) default null');
	
	$countries = array(
		'American Samoa'=>'AS',
		'Guam'=>'GU',
		'Puerto Rico'=>'PR',
		'Marshall Islands'=>'MH',
		'Micronesia'=>'FM',
		'North Mariana Islands'=>'MP',
		'Palau' => 'PW',
		'Virgin Islands'=> 'VI'		
	);

	foreach ($countries as $country_name => $iso_code)
	{
		$count = Db_DbHelper::scalar('select count(*) from shop_country_lookup where iso_code=:iso_code', array('iso_code'=>$iso_code));
		if($count)
			Db_DbHelper::query('update shop_country_lookup set usps_domestic = 1 where iso_code = :iso_code', array('iso_code'=>$iso_code));
		else
			Db_DbHelper::query('insert into shop_country_lookup (iso_code, usps_name, usps_domestic) values (:iso_code, :usps_name, 1)', array('iso_code'=>$iso_code, 'usps_name'=> $country_name));
	}

?>