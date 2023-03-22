<?php

class Shop_ShippingServiceInfo implements Shop_ShippingServiceInterface {

    public $carrierName;
    public $serviceName;
    public $providesTracking;
    public $providesProofOfDelivery;
    public $supportedIncoterms;
    public $deliveryDays;
    public $requiresRecipientPhoneNumber;

    public function getCarrierName() {
        return (string) $this->carrierName;
    }

    public function getServiceName(){
        return (string) $this->serviceName;
    }

    public function providesTracking(){
        if($this->providesTracking === null){
            return null;
        }
        return (bool)$this->providesTracking;
    }

    public function providesProofOfDelivery(){
        if($this->providesProofOfDelivery === null){
            return null;
        }
        return (bool)$this->providesProofOfDelivery;
    }

    public function getSupportedIncoterms(){
        return is_array($this->supportedIncoterms) ? $this->supportedIncoterms : array();
    }

    public function getDeliveryDays(){
        if($this->deliveryDays === null){
            return null;
        }
        return (int) $this->deliveryDays;
    }

    public function requiresRecipientPhoneNumber()
    {
        if($this->requiresRecipientPhoneNumber === null){
            return null;
        }
        return (bool)$this->requiresRecipientPhoneNumber;
    }
}