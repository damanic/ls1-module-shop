<?php

/**
 * This behavior provides user interface for storing price fields in multiple currencies
 */
class Shop_CurrencyBehavior extends Phpr_ControllerBehavior {


	public function __construct( $controller ) {
		parent::__construct( $controller );

		$this->_controller->formRegisterViewPath(PATH_APP.'/modules/shop/behaviors/shop_currencybehavior/form_register');
		$this->_controller->addCss('/modules/shop/behaviors/shop_currencybehavior/resources/css/shop-currency.css?'.module_build('shop'));
		$this->_controller->addJavascript('/modules/shop/behaviors/shop_currencybehavior/resources/javascript/shop-currency.js?'.module_build('shop'));

		$this->hideAction('formAfterCreateSave');
		$this->hideAction('onLoadPriceFieldPopup');
		$this->hideAction('onSavePriceFields');

		if ( Phpr::$router->action == 'edit' || Phpr::$router->action == 'create' ) {
			$this->addGlobalEventHandler( 'onLoadPriceFieldPopup' );
			$this->addGlobalEventHandler( 'onSavePriceFields' );
		}

	}

	public function formAfterCreateSave($model, $session_key){
		Shop_CurrencyPriceRecord::assign_deferred_bindings($model,$session_key);
	}

	public function onLoadPriceFieldPopup( $model_id = null ) {
		try {
			$prices             = array();
			$field_name              = post( 'field', 'price' );
			$internal_currency  = Shop_CurrencySettings::get();
			$currency_converter = Shop_CurrencyConverter::create();

			$object_class       = post( 'master_object_class', $this->_controller->form_model_class );
			$object_id   = post( 'master_object_id' );
			$session_key = post( 'edit_session_key' );


			$master_obj = new $object_class();
			if ( $object_id ) {
				$master_obj = $master_obj->find( $object_id );
			}

			try {
				$field_value = post( 'field_value', $master_obj->$field_name );
			} catch ( Phpr_PhpException $e ) {
				var_dump( $master_obj );
				exit;
			}

			$currencies = new Shop_CurrencySettings();
			$currencies = $currencies->where( 'id != ?', $internal_currency->id )->find_all();

			$price_records = Shop_CurrencyPriceRecord::create();
			$price_records->where('master_object_class = ?', $object_class);
			$price_records->where('master_field_name = ?', $field_name);

			if ( $master_obj->id ) {
				$price_records->where('master_object_id = ?', $object_id);
			} else {
				$price_records->where('deferred_session_key = ?', $session_key);
			}

			$price_records = $price_records->find_all();

			foreach ( $currencies as $currency ) {
				foreach ( $price_records as $pr ) {
					if ( $currency->id == $pr->currency_id ) {
						$value = $pr->value;
						$prices[$pr->currency_id] = (object) array(
							'id'          => $pr->id,
							'value'       => $value,
							'placeholder' => $currency_converter->convert( $field_value, $internal_currency->code, $currency->code ),
							'code'        => $currency->code
						);
					}
				}
				if ( !isset( $prices[$currency->id] ) ) {
					$prices[$currency->id] = (object) array(
						'id'          => '',
						'value'       => '',
						'placeholder' => $currency_converter->convert( $field_value, $internal_currency->code, $currency->code ),
						'code'        => $currency->code
					);
				}
			}

			$this->viewData['prices']              = $prices;
			$this->viewData['edit_session_key']    = $session_key;
			$this->viewData['master_object_class'] = $object_class;
			$this->viewData['master_object_id']    = $object_id;
			$this->viewData['field']               = $field_name;
		} catch ( exception $ex ) {
			$this->_controller->handlePageError( $ex );
		}

		$this->renderPartial( 'price_popup' );
	}

	public function onSavePriceFields( $model_id = null ) {
		try {
			$object_id    = post( 'master_object_id', null );
			$session_key  = post( 'edit_session_key' );
			$field_name        = post( 'field', 'price' );
			$object_class = post( 'master_object_class', $this->_controller->form_model_class  );
			
				
			$model = new $object_class();
			if ( $object_id ) {
				$model = $model->find( $object_id );
			}
			
			$form_prefix = post( 'form_prefix' );

			foreach ( $_POST[$form_prefix . 'shop_currency_prices'] as $currency_id => $values ) {

				$price_record = null;
				$setting_price = ( isset( $values['price'] ) && is_numeric($values['price']) );
				$price_records  = Shop_CurrencyPriceRecord::create();
				if ( isset( $values['id'] ) && $values['id'] ) {
					$price_record = $price_records->find( $values['id'] );
				}

				if ( !$price_record ) {
					$price_record = Shop_CurrencyPriceRecord::create();
					$price_record->currency_id  = $currency_id;
					$price_record->master_object_class = class_exists($object_class) ? $object_class : null;
					$price_record->master_object_id = $model->get_primary_key_value();
					$price_record->master_field_name = $field_name;
					$price_record->deferred_session_key = $session_key;
				}
				if ( $setting_price ) {
					$price_record->value = $values['price'];
					$price_record->save();
				} else if($price_record->id){
					$price_record->delete();
				}
			}
		} catch ( Exception $ex ) {
			Phpr::$response->ajaxReportException( $ex, true, true );
		}
	}


}
