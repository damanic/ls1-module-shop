<?

	class Shop_InMemoryCartItem
	{
		public $product_id = null;
		public $options = array();
		public $extras = array();
		public $quantity = 0;
		public $key = null;
		public $postponed = false;
		public $cart_name = 'main';
		public $custom_data = array();
		public $uploaded_files = array();
		public $bundle_master_cart_key = null;			// Reference to a cart item representing the bundle master product
		public $bundle_master_item_id = null;			// Master bundle item (Shop_ProductBundleItem) identifier
		public $bundle_master_item_product_id = null;	// Master bundle item product (Shop_BundleItemProduct) identifier

		public function construct()
		{
		}

		public static function gen_item_key($product_id, $options, $extras, $custom_data, $uploaded_files)
		{
			$result = $product_id;

			foreach ($options as $key=>$value)
				$result .= $key.$value;

			foreach ($extras as $key=>$value)
				$result .= $key.$value;

			foreach ($custom_data as $key=>$value)
				$result .= $key.trim($value);

			try
			{
				if ($uploaded_files && $uploaded_files instanceof Db_DataCollection)
				{
					foreach ($uploaded_files as $file)
						$result .= $file->name.$file->size.md5_file(PATH_APP.$file->getPath());
				}
			}
			catch (exception $ex) {}

			return md5($result);
		}
		
		public static function gen_item_content_key($product_id, $options, $extras, $custom_data, $uploaded_files, $bundle_master_cart_key, $bundle_master_item_id, $bundle_master_item_product_id, $master_bundle_data, $quantity)
		{
			$result = $product_id;

			foreach ($options as $key=>$value)
				$result .= $key.$value;

			foreach ($extras as $key=>$value)
				$result .= $key.$value;

			foreach ($custom_data as $key=>$value)
				$result .= $key.trim($value);

			$result .= serialize($uploaded_files);

			$result .= $bundle_master_cart_key;
			$result .= $bundle_master_item_id;
			$result .= $bundle_master_item_product_id;

			$result .= serialize($master_bundle_data);
			
			if ($bundle_master_cart_key)
				$result .= (int)$quantity;

			return md5($result);
		}
		
		public function get_content_key(&$existing_items)
		{
			$master_bundle_data = array();
			
			foreach ($existing_items as $existing_item)
			{
				if ($existing_item->bundle_master_cart_key != $this->key || $existing_item->key == $this->key || !$existing_item->bundle_master_cart_key)
					continue;

				if (!array_key_exists($existing_item->bundle_master_item_id, $master_bundle_data))
					$master_bundle_data[$existing_item->bundle_master_item_id] = array();

				$master_bundle_data[$existing_item->bundle_master_item_id][] = array(
					'extra_options'=>$existing_item->extras,
					'options'=>$existing_item->options,
					'custom_data'=>$existing_item->custom_data,
					'quantity'=>(int)round($existing_item->quantity/$this->quantity),
					'product_id'=>$existing_item->product_id,
					'bundle_item_product_id'=>$existing_item->bundle_master_item_product_id
				);
			}
			
			return self::gen_item_content_key(
				$this->product_id, 
				$this->options, 
				$this->extras, 
				$this->custom_data, 
				$this->uploaded_files, 
				$this->bundle_master_cart_key, 
				$this->bundle_master_item_id,
				$this->bundle_master_item_product_id, 
				$master_bundle_data,
				$this->quantity
			);
		}
		
		/**
		 * Return a custom data field value. Custom field names should begin with the 'x_' prefix
		 * @param string $field_name Specifies a field name
		 * @param mixed $default Specifies a default field value
		 */
		public function get_data_field($field_name, $default_value = null)
		{
			if (!array_key_exists($field_name, $this->custom_data))
				return $default_value;
				
			return $this->custom_data[$field_name];
		}
		
		public function set_custom_data($values)
		{
			$this->custom_data = $values;
		}
		
		public function get_data_fields()
		{
			return $this->custom_data;
		}
		
		public function add_uploaded_file($db_file)
		{
			$file_info = array();
			$file_info['name'] = $db_file->name;
			$file_info['size'] = $db_file->size;
			$file_info['path'] = $db_file->getPath();

			$this->uploaded_files[] = $file_info;
		}
		
		public function list_uploaded_files()
		{
			return $this->uploaded_files;
		}
		
		public function is_bundle_item()
		{
			return $this->bundle_master_cart_key != null;
		}
		
		public function get_bundle_item()
		{
			if (!$this->is_bundle_item())
				return;

			return Shop_ProductBundleItem::find_by_id($this->bundle_master_item_id);
		}
		
		public function get_bundle_item_product()
		{
			if (!$this->is_bundle_item())
				return;

			return Shop_BundleItemProduct::find_by_id($this->bundle_master_item_product_id);
		}
		
		public function get_master_bundle_data(&$existing_items)
		{
			$result = array();
			
			foreach ($existing_items as $item)
			{
				if ($item->bundle_master_cart_key != $this->key)
					continue;
					
				if (!isset($result[$item->bundle_master_item_id]))
					$result[$item->bundle_master_item_id] = array();
					
				$result[$item->bundle_master_item_id][] = array(
					'extra_options'=>$item->extras,
					'options'=>$item->options,
					'custom_data'=>$item->custom_data,
					'quantity'=>(int)round($item->quantity / $this->quantity),
					'product_id'=>$item->product_id,
					'bundle_item_product_id'=>$item->bundle_master_item_product_id
				);
			}
			
			return $result;
		}
	}

?>