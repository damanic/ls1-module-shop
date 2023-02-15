 <?php

     class Shop_ShippingTrackerProvider extends Db_ActiveRecord implements Shop_ShippingTracker {

         public $table_name = 'shop_shipping_tracker_providers';
         protected $trackingCodePlaceHolder = '{tracking-code}';

         public static function create() {
             return new self();
         }

         public function define_columns( $context = null ) {
             $this->define_column( 'name', 'Name' )->validation()->fn( 'trim' )->required('Please enter a name')->unique('This tracker name is already in use. Choose another');
             $this->define_column( 'tracker_url_format', 'Tracking URL format' )
                 ->validation()
                 ->fn( 'trim' )
                 ->fn('strtolower')
                 ->method('validateTrackerUrlFormat')
                 ->required( "Please specify a name" );
         }

         public function define_form_fields( $context = null ) {
             $this->add_form_field( 'name' )->comment('Enter a name for the tracking provider. This is usually the shipping carriers name','above');
             $this->add_form_field( 'tracker_url_format' )->comment('Use '.$this->trackingCodePlaceHolder.' as the placeholder for the tracking code. Example: https://site.com/tracker/{tracking-code}','above');
         }

         public function before_delete($id = null){
             $in_use = Db_DbHelper::scalar('select count(*) from shop_order_shipping_track_codes where shop_shipping_tracker_provider_id=?', $this->id);

             if ($in_use)
                 throw new Phpr_ApplicationException("Cannot delete this provider because it has been assigned to a tracking code.");
         }


         public function getName()
         {
             return $this->name;
         }

         public function getDescription()
         {
             return 'URL Tracker';
         }

         public function getTrackerUrl($trackingCode, $orderId=null)
         {
             return str_replace($this->trackingCodePlaceHolder,$trackingCode,$this->tracker_url_format);
         }

         /*
          * NO API, return empty array
          */
         public function getTrackerDetails($trackingCode, $orderId=null)
         {
             return array();
         }

         protected function validateTrackerUrlFormat($name, $value){
             $pass = true;
             if(!stristr($value, $this->trackingCodePlaceHolder)){
                 $pass = false;
             }
             $testUrl = $this->getTrackerUrl('1234');
             if(!filter_var($testUrl, FILTER_VALIDATE_URL)){
                 $pass = false;
             }
             if(!$pass){
                 $this->validation->setError("Tracking URL Format ".$value." must be a valid URL and contain the tracking code placeholder ".$this->trackingCodePlaceHolder, $name, true);
             }
             return $pass;
         }
     }