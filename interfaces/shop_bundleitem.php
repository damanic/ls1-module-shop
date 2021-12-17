<?php

/**
 * Interface Shop_BundleItemInterface
 * For retail items that support product bundle offers  (@see Shop_ProductBundleOffer)
 * Establishes common methods for interaction with Shop_Cart_Item and Shop_Order_Item
 */


interface Shop_BundleItem {

    /**
     * Check if item is part of a bundle
     * @return bool Return true if item is bundled and dependent on another item, otherwise false.
     */
    public function is_bundle_item();

    /**
     * Check if item has bundled items
     * @return bool Return true if this item has bundled items attached, otherwise false
     */
    public function has_bundle_items();

    /**
     * For items that are master bundle retail items, this will return an array of bundled retail items.
     * @documentable
     * @return array Returns an array of {@link Shop_RetailItem} compatible objects.
     */
    public function get_bundle_items();

    /**
     * For items that are master bundle retail items, this will return the total discount applied to the bundle.
     * @documentable
     * @return float Returns the amount discounted or zero.
     */
    public function get_bundle_discount();

    /**
     * For items that are master bundle retail items,
     * this will return the unit price for all items in the bundle without any discounts
     * @documentable
     * @return float Returns the list price for a single bundle
     */
    public function get_bundle_list_price();

    /**
     * For items that are master bundle retail items,
     * this will return the price for a single bundle after discounts
     * @documentable
     * @return float Returns the offer price for a single bundle
     */
    public function get_bundle_offer_price();

    /**
     * For items that are master bundle retail items,
     * this will return the price for bundle before discounts,
     * multiplied by the total bundle quantity ordered.
     * @documentable
     * @return float Returns the total list price for the bundle quantity ordered
     */
    public function get_bundle_total_list_price();

    /**
     * For items that are master bundle retail items,
     * this will return the price for the bundle after discounts,
     * multiplied by the total bundle quantity ordered.
     * @documentable
     * @return float Returns the total offer price for the bundle quantity ordered
     */
    public function get_bundle_total_offer_price();

    /**
     * Returns the bundle offer this item belongs to
     * @return mixed Returns the Shop_ProductBundleOffer associated with this item, or NULL
     */
    public function get_bundle_offer();



}