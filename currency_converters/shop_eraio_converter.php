<?

	/**
	 * Currency converter
	 * free currency exchange rate service exchangeratesapi.io replaces deprecated service from fixer.io
	 */
	class Shop_ERAIO_Converter extends Shop_CurrencyConverterBase
	{
		const feed_address = 'api.exchangeratesapi.io/latest?access_key=%s';
		protected static $api_result = array();
		
		/**
		 * Returns information about the currency converter.
		 * @return array Returns array with two keys: name and description
		 * array('name'=>'My converter name', 'description'=>'My converter description')
		 */
		public function get_info()
		{
			return array(
				'name'=>'exchangeratesapi.io',
				'description'=>'A free API for current and historical foreign exchange rates published by the European Central Bank.'
			);
		}

		public function build_config_ui($host_obj)
		{
			$host_obj->add_field('api_key', 'API KEY')->tab('Configuration')->renderAs(frm_text)->comment('This service requires an API key from exchangeratesapi.io ', 'above', true);
			$host_obj->add_field('use_ssl', 'Use SSL')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Only switch this on if your API plan allows for HTTPS access ', 'above', true);
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
			$rate = null;


			if(empty(self::$api_result)) {
				$access_point = $host_obj->use_ssl ? 'https://'.self::feed_address : 'http://'.self::feed_address ;
				$access_point = sprintf( $access_point, $host_obj->api_key);
				try {
					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $access_point );
					curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
					curl_setopt( $ch, CURLOPT_POST, false );
					$feed_content = curl_exec( $ch );
					curl_close( $ch );
				} catch ( Exception $ex ) {
				}

				if ( !strlen( $feed_content ) ) {
					throw new Phpr_SystemException( 'Error loading exchangeratesapi.io currency exchange feed.' );
				}

				$data = json_decode( $feed_content, true );
				if(!$data || !isset($data['base'])){
					throw new Phpr_SystemException('exchangeratesapi.io currency exchange rate service has returned invalid data');
				}
				self::$api_result = $data;
			}

			$data = self::$api_result;

			$result_from_currency = trim(strtoupper($data['base']));
			if($result_from_currency == $from_currency){
				$rate = isset($data['rates'][$to_currency]) ? $data['rates'][$to_currency] : false;
			} else {
				$from_rate = isset($data['rates'][$from_currency]) ? $data['rates'][$from_currency] : false;
				$to_rate = isset($data['rates'][$to_currency]) ? $data['rates'][$to_currency] : false;
				if($from_rate && $to_rate){
					$rate = round((1 / $from_rate) * $to_rate, 6);
				}
			}

			if(!is_numeric($rate) || ($rate <= 0)){
				throw new Phpr_SystemException('exchangeratesapi.io currency exchange rate service has returned invalid data');
			}

			return $rate;
		}
	}

?>