<?php

class PriceWaiter_NYPWidget_ProductinfoController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        // Ensure that we have received POST data
        $requestBody = Mage::app()->getRequest()->getRawBody();
        $postFields = Mage::app()->getRequest()->getPost();
        Mage::helper('nypwidget')->setHeaders();

        $productHelper = Mage::helper('nypwidget/product');

        if (count($postFields) == 0) {
            $this->norouteAction();
            return;
        }

        // Validate the request
        // - return 400 if signature cannot be verified
        $signature = Mage::app()->getRequest()->getHeader('X-PriceWaiter-Signature');
        if (Mage::helper('nypwidget')->isPriceWaiterRequestValid($signature, $requestBody) == false) {
            Mage::app()->getResponse()->setHeader('HTTP/1.0 400 Bad Request Error', 400, true);
            return false;
        }

        // Process the request
        // - return 404 if the product does not exist (or PriceWaiter is not enabled)
        $productConfiguration = array();
        parse_str(urldecode($postFields['_magento_product_configuration']), $productConfiguration);

        // always lookup the product with a low quantity
        // the below code will fail if the product is out of stock
        if ($productConfiguration && isset($productConfiguration['qty'])) {
            $productConfiguration['qty'] = 1;
        }

        // Create a cart and add the product to it
        // This is necessary to make Magento calculate the cost of the item in the correct context.
        try {
            $productInformation = $productHelper->lookupData($productConfiguration);

            if ($productInformation) {
                // Sign response and send.
                $json = json_encode($productInformation);
                $signature = Mage::helper('nypwidget')->getResponseSignature($json);

                Mage::app()->getResponse()->setHeader('X-PriceWaiter-Signature', $signature);
                Mage::app()->getResponse()->setBody($json);
            } else {
                Mage::app()->getResponse()->setHeader('HTTP/1.0 404 Not Found', 404, true);
                return;
            }
        } catch (Exception $e) {
            Mage::log("Unable to fulfill PriceWaiter Product Information request for product ID: " . $productConfiguration['product']);
            Mage::log($e->getMessage());
            Mage::app()->getResponse()->setHeader('HTTP/1.0 404 Not Found', 404, true);
            return;
        }
    }
}
