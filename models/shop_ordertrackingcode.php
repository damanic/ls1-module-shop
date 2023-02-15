<?php

	class Shop_OrderTrackingCode extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_shipping_track_codes';

		public $belongs_to = array(
			'code_shipping_method'=>array('class_name'=>'Shop_ShippingOption', 'foreign_key'=>'shipping_method_id'),
            'shop_shipping_tracker_provider'=>array('class_name'=>'Shop_ShippingTrackerProvider', 'foreign_key'=>'shop_shipping_tracker_provider_id')
		);
		
		public function define_columns($context = null)
		{
			$this->define_relation_column('code_shipping_method', 'code_shipping_method', 'Shipping Method', db_varchar, '@name');
            $this->define_relation_column('shop_shipping_tracker_provider', 'shop_shipping_tracker_provider', 'Tracking Provider', db_varchar, '@name');
			$this->define_column('code', 'Tracking Code')->validation()->fn('trim')->required('Please enter the shipping tracking number.')->method('validateTrackingCode');
		}
		
		public function define_form_fields($context = null)
		{
            $this->add_form_field('shop_shipping_tracker_provider')->referenceSort('name');
			$this->add_form_field('code');
		}
		
		public static function create()
		{
			return new self();
		}

        /**
         * Get a shipping tracker provider that can return
         * a tracking URL and tracking events.
         * @return null|Shop_ShippingTracker
         */
        public function getShippingTrackerProvider(){
            $provider = null;
            if(!$provider && $this->shop_shipping_tracker_provider){
                $provider = $this->shop_shipping_tracker_provider;
            }
            if(!$provider && $this->code_shipping_method){
                $provider = $this->code_shipping_method->getShippingTrackerProvider();
            }
            return $provider;
        }

        /**
         * Returns a name for the shipping provider associated with the tracking code
         * @return string Name of Provider, or name of Shipping Method or 'Unknown'
         */
        public function getShippingTrackerProviderName(){
            $provider = $this->getShippingTrackerProvider();
            if(!$provider){
                if($this->code_shipping_method) {
                    return 'Shipping Method: ' . $this->code_shipping_method->name;
                }
                return 'Unknown';
            }
            return $provider->getName();
        }

        protected function validateTrackingCode($name, $value) {
            $pass = true;
            $bind = array(
              'code' => $value,
              'order_id' => $this->order_id
            );
            $exists  = Db_DbHelper::scalar('select count(*) from shop_order_shipping_track_codes where code=:code and order_id=:order_id', $bind);
            if($exists){
                $this->validation->setError("The tracking code ".$value." has already been added to order ID  ".$this->order_id, $name, true);
                $pass = false;
            }
            return $pass;
        }

        public function before_create($deferred_session_key = null)
        {
            if(!$this->order_id){
                throw new Phpr_ApplicationException('Tracking codes must be assigned to an order ID');
            }
        }


        public static function set_code($order, $shipping_method, $code)
		{
			self::remove_codes($order, $shipping_method);
			self::add_code($order,$shipping_method,$code);
		}

		public static function add_code($order, $shipping_method, $code){
			$obj = self::create();
			$obj->order_id = $order->id;
			$obj->shipping_method_id = $shipping_method->id;
			$obj->code = $code;
			$obj->save();
		}

		public static function remove_codes($order, $shipping_method, $code=null){
			$sql = 'delete from shop_order_shipping_track_codes where order_id=:order_id and shipping_method_id=:method_id';
			if($code){
				$sql .= ' and code=:code';
			}
			Db_DbHelper::query( $sql,
				array(
					'order_id'=>$order->id,
					'method_id'=>$shipping_method->id,
					'code' => $code
				)
			);
		}
		
		public static function find_by_order_and_method($order, $shipping_method)
		{
			$obj = self::create();
			$obj->where('order_id=?', $order->id);
			$obj->where('shipping_method_id=?', $shipping_method->id);
			
			return $obj->order('id DESC')->limit(1)->find();
		}
		
		public static function find_by_order($order)
		{
			$obj = self::create();
			$obj->where('order_id=?', $order->id);
			$obj->order('id');
			
			return $obj->find_all();
		}

}