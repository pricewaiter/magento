<?php

class PriceWaiter_NYPWidget_Helper_Data extends Mage_Core_Helper_Abstract
{
    const PRICEWAITER_API_URL = 'https://api.pricewaiter.com';
    const PRICEWAITER_RETAILER_URL = 'https://retailer.pricewaiter.com';
    const PRICEWAITER_WIDGET_URL = 'https://widget.pricewaiter.com';

    const XML_PATH_API_KEY = 'pricewaiter/configuration/api_key';
    const XML_PATH_BUTTON_ENABLED = 'pricewaiter/configuration/enabled';
    const XML_PATH_CONVERSION_TOOLS_ENABLED = 'pricewaiter/conversion_tools/enabled';
    const XML_PATH_DEFAULT_ORDER_STATUS = 'pricewaiter/orders/default_status';
    const XML_PATH_DISABLE_BY_CUSTOMER_GROUP = 'pricewaiter/customer_groups/disable';
    const XML_PATH_BUTTON_DISABLED_CUSTOMER_GROUPS = 'pricewaiter/customer_groups/group_select';
    const XML_PATH_CONVERSION_TOOLS_DISABLED_CUSTOMER_GROUPS = 'pricewaiter/conversion_tools/customer_group_disable';
    const XML_PATH_DISABLED_BY_CATEGORY = 'pricewaiter/categories/disable_by_category';
    const XML_PATH_SECRET = 'pricewaiter/configuration/api_secret';

    const CACHE_TAG = 'pricewaiter_configuration';

    /**
     * Clears any caches dependent on PriceWaiter config data.
     */
    public function clearCache()
    {
        Mage::app()->cleanCache(array(
            self::CACHE_TAG,
        ));
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
     * @param  Mage_Core_Model_Store|number|null $store
     * @return String|false  API key or false if not configured.
     */
    public function getPriceWaiterApiKey($store = null)
    {
        $apiKey = Mage::getStoreConfig(self::XML_PATH_API_KEY, $store);
        return $apiKey ? (string)$apiKey : false;
    }

    public function getPriceWaiterSettingsUrl()
    {
        return $this->getRetailerUrl();
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

    /**
     * Returns the secret token used when communicating with PriceWaiter.
     * @return {String} Secret token
     */
    public function getSecret($store = null)
    {
        $token = Mage::getStoreConfig(self::XML_PATH_SECRET, $store);

        if (is_null($token) || $token == '') {
            $token = bin2hex(openssl_random_pseudo_bytes(24));
            $config = Mage::getModel('core/config');
            $config->saveConfig(self::XML_PATH_SECRET, $token);
        }

        return $token;
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
                self::XML_PATH_API_KEY,
                $store->getId()
            );

            if (strcasecmp($apiKey, $storeApiKey) === 0) {
                return $store;
            }
        }

        return false;
    }

    /**
     * @param  Mage_Core_Model_Store|number|null $store
     * @return String|false The widget.js URL, or false if not available.
     */
    public function getWidgetJsUrl($store = null)
    {
        $apiKey = $this->getPriceWaiterApiKey($store);

        if (!$apiKey) {
            return false;
        }

        // Allow overriding widget js url via ENV
        $url = getenv('PRICEWAITER_WIDGET_URL');

        if (!$url) {
            $url = self::PRICEWAITER_WIDGET_URL;
        }

        $apiKey = rawurlencode($apiKey);
        return "{$url}/script/$apiKey.js";
    }

    /**
     * @param  array  $categories An array of categories (or category ids).
     * @param  Mage_Core_Model_Store|number|null  $store
     * @return boolean Whether the button is enabled for *at least one* of the given categories.
     */
    public function isButtonEnabledForAnyCategory(array $categories, $store = null)
    {
        return $this->isFeatureEnabledForAnyCategory(
            $categories,
            $store,
            'isActive' // e.g. $nypcategory->isActive()
        );
    }

