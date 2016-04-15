const simpleProduct = require('./simple_product');

module.exports = Object.assign({}, simpleProduct, {

    name: 'Magento v1.9 Configurable Product',

    product: {
        id: 404,
        sku: 'msj006c',
        options: [
            {
                id: 92,
                name: 'Color',
                value: 'Charcoal',
                value_id: 17,
            },
            {
                id: 180,
                name: 'Size',
                value: 'S',
                value_id: 80,
            },
        ],

        can_backorder: false,
        inventory: 22,
        regular_price: '160.0000',
        regular_price_currency: 'USD',
        retail_price: '160',
        retail_price_currency: 'USD',
    },

    getAddToCartForm() {
        const result = simpleProduct.getAddToCartForm.call(this);

        // Add super_attribute fields for selected product options
        this.product.options.forEach(opt => {
            result[`super_attribute[${opt.id}]`] = opt.value_id;
        });

        return result;
    },

});
