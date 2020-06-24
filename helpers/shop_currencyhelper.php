<?php

class Shop_CurrencyHelper{

	protected static $cache_currency_settings = array();

	public static function format_currency($num,$decimals,$currency_code){
		if (strlen($num) && strlen($currency_code)) {

			if(!isset(self::$cache_currency_settings[$currency_code])){
				self::$cache_currency_settings[$currency_code] = null;
				$currencies = new Shop_CurrencySettings();
				$obj = $currencies->where( 'code = :code || iso_4217_code = :code', array( 'code' => $currency_code ) )->find_all();
				if ( $obj ) {
					self::$cache_currency_settings[$currency_code] = $obj;
				}
			}

			$obj = self::$cache_currency_settings[$currency_code];

			if ( $obj ) {
				$negative   = $num < 0;
				$neg_symbol = null;
				if ( $negative ) {
					$num        *= - 1;
					$neg_symbol = '-';
				}

				$num = number_format( $num, $decimals, $obj->dec_point, $obj->thousands_sep );
				if ( $obj->sign_before ) {
					return $neg_symbol . $obj->sign . $num;
				}

				return $neg_symbol . $num . $obj->sign;
			}
		}
		return null;
	}


}