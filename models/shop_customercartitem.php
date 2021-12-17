<?php

	class Shop_CustomerCartItem extends Db_ActiveRecord
	{
		public $table_name = 'shop_customer_cart_items';

		public $has_many = array(
			'uploaded_files'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_CustomerCartItem' and field='uploaded_files'", 'order'=>'id', 'delete'=>true)
		);
		
		public static function create()
		{
			return new self();
		}
		
		public function before_save($deferred_session_key = null)
		{
			if (is_array($this->options))
				$this->options = serialize($this->options);
			
			if (is_array($this->extras))
				$this->extras = serialize($this->extras);
		}

		public function after_save()
		{
			$this->after_fetch();
		}

		protected function after_fetch()
		{
			$this->options = strlen($this->options) ? unserialize($this->options) : array();
			$this->extras = strlen($this->extras) ? unserialize($this->extras) : array();
		}
		
		public function __get($name)
		{
			if ($name == 'key')
				return $this->item_key;
			
			return parent::__get($name);
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
				if ($uploaded_files && ($uploaded_files instanceof Db_DataCollection || is_array($uploaded_files)))
				{
					foreach ($uploaded_files as $file)
					{
						if ($file instanceof Db_File)
							$result .= $file->name.$file->size.md5_file(PATH_APP.$file->getPath());
						elseif (is_array($file))
						{
							$result .= $file['name'].$file['size'].md5_file(PATH_APP.$file['path']);
						}
					}
				}
			}
			catch (exception $ex){}

			return md5($result);
		}
		
		public static function gen_item_content_key($product_id, $options, $extras, $custom_data, $uploaded_files, $bundle_master_cart_key, $bundle_offer_id, $bundle_offer_item_id, $master_bundle_data, $quantity)
		{
			$result = $product_id;

			foreach ($options as $key=>$value)
				$result .= $key.$value;

			foreach ($extras as $key=>$value)
				$result .= $key.$value;

			foreach ($custom_data as $key=>$value)
			{
				if (strlen($value))
					$result .= $key.trim($value);
			}

			$result .= serialize($uploaded_files);

			$result .= $bundle_master_cart_key;
			$result .= $bundle_offer_id;
			$result .= $bundle_offer_item_id;

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
				if ($existing_item->bundle_master_cart_key != $this->item_key || $existing_item->item_key == $this->item_key || !$existing_item->bundle_master_cart_key)
					continue;

				if (!array_key_exists($existing_item->bundle_offer_id, $master_bundle_data))
					$master_bundle_data[$existing_item->bundle_offer_id] = array();

				$master_bundle_data[$existing_item->bundle_offer_id][] = array(
					'extra_options'=>$existing_item->extras,
					'options'=>$existing_item->options,
					'custom_data'=>$existing_item->get_data_fields(true),
					'quantity'=>(int)round($existing_item->quantity/$this->quantity),
					'product_id'=>$existing_item->product_id,
					'bundle_offer_item_id'=>$existing_item->bundle_offer_item_id
				);
			}
			
			$uploaded_files = Shop_Cart::uploaded_files_to_array($this->list_related_records_deferred('uploaded_files', $this->id.session_id()));
			
			return self::gen_item_content_key(
				$this->product_id, 
				$this->options, 
				$this->extras, 
				$this->get_data_fields(true), 
				$uploaded_files, 
				$this->bundle_master_cart_key, 
				$this->bundle_offer_id,
				$this->bundle_offer_item_id, 
				$master_bundle_data,
				$this->quantity
			);
		}
		
		public function mark_as_postponed($postponed)
		{
			$this->postponed = $postponed ? 1 : 0;
			Db_DbHelper::query('update shop_customer_cart_items set postponed=:postponed where id=:id', array(
				'postponed'=>$this->postponed,
				'id'=>$this->id
			));
		}
		
		/**
		 * Return a custom data field value. Custom field names should begin with the 'x_' prefix
		 * @param string $field_name Specifies a field name
		 * @param mixed $default Specifies a default field value
		 */
		public function get_data_field($field_name, $default_value = null)
		{
			if (!$this->has_column($field_name))
				return $default_value;

			return $this->$field_name;
		}
		
		public function get_data_fields($filter = false)
		{
			$result = array();

			$fields = $this->fields();
			foreach ($fields as $field_name=>$field_info)
			{
				if (preg_match('/^x_/', $field_name) && !($filter && !$this->$field_name))
					$result[$field_name] = $this->$field_name;
			}
			
			return $result;
		}
		
		public function add_uploaded_file($db_file)
		{
			if ($db_file instanceof Db_File)
				$file = $db_file->copy();
			else
			{
				$file = new Db_File();
				$file->fromFile(PATH_APP.$db_file['path']);
				$file->name = $db_file['name'];
			}
			
			$file->is_public = false;
			$file->master_object_class = get_class($this);
			$file->field = 'uploaded_files';
			$file->save();
			
			$this->uploaded_files->add($file, $this->id.session_id());
		}
		
		public function list_uploaded_files()
		{
			$result = array();

			$files = $this->list_related_records_deferred('uploaded_files', $this->id.session_id());
			foreach ($files as $db_file)
			{
				$file_info = array();
				
				$file_info['name'] = $db_file->name;
				$file_info['size'] = $db_file->size;
				$file_info['path'] = $db_file->getPath();

				$result[] = $file_info;
			}
			
			return $result;
		}
		
		public function is_bundle_item()
		{
			return $this->bundle_master_cart_key != null;
		}
		
		public function get_bundle_offer()
		{
			if (!$this->is_bundle_item())
				return;

			return Shop_ProductBundleOffer::find_by_id($this->bundle_offer_id);
		}

        public function get_bundle_offer_item(){
            if (!$this->is_bundle_item())
                return;

            return Shop_ProductBundleOfferItem::find_by_id($this->bundle_offer_item_id);
        }


        /**
         * @deprecated
         * @see get_bundle_offer_item()
         */
		public function get_bundle_item_product()
		{
			return $this->get_bundle_offer_item();
		}

        /**
         * @deprecated
         */
        public function get_bundle_item()
        {
            return $this->get_bundle_offer();
        }
	}

?>