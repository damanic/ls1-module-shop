<?php
/*
 * This helper requires PHP v7.1+
 */

if (version_compare(phpversion(), '7.1.0', '<')) {
	return;
}

use DVDoug\BoxPacker\PackedBoxList as PackedBoxList;
use DVDoug\BoxPacker\Packer as BoxPacker;
use DVDoug\BoxPacker\LimitedSupplyBox as BoxPackerBox;
use DVDoug\BoxPacker\Item as BoxPackerItem;

class Shop_BoxPacker {


	public $max_items_to_distribute = 100; //lower this value if experiencing slow packing calculations
	protected $shipping_params = null;
	protected $weights_in_kg = false;
	protected $dimensions_in_cm = false;
	protected $unpackable_items = array();

	public function __construct() {
		if (version_compare(phpversion(), '7.1.0', '<')) {
			throw new Phpr_ApplicationException('Error: Shop_Boxpacker requires PHP >= 7.1');
		}
		$this->shipping_params  = Shop_ShippingParams::get();
		$this->weights_in_kg    = ( $this->shipping_params->weight_unit == 'KGS' );
		$this->dimensions_in_cm = ( $this->shipping_params->dimension_unit == 'CM' );
	}

	/**
	 * @param array $items  Array of Shop_CartItem or Shop_OrderItem
	 * @param null  $boxes Optional array of Shop_BoxPacker_Box objects that can be used for packing
	 * @param array $info Supporting information that could influence packing constraints. Passed to shop:onBoxPackerPack event
	 *
	 * @return PackedBoxList|false
	 */
	public function pack( $items, $boxes = null, $info=array() ) {
		$item_count = 0;
		$boxes = $boxes ? $boxes : $this->shipping_params->shipping_boxes;
		$packer = new BoxPacker();
		$packer_items = array();
		$packer_boxes = array();
		$default_info = array(
			'context' => null,
			'order' => null
		);
		$data = array_merge($default_info,$info);

		$packable_list = $this->get_packable_items_list($items);

		if($packable_list['failed_compat']){
			throw new Phpr_ApplicationException('Packing failed. Some items did not have valid height, width, depth dimensions');
		}

		if(!count($packable_list['items'])){
			return false;
		}

		foreach ( $boxes as $box ) {
			$packer_boxes[] = $this->make_box_compat($box);
		}

		$packable_items = $packable_list['items'];
		foreach ( $items as $item ) {
            $item_key = (isset($item->key) && $item->key) ? $item->key : $item->id;;
			if ( isset( $packable_items[$item_key] ) ) {
				$quantity = $item->quantity;
				while ( $quantity > 0 ) {
					$quantity--;
					$packer_items[] =  $packable_items[$item_key]['item'];
					$item_count++;
				}
				if ( isset( $packable_items[$item_key]['extras'] ) ) {
					foreach ( $packable_items[$item_key]['extras'] as $extra ) {
						$packer_items[] = $extra;
						$item_count++;
					}
				}
			}
		}

		if($item_count > $this->max_items_to_distribute){
			$packer->setMaxBoxesToBalanceWeight(0); //disable weight distribution to avoid potential slow down
		}

		/**
		 * Event: Opportunity to update packer items/boxes or force a result by returning a PackedBoxList
		 */
		$results = Backend::$events->fireEvent('shop:onBoxPackerPack', $packer_items, $packer_boxes, $info);
		foreach ($results as $packed_boxes) {
			if ($packed_boxes && is_a($packed_boxes,'DVDoug\BoxPacker\PackedBoxList')){
				return $packed_boxes;
			}
		}

		if ( !$boxes || !$packer_items) {
			return false;
		}

		foreach ( $packer_boxes as $packer_box ) {
			$packer->addBox( $packer_box );
		}

		foreach ( $packer_items as $packer_item){
			$packer->addItem( $packer_item );
		}

		$packed_boxes = $packer->pack();
		return $packed_boxes;
	}

	public function get_packable_items_list($items){
		$compat_items = array();
		$failed_compat = false;
		foreach ( $items as $item ) {
			$shipping_enabled = $item->product->product_type->shipping;
			if($shipping_enabled) {
				$compatible_item = $this->make_item_compat( $item );
				if($compatible_item) {
					$compat_items[$compatible_item->item_id]['item']  = $compatible_item;
				} else {
					$failed_compat = true;
				}
			}
			$extras = false;
			if(isset($item->extra_options)){
				$extras = $item->extra_options;
			} else if(method_exists($item,'get_extra_option_objects')) {
				$extras = $item->get_extra_option_objects();
			}
			if($extras){
				foreach ($extras as $extra_item) {
					$compatible_item = $this->make_item_compat( $extra_item );
					if($compatible_item){
						$compat_items[$compatible_item->item_id]['extras'][]  = $compatible_item;
					}
				}
			}
		}
		return array(
			'items' => $compat_items,
			'failed_compat' => $failed_compat
		);
	}

