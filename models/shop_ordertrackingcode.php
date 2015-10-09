<?php

	class Shop_OrderTrackingCode extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_shipping_track_codes';

		public $belongs_to = array(
			'code_shipping_method'=>array('class_name'=>'Shop_ShippingOption', 'foreign_key'=>'shipping_method_id')
		);
		
		public function define_columns($context = null)
		{
			$this->define_relation_column('code_shipping_method', 'code_shipping_method', 'Shipping Method', db_varchar, '@name');
			$this->define_column('code', 'Code')->validation()->fn('trim')->required('Please enter the shipping tracking number.');
		}
		
		public function define_form_fields($context = null)
		{
			$this->add_form_field('code_shipping_method')->referenceSort('name');
			$this->add_form_field('code');
		}
		
		public static function create()
		{
			return new self();
		}
		
		public static function set_code($order, $shipping_method, $code)
		{
			Db_DbHelper::query(
				'delete from shop_order_shipping_track_codes where order_id=:order_id and shipping_method_id=:method_id', 
				array(
					'order_id'=>$order->id,
					'method_id'=>$shipping_method->id
				)
			);

			$obj = self::create();
			$obj->order_id = $order->id;
			$obj->shipping_method_id = $shipping_method->id;
			$obj->code = $code;
			$obj->save();
		}
		
		public static function find_by_order_and_method($order, $shipping_method)
		{
			$obj = self::create();
			$obj->where('order_id=?', $order->id);
			$obj->where('shipping_method_id=?', $shipping_method->id);
			
			return $obj->find();
		}
		
		public static function find_by_order($order)
		{
			$obj = self::create();
			$obj->where('order_id=?', $order->id);
			$obj->order('id');
			
			return $obj->find_all();
		}
	}
	
?>