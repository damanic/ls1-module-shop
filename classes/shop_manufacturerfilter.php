<?

	class Shop_ManufacturerFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_Manufacturer';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			if ($context != 'product_list')
			{
				$model->where('(exists (select shop_products.id from shop_products, shop_order_items, shop_manufacturers where shop_products.id=shop_order_items.shop_product_id and shop_order_items.shop_order_id=shop_orders.id and shop_manufacturers.id=(
					if(
						shop_products.grouped is null or shop_products.grouped=0, 
						shop_products.manufacturer_id, 
						(select manufacturer_id from shop_products as inner_list where inner_list.id = shop_products.product_id))) and shop_manufacturers.id in (?)))', array($keys));
			} else {
				$model->where('(exists (select * from shop_manufacturers where shop_manufacturers.id=(
					if(
						shop_products.grouped is null or shop_products.grouped=0, 
						shop_products.manufacturer_id, 
						(select manufacturer_id from shop_products as inner_list where inner_list.id = shop_products.product_id)
					)) and shop_manufacturers.id in (?)))', array($keys));
			}
		}
		
		public function asString($keys, $context = null)
		{
			return 'and (if(
					shop_products.grouped is null or shop_products.grouped=0, 
					shop_products.manufacturer_id, 
					(select manufacturer_id from shop_products as inner_list where inner_list.id = shop_products.product_id))) in '.$this->keysToStr($keys);
		}
	}

?>