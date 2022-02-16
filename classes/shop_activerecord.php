<?php
/*
 * Extends support for currency fields in DB_ActiveRecord
 */
class Shop_ActiveRecord extends Db_ActiveRecord{

	protected $internal_currency_code = null;


	/**
	 * Set the base currency code for currency fields stored in this data model
	 * @param string $currency_code  ISO 4217 currency code Eg. USD
	 * @return void
	 */
	public function set_currency_code($currency_code=null){
		if(!$currency_code){
			$internal_currency  = Shop_CurrencySettings::get();
			$this->internal_currency_code = $internal_currency->code;
			return;
		}
		$this->internal_currency_code = $currency_code;
	}


	/**
	 * Get the base currency code for this data model
	 * @return string ISO 4217 currency code
	 */
	public function get_currency_code(){
		if(!$this->internal_currency_code){
			$this->set_currency_code();
		};
		return $this->internal_currency_code;
	}


	/**
	 * Returns a currency value, and applies currency conversion when required
	 * @param string $db_name Specifies the column name.
	 * @param string $currency_code  ISO 4217 , specifies which currency the value should be returned in. Eg. USD
	 * @return float Returns the field value adjusted for currency.
	 */
	public function get_currency_field($db_name, $currency_code){

		if($this->get_currency_code() == $currency_code){
			return $this->$db_name;
		}

		//check if a price record has been set for this currency
		$price_record = Shop_CurrencyPriceRecord::find_record($this,$db_name, $currency_code);
		if($price_record){
			return $price_record->value;
		}

		//do a currency conversion
		$currency_converter = Shop_CurrencyConverter::create();
		$from_currency_code =  $this->get_currency_code();
		$to_currency_code = $currency_code;
		return $currency_converter->convert($this->$db_name, $from_currency_code, $to_currency_code, 4);
	}

	/**
	 * Returns a formatted currency value, and applies currency conversion when required
	 * @param string $db_name Specifies the column name.
	 * @param string $currency_code  ISO 4217 , specifies which currency the value should be returned in. Eg. USD
	 * @return string Returns the formatted currency value.
	 */
	public function display_currency_field( $db_name, $currency_code) {
		$value = $this->get_currency_field($db_name, $currency_code);
		return Shop_CurrencyHelper::format_currency($value,2, $currency_code);
	}

    /**
     * Returns a formatted currency value formatted according to the records internal currency code.
     * This is used by the application framework to render currency values in list/presentation views
     * @param float $value A currency value
     * @return string Returns the formatted currency value.
     */
	public function format_currency($value){
        if($this->internal_currency_code) {
            return Shop_CurrencyHelper::format_currency($value, 2, $this->get_currency_code());
        }
        return format_currency($value,2);
	}


}