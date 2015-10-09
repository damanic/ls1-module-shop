<?

	$country_id = Db_DbHelper::scalar('select id from shop_country_lookup where iso_code=:code', array('code'=>'ME'));
	if($country_id)
		Db_DbHelper::query('update shop_country_lookup set usps_name = :usps_name where iso_code = :code', array('usps_name' => 'Montenegro', 'code'=>'ME'));
	else
		Db_DbHelper::query('insert into shop_country_lookup (iso_code, usps_name) values (:code, :usps_name)', array('usps_name' => 'Montenegro', 'code'=>'ME'));

?>