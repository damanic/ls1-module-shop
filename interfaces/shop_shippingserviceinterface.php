<?php
interface Shop_ShippingServiceInterface {

    /**
     * @return string The name of the shipping carrier delivering this service
     */
    public function getCarrierName();

    /**
     * @return string The name of the shipping carriers service
     */
    public function getServiceName();

    /**
     * @return boolean If the service provides end to end tracking
     */
    public function providesTracking();

    /**
     * @return boolean If the service provides proof of delivery
     */
    public function providesProofOfDelivery();

    /**
     * @return boolean If the carrier requires a phone number for delivery recipient
     */
    public function requiresRecipientPhoneNumber();

    /**
     * Although not strictly enforced, this method is expected to return any combination of the
     * following values : ["EXW", "FCA", "CPT", "CIP", "DAT", "DAP", "DDP", "FAS", "FOB", "CFR", "CIF"]
     * Ref: International Chamber of Commerce (ICC)
     * Note: DDU replaced with DAP as of 2010
     * @return array An array of incoterms this service can be shipped under
     */
    public function supportedIncoterms();

    /**
     * @return int Estimated number of days required to deliver this service
     */
    public function getDeliveryDays();

}