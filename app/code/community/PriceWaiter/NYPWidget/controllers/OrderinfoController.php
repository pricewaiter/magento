<?php

class PriceWaiter_NYPWidget_OrderinfoController extends Mage_Core_Controller_Front_Action
{
    /**
     * Header used to pass error messages back to PriceWaiter.
     */
    const ERROR_MESSAGE_HEADER = 'X-Platform-Error';

    /**
     * Header used to pass error codes back to PriceWaiter.
     */
    const ERROR_CODE_HEADER = 'X-Platform-Error-Code';

    public function indexAction()
    {
        $httpRequest = $this->getRequest();
        $httpResponse = $this->getResponse();
        $params = $httpRequest->getParams();

        // Add debugging headers
        Mage::helper('nypwidget/about')->setResponseHeaders($httpResponse);
        $pricewaiterId = '';

        try
        {
            if (null === $params['pw_order_id'] || empty($params['pw_order_id'])) {
                $ex = new PriceWaiter_NYPWidget_Exception_OrderNotFound("No order ID was passed. Cannot retrieve data.");
            }
            $pwOrder = Mage::getModel('nypwidget/order')
                ->loadByPriceWaiterId($params['pw_order_id']);

            $order = Mage::getModel('sales/order')->load($pwOrder->getOrderId());
            if (count($order->getAllItems()) === 0) {
                $ex = new PriceWaiter_NYPWidget_Exception_OrderNotFound("No order record was found in Magento for PW order ${params['pw_order_id']}");
                throw $ex;
            }
            $shipmentsCollection = $order->getShipmentsCollection();
            if (!isset($shipmentsCollection)) {
                $httpResponse->setBody(json_encode([]));
            }
            $shipments = $shipmentsCollection->getItems();
            $trackingNumbers = array();
            foreach ($shipments as $id => $shipment) {
                $trackings = $shipment->getallTracks();
                foreach ($trackings as $tracking) {
                    array_push($trackingNumbers, array(
                        'carrier' => $tracking->getTitle(),
                        'carrier_code' => $tracking->getCarrierCode(),
                        'tracking' => $tracking->getTrackNumber(),
                    ));
                }
            }
            $httpResponse->setBody(json_encode(array('tracking_numbers' => $trackingNumbers)));
        }
        catch (Exception $ex)
        {
            if ($ex instanceof PriceWaiter_NYPWidget_Exception_Abstract) {
                // These are normal errors indicating problems we've previously thought of
                // occurring during error processing.
                $httpResponse->setHttpResponseCode($ex->httpStatusCode);

                if (!empty($ex->errorCode)) {
                    $httpResponse->setHeader(self::ERROR_CODE_HEADER, $ex->errorCode, true);
                }
                if (!empty($ex->message)) {
                    $httpResponse->setHeader(self::ERROR_MESSAGE_HEADER, $ex->message, true);
                }

            } else {
                // These are not.
                $httpResponse->setHttpResponseCode(500);
            }

            $httpResponse->setHeader(self::ERROR_MESSAGE_HEADER, $ex->getMessage(), true);
        }
    }
}
