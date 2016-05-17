<?php

class PriceWaiter_NYPWidget_Model_Callback
{
    /**
     * Description used for shipping if none specified.
     */
    const DEFAULT_SHIPPING_DESCRIPTION = 'PriceWaiter';

    const LOGFILE = 'PriceWaiter_Callback.log';

    const XML_PATH_ORDER_LOGGING = 'pricewaiter/orders/log';

    /**
     * @var PriceWaiter_NYPWidget_Helper_Data
     */
    private $_helper = null;

    /**
     * @internal Probably redundant, but don't want to tie our field names to internal Magento constants.
     * @var array
     */
    protected static $magentoAddressTypes = array(
        'billing' => Mage_Sales_Model_Quote_Address::TYPE_BILLING,
        'shipping' => Mage_Sales_Model_Quote_Address::TYPE_SHIPPING
    );

    /**
     * @internal
     * @var string
     */
    protected $passwordCharacters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @internal Adds items to the given order and calculates final total.
     * @param Mage_Sales_Model_Order       $order    [description]
     * @param Array                        $request  [description]
     * @param Mage_Core_Model_Store        $store    [description]
     * @param Mage_Customer_Model_Customer $customer [description]
     */
    public function addItemsToOrder(
        Mage_Sales_Model_Order $order,
        Array $request,
        Mage_Core_Model_Store $store,
        Mage_Customer_Model_Customer $customer
    )
    {
        $product = $this->findProduct($request);

        if (!$product) {
            throw new PriceWaiter_NYPWidget_Exception_Product_NotFound();
        }

        // Build the pricing information of the product
        $orderItem = Mage::getModel('sales/order_item')
            ->setStoreId($store->getId())
            ->setQuoteItemId(0)
            ->setQuoteParentItemId(null)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setTotalQtyOrdered($request['quantity'])
            ->setQtyOrdered($request['quantity'])
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setIsNominal(0);

        // Delegate out to some more unit-testable code to calculate all the
        // various amount fields present on the order item.
        $amounts = $this->calculateOrderItemAmounts(
            $request,
            $product->getPrice(),
            array($store, 'roundPrice')
        );
        foreach($amounts as $key => $value) {
            $orderItem->setData($key, $value);
            $orderItem->setData("base_$key", $value);
        }

        $additionalOptions = array();
        foreach($this->buildProductOptionsArray($request) as $label => $value) {
            $additionalOptions[] = array(
                'label' => $label,
                'value' => $value,
            );
        }

        $orderItem->setProductOptions(array('additional_options' => $additionalOptions));

        $order->addItem($orderItem);
    }

    /**
     * @internal Constructs address models based on request data.
     * @param  String                       $type     'billing' or 'shipping'.
     * @param  Array                        $request
     * @param  Mage_Customer_Model_Customer $customer
     * @param  Mage_Core_Model_Store        $store
     * @return Mage_Sales_Model_Order_Address
     * @throws PriceWaiter_NYPWidget_Exception_InvalidRegion
     */
    public function buildAddress($type, Array $request, Mage_Customer_Model_Customer $customer, Mage_Core_Model_Store $store)
    {
        $state = $request["buyer_{$type}_state"];
        $country = $request["buyer_{$type}_country"];

        // Resolve state + country into a Mage_Directory_Model_Region
        $regionModel = Mage::getModel('directory/region')
            ->loadByCode($state, $country);

        if (!$regionModel->getId()) {
            throw new PriceWaiter_NYPWidget_Exception_InvalidRegion($state, $country);
        }

        return Mage::getModel('sales/order_address')
            // System data
            ->setStoreId($store->getId())
            ->setAddressType(self::$magentoAddressTypes[$type])

            // Customer + name
            ->setCustomerId($customer->getId())
            ->setPrefix('')
            ->setFirstname($request["buyer_{$type}_first_name"])
            ->setMiddlename('')
            ->setLastname($request["buyer_{$type}_last_name"])
            ->setSuffix('')
            ->setCompany('')

            // Actual address
            ->setStreet(array_filter(array(
                $request["buyer_{$type}_address"],
                $request["buyer_{$type}_address2"],
                $request["buyer_{$type}_address3"],
            )))
            ->setCity($request["buyer_{$type}_city"])
            ->setRegionId($regionModel->getId())
            ->setPostcode($request["buyer_{$type}_zip"])
            ->setCountryId($country)

            // Phone numbers
            ->setTelephone($request["buyer_{$type}_phone"])
            ->setFax('');
    }

