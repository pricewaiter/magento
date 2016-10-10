const simpleProduct = require('./simple_product');

module.exports = Object.assign({}, simpleProduct, {

    name: 'Magento v1.9 Bundle Product',

    product: {
        id: 445,
        sku: 'hde009',
        options: [
            {
                name: 'Camera',
                value: 'Madison LX2200 +$425.00',
                id: 20,
                value_id: 82,
            },
            {
                name: 'Case',
                value: 'Large Camera Bag +$120.00',
                id: 18,
                value_id: 79,
            },
            {
                name: 'Memory',
                value: '8 GB Memory Card +$20.00',
                id: 19,
                value_id: 81,
            },
            {
                name: 'Warranty',
                value: '3-Year Warranty +$75.00',
                id: 17,
                value_id: 77,
            },
        ],

        can_backorder: true,
        inventory: 0,
        // regular_price: '640.0000',
        // regular_price_currency: 'USD',
        retail_price: '640',
        retail_price_currency: 'USD',

    },

    getAddToCartForm() {
        const result = simpleProduct.getAddToCartForm.call(this);
        result._magento_product_type = 'bundle';

        this.product.options.forEach(opt => {
            result[`bundle_option[${opt.id}]`] = opt.value_id.toString();
            result[`bundle_option_qty[${opt.id}]`] = '1';
        });

        return result;
    },

});
