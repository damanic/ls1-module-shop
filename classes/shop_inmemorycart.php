<?

	class Shop_InMemoryCart extends Shop_CartBase
	{
		public function add_item($product, $options, $extra_options, $quantity, $cart_name, $custom_data = array(), $uploaded_files = null, $bundle_data = array(), $master_bundle_data = array())
		{
			$items = $this->load_items($cart_name);
			$key = md5(uniqid(count($items)+1+microtime(true)));
			
			$item = new Shop_InMemoryCartItem();
			$item->product_id = $product->id;
			$item->options = $options;
			$item->extras = $extra_options;
			$item->quantity = $quantity;
			$item->key = $key;
			$item->cart_name = $cart_name;
			
			$item->cart_name = $cart_name;

			if (array_key_exists('bundle_master_cart_key', $bundle_data))
			{
				$item->bundle_master_cart_key = $bundle_data['bundle_master_cart_key'];
				$item->bundle_offer_id = $bundle_data['bundle_offer_id'];
				$item->bundle_offer_item_id = $bundle_data['bundle_offer_item_id'];
			}
			
			$item->set_custom_data($custom_data);
			$items[$key] = $item;

			$this->save_items($items, $cart_name);
			
			if ($uploaded_files && $uploaded_files instanceof Db_DataCollection)
				self::add_uploaded_files($item->key, $uploaded_files, $cart_name);
			
			return $item;
		}
		
		public function get_item_total_num($cart_name, $count_bundle_items = true)
		{
			$result = 0;
			$items = $this->load_items($cart_name);
			foreach ($items as $item)
			{
				if (!$item->postponed && !(!$count_bundle_items && $item->is_bundle_item()))
					$result += $item->quantity;
			}

			return $result;
		}
		
		public function list_items($cart_name)
		{
			return $this->load_items($cart_name);
		}
		
		public function remove_item($key, $cart_name)
		{
			$items = $this->load_items($cart_name);
			if (array_key_exists($key, $items))
			{
				unset($items[$key]);
				$this->save_items($items, $cart_name);
			}
		}
		
		public function set_quantity($key, $value, $cart_name)
		{
			$items = $this->load_items($cart_name);
			if (array_key_exists($key, $items))
			{
				if ($value > 0)
					$items[$key]->quantity = $value;
				else
					unset($items[$key]);

				$this->save_items($items, $cart_name);
				
				return true;
			}
			
			return false;
		}
		
		public function set_custom_data($key, $values, $cart_name)
		{
			$items = $this->load_items($cart_name);
			if (array_key_exists($key, $items))
			{
				$items[$key]->set_custom_data($values);
				$this->save_items($items, $cart_name);
				
				return true;
			}
			
			return false;
		}
		
		public function add_uploaded_files($key, $files, $cart_name)
		{
			$items = $this->load_items($cart_name);
			if (array_key_exists($key, $items))
			{
				foreach ($files as $file)
					$items[$key]->add_uploaded_file($file);

				$this->save_items($items, $cart_name);
				return true;
			}
			
			return false;
		}

		public function change_postpone_status($values, $cart_name)
		{
			$result = false;
			
			$items = $this->load_items($cart_name);
			foreach ($values as $key=>$value)
			{
				if (array_key_exists($key, $items))
				{
					if ($items[$key]->postponed != $value)
					{
						$result = true;
						$items[$key]->postponed = $value;
					}
				}
			}
			$this->save_items($items, $cart_name);

			return $result;
		}

		public function empty_cart($cart_name)
		{
			$items = array();
			$this->save_items($items, $cart_name);
		}
		
		public function list_cart_names()
		{
			$items = Phpr::$session->get('shop_in_memory_cart_items', array());
			return array_keys($items);
		}

		protected function load_items($cart_name)
		{
			$items = Phpr::$session->get('shop_in_memory_cart_items', array());

			if (!array_key_exists($cart_name, $items))
				return array();

			return $items[$cart_name];
		}
		
		protected function save_items(&$items, $cart_name)
		{
			$cart_items = Phpr::$session->get('shop_in_memory_cart_items', array());
			$cart_items[$cart_name] = $items;

			Phpr::$session['shop_in_memory_cart_items'] = $cart_items;
		}
		
		public function find_matching_item($cart_name, $product_id, $options, $extras, $custom_data, $uploaded_files, $bundle_data, $master_bundle_data,  $quantity)
		{
			/*
			 * Do not merge bundle item products
			 */
			if (isset($bundle_data['bundle_master_cart_key']))
				return null;

			$uploaded_files_array = Shop_Cart::uploaded_files_to_array($uploaded_files);

			$new_item_content_key = Shop_InMemoryCartItem::gen_item_content_key(
				$product_id, 
				$options, 
				$extras, 
				$custom_data, 
				$uploaded_files_array, 
				null, 
				null, 
				null,
				$master_bundle_data,
				$quantity);

			$existing_items = self::load_items($cart_name);
			$is_bundle_product = count($bundle_data);
			foreach ($existing_items as $item)
			{
				if ($item->bundle_master_cart_key)
					continue;

				$item_content_key = $item->get_content_key($existing_items);
				if ($item_content_key == $new_item_content_key)
					return $item;
			}

			return null;
		}
	}

?>