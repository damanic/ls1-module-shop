<?php

	class Shop_OrderShippingLabelParams extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_shipping_label_params';
		protected $parameters = false;
		
		public static function create()
		{
			return new self();
		}
		
		public static function find_by_order_and_method($order, $shipping_method)
		{
			$obj = self::create();
			$obj->where('order_id=?', $order->id);
			$obj->where('shipping_method_id=?', $shipping_method->id);
			
			return $obj->find();
		}
		
		public function get_parameters()
		{
			if ($this->parameters !== false)
				return $this->parameters;
			
			if (!strlen($this->xml_data))
				return $this->parameters = array();
				
			$result = array();
			$object = new SimpleXMLElement($this->xml_data);
			foreach ($object->children() as $child)
			{
				$code = (array)($child->id);
				$code = $code[0];
				$result[$code] = unserialize($child->value);
			}

			return $this->parameters = $result;
		}
		
		public function get_parameter($name)
		{
			$parameters = $this->get_parameters();
			if (array_key_exists($name, $parameters))
				return $parameters[$name];
				
			return null;
		}
		
		public static function get_recent_order_params($shipping_method)
		{
			$obj = self::create();
			$obj->where('shipping_method_id=?', $shipping_method->id);
			$obj->order('updated_at desc');
			$obj->limit(1);
			return $obj->find();
		}
		
		public static function save_parameters($order, $shipping_method, $parameters)
		{
			$obj = self::find_by_order_and_method($order, $shipping_method);
			if (!$obj)
			{
				$obj = self::create();
				$obj->shipping_method_id = $shipping_method->id;
				$obj->order_id = $order->id;
			}
			
			$document = new SimpleXMLElement('<shipping_label_settings></shipping_label_settings>');
			foreach ($parameters as $code=>$value)
			{
				$field_element = $document->addChild('field');
				$field_element->addChild('id', $code);
				$field_element->addChild('value', serialize($value));
			}

			$obj->xml_data = $document->asXML();
			$obj->save();
		}
	}

?>