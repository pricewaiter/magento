const { expect, assert } = require('chai');
const crypto = require('crypto');
const querystring = require('querystring');
const request = require('request');

module.exports = function productInfoSuite(dataset) {

    function sign(body, secret) {
        const hash = crypto.createHmac('sha256', secret).update(body).digest('hex');
        return `sha256=${hash}`;
    }

    const PRICEWAITER_SHARED_SECRET = process.env.PRICEWAITER_SHARED_SECRET;

    it('returns expected data', (done) => {

        assert(PRICEWAITER_SHARED_SECRET, 'PRICEWAITER_SHARED_SECRET env is available');

        const body = querystring.stringify(
            Object.assign(
                {},
                dataset.buildOfferMetadata(),
                {
                    product_sku: dataset.product.sku,
                }
            )
        );

        const signature = sign(body, PRICEWAITER_SHARED_SECRET);

        const expectedResponse = {
            allow_pricewaiter: true,
            inventory: dataset.product.inventory,
            can_backorder: dataset.product.can_backorder,
            retail_price: dataset.product.retail_price,
            retail_price_currency: dataset.product.retail_price_currency,
            regular_price: dataset.product.regular_price,
            regular_price_currency: dataset.product.regular_price_currency,
        };

        const requestOptions = {
            url: dataset.urls.productInfo,
            method: 'POST',
            headers: {
                'Content-type': 'application/x-www-form-urlencoded',
                'X-PriceWaiter-Signature': signature,
            },
            body,
        };

        request(requestOptions, (err, resp, responseBody) => {

            if (err) {
                done(err);
                return;
            }

            expect(resp).to.have.property('statusCode', 200);

            if (!resp.headers['x-pricewaiter-signature']) {
                /* eslint-disable no-console */
                // The idea here is that if there's no signature header you're probably var_dumping
                // in PHP, so we should attempt to display it.
                console.log(responseBody);
                /* eslint-enable no-console */
            }

            const expectedSignature = sign(responseBody, PRICEWAITER_SHARED_SECRET);
            expect(resp.headers).to.have.property('x-pricewaiter-signature', expectedSignature);

            const parsedBody = JSON.parse(responseBody);
            expect(parsedBody).to.deep.equal(expectedResponse);
            done();
        });
    });
};
