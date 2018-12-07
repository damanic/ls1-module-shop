<?php

class Shop_ShippingZone extends Db_ActiveRecord {
	public $table_name = 'shop_shipping_zones';

	public static function create() {
		return new self();
	}

	public function define_columns( $context = null ) {
		$this->define_column( 'name', 'Name' )->validation()->fn( 'trim' )->required( "Please specify a name" );
		$this->define_column( 'delivery_min_days', 'Delivery Time: Min Days' )->validation()->fn( 'trim' )->required( "Please specify the fastest delivery time for countries in this shipping zone" );
		$this->define_column( 'delivery_max_days', 'Delivery Time: Max Days' )->validation()->fn( 'trim' )->required( "Please specify the slowest delivery time for countries in this shipping zone" );

		$this->defined_column_list = array();
		Backend::$events->fireEvent( 'shop:onExtendShippingZoneModel', $this );
		$this->api_added_columns = array_keys( $this->defined_column_list );
	}

	public function define_form_fields( $context = null ) {
		$this->add_form_field( 'name' );
		$this->add_form_field( 'delivery_min_days', 'left' );
		$this->add_form_field( 'delivery_max_days', 'right' );
		Backend::$events->fireEvent( 'shop:onExtendShippingZoneForm', $this, $context );
	}

	public function get_added_field_options( $db_name, $current_key_value = - 1 ) {
		$result = Backend::$events->fireEvent( 'shop:onGetShippingZoneFieldOptions', $db_name, $current_key_value );
		foreach ( $result as $options ) {
			if ( is_array( $options ) || ( strlen( $options && $current_key_value != - 1 ) ) ) {
				return $options;
			}
		}
		return false;
	}

	public function get_added_field_option_state( $db_name, $key_value ) {
		$result = Backend::$events->fireEvent( 'shop:onGetShippingZoneFieldState', $db_name, $key_value, $this );
		foreach ( $result as $value ) {
			if ( $value !== null ) {
				return $value;
			}
		}
		return false;
	}

	public function before_save( $deferred_session_key = null ) {
		if ( !is_numeric( $this->delivery_min_days ) || !is_numeric( $this->delivery_max_days ) ) {
			throw new Phpr_ApplicationException( 'Please enter valid min/max day numbers' );
		}
	}

	/*
	* Event descriptions
	*/

	/**
	 * @event   shop:onExtendShippingZoneModel
	 * @package shop.events
	 * @author  github:damanic | LemonStand eCommerce Inc.
	 * @see     shop:onExtendShippingZoneForm
	 * @see     http://lemonstand.com/docs/extending_existing_models Extending existing models
	 * @see     http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables.
	 *
	 * @param Shop_ShippingZone $shipping_zone Specifies the shipping zone object.
	 */
	private function event_onExtendShippinZoneModel( $shipping_zone ) {
	}

	/**
	 * @event   shop:onExtendShippingZoneForm
	 * @package shop.events
	 * @author  github:damanic | LemonStand eCommerce Inc.
	 * @see     shop:onExtendShippingZoneModel
	 * @see     http://lemonstand.com/docs/extending_existing_models Extending existing models
	 * @see     http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
	 *
	 * @param Shop_ShippingZone $shipping_zone Specifies the shipping zone object.
	 * @param string            $context       Specifies the execution context.
	 */
	private function event_onExtendShippingZoneForm( $shipping_zone, $context ) {
	}

}