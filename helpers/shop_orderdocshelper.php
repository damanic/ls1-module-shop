<?php
class Shop_OrderDocsHelper{

	public static function get_default_variant($orders, $template_info){
		$orders = is_array($orders) ? $orders : array($orders);
		foreach(self::get_applicable_variants($orders,$template_info) as $variant => $params){
			if(isset($params['default']) && $params['default']){
				return $variant;
			}
		}
	}

	/**
	 * Gets template HTML file
	 * @documentable
	 * @deprecated Use {@link Shop_OrderDocsHelper::get_template_file()} method instead.
	 */
	public static function get_template_variant($order, $template_info, $variant){
		self::get_template_file($order, $template_info, $variant);
	}

	public static function get_template_file($order, $template_info, $variant){
		//do specified variant
		if(!empty($variant)){
			if(self::can_show_template_variant($order,$template_info,$variant)){
				$template = self::get_template_dir( $template_info['template_id'] ) . '/variants/' . strtolower( $variant ) . '.htm';
				if(file_exists($template)) {
					return $template;
				}
			}
		}

		//use default variant
		$default_variant = self::get_default_variant($order,$template_info);
		$template = self::get_template_dir( $template_info['template_id'] ) . '/variants/' . strtolower( $default_variant ) . '.htm';
		if ( file_exists( $template ) ) {
			return $template;
		}



		//fallback
		$template = self::get_template_dir($template_info['template_id']).'/invoice.htm';
		if(file_exists($template)){
			return $template;
		}

		return false;
	}

	public static function get_applicable_variants($orders, $template_info){
		$orders = is_array($orders) ? $orders : array($orders);
		$variants = array();
		if(isset($template_info['variants'])) {
			foreach ( $template_info['variants'] as $variant => $params ) {
				foreach ( $orders as $order ) {
					if ( self::can_show_template_variant( $order, $template_info, $variant ) ) {
						$variants[$variant] = $params;
					}
				}
			}
		}
		return $variants;
	}

	public static function get_view_data($order){
		$company_info = Shop_CompanyInformation::get();
		$template_info = $company_info->get_invoice_template();

		$has_bundles = false;
		foreach ( $order->items as $item ) {
			if ( $item->bundle_master_order_item_id ) {
				$has_bundles = true;
				break;
			}
		}
		$invoice_date = $order->get_invoice_date();
		$view_data    = array(
			'order_id'             => $order->id,
			'order'                => $order,
			'company_info'         => $company_info,
			'template_info'        => $template_info,
			'display_tax_included' => Shop_CheckoutData::display_prices_incl_tax( $order ),
			'has_bundles'          => $has_bundles,
			'invoice_date'         => $invoice_date,
			'display_due_date'     => strlen( $company_info->invoice_due_date_interval ),
			'due_date'             => $company_info->get_invoice_due_date( $invoice_date ),
		);

		return $view_data;
	}

	protected static function apply_view_data($controller, $view_data){
		if(is_array($view_data)){
			foreach($view_data as $key => $value){
				$controller->viewData[$key] = $value;
			}
		}
	}

	public static function can_show_template_variant($order,$template_info,$variant){
		$show_on_status_code = isset($template_info['variants'][$variant]['on_status']) ? $template_info['variants'][$variant]['on_status'] : false;
		$show_if_has_status_code =  isset($template_info['variants'][$variant]['has_status']) ? $template_info['variants'][$variant]['has_status'] : false ;

		if($show_on_status_code){
			$show_on_status_code = is_array($show_on_status_code) ? $show_on_status_code : array($show_on_status_code);
			if(!in_array($order->status->code,$show_on_status_code)) {
				return false;
			}
		}

		if($show_if_has_status_code){
			if(!Shop_OrderStatusLog::order_has_status_code($order, $show_if_has_status_code)){
				return false;
			}
		}

		return true;
	}


	public static function render_doc($controller, Shop_Order $order, $variant=null){

		$view_data = self::get_view_data($order);
		$template_file = self::get_template_file($order, $view_data['template_info'], $variant);
		$html = null;

		if(!$template_file) {
			return false;
		}

		self::apply_view_data($controller,$view_data);
		ob_start();
		$controller->renderPartial( $template_file, $view_data );
		$html = ob_get_contents();
		ob_end_clean();

		//Allow HTML output to be modified (eg. use html to create a pdf and show pdf in iframe).
		$html_modified = Backend::$events->fireEvent('shop:onBeforeRenderOrderDoc', $html);
		if($html_modified){
			echo $html_modified;
			return;
		}

		echo $html;
	}

	public static function render_custom_doc($controller, $orders, $template_info, $variant=null){
		$orders = is_array($orders) ? $orders : array($orders);
		//Allow HTML output to be modified (eg. use html to create a pdf and show pdf in iframe).
		Backend::$events->fireEvent('shop:onRenderCustomOrderDoc', $controller, $orders, $template_info, $variant);
	}

	public static function get_template_dir($template_id = null){
		$path = PATH_APP."/modules/shop/invoice_templates";

		if($template_id){
			return $path.'/'.$template_id;
		}

		return $path;
	}

	public static function get_partial($partial,$template_id){
		return self::get_template_dir($template_id).'/partials/'.$partial.'.htm';
	}


	public static function get_document_urls($template_info, $order_ids, $variant=null){

		$order_ids = is_array($order_ids) ? $order_ids : array($order_ids);
		$order_id_string = urlencode(implode('|', $order_ids));
		if($template_info['custom_render']){
			$results = Backend::$events->fire_event('shop:onGetCustomOrderDocUrl', $template_info, $order_ids, $variant );


			if($results){
				$urls = array();
				foreach($results as $result){
					if(is_array($result)){
						foreach($result as $url){
							$urls[] = $url;
						}
					} else {
						$urls[] = $result;
					}
				}
				return $urls;
			}
		}

		return array(root_url(url('/shop/orders/document/'.$order_id_string.'/'.$variant), true));
	}


}