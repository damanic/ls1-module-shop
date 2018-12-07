<?php

class Shop_ShippingServiceLevel extends Db_ActiveRecord {
	public $table_name = 'shop_shipping_service_level';

	public static function create() {
		return new self();
	}
	public $has_many = array(
		'delivery_estimates'=>array('class_name'=>'Shop_ShippingDeliveryEstimate', 'foreign_key'=>'shipping_service_level_id', 'delete'=>true),
	);


	public function define_columns( $context = null ) {
		$this->define_column( 'name', 'Name' )->validation()->fn( 'trim' )->required( "Please specify a name" );
		$this->define_multi_relation_column('delivery_estimates', 'delivery_estimates', 'Service Delivery Estimates', '@id')->invisible();

		$this->defined_column_list = array();
		Backend::$events->fireEvent( 'shop:onExtendShippingServiceLevelModel', $this );
		$this->api_added_columns = array_keys( $this->defined_column_list );
	}

	public function define_form_fields( $context = null ) {
		$this->add_form_field( 'name' );
		$this->add_form_field('delivery_estimates')->renderAs('delivery_estimates');
		Backend::$events->fireEvent( 'shop:onExtendShippingServiceLevelForm', $this, $context );
	}

	public function get_added_field_options( $db_name, $current_key_value = - 1 ) {
		$result = Backend::$events->fireEvent( 'shop:onGetShippingServiceLevelFieldOptions', $db_name, $current_key_value );
		foreach ( $result as $options ) {
			if ( is_array( $options ) || ( strlen( $options && $current_key_value != - 1 ) ) ) {
				return $options;
			}
		}
		return false;
	}

	public function get_added_field_option_state( $db_name, $key_value ) {
		$result = Backend::$events->fireEvent( 'shop:onGetShippingServiceLevelFieldState', $db_name, $key_value, $this );
		foreach ( $result as $value ) {
			if ( $value !== null ) {
				return $value;
			}
		}
		return false;
	}

	public function before_save( $deferred_session_key = null ) {
		$estimates = $this->list_related_records_deferred('delivery_estimates',$deferred_session_key);
		$shipping_zone_ids = array();
		foreach($estimates as $estimate){
			if(in_array($estimate->shipping_zone_id,$shipping_zone_ids)){
				throw new Phpr_ApplicationException('Error: A service level can only have one delivery estimate per shipping zone');
			}
			$shipping_zone_ids[] = $estimate->shipping_zone_id;
		}

	}

	public function get_zones_as_string(){
		if(!$this->delivery_estimates->count){
			return null;
		}
		$string = '';
		foreach($this->delivery_estimates as $estimate){
			if($estimate->shipping_zone){
				$string.=$estimate->shipping_zone->name.', ';
			}
		}
		return substr($string,0,-2);
	}
	/*
	* Event descriptions
	*/

	/**
	 * @event   shop:onExtendShippingServiceLevelModel
	 * @package shop.events
	 * @author  github:damanic | LemonStand eCommerce Inc.
	 * @see     shop:onExtendShippingServiceLevelForm
	 * @see     http://lemonstand.com/docs/extending_existing_models Extending existing models
	 * @see     http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables.
	 *
	 * @param Shop_ShippingServiceLevel $shipping_service_level Specifies the shipping service level object.
	 */
	private function event_onExtendShippingServiceLevelModel( $shipping_service_level ) {}

	/**
	 * @event   shop:onExtendShippingServiceLevelForm
	 * @package shop.events
	 * @author  github:damanic | LemonStand eCommerce Inc.
	 * @see     shop:onExtendShippingServiceLevelModel
	 * @see     http://lemonstand.com/docs/extending_existing_models Extending existing models
	 * @see     http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
	 *
	 * @param Shop_ShippingServiceLevel $shipping_service_level Specifies the shipping service level object.
	 * @param string            $context       Specifies the execution context.
	 */
	private function event_onExtendShippingServiceLevelForm( $shipping_service_level, $context ) {}

}