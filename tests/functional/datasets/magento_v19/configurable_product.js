const qs = require('querystring');
const simpleProduct = require('./simple_product');

module.exports = Object.assign({}, simpleProduct, {

    name: 'Magento v1.9 Configurable Product',

    dealItems: [
        {
            product: {
                sku: 'msj006c',
            },
            amount_per_item: {
                cents: 9999,
                value: '99.99',
            },
            quantity: {
                min: 1,
                max: 1,
            },
            metadata: {
                _magento_product_type: 'configurable',
                _magento_product_configuration: qs.stringify({
                    form_key: 'xXMpsryvI88PjkwD',
                    product: 404,
                    'super_attribute[92]': 25,
                    'super_attribute[180]': 79,
                }),
            },
        },
    ],

    product: {
        id: 404,
        sku: 'msj006c',
        options: [
            {
                id: 92,
                name: 'Color',
                value: 'Khaki',
                value_id: 25,
            },
            {
                id: 180,
                name: 'Size',
                value: 'M',
                value_id: 79,
            },
        ],

        can_backorder: false,
        inventory: 100,
        regular_price: '160.0000',
        regular_price_currency: 'USD',
        retail_price: '160',
        retail_price_currency: 'USD',
    },

    getAddToCartForm() {
        const result = simpleProduct.getAddToCartForm.call(this);
        result._magento_product_type = 'configurable';

        // Add super_attribute fields for selected product options
        this.product.options.forEach(opt => {
            result[`super_attribute[${opt.id}]`] = opt.value_id;
        });

        return result;
    },
});
