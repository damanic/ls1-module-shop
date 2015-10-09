<?php

	class Shop_ExtraOptionSet extends Db_ActiveRecord
	{
		public $table_name = 'shop_extra_option_sets';

		public $custom_columns = array('existing_id');

		public $has_many = array(
			'extra_options'=>array('class_name'=>'Shop_ExtraOption', 'foreign_key'=>'product_id', 'order'=>'extra_option_sort_order', 'delete'=>true, 'conditions'=>'option_in_set=1')
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->unique('Extra option set with name "%s" already exists.')->required();
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('code', 'API Code')->defaultInvisible()->validation()->fn('trim')->fn('mb_strtolower')->unique('Extra option set with the specified  API code already exists.');
			
			$this->define_multi_relation_column('extra_options', 'extra_options', 'Options', "@description")->invisible();
		}

		public function define_form_fields($context = null)
		{
			if ($context != 'load')
			{
				$this->add_form_field('name')->tab('Set Parameters');
				$this->add_form_field('description')->size('small')->tab('Set Parameters');
				$this->add_form_field('code')->comment('You can use the API Code for referring the set in the product CSV spreadsheets.', 'above')->tab('Set Parameters');
				$this->add_form_field('extra_options')->tab('Options');
				
			} else
			{
				$this->define_column('existing_id', 'Extra Option Set')->validation();
				$this->add_form_field('existing_id')->renderAs(frm_dropdown)->comment('Please select an option set to load options from.', 'above');
			}
		}
		
		public function get_existing_id_options()
		{
			$sets = self::create()->order('name')->find_all();
			$result = array();
			$result[''] = '<select>';

			foreach ($sets as $set)
				$result[$set->id] = $set->name;
				
			return $result;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
	}

?>