    /**
     * @internal  Assembles a Mage_Sales_Model_Order out of all incoming request data.
     * @param  Array                        $request
     * @param  Mage_Core_Model_Store        $store
     * @param  Mage_Customer_Model_Customer $customer
     * @return Mage_Sales_Model_Order
     */
    public function buildMagentoOrder(Array $request, Mage_Core_Model_Store $store, Mage_Customer_Model_Customer $customer)
    {
        $reservedOrderId = Mage::getSingleton('eav/config')
            ->getEntityType('order')
            ->fetchNewIncrementId($store->getId());

        $currencyCode = $request['currency'];

        $order = Mage::getModel('sales/order')
            // System fields
            ->setIncrementId($reservedOrderId)
            ->setStoreId($store->getId())
            ->setQuoteId(0)
            ->setGlobalCurrencyCode($currencyCode)
            ->setBaseCurrencyCode($currencyCode)
            ->setStoreCurrencyCode($currencyCode)
            ->setOrderCurrencyCode($currencyCode)

            // Customer
            ->setCustomerEmail($customer->getEmail())
            ->setCustomerFirstname($customer->getFirstname())
            ->setCustomerLastname($customer->getLastname())
            ->setCustomerGroupId($customer->getGroupId())
            ->setCustomerIsGuest(0)
            ->setCustomer($customer)

            // Addresses
            ->setBillingAddress($this->buildAddress(
                'billing',
                $request,
                $customer,
                $store
            ))
            ->setShippingAddress($this->buildAddress(
                'shipping',
                $request,
                $customer,
                $store
            ));

        $this->setOrderShippingMethod($order, $request, $store, $customer);

        $this->setOrderPaymentMethod($order, $request, $store, $customer);

        $this->addItemsToOrder($order, $request, $store, $customer);

        $product = $this->findProduct($request);
        if (!$product) {
            throw new PriceWaiter_NYPWidget_Exception_Product_NotFound();
        }

        $amounts = $this->calculateOrderAmounts($request, $product->getPrice(), array($store, 'roundPrice'));

        foreach($amounts as $key => $value) {
            $order->setData($key, $value);
            $order->setData("base_$key", $value);
        }

        $order->setDiscountDescription('PriceWaiter');

        $comment = $this->getNewOrderComment($request);
        if ($comment) {
            $order->addStatusHistoryComment($comment);
        }

        return $order;
    }

    /**
     * @internal
     * Assembles an array of product options in the format array("name" => "value", "name" => "value")
     * @param  Array  $request
     */
    public function buildProductOptionsArray(Array $request)
    {
        $options = array();

        $count = intval($request['product_option_count']);

        // Decode product_option_name<N> and product_option_value<N>-style incoming data.
        // Note that we start numbering at 1.

        for ($i = 1; $i <= $count; $i++) {
            $nameKey = "product_option_name{$i}";
            $name = $request[$nameKey];

            $valueKey = "product_option_value{$i}";
            $value = $request[$valueKey];

            $options[$name] = $value;
        }

        return $options;
    }

    /**
     * @internal Calculates pricing-related fields to go on the order.
     * @param  Array  $request
     * @return Array
     */
    public function calculateOrderAmounts(Array $request, $productPrice, Callable $rounder)
    {
        $amountPerItem = $request['unit_price'];
        $qty = $request['quantity'];
        $shipping = $request['shipping'];
        $tax = $request['tax'];
        $subtotal = $amountPerItem * $qty;

        $regularSubtotal = $productPrice * $qty;
        $discountAmount = $regularSubtotal - $subtotal;

        $total = $rounder($subtotal + $shipping + $tax);

        return array(
            'discount_amount' => $rounder(-$discountAmount),
            'grand_total' => $total,
            'shipping_amount' => $shipping,
            'shipping_discount_amount' => 0,
            'shipping_incl_tax' => $shipping,
            'shipping_tax_amount' => 0,
            'subtotal' => $rounder($regularSubtotal),
            'subtotal_incl_tax' => $rounder($regularSubtotal + $tax),
            'tax_amount' => $rounder($tax),
        );
    }

