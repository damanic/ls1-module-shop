<?

	class Shop_GridTierPriceEditor extends Db_GridEditor
	{
		public function format_row_content($row_index, $column_info, $field_name, $row_data, $session_key)
		{
			return null;
		}
		
		public function render_popup_contents($column_info, $controller, $field_name)
		{
			try
			{
				$product = $this->load_product_object();
				$price_tiers = $product->list_related_records_deferred('price_tiers', $controller->formGetEditSessionKey());
			
				$price_colum_data = post_array_item(post('widget_model_class'), 'grid_data', array());
				$data_index = post('phpr_grid_row_index');
				$price_data = array();
				$base_price = null;

				if ($price_colum_data 
					&& is_array($price_colum_data) 
					&& array_key_exists($data_index, $price_colum_data))
				{
					try
					{
						$data = @unserialize($price_colum_data[$data_index]['base_price_internal']);
						if (is_array($data))
							$price_data = $data;
							
						$base_price = trim($price_colum_data[$data_index]['base_price']);
					} catch (exception $ex) {}
				}

				$controller->renderPartial(PATH_APP.'/modules/shop/behaviors/shop_optionmatrixbehavior/partials/_tier_price_editor.htm', array(
					'price_tiers'=>$price_tiers,
					'form_model'=>$this->model,
					'row_index'=>post('phpr_grid_row_index'),
					'price_data'=>$price_data,
					'base_price'=>$base_price
				));
			} catch (exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function load_product_object()
		{
			$product_id = Phpr::$router->param('param1');

			$product = Shop_Product::create();
			if ($product_id)
			{
				$product = $product->find($product_id);
				if (!$product)
					throw new Phpr_ApplicationException('Product not found');
			}

			return $product;
		}
		
		protected function on_get_cell_internal_value($field, $model, $column_info, $controller, $column_name)
		{
			try
			{
				$validation = new Phpr_Validation();
				$validation->add('price', 'Price')->float();

				$base_price = trim(post('base_price'));
				if (!$validation->validate(array('price'=>$base_price)))
					throw new Phpr_ApplicationException('Invalid value in the Base Price field');
					
				$tier_price = post('tier_price', array());
				foreach ($tier_price as $tier_id=>&$price)
				{
					$price = trim($price);
					if (!$validation->validate(array('price'=>$price)))
						throw new Phpr_ApplicationException('Invalid value in the tier price configuration: '.$price);
				}

				echo json_encode(array(
					'tiers'=>serialize($tier_price),
					'base'=>$base_price
				));
			} catch (exception $ex) 
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>