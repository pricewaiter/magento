const chai = require('chai');
const expect = chai.expect;
const assert = chai.assert;

const uuid = require('node-uuid');

module.exports = function (dataset, createApiClient) {

    function buildCreateDealRequestBody() {
        return {
            id: uuid.v4(),
            currency: 'USD',
            items: dataset.dealItems,
            buyer: {
                email: 'buyer@example.org',
            },
            coupon_code_prefix: 'PW',
        };
    }

    const makeCreateDealRequest = createApiClient({
        type: 'create_deal',
        url: dataset.urls.createDeal,
        version: dataset.version,
    });

    const makeRevokeDealRequest = createApiClient({
        type: 'revoke_deal',
        url: dataset.urls.revokeDeal,
        version: dataset.version,
    });

    it('returns deal_not_found for a made-up deal id.', () => {

        const fakeDealId = 'some-fake-deal-that-does-not-exist';

        const revokePromise = makeRevokeDealRequest({ id: fakeDealId });

        return revokePromise
            .then(() => {
                assert.fail('Request should not succeed.');
            })
            .catch(err => {
                if (err.code !== 'deal_not_found') {
                    throw err;
                }
                expect(err).to.have.property('statusCode', 400);
            });
    });

    it('revokes a deal', () => {

        const deal = buildCreateDealRequestBody();

        const createPromise = makeCreateDealRequest(deal);

        const revokePromise = createPromise.then(() =>
            makeRevokeDealRequest({ id: deal.id })
        );

        return revokePromise.then(r => {
            expect(r.response).to.have.property('statusCode', 200);
        });

    });

    it('returns deal_already_revoked after a deal has already been revoked.', () => {

        const deal = buildCreateDealRequestBody();

        function doRevoke() {
            return makeRevokeDealRequest({ id: deal.id });
        }

        const createPromise = makeCreateDealRequest(deal);

        const revokePromise = createPromise.then(doRevoke);

        const revokeAgainPromise = revokePromise.then(doRevoke);

        return revokeAgainPromise
            .then(() => {
                assert.fail('second revoke should result in deal_already_revoked error.');
            })
            .catch(err => {
                if (err.code !== 'deal_already_revoked') {
                    throw err;
                }
                expect(err).to.have.property('statusCode', 400);
            });
    });

};
