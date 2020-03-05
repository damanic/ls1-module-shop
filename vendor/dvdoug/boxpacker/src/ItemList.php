<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

/**
 * List of items to be packed, ordered by volume.
 *
 * @author Doug Wright
 */
class ItemList extends \SplMaxHeap
{
    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     *
     * @see \SplMaxHeap::compare()
     *
     * @param mixed $itemA
     * @param mixed $itemB
     *
     * @return int
     */
    public function compare($itemA, $itemB)
    {
        if ($itemA->getVolume() > $itemB->getVolume()) {
            return 1;
        } elseif ($itemA->getVolume() < $itemB->getVolume()) {
            return -1;
        } elseif ($itemA->getWeight() !== $itemB->getWeight()) {
            return $itemA->getWeight() - $itemB->getWeight();
        } elseif ($itemA->getDescription() < $itemB->getDescription()) {
            return 1;
        } else {
            return -1;
        }
    }

    /**
     * Do a bulk create.
     *
     * @param  Item[]   $items
     * @return ItemList
     */
    public static function fromArray(array $items)
    {
        $list = new static();
        foreach ($items as $item) {
            $list->insert($item);
        }
        return $list;
    }

    /**
     * Get copy of this list as a standard PHP array.
     *
     * @return Item[]
     */
    public function asArray()
    {
        $return = [];
        foreach (clone $this as $item) {
            $return[] = $item;
        }

        return $return;
    }

    /**
     * @internal
     *
     * @param  int      $n
     * @return ItemList
     */
    public function topN($n)
    {
        $workingList = clone $this;
        $topNList = new self();
        $i = 0;
        while(!$workingList->isEmpty() && $i < $n) {
            $topNList->insert($workingList->extract());
            $i++;
        }

        return $topNList;
    }

    /**
     * Remove item from list.
     *
     * @param Item $item
     */
    public function remove(Item $item)
    {
        $workingSet = [];

        foreach ($this as $that) {
            if ($that === $item) {
                $this->extract();
                break;
            } else {
                $workingSet[] = $that;
            }
        }

        foreach ($workingSet as $workingSetItem) {
            $this->insert($workingSetItem);
        }

    }
}
