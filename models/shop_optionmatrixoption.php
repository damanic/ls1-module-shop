<?php

	class Shop_OptionMatrixOption extends Db_ActiveRecord
	{
		public $table_name = 'shop_option_matrix_options';
	
		public static function create()
		{
			return new self();
		}
	}

?>