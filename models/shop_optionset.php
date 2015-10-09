<?php

	class Shop_OptionSet extends Db_ActiveRecord
	{
		public $table_name = 'shop_option_sets';

		public $custom_columns = array('existing_id');

		public $has_many = array(
			'options'=>array('class_name'=>'Shop_OptionSetOption', 'foreign_key'=>'option_set_id', 'order'=>'id', 'delete'=>true)
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->unique('Option set with name "%s" already exists.');
		}

		public function define_form_fields($context = null)
		{
			if ($context != 'load')
			{
				$this->add_form_field('name')->comment('Specify a name to create a new set, or select existing set to override it.', 'above')->size('small');
				$this->define_column('existing_id', 'Existing Option Set')->validation();
				$this->add_form_field('existing_id')->renderAs('existing_optionset')->cssClassName('reference dropdown');
			} else
			{
				$this->define_column('existing_id', 'Option Set')->validation();
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
		
		public function copyOptions($options)
		{
			Db_DbHelper::query("delete from shop_option_set_options where option_set_id=:id", array('id'=>$this->id));
			$this->options->clear();

			foreach ($options as $option)
				$this->options->add(Shop_OptionSetOption::create()->copy_from_option($option));

			return $this;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
	}

?>