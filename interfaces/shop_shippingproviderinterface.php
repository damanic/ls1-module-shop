<?php

interface Shop_ShippingProviderInterface {


    /**
     * @return string Name of shipping provider
     */
    public function getProviderName();

    /**
     * @return bool TRUE if provider can return shipping rates, otherwise FALSE
     */
    public function supportsRates();

    /**
     * Returns shipping rate estimates for a collection of shippable items
     * This is used in checkout and order quote context.
     *
     * NOTE: $items may contain the parameter `free_shipping` added by discount rules.
     * To handle this appropriately the shipping provider needs to find a service that
     * can ship all the items, and then calculate a rate that ignores items `free_shipping`.
     *
     * @param Shop_ShippingOption $shippingOption
     * @param Shop_ShippableItem[] $items
     * @param Shop_AddressInfo $toAddress Usually derived from Shop_CheckoutData::get_shipping_info()
     * @param Shop_AddressInfo|null $fromAddress If not given the senders address in system shipping config can be used
     * @return Shop_ShippingRate[] Iterable collection of shipping rates
     */
    public function getItemRates(Shop_ShippingOption $shippingOption, $items, Shop_AddressInfo $toAddress, Shop_AddressInfo $fromAddress = null);



    /**
     * Return shipping rate estimates for a collection of one or more packed boxes.
     * This should be used when shipping boxes have been packed for dispatch,
     * or when Shop_BoxPacker is being used to assist with shipping estimates.
     * @param Shop_ShippingOption $shippingOption The configuration model for this shipping type
     * @param Shop_AddressInfo $toAddress The destination address
     * @param Shop_PackedBox[] $packedBoxes Iterable collection of packed box objects
     * @param Shop_AddressInfo|null $fromAddress If not given the senders address in system shipping config can be used
     * @return Shop_ShippingRate[] Iterable collection of shipping rate objects
     */
    public function getPackedBoxRates(Shop_ShippingOption $shippingOption, Shop_AddressInfo $toAddress, array $packedBoxes, Shop_AddressInfo $fromAddress = null);

    /**
     * @return bool TRUE if provider can generate shipping labels. otherwise FALSE
     */
    public function supportsLabels();

    /**
     * Generates shipping labels for a given shipping option
     * @param Shop_ShippingOption $shippingOption
     * @param Shop_Order $order The order to which the shipping label data will be attached
     * @param array $postData Post data submitted by the ShippingOption generated form
     * @return mixed
     */
    public function generateShippingLabels(Shop_ShippingOption $shippingOption, Shop_Order $order, $postData = array());

    /**
     * This is used to present all shipping services available from the provider.
     * These options can be used in discount rules and label generation.
     *
     * Returns simple name and id in format:
     *
     * array(
     *   array('name'=>'First class', 'id'=>'first_class'),
     *   array('name'=>'Priority', 'id'=>'priority'),
     * )
     *
     * The option ID should be the same ID returned in Shop_ShippingRate.
     * @param $shippingOption Shop_ShippingOption object containing configuration fields values
     * @return array
     */
    public function listEnabledShippingServices(Shop_ShippingOption $shippingOption);


    /**
     * If the shipping provider returns more than one service return true, otherwise false.
     * @return bool
     */
    public function supportsMultipleShippingServices(Shop_ShippingOption $shippingOption);

    /**
     * Return a class that implements the Shop_ShippingTrackerInterface
     * This can be used to provide access to tracking events over API
     * @return Shop_ShippingTrackerInterface|null
     */
    public function getShippingTracker();


    /**
     * This method supports legacy events.
     * Useful for passing execution context variables.
     * @param array $parameters
     * @return mixed
     */
    public function setEventParameters(array $parameters);

    /**
     * This method supports legacy events.
     * Useful for accessing execution context variables.
     * @return array
     */
    public function getEventParameters();
}