<?php

	class Shop_PropertySetProperty extends Db_ActiveRecord
	{
		public $table_name = 'shop_property_set_properties';

		public static function create()
		{
			return new self();
		}

		public function copy_from_property($property)
		{
			$this->name = $property->name;
			$this->value = null;
			$this->save();
			return $this;
		}
	}

?>