	/** @deprecated
	 * Use Shop_ShippingBox::get_boxes();
	 */
	public function get_boxes() {
		return Shop_ShippingBox::get_boxes();
	}

	public function make_box_compat( Shop_ShippingBox $box ) {

		$bp_box = new Shop_BoxPacker_Box(
			$box->name,
			$this->convert_to_mm( $box->width ),
			$this->convert_to_mm( $box->length ),
			$this->convert_to_mm( $box->depth ),
			$this->convert_to_grams( $box->empty_weight ? $box->empty_weight : 0 ),
			$this->convert_to_mm( $box->inner_width ?  $box->inner_width : $box->width),
			$this->convert_to_mm( $box->inner_length ? $box->inner_length : $box->length ),
			$this->convert_to_mm( $box->inner_depth ? $box->inner_depth : $box->depth),
			$this->convert_to_grams( $box->max_weight )
		);

		$bp_box->box_id = $box->id;
		return $bp_box;
	}

	public function make_item_compat($item, $force = false){
		if(method_exists($item, 'om')) {
			$width  = $item->om( 'width' );
			$length = $item->om( 'depth' );
			$depth  = $item->om( 'height' ); //In this case item height is box depth
			$weight = $item->om( 'weight' );
		} else {
			$width  = $item->width;
			$length = $item->depth;
			$depth  = $item->height; //In this case item height is box depth
			$weight = $item->weight;
		}

		if(!$force && (!$width || !$length || !$depth)){
			return false; // cannot pack items with no given dimensions
		}

		$keep_flat = false;
		$result = Backend::$events->fireEvent('shop:onBoxPackerGetKeepFlat', $item); //return true if item should be packed upright
		foreach ($result as $true) {
			if ($true){
				$keep_flat = $true;
				break;
			}
		}
		$description = $item->om('sku').' | ';

		if(!is_a($item, 'Db_ActiverecordProxy')) {
			$description .= is_a( $item, 'Shop_ExtraOption' ) ? 'Extra Option: ' . $item->description : $item->om('name');
		}

		$bp_item = new Shop_BoxPacker_Item(
			$description,
			$this->convert_to_mm( $width ),
			$this->convert_to_mm( $length ),
			$this->convert_to_mm( $depth ),
			$this->convert_to_grams( $weight ),
			$keep_flat
		);

		//The following event can return an updated box packer item
		//This can be used to return a box packer item that applies additional constraints
		$result = Backend::$events->fireEvent('shop:onBoxPackerNewItem', $bp_item, $item);
		if($result) {
			foreach ( $result as $new_item ) {
				if ( $new_item instanceof BoxPackerItem ) {
					$bp_item = $new_item;
					break;
				}
			}
		}
        $item_id = (isset($item->key) && $item->key) ? $item->key : $item->id;;
		$bp_item->item_id = $item_id;
		return $bp_item;
	}

	public function get_unpackable_items(){
		return $this->unpackable_items;
	}

	public function convert_to_mm( $unit, $precision=0 ) {
		$unit = ($unit && is_numeric($unit)) ? $unit : 0;
		if(!$unit){
			return $unit;
		}
		if ( $this->dimensions_in_cm ) {
			return round( $unit * 10, $precision);
		}
		$inches = $unit;
		return round( $inches * 25.4, $precision);

	}

	public function convert_to_grams( $unit, $precision=0 ) {
		$unit = ($unit && is_numeric($unit)) ? $unit : 0;
		if(!$unit){
			return $unit;
		}
		if ( $this->weights_in_kg ) {
			return round( $unit * 1000, $precision );
		}
		$lbs = $unit;
		return round( $lbs * 453.592, $precision );
	}


	/**
	 * Returns a collection of packed boxes for a given Shop_Order
	 * This method allows you to calculate shipping dimensions and weight based on packing boxes available
	 * @documentable
	 * @param Shop_Order $order The order to pack
	 * @return PackedBoxList Returns collection of PackedBox.
	 */
	public static function pack_order($order){
		$packer   = new self();
		$calculated_packages = false;
		$items_shipping = $order->get_items_shipping();
		$info = array(
			'context' => 'order',
			'order' => $order
		);
		try {
			$calculated_packages = $packer->pack( $items_shipping, null, $info);
		} catch ( Exception $e ) {
			throw new Phpr_ApplicationException('No package calculations could be determined ('.$e->getMessage().')');
		}
		return $calculated_packages;
	}

