const { expect, assert } = require('chai');
const qs = require('querystring');

const MAGENTO_BASE_URL = process.env.MAGENTO_BASE_URL;
assert(MAGENTO_BASE_URL, 'MAGENTO_BASE_URL is defined');

const PRICEWAITER_API_KEY = process.env.PRICEWAITER_API_KEY;
assert(PRICEWAITER_API_KEY, 'PRICEWAITER_API_KEY is defined');

const PRICEWAITER_SHARED_SECRET = process.env.PRICEWAITER_SHARED_SECRET;
assert(PRICEWAITER_SHARED_SECRET, 'PRICEWAITER_API_KEY is defined');

// Helper used to highlight PHP errors when running functional tests
function checkForPhpError(rawBody) {

    // The idea here is that if PHP outputted an error we may be able to detect it.
    // This makes developing the Magento extension easier.
    const regex = /<b>(?:Fatal|Parse) error<\/b>:(.+)\n/;
    const m = regex.exec(rawBody);

    if (m) {
        const message = m[1].replace(/<[^>]+>/g, '');
        const err = new Error(message);
        err.code = 'EPHP';
        throw err;
    }

}

module.exports = {

    name: 'Magento v1.9 Simple Product',

    apiKey: PRICEWAITER_API_KEY,

    sharedSecret: PRICEWAITER_SHARED_SECRET,

    version: '2016-03-01',

    supports: {
        couponCodes: false,
        testDeals: false,
    },

    urls: {
        cart: `${MAGENTO_BASE_URL}/checkout/cart`,
        createDeal: `${MAGENTO_BASE_URL}/pricewaiter/createdeal`,
        ping: `${MAGENTO_BASE_URL}/pricewaiter/ping`,
        revokeDeal: `${MAGENTO_BASE_URL}/pricewaiter/revokedeal`,
        orderCallback: `${MAGENTO_BASE_URL}/index.php/pricewaiter/callback`,
        productInfo: `${MAGENTO_BASE_URL}/pricewaiter/productinfo`,
        listOrders: `${MAGENTO_BASE_URL}/pricewaiter/listorders`,
    },

    dealItems: [
        {
            product: {
                sku: 'hde012',
            },
            amount_per_item: {
                cents: 9999,
                value: '99.99',
            },
            quantity: {
                min: 3,
                max: 3,
            },
            metadata: {
            },
        },
    ],

    getDealItems() {
        const items = [].concat(this.dealItems);
        items[0].metadata = this.buildOfferMetadata();
        return items;
    },

    product: {
        id: 399,
        sku: 'hde012',
        can_backorder: false,
        inventory: 24,
        regular_price: '150.0000',
        regular_price_currency: 'USD',
        retail_price: '150',
        retail_price_currency: 'USD',
    },

    billingAddress: {
        address: '1234 Billing St.',
        address2: 'Apt B',
        address3: '',
        city: 'Billings',
        state: 'MT',
        zip: '59101',
        country: 'US',
    },

    shippingAddress: {
        address: '99 Shipping Ave.',
        address2: 'Floor 5',
        address3: 'Unit 3',
        city: 'Bellingham',
        state: 'WA',
        zip: '98225',
        country: 'US',
    },

    offer: {
        amountPerItem: '99.99',
        quantity: 2,
    },

    // Builds a set of standard offer metadata
    buildOfferMetadata() {

        const metadata = {
            _magento_product_configuration: qs.stringify(this.getAddToCartForm()),
        };

        // HACK: Platform JS currently double-encodes this.
        metadata._magento_product_configuration =
            encodeURIComponent(metadata._magento_product_configuration);

        return metadata;
    },

    getAddToCartForm() {
        return {
            form_key: 'eXuDEldwlIECwEeU',
            product: this.product.id,
            related_product: '',
            qty: this.dealItems[0].quantity.min,
        };
    },

    // Hook called for every request made via the client.
    validateResponse(r) {

        const { rawBody, headers } = r.response;

        checkForPhpError(rawBody);

        expect(headers).to.have.property('x-pricewaiter-platform', 'Magento Community');

        expect(headers).to.have.property('x-pricewaiter-platform-version')
            .match(/^1\.9\.\d+\.\d+$/);

        expect(headers).to.have.property('x-pricewaiter-extension-version')
            .match(/^3\.\d+\.\d+$/);
    },

    validateCheckoutUrl(deal, url) {
        expect(url).to.contain(deal.id);
    },

    /**
     * @param  {Array} responses An array of objects with `url`, `response` and `body` properties.
     */
    validateCheckoutUrlFlow(responsesWithBodies) {
        expect(responsesWithBodies).to.have.length(2);

        const paths = responsesWithBodies
            .map(r =>
                r.url
                    .replace(MAGENTO_BASE_URL, '')
                    .replace(/\?.*/, '')
            );

        // First item in list is the checkout_url
        paths.shift();

        expect(paths).to.deep.equal([
            '/checkout/cart/',
        ]);
    },
};
