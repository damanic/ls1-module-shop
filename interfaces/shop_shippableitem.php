<?php

/**
 * Interface Shop_ShippableItem
 * Establishes common methods for interaction with Shop_Cart_Item and Shop_Order_Item
 */

interface Shop_ShippableItem {

    /**
     * Returns the volume of one item.
     * @documentable
     * @return float Returns the item volume.
     */
    public function volume();

    /**
     * Returns the total volume of the item.
     * The total volume is <em>unit volume * quantity</em>.
     * @documentable
     * @return float Returns the item total volume.
     */
    public function total_volume();

    /**
     * Returns the weight of one item.
     * @documentable
     * @return float Returns the item weight.
     */
    public function weight();

    /**
     * Returns the total weight of the item.
     * The total weight is <em>unit weight * quantity</em>.
     * @documentable
     * @return float Returns the item total weight.
     */
    public function total_weight();

    /**
     * Returns the depth of one item.
     * @documentable
     * @return float Returns the item depth.
     */
    public function depth();

    /**
     * Returns the total depth of the item.
     * The total depth is <em>unit depth * quantity</em>.
     * @documentable
     * @return float Returns the item total depth.
     */
    public function total_depth();

    /**
     * Returns the width of one item.
     * @documentable
     * @return float Returns the item width.
     */
    public function width();

    /**
     * Returns the total width of the item.
     * The total width is <em>unit width * quantity</em>.
     * @documentable
     * @return float Returns the item total width.
     */
    public function total_width();

    /**
     * Returns the height of one item.
     * @documentable
     * @return float Returns the item height.
     */
    public function height();

    /**
     * Returns the total height of the item.
     * The total height is <em>unit height * quantity</em>.
     * @documentable
     * @return float Returns the item total height.
     */
    public function total_height();

}