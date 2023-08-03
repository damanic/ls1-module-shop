<?php

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
         * @return float
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

			$converter_params = Shop_CurrencyConversionParams::create()->get();
			if (!$converter_params)
				throw new Phpr_ApplicationException('Currency rate converter is not configured.');

			$interval = $converter_params->refresh_interval;

			if($converter_params->enable_cron_updates){
				/*
				* Use most recent rate, cron process keeps them up to date
				*/
				$recent_record = $this->get_most_recent_record($from_currency, $to_currency);
				if($recent_record){
					return self::$rate_cache[$key] = $recent_record->rate;
				}
			} else {
				/*
				 * Look for a rate that has not expired the interval
				 */
				$record = Shop_CurrencyRateRecord::create();
				$record->where('from_currency=?', $from_currency);
				$record->where('to_currency=?',  $to_currency);
				$record->where('DATE_ADD(created_at, interval '.$interval.' hour) >= ?', Phpr_DateTime::now());
				$record = $record->find();

				if ($record)
					return self::$rate_cache[$key] = $record->rate;
			}


			/*
			 * Evaluate rate using a currency rate converter
			 */
			$converter_driver = $converter_params->get_converter_object();

			try {
				if (!$converter_driver)
					throw new Phpr_ApplicationException('Currency rate converter is not configured.');

				$converter_params->define_form_fields(); //loads all field params including those added by extension
				$rate = $converter_driver->get_exchange_rate($converter_params, $from_currency, $to_currency);
				if(!$rate) {
					throw new Phpr_ApplicationException("Currency converter could not determine an exchange rate for $from_currency => $to_currency");
				}
				$rate = $this->update_rate( $from_currency, $to_currency, $rate );
				self::$rate_cache[$key] = $rate;
			} catch (Exception $ex) {
				$fallback_record = $this->get_most_recent_record($from_currency, $to_currency);
				if (!$fallback_record)
					throw $ex;

				traceLog("Currency converter did not return an exchange rate for $from_currency => $to_currency . Used last record as fallback");
				return self::$rate_cache[$key] = $fallback_record->rate;
			}
		}

		public function update_rate($from_currency, $to_currency, $rate){
			$result = Backend::$events->fireEvent('shop:onAdjustCurrencyConverterRate', $from_currency, $to_currency, $rate);
			foreach ($result as $new_rate) {
				if (is_numeric($new_rate)){
					$rate = $new_rate;
				}
			}
			$record = Shop_CurrencyRateRecord::create();
			$record->from_currency = $from_currency;
			$record->to_currency = $to_currency;
			$record->rate = $rate;
			$record->save();
			return $record->rate;
		}

		protected function get_most_recent_record($from_currency, $to_currency){
			$record = Shop_CurrencyRateRecord::create();
			$record->where('from_currency=?', $from_currency);
			$record->where('to_currency=?',  $to_currency);
			$record->order('created_at desc');
			$record = $record->find();
			return $record;
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
            if(!is_numeric($value) || $value == 0){
                return 0;
            }
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
            if(!is_numeric($value) || $value == 0){
                return 0;
            }
			$result = $value*$this->get_rate($from_currency, Shop_CurrencySettings::get()->code);
			return $round === null ? $result : round($result, $round);
		}

        /**
         * This method will update all cached exchange rates
         *
         * @param bool $force If false only rates that are older than the refresh interval will be updated
         * @return void
         * @throws Phpr_ApplicationException
         */
        public function update_all_rates($force = false)
        {
            $sql = "SELECT from_currency, to_currency
				FROM shop_currency_exchange_rates
				GROUP BY from_currency, to_currency";

            $currencies = Db_DbHelper::queryArray($sql);

            if (!$currencies) {
                return;
            }

            foreach ($currencies as $data) {
                $fromCurrency = $data['from_currency'];
                $toCurrency = $data['to_currency'];
                $this->refreshRate($fromCurrency, $toCurrency, $force);
            }
        }

        /**
         * This method will update all exchange rate pairs for the specified currency
         * @param string $currencyCode Specifies 3 character ISO currency code (e.g. USD)
         * @param bool $force If false only rates that are older than the refresh interval will be updated
         * @return void
         * @throws Phpr_ApplicationException
         */
        public function updateRates($currencyCode, $force = false)
        {
            $sql = "SELECT from_currency, to_currency
				FROM shop_currency_exchange_rates
				WHERE (from_currency = :currencyCode OR to_currency = :currencyCode)
				GROUP BY from_currency, to_currency";

            $currencies = Db_DbHelper::queryArray($sql, array( 'currencyCode' => $currencyCode));

            if (!$currencies) {
                return;
            }

            foreach ($currencies as $data) {
                $fromCurrency = $data['from_currency'];
                $toCurrency = $data['to_currency'];
                $this->refreshRate($fromCurrency, $toCurrency, $force);
            }
        }


        protected function refreshRate($fromCurrency, $toCurrency, $force = false)
        {
            $converterConfig = Shop_CurrencyConversionParams::create()->get();
            $className = $converterConfig->class_name;
            if (!class_exists($className)) {
                throw new Phpr_ApplicationException("Currency converter class $className not found.");
            }

            $converterConfig->define_form_fields();
            $converter_obj = new $className();
            $interval = $converterConfig->refresh_interval ?: 24;

            $bind = [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'interval' => $interval,
                'now' => Phpr_DateTime::now()->toSqlDateTime()
            ];

            $updateRate = true;
            if (!$force) {
                //check if rate is older than refresh interval
                $sql = "SELECT count(id)
                    FROM shop_currency_exchange_rates
                    WHERE from_currency = :from_currency
                    AND to_currency = :to_currency
                    AND DATE_ADD(created_at, interval :interval hour) >= :now";

                $updateRate = !Db_DbHelper::scalar($sql, $bind);
            }

            if ($updateRate) {
                try {
                    $rate = $converter_obj->get_exchange_rate($converterConfig, $fromCurrency, $toCurrency);
                    if ($rate) {
                        $this->update_rate($fromCurrency, $toCurrency, $rate);
                    }
                } catch (\Exception $ex) {
                    traceLog('Failed to get exchange rate ' . $ex->getMessage());
                }
            }

            return true;
        }
	}

