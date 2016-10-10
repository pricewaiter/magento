<?php

// Response that doesn't throw exceptions about sending headers.
class TestableHttpResponse extends Zend_Controller_Response_Http
{
    public function canSendHeaders($throw = false)
    {
        return true;
    }

    public function sendHeaders() { }
}

class TestableHttpRequest extends Zend_Controller_Request_Http
{
    private $_mockHeaders = array();

    public function setRawBody($raw)
    {
        $this->_rawBody = $raw;
        return $this;
    }

    public function getHeader($name)
    {
        $name = strtolower($name);

        if (isset($this->_mockHeaders[$name])) {
            return $this->_mockHeaders[$name];
        }

        return parent::getHeader($name);
    }

    public function setMockHeader($name, $value)
    {
        $name = strtolower($name);
        $this->_mockHeaders[$name] = $value;
        return $this;
    }
}
