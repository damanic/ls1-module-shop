<?php

/**
 * Represents the generic shipping type.
 * All other shipping types must be derived from this class
 */
abstract class Shop_ShippingType extends Core_XmlController implements Shop_ShippingProviderInterface
{

    protected $eventParameters = array();

    /**
     * Returns information about the shipping type
     * Must return array with key 'name': array('name'=>'FedEx')
     * Also the result can contain an optional 'description'
     * @return array
     */
    abstract public function get_info();

    /**
     * Builds the shipping type administration user interface
     * For drop-down and radio fields you should also add methods returning
     * options. For example, of you want to have Sizes drop-down:
     * public function get_sizes_options();
     * This method should return array with keys corresponding your option identifiers
     * and values corresponding its titles.
     *
     * @param Shop_ShippingOption $host_obj ActiveRecord object to add fields to
     * @param string $context Form context. In preview mode its value is 'preview'
     */
    abstract public function build_config_ui($host_obj, $context = null);

    /**
     * Builds the user interface for printing the shipping labels.
     * Implementing this method is not required if no special parameters
     * are required for printing the shipping label.
     *
     * @param Shop_ShippingOption $host_obj ActiveRecord object to add fields to
     * @param Shop_Order $order Order object.
     */
    public function build_print_label_ui($host_obj, $order)
    {
    }

    /**
     * Validates configuration data before it is saved to database
     * Use host object field_error method to report about errors in data:
     * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
     * @param $host_obj Shop_ShippingOption object containing configuration fields values
     */
    abstract public function validate_config_on_save($host_obj);

    /**
     * Validates configuration data after it is loaded from database
     * Use host object to access fields previously added with build_config_ui method.
     * You can alter field values if you need
     * @param $host_obj Shop_ShippingOption object containing configuration fields values
     */
    public function validate_config_on_load($host_obj)
    {
    }

    /**
     * Initializes configuration data when the shipping option is first created
     * Use host object to access and set fields previously added with build_config_ui method.
     * @param $host_obj Shop_ShippingOption object containing configuration fields values
     */
    public function init_config_data($host_obj)
    {
    }

    /**
     * Determines whether a list of countries should be displayed in the
     * configuration form. For most shipping  methods the country list should be displayed.
     */
    public function config_countries()
    {
        return true;
    }

    /**
     * Initializes shipping label parameters for a new order.
     * @param $host_obj ActiveRecord object containing configuration fields values.
     * @param Shop_Order $order The order object.
     */
    public function init_order_label_parameters($host_obj, $order)
    {
    }


    public function getProviderName(){
        $providerInfo = $this->get_info();
        return isset($providerInfo['name']) ? $providerInfo['name'] : 'unknown';
    }
    /**
     * This implementation provides backwards compatibility for third party shipping types
     * that have not implemented Shop_ShippingProviderInterface;
     * @see Shop_ShippingProviderInterface
     */
    public function listEnabledShippingServices(Shop_ShippingOption $shippingOption){
        return $this->list_enabled_options($shippingOption);
    }


    public function supportsMultipleShippingServices(Shop_ShippingOption $shippingOption){
        if(count($this->listEnabledShippingServices($shippingOption)) > 1){
            return true;
        }
        return false;
    }


    /**
     * This implementation provides backwards compatibility for third party shipping types
     * that have not implemented Shop_ShippingProviderInterface;
     * @see Shop_ShippingProviderInterface
     */
    public function getItemRates(Shop_ShippingOption $shippingOption, array $items, Shop_AddressInfo $toAddress, Shop_AddressInfo $fromAddress = null, $context = ''){

        $rates = array();

        $totalValue = 0;
        $totalVolume = 0;
        $totalWeight = 0;
        $totalItems = 0;

        foreach($items as $cartItem){
            $totalValue += $cartItem->get_total_offer_price();
            $totalVolume += $cartItem->total_volume(false); //ignores items marked free shipping
            $totalWeight += $cartItem->total_weight(false); //ignores items marked free shipping
            $totalItems += $cartItem->quantity;
        }

        $params = array(
            'host_obj'=>$shippingOption,
            'country_id'=>$toAddress->country,
            'state_id'=>$toAddress->state,
            'zip'=>$toAddress->zip,
            'city'=>$toAddress->city,
            'is_business'=>$toAddress->is_business,
            'total_price'=> $totalValue,
            'total_volume'=>$totalVolume,
            'total_weight'=>$totalWeight,
            'total_item_num'=>$totalItems,
            'cart_items'=>$items,
        );

        $params = array_merge($params, $this->getEventParameters());
        $quote = $this->get_quote($params);
        try{
            $shippingProvider = $this->get_shippingtype_object();
        } catch (Exception $e) {
            $shippingProvider = null;
        }
        if(is_array($quote)){
            foreach($quote as $serviceName => $quoteEntry){
                if(!isset($quoteEntry['id'])){
                    continue;
                }
                $rate = new Shop_ShippingRate();
                $rate->setId($quoteEntry['id']);
                $rate->setShippingOptionId($shippingOption->id);
                if($shippingProvider){
                    $rate->setShippingProviderClassName(get_class($shippingProvider));
                }
                $rate->setShippingServiceName($serviceName);
                $rate->setRate($quoteEntry['quote']);
                $rates[] = $rate;
            }
        } else if(is_numeric($quote)) {
                $rate = new Shop_ShippingRate();
                $rate->setShippingOptionId($shippingOption->id);
                if($shippingProvider){
                    $rate->setShippingProviderClassName(get_class($shippingProvider));
                }
                $rate->setShippingServiceName($shippingOption->name);
                $rate->setRate($quote);
                $rates[] = $rate;
        }

        return $rates;

    }


