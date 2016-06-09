<?php

/**
 * PriceWaiter Payment method.
 */
class PriceWaiter_NYPWidget_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'nypwidget';

    // Custom info block to display payment details on order in admin.
    protected $_infoBlockType = 'nypwidget/payment_info_pricewaiter';

    protected $_isGateway = false;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = false;
    protected $_canUseForMultishipping = false;

    private static $_currentOrderCallbackRequest = array();

    public function authorize(Varien_Object $payment, $amount)
    {
        // Don't close transactions for auth-only.
        $payment->setIsTransactionClosed(0);

        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        return $this;
    }

    public function isAvailable($quote = null)
    {
        return false;
    }

    public function getConfigData($field, $storeId = null)
    {
        if ($field !== 'order_status') {
            return parent::getConfigData($field, $storeId);
        }

        if ($storeId === null) {
            $storeId = $this->getStore();
        }

        $status = Mage::helper('nypwidget')->getDefaultOrderStatus($storeId);
        return $status;
    }

    public function getConfigPaymentAction()
    {
        // For test orders (which will be immediately canceled) we don't
        // want to "capture" payment, since that removes our ability to cancel.
        if ($this->isCurrentRequestTest()) {
            return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
        }

        // Detect auth-only transactions and, uh, only auth them.
        if ($this->isCurrentRequestAuthorizeOnly()) {
            return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
        }

        return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * @return boolean Whether the current order callback request being processed is authorize only (no capture).
     */
    protected function isCurrentRequestAuthorizeOnly()
    {
        $request = $this->getCurrentOrderCallbackRequest();

        return (
            is_array($request) &&
            isset($request['transaction_type']) &&
            strcasecmp($request['transaction_type'], 'auth') === 0
        );
    }

    /**
     * @return boolean Whether the current order callback request being processed is a test.
     */
    protected function isCurrentRequestTest()
    {
        $request = $this->getCurrentOrderCallbackRequest();
        return is_array($request) && !empty($request['test']);
    }

    /**
     * @internal
     */
    public static function resetCurrentOrderCallbackRequest()
    {
        self::$_currentOrderCallbackRequest = array();
    }

    /**
     * @internal Hack to allow payment method access to incoming order data.
     * @param Array $request
     */
    public static function setCurrentOrderCallbackRequest(Array $request)
    {
        self::$_currentOrderCallbackRequest = $request;
    }

    /**
     * @internal
     * @return Array
     */
    protected function getCurrentOrderCallbackRequest()
    {
        return self::$_currentOrderCallbackRequest;
    }
}
