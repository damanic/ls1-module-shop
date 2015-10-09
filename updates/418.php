<?

	$states = array(
		'England/Greater Manchester'=>'england_greater_manchester'
	);

	$country_id = Db_DbHelper::scalar('select id from shop_countries where code_3=:code_3', array('code_3'=>'GBR'));
	if ($country_id)
	{
		foreach ($states as $state_name=>$state_code)
		{
			$states_cnt = Db_DbHelper::scalar('select count(*) from shop_states where country_id=:country_id and lower(code)=:state_code', array('country_id'=>$country_id, 'state_code'=>$state_code));

			if (!$states_cnt)
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