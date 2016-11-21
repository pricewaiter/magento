<?php

/**
 * Represents a single item in a PriceWaiter Offer / Deal.
 *
 * This item can map to more than 1 Magento product (for example, if the offer
 * was for a bundle product).
 */
class PriceWaiter_NYPWidget_Model_Offer_Item
{
    /**
     * Key in metadata that holds the serialized add to cart form.
     */
    const ADD_TO_CART_FORM_METADATA_KEY = '_magento_product_configuration';

    /**
     * The absolute minimum quantity we support for things. Numbers lower
     * than this will be set to this value.
     */
    const MINIMUM_QUANTITY = 1;

    /**
     * @var Object
     */
    private $_data;

    /**
     * @internal Cached array representation of item metadata.
     * @var Array
     */
    private $_metadataArray = null;

    /**
     * @internal Cached array of product option data.
     * @var Array
     */
    private $_optionsArray = null;

    /**
     * ID of the Magento store this item is for.
     * @var integer
     */
    protected $_storeId;

    /**
     * @param Object $data
     * @param Object|integer $store Store (or just ID) this item is for.
     */
    public function __construct($data = null, $store = null)
    {
        $this->_data = $data ? $data : array();

        if ($store === null) {
            $store = Mage::app()->getStore();
        }

        if ($store instanceof Mage_Core_Model_Store) {
            $this->_storeId = $store->getId();
        } else if (is_numeric($store)) {
            $this->_storeId = $store;
        } else {
            throw new InvalidArgumentException(__CLASS__ . ' constructor requires store.');
        }
    }

    /**
     * Adds the product(s) contained in this Offer item to the given quote.
     * @param Mage_Sales_Model_Quote $quote
     * @return Mage_Sales_Model_Quote_Item The item added.
     */
    public function addToQuote(Mage_Sales_Model_Quote $quote)
    {
        list($product, $addToCartForm, $handler) = $this->loadProduct();

        return $handler->addProductToQuote(
            $quote,
            $product,
            $addToCartForm,
            $this->getMaximumQuantity()
        );
    }

    /**
     * Checks that the product(s) represented by this Offer Item is present
     * (in at least the minimum qty) in the given quote.
     *
     * If not present, the product(s) is/are added to the quote.
     * @param  Mage_Sales_Model_Quote $quote
     * @return Mage_Sales_Model_Quote_Item The quote item found or added.
     */
    public function ensurePresentInQuote(Mage_Sales_Model_Quote $quote)
    {
        $items = $quote->getAllItems();
        $item = $this->findQuoteItem($items);

        try
        {
            if ($item) {
                $minOk = $item->getQty() >= $this->getMinimumQuantity();
                $maxOk = $item->getQty() <= $this->getMaximumQuantity();

                if ($minOk && $maxOk) {
                    // Already in quote, no update required.
                    return $item;
                } else if (!$minOk) {
                    // Need to bring quantity *up* to minimum
                    $item->setQty($this->getMinimumQuantity());
                } else if (!$maxOk) {
                    // Need to bring quantity *down* to maximum
                    $item->setQty($this->getMaximumQuantity());
                }
            } else {
                // Item is not even present in quote. So add it.
                $item = $this->addToQuote($quote);
            }

            return $item;
        }
        catch (Exception $ex)
        {
            $translatedEx = PriceWaiter_NYPWidget_Exception_Abstract::translateMagentoException($ex);
            throw $translatedEx;
        }
    }

    /**
     * Given a set of Mage_Sales_Model_Quote_Item instances, returns the *1*
     * that (when also considering its children) contains the product(s)
     * contained in this PriceWaiter order.
     *
     * NOTE: This does *not* consider min and max quantity--the result is based
     *       solely on whether the product in the quote item is the same product
     *       we're considering.
     *
     * If no matching quote item is found, returns false.
     * @param  array  $items
     * @return Mage_Sales_Model_Quote_Item|false
     */
    public function findQuoteItem(array $items)
    {
        list($product, $cart, $handler) = $this->loadProduct();

        // $handler handles trickiness associated with bundle products etc.
        $item = $handler->findQuoteItem($product, $cart, $items);

        if (!$item) {
            return false;
        }

        return $item;
    }