    /**
     * @internal Calculates pricing-related fields to be set on an order item.
     * @param  Array                      $request
     * @param  Mage_Catalog_Model_Product $product
     */
    public function calculateOrderItemAmounts(Array $request, $productPrice, Callable $rounder)
    {
        $amountPerItem = $request['unit_price'];
        $qty = $request['quantity'];
        $subtotal = $amountPerItem * $qty;
        $tax = $request['tax'];
        $taxPercent = $tax / ($amountPerItem * $qty);

        $regularSubtotal = $productPrice * $qty;
        $taxBeforeDiscount = $regularSubtotal * $taxPercent;

        $discountAmount = $regularSubtotal - $subtotal;
        $discountPercent = $discountAmount / $regularSubtotal * 100;

        return array(
            'discount_amount' => $rounder($discountAmount),
            'discount_percent' => $rounder($discountPercent),
            'original_price' => $productPrice,
            'price' => $productPrice,
            'price_incl_tax' => $rounder($productPrice + ($tax / $qty)),
            'row_total' => $rounder($regularSubtotal),
            'row_total_incl_tax' => $rounder($regularSubtotal + $tax),
            'tax_amount' => $tax,
            'tax_before_discount' => $rounder($taxBeforeDiscount),
            'tax_percent' => $rounder($taxPercent * 100),
        );
    }

    /**
     * @internal Creates a new customer, saves it, and returns it.
     * @param  Array                 $request
     * @param  Mage_Core_Model_Store $store
     * @return Mage_Customer_Model_Customer
     */
    public function createCustomer(Array $request, Mage_Core_Model_Store $store)
    {
        $customer = Mage::getModel('customer/customer')
            // System
            ->setWebsiteId($store->getWebsiteId())
            ->setPassword($this->generatePassword(10))
            ->setConfirmation(null)

            // Person
            ->setEmail($request['buyer_email'])
            ->setFirstname($request['buyer_first_name'])
            ->setLastname($request['buyer_last_name']);

        $customer->save();

        $this->sendWelcomeEmail($customer, $store);

        return $customer;
    }

    /**
     * @internal Either returns an existing customer (by email) or creates a new one.
     * @param  Array                 $request
     * @param  Mage_Core_Model_Store $store
     * @return Mage_Customer_Model_Customer
     */
    public function findOrCreateCustomer(Array $request, Mage_Core_Model_Store $store)
    {
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId($store->getWebsiteId())
            ->loadByEmail($request['buyer_email']);

        if ($customer->getId()) {
            // This an existing customer for this Magento store.
            return $customer;
        }

        return $this->createCustomer($request, $store);
    }

    /**
     * @internal Resolves the product for an incoming request.
     * @param  Array  $request
     * @return Mage_Catalog_Model_Product|false
     */
    public function findProduct(Array $request)
    {
        $productSku = $request['product_sku'];
        $productOptions = $this->buildProductOptionsArray($request);

        $product = $this->getHelper()->getProductWithOptions($productSku, $productOptions);

        return $product->getId() ? $product : false;
    }

    /**
     * Looks for an existing order that matches the given order callback request.
     * @param  Array $request
     * @return PriceWaiter_NYPWidget_Model_Order|false
     */
    public function getExistingOrder(Array $request)
    {
        $existingOrder = Mage::getModel('nypwidget/order');
        $existingOrder->loadByPriceWaiterId($request['pricewaiter_id']);

        if ($existingOrder->getId()) {
            return $existingOrder;
        }

        return false;
    }

