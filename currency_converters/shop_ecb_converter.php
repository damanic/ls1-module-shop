<?

	/**
	 * Currency converter based on European Central Bank 
	 * free XML currency rate feed
	 */
	class Shop_Ecb_Converter extends Shop_CurrencyConverterBase
	{
		const feed_address = 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
		
		/**
		 * Returns information about the currency converter.
		 * @return array Returns array with two keys: name and description
		 * array('name'=>'My converter name', 'description'=>'My converter description')
		 */
		public function get_info()
		{
			return array(
				'name'=>'European Central Bank',
				'description'=>'This converter uses the free currency exchange rate feed provided by European Central Bank (www.ecb.int)'
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
			
			$feed_content = null;
			try
			{
//				$feed_content = @file_get_contents(self::feed_address);
				
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, self::feed_address);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_POST, false);
				$feed_content = curl_exec($ch);
				curl_close($ch);
			} catch (Exception $ex) {}

			if (!strlen($feed_content))
				throw new Phpr_SystemException('Error loading European Central Bank feed.');

			$doc = new DOMDocument('1.0');
			$doc->loadXML($feed_content);
			$xPath = new DOMXPath($doc);
			$xPath->registerNamespace('ns', "http://www.ecb.int/vocabulary/2002-08-01/eurofxref");

			if ($from_currency == 'EUR')
				$from_rate = 1;
			else
			{
				$from_rate = $xPath->query("//gesmes:Envelope/ns:Cube/ns:Cube/ns:Cube[@currency='$from_currency']");
				if (!$from_rate->length)
					throw new Phpr_SystemException('Currency rate for '.$from_currency.' not found');

				$from_rate = $from_rate->item(0)->getAttribute('rate');
			}
			
			if ($to_currency == 'EUR')
				$to_rate = 1;
			else
			{
				$to_rate = $xPath->query("//gesmes:Envelope/ns:Cube/ns:Cube/ns:Cube[@currency='$to_currency']");
				if (!$to_rate->length)
					throw new Phpr_SystemException('Currency rate for '.$to_currency.' not found');

				$to_rate = $to_rate->item(0)->getAttribute('rate');
			}

			if(!is_numeric($to_rate) || !is_numeric($from_rate)){
				throw new Phpr_SystemException('Invalid currency rates received');
			}

			if(($to_rate <= 0) || ($from_rate <= 0)){
				throw new Phpr_SystemException('Invalid currency rates received');
			}

			return $to_rate/$from_rate;
		}
	}

?>