    /**
     * @param  $customerGroup
     * @param  Mage_Core_Model_Store|number|null  $store
     * @return boolean
     */
    public function isButtonEnabledForCustomerGroup($customerGroup, $store = null)
    {
        return $this->isFeatureEnabledForCustomerGroup(
            $customerGroup,
            $store,
            self::XML_PATH_BUTTON_DISABLED_CUSTOMER_GROUPS
        );
    }
        /**
     * @param  Mage_Core_Model_Store|number|null  $store
     * @return boolean Whether the PW button is enabled for the given store.
     */
    public function isButtonEnabledForStore($store = null)
    {
        $enabled = !!Mage::getStoreConfig(self::XML_PATH_BUTTON_ENABLED, $store);
        $apiKey = Mage::getStoreConfig(self::XML_PATH_API_KEY, $store);

        return $enabled && $apiKey;
    }

    /**
     * @param  $customerGroup
     * @param  Mage_Core_Model_Store|number|null  $store
     * @return boolean
     */
    public function isConversionToolsEnabledForCustomerGroup($customerGroup, $store = null)
    {
        return $this->isFeatureEnabledForCustomerGroup(
            $customerGroup,
            $store,
            self::XML_PATH_CONVERSION_TOOLS_DISABLED_CUSTOMER_GROUPS
        );
    }

    /**
     * @param  array  $categories An array of categories (or category ids).
     * @param  Mage_Core_Model_Store|number|null  $store
     * @return boolean Whether the conversion tools are enabled for *at least one* of the given categories.
     */
    public function isConversionToolsEnabledForAnyCategory(array $categories, $store = null)
    {
        return $this->isFeatureEnabledForAnyCategory(
            $categories,
            $store,
            'isConversionToolsEnabled' // e.g. $nypcategory->isConversionToolsEnabled()
        );
    }

    /**
     * @param  Mage_Core_Model_Store|number|null  $store
     * @return boolean Whether the PW conversion tools are enabled for the given store.
     */
    public function isConversionToolsEnabledForStore($store = null)
    {
        $enabled = !!Mage::getStoreConfig(self::XML_PATH_CONVERSION_TOOLS_ENABLED, $store);
        $apiKey = Mage::getStoreConfig(self::XML_PATH_API_KEY, $store);

        return $enabled && $apiKey;
    }

    /**
     * @param  array   $categories
     * @param  Mage_Core_Model_Store|number|null  $store
     * @param  string $categoryGetter
     * @return boolean
     */
    protected function isFeatureEnabledForAnyCategory(
        array $categories,
        $store,
        $categoryGetter
    )
    {
        $store = Mage::app()->getStore($store);

        // See if we're even doing "disable by category"
        // This helps avoid recursive category lookups...
        $areDisablingByCategory = Mage::getStoreConfig(self::XML_PATH_DISABLED_BY_CATEGORY, $store);
        if (!$areDisablingByCategory) {
            return true;
        }

        // Resolve the $categories array into a an actual
        // array of Mage_Catalog_Model_Category instances
        $resolvedCategories = array();
        foreach ($categories as $cat) {
            if (is_object($cat)) {
                $resolvedCategories[] = $cat;
            } else if (is_numeric($cat)) {
                // Load category by id
                $cat = Mage::getModel('catalog/category')->load($cat);
                if ($cat->getId()) {
                    $resolvedCategories[] = $cat;
                }
            }
        }

        $enabled = false;

        foreach($resolvedCategories as $category) {
            // We store category config in a parallel model.
            $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category, $store->getId());
            if ($nypcategory->$categoryGetter()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  object|number  $customerGroup
     * @param  Mage_Core_Model_Store|number|null  $store
     * @param  string  $xmlPath       Path to the setting that holds the customer group ids.
     * @return boolean
     */
    protected function isFeatureEnabledForCustomerGroup(
        $customerGroup,
        $store,
        $xmlPath
    )
    {
        $anyDisabledByCustomerGroup = Mage::getStoreConfig(self::XML_PATH_DISABLE_BY_CUSTOMER_GROUP, $store);

        if (!$anyDisabledByCustomerGroup) {
            // Not using "disable by customer group" feature
            return true;
        }

        $id = is_object($customerGroup) ?
            $customerGroup->getId() :
            $customerGroup;

        $customerGroupIds = Mage::getStoreConfig($xmlPath, $store);
        $customerGroupIds = explode(',', $customerGroupIds);

        // $customerGroupIds contains ids of groups for which feature is *disabled*

        if (in_array($id, $customerGroupIds)) {
            return false;
        }

        return true;
    }
}