    /**
     * @param  Array  $request
     * @return String Comment to be added to the order generated from $request.
     */
    public function getNewOrderComment(Array $request)
    {
        $helper = $this->getHelper();
        $url = $helper->getOfferUrl($request['pricewaiter_id']);

        $safeUrl = htmlentities($url, ENT_QUOTES);

        return sprintf(
            'Ordered via PriceWaiter (<a href="%s" target="_blank">%s</a>).',
            $safeUrl,
            $safeUrl
        );
    }

    /**
     * @param  Array  $request
     * @return Mage_Core_Model_Store
     * @throws PriceWaiter_NYPWidget_Exception_ApiKey If no store configured for API key.
     */
    public function getStore(Array $request)
    {
        $apiKey = isset($request['api_key']) ? $request['api_key'] : null;
        $store = false;

        if ($apiKey) {
            $store = $this->getHelper()->getStoreByPriceWaiterApiKey($apiKey);
        }

        if (!$store) {
            throw new PriceWaiter_NYPWidget_Exception_ApiKey();
        }

        return $store;
    }

    /**
     * @return boolean
     */
    public function isTestOrder(Array $request)
    {
        return !empty($request['test']);
    }

    /**
     * @param  Array  $request
     * @return Mage_Sales_Model_Order
     */
    public function processRequest(Array $request)
    {
        $this->logIncomingRequest($request);

        // Hint to our custom payment method about the incoming request
        PriceWaiter_NYPWidget_Model_PaymentMethod::setCurrentOrderCallbackRequest($request);

        try
        {
            if (!$this->verifyRequest($request)) {
                throw new PriceWaiter_NYPWidget_Exception_InvalidOrderData();
            }

            $store = $this->getStore($request);

            $existingOrder = $this->getExistingOrder($request);

            if ($existingOrder) {
                throw new PriceWaiter_NYPWidget_Exception_DuplicateOrder($existingOrder);
            }

            $customer = $this->findOrCreateCustomer($request, $store);

            $order = $this->buildMagentoOrder($request, $store, $customer);

            // Ok, done with the order.
            $this->saveAndPlaceOrder($order);

            $this->logOrderCreated($request, $order);

            // --- After this point, we have a *live* order in the system. ---

            $this->recordOrderCreation($request, $order);

            $this->sendNewOrderEmail($order, $store);

            if ($this->isTestOrder($request)) {
                $this->cancelOrder($order);
            }

            PriceWaiter_NYPWidget_Model_PaymentMethod::resetCurrentOrderCallbackRequest();
            return $order;
        }
        catch (Exception $ex)
        {
            $this->logException($request, $ex);
            PriceWaiter_NYPWidget_Model_PaymentMethod::resetCurrentOrderCallbackRequest();
            throw $ex;
        }
    }

    /**
     * @internal Assigns details around order payment.
     * @param Mage_Sales_Model_Order       $order
     * @param Array                        $request
     * @param Mage_Core_Model_Store        $store
     * @param Mage_Customer_Model_Customer $customer
     */
    public function setOrderPaymentMethod(
        Mage_Sales_Model_Order $order,
        Array $request,
        Mage_Core_Model_Store $store,
        Mage_Customer_Model_Customer $customer
    )
    {
        // Add PriceWaiter payment method
        $orderPayment = Mage::getModel('sales/order_payment')
            ->setMethod('nypwidget')
            ->setStoreId($store->getId())
            ->setCustomerPaymentId(0)
            ->setTransactionId($request['transaction_id']);

        // Extra details for credit card payments
        if (!empty($request['cc_type'])) {
            $ccType = $this->translateCcType($request['cc_type']);
            if ($ccType) {
                $orderPayment->setCcType($ccType);
            }
        }

        if (!empty($request['cc_last4'])) {
            $orderPayment->setCcLast4($request['cc_last4']);
        }

        if (!empty($request['avs_result'])) {
            $orderPayment->setCcAvsStatus($request['avs_result']);
        }

        // Stash PW-specific stuff in "additional data"
        $additionalData = array(
            'pricewaiter_payment_method' => $request['payment_method'],
        );

        if (!empty($request['payment_method_nice'])) {
            $additionalData['pricewaiter_payment_method_nice'] =
                $request['payment_method_nice'];
        }

        $orderPayment->setAdditionalData(serialize($additionalData));

        $order->setPayment($orderPayment);
    }

