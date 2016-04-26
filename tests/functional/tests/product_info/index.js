const { expect } = require('chai');
const crypto = require('crypto');
const querystring = require('querystring');
const request = require('request');

module.exports = function productInfoSuite(dataset) {

    function sign(body, secret) {
        const hash = crypto.createHmac('sha256', secret).update(body).digest('hex');
        return `sha256=${hash}`;
    }

    it('returns expected data', (done) => {

        const body = querystring.stringify(
            Object.assign(
                {},
                dataset.buildOfferMetadata(),
                {
                    product_sku: dataset.product.sku,
                }
            )
        );

        const signature = sign(body, dataset.sharedSecret);

        const expectedResponse = {
            allow_pricewaiter: true,
            inventory: dataset.product.inventory,
            can_backorder: dataset.product.can_backorder,
            retail_price: dataset.product.retail_price,
            retail_price_currency: dataset.product.retail_price_currency,
            regular_price: dataset.product.regular_price,
            regular_price_currency: dataset.product.regular_price_currency,
        };

        // Allow for optional fields in response
        ['regular_price', 'regular_price_currency'].forEach(optionalKey => {
            if (expectedResponse[optionalKey] === undefined) {
                delete expectedResponse[optionalKey];
            }
        });

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

            const expectedSignature = sign(responseBody, dataset.sharedSecret);
            expect(resp.headers).to.have.property('x-pricewaiter-signature', expectedSignature);

            const parsedBody = JSON.parse(responseBody);
            expect(parsedBody).to.deep.equal(expectedResponse);
            done();
        });
    });
};
