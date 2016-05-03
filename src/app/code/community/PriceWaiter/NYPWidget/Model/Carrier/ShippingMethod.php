<?php

class PriceWaiter_NYPWidget_Model_Carrier_ShippingMethod
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'nypwidget';
    protected $_isFixed = true;

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        return;
    }

    public function getAllowedMethods()
    {
        return;
    }
}
