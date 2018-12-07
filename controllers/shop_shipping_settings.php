<?

	class Shop_Shipping_Settings extends Backend_SettingsController
	{
		protected $required_permissions = array( 'shop:manage_shipping_settings' );
		public $implement = 'Db_FormBehavior, Db_ListBehavior';

		public $form_edit_title = 'Shipping Settings';
		public $form_model_class = 'Shop_ShippingParams';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'Shipping configuration has been saved.';

		protected $globalHandlers = array(
			'onLoadShippingBoxForm',
			'onUpdateShippingBox',
			'onUpdateShippingBoxList',
			'onDeleteShippingBox',
			'onLoadShippingZoneForm',
			'onUpdateShippingZone',
			'onUpdateShippingZoneList',
			'onDeleteShippingZone',
			'onLoadShippingServiceLevelForm',
			'onUpdateShippingServiceLevel',
			'onUpdateShippingServiceLevelList',
			'onDeleteShippingServiceLevel',
			'onLoadShippingDeliveryEstimateForm',
			'onUpdateShippingDeliveryEstimate',
			'onUpdateShippingDeliveryEstimateList',
			'onDeleteShippingDeliveryEstimate'
		);
		
		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			
			$this->form_redirect = url('system/settings/');
		}

		public function index()
		{
			try
			{
				$record = Shop_ShippingParams::get(true);
				if (!$record)
					throw new Phpr_ApplicationException('Shipping configuration not found.');
				
				$this->edit($record->id);
				$this->app_page_title = $this->form_edit_title;
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function index_onCountryChange()
		{
			$form_model = $this->formCreateModelObject();
			
			$data = post('Shop_ShippingParams');
			$form_model->country_id = $data['country_id'];
			echo ">>form_field_container_state_idShop_ShippingParams<<";
			$this->formRenderFieldContainer($form_model, 'state');
		}
		
		protected function index_onDefaultLocationCountryChange()
		{
			$form_model = $this->formCreateModelObject();
			
			$data = post('Shop_ShippingParams');
			$form_model->default_shipping_country_id = $data['default_shipping_country_id'];
			echo ">>form_field_container_default_shipping_state_idShop_ShippingParams<<";
			$this->formRenderFieldContainer($form_model, 'default_state');
		}
		
		protected function index_onOriginToDefault()
		{
			$form_model = $this->formCreateModelObject();
			$data = post('Shop_ShippingParams');
			
			$form_model->default_shipping_country_id = isset($data['country_id']) ? $data['country_id'] : null;
			$form_model->default_shipping_state_id = isset($data['state_id']) ? $data['state_id'] : null;
			$form_model->default_shipping_city = isset($data['city']) ? $data['city'] : null;
			$form_model->default_shipping_zip = isset($data['zip_code']) ? $data['zip_code'] : null;
			
			echo ">>tab_4<<";
			$this->formRenderFormTab($form_model, 3);
		}

		protected function index_onSave()
		{
			$record = Shop_ShippingParams::get(true);
			$this->edit_onSave($record->id);
		}
		
		protected function index_onCancel()
		{
			$record = Shop_ShippingParams::get(true);
			$this->edit_onCancel($record->id);
		}


		/*
		 * Shipping Boxes
		 */

		protected function onLoadShippingBoxForm()
		{
			try
			{
				$id = post('shipping_box_id');
				$box = $id ? Shop_ShippingBox::create()->find($id) : Shop_ShippingBox::create();
				if (!$box)
					throw new Phpr_ApplicationException('Shipping Box not found');

				$box->define_form_fields();

				$this->viewData['box'] = $box;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['box_id'] = post('shipping_box_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('shipping_box_form');
		}

		protected function onUpdateShippingBox($parentId = null)
		{
			try
			{
				$id = post('box_id');
				$box = $id ? Shop_ShippingBox::create()->find($id) : Shop_ShippingBox::create();
				if (!$box)
					throw new Phpr_ApplicationException('Shipping Box not found');

				if (!$id)
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $box);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $box);

				$shipping_params = Shop_ShippingParams::get(true);

				$box->init_columns_info();
				$box->define_form_fields();
				$box->save(post('Shop_ShippingBox'), $this->formGetEditSessionKey());

				if (!$id)
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $box);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $box);

				if (!$id)
					$shipping_params->shipping_boxes->add($box, post('shippingparams_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onUpdateShippingBoxList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = Shop_ShippingParams::get();
				$this->renderPartial('shipping_box_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onDeleteShippingBox($parentId = null)
		{
			try
			{
				$shipping_params = Shop_ShippingParams::get(true);

				$id = post('shipping_box_id');
				$box = $id ? Shop_ShippingBox::create()->find($id) : Shop_ShippingBox::create();
				if ($box)
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $box);
					$shipping_params->shipping_boxes->delete($box, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $shipping_params;
				$this->renderPartial('shipping_box_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/*
		 * Shipping Zones
		 */

		protected function onLoadShippingZoneForm()
		{
			try
			{
				$id = post('shipping_zone_id');
				$zone = $id ? Shop_ShippingZone::create()->find($id) : Shop_ShippingZone::create();
				if (!$zone)
					throw new Phpr_ApplicationException('Shipping Zone not found');

				$zone->define_form_fields();

				$this->viewData['zone'] = $zone;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['zone_id'] = post('shipping_zone_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('shipping_zone_form');
		}


		protected function onUpdateShippingZone($parentId = null)
		{
			try
			{
				$id = post('zone_id');
				$zone = $id ? Shop_ShippingZone::create()->find($id) : Shop_ShippingZone::create();
				if (!$zone)
					throw new Phpr_ApplicationException('Shipping Zone not found');

				if (!$id)
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $zone);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $zone);

				$shipping_params = Shop_ShippingParams::get(true);

				$zone->init_columns_info();
				$zone->define_form_fields();
				$zone->save(post('Shop_ShippingZone'), $this->formGetEditSessionKey());

				if (!$id)
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $zone);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $zone);

				if (!$id)
					$shipping_params->shipping_zones->add($zone, post('shippingparams_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onUpdateShippingZoneList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = Shop_ShippingParams::get();
				$this->renderPartial('shipping_zone_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onDeleteShippingZone($parentId = null)
		{

				$shipping_params = Shop_ShippingParams::get(true);

				$id = post('shipping_zone_id');
				$zone = $id ? Shop_ShippingZone::create()->find($id) : Shop_ShippingZone::create();
				if ($zone)
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $zone);
					$zone->before_delete();
					$shipping_params->shipping_zones->delete($zone, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $shipping_params;
				$this->renderPartial('shipping_zone_list');

		}

	/*
	* Service Levels
	*/

		protected function onLoadShippingServiceLevelForm()
		{
			try
			{
				$id = post('shipping_service_level_id');
				$service_level = $id ? Shop_ShippingServiceLevel::create()->find($id) : Shop_ShippingServiceLevel::create();
				if (!$service_level)
					throw new Phpr_ApplicationException('Shipping Service Level not found');

				$service_level->define_form_fields();

				$this->viewData['service_level'] = $service_level;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['service_level_id'] = post('shipping_service_level_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('shipping_service_level_form');
		}


		protected function onUpdateShippingServiceLevel($parentId = null)
		{
			try
			{
				$id = post('service_level_id');
				$service_level = $id ? Shop_ShippingServiceLevel::create()->find($id) : Shop_ShippingServiceLevel::create();
				if (!$service_level)
					throw new Phpr_ApplicationException('Shipping Service Level not found');

				if (!$id)
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $service_level);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $service_level);

				$shipping_params = Shop_ShippingParams::get(true);

				$service_level->init_columns_info();
				$service_level->define_form_fields();
				$service_level->save(post('Shop_ShippingServiceLevel'), $this->formGetEditSessionKey());

				if (!$id)
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $service_level);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $service_level);

				if (!$id)
					$shipping_params->shipping_service_levels->add($service_level, post('shippingparams_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onUpdateShippingServiceLevelList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = Shop_ShippingParams::get();
				$this->renderPartial('shipping_service_level_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onDeleteShippingServiceLevel($parentId = null)
		{
			try
			{
				$shipping_params = Shop_ShippingParams::get(true);

				$id = post('shipping_service_level_id');
				$service_level = $id ? Shop_ShippingServiceLevel::create()->find($id) : Shop_ShippingServiceLevel::create();
				if ($service_level)
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $service_level);
					$shipping_params->shipping_service_levels->delete($service_level, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $shipping_params;
				$this->renderPartial('shipping_service_level_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}


	/*
	* Delivery Estimates
	*/

		protected function onLoadShippingDeliveryEstimateForm()
		{
			try
			{
				$id = post('shipping_delivery_estimate_id');
				$delivery_estimate = $id ? Shop_ShippingDeliveryEstimate::create()->find($id) : Shop_ShippingDeliveryEstimate::create();
				if (!$delivery_estimate)
					throw new Phpr_ApplicationException('Delivery Estimate not found');

				$delivery_estimate->define_form_fields();


				$this->viewData['delivery_estimate'] = $delivery_estimate;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['delivery_estimate_id'] = post('shipping_delivery_estimate_id');
				$this->viewData['service_level_id'] = post('service_level_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('shipping_delivery_estimate_form');
		}


		protected function onUpdateShippingDeliveryEstimate($parentId = null)
		{
			try
			{
				$delivery_estimate_id = post('delivery_estimate_id');
				$delivery_estimate = $delivery_estimate_id ? Shop_ShippingDeliveryEstimate::create()->find($delivery_estimate_id) : Shop_ShippingDeliveryEstimate::create();
				if (!$delivery_estimate)
					throw new Phpr_ApplicationException('Shipping Delivery Estimate not found');

				if (!$delivery_estimate_id)
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $delivery_estimate);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $delivery_estimate);

				$service_level_id = post('service_level_id',false);
				$service_level = Shop_ShippingServiceLevel::create();
				if($service_level_id){
					$service_level = $service_level->find($service_level_id);
				}

				$shipping_params = Shop_ShippingParams::get(true);

				$delivery_estimate->init_columns_info();
				$delivery_estimate->define_form_fields();
				$delivery_estimate->save(post('Shop_ShippingDeliveryEstimate'), $this->formGetEditSessionKey());

				if (!$delivery_estimate_id)
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $delivery_estimate);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $delivery_estimate);

				if (!$delivery_estimate_id)
					$service_level->delivery_estimates->add($delivery_estimate, post('shippingparams_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onUpdateShippingDeliveryEstimateList($parentId = null)
		{
			try
			{
				$service_level = Shop_ShippingServiceLevel::create();
				$service_level_id = post('service_level_id',false);
				$this->viewData['form_model'] = $service_level_id ? $service_level->find($service_level_id) : $service_level;
				$this->renderPartial('shipping_delivery_estimate_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onDeleteShippingDeliveryEstimate($parentId = null)
		{

				$service_level_id = post('service_level_id',false);
				$service_level = Shop_ShippingServiceLevel::create();
				if($service_level_id){
					$service_level = $service_level->find($service_level_id);
				}

				$id = post('shipping_delivery_estimate_id');
				$delivery_estimate = $id ? Shop_ShippingDeliveryEstimate::create()->find($id) : Shop_ShippingDeliveryEstimate::create();
				if ($delivery_estimate)
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $delivery_estimate);
					$service_level->delivery_estimates->delete($delivery_estimate, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $service_level;
				$this->renderPartial('shipping_delivery_estimate_list');

		}

	}

?>