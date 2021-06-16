<?php
class Shop_PackedBox {

	protected $box = null;
	protected $items = array();
	protected $native_dimension_unit = null;
	protected $native_weight_unit = null;
	protected $weight = null;

	public function __construct($box, $shop_items=array()){
		if(!is_a($box, 'Shop_ShippingBox')){
			throw new Phpr_ApplicationException('Invalid Box Given, must be instance of Shop_ShippingBox');
		}
		$this->box = $box;
		if(count($shop_items)) {
			foreach ( $shop_items as $item ) {
				if ( !Db_ActiverecordProxy::is_a( $item, 'Shop_OrderItem' ) && !Db_ActiverecordProxy::is_a( $item, 'Shop_CartItem' ) ) {
					throw new Phpr_ApplicationException( 'Invalid Item given, must be instance of Shop_CartItem or Shop_OrderItem' );
				}
				$this->add_item($item, $item->quantity);
			}
		}
		$this->load_native_units();
	}

	public function set_weight($weight){
		$this->weight = $weight;
	}

	public function get_weight($unit='native'){
		if(!$this->weight){
			$this->weight = $this->get_calculated_weight();
		}

		if($unit == 'native'){
			return $this->weight;
		}
		return $this->convert_weight_unit($this->weight, $unit);
	}

	public function get_calculated_weight(){
		$weight = $this->box->empty_weight ? $this->box->empty_weight : 0;
		$weight += $this->get_item_weight();
		return $weight;
	}

	protected function get_item_weight(){
		$weight = 0;
		foreach ($this->items as $item){
			$weight += $item->total_weight();
		}
		return $weight;
	}

	public function add_item($item, $quantity=1){
		$packed_item = clone $item;
		$packed_item->quantity = $quantity;
		$this->items[] = $packed_item;
	}

	public function get_items(){
		return $this->items;
	}

	public function get_items_count(){
		$count = 0;
		foreach($this->get_items() as $item){
			$count += $item->quantity;
		}
		return $count;
	}

	public function get_box(){
		return $this->box;
	}

	public function get_length($unit='native'){
		$length = $this->box->length ? $this->box->length  : 0;
		if($unit == 'native'){
			return $length;
		}
		return $this->convert_dimension_unit($length, $unit);
	}

	public function get_width($unit='native'){
		$width = $this->box->width ? $this->box->width  : 0;
		if($unit == 'native'){
			return $width;
		}
		return $this->convert_dimension_unit($width, $unit);
	}

	public function get_depth($unit='native'){
		$depth = $this->box->depth ? $this->box->depth  : 0;
		if($unit == 'native'){
			return $depth;
		}
		return $this->convert_dimension_unit($depth, $unit);
	}

	public function get_native_dimension_unit(){
		if(!$this->native_dimension_unit){
			$this->load_native_units();
		}
		return $this->native_dimension_unit;
	}

	public function get_native_weight_unit(){
		if(!$this->native_weight_unit){
			$this->load_native_units();
		}
		return $this->native_weight_unit;
	}

	protected function load_native_units(){
		$params = Shop_ShippingParams::get();
		$this->native_dimension_unit = $params->dimension_unit;
		$this->native_weight_unit = $params->weight_unit;
	}

	protected function convert_dimension_unit($value, $unit){

		if($this->native_dimension_unit == 'IN' ){
			$value = $value * 2.54; //convert to CM
		}

		$unit = strtolower($unit);
		switch($unit){
			case 'mm':
				return round($value * 10, 2);
			case 'cm':
				return round($value, 2);
			case 'inches':
				return round($value * 0.393701, 2);
			default:
				throw new Phpr_ApplicationException('Invalid dimension unit given');
		}
	}

	protected function convert_weight_unit($value, $unit){
		$unit = strtolower($unit);
		if($this->native_weight_unit == 'LB' ){
			$value = $value * 0.453592; //convert to KG
		}
		switch($unit){
			case 'grams':
				return round($value * 1000);
			case 'kg':
				return round($value, 3);
			case 'lb':
				return round($value * 2.20462, 6);
			case 'oz':
				return round($value * 35.274, 2);
			default:
				throw new Phpr_ApplicationException('Invalid weight unit given');
		}
	}

