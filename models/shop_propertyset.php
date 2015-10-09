<?php

	class Shop_PropertySet extends Db_ActiveRecord
	{
		public $table_name = 'shop_property_sets';

		public $custom_columns = array('existing_id');

		public $has_many = array(
			'properties'=>array('class_name'=>'Shop_PropertySetProperty', 'foreign_key'=>'property_set_id', 'order'=>'id', 'delete'=>true)
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->unique('Property set with name "%s" already exists.');
		}

		public function define_form_fields($context = null)
		{
			if ($context != 'load')
			{
				$this->add_form_field('name')->comment('Specify a name to create a new set, or select existing set to override it.', 'above')->size('small');
				$this->define_column('existing_id', 'Existing Property Set')->validation();
				$this->add_form_field('existing_id')->renderAs('existing_propertyset');
			} else
			{
				$this->define_column('existing_id', 'Property Set')->validation();
				$this->add_form_field('existing_id')->renderAs(frm_dropdown)->comment('Please select a property set to load properties from.', 'above');
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
		
		public function copyProperties($properties)
		{
			Db_DbHelper::query("delete from shop_property_set_properties where property_set_id=:id", array('id'=>$this->id));
			$this->properties->clear();

			foreach ($properties as $property)
				$this->properties->add(Shop_PropertySetProperty::create()->copy_from_property($property));

			return $this;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
	}

?>