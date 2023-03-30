<?php

class Shop_ShippingOptionQuote{


   public $discount;

   protected $deprecatedProperties = array(
       'quote' => null,
       'quote_no_discount' => null,
       'quote_no_tax' => null,
       'quote_tax_incl' => null,
       'is_free' => null
   );

   protected $price;
   protected $isFree;
   protected $taxInclMode;
   protected $quoteData;
   protected $shippingOptionId;
   protected $shippingServiceName;
   private $rateInfo;
   private $currencyCode;
   private static $optionCache = array();


    /**
     * Create a quote from a shipping rate object.
     * @param Shop_ShippingRate $rate
     * @param string $currencyCode The quote will be returned in the given currency. If not given the quote currency will match the rate currency.
     * @return Shop_ShippingOptionQuote
     */
   public static function createFromShippingRate(Shop_ShippingRate $rate, $currencyCode = null){
       $shippingOptionId = $rate->getShippingOptionId();
       if(!$shippingOptionId){
           throw new Phpr_ApplicationException('A shipping option quote can only be created from shipping rates that contain a valid shipping option ID');
       }
       $inst = new self($rate->getShippingOptionId());
       $inst->setShippingServiceName($rate->getShippingServiceName());
       $inst->setPrice($rate->getRate($currencyCode));

       if($currencyCode) {
           $inst->setCurrencyCode($currencyCode);
       }
       $inst->setRateInfo($rate);
       return $inst;
   }

   /**
     * @param int $shippingOptionId ID for associated Shop_ShippingOption record
     */
   public function __construct($shippingOptionId){

       $this->shippingOptionId = (int) $shippingOptionId;
   }

    /**
     * Set the quoted price
     * @param string $value Monetary value  (decimal number)
     * @return void
     */
   public function setPrice($value){
       if(!is_numeric($value)){
           throw new Phpr_ApplicationException('Quote value must be numeric');
       }
       $this->price = max($value,'0');
   }

    /**
     * Get the quoted price after discounts
     * @return string|null Monetary value or null if quote not determined
     */
   public function getPrice(){

       //legacy consideration
       if($this->taxInclMode && $this->deprecatedProperties['quote_tax_incl']){
           return $this->deprecatedProperties['quote_tax_incl'];
       }

       if( $this->price === null) {
           return null;
       }

       $price = $this->price - $this->getDiscount();
       return (string) max($price , 0);
   }

    /**
     * Set an amount to discount the price by
     * @param string $value Monetary value (decimal number)
     * @return void
     */
   public function setDiscount($value){
       if(!is_numeric($value)){
           throw new Phpr_ApplicationException('Discount value must be numeric');
       }
       $this->discount = max($value,'0');
   }

    /**
     * Get the amount the price has been discounted by
     * @return string $value Monetary value (decimal number)
     */
   public function getDiscount(){
       if($this->price === null || $this->discount === null){
           return '0';
       }
       //discount cannot be more than price
       return (string) min($this->discount, $this->price);
   }

    /**
     * Get the price before discount
     * @return string $value Monetary value (decimal number)
     */
   public function getPriceNoDiscount(){
       if($this->deprecatedProperties['quote_no_discount']){
           return $this->deprecatedProperties['quote_no_discount'];
       }

       if( $this->price === null) {
           return null;
       }

       return (string) $this->price;
   }

    /**
     * Check if the quoted option is free
     * @return bool
     */
    public function isFree(){
        if($this->getPrice() == '0' || $this->isFree){
            return true;
        }
        return false;
    }

    /**
     * Mark the quote as FREE
     * @param boolean $value
     * @return void
     */
    public function setIsFree($value){
        $this->isFree  = (bool)$value;
    }



    /**
     * Set the name for the shipping service quoted (customer facing)
     * @param $name
     * @return void
     */
   public function setShippingServiceName($name){
       $this->shippingServiceName = $name;
   }

    /**
     * Returns the name for the shipping service quoted (customer facing)
     * @return string|null
     */
   public function getShippingServiceName(){
       if($this->shippingServiceName){
           return $this->shippingServiceName;
       }
       $shippingOption = $this->getShippingOption();
       return $shippingOption ? $shippingOption->name : null;
   }

    /**
     * Returns the Shop_ShippingOption ID from which this quote derived
     * @return int
     */
   public function getShippingOptionId(){
       return (int) $this->shippingOptionId;
   }

    /**
     * Attach the Shop_ShippingRate object from which this quote derived
     * @param Shop_ShippingRate $rate
     * @return void
     */
   public function setRateInfo(Shop_ShippingRate $rate){
        $this->rateInfo = $rate;
   }

    /**
     * Return the Shop_ShippingRate object from which this quote derived
     * @return Shop_ShippingRate|null
     */
   public function getRateInfo(){
       return $this->rateInfo;
   }

