<?

	class Shop_CurrencyRateRecord extends Db_ActiveRecord
	{
		public $table_name = 'shop_currency_exchange_rates';

		public static function create()
		{
			return new self();
		}
		
		public static function delete_old_records()
		{
			Db_DbHelper::query('delete from shop_currency_exchange_rates where DATE_ADD(created_at, interval 90 day) < NOW()');
		}
	}
?>