<?php

/**
 * Controller that serves the checkout_url endpoint used for deals.
 */
class PriceWaiter_NYPWidget_CheckoutController
    extends Mage_Core_Controller_Front_Action
{
    /**
     * Header used to provide feedback about errors.
     * This is non-spec behavior added as a convenience.
     */
    const ERROR_HEADER = 'X-PriceWaiter-Error';

    /**
     * @internal
     */
    public function indexAction()
    {
        $httpRequest = $this->getRequest();
        $httpResponse = $this->getResponse();

        try
        {

            $id = $httpRequest->getQuery(
                PriceWaiter_NYPWidget_Model_Deal::CHECKOUT_URL_DEAL_ID_ARG,
                null
            );

            $deal = $this->getDealById($id);

            if (!$deal) {
                $this->redirectToHomepage('deal_not_found');
                return;
            }

            $this->prepareCart($deal);

            // Attempt to include some debugging info with redirect
            $errorCode = null;

            if ($deal->isRevoked()) {
                $errorCode = 'deal_revoked';
            } else if ($deal->isExpired()) {
                $errorCode = 'deal_expired';
            }

            $this->redirectToCart($errorCode);
        }
        catch (PriceWaiter_NYPWidget_Exception_Product_OutOfStock $ex)
        {
            // The product is not currently in stock, so our add-to-cart
            // failed. Forward the user to the cart page and show an
            // error message (this matches built-in A2C error behavior)
            $this->redirectToProductPage(
                $deal,
                $ex->errorCode,
                $ex->getMessage()
            );
        }
        catch (PriceWaiter_NYPWidget_Exception_Abstract $ex)
        {
            Mage::logException($ex);
            $this->redirectToHomepage($ex->errorCode);
        }
        catch (Exception $ex)
        {
            Mage::logException($ex);
            $this->redirectToHomepage();
        }
    }

    /**
     * @internal
     */
    protected function addMessageToSession($message)
    {
        // This is adapted from CartController's A2C handling.
        // The idea is that if there was an error adding to cart, we should
        // report that error to the user in some way.
        $session = Mage::getSingleton('checkout/session');
        $shouldUseNotice = $session->getUseNotice(true);

        if ($shouldUseNotice) {
            $message = Mage::helper('core')->escapeHtml($message);
            $session->addNotice($message);
        } else {
            $messages = array_unique(explode("\n", $message));
            foreach ($messages as $message) {
                $message = Mage::helper('core')->escapeHtml($message);
                $session->addError($message);
            }
        }
    }

    /**
     * @param  String $id
     * @return PriceWaiter_NYPWidget_Model_Deal|false
     */
    protected function getDealById($id)
    {
        if (!$id) {
            return false;
        }

        $deal = Mage::getModel('nypwidget/deal');
        $deal->load($id);

        return $deal->getId() ? $deal : false;
    }

    /**
     * Ensures that the buyer's cart contains the contents of the given deal.
     * @param  PriceWaiter_NYPWidget_Model_Deal $deal
     */
    protected function prepareCart(PriceWaiter_NYPWidget_Model_Deal $deal)
    {
        $cart = Mage::getSingleton('checkout/cart');
        $quote = $cart->getQuote();
        $deal->ensurePresentInQuote($quote);
        $cart->save();

        // Track the current PW buyer ID so we can automatically discover
        // other deals the buyer has made.
        Mage::getSingleton('nypwidget/session')
            ->setBuyerId($deal->getPricewaiterBuyerId());
    }

    /**
     * Redirects the buyer back to the cart page.
     * @param  string $errorCode
     */
    protected function redirectToCart($errorCode = null, $errorMessage = null)
    {
        $url = Mage::getUrl('checkout/cart');
        $this->_doRedirectWithError($url, $errorCode, $errorMessage);
    }

    /**
     * Redirects the buyer back to the store's homepage.
     * Used when deal is invalid in some way.
     */
    protected function redirectToHomepage($errorCode = null)
    {
        $url = Mage::getUrl('/');
        $this->_doRedirectWithError($url, $errorCode);
    }

    /**
     * Sends the user to the product page, optionally setting an error
     * code header and displaying an error message.
     *
     * If something goes wrong, the user is redirected to the homepage.
     *
     * @param  PriceWaiter_NYPWidget_Model_Deal $deal
     * @param  string                           $errorCode
     * @param  string                           $errorMessage
     */
    protected function redirectToProductPage(
        PriceWaiter_NYPWidget_Model_Deal $deal,
        $errorCode = null,
        $errorMessage = null
    )
    {
        $offerItems = $deal->getOfferItems();
        if (count($offerItems) > 0) {
            try
            {
                $url = $offerItems[0]->getMagentoProductUrl();
                return $this->_doRedirectWithError($url, $errorCode, $errorMessage);
            }
            catch (Exception $ex)
            {
                // A malformed deal could result in getMagentoProductUrl() throwing
                Mage::logException($ex);
            }
        }

        // Fall back to homepage when something horrible happens
        return $this->redirectToHomepage($errorCode);
    }

    /**
     * @internal
     * 302 Redirects the user, optionally setting an error code header
     * and session-based error message.
     */
    private function _doRedirectWithError($url, $errorCode = null, $errorMessage = null)
    {
        $httpResponse = $this->getResponse();

        if ($errorCode !== null) {
            $httpResponse->setHeader(self::ERROR_HEADER, $errorCode, true);
        }

        $httpResponse->setRedirect($url);

        if ($errorMessage !== null) {
            $this->addMessageToSession($errorMessage);
        }
    }

}