    /**
     * This implementation provides backwards compatibility for third party shipping types
     * that have not implemented Shop_ShippingProviderInterface;
     * @see Shop_ShippingProviderInterface
     */
    public function generateShippingLabels(Shop_ShippingOption $shippingOption, Shop_Order $order, $postData = array()){
        return $this->generate_shipping_labels($shippingOption, $order, $postData);
    }

    /**
     * This implementation provides backwards compatibility for third party shipping types
     * that have not implemented Shop_ShippingProviderInterface;
     * @see Shop_ShippingProviderInterface
     */
    public function getShippingTracker()
    {
        return null;
    }

    /**
     * This implementation provides backwards compatibility for third party shipping types
     * that have not implemented Shop_ShippingProviderInterface;
     * @see Shop_ShippingProviderInterface
     */
    public function supportsRates(){
        return true;
    }

    /**
     * This implementation provides backwards compatibility for third party shipping types
     * that have not implemented Shop_ShippingProviderInterface;
     * @see Shop_ShippingProviderInterface
     */
    public function getPackedBoxRates(Shop_ShippingOption $shippingOption, Shop_AddressInfo $toAddress, array $packedBoxes, Shop_AddressInfo $fromAddress = null, $context = ''){

        $items = array();
        foreach($packedBoxes as $packedBox){
            foreach($packedBox->get_items() as $item){
                $items[] = $item;
            }
        }
        return $this->getItemRates($shippingOption, $items,  $toAddress, $fromAddress, $context);
    }

    /**
     * This implementation provides backwards compatibility for third party shipping types
     * that have not implemented Shop_ShippingProviderInterface;
     * @see Shop_ShippingProviderInterface
     */
    public function supportsLabels()
    {
        return $this->supports_shipping_labels();
    }

    public function setEventParameters(array $parameters)
    {
        $this->eventParameters = $parameters;
    }

    public function getEventParameters(){
        return $this->eventParameters;
    }


    /**
     * @deprecated use generateShippingLabels()
     * Sends request to the server and returns the shipping label data.
     * The method should also set the order shipping tracking number.
     * @param $host_obj ActiveRecord object containing configuration fields values
     * @param Shop_Order $order The order to generate the label form
     * @param array $parameters Optional list of the shipping method specific parameters.
     * @return array Returns an array of Shop_ShippingLabel objects representing the shipping labels.
     */
    public function generate_shipping_labels($host_obj, $order, $parameters = array())
    {
        return null;
    }

    /**
     * @deprecated use supportsLabels()
     * This method should return TRUE if the shipping module supports label printing.
     * The shipping module must implement the generate_shipping_label() method if this method returns true.
     */
    public function supports_shipping_labels()
    {
        return false;
    }


    /**
     * @deprecated use listEnabledShippingServices()
     * The Discount Engine uses this method for displaying a list of available shipping options on the
     * Free Shipping tab of the Cart Price Rule form.
     *
     * For multi-options shipping methods (like USPS or UPS) this method should return a list of
     * enabled options in the following format:
     * array(
     *   array('name'=>'First class', 'id'=>'first_class'),
     *   array('name'=>'Priority', 'id'=>'priority'),
     * )
     * The options identifiers must match the identifiers returned by the get_quote() method.
     * @param $host_obj Shop_ShippingOption object containing configuration fields values
     * @return array
     */
    public function list_enabled_options($host_obj)
    {
        return array();
    }

    /**
     * @deprecated
     * @see Shop_ShippingProviderInterface::getItemRates()
     *
     * Previously an abstract method for implementation.
     * Shipping types/providers should implement Shop_ShippingProviderInterface::getItemRates()
     *
     * Returns price of shipping. If shipping type is not applicable, returns null.
     * If there is only one shipping service available for this shipping option,
     * returns its quote.
     * If there is more than one shipping service available, for example UPS Express,
     * UPS Expedited, UPS Standard, the method must return an array of shipping service names,
     * identifiers and quotes:
     * array(
     *        'UPS Express'=>array('id'=>'express', 'quote'=>12.29),
     *        'UPS Expedited'=>array('id'=>'expedited, 'quote'=>32.12)
     * )
     * The shipping service identifiers must match the identifiers returned by the list_enabled_options() method
     * @param array $parameters Contains the method parameters. The array has the following keys:
     *  - host_obj ActiveRecord object containing configuration fields values
     *  - country_id Specifies shipping country id
     *  - state_id Specifies shipping state id
     *  - zip Specifies shipping zip/postal code
     *  - city Specifies shipping city name
     *  - total_price Specifies total price of items in the shopping cart
     *  - total_volume Specifies total volume of items in the shopping cart
     *  - total_weight Specifies total weight of items in the shopping cart
     *  - total_item_num Specifies total number of items in the shopping cart
     *  - cart_items a list of cart items (Shop_CartItem objects)
     *  - is_business Determines whether the shipping address is a business address
     *  - currency ISO currency code (three character alpha) for the returned shipping rate. If left blank, shop currency is assumed.
     * @return mixed
     */
     public function get_quote($parameters){
         traceLog('Use of deprecated method: Shop_ShippingType::get_quote(). See: Shop_ShippingProviderInterface::getItemRates() ');
         return null;
     }


}


