<?php

/**
 * Exception thrown when an order already exists for an incoming order callback request.
 */
class PriceWaiter_NYPWidget_Exception_DuplicateOrder
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'duplicate_order';

    public function __construct(PriceWaiter_NYPWidget_Model_Order $existingOrder)
    {
        $id = $this->data['existing_order_id'] = $existingOrder->getId();

        parent::__construct(sprintf(
            'Duplicate order callback detected: Order %s already exists.',
            $id
        ));
    }

    public function getExistingOrderId()
    {
        return $this->data['existing_order_id'];
    }
}
