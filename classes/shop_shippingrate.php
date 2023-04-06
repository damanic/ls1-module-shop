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
     * @var string|null Class name for associated ShippingProviderInterface
     */
    protected $shippingProviderClassName = null;

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
     * Set the ShippingProviderInterface class name associated with this rate
     * @param string $className
     * @return void
     */
    public function setShippingProviderClassName($className){
        $this->shippingProviderClassName = $className;
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
        if(!$this->id){
            return $this->getShippingOptionId().'_'.md5($this->getShippingServiceName());
        }
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
     * If a name has not been assigned, the name
     * will be returned from getCarrierServiceName()
     * @return string
     */
    public function getShippingServiceName(){
        if(!$this->shippingServiceName){
            return $this->getCarrierServiceName();
        }
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
     * Returns the carrier service description/name for this rate
     * from carrier service info.
     * @return string|null
     */
    public function getCarrierServiceName()
    {
        $carrierServiceInfo = $this->getCarrierServiceInfo();
        if($carrierServiceInfo){
            return trim($carrierServiceInfo->getCarrierName().' '.$carrierServiceInfo->getServiceName());
        }
        return null;
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
            return $currency_converter->convert( $this->rate, $this->getCurrencyCode(), $currencyCode );
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

    /**
     * Get the ShippingProvider associated with this rate
     * If ShippingProvider cannot be determined null will be returned.
     * @return Shop_ShippingProviderInterface|null
     */
    public function getShippingProvider(){

        if($this->shippingProviderClassName){
            if(Phpr::$classLoader->load($this->shippingProviderClassName)) {
                $className = $this->shippingProviderClassName;
                return new $className();
            }
        }
        if($this->shippingOptionId){
            try {
                $shippingOption = $this->getShippingOption();
                if ($shippingOption) {
                    return $shippingOption->get_shippingtype_object();
                }
            }catch(\Exception $e){
                return null;
            }
        }
        return null;
    }

    /**
     * Get the ShippingOption associated with this rate
     * If no ShippingOption ID is set, null will be returned.
     * @return Shop_ShippingOption|null
     */
    public function getShippingOption()
    {
        if($this->shippingOptionId) {
            $shippingOption = Shop_ShippingOption::create()->find($this->shippingOptionId);
            if(is_a($shippingOption,'Shop_ShippingOption') && $shippingOption->id){
                return $shippingOption;
            }
        }
        return null;
    }


}
