<?

	class Shop_GroupProductsModel extends Db_ActiveRecord
	{
		public $table_name = 'core_configuration_records';
		private $_products;
		
		public $custom_columns = array(
			'parent_product'=>db_number,
			'grouped_attribute_name'=>db_varchar,
			'grouped_attribute_values'=>db_text
		);

		public function define_columns($context = null)
		{
			$this->define_column('parent_product', 'Parent product')->validation()->required('Please select parent product.');
			$this->define_column('grouped_attribute_name', 'Attribute name')->validation()->fn('trim')->required('Please specify the attribute name.');
			$this->define_column('grouped_attribute_values', 'Product descriptions')->validation();
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('parent_product')->renderAs(frm_dropdown)->comment('Please select product to be a parent for other selected products.', 'above');
			$this->add_form_field('grouped_attribute_name')->comment('Provide a text label to be displayed near the grouped products drop-down menu, e.g. "Size".', 'above');
			$this->add_form_field('grouped_attribute_values')->comment('Please specify a description for a drop-down option list for each of the selected products, e.g. "Small size".', 'above')->renderAs(frm_grid)->gridColumns(array(
				'name'=>array('title'=>'Name', 'read_only'=>true), 
				'sku'=>array('title'=>'SKU', 'read_only'=>true), 
				'description'=>array('title'=>'Description')
			))->gridSettings(array('no_toolbar'=>true, 'allow_adding_rows'=>false, 'allow_deleting_rows'=>false, 'no_sorting'=>true, 'data_index_is_key'=>true));
		}
		
		public function get_parent_product_options()
		{
			$result = array();
			
			foreach ($this->_products as $product)
				$result[$product->id] = $product->name.' ('.$product->sku.')';
				
			return $result;
		}
		
		public function init($product_ids)
		{
			$this->grouped_attribute_values = array();
			$this->_products = array();
			foreach ($product_ids as $id)
			{
				$product = Shop_Product::create()->find($id);
				if ($product) {
					if (!$this->grouped_attribute_name)
						$this->grouped_attribute_name = $product->grouped_attribute_name;
					
					$this->_products[] = $product;

					$item = array(
						'name'=>$product->name,
						'sku'=>$product->sku,
						'description'=>$product->grouped_option_desc
					);
					$this->grouped_attribute_values[$product->id] = $item;
				}
			}
			
			$this->define_form_fields();
		}
		
		public function apply($data)
		{
			$this->define_form_fields();
			$this->validate_data($data);
			$products = array();

			foreach ($data['grouped_attribute_values'] as $product_id=>$product_data)
			{
				$description = trim($product_data['description']);
				if (!strlen($description))
				{
					$product = Shop_Product::create()->find($product_id);
					if ($product)
						$this->field_error(
							'grouped_attribute_values', 
							sprintf('Please specify description for product "%s" (%s).', h($product->name), h($product->sku)), 
							$product_id, 
							'description');
				}
				
				$product = Shop_Product::create()->find($product_id);
				if ($product)
				{
					if (
						$product_id != $data['parent_product'] 
						&& Db_DbHelper::scalar('select count(*) from shop_products where product_id is not null and product_id=:parent', array('parent'=>$product_id)))
						throw new Phpr_ApplicationException(
							sprintf('Product "%s" (%s) cannot be grouped, because it already contains other grouped products.', h($product->name), h($product->sku)));
					
					$products[] = array($product, $description);
				}
			}
			
			$parent_product = Shop_Product::create()->find($data['parent_product']);
			if (!$parent_product)
				throw new Phpr_ApplicationException('The parent product is not found');
			
			foreach ($products as $product_data)
			{
				$product = $product_data[0];
				$description = $product_data[1];

				$product->grouped_attribute_name = $this->validation->fieldValues['grouped_attribute_name'];
				$product->grouped_option_desc = $description;
				if (!$product->grouped_sort_order || $product->grouped_sort_order == -1)
					$product->grouped_sort_order = $product->id;

				if ($product->id != $data['parent_product'])
				{
					$product->product_id = $data['parent_product'];
					$product->grouped = 1;
					$product->product_type_id = $parent_product->product_type_id;
				}

				$product->save();
			}
		}
		
		protected function field_error($field, $message, $grid_row = null, $grid_column = null)
		{
			if ($grid_row != null)
			{
				$rule = $this->validation->getRule($field);
				if ($rule)
					$rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
			}
			
			$this->validation->setError($message, $field, true);
		}
	}

?>