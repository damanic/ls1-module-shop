<?php

	class Shop_Shipping_Tracking_Providers extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_ShippingTrackerProvider';
		public $list_record_url = null;

		public $form_preview_title = 'Tracking Provider';
		public $form_create_title = 'New Tracking Provider';
		public $form_edit_title = 'Edit Tracking Provider';
		public $form_model_class = 'Shop_ShippingTrackerProvider';
		public $form_not_found_message = 'Tracking provider not found.';
		public $form_redirect = null;

		public $form_edit_save_flash = 'The tracking provider has been successfully saved.';
		public $form_create_save_flash = 'The tracking provider has been successfully added.';
		public $form_edit_delete_flash = 'The tracking provider has been successfully deleted.';

		public $list_items_per_page = 20;

		protected $required_permissions = array('shop:manage_shop_settings');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'orders';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/shipping_tracking_providers/edit/');
			$this->form_redirect = url('/shop/shipping_tracking_providers');
		}

		public function index()
		{
			$this->app_page_title = 'Tracking Providers';
		}

	}