<?php

/**
 * Represents a unit of measurement.
 * @documentable
 * @author Matt Manning (github:damanic)
 * @package shop.classes
 */

class Shop_MeasurementUnit {

	public $name = null;
	public $name_plural = null;
	public $symbol = null;
	public $code = null;

	protected static $_instance;
	protected static $measurement_units = array(
		//length
		'm' => array(
			'name' => 'Meter',
			'symbol' => 'm',
			'name_plural' => 'Meters',
		),
		'm2'  => array(
			'name' => 'Square Meter',
			'symbol' => 'm2',
			'name_plural' => 'Square Meters',
		),
		'cm' => array(
			'name' => 'Centimeter',
			'symbol' => 'cm',
			'name_plural' => 'Centimeters'
		),
		'mm' => array(
			'name' => 'Millimeter',
			'symbol' => 'mm',
			'name_plural' => 'Millimeters'
		),
		'in' => array(
			'name' => 'Inch',
			'symbol' => 'in',
			'name_plural' => 'Inches'
		),
		'in2' => array(
			'name' => 'Square Inch',
			'symbol' => 'in2',
			'name_plural' => 'Square Inches'
		),
		'ft' => array(
			'name' => 'Foot',
			'symbol' => 'ft',
		),
		'ft2' => array(
			'name' => 'Square Foot',
			'symbol' => 'ft2',
		),
		'yrd' => array(
			'name' => 'Yard',
			'symbol' => 'yrd',
			'name_plural' => 'Yards'
		),
		//mass
		'g' => array(
			'name' => 'Gram',
			'symbol' => 'g',
			'name_plural' => 'Grams'
		),
		'kg' => array(
			'name' => 'Kilogram',
			'symbol' => 'kg',
			'name_plural' => 'Kilograms'
		),
		'lb' => array(
			'name' => 'Pound',
			'symbol' => 'lb',
			'name_plural' => 'Pounds'
		),
		'oz' => array(
			'name' => 'Ounce',
			'symbol' => 'oz',
			'name_plural' => 'Ounces'
		),
		//volume
		'l' => array(
			'name' => 'Litre',
			'symbol' => 'L',
			'name_plural' => 'Litres'
		),
		'ml' => array(
			'name' => 'Millilitre',
			'symbol' => 'mL',
			'name_plural' => 'Millilitres'
		),
		'gal' => array(
			'name' => 'Gallon',
			'symbol' => 'gal',
			'name_plural' => 'Gallons'
		),
		'foz' => array(
			'name' => 'Fluid Ounce',
			'symbol' => 'fl oz',
			'name_plural' => 'Fluid Ounces'
		)

	);

	private static $measurement_units_extended = false;

	public function __construct(){}

	public static function create(){
		if(!self::$_instance){
			$obj = new self();
			$obj->name = 'Unit';
			$obj->name_plural = 'Units';
			$obj->symbol = 'unit';
			$obj->code = 'unit';
			self::$_instance = $obj;
		}
		return self::$_instance;
	}

	public function find_all(){
		$results = array();
		if(!self::$measurement_units_extended) {
			$this->extend_measurement_units();
		}
		foreach(self::$measurement_units as $code => $data){
			$results[] = $this->convert_to_obj($code,$data);
		}
		return $results;
	}

	public function find_by_code($code){
		if($code){
			if(isset(self::$measurement_units[$code])){
				$data = self::$measurement_units[$code];
				return $this->convert_to_obj($code, $data);
			}
			if(!self::$measurement_units_extended) {
				$this->extend_measurement_units();
				return $this->find_by_code($code); //try again now extended
			}
		}
		return false;
	}

	public function __toArray(){
		return array(
			'name' => $this->name,
			'symbol' => $this->symbol,
			'name_plural' => $this->name_plural,
			'code' => $this->code,
		);
	}

	private function convert_to_obj($code , $data){
		$obj = new self();
		$obj->name = $data['name'];
		$obj->name_plural = isset($data['name_plural']) ? $data['name_plural'] : $data['name'];
		$obj->symbol = $data['symbol'];
		$obj->code = $code;
		return $obj;
	}

	private function extend_measurement_units(){
		$uom                  = self::$measurement_units;
		$params               = array();
		$updated_measurements = Backend::$events->fire_event( array( 'name' => 'shop:onUpdateMeasurementUnits', 'type' => 'update_result' ), $uom, $params );
		if ( $updated_measurements && is_array( $updated_measurements ) ) {
			self::$measurement_units = array_merge( $uom, $updated_measurements );
		}
		self::$measurement_units_extended = true;
	}

	/**
	 * Triggered when measurement units are loaded in.
	 * Return an array in the same format as Shop_MeasurementUnit::$measurement_units
	 * to add new measurement options
	 * @event shop:onUpdateMeasurementUnits
	 * @package shop.events
	 * @author Matt Manning (github:damanic)
	 * @param array $measurement_units
	 */
	private function event_onUpdateMeasurementUnits($measurement_units) {}
}

