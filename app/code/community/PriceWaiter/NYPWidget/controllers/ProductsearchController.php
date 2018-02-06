<?php

class PriceWaiter_NYPWidget_ProductsearchController
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
            $search = $postFields['search'];
            $field = $postFields['field'];

            if (!in_array($field, array('name', 'sku'))) {
                $httpResponse->setHttpResponseCode(400);
                return false;
            }

            $products = Mage::getResourceModel('catalog/product_collection')
                ->setFlag('require_stock_items', true)
                ->addAttributeToSelect('*')
                ->addAttributeToFilter($field, array('like' => '%'.$search.'%'))
                ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->load();

            $embed = Mage::getModel('nypwidget/embed');

            $result = array();
            foreach ($products as $product) {
                $brand = $embed->getProductBrand($product);

                $result[] = array(
                    'id' => $product->getSku(),
                    'name' => $product->getName(),
                    'sku' => $product->getSku(),
                    'brand' => $brand ? $brand : '',
                    'price' => $product->getFinalPrice(),
                    'image' => $product->getImageUrl(),
                    'quantity' => (int)$product->getStockItem()->getQty(),
                    'has_children' => $product->getTypeId() !== 'simple',
                );
            }

            // And finally, return it.
            $json = json_encode($result);

            $httpResponse->setHeader('X-PriceWaiter-Signature', Mage::helper('nypwidget/signing')->getResponseSignature($json));
            $httpResponse->setHeader('Content-Type', 'application/json');
            $httpResponse->setBody($json);
        }
        catch (Exception $ex)
        {
            error_log('error');
            Mage::logException($ex);

            $httpResponse->setHttpResponseCode(404);

            // Extra: Include an error code if we have one.
            if ($ex instanceof PriceWaiter_NYPWidget_Exception_Abstract) {
                $httpResponse->setHeader('X-PriceWaiter-Error', $ex->errorCode);
            }
        }
    }

}