    /**
     * Attempts to read a value from the serialized Magento Add to Cart form
     * stored in the Offer metadata. Uses parse_str internally, so array-style
     * keys[] are expanded into arrays.
     * If called without arguments, returns the full contents of the
     * add to cart form as an array.
     * @param  String $key
     * @param  Mixed  $default
     * @return Mixed
     */
    public function getAddToCartForm($key = null, $default = null)
    {
        $values = $this->getAllAddToCartFormValues();

        // Allow getAddToCartForm() to return full form. (like getMetadata()).
        if (func_num_args() === 0) {
            return $values;
        }

        return array_key_exists($key, $values) ?
            $values[$key] :
            $default;
    }

    /**
     * @return Number Amount per item offered (as a decimal).
     */
    public function getAmountPerItem()
    {
        return doubleval($this->get('amount_per_item.value', 0));
    }

    /**
     * @return String The 3-letter ISO currency code the offer was made in.
     */
    public function getCurrencyCode()
    {
        return $this->get('currency');
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Offer_Inventory
     */
    public function getInventory()
    {
        list($product, $cart, $handler) = $this->loadProduct();
        return $handler->getInventory($product, $cart);
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Offer_Pricing
     */
    public function getPricing()
    {
        list($product, $cart, $handler) = $this->loadProduct();
        return $handler->getPricing($product, $cart);
    }

    /**
     * @return Integer Maximum quantity of this item that can be purchased.
     */
    public function getMaximumQuantity()
    {
        $max = $this->get('quantity.max', null);

        if (!is_numeric($max)) {
            return $this->getMinimumQuantity();
        }

        return max($max, $this->getMinimumQuantity());
    }

    /**
     * Returns the URL of the Magento product referenced by this item.
     * @throws  PriceWaiter_NYPWidget_Exception_Product_NotFound
     * @return String
     */
    public function getMagentoProductUrl()
    {
        list($product) = $this->loadProduct();
        return $product->getProductUrl();
    }

    /**
     * Attempts to read a string value from the Offer's metadata.
     * If you don't pass in any arguments, returns the full metadata array.
     * @param  String $key
     * @param  Mixed $default
     * @return Mixed
     */
    public function getMetadata($key = null, $default = null)
    {
        // Read metadata out of _data and into a standard array first
        if (is_null($this->_metadataArray)) {

            $this->_metadataArray = array();

            $m = $this->get('metadata');

            if ($m) {
                foreach ($m as $k => $v) {
                    $this->_metadataArray[$k] = $v;
                }
            }
        }

        if (func_num_args() === 0) {
            // No args = return all metadata
            return $this->_metadataArray;
        }

        return array_key_exists($key, $this->_metadataArray) ?
            $this->_metadataArray[$key] :
            $default;
    }

    /**
     * @return Integer The minimum quantity of this item that must be purchased.
     */
    public function getMinimumQuantity()
    {
        $min = $this->get('quantity.min', null);

        if (is_null($min)) {
            // Allow specifying min+max with a single number
            $min = $this->get('quantity');
        }

        if (!is_numeric($min)) {
            return self::MINIMUM_QUANTITY;
        }

        return max(self::MINIMUM_QUANTITY, $min);
    }

    /**
     * @return String The reported name of the product.
     */
    public function getProductName()
    {
        return (string)$this->get('product.name');
    }

    /**
     * @return Array An associative array whose keys are product option names and values are the associated values.
     */
    public function getProductOptions()
    {
        if (!is_null($this->_optionsArray)) {
            return $this->_optionsArray;
        }

        $this->_optionsArray = array();

        $options = $this->get('product.options', null);
        if ($options) {
            foreach ($options as $o) {
                $name = (string)self::_get($o, 'name', '');
                $value = (string)self::_get($o, 'value', '');
                $this->_optionsArray[$name] = $value;
            }
        }

        return $this->_optionsArray;
    }

    /**
     * @return String The SKU of the product on the PriceWaiter offer.
     */
    public function getProductSku()
    {
        return (string)$this->get('product.sku');
    }

    /**
     * @return Integer ID of the store this Offer Item is meant for.
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    /**
     * @return Boolean
     */
    public function quoteItemMeetsQuantityRequirements(Mage_Sales_Model_Quote_Item $item)
    {
        $qty = $item->getQty();
        return ($qty >= $this->getMinimumQuantity()) && ($qty <= $this->getMaximumQuantity());
    }

    /**
     * @param  Array $addToCartForm
     * @return PriceWaiter_NYPWidget_Model_Offer_Item A clone of this item with a new add to cart form.
     */
    public function withAddToCartForm(array $addToCartForm)
    {
        $metadata = $this->getMetadata();
        $metadata[self::ADD_TO_CART_FORM_METADATA_KEY] = http_build_query($addToCartForm, '', '&');

        $newData = $this->_data;
        $newData['metadata'] = $metadata;

        return new self($newData, $this->_storeId);
    }

    /**
     * @internal
     */
    protected function get($key, $default = null)
    {
        return self::_get($this->_data, $key, $default);
    }

    /**
     * @internal
     * @return Array The full add to cart form.
     */
    protected function getAllAddToCartFormValues()
    {
        // Magento platform JS serializes the add to cart form into metadata.
        $serializedAddToCartForm = $this->getMetadata(self::ADD_TO_CART_FORM_METADATA_KEY, null);

        if (is_null($serializedAddToCartForm)) {
            return array();
        }

        // Ok. $serializedAddToCartForm is an url-encoded form string like
        // 'foo=bar&baz=bat'

        $addToCartFormValues = array();
        parse_str($serializedAddToCartForm, $addToCartFormValues);

        // HACK: Ok, for some reason we are currently double-encoding the contents
        // of this key--platform JS is taking the serialized form string and
        // passing it through encodeURIComponent(). Here we detect that and try again.
        if (count($addToCartFormValues) === 1) {

            // $values *might* look something like
            //  `array('field=value&field2=value' => '')`
            // which would indicate it was double-encoded.

            $keys = array_keys($addToCartFormValues);
            $values = array_values($addToCartFormValues);

            $looksDoubleEncoded = (
                $values[0] === '' &&
                strpos($keys[0], '&') !== false
            );

            if ($looksDoubleEncoded) {
                $addToCartFormValues = array();
                parse_str($keys[0], $addToCartFormValues);
            }
        }

        return $addToCartFormValues;
    }

    /**
     * Returns a separate class that thinks about the dirty details of Magento
     * products.
     * @param  Mage_Catalog_Model_Product $product
     * @return PriceWaiter_NYPWidget_Model_Offer_Item_Handler
     */
    protected function getHandlerForProduct(Mage_Catalog_Model_Product $product)
    {
        $class = 'nypwidget/offer_item_handler';
        $handler = Mage::getSingleton($class);
        return $handler;
    }

    /**
     * Returns an array with 3 elements:
     *
     * 1. The main Magento product this item refers to.
     * 2. A Varien_Object of add to cart data
     * 3. A handler instance to use to query for more information
     *
     * @return Array
     * @throws  PriceWaiter_NYPWidget_Exception_Product_NotFound
     */
    protected function loadProduct()
    {
        // This method resembles what CartController::_initProduct does when
        // reconstituting a product instance from add to cart data.

        $addToCartForm = $this->getAddToCartForm();
        $id = isset($addToCartForm['product']) ? $addToCartForm['product'] : null;

        if (!$id) {
            throw new PriceWaiter_NYPWidget_Exception_Product_NotFound('product not specified in add to cart form.');
        }

        $product = Mage::getModel('catalog/product')
            ->setStoreId($this->getStoreId())
            ->load($id);

        if (!$product->getId()) {
            throw new PriceWaiter_NYPWidget_Exception_Product_NotFound("Product with id '{$id}' not found.");
        }

        $handler = $this->getHandlerForProduct($product);

        $addToCartForm = new Varien_Object($addToCartForm);

        // Slight HACK: Ensure that we're always considering 1 of the main product at a time.
        //              We support > 1 qty for *child* products (such as items in a bundle),
        //              but don't want quantities to affect price calculations for parent products.
        if ($addToCartForm->hasQty()) {
            $addToCartForm->setQty(1);
        }

        return array(
            $product,
            $addToCartForm,
            $handler,
        );
    }

    /**
     * @internal Reads dot.separated.keys off an object or array.
     */
    private static function _get($obj, $key, $default = null)
    {
        $keyParts = is_array($key) ? $key : explode('.', $key);
        $k = array_shift($keyParts);

        if (is_object($obj)) {
            if (isset($obj->$k)) {
                if (count($keyParts) === 0) {
                    return $obj->$k;
                } else {
                    return self::_get($obj->$k, $keyParts, $default);
                }
            }
        } else if (is_array($obj)) {
            if (array_key_exists($k, $obj)) {
                if (count($keyParts) === 0) {
                    return $obj[$k];
                } else {
                    return self::_get($obj[$k], $keyParts, $default);
                }
            }
        }

        return $default;
    }
}
