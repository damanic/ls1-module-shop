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
		$this->items = $shop_items;

		$ship_params = Shop_ShippingParams::get();
		$this->native_dimension_unit = $ship_params->dimension_unit;
		$this->native_weight_unit = $ship_params->weight_unit;
	}

	public function set_weight($weight){
		$this->weight = $weight;
	}

	public function get_weight($unit='native'){
		$weight = 0;
		if($this->weight){
			$weight = $this->weight;
		} else {
			$weight = $this->box->empty_weight ? $this->box->empty_weight : 0;
			$weight += $this->get_item_weight();
		}

		if($unit == 'native'){
			return $weight;
		}
		return $this->convert_weight_unit($weight, $unit);
	}

	protected function get_item_weight(){
		$weight = 0;
		foreach ($this->items as $item){
			$weight += $item->total_weight();
		}
		return $weight;
	}

	public function get_items(){
		return $this->items;
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
		$shipping_params  = Shop_ShippingParams::get();
		$shipping_boxes = $shipping_params->shipping_boxes;
		$shipping_boxes_indexed = array();
		foreach($shipping_boxes as $shipping_box){
			$shipping_boxes_indexed[$shipping_box->id] = $shipping_box;
		}

		$order_items_indexed = array();
		foreach($order->items as $order_item){
			$order_items_indexed[$order_item->id] = $order_item;
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

			$shop_items = array();
			$packer_items = $package->getItems();
			foreach($packer_items as $packer_item){
				if($packer_item->item_id && isset($order_items_indexed[$packer_item->item_id])){
					$shop_item = $order_items_indexed[$packer_item->item_id];
					$shop_item->quantity = 1;
					$shop_items[] = $shop_item;
				}
			}
			$packed_box = new self($shipping_box,$shop_items);
			$box_weight = $packer_box->native_weight($packer_box->getEmptyWeight());
			if($box_weight){
				$packed_box->set_weight($packed_box->get_weight() + $box_weight);
			}
			$order_packed_boxes[] = $packed_box;
		}

		return $order_packed_boxes;
	}

}