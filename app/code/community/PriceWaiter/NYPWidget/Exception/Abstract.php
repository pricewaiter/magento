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
}
