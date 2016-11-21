const chai = require('chai');
const expect = chai.expect;
const request = require('request');

module.exports = function testEndpointBasics(type, url) {
    ['GET', 'PUT', 'DELETE'].forEach(method => {
        it(`returns 404 Not Found for ${method} method`, (done) => {

            const options = {
                method,
            };

            request(url, options, function (err, resp) {

                if (err) {
                    done(err);
                    return;
                }

                expect(resp).to.have.property('statusCode', 404);
                done();
            });

        });
    });

    it('returns 400 error for non-JSON POST');

    it('sets appropriate caching headers on response');

    it('returns invalid_response_signature error with invalid secret');

    it('returns invalid_version error for unexpected request data version');
};
