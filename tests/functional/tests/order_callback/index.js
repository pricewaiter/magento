'use strict';

const { expect } = require('chai');
const request = require('request');
const uuid = require('node-uuid');

module.exports = function orderCallbackSuite(dataset) {
    'use strict';

    function makeOrderCallbackRequest(requestBody) {
        return new Promise((resolve, reject) => {
            const requestOptions = {
                url: dataset.urls.orderCallback,
                method: 'POST',
                form: requestBody,
            };

            request(requestOptions, (err, resp, body) => {
                if (err) {
                    reject(err);
                    return;
                }
                resolve({ resp, body });
            });
        });
    }

    function throwErrorIfDetected(resp) {

        let message = resp.headers['x-platform-error'];
        const code = resp.headers['x-platform-error-code'];

        if (message || code) {
            if (code) {
                message = `${message} (${code})`;
            }
            throw new Error(message);
        }

    }

    const goodRequestBody = {
        api_key: dataset.apiKey,
        pricewaiter_id: uuid.v4(),
        product_sku: dataset.product.sku,
        buyer_email: `${uuid.v4()}@example.org`,
        buyer_billing_address: dataset.billingAddress.address,
        buyer_billing_address2: dataset.billingAddress.address2 || '',
        buyer_billing_address3: dataset.billingAddress.address3 || '',
        buyer_billing_state: dataset.billingAddress.state,
        buyer_billing_zip: dataset.billingAddress.zip,
        buyer_billing_country: dataset.billingAddress.country,
        buyer_billing_phone: dataset.billingAddress.phone || '',
        buyer_shipping_address: dataset.shippingAddress.address,
        buyer_shipping_address2: dataset.shippingAddress.address2 || '',
        buyer_shipping_address3: dataset.shippingAddress.address3 || '',
        buyer_shipping_state: dataset.shippingAddress.state,
        buyer_shipping_zip: dataset.shippingAddress.zip,
        buyer_shipping_country: dataset.shippingAddress.country,
        buyer_shipping_phone: dataset.shippingAddress.phone || '',
        unit_price: dataset.offer.amountPerItem,
        quantity: dataset.offer.quantity,
    };

    (dataset.product.options || []).forEach((opt, i) => {
        goodRequestBody[`product_option_name${i + 1}`] = opt.name;
        goodRequestBody[`product_option_value${i + 1}`] = opt.value;
    });
    goodRequestBody.product_option_count = (dataset.product.options || []).length;

    const badRequestBody = Object.assign({}, goodRequestBody, {
        api_key: dataset.apiKey,
        pricewaiter_id: '666', // known bad order id, will fail verification
    });

    it('responds to invalid data appropriately', () =>
        makeOrderCallbackRequest(badRequestBody).then(({ resp, body }) => {

            if (resp.statusCode !== 400) {
                /* eslint-disable no-console */
                console.log(body);
                /* eslint-enable no-console */
            }

            expect(resp).to.have.property('statusCode', 400);
            expect(resp.headers).to.have.property('x-platform-error');
            expect(resp.headers).to.have.property(
                'x-platform-error-code',
                'invalid_order_data'
            );
            expect(resp.headers).not.to.have.property('x-platform-order-id');
        })
    );

    it('responds to good data appropriately', () =>
        makeOrderCallbackRequest(goodRequestBody).then(({ resp, body }) => {

            throwErrorIfDetected(resp);

            if (resp.statusCode === 200 && !resp.headers['x-platform-order-id']) {
                /* eslint-disable no-console */
                console.log(resp.headers, body);
                /* eslint-enable no-console */
            }

            expect(resp).to.have.property('statusCode', 200);
            expect(resp.headers).not.to.have.property('x-platform-error');
            expect(resp.headers).not.to.have.property('x-platform-error-code');
            expect(resp.headers).to.have.property('x-platform-order-id').match(/^\d+$/);
        })
    );
};
