<?

	class Shop_CurrencyConverter
	{
		public static $rate_cache = array();
		
		public static function create()
		{
			return new self();
		}

		/**
		 * Returns exchange rate for two currencies
		 * @param string $from_currency Specifies 3 character ISO currency code (e.g. USD) to convert from.
		 * @param string $to_currency Specifies a currency code to convert to
		 * @return number 
		 */
		public function get_rate($from_currency, $to_currency)
		{
			$from_currency = trim(strtoupper($from_currency));
			$to_currency = trim(strtoupper($to_currency));
			
			if ($from_currency == $to_currency)
				return 1;
			
			/*
			 * Look up in the cache
			 */

			$key = $from_currency.'_'.$to_currency;
			if (array_key_exists($key, self::$rate_cache))
				return self::$rate_cache[$key];
				
			/*
			 * Look up in the database cache
			 */

			$converter = Shop_CurrencyConversionParams::create()->get();
			if (!$converter)
				throw new Phpr_ApplicationException('Currency rate converter is not configured.');

			$interval = $converter->refresh_interval;

			$record = Shop_CurrencyRateRecord::create();
			$record->where('from_currency=?', $from_currency);
			$record->where('to_currency=?',  $to_currency);
			$record->where('DATE_ADD(created_at, interval '.$interval.' hour) >= ?', Phpr_DateTime::now());
			$record = $record->find();
			
			if ($record)
				return self::$rate_cache[$key] = $record->rate;
				
			/*
			 * Evaluate rate using a currency rate converter
			 */

			$class_name = $converter->class_name;
			if (!class_exists($class_name))
				throw new Phpr_ApplicationException("Currency converter class $class_name not found.");
				
			$converter->define_form_fields();

			$converter_obj = new $class_name();

			try
			{
				$rate = $converter_obj->get_exchange_rate($converter, $from_currency, $to_currency);

				$record = Shop_CurrencyRateRecord::create();
				$record->from_currency = $from_currency;
				$record->to_currency = $to_currency;
				$record->rate = $rate;
				$record->save();

				return self::$rate_cache[$key] = $rate;
			} catch (Exception $ex)
			{
				/*
				 * Load the most recent rate from the cache
				 */
				$record = Shop_CurrencyRateRecord::create();
				$record->where('from_currency=?', $from_currency);
				$record->where('to_currency=?',  $to_currency);
				$record->order('created_at desc');
				$record = $record->find();
				if (!$record)
					throw $ex;

				return self::$rate_cache[$key] = $record->rate;
			}
		}
		
		/**
		 * Converts currency value from one currency to another
		 * @param number $value Specifies a value to convert
		 * @param string $from_currency Specifies 3 character ISO currency code (e.g. USD) to convert from.
		 * @param string $to_currency Specifies a currency code to convert to
		 * @param int $round Number of decimal digits to round the result to. Pass NULL to disable rounding.
		 * @return number 
		 */
		public function convert($value, $from_currency, $to_currency, $round = 2)
		{
			$result = $value*$this->get_rate($from_currency, $to_currency);
			return $round === null ? $result : round($result, $round);
		}
		
		/**
		 * Converts currency value from specified currency to shop currency
		 * @param number $value Specifies a value to convert
		 * @param string $from_currency Specifies 3 character ISO currency code (e.g. USD) to convert from.
		 * @param int $round Number of decimal digits to round the result to. Pass NULL to disable rounding.
		 * @return number 
		 */
		public function convert_from($value, $from_currency, $round = 2)
		{
			$result = $value*$this->get_rate($from_currency, Shop_CurrencySettings::get()->code);
			return $round === null ? $result : round($result, $round);
		}
	}

?>