<?php

/**
 * A Deal is a single opportunity for a single buyer to purchase one or more
 * products at a certain price.
 */
class PriceWaiter_NYPWidget_Model_Deal extends Mage_Core_Model_Abstract
{
    /**
     * Querystring arg used to specify deal id.
     */
    const CHECKOUT_URL_DEAL_ID_ARG = 'd';

    /**
     * @var Array
     */
    private $_offerItems = null;

    /**
     * @var Array
     */
    private $_resolvedItems = null;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->_init('nypwidget/deal');
        parent::__construct();
    }

    /**
     * Initializes the parameters of this deal from a request made to the
     * "create deal" endpoint.
     * Does not actually save this Deal to the Db.
     * @return PriceWaiter_NYPWidget_Model_Deal $this
     */
    public function initFromCreateRequest(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request)
    {
        $body = $request->getBody();

        if (!empty($body->test)) {
            // Allow test deals in development
            if (getenv('MAGE_MODE') === 'developer') {
                Mage::log("Allowing test PriceWaiter deal: " . $body->id);
            } else {
                throw new PriceWaiter_NYPWidget_Exception_NoTestDeals();
            }
        }

        $this->setId($body->id);
        $this->setCreateRequestId($request->getId());
        $this->setCreatedAt(date('Y-m-d H:i:s', $request->getTimestamp()));

        $storeId = $this->getStoreIdForApiKey($request->getApiKey());
        if (!$storeId) {
            throw new PriceWaiter_NYPWidget_Exception_ApiKey();
        }
        $this->setStoreId($storeId);

        if (!empty($body->expires_at)) {
            $expires = strtotime($body->expires_at);
            $this->setExpiresAt(date('Y-m-d H:i:s', $expires));
        }

        $this->setPricewaiterBuyerId($body->buyer->id);

        // Stash full JSON for create request, so we have access to items later on.
        $this->setCreateRequestBodyJson($body);

        return $this;
    }

    /**
     */
    public function ensurePresentInQuote(Mage_Sales_Model_Quote $quote)
    {
        $items = $this->getOfferItems();

        foreach($items as $item) {
            $item->ensurePresentInQuote($quote);
        }
    }

    /**
     * @return Array Array of PriceWaiter_NYPWidget_Model_OfferItem instances.
     */
    public function getOfferItems()
    {
        if (!is_null($this->_offerItems)) {
            return $this->_offerItems;
        }

        $this->_offerItems = array_map(
            function($itemData) {
                return Mage::getModel('nypwidget/offer_item', $itemData);
            },
            $this->getCreateRequestBodyField('items', array())
        );

        return $this->_offerItems;
    }

    /**
     * @param String $formKey Magento form key used for the add to cart form.
     * @return String A URL that, when followed, will end up on the cart page with this deal applied.
     * @throws PriceWaiter_NYPWidget_Exception_SingleItemOnly
     */
    public function getAddToCartUrl($formKey)
    {
        $offerItems = $this->getOfferItems();

        if (count($offerItems) !== 1) {
            throw new PriceWaiter_NYPWidget_Exception_SingleItemOnly();
        }

        $onlyItem = $offerItems[0];
        $formValues = $onlyItem->getAddToCartForm();

        $addToCartQuery = array(
            'form_key' => $formKey,
            'product' => $formValues['product'],
            'qty' => $onlyItem->getMinimumQuantity(),
        );

        if (isset($formValues['super_attribute'])) {
            $addToCartQuery['super_attribute'] = $formValues['super_attribute'];
        }

        $urls = array(
            // 1. Add to cart.
            array(
                'checkout/cart/add',
                '_query' => $addToCartQuery,
            ),
        );

        return array_reduce(array_reverse($urls), function($prevUrl, $params) {

            $path = array_shift($params);

            if ($prevUrl) {
                $params['_query']['return_url'] = $prevUrl;
            }

            return Mage::getUrl($path, $params);

        });
    }

    /**
     * @return String A publically routeable URL to take advantage of this deal.
     */
    public function getCheckoutUrl()
    {
        return Mage::getUrl("_dealpw/checkout", array(
            '_store' => $this->getStoreId(),
            '_query' => array(
                self::CHECKOUT_URL_DEAL_ID_ARG => $this->getId(),
            ),
        ));
    }

    /**
     * @return Mage_Core_Model_Store The Store this deal is part of.
     */
    public function getStore()
    {
        $id = $this->getStoreId();
        if ($id) {
            $store = Mage::getModel('core/store');
            return $store->load($id);
        }
    }

    /**
     * @param  Integer  $now UNIX timestamp representing current time.
     * @return boolean
     */
    public function isExpired($now = null)
    {
        $expiry = $this->getExpiresAt();

        if (empty($expiry)) {
            return false;
        }

        $expiry = strtotime($expiry);
        $now = ($now === null ? time() : $now);

        return $expiry < $now;
    }

    /**
     * Nice alias for getRevoked()
     * @return boolean
     */
    public function isRevoked()
    {
        return !!$this->getRevoked();
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Deal $this
     */
    public function processCreateRequest(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request)
    {
        $this->initFromCreateRequest($request);

        $existingDeal = Mage::getModel('nypwidget/deal')->load($this->getId());

        if ($existingDeal && $existingDeal->getId()) {
            // This deal has already been created.
            throw new PriceWaiter_NYPWidget_Exception_DealAlreadyCreated();
        }

        $saveTransaction = Mage::getModel('core/resource_transaction');
        $saveTransaction->addObject($this);
        $saveTransaction->save();

        return $this;
    }

    /**
     * Main "Revoke Deal" logic.
     * @param  PriceWaiter_NYPWidget_Controller_Endpoint_Request $request
     * @return PriceWaiter_NYPWidget_Model_Deal $this
     */
    public function processRevokeRequest(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request)
    {
        if ($this->revoked) {
            throw new PriceWaiter_NYPWidget_Exception_DealAlreadyRevoked();
        }

        $this->setRevoked(1);
        $this->setRevokeRequestId($request->getId());
        $this->setRevokedAt(date('Y-m-d H:i:s', $request->getTimestamp()));

        $saveTransaction = Mage::getModel('core/resource_transaction');
        $saveTransaction->addObject($this);
        $saveTransaction->save();

        return $this;
    }

    /**
     * @internal
     * @param String $json
     */
    public function setCreateRequestBodyJson($json)
    {
        if (!is_string($json)) {
            $json = json_encode($json);
        }

        $this->setData('create_request_body_json', $json);
        $this->_createRequestBody = null;
        $this->_items = null;

        return $this;
    }

    /**
     * @internal Reads a field off the original "create" request.
     * @param  String $key
     * @param  Mixed $default
     * @return Mixed
     */
    protected function getCreateRequestBodyField($key, $default = null)
    {
        if ($this->_createRequestBody === null) {
            // Parse it!
            $this->_createRequestBody = json_decode($this->getCreateRequestBodyJson());
        }

        $b = $this->_createRequestBody;
        return isset($b->$key) ? $b->$key : null;
    }

    protected function getStoreIdForApiKey($apiKey)
    {
        $helper = Mage::helper('nypwidget');
        $store = $helper->getStoreByPriceWaiterApiKey($apiKey);
        return $store ? $store->getId() : false;
    }
}
