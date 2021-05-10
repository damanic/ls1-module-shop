<?php

	class Shop_PropertySetProperty extends Db_ActiveRecord
	{
		public $table_name = 'shop_property_set_properties';

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('value', 'Default Value')->order('asc')->validation()->fn('trim');
			$this->define_column('comment', 'Comment')->order('asc')->validation()->fn('trim');
			$this->define_column('validate', 'Validate')->order('asc')->validation()->fn('trim');
			$this->define_column('select_values', 'Select Values')->validation()->fn('trim');
			$this->define_column('api_code', 'API Code')->order('asc')->validation()->fn('trim')->unique('API CODE "%s" already exists.');
			$this->define_column('required', 'Required Field');
		}

		public function define_form_fields($context = null) {
			$this->add_form_field('name','left')->size('small');
			$this->add_form_field('comment','right')->size('small');
			$this->add_form_field('value','left')->comment('If this property should always be presented with a default value, enter it here')->size('small');
			$this->add_form_field('validate','right')->renderAs(frm_dropdown)->emptyOption('<none>')->comment('Select a validation rule, if applicable')->size('small');
			$this->add_form_field('required','right')->comment('Tick if a value for this property is always required', 'above')->size('small');
			$this->add_form_field('select_values')->renderAs(frm_text)->comment('If the property value should be limited to a select list, enter the values separated by the | character. Eg: Option 1 | Option 2 | Option 3 ', 'above');
			$this->add_form_field('api_code')->comment('Specify a unique API code for this property', 'above')->size('small');
		}


		public static function create() {
			return new self();

		}

		public function get_validate_options($key_value=1)
		{
			$options = array(
				'db_number' => 'Number',
				'db_float' => 'Decimal Number',
				'db_date' => 'Date',
			);
			return $options;
		}

		public function copy_from_property($property)
		{
			$this->name = $property->name;
			$this->value = null;
			$this->save();
			return $this;
		}

		public function before_save($deferred_session_key = null)
		{
			if($this->validate){

					$validation = new Phpr_Validation();
					$validate_default_value = $validation->add( 'value', 'Default Value' );

					switch ( $this->validate ) {
						case 'db_number':
							$validate_default_value->numeric('All values must be numeric');
							break;
						case 'db_float':
							$validate_default_value->float('%x', 'Value must be a valid number (decimal)');
							break;
						case 'db_date':
							$validate_default_value->date('%x', 'All values must be a valid date format (eg. '.Phpr_DateTime::now()->format('%x').')');
							break;
						default:
							break;
					}

					//validate default value
					if($this->value) {
						if ( !$validation->validate( $this ) ) {
							$validation->throwException();
						}
					}

					//validate select values
					if($this->select_values) {
						$select_values_array = $this->get_select_values();
						$unique[] = array();
						foreach($select_values_array as $select_value){
							if(isset($unique[$select_value])){
								$validation->errorMessage = 'All select values must be unique';
								$validation->throwException();
							}
							$unique[$select_value] = $select_value;
							if($select_value){
								$field_data = array('value' => $select_value);
								if ( !$validation->validate( $field_data ) ) {
									$validation->throwException();
								}
							}
						}
					}
				}

		}


		public function get_select_values(){
			$values = null;
			if( !empty($this->select_values)) {
				$values = explode( '|', $this->select_values );
			}
			return is_array($values) ? $values : array();
		}

		public function get_property_set(){
			if($this->property_set_id){
				return Shop_PropertySet::create()->find($this->property_set_id);
			}
			return null;
		}

		public function validate_property_value($value){
			$validation = new Phpr_Validation();
			$validate_value = $validation->add( 'value', 'Value' );

			if($this->required) {
				$validate_value->required();
			} else if(empty($value)){
				return;
			}

			switch ( $this->validate ) {
				case 'db_number':
					$validate_value->numeric('Value must be a whole number');
					break;
				case 'db_float':
					$validate_value->float('%x', 'Value must be a valid number (decimal)');
					break;
				case 'db_date':
					$validate_value->date('%x', 'Value must be a valid date format (eg. '.Phpr_DateTime::now()->format('%x').')');
					break;
				default:
					break;
			}

			//validate default value
			if ( !$validation->validate( array('value' => $value) ) ) {
				$validation->throwException();
			}

			//validate select values
			if($this->select_values) {
				$value_is_present = false;
				$select_values_array = $this->get_select_values();
				if($select_values_array) {
					foreach ( $select_values_array as $select_value ) {
						if ( $select_value == $value ) {
							$value_is_present = true;
							break;
						}
					}
					if(!$value_is_present) {
						$validation->errorMessage = 'The value given is not from a valid selection';
						$validation->throwException();
					}
				}

			}
		}
	}

?>