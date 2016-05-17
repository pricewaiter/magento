// This is the functional test running.
const fs = require('fs');
const path = require('path');

const SUITES = [
    {
        name: 'Product Info',
        modules: [
            './tests/product_info',
        ],
    },
    {
        name: 'Order Callback',
        modules: [
            './tests/order_callback',
        ],
    },
];

const DATASETS = [];
fs.readdirSync(path.join(__dirname, 'datasets/magento_v19')).forEach(f => {

    if (!/\.js$/.test(f)) {
        return;
    }

    const name = path.basename(f, '.js');
    const module = path.join(__dirname, 'datasets', 'magento_v19', name);

    DATASETS.push(require(module));
});

// -----------------------------------------------------------------------------
// Actual test suite follows
// -----------------------------------------------------------------------------

DATASETS.forEach(dataset => {

    function createApiClient(incomingOptions) {

        const options = Object.assign(
            {
                apiKey: process.env.PRICEWAITER_API_KEY,
                sharedSecret: process.env.PRICEWAITER_SHARED_SECRET,
            },
            incomingOptions || {}
        );

        const makeApiRequest = require('@pricewaiter/integration-api-client')(options);

        return function makeApiRequestWithTweaks(url, body, version) {
            return makeApiRequest.call(this, {
                url,
                body,
                version,
            })
                .then(r => {

                    if (typeof dataset.validateResponse === 'function') {
                        dataset.validateResponse(r);
                    }

                    return r;
                })
                .catch(err => {
                    /* eslint-disable no-console */
                    if (err.response) {
                        console.log(err.response.rawBody);
                    }
                    /* eslint-enable no-console */
                    throw err;
                });
        };

    }

    describe(dataset.name, () => {

        SUITES.forEach(suite => {

            describe(suite.name, () => {

                suite.modules.forEach(m => {

                    const resolvedModule = typeof m === 'function' ?
                        m :
                        require(m);

                    resolvedModule(dataset, createApiClient);

                });

            });

        });

    });

});
