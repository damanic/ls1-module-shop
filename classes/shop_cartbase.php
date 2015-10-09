<?

	/**
	 * This is a base class for in-memory and customer carts
	 */
	abstract class Shop_CartBase
	{
		abstract public function add_item($product, $options, $extra_options, $quantity, $cart_name, $custom_data = array(), $uploaded_files = null, $bundle_data = array());
		
		abstract public function get_item_total_num($cart_name);
		
		abstract public function list_items($cart_name);
		
		abstract public function remove_item($key, $cart_name);
		
		abstract public function set_quantity($key, $value, $cart_name);
		
		abstract public function change_postpone_status($values, $cart_name);
		
		abstract public function set_custom_data($key, $values, $cart_name);
		
		abstract public function add_uploaded_files($key, $files, $cart_name);
		
		abstract public function find_matching_item($cart_name, $product_id, $options, $extras, $custom_data, $uploaded_files, $bundle_data, $master_bundle_data,  $quantity);
	}

?>