    /**
     * This method can be used by shipping provider or event
     * subscribers to attach arbitrary data.
     * Warning: This data does not persist in session/cache.
     * @param array $data
     * @return void
     */
   public function setQuoteData(array $data){
       $this->quoteData = $data;
   }

    /**
     * Returns arbitrary data that may have been attached
     * by shipping provider or event subscribers.
     * @return array
     */
   public function getQuoteData(){
       return $this->quoteData;
   }

    /**
     * Define the currency for the values on this quote
     * @param string $currencyCode ISO currency code
     * @return void
     */
   public function setCurrencyCode($currencyCode){
       $currencyCode = strtoupper(trim($currencyCode));
       if(strlen($currencyCode) !== 3){
           throw new Phpr_ApplicationException('A three character currency code is required for conversion');
       }
       $this->currencyCode = $currencyCode;
   }

    /**
     * Get the currency for the values on this quote
     * @return string ISO currency code
     */
   public function getCurrencyCode(){
       if(!$this->currencyCode){
           return $this->currencyCode = Shop_CurrencySettings::get()->code;
       }
       return $this->currencyCode;
   }

    /**
     * Convert the values on this quote to the given currency code
     * @param string $currencyCode ISO currency code
     * @return void
     */
    public function currencyConvert($currencyCode){
        $currencyCode = strtoupper(trim($currencyCode));
        if(strlen($currencyCode) !== 3){
            throw new Phpr_ApplicationException('A three character currency code is required for conversion');
        }
        if($this->getCurrencyCode() !== $currencyCode) {
            $currency_converter = Shop_CurrencyConverter::create();
            $fromCurrency = $this->getCurrencyCode();
            $this->price = $currency_converter->convert($this->price, $fromCurrency, $currencyCode);
            $this->discount = $currency_converter->convert($this->discount, $fromCurrency, $currencyCode);

            $deprecatedValues = array(
                'quote',
                'quote_no_tax',
                'quote_tax_incl',
                'quote_no_discount'
            );
            foreach($deprecatedValues as $value){
                if($this->deprecatedProperties[$value] !== null){
                    $this->deprecatedProperties[$value] = $currency_converter->convert(
                        $this->deprecatedProperties[$value],
                        $fromCurrency,
                        $currencyCode
                    );
                }
            }
            $this->setCurrencyCode($currencyCode);
        }

    }

    /**
     * Returns a unique ID for this shipping quote
     * This is used by form selectors
     * @return string
     */
    public function getShippingQuoteId(){
       return $this->getShippingOptionId().'_'.md5($this->getShippingServiceName());
    }

    /**
     * Return the shipping option object associated with this quote;
     * @return Shop_ShippingOption|null
     */
    public function getShippingOption(){

       if($this->shippingOptionId){
           if(isset(self::$optionCache[$this->shippingOptionId])){
               return self::$optionCache[$this->shippingOptionId];
           }
           $option = Shop_ShippingOption::create()->find($this->shippingOptionId);
           if(!$option){
               $option = null;
           }
           return self::$optionCache[$this->shippingOptionId] = $option;
       }

       return null;
    }

    /**
     * Helper method for legacy support
     * This can be used to inject a shippingOption
     * into cache that has had quotes applied.
     * Effectively this will update the object
     * returned by getShippingOption();
     * @param Shop_ShippingOption $shippingOption
     * @return void
     */
    public function setCachedShippingOption(Shop_ShippingOption $shippingOption){
        self::$optionCache[$shippingOption->id] = $shippingOption;
    }

    /**
     * Use of this method is discouraged. Tax considerations
     * should not be handled by this class.
     *
     * If set to true the getPrice() method will return
     * the price including tax (if applied to deprecated property `quote_tax_incl`)
     * @param boolean $value
     * @return void
     */
    public function setTaxInclMode($value){
        $this->taxInclMode = $value;
    }

