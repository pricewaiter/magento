<?php

/**
 * Base for implementing exceptions to be thrown by the PriceWaiter extension.
 */
abstract class PriceWaiter_NYPWidget_Exception_Abstract extends RuntimeException
{
    /**
     * Unchanging error code to be reported back to clients.
     * This is a string, favored over PHP's built-in int `code` property.
     * @var String
     */
    public $errorCode;

    /**
     * HTTP status code to be used when reporting this error back to the client.
     * @var integer
     */
    public $httpStatusCode = 400;

    /**
     * @internal
     */
    protected static $translations = array(
        array(
            'class' => 'Mage_Core_Exception',
            'message' => 'This product is currently out of stock.',
            'helper' => 'cataloginventory',
            'translatedClass' => 'PriceWaiter_NYPWidget_Exception_Product_OutOfStock',
        ),
        array(
            'class' => 'Mage_Core_Exception',
            'message' => 'Not all products are available in the requested quantity',
            'helper' => 'cataloginventory',
            'translatedClass' => 'PriceWaiter_NYPWidget_Exception_Product_OutOfStock',
        ),
    );

    /**
     * @return Array Representation of this error, ready to be passed to json_encode.
     */
    public function jsonSerialize()
    {
        $json = array(
            'message' => $this->getMessage(),
        );

        if ($this->errorCode) {
            $json['code'] = $this->errorCode;
        }

        return $json;
    }

    /**
     * @internal Attempts to convert a generic Magento exception into a typed PW one.
     * @param  Exception $ex
     */
    public static function translateMagentoException(Exception $ex)
    {
        foreach (self::$translations as $t) {
            $translated = self::applyExceptionTranslation($ex, $t);
            if ($translated) {
                return $translated;
            }
        }

        return $ex;
    }

    private static function applyExceptionTranslation(Exception $ex, array $t)
    {
        if (!($ex instanceof $t['class'])) {
            return false;
        }

        $messages = array(
            $t['message'],
        );

        if (!empty($t['helper'])) {
            // Also check for a *translated* version of the message
            $helper = Mage::helper($t['helper']);
            $messages[] = $helper->__($t['message']);
        }

        $actualMessage = $ex->getMessage();
        if (!in_array($actualMessage, $messages)) {
            // No match
            return false;
        }

        $translatedClass = $t['translatedClass'];
        return new $translatedClass($actualMessage);
    }
}
