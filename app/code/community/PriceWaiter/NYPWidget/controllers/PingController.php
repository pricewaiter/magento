<?php

/**
 * Controller that handles /pricewaiter/ping
 */
class PriceWaiter_NYPWidget_PingController extends PriceWaiter_NYPWidget_Controller_Endpoint
{
    /**
     * Versions of request data this controller supports.
     * @var Array
     */
    protected $supportedVersions = array(
        '2016-03-01',
    );

    public function processRequest(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request)
    {
        $response = new PriceWaiter_NYPWidget_Controller_Endpoint_Response(200, $request->getBody());
        return $response;
    }

}
