<?php


/**
 * Exception thrown when we encounter product data that cannot be resolved to a
 * single product, for example when we receive a metadata product id pointing
 * one way but a SKU pointing another. This is generally indicative of a
 * problem
 */
class PriceWaiter_NYPWidget_Exception_Product_InconsistentData extends PriceWaiter_NYPWidget_Exception_Product_Abstract
{
    public $productFoundById = null;
    public $productFoundBySku = null;
}
