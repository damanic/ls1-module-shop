<?

	$country_id = Db_DbHelper::scalar('select id from shop_countries where code_3=:code_3', array('code_3'=>'DNK'));
	
	if ($country_id)
	{
		$wrong_state_codes = array(
			'PRAGUE', 'CZ-ST', 'CZ-JC', 'CZ-PL', 'CZ-KA', 'CZ-US', 'CZ-LI', 'CZ-KR', 'CZ-PA', 'CZ-OL', 'CZ-MO', 'CZ-JM', 'CZ-ZL', 'CZ-VY'
		);

		Db_DbHelper::query(
			'delete from shop_states where country_id=:country_id and code in (:wrong_codes)', array(
				'country_id'=>$country_id,
				'wrong_codes'=>$wrong_state_codes
			)
		);
		
		$state_cnt = Db_DbHelper::scalar('select count(*) from shop_states where country_id=:country_id', array('country_id'=>$country_id));
		
		if (!$state_cnt)
		{
			$states = array(
				'Capital'=>'CAPITAL',
				'Central Denmark'=>'CENTRAL',
				'North Denmark '=>'NORTH-DENMARK',
				'Zealand'=>'ZEALAND',
				'Southern Denmark'=>'SOUTHERN-DENMARK',
			);

			foreach ($states as $state_name=>$state_code)
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