<?php

class Shop_CurrencyHelper{

	public static $allow_unknown_currency_codes = false;

	protected static $cache_currency_settings = array();

	public static function get_currency_setting($currency_code){
		if(!isset(self::$cache_currency_settings[$currency_code])){
			self::$cache_currency_settings[$currency_code] = null;
			$currencies = new Shop_CurrencySettings();
			$obj = $currencies->where( 'code = :code || iso_4217_code = :code', array( 'code' => $currency_code ) )->find_all();
			if ( $obj ) {
				self::$cache_currency_settings[$currency_code] = $obj;
			}
		}
		return $obj = self::$cache_currency_settings[$currency_code];
	}

	public static function format_currency($num,$decimals,$currency_code){
		if (strlen($num) && strlen($currency_code)) {

			$obj = self::get_currency_setting($currency_code);

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

	public static function convert_price($price, $currency_code) {

		$internal_currency = Shop_CurrencySettings::get();
		$currency_converter = Shop_CurrencyConverter::create();
		$to_currency = null;

		if(!is_numeric($price) || !$currency_code){
			throw new Phpr_ApplicationException('Cannot convert price: Invalid price/currency parameters given');
		}

		if(!self::$allow_unknown_currency_codes){
			$to_currency = self::get_currency_setting($currency_code);
			if ( !$to_currency ) {
				throw new Phpr_ApplicationException( 'Cannot convert price: Unknown currency code give (' . $currency_code . ')' );
			}
		}

		$to_currency_code = $to_currency ? $to_currency->code : $currency_code;

		if ($to_currency && $internal_currency->id != $to_currency->id) {
			return $currency_converter->convert($price, $internal_currency->code, $to_currency_code, 2);
		}
		return $price;
	}

}