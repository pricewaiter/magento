<?php

class PriceWaiter_NYPWidget_CallbackController extends Mage_Core_Controller_Front_Action
{
    /**
     * Header used to pass error messages back to PriceWaiter.
     */
    const ERROR_MESSAGE_HEADER = 'X-Platform-Error';

    /**
     * Header used to pass error codes back to PriceWaiter.
     */
    const ERROR_CODE_HEADER = 'X-Platform-Error-Code';

    /**
     * Header used to pass the generated order id back to PriceWaiter.
     */
    const ORDER_ID_HEADER = 'X-Platform-Order-Id';

    public function indexAction()
    {
        $httpRequest = $this->getRequest();
        $httpResponse = $this->getResponse();

        if (!$httpRequest->isPost()) {
            // Pretend like this page isn't even *here*
            $this->norouteAction();
            return;
        }

        // Add debugging headers
        Mage::helper('nypwidget/about')->setResponseHeaders($httpResponse);
        $pricewaiterId = '';

        try
        {
            $data = $httpRequest->getPost();

            $order = Mage::getModel('nypwidget/callback')
                ->processRequest($data);

            // Success!
            $httpResponse->setHeader(self::ORDER_ID_HEADER, $order->getIncrementId(), true);
        }
        catch (Exception $ex)
        {
            // Augment duplicate order errors with the existing order id.
            if ($ex instanceof PriceWaiter_NYPWidget_Exception_DuplicateOrder) {
                $httpResponse->setHeader(self::ORDER_ID_HEADER, $ex->getExistingOrderId(), true);
            }

            if ($ex instanceof PriceWaiter_NYPWidget_Exception_Abstract) {
                // These are normal errors indicating problems we've previously thought of
                // occurring during error processing.
                $httpResponse->setHttpResponseCode($ex->httpStatusCode);

                if (!empty($ex->errorCode)) {
                    $httpResponse->setHeader(self::ERROR_CODE_HEADER, $ex->errorCode, true);
                }

            } else {
                // These are not.
                $httpResponse->setHttpResponseCode(500);
            }

            $httpResponse->setHeader(self::ERROR_MESSAGE_HEADER, $ex->getMessage(), true);
        }
    }
}
