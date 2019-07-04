<?php

class Shop_CustomerPreferences {

	private static $cache = array();
	private $table_name = 'shop_customer_preferences';
	protected $preference_data = array();
	public static $access_point = 'shop_scp';


	public static function load( $customer, $field_name ) {
		$field_name  = trim( $field_name );
		$customer_id = is_numeric( $customer ) ? $customer : $customer->id;
		if ( !$customer_id ) {
			return false;
		}
		$_this  = new self();
		$result = $_this->load_preference( $customer_id, $field_name );
		return $result ? $_this : false;
	}

	public static function load_by_hash( $hash ) {
		if ( !$hash || !preg_match('/^[a-f0-9]{32}$/', $hash) ) {
			return false;
		}
		$_this  = new self();
		$result = $_this->load_preference_by_hash( $hash );
		return $result ? $_this : false;
	}


	public static function get( $customer, $field_name, $default = null ) {
		$field_name  = trim( $field_name );
		$customer_id = is_numeric( $customer ) ? $customer : $customer->id;
		if ( !$customer_id ) {
			return false;
		}
		if ( isset( self::$cache[$customer_id][$field_name] ) ) {
			return self::$cache[$customer_id][$field_name];
		}
		$preference = self::load( $customer, $field_name );
		return $preference ? $preference->value( $default ) : $default;
	}

	public static function get_by_hash( $hash, $default = null ) {
		if ( isset( self::$cache[$hash] ) ) {
			return self::$cache[$hash];
		}
		$_this = new self();
		$preference = self::load_by_hash( $hash );
		return $preference ? $preference->value( $default ) : $default;
	}

	public static function set( $customer, $field_name, $value ) {
		$field_name  = trim( $field_name );
		$customer_id = is_numeric( $customer ) ? $customer : $customer->id;
		if ( !$customer_id ) {
			throw new Phpr_ApplicationException( 'Cannot set customer preference: Invalid customer ID provided' );
		}
		$_this = new self();
		$_this->save_preference( $customer_id, $field_name, $value );

		return $_this;
	}

	public static function set_by_hash( $hash, $value ) {
		$_this = new self();
		$found = $_this->load_preference_by_hash( $hash );
		if ( $found ) {
			return self::set( $_this->customer_id(), $_this->field(), $value );
		}
		return false;
	}

	public static function get_customer_preference($customer, $pref_name){
			$customer_preference = Shop_CustomerPreferences::load($customer, $pref_name);
			if(!$customer_preference){
				return Shop_CustomerPreferences::set($customer, $pref_name, null);
			}
			return $customer_preference;
	}

	public function value( $default = null ) {
		if ( !key_exists( 'pref_value', $this->preference_data ) ) {
			return $default;
		}
		return $this->preference_data['pref_value'];
	}

	public function hash() {
		if ( !key_exists( 'pref_hash', $this->preference_data ) ) {
			return null;
		}
		return $this->preference_data['pref_hash'];
	}

	public function customer_id() {
		if ( !key_exists( 'customer_id', $this->preference_data ) ) {
			return null;
		}
		return $this->preference_data['customer_id'];
	}

	public function field() {
		if ( !key_exists( 'pref_field', $this->preference_data ) ) {
			return null;
		}
		return $this->preference_data['pref_field'];
	}

	public function generate_preference_url($value,$redirect='/'){
		$redirect = str_replace('/','|',$redirect);
		$params = array(
			'h' => $this->hash(),
			'r' => $redirect,
			'v' => $value
		);
		return root_url( '/'.self::$access_point.'/',true).'?'. http_build_query($params);
	}


	protected function save_preference( $customer_id, $field_name, $value ) {
		$bind = array(
			'customer_id' => $customer_id,
			'pref_hash'   => $this->generate_preference_hash( $customer_id, $field_name ),
			'pref_field'  => $field_name,
			'pref_value'  => $value,
		);

		$sql = "INSERT INTO " . $this->table_name . "
				(customer_id, pref_hash, pref_field, pref_value)
				VALUES (:customer_id,:pref_hash,:pref_field, :pref_value)
				ON DUPLICATE KEY UPDATE pref_value = :pref_value";

		Db_DbHelper::query( $sql, $bind );
		$this->load_preference( $customer_id, $field_name, $value );
		self::$cache[$bind['pref_hash']]        = $this->value();
		self::$cache[$customer_id][$field_name] = $this->value();

		return true;
	}

	protected function load_preference( $customer_id, $field_name ) {
		$bind = array(
			'customer_id' => $customer_id,
			'pref_field'  => $field_name,
		);
		$sql  = "SELECT * FROM " . $this->table_name . "
				WHERE customer_id = :customer_id  
				AND pref_field = :pref_field";

		$result = Db_DbHelper::queryArray( $sql, $bind );
		if ( !$result ) {
			$this->preference_data = array();
			return false;
		}
		$this->preference_data = $result[0];

		return true;
	}

	protected function load_preference_by_hash( $hash ) {
		$bind = array(
			'pref_hash' => $hash,
		);
		$sql  = "SELECT * FROM " . $this->table_name . "
				WHERE pref_hash = :pref_hash";

		$result = Db_DbHelper::queryArray( $sql, $bind );
		if ( !$result ) {
			$this->preference_data = array();

			return false;
		}
		$this->preference_data = $result[0];

		return true;
	}

	protected function generate_preference_hash( $customer_id, $field_name ) {
		$string = get_class( $this ) .'_'. $customer_id .'_'. $field_name .'_'. rand( 0, 1000 );

		return md5( $string );
	}



}