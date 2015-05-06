# PriceWaiter® Name Your Price Widget Extension

[![Build Status](https://api.travis-ci.org/pricewaiter/magento.svg)](https://travis-ci.org/pricewaiter/magento)

The PriceWaiter Name Your Price Widget Extension integrates the Name Your Price button available
from [PriceWaiter](http://pricewaiter.com) into your Magento store.

For more information about this extension's features, or to install through the Magento Connect
Manager, visit our page on [Magento Connect](http://www.magentocommerce.com/magento-connect/).

## Installation

We recommend installing this extension through the Magento Connect Manager. If you prefer to
install via git, we suggest using [modman](https://github.com/colinmollenhour/modman). You will
also need a PriceWaiter account and API key to enable the widget. Signup for a PriceWaiter account
[here](http://www.pricewaiter.com/) and get your API key by logging into your account and selecting
'Name Your Price Button' from the left navigation. The API key is in the second block of JavaScript.

1. Navigate to the root directory of your Magento installation
2. If you have not initiallized modman, execute `modman init`.
3. Navigate to '.modman' and execute `modman clone git://github.com/pricewaiter/magento.git`. This will
clone the git repo into your Magento install, and create the necessary symlinks.
4. Clear your Magento Cache, and log into your Magento Admin Panel. If you are logged in now, you may
need to log out and back in to trigger the installation process.
5. In Magento, navigate to System -> Configuration. Under the 'Advanced' tab, click 'Developer', and
under the 'Template Settings' heading set 'Allow Symlinks' to 'Yes'.
6. Under the 'Sales' tab, click 'PriceWaiter'. Enter your API Key into the 'API Key' field.
7. Log into your PriceWaiter account, and click the 'Name Your Price Button' link. In the 'New Order
Notification API' field, enter 'http://yourmagentostoreurl.com/pricewaiter/callback'. Be sure to
reploace 'yourmagentostoreurl.com' with the Base URL of your Magento Store.

The PriceWaiter Name Your Price Extension is now installed in your Magento store.

## Controlling the Widget

This extension allows you to enable the Name Your Price button by store, product, or category.
In your Magento Admin Panel, under System -> Configuration, click 'PriceWaiter' under the 'Sales'
tab. You can disable the button by setting 'Enabled' to 'No'. You can also change the configuration
scope, and set any options by store.

To disable the button for a product, select Catalog -> Manage Products in your Magento Admin Panel.
Edit the product you would like to disable the button for, and find the 'PriceWaiter Widget Enabled'
field in the 'General' tab. Setting this to 'No' will hide the button on this product's page.

To disable the button for a category, select Catalog -> Manage Categories in your Magento Admin Panel.
Edit the category you would like to disable the button for, and find the 'PriceWaiter Widget Enabled'
field in the 'General' tab. Setting this to 'No' will hide the button in this category.

## Appearance of the Widget

In your Magento Admin Panel, under System -> Configuration, click 'PriceWaiter' under the 'Sales'
tab. The color of the button, and button's hover color can be controlled under the 'Appearance' heading.
Saving these settings provides a preview button on this page.

Changing the configuration scope allows for the appearance to be controlled by store.

The widget is displayed on product pages inside a `<div>` with the class 'name-your-price-widget'. This
allows custom CSS to alter the position of the button on your product page. For example, to add 20px of
padding around the button on your product page, add this CSS to your existing template.
```css
.name-your-price-widget {
    padding: 20px;
}
```

## Building as a custom package

As of version 1.2.5, you can now build the Extension as a custom package from source.

Dependencies:

* [modman](https://github.com/colinmollenhour/modman)
* [composer](https://getcomposer.org/)
* [npm](https://www.npmjs.com/)

Build Instructions:

1. Create a Magento store to use for development.
2. Follow steps 1-4 of the installation instructions.
3. Make your desired modifications to the extension in `.modman/magento/`
4. Run `npm install` in `.modman/magento`.
5. Run `composer install` in `.modman/magento`.
6. Run `vendor/bin/magegen list` to review all subcommands of the make tool.
7. When you are ready to build the new package, execute `vendor/bin/magegen build`

You should now have a 'nypwidget-{version number}.tgz' file in your git repo.