    public function __get($name){

        //Support legacy code, access deprecated properties
        if($name == 'quote'){
            traceLog('Use of deprecated property `quote`. Query getPrice()');
            if($this->deprecatedProperties[$name] !== null){
                return $this->deprecatedProperties[$name];
            }
            return $this->getPrice();
        }
        if($name == 'quote_no_discount'){
            traceLog('Use of deprecated property `quote_no_discount`. Query getPriceNoDiscount()');
            if($this->deprecatedProperties[$name] !== null){
                return $this->deprecatedProperties[$name] ?: 0;
            }
            return $this->getPriceNoDiscount();
        }
        if($name == 'quote_no_tax'){
            traceLog('Use of deprecated property `quote_no_tax`');
            if($this->deprecatedProperties[$name] !== null){
                return $this->deprecatedProperties[$name] ?: 0;
            }
            return $this->getPrice();
        }
        if($name == 'quote_tax_incl'){
            traceLog('Use of deprecated property `quote_tax_incl`');
            if($this->deprecatedProperties[$name] !== null){
                return $this->deprecatedProperties[$name] ?: 0;
            }
            return $this->getPrice();
        }
        if($name == 'is_free'){
            traceLog('Use of deprecated property `is_free`. Use method isFree()');
            return $this->isFree();
        }

        //Support legacy code, shipping option properties
        if($name === 'id'){
            traceLog('Use of deprecated property `id` Use getShippingOptionId() or getShippingQuoteId()');
            if($this->shippingServiceName){
                $optionName = $this->getShippingOption()->name;
                $serviceName = $this->getShippingServiceName();
                if($optionName !== $serviceName) {
                    return $this->getShippingQuoteId();
                }
            }
            return $this->getShippingOptionId();
        }

        if($name === 'suboption_id' || $name === 'sub_option_id'){
            traceLog('Use of deprecated property `sub_option_id` || `suboption_id`. Use Shop_ShippingRate::getShippingQuoteId()');
            $optionName = $this->getShippingOption()->name;
            $serviceName = $this->getShippingServiceName();
            if($optionName == $serviceName){
                return null; //backwards compat
            }
            return $this->getShippingQuoteId();
        }

        if($name == 'internal_id'){
            traceLog('Use of deprecated property `internal_id`. Use Shop_ShippingRate::getShippingQuoteId()');
            return $this->getShippingQuoteId();
        }

        if($name === 'name'){
            traceLog('Use of deprecated property `name`. Use getShippingServiceName()');
            return $this->getShippingServiceName();
        }

        if($name === 'quote_data'){
            traceLog('Use of deprecated property `quote_data`. Use getQuoteData()');
            return $this->getQuoteData();
        }

        if($name == 'ls_api_code'){
            traceLog('Use of deprecated property `ls_api_code`. Use getShippingOption()->ls_api_code');
            $shippingOption = $this->getShippingOption();
            return $shippingOption ? $shippingOption->ls_api_code : null;
        }

        if($name == 'multi_option_name'){
            traceLog('Use of deprecated property `multi_option_name`. Use getShippingOption()->name');
            $shippingOption = $this->getShippingOption();
            if($shippingOption){
                if($shippingOption->get_shippingtype_object()->supportsMultipleShippingServices($shippingOption)){
                    return  $shippingOption->name;
                }
            }
            return null;
        }

        if($name == 'multi_option_id'){
            traceLog('Use of deprecated property `multi_option_id`. Use getShippingOptionId()');
            return $this->getShippingOptionId();
        }

        if($name == 'multi_option'){
            traceLog('Use of deprecated property `multi_option`. Query the shipping option object, not the shipping quote');
            $shippingOption = $this->getShippingOption();
            return $shippingOption ? $shippingOption->get_shippingtype_object()->supportsMultipleShippingServices($shippingOption) : false;
        }

        if($name == 'description'){
            traceLog('Use of deprecated property `description`. Use getShippingOption()->description');
            $shippingOption = $this->getShippingOption();
            return $shippingOption ? $shippingOption->description : null;
        }

        if($name == 'error_hint'){
            traceLog('Use of deprecated property `error_hint`. Query the shipping option object, not the shipping quote');
            $shippingOption = $this->getShippingOption();
            return $shippingOption ? $shippingOption->error_hint : null;
        }

        //support legacy code, maintain access to shipping option extended properties
        $shippingOption = $this->getShippingOption();
        if($shippingOption){
            $shippingOption->define_columns();
            if(in_array($name, $shippingOption->api_added_columns)){
                return $shippingOption->{$name};
            }
        }

        return null;
    }

    public function __set($name, $value){
        if(array_key_exists($name, $this->deprecatedProperties)){
            $this->deprecatedProperties[$name] = $value;
            if($name == 'quote'){
                $this->setPrice($value);
            }
            return;
        }
        if($name == 'is_free'){
            traceLog('Use of deprecated property `is_free`. Use method setIsFree()');
            $this->setIsFree($value);
        }
    }

    public function __sleep(){
        $keepProperties = array();
        $omitProperties = array(
            'quoteData',
            'optionCache'
        );
        $reflect = new ReflectionClass($this);
        $props   = $reflect->getProperties();
        foreach($props as $prop){
            $propName =  $prop->getName();
            if(!in_array($propName, $omitProperties)){
                $keepProperties[] = $propName;
            }
        }
        return $keepProperties;
    }

    /**
     * Helper function. Extracts the shipping option ID from the shipping quote ID
     * @param string $shippingQuoteId as returned by getShippingQuoteId()
     * @return int ID for Shop_ShippingOption record
     */
    public static function getOptionIdFromQuoteId($shippingQuoteId){

        if (strpos((string) $shippingQuoteId, '_') === false)
        {
            throw new Phpr_ApplicationException('Invalid Shipping Quote ID 1');
        }

        $parts = explode('_', $shippingQuoteId);
        $shippingOptionId = $parts[0];

        if(!is_numeric($shippingOptionId)){
            throw new Phpr_ApplicationException('Invalid Shipping Quote ID');
        }

        return $shippingOptionId;
    }



}