	/**
	 * Returns total volume for a collection of Shop_OrderItem
	 * This method allows you to calculate shipping dimensions and weight based on packing boxes available
	 * @documentable
	 * @param array $items A collection of Shop_OrderItem
	 * @return float Volume
	 */
	public static function get_items_total_volume($items){
		$volume = 0;
		foreach ( $items as $item ) {
			$shipping_enabled = $item->product->product_type->shipping;
			if($shipping_enabled) {
				$volume += $item->total_volume();
			}
			$extras = false;
			if(isset($item->extra_options)){
				$extras = $item->extra_options;
			} else if(method_exists($item,'get_extra_option_objects')) {
				$extras = $item->get_extra_option_objects();
			}
			if($extras){
				foreach ($extras as $extra_item) {
					$volume += $extra_item->volume();
				}
			}
		}
		return $volume;
	}


}

/*
 * boxpacker classes use mm and grams.
 * This trait allow classes to return native kg/lbs cm/inches as set up in the system shipping options
 */
trait Shop_BoxPacker_NativeUnits {

	private $native_shipping_params = null;

	public function get_native_shipping_params(){
		if(empty($this->native_shipping_params)){
			return $this->native_shipping_params = Shop_ShippingParams::get();
		}
		return $this->native_shipping_params;
	}

	public function native_dimension($value){
		if($this->get_native_dimension_unit() == 'CM' ){
			return $this->convert_to_cm($value);
		}
		return $this->convert_to_lbs($value);
	}

	public function native_weight($value){
		if($this->get_native_weight_unit() == 'KGS' ){
			return $this->convert_to_kgs($value);
		}
		return $this->convert_to_inches($value);
	}

	public function get_native_dimension_unit(){
		return $this->get_native_shipping_params()->dimension_unit;
	}

	public function get_native_weight_unit(){
		return $this->get_native_shipping_params()->weight_unit;
	}

	public function convert_to_cm($unit, $precision=null){
		$val = $unit / 10;
		return $precision === null ? $val : round($val, $precision);
	}

	public function convert_to_inches($unit, $precision=null){
		$val = $unit * 0.0393701;
		return $precision === null ? $val : round($val, $precision);
	}

	public function convert_to_lbs($unit, $precision=null){
		$val = $unit * 0.00220462;
		return $precision === null ? $val : round($val, $precision);
	}

	public function convert_to_kgs($unit, $precision=null){
		$val = $unit / 1000;
		return $precision === null ? $val : round($val, $precision);
	}
}

class Shop_BoxPacker_Box implements BoxPackerBox
{
	use Shop_BoxPacker_NativeUnits;

	/**
	 * @var string
	 */
	public $box_id;

	/**
	 * @var string
	 */
	private $reference;

	/**
	 * @var int
	 */
	private $outerWidth;

	/**
	 * @var int
	 */
	private $outerLength;

	/**
	 * @var int
	 */
	private $outerDepth;

	/**
	 * @var int
	 */
	private $emptyWeight;

	/**
	 * @var int
	 */
	private $innerWidth;

	/**
	 * @var int
	 */
	private $innerLength;

	/**
	 * @var int
	 */
	private $innerDepth;

	/**
	 * @var int
	 */
	private $maxWeight;

	/**
	 * @var int
	 */
	private $innerVolume;

	/**
	 * @var int
	 */
	private $quantity;

	/**
	 * @var int
	 */
	private $default_quantity = 100000;


	/**
	 * TestBox constructor.
	 *
	 * @param string $reference
	 * @param int    $outerWidth
	 * @param int    $outerLength
	 * @param int    $outerDepth
	 * @param int    $emptyWeight
	 * @param int    $innerWidth
	 * @param int    $innerLength
	 * @param int    $innerDepth
	 * @param int    $maxWeight
	 */
	public function __construct(
		$reference,
		$outerWidth,
		$outerLength,
		$outerDepth,
		$emptyWeight,
		$innerWidth,
		$innerLength,
		$innerDepth,
		$maxWeight
	) {
		$this->reference = $reference;
		$this->outerWidth = $outerWidth;
		$this->outerLength = $outerLength;
		$this->outerDepth = $outerDepth;
		$this->emptyWeight = $emptyWeight;
		$this->innerWidth = $innerWidth;
		$this->innerLength = $innerLength;
		$this->innerDepth = $innerDepth;
		$this->maxWeight = $maxWeight;
		$this->innerVolume = $this->innerWidth * $this->innerLength * $this->innerDepth;
	}

