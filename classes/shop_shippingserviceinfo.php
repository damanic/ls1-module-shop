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
        return (bool)$this->providesTracking;
    }

    public function providesProofOfDelivery(){
        return (bool)$this->providesProofOfDelivery;
    }

    public function supportedIncoterms(){
        return is_array($this->supportedIncoterms) ? $this->supportedIncoterms() : array();
    }

    public function getDeliveryDays(){
        return (int) $this->deliveryDays;
    }

    public function requiresRecipientPhoneNumber()
    {
        return (bool)$this->requiresRecipientPhoneNumber;
    }
}