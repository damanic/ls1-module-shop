<?php
interface Shop_ShippingTrackerInterface{


    /**
     * Returns a name for the tracker provider
     * @return string
     */
    public function getName();

    /**
     * Returns a description of capabilities for the tracker provider
     * @return string
     */
    public function getDescription();

    /**
     * Returns a tracking URL for a given tracking code
     * @param string $trackingCode
     * @param int $orderId Provided if the tracking code is associated with a Shop_Order
     * @return string tracking URL
     */
    public function getTrackerUrl($trackingCode, $orderId=null);

    /**
     * @return bool TRUE if getTrackerDetails can be provided, otherwise FALSE
     */
    public function providesTrackerDetails();

    /**
     * If the tracker can provide real time tracking detail over API it
     * can return the details of tracking events as an array of Shop_ShippingTrackerDetail
     * objects
     * @param string $trackingCode
     * @param int $orderId Provided if the tracking code is associated with a Shop_Order
     * @return Shop_ShippingTrackerDetail[] An array of tracker detail objects
     */
    public function getTrackerDetails($trackingCode, $orderId=null);
}