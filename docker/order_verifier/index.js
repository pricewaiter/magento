const express = require('express');
const bodyParser = require('body-parser');

const app = express();

app.use(bodyParser.urlencoded({ extended: true }));

app.post('/1/order/verify', (req, res) => {

    if (!req.body) {
        console.log('No body detected on incoming request', req.headers);
        res.send('0');
        return;
    }

    if (req.body.pricewaiter_id === '666') {
        console.log('Evil pricewaiter_id detected, not verifying');
        res.send('0');
        return;
    }

    console.log('Verifying order', req.body);
    res.send('1');
});

const port = process.env.PORT;

if (!port) {
    throw new Error('No port provided.');
}

app.listen(port, function() {
    console.log(`Listening for orders to verify on ${port}`);
});
