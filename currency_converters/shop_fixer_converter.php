<?

	/**
	 * Currency converter based on Yahoo 
	 * free currency exchange rate service
	 */
	class Shop_Fixer_Converter extends Shop_CurrencyConverterBase
	{
		const feed_address = 'https://api.fixer.io/latest?base=%s&symbols=%s';
		
		/**
		 * Returns information about the currency converter.
		 * @return array Returns array with two keys: name and description
		 * array('name'=>'My converter name', 'description'=>'My converter description')
		 */
		public function get_info()
		{
			return array(
				'name'=>'Fixer.io',
				'description'=>'Fixer.io is a free API for current and historical foreign exchange rates published by the European Central Bank.'
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
				throw new Phpr_SystemException('Error loading Fixer.io currency exchange feed.');

			$data = json_decode($feed_content,true);

			if(!$data || !isset($data['base'])){
				throw new Phpr_SystemException('Fixer.io currency exchange rate service has returned invalid data');
			}

			$result_from_currency = trim(strtoupper($data['base']));
			if($result_from_currency !== $from_currency){
				throw new Phpr_SystemException('Fixer.io currency exchange rate service has returned invalid data');
			}

			$rate = isset($data['rates'][$to_currency]) ? $data['rates'][$to_currency] : false;

			if(!is_numeric($rate) || ($rate <= 0)){
				throw new Phpr_SystemException('Fixer.io currency exchange rate service has returned invalid data');
			}

			return $rate;
		}
	}

?>