<?

	/**
	 * Currency converter based on Yahoo 
	 * free currency exchange rate service
	 */
	class Shop_Yahoo_Converter extends Shop_CurrencyConverterBase
	{
		const feed_address = 'http://finance.yahoo.com/d/quotes.csv?f=l1d1t1&s=%s%s=X';
		
		/**
		 * Returns information about the currency converter.
		 * @return array Returns array with two keys: name and description
		 * array('name'=>'My converter name', 'description'=>'My converter description')
		 */
		public function get_info()
		{
			return array(
				'name'=>'Yahoo',
				'description'=>'This converter uses the free currency exchange rate service provided by Yahoo (yahoo.com)'
			);
		}
		
		/**
		 * Returns exchange rate for two currencies
		 * This method must be implemented in a specific currency converter.
		 * @param $parameters_base Active Record object to read parameters from
		 * @param string $from_currency Specifies 3 character ISO currency code (e.g. USD) to convert from.
		 * @param string $to_currency Specifies a currency code to convert to
		 * @return number 
		 */
		public function get_exchange_rate($host_obj, $from_currency, $to_currency)
		{
			$from_currency = trim(strtoupper($from_currency));
			$to_currency = trim(strtoupper($to_currency));
			
			$response = null;
			try
			{
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, sprintf(self::feed_address, $from_currency, $to_currency));
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_POST, false);
				$feed_content = curl_exec($ch);
				curl_close($ch);
			} catch (Exception $ex) {}

			if (!strlen($feed_content))
				throw new Phpr_SystemException('Error loading Yahoo currency exchange feed.');

			$data = explode(',', $feed_content);
			if (count($data) < 2)
				throw new Phpr_SystemException('Yahoo currency exchange rate service has returned invalid data');
				
			return $data[0];
		}
	}

?>