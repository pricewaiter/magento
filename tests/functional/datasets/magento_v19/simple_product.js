const { expect, assert } = require('chai');
const querystring = require('querystring');

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

    urls: {
        orderCallback: `${MAGENTO_BASE_URL}/index.php/pricewaiter/callback`,
        productInfo: `${MAGENTO_BASE_URL}/pricewaiter/productinfo`,
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
            _magento_product_configuration: querystring.stringify(this.getAddToCartForm()),
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
            qty: '1',
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

};
