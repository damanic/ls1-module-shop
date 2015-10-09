<?php

	class Shop_OptionSetOption extends Db_ActiveRecord
	{
		public $table_name = 'shop_option_set_options';

		public static function create()
		{
			return new self();
		}

		public function copy_from_option($option)
		{
			$this->name = $option->name;
			$this->attribute_values = $option->attribute_values;
			$this->option_key = $option->option_key;
			$this->sort_order = $option->sort_order;
			$this->save();
			return $this;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
	}

?>