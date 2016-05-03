<?php

/**
 * Block used to display PriceWaiter payment details.
 */
class PriceWaiter_NYPWidget_Block_Payment_Info_Pricewaiter extends Mage_Payment_Block_Info
{
    /**
     * @return String
     */
    public function getCcLast4()
    {
        $info = $this->getInfo();
        return $info ? $info->getCcLast4() : '';
    }

    /**
     * @return String
     */
    public function getCcTypeName()
    {
        $types = Mage::getSingleton('payment/config')->getCcTypes();
        $ccType = $this->getInfo()->getCcType();

        if (!$ccType) {
            return '';
        }

        if (isset($types[$ccType])) {
            return $types[$ccType];
        }

        return (empty($ccType)) ? Mage::helper('payment')->__('N/A') : $ccType;
    }

    /**
     * @return String|false The full name of the payment method used on PriceWaiter.
     */
    public function getPriceWaiterPaymentMethodName()
    {
        $payment = $this->getInfo();

        // HACK: We stash PW payment method information (serialized) in the
        //       "additional data" field on payment.

        $data = $payment->getAdditionalData();
        $data = $data ? @unserialize($data) : $data;
        $data = is_array($data) ? $data : array();

        if (isset($data['pricewaiter_payment_method_nice'])) {
            return $data['pricewaiter_payment_method_nice'];
        }

        if (isset($data['pricewaiter_payment_method'])) {
            return ucfirst($data['pricewaiter_payment_method']);
        }

        return false;
    }

    /**
     * Prepare credit card related payment info
     *
     * @param Varien_Object|array $transport
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        // This is adapted from Mage_Payment_Block_Info_Cc

        if ($this->_paymentSpecificInformation !== null) {
            return $this->_paymentSpecificInformation;
        }

        /**
         * @var Mage_Payment_Helper_Data
         */
        $p = Mage::helper('payment');

        $transport = parent::_prepareSpecificInformation($transport);
        $data = array();

        $method = $this->getPriceWaiterPaymentMethodName();
        if ($method) {
            $data[$p->__('Paid via')] = $method;
        }

        $ccType = $this->getCcTypeName();
        if ($ccType) {
            $data[$p->__('Credit Card Type')] = $ccType;
        }

        $ccLast4 = $this->getCcLast4();

        if ($ccLast4) {
            $data[$p->__('Credit Card Number')] = sprintf('xxxx-%s', $ccLast4);
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
