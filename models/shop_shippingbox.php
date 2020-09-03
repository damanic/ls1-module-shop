<?php
class Shop_ShippingBox extends Db_ActiveRecord {
	public $table_name = 'shop_shipping_boxes';

	public static function create() {
		return new self();
	}

	public function define_columns( $context = null ) {
		$this->define_column( 'name', 'Name' )->validation()->fn( 'trim' )->required( "Please specify a name" );
		$this->define_column( 'width', 'Width' )->validation()->fn( 'trim' )->required( "Please specify the box width" );
		$this->define_column( 'length', 'Length' )->validation()->fn( 'trim' )->required( "Please specify the box length" );
		$this->define_column( 'depth', 'Depth' )->validation()->fn( 'trim' )->required( "Please specify the box depth" );
		$this->define_column( 'empty_weight', 'Empty Weight' );
		$this->define_column( 'max_weight', 'Max Weight' )->validation()->fn( 'trim' )->required( "Please specify the maximum weight this box can carry" );
		$this->define_column( 'inner_width', 'Inner Width' );
		$this->define_column( 'inner_length', 'Inner Length' );
		$this->define_column( 'inner_depth', 'Inner Depth' );
		$this->define_column( 'volume', 'Volume' );
	}

	public function define_form_fields( $context = null ) {
		$this->add_form_field( 'name' );
		$this->add_form_field( 'width','left' );
		$this->add_form_field( 'length','left'  );
		$this->add_form_field( 'depth','left'  );
		$this->add_form_field( 'max_weight','left' );
		$this->add_form_field( 'empty_weight','right');
		$this->add_form_field( 'inner_width','left'  );
		$this->add_form_field( 'inner_length','left'  );
		$this->add_form_field( 'inner_depth','left'  );
	}

	public function before_save($deferred_session_key = null) {
		if($this->inner_width && $this->inner_length && $this->inner_depth){
			$volume = ($this->inner_width * $this->inner_length * $this->inner_depth);
		} else {
			$volume = ($this->width * $this->length * $this->depth);
		}
		$this->volume = round($volume,2);
	}

	public static function get_closest_by_volume($volume){
		$box = self::create()->where('volume >= ?', $volume)->order('volume ASC')->limit(1);
		if($box && $box->id){
			return $box;
		}
		return false;
	}

	public static function get_boxes(){
		return self::create()->find_all();
	}


}