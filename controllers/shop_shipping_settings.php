<?

	class Shop_Shipping_Settings extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
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
			'onDeleteShippingZone'
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
			try
			{
				$shipping_params = Shop_ShippingParams::get(true);

				$id = post('shipping_zone_id');
				$zone = $id ? Shop_ShippingZone::create()->find($id) : Shop_ShippingZone::create();
				if ($zone)
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $zone);
					$shipping_params->shipping_zones->delete($zone, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $shipping_params;
				$this->renderPartial('shipping_zone_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
	}

?>