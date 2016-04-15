<?php

/**
 * Helper that wraps a blob of item data from an offer (usually derived from JSON).
 * This is a combination of product data, offer amount, quantity, and metadata.
 * It has *not* yet been tied into any data inside Magento.
 */
class PriceWaiter_NYPWidget_Model_OfferItem
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
     * @param Object $data
     */
    public function __construct($data)
    {
        $this->_data = $data ? $data : array();
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
     * @return Integer Amount per item offered (in smallest unit of currency).
     */
    public function getAmountPerItemInCents()
    {
        return intval($this->get('amount_per_item.cents', 0));
    }

    /**
     * @return Number Amount per item offered (as a decimal).
     */
    public function getAmountPerItem()
    {
        return doubleval($this->get('amount_per_item.value', 0));
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
     * @return String The reported SKU of the product.
     */
    public function getProductSku()
    {
        return (string)$this->get('product.sku');
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
