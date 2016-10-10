const { expect } = require('chai');
const uuid = require('node-uuid');

module.exports = function (dataset, createApiClient) {

    it('responds to pings', function () {
        const makeApiRequest = createApiClient({
            type: 'ping',
            url: dataset.urls.ping,
            version: dataset.version,
        });

        const body = {
            ping: uuid.v4(),
        };

        return makeApiRequest(body).then(r => {
            expect(r.response.body).to.deep.equal(body);
        });
    });

};
