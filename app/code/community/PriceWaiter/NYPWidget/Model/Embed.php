<?php

/**
 * Model responsible for figuring how to format PW's embed on the product page.
 *
 * @method Mage_Core_Model_Store getStore() Gets the store being embedded on.
 * @method Mage_Customer_Model_Customer getCustomer()
 * @method Mage_Catalog_Model_Product getProduct()
 * @method Mage_Catalog_Model_Category getCategory()
 */
class PriceWaiter_NYPWidget_Model_Embed
    extends Varien_Object
{
    // Cache values for these because they can be expensive to compute.
    protected $_isButtonEnabled = null;
    protected $_isConversionToolsEnabled = null;
    protected $_scriptTags = null;

    const IMAGE_WIDTH = 800;

    /**
     * Build a variable describing *all* categories product is part of.
     * @param  Mage_Catalog_Model_Product $product
     * @param Mage_Core_Model_Store $store
     * @return Array
     */
    public function buildCategoriesVar(Mage_Catalog_Model_Product $product, Mage_Core_Model_Store $store)
    {
        $categorization = array();
        $assignedCategories = $product->getCategoryCollection()
            ->addAttributeToSelect('name');

        $baseUrl = $store->getBaseUrl();

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

        return $categorization;
    }

    /**
     * Builds a variable describing the custom options configuration for $product.
     * @param  Mage_Catalog_Model_Product $product
     * @return Array
     */
    public function buildCustomOptionsVar(Mage_Catalog_Model_Product $product)
    {
        $options = $product->getOptions();
        $result = array();

        foreach($options as $opt) {
            $jsonOpt = array(
                'id' => $opt->getId(),
                'type' => $opt->getType(),
                'title' => $opt->getTitle(),
                'required' => !!$opt->getIsRequire(),
            );

            $sku = $opt->getSku();
            if ($sku !== null && $sku !== '') {
                $jsonOpt['sku'] = $opt->getSku();
            }

            $values = $opt->getValues();
            if ($values) {
                $jsonOpt['values'] = array();
                foreach($values as $v) {
                    $jsonValue = array(
                        'id' => $v->getId(),
                        'title' => $v->getTitle(),
                    );

                    $sku = $v->getSku();
                    if ($sku !== null && $sku !== '') {
                        $jsonValue['sku'] = $v->getSku();
                    }

                    $jsonOpt['values'][] = $jsonValue;
                }
            }

            $result[] = $jsonOpt;
        }

        return $result;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return Array|false A variable mapping simple product ids to skus, or false if not available.
     */
    public function buildIdToSkuVar(Mage_Catalog_Model_Product $product)
    {
        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            // This is only used for configurables
            return false;
        }

        $simples = Mage::getModel('catalog/product_type_configurable')
            ->setProduct($product)
            ->getUsedProductCollection()
            ->addAttributeToSelect('sku');

        $idsToSkus = array();

        foreach ($simples as $simple) {
            $id = $simple->getId();
            $sku = $simple->getSku();
            $idsToSkus[$id] = $sku;
        }

        return count($idsToSkus) > 0 ? $idsToSkus : false;
    }

    /**
     * @return Object An object containing the PriceWaiterOptions structure.
     */
    public function buildPriceWaiterOptionsVar()
    {
        $options = new StdClass();

        if (!$this->isButtonEnabled()) {
            $options->enableButton = false;
        }

        if (!$this->isConversionToolsEnabled()) {
            $options->enableConversionTools = false;
        }

        $currency = $this->getStore()->getCurrentCurrencyCode();
        if ($currency) {
            $options->currency = $currency;
        }

        $options->metadata = (object)array(
            '_magento_version' => Mage::helper('nypwidget/about')->getPlatformVersion(),
            '_magento_extension_version' => Mage::helper('nypwidget/about')->getExtensionVersion(),
        );

        $product = $this->getProduct();
        if ($product) {
            $options->product = $this->buildProductObject($product);
        }

        // Set user.email and postal_code when available
        $customer = $this->getCustomer();
        if ($customer && $customer->getId()) {
            $options->user = new StdClass();
            $options->user->email = $customer->getEmail();

            $addr = $customer->getDefaultShippingAddress();
            if ($addr && $addr->getId()) {
                $postcode = $addr->getPostcode();
                if ($postcode) {
                    $options->postal_code = $postcode;
                }

                $country = $addr->getCountryId(); // 2-char ISO code, e.g. 'US'
                if ($country) {
                    $options->country = $country;
                }
            }
        }

        return $options;
    }

    /**
     * Builds the `product` object for PriceWaiterOptions.
     * @param  Mage_Catalog_Model_Product $product
     * @return Object
     */
    public function buildProductObject(Mage_Catalog_Model_Product $product)
    {
        $result = new StdClass();
        $result->sku = $product->getSku();
        $result->name = $product->getName();

        $brand = $this->getProductBrand($product);
        if ($brand !== false) {
            $result->brand = $brand;
        }

        $imageHelper = Mage::helper('catalog/image');
        $image = (string)$imageHelper->init($product, 'image')->resize(self::IMAGE_WIDTH);
        if ($image) {
            $result->image = $image;
        } else {
            $image = $product->getImageUrl();
            if ($image) {
                $result->image = $image;
            }
        }

        // If possible, set the base price.
        // For configurables etc, platform JS will have to take over and
        // dynamically calculate price.
        $price = $product->getFinalPrice();
        if ($price > 0) {
            $result->price = $price;
        }

        return $result;
    }

    /**
     * @return array An array of script tags to be rendered onto the page.
     */
    public function getScriptTags()
    {
        // NOTE: Anything coming out of this method is rendered directly to
        //       the page. Be sure to escape your HTML etc.

        if ($this->_scriptTags !== null) {
            return $this->_scriptTags;
        }

        $store = $this->getStore();

        $helper = Mage::helper('nypwidget');
        $widgetJsUrl = $helper->getWidgetJsUrl($store);

        if (!$widgetJsUrl) {
            // Can't actually embed.
            $this->_scriptTags = array();
            return $this->_scriptTags;
        }

        $JSON_OPTIONS = 0;
        if (defined('JSON_UNESCAPED_SLASHES')) {
            $JSON_OPTIONS |= JSON_UNESCAPED_SLASHES;
        }

        // First, a <script> tag containing global JS variables used
        // by our platform JS.

        $variablesTag = array('<script>');
        foreach($this->getJavascriptVariables() as $name => $value) {
            $value = json_encode($value, $JSON_OPTIONS);
            $variablesTag[] = "var $name = $value;";
        }
        $variablesTag[] = '</script>';

        // Then, the actual PW embed
        $embedTag = array(
            '<script src="',
            htmlspecialchars($widgetJsUrl),
            '" async></script>',
        );

        $this->_scriptTags = array(
            implode('', $variablesTag),
            implode('', $embedTag),
        );
        return $this->_scriptTags;
    }

    /**
     * @return Array An array of global variables to register on the page. Key is variable name, value is value (to be JSON-encoded);
     */
    public function getJavascriptVariables()
    {
        $vars = array(
            'PriceWaiterOptions' => $this->buildPriceWaiterOptionsVar(),
        );

        $product = $this->getProduct();
        $store = $this->getStore();

        if ($product) {
            // Provide a hint to JS about what *kind* of product we're looking at.
            $vars['PriceWaiterProductType'] = $product->getTypeId();

            // Platform JS picks this up to refer back to.
            // TODO: It seems like we shoudl not have to provide PriceWaiterRegularPrice--
            //       there should be enough data available already on the frontend to
            //       figure this out.
            $vars['PriceWaiterRegularPrice'] = (double)$product->getPrice();

            // Provide frontend a means to map product ids to skus
            // (Used for configurable product support)
            $idsToSkus = $this->buildIdToSkuVar($product);
            if ($idsToSkus !== false) {
                $vars['PriceWaiterIdToSkus'] = $idsToSkus;
            }

            // Provide detailed information about product categories
            $vars['PriceWaiterCategories'] = $this->buildCategoriesVar($product, $store);

            // Provide data about custom options (SKU modifiers + labels, mostly)
            $custom = $this->buildCustomOptionsVar($product);
            if ($custom) {
                $vars['PriceWaiterCustomOptions'] = $custom;
            }
        }

        return $vars;
    }

    /**
     * @return boolean Whether the PW button is enabled.
     */
    public function isButtonEnabled()
    {
        if ($this->_isButtonEnabled === null) {
            $this->_isButtonEnabled = $this->isFeatureEnabled(
                'isButtonEnabledForStore',
                'isButtonEnabledForCustomerGroup',
                'isButtonEnabledForAnyCategory'
            );
        }

        return $this->_isButtonEnabled;
    }

    /**
     * @return boolean Whether PW's non-button "conversion tools" are enabled.
     */
    public function isConversionToolsEnabled()
    {
        if ($this->_isConversionToolsEnabled === null) {
            $this->_isConversionToolsEnabled = $this->isFeatureEnabled(
                'isConversionToolsEnabledForStore',
                'isConversionToolsEnabledForCustomerGroup',
                'isConversionToolsEnabledForAnyCategory'
            );
        }

        return $this->_isConversionToolsEnabled;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return String|false
     */
    public function getProductBrand(Mage_Catalog_Model_Product $product)
    {
        $attributesToTry = array(
            'brand',
            'manufacturer',
            'c2c_brand',
        );

        foreach($attributesToTry as $attr) {
            $value = $this->safeGetAttributeText($product, $attr);
            if ($value) {
                return $value;
            }
        }

        return false;
    }

    /**
     * @param Mage_Catalog_Model_Category $category
     */
    public function setCategory($category)
    {
        $this->invalidateCachedValues();
        return parent::setCategory($category);
    }

    /**
     * @param Number $id
     */
    public function setCustomerGroupId($id)
    {
        $this->invalidateCachedValues();
        return parent::setCustomerGroupId($id);
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     */
    public function setProduct($product)
    {
        $this->invalidateCachedValues();
        return parent::setProduct($product);
    }

    /**
     * @param Mage_Core_Model_Store $store
     */
    public function setStore($store)
    {
        $this->invalidateCachedValues();
        return parent::setStore($store);
    }

    /**
     * @return Boolean Whether we should render the placeholder span.
     */
    public function shouldRenderButtonPlaceholder()
    {
        // 1. If no <script> tags to render, no placeholder
        $scriptTags = $this->getScriptTags();
        if (empty($scriptTags)) {
            return false;
        }

        // If Button + Conversion tools disabled, no placeholder required.
        // (Exit Intent can lead to button being shown later, so we need
        // the placeholder present.)
        if ($this->isButtonEnabled() || $this->isConversionToolsEnabled()) {
            return true;
        }

        return false;
    }

    /**
     * @internal Resets cached values
     */
    protected function invalidateCachedValues()
    {
        $this->_isButtonEnabled = null;
        $this->_isConversionToolsEnabled = null;
        $this->_scriptTags = null;
    }

    /**
     * @internal
     * @return boolean
     */
    protected function isFeatureEnabled(
        $isEnabledForStore,
        $isEnabledForCustomerGroup,
        $isEnabledForAnyCategory
    )
    {
        $store = $this->getStore();
        $product = $this->getProduct();

        if (!$store) {
            return false;
        }

        if (!$product) {
            return false;
        }

        $helper = Mage::helper('nypwidget');

        if (!$helper->$isEnabledForStore($store)) {
            // Store has us globally disabled
            return false;
        }

        $customerGroupId = $this->getCustomerGroupId();
        if (!$helper->$isEnabledForCustomerGroup($customerGroupId, $store)) {
            // Disabled for customer group
            return false;
        }

        $category = $this->getCategory();
        if ($category) {
            // We're looking at a product page via a specific category.
            $enabled = $helper->$isEnabledForAnyCategory(
                array($category),
                $store
            );

            return $enabled;
        }

        // Look at *all* categories the product is in. If *any* of them
        // enable the button, enable it here.
        // We can hit this code path when viewing the product page *not*
        // through the lens of a certain category (i.e. when linked from
        // the home page or search results).
        return $helper->$isEnabledForAnyCategory(
            $product->getCategoryIds(),
            $store
        );
    }

    protected function safeGetAttributeText(Mage_Catalog_Model_Product $product, $code)
    {
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
}
