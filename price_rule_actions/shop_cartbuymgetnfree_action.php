<?php

/**
 * @deprecated
 * CartBuyMGetNFree_Action class
 *
 * This action has been superseded by CartBuyMGetNDiscounted_Action.
 *
 * This file prevents old discount rules that have a reference to CartBuyMGetNFree_Action from breaking.
 *
 * The class_exists() check in this file will make sure the autoloader
 * loads the CartBuyMGetNDiscounted_Action which is compatible with and has a class alias for CartBuyMGetNFree_Action
 */

\class_exists('Shop_CartBuyMGetNDiscounted_Action');