	/**
	 * @return string
	 */
	public function getReference(): string
	{
		return $this->reference;
	}

	/**
	 * @return int
	 */
	public function getOuterWidth($native_units=false): int
	{
		if($native_units){
			return $this->native_dimension($this->outerWidth);
		}
		return $this->outerWidth;
	}

	/**
	 * @return int
	 */
	public function getOuterLength($native_units=false) : int
	{
		if($native_units){
			return $this->native_dimension($this->outerLength);
		}
		return $this->outerLength;
	}

	/**
	 * @return int
	 */
	public function getOuterDepth($native_units=false) : int
	{
		if($native_units){
			return $this->native_dimension($this->outerDepth);
		}
		return $this->outerDepth;
	}

	/**
	 * @return int
	 */
	public function getEmptyWeight($native_units=false) : int
	{
		if($native_units){
			return $this->get_native_dimension_unit($this->emptyWeight);
		}
		return $this->emptyWeight;
	}

	/**
	 * @return int
	 */
	public function getInnerWidth($native_units=false) : int
	{
		if($native_units){
			return $this->native_dimension($this->innerWidth);
		}
		return $this->innerWidth;
	}

	/**
	 * @return int
	 */
	public function getInnerLength($native_units=false) : int
	{
		if($native_units){
			return $this->native_dimension($this->innerLength);
		}
		return $this->innerLength;
	}

	/**
	 * @return int
	 */
	public function getInnerDepth($native_units=false) : int
	{
		if($native_units){
			return $this->native_dimension($this->innerDepth);
		}
		return $this->innerDepth;
	}

	/**
	 * @return int
	 */
	public function getInnerVolume() : int
	{
		return $this->innerVolume;
	}

	/**
	 * @return int
	 */
	public function getMaxWeight($native_units=false) : int
	{
		if($native_units){
			return $this->native_weight($this->maxWeight);
		}
		return $this->maxWeight;
	}

	public function setMaxWeight($weight, $native_units=false) : void
	{
		$this->maxWeight = $native_units ? $this->native_weight($weight) : $weight;
	}

	/**
	 * @return int
	 */
	public function getQuantityAvailable(): int
	{
		return is_numeric($this->quantity) ? $this->quantity : $this->default_quantity;
	}

	public function setQuantityAvailable(int $quantity): void
	{
		$this->quantity = $quantity;
	}

}

class Shop_BoxPacker_Item implements BoxPackerItem
{
	use Shop_BoxPacker_NativeUnits;

	/**
	 * @var string
	 */
	public $item_id;

	/**
	 * @var string
	 */
	private $description;

	/**
	 * @var int
	 */
	private $width;

	/**
	 * @var int
	 */
	private $length;

	/**
	 * @var int
	 */
	private $depth;

	/**
	 * @var int
	 */
	private $weight;

	/**
	 * @var int
	 * set to true if item should be packed 'right way up'
	 */
	private $keepFlat;

	/**
	 * @var int
	 */
	private $volume;

	/**
	 * TestItem constructor.
	 *
	 * @param string $description
	 * @param int    $width
	 * @param int    $length
	 * @param int    $depth
	 * @param int    $weight
	 * @param int    $keepFlat
	 */
	public function __construct($description, $width, $length, $depth, $weight, $keepFlat=false)
	{
		$this->description = $description;
		$this->width = $width;
		$this->length = $length;
		$this->depth = $depth;
		$this->weight = $weight;
		$this->keepFlat = $keepFlat;
		$this->volume = $this->width * $this->length * $this->depth;
	}

	/**
	 * @return string
	 */
	public function getDescription() : string
	{
		return $this->description;
	}

	/**
	 * @return int
	 */
	public function getWidth($native_units=false): int
	{
		if($native_units){
			return $this->native_dimension($this->width);
		}
		return $this->width;
	}

	/**
	 * @return int
	 */
	public function getLength($native_units=false): int
	{
		if($native_units){
			return $this->native_dimension($this->length);
		}
		return $this->length;
	}

	/**
	 * @return int
	 */
	public function getDepth($native_units=false): int
	{
		if($native_units){
			return $this->native_dimension($this->depth);
		}
		return $this->depth;
	}

	/**
	 * @return int
	 */
	public function getWeight($native_units=false): int
	{
		if($native_units){
			return $this->native_weight($this->weight);
		}
		return $this->weight;
	}

	/**
	 * @return int
	 */
	public function getVolume() : int
	{
		return $this->volume;
	}

	/**
	 * @return int
	 */
	public function getKeepFlat() : bool
	{
		return $this->keepFlat;
	}


}
