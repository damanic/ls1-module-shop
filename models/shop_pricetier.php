<?php

	class Shop_PriceTier extends Db_ActiveRecord
	{
		public $table_name = 'shop_tier_prices';
		protected static $cache = array();

		public $belongs_to = array(
			'customer_group'=>array('class_name'=>'Shop_CustomerGroup', 'foreign_key'=>'customer_group_id')
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_relation_column('customer_group', 'customer_group', 'Customer Group ', db_varchar, '@name');
			$this->define_column('quantity', 'Quantity')->validation()->fn('trim')->required('Please specify quantity')->method('validate_quantity');
			$this->define_column('price', 'Price')->validation()->fn('trim')->required('Please specify price');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('customer_group')->emptyOption('Any customer')->referenceSort('name');
			$this->add_form_field('quantity')->comment('A minimum quantity of ordered items, starting from which the price should be applied.', 'above');
			$this->add_form_field('price');
		}
		
		public function copy()
		{
			$obj = self::create();
			$obj->customer_group_id = $this->customer_group_id;
			$obj->quantity = $this->quantity;
			$obj->price = $this->price;

			return $obj;
		}
		
		public function validate_quantity($name, $value)
		{
			if ($value < 1)
				$this->validation->setError('Quantity cannot be less than 1', $name, true);
				
			return true;
		}
		
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}
	}

?>