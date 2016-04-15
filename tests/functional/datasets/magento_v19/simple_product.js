const { expect, assert } = require('chai');
const querystring = require('querystring');

const MAGENTO_BASE_URL = process.env.MAGENTO_BASE_URL;
assert(MAGENTO_BASE_URL, 'MAGENTO_BASE_URL is defined');

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

    version: '2016-03-01',

    urls: {
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
