<?php
/**
 * Controller that handles /pricewaiter/listorders
 */
class PriceWaiter_NYPWidget_ListordersController extends PriceWaiter_NYPWidget_Controller_Endpoint
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
        $body = $request->getBody();
        $res = Mage::getResourceModel('nypwidget/deal_usage');

        // Resolve deal ids into a set of order -> deal id links
        $usage = $res->getOrdersAndDealUsageForDealIds($body->pricewaiter_deals);

        // Format and return the resulting array
        $orders = $this->formatUsage($usage);

        $response = new PriceWaiter_NYPWidget_Controller_Endpoint_Response(200, $orders);

        return $response;
    }

    /**
     * @internal
     * @param  array  $usage
     * @return array
     */
    public function formatUsage(array $usage)
    {
        $result = array();

        foreach($usage as $u) {
            $order = $u['order'];
            $dealIds = $u['dealIds'];
            $formatted = $this->formatOrder($order, $dealIds);
            if ($formatted) {
                $result[] = $formatted;
            }
        }

        return $result;
    }

    /**
     * Attempts to format
     * @param  Mage_Sales_Model_Order $order
     * @param  array                  $dealIds
     * @return Object|false
     */
    public function formatOrder(Mage_Sales_Model_Order $order, array $dealIds)
    {
        $state = $this->translateMagentoOrderState($order->getState());
        if (!$state) {
            return false;
        }

        return (object)array(
            'id' => strval($order->getIncrementId()),
            'state' => $state,
            'currency' => $order->getOrderCurrency()->getCode(),
            'subtotal' => (object)array(
                'value' => strval(
                    // "subtotal" here means "order total minus shipping and tax"
                    $order->getGrandTotal() -
                    $order->getShippingAmount() -
                    $order->getTaxAmount()
                ),
            ),
            'pricewaiter_deals' => $dealIds,
        );
    }

    /**
     * @internal Translates a Magento order state into one of the states
     * PriceWaiter uses.
     * @param  string $state
     * @return string|false A PW state, or false if no translation is possible.
     */
    public static function translateMagentoOrderState($state)
    {
        $state = strtolower(strval($state));

        switch($state) {
            case Mage_Sales_Model_Order::STATE_CANCELED:
                return false;

            case Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW:
            case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
                return 'pending';

            default:
                return 'paid';
        }
    }
}
