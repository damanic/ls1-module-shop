<?php

class Shop_ShippingTrackerDetail {

    protected $trackingCode;
    protected $message;
    protected $statusCode;
    protected $datetime;

    private $statusCodes = array(
        "unknown" => 'Unknown',
        "pre_transit" => 'Pre transit',
        "in_transit" => 'In transit',
        "out_for_delivery" => 'Out for delivery',
        "delivered" => 'Delivered',
        "available_for_pickup" => 'Available for pickup',
        "return_to_sender" => 'Return to sender',
        "failure" => 'Failure',
        "cancelled" => 'Cancelled',
        "error" => 'Error',
    );

    /**
     * @param string $trackingCode
     * @param string $message
     * @param string $statusCode
     * @param Phpr_DateTime $datetime
     */
    public function __construct($trackingCode, $message, $statusCode, Phpr_DateTime $datetime){
        if(!in_array($statusCode,$this->statusCodes )){
            throw new Phpr_ApplicationException('Invalid status code');
        }
        $this->trackingCode = (string) $trackingCode;
        $this->message = (string) $message;
        $this->statusCode = (string) $statusCode;
        $this->datetime = $datetime;
    }

    /**
     * @return string Tracking Code
     */
    public function getTrackingCode(){
        return $this->trackingCode;
    }

    /**
     * @return string Status Name
     */
    public function getStatusName()
    {
        return $this->statusCodes[$this->statusCode];
    }

    /**
     * @return string Status Code
     */
    public function getStatusCode(){
        return $this->statusCode;
    }

    /**
     * @return string Description of Tracking Event
     */
    public function getMessage(){
        return $this->message;
    }

    /**
     * @return Phpr_DateTime  A datetime obj representing time tracking event was recorded
     */
    public function getDateTime(){
        return $this->datetime;
    }

}