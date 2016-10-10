'use strict';
const { expect, assert } = require('chai');
const request = require('request');
const uuid = require('node-uuid');

module.exports = function (dataset, createApiClient) {

    const itIfUsingCouponCodes = dataset.supports.couponCodes ? it : it.skip;

    function buildCreateDealRequestBody() {
        return {
            id: uuid.v4(),
            currency: 'USD',
            items: dataset.getDealItems(),
            buyer: {
                email: 'buyer@example.org',
            },
            coupon_code_prefix: 'PW',
        };
    }

    /**
     * Takes a URL and returns a Promise that resolves with an array of
     * objects with the following keys: url, resp, body. Details the
     * actual redirects handled.
     */
    function doRequestChain(url, responsesWithBodies, cookie) {

        /* eslint-disable no-param-reassign*/
        if (responsesWithBodies === undefined) {
            responsesWithBodies = [];
        }
        /* eslint-enable no-param-reassign*/

        return new Promise((resolve, reject) => {

            const options = {
                followRedirect: false,
            };
            if (cookie) {
                options.headers = {
                    Cookie: cookie,
                };
            }

            request(url, options, function (err, resp, body) {

                if (err) {
                    reject(err);
                    return;
                }

                // Check for (non-spec) x-pricewaiter-error header used to report problems.
                if (resp.headers['x-pricewaiter-error']) {
                    const errCode = resp.headers['x-pricewaiter-error'];
                    reject(new Error(`Error requesting '${url}': ${errCode}`));
                    return;
                }

                let newCookie = null;
                if (resp.headers['set-cookie']) {
                    newCookie = request.cookie(resp.headers['set-cookie'][0]).cookieString();
                }

                responsesWithBodies.push({ url, resp, body });

                if (resp.statusCode === 301 || resp.statusCode === 302) {
                    doRequestChain(
                        resp.headers.location,
                        responsesWithBodies,
                        newCookie
                    ).then(resolve, reject);
                    return;
                }

                resolve(responsesWithBodies);
            });

        });

    }

    const makeCreateDealRequest = createApiClient({
        type: 'create_deal',
        url: dataset.urls.createDeal,
        version: dataset.version,
    });

    it('returns a checkout_url that redirects to pre-filled cart', function () {
        this.timeout(20 * 1000);

        const createDealRequestBody = buildCreateDealRequestBody();

        return makeCreateDealRequest(createDealRequestBody)
            .then(function (r) {

                expect(r.response).to.have.property('statusCode', 200);
                expect(r.response.body).to.be.an('object');
                expect(r.response.body).to.have.property('checkout_url');
                expect(r.response.body.checkout_url).to.match(/^https?:/);

                if (typeof dataset.validateCheckoutUrl === 'function') {
                    dataset.validateCheckoutUrl(
                        createDealRequestBody,
                        r.response.body.checkout_url
                    );
                }

                return r.response.body.checkout_url;
            })
            .then(doRequestChain)
            .then(responsesWithBodies => {
                dataset.validateCheckoutUrlFlow(responsesWithBodies);
            });

    });

    itIfUsingCouponCodes('returns a coupon_code', function () {

        const requestBody = buildCreateDealRequestBody();

        return makeCreateDealRequest(requestBody)
            .then(function (r) {
                expect(r.response).to.have.property('statusCode', 200);
                expect(r.response.body).to.be.an('object');
                return r.response.body.coupon_code;
            })
            .then(function (couponCode) {
                expect(couponCode).to.be.a('string');
                expect(couponCode).to.match(/^PW.+$/);
            });
    });

    it('returns deal_already_exists error if deal already created', function () {

        const deal = buildCreateDealRequestBody();

        function doCreate() {
            return makeCreateDealRequest(deal);
        }

        return doCreate().then(doCreate)
            .then(() => {
                assert(false, 'Second create deal should not have succeeded.');
            })
            .catch(err => {
                if (err.code !== 'deal_already_exists') {
                    throw err;
                }
                expect(err).to.have.property('statusCode', 400);
            });

    });

};
