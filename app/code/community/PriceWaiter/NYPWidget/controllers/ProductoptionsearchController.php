<?php

class PriceWaiter_NYPWidget_ProductoptionsearchController
    extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $httpRequest = $this->getRequest();
        $httpResponse = $this->getResponse();

        Mage::helper('nypwidget/about')->setResponseHeaders($httpResponse);

        // Ensure that we have received POST data
        $requestBody = $httpRequest->getRawBody();
        $postFields = $httpRequest->getPost();

        // Validate the request
        // - return 400 if signature cannot be verified
        $signature = $httpRequest->getHeader('X-PriceWaiter-Signature');
        if (!Mage::helper('nypwidget/signing')->isPriceWaiterRequestValid($signature, $requestBody)) {
            $httpResponse->setHttpResponseCode(400);
            return false;
        }

        try
        {
            $sku = $postFields['productId'];
            $product = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('sku', $sku)
                ->addAttributeToSelect('*')
                ->getFirstItem();

            $children = array();

            // ensure we are looking at a configurable product
            if ($product->getId() && $product->getTypeId() !== 'simple') {

                $attributeCodeMap = array();

                $type = $product->getTypeInstance(true);
                $productAttributeOptions = $type->getConfigurableAttributesAsArray($product);
                foreach ($productAttributeOptions as $productAttribute) {
                    $attributeCodeMap[$productAttribute['attribute_code']] = $productAttribute['store_label'];
                }

                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($product);
                $collection = $conf->getUsedProductCollection()
                        ->addAttributeToSelect('*');
                foreach ($collection as $child) {

                    $item = array(
                        'sku' => $child->getSku(),
                        'price' => $child->getPrice(),
                        'image' => $child->getImageUrl(),
                        'quantity' => (int)$child->getStockItem()->getQty(),
                        'options' => array(),
                    );

                    foreach ($attributeCodeMap as $key => $value) {
                        $item['options'][] = array(
                            "name" => $value,
                            "value" => $child->getAttributeText($key),
                        );
                    }

                    $children[] = $item;
                }
            }

            // And finally, return it.
            $json = json_encode($children);

            $httpResponse->setHeader('X-PriceWaiter-Signature', Mage::helper('nypwidget/signing')->getResponseSignature($json));
            $httpResponse->setHeader('Content-Type', 'application/json');
            $httpResponse->setBody($json);
        }
        catch (Exception $ex)
        {
            error_log('error' . $ex->getMessage());
            Mage::logException($ex);

            $httpResponse->setHttpResponseCode(404);

            // Extra: Include an error code if we have one.
            if ($ex instanceof PriceWaiter_NYPWidget_Exception_Abstract) {
                $httpResponse->setHeader('X-PriceWaiter-Error', $ex->errorCode);
            }
        }
    }
}
