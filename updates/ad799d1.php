<?

	Db_DbHelper::query('alter table shop_configuration add column nested_category_urls tinyint(4);');
	Db_DbHelper::query('alter table shop_configuration add column category_urls_prepend_parent tinyint(4);');
	
	if (Phpr::$config->get('NESTED_CATEGORY_URLS'))
		Db_DbHelper::query('update shop_configuration set nested_category_urls=1');

?>