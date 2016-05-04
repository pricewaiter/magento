<?php

class PriceWaiter_NYPWidget_Helper_Data extends Mage_Core_Helper_Abstract
{
    const PRICEWAITER_API_URL = 'https://api.pricewaiter.com';
    const PRICEWAITER_RETAILER_URL = 'https://retailer.pricewaiter.com';

    const XML_PATH_DEFAULT_ORDER_STATUS = 'pricewaiter/orders/default_status';

    private $_product = false;
    private $_buttonEnabled = null;
    private $_conversionToolsEnabled = null;

    private $_widgetUrl = 'https://widget.pricewaiter.com';

    public function __construct()
    {
        if (!!getenv('PRICEWAITER_WIDGET_URL')) {
            $this->_widgetUrl = getenv('PRICEWAITER_WIDGET_URL');
        }
    }

    /**
     * @return String URL of the PriceWaiter API.
     */
    public function getApiUrl()
    {
        $url = getenv('PRICEWAITER_API_URL');

        if ($url) {
            return $url;
        }

        return self::PRICEWAITER_API_URL;
    }

    /**
     * @return String The status to assign to new PriceWaiter orders.
     */
    public function getDefaultOrderStatus($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DEFAULT_ORDER_STATUS, $store);
    }

    /**
     * @param  String $id
     * @return String URL to view the given offer on PriceWaiter.
     */
    public function getOfferUrl($id)
    {
        $url = $this->getRetailerUrl();
        $url = rtrim($url, '/');
        $url .= '/offers/' . rawurlencode($id);

        return $url;
    }

    /**
     * @return String URL to which to POST order data for verification.
     */
    public function getOrderVerificationUrl()
    {
        $url = getenv('PRICEWAITER_ORDER_VERIFICATION_URL');
        if ($url) {
            return $url;
        }

        // Build verification URL off base API url.
        $url = $this->getApiUrl();
        $url = rtrim($url, '/');
        $url .= '/1/order/verify';

        return $url;
    }

    /**
     * @return String The URL of the PriceWaiter Retailer area.
     */
    public function getRetailerUrl()
    {
        $url = getenv('PRICEWAITER_RETAILER_URL');

        if ($url) {
            return $url;
        }

        return self::PRICEWAITER_RETAILER_URL;
    }

    public function isEnabledForStore()
    {
        // Is the pricewaiter widget enabled for this store and an API Key has been set.
        if (Mage::getStoreConfig('pricewaiter/configuration/enabled')
            && Mage::getStoreConfig('pricewaiter/configuration/api_key')
        ) {
            return true;
        }

        return false;
    }

    // Set the values of $_buttonEnabled and $_conversionToolsEnabled
    private function _setEnabledStatus()
    {
        if ($this->_buttonEnabled != null && $this->_conversionToolsEnabled != null) {
            return true;
        }

        if (Mage::getStoreConfig('pricewaiter/configuration/enabled')) {
            $this->_buttonEnabled = true;
        }

        if (Mage::getStoreConfig('pricewaiter/conversion_tools/enabled')) {
            $this->_conversionToolsEnabled = true;
        }

        $product = $this->_getProduct();

        // Is the PriceWaiter widget enabled for this category
        $category = Mage::registry('current_category');
        if (is_object($category)) {
            $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category);
            if (!$nypcategory->isActive()) {
                $this->_buttonEnabled = false;
            }
            if (!$nypcategory->isConversionToolsEnabled()) {
                $this->_conversionToolsEnabled = false;
            }
        } else {
            // We end up here if we are visiting the product page without being
            // "in a category". Basically, we arrived via a search page.
            // The logic here checks to see if there are any categories that this
            // product belongs to that enable the PriceWaiter widget. If not, return false.
            $categories = $product->getCategoryIds();
            $categoryActive = false;
            $categoryCTActive = false;
            foreach ($categories as $categoryId) {
                unset($currentCategory);
                unset($nypcategory);
                $currentCategory = Mage::getModel('catalog/category')->load($categoryId);
                $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($currentCategory);
                if ($nypcategory->isActive()) {
                    if ($nypcategory->isConversionToolsEnabled()) {
                        $categoryCTActive = true;
                    }
                    $categoryActive = true;
                    break;
                }
            }
            if (!$categoryActive) {
                $this->_buttonEnabled = false;
            }

            if (!$categoryCTActive) {
                $this->_conversionToolsEnabled = false;
            }

        }

        // Is PriceWaiter enabled for this Customer Group
        $disable = Mage::getStoreConfig('pricewaiter/customer_groups/disable');
        if ($disable) {
            // An admin has chosen to disable the PriceWaiter widget by customer group.
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            $customerGroups = Mage::getStoreConfig('pricewaiter/customer_groups/group_select');
            $customerGroups = preg_split('/,/', $customerGroups);

            if (in_array($customerGroupId, $customerGroups)) {
                $this->_buttonEnabled = false;
            }
        }

        // Are Conversion Tools  enabled for this Customer Group
        $disableCT = Mage::getStoreConfig('pricewaiter/conversion_tools/customer_group_disable');
        if ($disableCT) {
            // An admin has chosen to disable the Conversion Tools by customer group.
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            $customerGroups = Mage::getStoreConfig('pricewaiter/conversion_tools/group_select');
            $customerGroups = preg_split('/,/', $customerGroups);

            if (in_array($customerGroupId, $customerGroups)) {
                $this->_conversionToolsEnabled = false;
            }
        }
    }

    public function isConversionToolsEnabled()
    {
        $this->_setEnabledStatus();

        return $this->_conversionToolsEnabled;
    }

    public function isButtonEnabled()
    {
        $this->_setEnabledStatus();

        return $this->_buttonEnabled;
    }

    public function getPriceWaiterSettingsUrl()
    {
        return $this->getRetailerUrl();
    }

    public function getWidgetUrl()
    {
        if ($this->isEnabledForStore()) {
            return $this->_widgetUrl . '/script/'
                . Mage::getStoreConfig('pricewaiter/configuration/api_key')
                . ".js";
        }

        return $this->_widgetUrl . '/nyp/script/widget.js';
    }


    public function getProductPrice($product)
    {
        $productPrice = 0;

        if ($product->getId()) {
            if ($product->getTypeId() != 'grouped') {
                $productPrice = $product->getFinalPrice();
            }
        }

        return $productPrice;
    }

    private function safeGetAttributeText($product, $code) {
        $value = $product->getData($code);

        // prevent Magento from rendering "No" when nothing is selected.
        if (!$value) {
            return false;
        }

        $resource = $product->getResource();
        if (!$resource) {
            return false;
        }

        $attr = $resource->getAttribute($code);
        if (!$attr) {
            return false;
        }

        $frontend = $attr->getFrontend();
        if (!$frontend) {
            return false;
        }

        return $frontend->getValue($product);
    }

    public function getProductBrand($product) {

        // prefer brand, but fallback to manufacturer attribute
        $brand = $product->getData('brand');

        if (!$brand) {
            $manufacturer = $this->safeGetAttributeText($product, 'manufacturer');
            if ($manufacturer) {
                $brand = $manufacturer;
            }
        }

        // try looking up popular plugin for brand attribute
        if (!$brand) {
            $manufacturer = $this->safeGetAttributeText($product, 'c2c_brand');
            if ($manufacturer) {
                $brand = $manufacturer;
            }
        }

        return $brand;
    }

    private function _getProduct()
    {
        if (!$this->_product) {
            $this->_product = Mage::registry('current_product');
        }

        return $this->_product;
    }

    public function getGroupedProductInfo()
    {
        $product = $this->_getProduct();
        $javascript = "var PriceWaiterGroupedProductInfo =  new Array();\n";

        $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
        foreach ($associatedProducts as $simpleProduct) {
            $javascript .= "PriceWaiterGroupedProductInfo[" . $simpleProduct->getId() . "] = ";
            $javascript .= "new Array('" . htmlentities($simpleProduct->getName()) . "', '"
                . number_format($simpleProduct->getPrice(), 2) . "')\n";
        }

        return $javascript;
    }

    /**
     * Returns the Magento store configured with the given PriceWaiter API key, or false if none is found.
     * @param  String $apiKey
     * @return Mage_Core_Model_Store|false
     * @throws PriceWaiter
     */
    public function getStoreByPriceWaiterApiKey($apiKey)
    {
        $stores = Mage::app()->getStores();

        foreach ($stores as $store) {

            $storeApiKey = Mage::getStoreConfig(
                'pricewaiter/configuration/api_key',
                $store->getId()
            );

            if (strcasecmp($apiKey, $storeApiKey) === 0) {
                return $store;
            }
        }

        return false;
    }

    /**
     * Returns the secret token used when communicating with PriceWaiter.
     * @return {String} Secret token
     */
    public function getSecret()
    {
        $token = Mage::getStoreConfig('pricewaiter/configuration/api_secret');

        if (is_null($token) || $token == '') {
            $token = bin2hex(openssl_random_pseudo_bytes(24));
            $config = Mage::getModel('core/config');

            $config->saveConfig('pricewaiter/configuration/api_secret', $token);
        }

        return $token;
    }

    /**
     * Returns a signature that can be added to the head of a PriceWaiter API response.
     * @param {String} $responseBody The full body of the request to sign.
     * @return {String} Signature that should be set as the X-PriceWaiter-Signature header.
     */
    public function getResponseSignature($responseBody)
    {
        $signature = 'sha256=' . hash_hmac('sha256', $responseBody, $this->getSecret(), false);
        return $signature;
    }

    /**
     * Validates that the current request came from PriceWaiter.
     * @param {String} $signatureHeader Full value of the X-PriceWaiter-Signature header.
     * @param {String} $requestBody Complete body of incoming request.
     * @return {Boolean} Wehther the request actually came from PriceWaiter.
     */
    public function isPriceWaiterRequestValid($signatureHeader = null, $requestBody = null)
    {
        if ($signatureHeader === null || $requestBody === null) {
            return false;
        }

        $detected = 'sha256=' . hash_hmac('sha256', $requestBody, $this->getSecret(), false);

        if (function_exists('hash_equals')) {
            // Favor PHP's secure hash comparison function in 5.6 and up.
            // For a robust drop-in compatibility shim, see: https://github.com/indigophp/hash-compat
            return hash_equals($detected, $signatureHeader);
        }

        return $detected === $signatureHeader;
    }

    /**
     * Finds the Product that matches the given options and SKU
     * @param {String} $sku SKU of the product
     * @param {Array} $productOptions An array of options for the product, name => value
     * @return {Object} Returns Mage_Catalog_Model_Product of product that matches options.
     * @throws  PriceWaiter_NYPWidget_Exception_Product_NotFound If no product can be found.
     */
    public function getProductWithOptions($sku, $productOptions)
    {
        $product = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('sku', $sku)
            ->addAttributeToSelect('*')
            ->getFirstItem();

        $additionalCost = null;

        if ($product->getTypeId() == 'configurable') {
            // Do configurable product specific stuff
            $attrs = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

            // Find our product based on attributes
            foreach ($attrs as $attr) {
                if (array_key_exists($attr['label'], $productOptions)) {
                    foreach ($attr['values'] as $value) {
                        if ($value['label'] == $productOptions[$attr['label']]) {
                            $valueIndex = $value['value_index'];
                            // If this attribute has a price assosciated with it, add it to the price later
                            if ($value['pricing_value'] != '') {
                                $additionalCost += $value['pricing_value'];
                            }
                            break;
                        }
                    }
                    unset($productOptions[$attr['label']]);
                    $productOptions[$attr['attribute_id']] = $valueIndex;
                }
            }

            $parentProduct = $product;
            $product = $product->getTypeInstance()->getProductByAttributes($productOptions, $product);

            if (!$product) {
                throw new PriceWaiter_NYPWidget_Exception_Product_NotFound();
            }

            $product->load($product->getId());
        }

        if ($additionalCost) {
            $product->setPrice($product->getPrice() + $additionalCost);
        }

        return $product;
    }

    public function getGroupedQuantity($productConfiguration)
    {
        $associatedProductIds = array_keys($productConfiguration['super_group']);
        $quantities = array();
        foreach ($associatedProductIds as $associatedProductId) {
            $associatedProduct = Mage::getModel('catalog/product')->load($associatedProductId);
            $quantities[] = $associatedProduct->getStockItem()->getQty();
        }

        return min($quantities);
    }

    public function getGroupedFinalPrice($productConfiguration)
    {
        $associatedProductIds = array_keys($productConfiguration['super_group']);
        $finalPrice = 0;
        foreach ($associatedProductIds as $associatedProductId) {
            $associatedProduct = Mage::getModel('catalog/product')->load($associatedProductId);
            $finalPrice += ($associatedProduct->getFinalPrice() * $productConfiguration['super_group'][$associatedProductId]);
        }
        return $finalPrice;
    }

    public function getGroupedCost($productConfiguration)
    {
        $associatedProductIds = array_keys($productConfiguration['super_group']);
        $costs = array();
        foreach ($associatedProductIds as $associatedProductId) {
            $associatedProduct = Mage::getModel('catalog/product')->load($associatedProductId);
            $costs[] = $associatedProduct->getData('cost');
        }

        return min($costs);
    }

    public function setHeaders()
    {
        $magentoEdition = 'Magento ' . Mage::getEdition();
        $magentoVersion = Mage::getVersion();
        $extensionVersion = Mage::getConfig()->getNode()->modules->PriceWaiter_NYPWidget->version;
        Mage::app()->getResponse()->setHeader('X-Platform', $magentoEdition, true);
        Mage::app()->getResponse()->setHeader('X-Platform-Version', $magentoVersion, true);
        Mage::app()->getResponse()->setHeader('X-Platform-Extension-Version', $extensionVersion, true);

        return true;
    }

    public function getCategoriesAsJSON($product)
    {
        $categorization = array();
        $assignedCategories = $product->getCategoryCollection()
            ->addAttributeToSelect('name');

        $baseUrl = Mage::app()->getStore()->getBaseUrl();

        // Find the path (parents) of each category, and add their information
        // to the categorization array
        foreach ($assignedCategories as $assignedCategory) {
            $parentCategories = array();
            $path = $assignedCategory->getPath();
            $parentIds = explode('/', $path);
            array_shift($parentIds); // We don't care about the root category

            $categoryModel = Mage::getModel('catalog/category');
            foreach($parentIds as $parentCategoryId) {
                $parentCategory = $categoryModel->load($parentCategoryId);
                $parentCategoryUrl = preg_replace('/^\//', '', $parentCategory->getUrlPath());

                $parentCategories[] = array(
                    'name' => $parentCategory->getName(),
                    'url' => $baseUrl . '/' . $parentCategoryUrl
                );
            }

            $categorization[] = $parentCategories;
        }

        return json_encode($categorization);
    }
}