    /**
     * @internal  Assigns details around shipping method to $order.
     * @param Mage_Sales_Model_Order       $order
     * @param Array                        $request
     * @param Mage_Core_Model_Store        $store
     * @param Mage_Customer_Model_Customer $customer
     */
    public function setOrderShippingMethod(
        Mage_Sales_Model_Order $order,
        Array $request,
        Mage_Core_Model_Store $store,
        Mage_Customer_Model_Customer $customer
    )
    {
        $description = $request['shipping_method'];
        if (trim($description) === '') {
            // Leaving description blank results in "No shipping information available" in PW admin
            $description = self::DEFAULT_SHIPPING_DESCRIPTION;
        }

        // Use PriceWaiter shipping method
        $order->setShippingMethod('nypwidget_nypwidget')
            ->setShippingAmount($request['shipping'])
            ->setShippingDescription($description);
    }

    /**
     * @internal
     * @return PriceWaiter_NYPWidget_Helper_Data
     */
    public function getHelper()
    {
        if ($this->_helper === null) {
            $this->_helper = Mage::helper('nypwidget');
        }
        return $this->_helper;
    }

    /**
     * @internal
     * @param PriceWaiter_NYPWidget_Helper_Data $helper
     * @return PriceWaiter_NYPWidget_Model_Callback $this
     */
    public function setHelper(PriceWaiter_NYPWidget_Helper_Data $helper)
    {
        $this->_helper = $helper;
        return $this;
    }

