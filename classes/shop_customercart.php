<?

	class Shop_CustomerCart extends Shop_CartBase
	{
		private $items = array();
		private $customer;
		
		public function __construct($customer)
		{
			$this->customer = $customer;
		}
		
		public function add_item($product, $options, $extra_options, $quantity, $cart_name, $custom_data = array(), $uploaded_files = null, $bundle_data = array(), $master_bundle_data = array())
		{
			$product_id = is_object($product) ? $product->id : $product;

			/*
			$key = Shop_CustomerCartItem::gen_item_key($product_id, $options, $extra_options, $custom_data, $uploaded_files);
			*/
			$this->list_items($cart_name);
			
			$cart_items = array_key_exists($cart_name, $this->items) ? $this->items[$cart_name] : array();
			$key = md5(uniqid(count($cart_items)+1+microtime(true)));

			$item = Shop_CustomerCartItem::create();
			$item->product_id = $product_id;
			$item->customer_id = $this->customer->id;
			$item->options = $options;
			$item->extras = $extra_options;
			$item->quantity = $quantity;
			$item->item_key = $key;
			$item->cart_name = $cart_name;
			
			if (array_key_exists('bundle_master_cart_key', $bundle_data))
			{
				$item->bundle_master_cart_key = $bundle_data['bundle_master_cart_key'];
				$item->bundle_offer_id = $bundle_data['bundle_offer_id'];
				$item->bundle_offer_item_id = $bundle_data['bundle_offer_item_id'];
			}
			
			if ($custom_data && is_array($custom_data))
			{
				foreach ($custom_data as $field_name=>$field_value)
					$item->$field_name = $field_value;
			}

			$item->save();

			if (!array_key_exists($cart_name, $this->items))
				$this->items[$cart_name] = array();

			$this->items[$cart_name][$key] = $item;

			if ($uploaded_files && ($uploaded_files instanceof Db_DataCollection || is_array($uploaded_files)))
				self::add_uploaded_files($item->key, $uploaded_files, $cart_name);

			return $item;
		}

		public function get_item_total_num($cart_name, $count_bundle_items = false)
		{
			$result = 0;
			$items = $this->list_items($cart_name);
			foreach ($items as $item)
			{
				if (!$item->postponed && !(!$count_bundle_items && $item->is_bundle_item()))
					$result += $item->quantity;
			}

			return $result;
		}

		public function list_items($cart_name)
		{
			if (array_key_exists($cart_name, $this->items))
				return $this->items[$cart_name];

			return $this->items[$cart_name] = Shop_CustomerCartItem::create()->where('customer_id=?', $this->customer->id)->where('cart_name=?', $cart_name)->order('shop_customer_cart_items.id')->find_all()->as_array(null, 'item_key');
		}

		public function remove_item($key, $cart_name)
		{
			$this->list_items($cart_name);
			
			if (array_key_exists($key, $this->items[$cart_name]))
			{
				$this->items[$cart_name][$key]->delete();
				unset($this->items[$cart_name][$key]);
			}
		}

		public function set_quantity($key, $value, $cart_name)
		{
			$this->list_items($cart_name);

			if (array_key_exists($key, $this->items[$cart_name]))
			{
				if ($value > 0)
				{
					$this->items[$cart_name][$key]->quantity = $value;
					$this->items[$cart_name][$key]->save();
				}
				else
				{
					$this->items[$cart_name][$key]->delete();
					unset($this->items[$cart_name][$key]);
				}

				return true;
			}
			
			return false;
		}
		
		public function set_custom_data($key, $values, $cart_name)
		{
			$this->list_items($cart_name);

			if (array_key_exists($key, $this->items[$cart_name]))
			{
				foreach ($values as $field_name=>$field_value)
				{
					$this->items[$cart_name][$key]->$field_name = $field_value;
					$this->items[$cart_name][$key]->save();
				}

				return true;
			}
			
			return false;
		}
		
		public function add_uploaded_files($key, $files, $cart_name)
		{
			$this->list_items($cart_name);

			if (array_key_exists($key, $this->items[$cart_name]))
			{
				foreach ($files as $file)
				{
					$this->items[$cart_name][$key]->add_uploaded_file($file);
					$this->items[$cart_name][$key]->save();
				}

				return true;
			}
			
			return false;
		}
		
		public function change_postpone_status($values, $cart_name)
		{
			$result = false;

			$items = $this->list_items($cart_name);
			foreach ($values as $key=>$value)
			{
				if (array_key_exists($key, $items))
				{
					if ($this->items[$cart_name][$key]->postponed != $value)
					{
						$result = true;
						$this->items[$cart_name][$key]->postponed = $value;
						$this->items[$cart_name][$key]->save();
					}
				}
			}

			return $result;
		}

		public function find_matching_item($cart_name, $product_id, $options, $extras, $custom_data, $uploaded_files, $bundle_data, $master_bundle_data,  $quantity)
		{
			/*
			 * Do not merge bundle item products
			 */

			if (isset($bundle_data['bundle_master_cart_key']))
				return null;

			$uploaded_files_array = Shop_Cart::uploaded_files_to_array($uploaded_files);

			$new_item_content_key = Shop_CustomerCartItem::gen_item_content_key(
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

			$existing_items = $this->list_items($cart_name);
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

		public function get_cart_items($cart_name){
			$cart_items = array();
			$stored_items = $this->list_items($cart_name);
			if ( $stored_items ) {
				foreach ( $stored_items as $key => $item ) {
					$cart_item                = new Shop_CartItem();
					$product_lookup        = Shop_Product::create()->where( 'id = ?', $item->product_id )->apply_visibility();
					$sql                   = $product_lookup->build_sql();
					$result = Db_DbHelper::queryArray($sql);
					$product_data = isset($result[0]) ? $result[0] : false;
					if($product_data) {
						$product                  = new Db_ActiverecordProxy( $product_data['id'], 'Shop_Product', $product_data );
						$cart_item->key           = $item->item_key;
						$cart_item->product       = $product;
						$cart_item->options       = $item->options;
						$cart_item->extra_options = $item->extras;
						$cart_item->quantity      = $item->quantity;
						$cart_item->price_preset  = false;
						$cart_items[]             = $cart_item;
					}
				}
			}
			return $cart_items;
		}
	}

?>