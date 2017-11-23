<?php

class Shop_CurrencyHelper{

	public static function format_currency($num,$decimals,$currency_code){
		if (strlen($num) && strlen($currency_code)) {

			$currencies = new Shop_CurrencySettings();
			$obj = $currencies->where( 'code = ?', $currency_code )->find_all();

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