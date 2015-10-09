<?

	$states = array(
		'Acre'=>'AC',
		'Alagoas'=>'AL',
		'Amapá'=>'AP',
		'Amazonas'=>'AM',
		'Bahia'=>'BA',
		'Ceará'=>'CE',
		'Distrito Federal'=>'DF',
		'Espírito Santo'=>'ES',
		'Goiás'=>'GO',
		'Maranhão'=>'MA',
		'Mato Grosso'=>'MT',
		'Mato Grosso do Sul'=>'MS',
		'Minas Gerais'=>'MG',
		'Pará'=>'PA',
		'Paraíba'=>'PB',
		'Paraná'=>'PR',
		'Pernambuco'=>'PE',
		'Piauí'=>'PI',
		'Rio de Janeiro'=>'RJ',
		'Rio Grande do Norte'=>'RN',
		'Rio Grande do Sul'=>'RS',
		'Rondônia'=>'RO',
		'Roraima'=>'RR',
		'Santa Catarina'=>'SC',
		'São Paulo'=>'SP',
		'Sergipe'=>'SE',
		'Tocantins'=>'TO'
	);

	$brazil_id = Db_DbHelper::scalar('select id from shop_countries where code_3=:code_3', array('code_3'=>'BRA'));
	if ($brazil_id)
	{
		$states_cnt = Db_DbHelper::scalar('select count(*) from shop_states where country_id=:country_id', array('country_id'=>$brazil_id));
		
		if (!$states_cnt)
		{
			foreach ($states as $state_name=>$state_code)
			{
				Db_DbHelper::query('insert into shop_states(country_id, code, name) values (:country_id, :code, :name)', array(
					'country_id'=>$brazil_id,
					'code'=>$state_code,
					'name'=>$state_name
				));
			}
		}
	}

?>