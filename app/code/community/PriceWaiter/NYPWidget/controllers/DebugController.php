<?php

class PriceWaiter_NYPWidget_DebugController extends Mage_Core_Controller_Front_Action
{
    protected $fields = array(
        'parent_id' => 'getParentItemId',

        'item_product_type' => 'getProductType',

        'qty' => 'getQty',
        'price' => 'getPrice',
        'discount_amount' => 'getDiscountAmount',
        'row_total' => 'getRowTotal',

        'product_id' => 'getProductId',
        'product_type' => 'getProduct.getTypeId',
        'product_sku' => 'getProduct.getSku',
        'product_name' => 'getProduct.getName',
        'product_price' => 'getProduct.getPrice',
        'product_final_price' => 'getProduct.getFinalPrice',
        'product_cost_price' => 'getProduct.getCost',

    );

    public function cartAction()
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $session->getQuote();

        $items = array();

        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach ($quote->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $this->addItem($item, $items);
        }

        echo 'Quote id: ' . $quote->getId() . '<br>';

        echo '<table border="" cellpadding="2" cellspacing="0">';
        $wroteHeader = false;
        foreach($items as $item) {
            if (!$wroteHeader) {
                $wroteHeader = true;
                echo '<tr>';
                foreach($item as $name => $value) {
                    echo '<th>';
                    echo htmlspecialchars($name);
                    echo '</th>';
                }
                echo '</tr>';
            }

            echo '<tr>';
            foreach($item as $name => $value) {
                echo '<td>';
                echo htmlspecialchars($value);
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</table>';


        exit();

    }

    public function storeAction()
    {
        $store = Mage::app()->getStore();

        echo '<pre>';
        echo(print_r($store->getBaseCurrency(), true));

        exit();
    }

    protected function addItem(Mage_Sales_Model_Quote_Item $item, &$items)
    {
        $values = array();
        foreach($this->fields as $name => $field) {
            $value = $this->readField($item, $field);
            $values[$name] = $value;
        }

        $items[] = $values;

        foreach($item->getChildren() as $child) {
            $this->addItem($child, $items);
        }
    }

    protected function readField($obj, $fields)
    {
        $fields = is_string($fields) ? explode('.', $fields) : $fields;
        $field =  array_shift($fields);

        $value = $obj->$field();

        if ($value === null || count($fields) === 0) {
            return $value;
        }

        return $this->readField($value, $fields);
    }
}