    /**
     * Attempts to validate incoming PriceWaiter order data by POSTing it back
     * to the PriceWaiter Order Verification endpoint.
     * @param  Array  $request
     */
    public function verifyRequest(Array $request)
    {
        $verifyUrl = $this->getHelper()->getOrderVerificationUrl();
        $ch = curl_init($verifyUrl);

        if (!$ch) {
            return false;
        }

        // NOTE: Building $postFields string manually to avoid multipart/form-data content type
        //       assigned by default when using Array.
        $postFields = http_build_query(
            $request,
            '',
            '&',
            // NOTE: PHP_QUERY_RFC1738 added in PHP 5.4
            defined('PHP_QUERY_RFC1738') ? PHP_QUERY_RFC1738 : 0
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        if (defined('CURLOPT_SAFE_UPLOAD')) {
            // Disable curl's dumb '@filename' upload option
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        }

        $response = curl_exec($ch);
        $valid = ($response === '1');

        return $valid;
    }

    /**
     * @internal
     * @param  Mage_Sales_Model_Order $order
     */
    protected function cancelOrder(Mage_Sales_Model_Order $order)
    {
        $order->cancel();
        $order->save();
    }

    /**
     * @internal
     * @param  Integer $length
     * @return String
     */
    protected function generatePassword($length)
    {
        $password = [];

        $numberOfChars = strlen($this->passwordCharacters);

        for ($i = 0; $i < $length; $i++) {
            $password[] = $this->passwordCharacters[mt_rand(0, $numberOfChars - 1)];
        }

        return implode('', $password);
    }

    protected function isLogEnabled($store = null)
    {
        return !!Mage::getStoreConfig(
            self::XML_PATH_ORDER_LOGGING,
            $store
        );
    }

    /**
     * @internal
     */
    protected function logException(Array $request, Exception $ex)
    {
        $id = isset($request['pricewaiter_id']) ? $request['pricewaiter_id'] : '';

        $message = $ex->getMessage();
        $code = isset($ex->errorCode) ? $ex->errorCode : null;

        return $this->log(
            'Error processing order callback for PriceWaiter offer #%s: %s (%s)',
            $id,
            $message,
            $code
        );
    }

    /**
     * @internal
     */
    protected function logIncomingRequest(Array $request)
    {
        $id = isset($request['pricewaiter_id']) ? $request['pricewaiter_id'] : '';
        return $this->log('Received order callback for PriceWaiter offer %s', $id);
    }

    /**
     * @internal
     */
    protected function logOrderCreated(Array $request, Mage_Sales_Model_Order $order)
    {
        $id = isset($request['pricewaiter_id']) ? $request['pricewaiter_id'] : '';
        $orderId = $order->getIncrementId();
        return $this->log('Created order #%s for PriceWaiter offer %s', $orderId, $id);
    }


    /**
     * Runs the arguments through sprintf and passes them to the order callback log.
     * @return PriceWaiter_NYPWidget_Model_Callback $this
     */
    protected function log()
    {
        if (!$this->isLogEnabled()) {
            return $this;
        }

        $args = func_get_args();
        $message = call_user_func_array('sprintf', $args);

        Mage::log($message, null, self::LOGFILE);
        return $this;
    }

    /**
     * Stores a record in the db indicating we've processed $request into $order. This is used for
     * duplicate order detection.
     * @param  Array                  $request
     * @param  Mage_Sales_Model_Order $order
     */
    protected function recordOrderCreation(Array $request, Mage_Sales_Model_Order $order)
    {
        Mage::getModel('nypwidget/order')
            ->setStoreId($order->getStoreId())
            ->setPricewaiterId($request['pricewaiter_id'])
            ->setOrderId($order->getId())
            ->save();
    }

    /**
     * @param  Mage_Sales_Model_Order $order
     */
    protected function saveAndPlaceOrder(Mage_Sales_Model_Order $order)
    {
        // NOTE: Magento's built-in inventory handling operates during the conversion
        //       of quotes -> orders. Since we write orders directly (without a quote),
        //       we have to handle inventory ourselves.
        $inventory = Mage::getModel('nypwidget/callback_inventory', $order);

        $transaction = Mage::getModel('core/resource_transaction');
        $transaction->addObject($order);
        $transaction->addCommitCallback(array($inventory, 'registerPricewaiterSale'));
        $transaction->addCommitCallback(array($order, 'place'));
        $transaction->addCommitCallback(array($order, 'save'));
        $transaction->save();
    }

    /**
     * @param  Mage_Sales_Model_Order $order
     * @param  Mage_Core_Model_Store  $store
     * @return Boolean Whether email was sent.
     */
    protected function sendNewOrderEmail(Mage_Sales_Model_Order $order, Mage_Core_Model_Store $store)
    {
        if (!$store->getConfig('pricewaiter/customer_interaction/send_new_order_email')) {
            return false;
        }

        $order->sendNewOrderEmail();
        return true;
    }

    /**
     * @param  Mage_Customer_Model_Customer $customer
     * @return Boolean Whether email was sent.
     */
    protected function sendWelcomeEmail(Mage_Customer_Model_Customer $customer, Mage_Core_Model_Store $store)
    {
        if (!$store->getConfig('pricewaiter/customer_interaction/send_welcome_email')) {
            return false;
        }

        $customer->sendNewAccountEmail('registered', '', $store->getId());

        return true;
    }

    /**
     * @internal Translates an incoming PriceWaiter cc_type into a 2-character Magento credit card type.
     * @param  String $type A credit card type, e.g. "Visa".
     * @return String|false A 2-character credit card type code or false if none can be resolved.
     */
    public function translateCcType($type)
    {
        /**
         * @var Mage_Payment_Model_Config
         */
        $config = Mage::getSingleton('payment/config');

        // $types is an array in the format:
        // array(
        //     'VI' => 'Visa',
        // )
        $types = $config->getCcTypes();

        $magentoTypeCodes = array();
        $normalizedNames = array();

        foreach($types as $code => $name) {
            $magentoTypeCodes[] = $code;
            $normalizedNames[] = strtolower(preg_replace('/[^\w\d]/', '', $name));
        }

        $map = array_combine($normalizedNames, $magentoTypeCodes);
        $normalizedType = strtolower(preg_replace('/[^\w\d]/', '', $type));

        if (isset($map[$normalizedType])) {
            return $map[$normalizedType];
        }

        return false;
    }

    private function _log($message)
    {
        if (Mage::getStoreConfig('pricewaiter/configuration/log')) {
            Mage::log($message, null, "PriceWaiter_Callback.log");
        }
    }
}
