const { expect, assert } = require('chai');
const uuid = require('node-uuid');

module.exports = function (dataset, createApiClient) {

    if (dataset.supports.testDeals) {
        return;
    }

    it('returns no_test_deals error when trying to create a test deal', () => {

        const makeApiRequest = createApiClient({
            type: 'create_deal',
            url: dataset.urls.createDeal,
            version: dataset.version,
        });

        const createPromise = makeApiRequest({
            id: uuid.v4(),
            currency: 'USD',
            test: true,
            items: dataset.dealItems,
            buyer: {
                email: 'buyer@example.org',
            },
            coupon_code_prefix: 'PW',
        });

        return createPromise
            .then(() => {
                assert(false, 'create deal should have failed');
            })
            .catch(err => {
                if (err.code !== 'no_test_deals') {
                    throw err;
                }
                expect(err.statusCode).to.equal(400);
            });
    });

};

