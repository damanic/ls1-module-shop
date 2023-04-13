<?php

/**
 * @deprecated Use Shop_HsCodes instead
 */
class Shop_ShippingHSCode extends Db_ActiveRecord {

	public $table_name = 'shop_shipping_hs_codes';

    public function __construct(){
        traceLog('Use of deprecated class `Shop_ShippingHSCode`. Use Shop_HsCodes instead.');
        parent::__construct();
    }


	public static function create( $init_columns = false ) {
		if ( $init_columns ) {
			return new self();
		} else {
			return new self( null, array( 'no_column_init' => true, 'no_validation' => true ) );
		}
	}

	public function define_columns( $context = null ) {
		$this->define_column( 'classification', 'Classification' )->validation()->fn( 'trim' )->required();
		$this->define_column( 'code', 'Code' )->validation()->fn( 'trim' )->required();
		$this->define_column( 'description', 'Description' )->validation()->fn( 'trim' )->required();
	}

	public function define_form_fields( $context = null ) {}

	public static function find_unique_codes() {
        traceLog('Use of deprecated method `find_unique_codes()`. Use Shop_HsCodes::listHsCodes() instead.');
		$obj    = self::create();
		$obj->where( 'CHAR_LENGTH(code) = 6' );
		$obj->group( 'shop_shipping_hs_codes.code' );
		return $obj->find_all_proxy();
	}

}