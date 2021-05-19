<?

	class Shop_Settings extends Backend_SettingsController
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $form_model_class = '';
		
		public $list_model_class = null;
		public $list_record_url = null;
		
		public $list_custom_body_cells = null;
		public $list_custom_head_cells = null;
		public $list_top_partial = null;
		
		public $list_search_enabled = false;
		public $list_search_fields = array();
		public $list_search_prompt = null;
		
		protected $globalHandlers = array(
			'onLoadCountryStateForm',
			'onSaveCountryState',
			'onUpdateCountryStateList',
			'onDeleteCountryState'
		);

		protected $required_permissions = array(
			'shop:manage_countries_and_states',
			'shop:manage_shop_currency',
		);

		public function __construct()
		{
			parent::__construct();

			switch (Phpr::$router->action)
			{
				case 'countries' : 
					$this->list_model_class = 'Shop_Country';
					$this->list_record_url = url('/shop/settings/edit_country');
					
					$this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
					$this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
					
					$this->list_search_enabled = true;
					$this->list_search_fields = array('shop_countries.name', 'shop_countries.code', 'shop_countries.code_3', 'shop_countries.code_iso_numeric');
					$this->list_search_prompt = 'find countries by name or code';
					$this->list_top_partial = 'country_selectors';
				break;
				case 'create_country' :
					$this->form_model_class = 'Shop_Country';
				break;
			}
		}
		
		/*
		 * Currency setup
		 */

		public function currency()
		{
			if ( !$this->currentUser->get_permission( 'shop', 'manage_shop_currency' ) ) {
				Phpr::$session->flash['error'] = 'You do not have permission, access denied.';
				Phpr::$response->redirect(url('system/settings'));
			}
			$this->app_page_title = 'Currency';
			$this->form_model_class = 'Shop_CurrencySettings';
			$settings = Shop_CurrencySettings::get();
			$settings->init_columns_info();
			$settings->define_form_fields();
			$this->viewData['settings'] = $settings;
		}
		
		protected function currency_onSave()
		{
			try
			{
				$settings = Shop_CurrencySettings::get();
				$settings->init_columns_info();
				$settings->define_form_fields();
				$settings->save(post('Shop_CurrencySettings'));
				
				Phpr::$session->flash['success'] = 'Currency settings have been saved.';
				Phpr::$response->redirect(url('system/settings'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Countries
		 */
		
		public function countries()
		{
			if ( !$this->currentUser->get_permission( 'shop', 'manage_countries_and_states' ) ) {
				Phpr::$session->flash['error'] = 'You do not have permission, access denied.';
				Phpr::$response->redirect(url('system/settings'));
			}
			$this->app_page_title = 'Countries';
		}
		
		protected function countries_onLoadEnableDisableCountriesForm()
		{
			try
			{
				$country_ids = post('list_ids', array());
				
				if (!count($country_ids))
					throw new Phpr_ApplicationException('Please select countries to enable or disable.');
				
				$this->viewData['country_count'] = count($country_ids);
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('enable_disable_country_form');
		}
		
		protected function countries_onApplyCountriesEnabledStatus()
		{
			$country_ids = post('list_ids', array());

			$enabled = post('enabled');
			$enabled_in_backend = post('enabled_in_backend');
			
			if ($enabled)
				$enabled_in_backend = true;
			
			foreach ($country_ids as $country_id)
			{
				$country = Shop_Country::create()->find($country_id);
				if ($country)
					$country->update_enabled_status($enabled, $enabled_in_backend);
			}
			
			$this->onListReload();
		}
		
		public function create_country()
		{
			$this->app_page_title = 'New Country';

			try
			{
				$country = $this->viewData['form_model'] = $this->init_country();
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function create_country_onSave()
		{
			try
			{
				$country = $this->init_country();

				Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $country);
				$country->save(post('Shop_Country', array()), $this->formGetEditSessionKey());
				Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $country);

				Phpr::$session->flash['success'] = 'Country has been successfully added';
				Phpr::$response->redirect(url('/shop/settings/countries'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function create_country_onCancel()
		{
			$this->init_country()->cancelDeferredBindings($this->formGetEditSessionKey());
			Phpr::$response->redirect(url('/shop/settings/countries'));
		}
		
		public function edit_country($id)
		{
			$this->app_page_title = 'Edit Country';

			try
			{
				$this->viewData['form_model'] = $this->init_country($id);
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function edit_country_onSave($id)
		{
			try
			{

				$country = $this->init_country($id);

				Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $country);
				$country->save(post('Shop_Country', array()), $this->formGetEditSessionKey());
				Backend::$events->fireEvent('core:onAfterFormRecordUpdate',$this, $country);

				Phpr::$session->flash['success'] = 'Country has been successfully saved';
				Phpr::$response->redirect(url('/shop/settings/countries'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function edit_country_onDelete($id)
		{
			try
			{
				$country = $this->init_country($id);
				$country->delete();
			
				Phpr::$session->flash['success'] = 'Country has been successfully deleted';
				Phpr::$response->redirect(url('/shop/settings/countries'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function edit_country_onCancel($id)
		{
			$this->init_country($id)->cancelDeferredBindings($this->formGetEditSessionKey());
			Phpr::$response->redirect(url('/shop/settings/countries'));
		}
		
		protected function onLoadCountryStateForm()
		{
			try
			{
				$id = post('state_id');
				$state = $id ? Shop_CountryState::create()->find($id) : Shop_CountryState::create();
				if (!$state)
					throw new Phpr_ApplicationException('State not found');

				$state->define_form_fields();

				$this->viewData['state'] = $state;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['state_id'] = post('state_id');
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('country_state_form');
		}
		
		protected function onSaveCountryState($countryId)
		{
			try
			{
				$id = post('state_id');
				$state = $id ? Shop_CountryState::create()->find($id) : Shop_CountryState::create();
				if (!$state)
					throw new Phpr_ApplicationException('State not found');

				$country = $this->init_country($countryId);

				$state->init_columns_info();
				$state->define_form_fields();

				if ($id)
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $state);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $state);

				$state->save(post('Shop_CountryState'), $this->formGetEditSessionKey());

				if (!$id)
					$country->states->add($state, post('country_session_key'));
					
				if ($id)
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $state);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $state);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdateCountryStateList($countryId)
		{
			try
			{
				$this->viewData['form_model'] = $this->init_country($countryId);
				$this->renderPartial('country_states_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeleteCountryState($countryId)
		{
			try
			{
				$country = $this->viewData['form_model'] = $this->init_country($countryId);

				$id = post('state_id');
				$state = $id ? Shop_CountryState::create()->find($id) : Shop_ExtraOption::create();
				
				if ($state)
				{
					$state->check_in_use();
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $state);

					$country->states->delete($state, $this->formGetEditSessionKey());
					$state->delete();
				}

				$this->viewData['form_model'] = $country;
				$this->renderPartial('country_states_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		private function init_country($id = null)
		{
			$obj = $id == null ? Shop_Country::create() : Shop_Country::create()->find($id);
			if ($obj)
			{
				//Include disabled states
				$obj->has_many['states'] = array(
					'class_name'=>'Shop_CountryState',
					'foreign_key'=>'country_id',
					'order'=>'shop_states.disabled, shop_states.name',
					'delete'=>true
				);
				$obj->init_columns_info();
				$obj->define_form_fields();
			} else if($id != null)
			{
				throw new Phpr_ApplicationException('Country not found');
			}

			return $obj;
		}
		
		public function listGetRowClass($model)
		{
			if ($model instanceof Shop_Country)
			{
				$result = 'country_'.($model->enabled ? 'enabled' : 'disabled').' ';
				
				$enabled_flag = null;
				if (!$model->enabled && !$model->enabled_in_backend)
					$enabled_flag = 'disabled';
				elseif (!$model->enabled && $model->enabled_in_backend)
					$enabled_flag = 'special';
				
				return $result.$enabled_flag;
			}
		}


		/*
		 * Statuses
		 */
		
		
	}
	
?>