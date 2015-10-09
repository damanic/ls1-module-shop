<?php

	class Shop_Coupon extends Db_ActiveRecord
	{
		public $table_name = 'shop_coupons';
		protected static $coupon_usage_map = null;
		
		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('code', 'Coupon Code')->order('asc')->validation()->fn('trim')->required("Please specify a coupon code")->fn('mb_strtolower')->unique("A coupon with the specified code already exists. Please specify another code, or use the existing coupon.");
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('code');
		}

		public function before_delete($id=null)
		{
			$in_use = Db_DbHelper::scalar('select count(*) from shop_cart_rules where coupon_id=:id', array('id'=>$this->id));
			if ($in_use)
				throw new Phpr_ApplicationException("The coupon cannot be deleted because it is used in $in_use shopping cart price rule(s).");
		}
		
		public static function find_coupon($coupon_code)
		{
			$coupon_code = trim(mb_strtolower($coupon_code));
			return self::create()->where('code=?', $coupon_code)->find();
		}
		
		/** 
		 * Returns a number of orders a coupon was used in
		 */
		public static function get_order_number($coupon_code)
		{
			$coupon_code = trim(mb_strtolower($coupon_code));
			
			if (self::$coupon_usage_map === null)
			{
				self::$coupon_usage_map = array();
				$coupon_usage = Db_DbHelper::objectArray('select shop_coupons.code as code, count(*) as cnt from shop_coupons, shop_orders where shop_coupons.id=shop_orders.coupon_id group by shop_orders.coupon_id');

				foreach ($coupon_usage as $coupon)
					self::$coupon_usage_map[$coupon->code] = $coupon->cnt;
			}

			if (!array_key_exists($coupon_code, self::$coupon_usage_map))
				return 0;
				
			return self::$coupon_usage_map[$coupon_code];
		}
	}

?>