	/**
	 * This uses the Shop_BoxPacker class to place order items into packed boxes
	 * Requires PHP v5.4+
	 * @documentable
	 * @param Shop_Order $order Specifies the order to calculate
	 * @return array of Shop_PackedBox objects
	 */
	public static function calculate_order_packed_boxes( $order ){
		$order_packed_boxes = array();
		$packages = Shop_BoxPacker::pack_order($order);
		if(!$packages){
			throw new Phpr_ApplicationException('Could not calculate boxes');
		}
		return self::_convert_boxpacker_packages($order->items, $packages);
	}

	/**
	 * This uses the Shop_BoxPacker class to place order items into packed boxes
	 * Requires PHP v5.4+
	 * @documentable
	 * @param array $items  Array of Shop_CartItem or Shop_OrderItem
	 * @param array $info Supporting information that could influence packing constraints. Passed to shop:onBoxPackerPack event
	 * @return array of Shop_PackedBox objects
	 */
	public static function calculate_item_packed_boxes($items, $info = array()){
		$context = null;
		$item_packed_boxes = array();
		foreach($items as $item){
			$context = is_a($item,'Shop_CartItem') ? 'cart' : 'order';
			break;
		}
		$default_info = array(
			'context' => $context
		);
		$info = array_merge($default_info,$info);
		try {
			$packer   = new Shop_BoxPacker();
			$packages = $packer->pack( $items, null , $info );
			$item_packed_boxes = self::_convert_boxpacker_packages($items, $packages);
		} catch ( Exception $e ) {
			traceLog( $e->getMessage() );
		}
		return $item_packed_boxes;
	}

	private static function _convert_boxpacker_packages($items, $packages){
		$packed_boxes = array();
		if($packages){
			$shipping_params  = Shop_ShippingParams::get();
			$shipping_boxes = $shipping_params->shipping_boxes;

			$shipping_boxes_indexed = array();
			foreach($shipping_boxes as $shipping_box){
				$shipping_boxes_indexed[$shipping_box->id] = $shipping_box;
			}

			$items_indexed = array();
			foreach($items as $item){
				$id = property_exists($item, 'id') ? $item->id : $item->key;
				$items_indexed[$id] = $item;
			}

			foreach($packages as $package){

				$packer_box = $package->getBox();
				if($packer_box->box_id && isset($shipping_boxes_indexed[$packer_box->box_id])){
					$shipping_box = $shipping_boxes_indexed[$packer_box->box_id];
				} else {
					$shipping_box = Shop_ShippingBox::create();
					$shipping_box->id = $packer_box->box_id;
					$shipping_box->length = $packer_box->getOuterLength(true);
					$shipping_box->width = $packer_box->getOuterLength(true);
					$shipping_box->depth = $packer_box->getOuterLength(true);
					$shipping_box->max_weight = $packer_box->getMaxWeight(true);
				}

				$packed_box = new self($shipping_box,array());

				$shop_items = array();
				$packer_packeditems = $package->getItems();
				$packed_item_counts = array();
				foreach( $packer_packeditems as $packer_packeditem){
					$packer_item = $packer_packeditem->getItem();
					if($packer_item->item_id && isset($items_indexed[$packer_item->item_id])){
						if(isset($packed_item_counts[$packer_item->item_id])){
							$packed_item_counts[$packer_item->item_id]++;
						} else{
							$packed_item_counts[$packer_item->item_id] = 1;
						}
					}
				}

				foreach($packed_item_counts as $item_id => $quantity){
					$shop_item = $items_indexed[$item_id];
					$packed_box->add_item($shop_item, $quantity);
				}

				$packed_boxes[] = $packed_box;
			}
		}

		return $packed_boxes;
	}


}