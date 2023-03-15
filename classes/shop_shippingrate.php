<?php
class Shop_ShippingRate{

    /**
     * @var string The rate ID defined by the shipping provider
     */
    protected $id;
    
    /**
     * @var int ID for associated Shop_ShippingOption
     */
    protected $shippingOptionId;

    /**
     * @var string Shipping service name to present to customer
     */
    protected $shippingServiceName;

    /**
     * Provides carrier service information for the rate.
     * @var Shop_ShippingServiceInterface
     */
    protected $carrierServiceInfo;

    /**
     * @var string Name of shipping carrier as returned by shipping provider
     */
    protected $carrierName;

    /**
     * @var string Name of shipping carriers service as returned by shipping provider
     */
    protected $carrierServiceName;


    /**
     * @var string Rate
     */
    protected $rate;

    /**
     * @var string ISO 3 char currency code for rate
     */
    protected $currencyCode;


    /**
     * Set the ID for the rate.
     * This is usually a foreign ID from shipping provider.
     * @param string $id
     * @return void
     */
    public function setId($id){
        $this->id = (string) $id;
    }

    /**
     * Set the Shop_ShippingOption ID associated with this rate
     * @param int $id
     * @return void
     */
    public function setShippingOptionId($id){
        $this->shippingOptionId = $id;
    }

    /**
     * Set the shipping service name for this rate (customer facing)
     * @param string $name
     * @return void
     */
    public function setShippingServiceName($name){
        $this->shippingServiceName = (string) $name;
    }

    /**
     * Allows the shipping provider to include additional service information
     * associated with the rate.
     * @param Shop_ShippingServiceInterface $infoObj
     * @return void
     */
    public function setCarrierServiceInfo(Shop_ShippingServiceInterface $infoObj){
        $this->carrierServiceInfo = $infoObj;
    }

    /**
     * Set the monetary RATE value
     * @param string $rate
     * @param string $currencyCode ISO currency code
     * @return void
     */
    public function setRate($rate, $currencyCode = null){
        $this->rate = (string) $rate;
        $this->currencyCode = (string) $currencyCode;
    }

    /**
     * Get the rate ID
     * @return string
     */
    public function getId(){
        return $this->id;
    }

    /**
     * Get the Shop_ShippingOption ID
     * @return int
     */
    public function getShippingOptionId(){
        return $this->shippingOptionId;
    }

    /**
     * Get the shipping service name (customer facing)
     * @return string
     */
    public function getShippingServiceName(){
        return $this->shippingServiceName;
    }


    /**
     * Get information on the service associated with this rate
     * @return Shop_ShippingServiceInterface
     */
    public function getCarrierServiceInfo(){
        return $this->carrierServiceInfo;
    }


    /**
     * Get the monetary value.
     * If currency code is given, value will be converted to given currency.
     * @param string $currencyCode
     * @return string
     */
    public function getRate($currencyCode = null){
        if($currencyCode && $currencyCode !== $this->getCurrencyCode()){
            $currency_converter  = Shop_CurrencyConverter::create();
            $currency_converter->convert( $this->rate, $this->getCurrencyCode(), $currencyCode );
        }
        return $this->rate;
    }

    /**
     * Get the ISO currency code for this rate
     * @return string
     */
    public function getCurrencyCode(){
        if(!$this->currencyCode){
            return $this->currencyCode = Shop_CurrencySettings::get()->code;
        }
        return $this->currencyCode;
    }


}
