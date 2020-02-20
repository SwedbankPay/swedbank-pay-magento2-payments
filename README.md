# Swedbank Pay Magento 2 Payments

![Swedbank Pay Magento 2 Payments][opengraph-image]

The Swedbank Pay Payments module for Magento 2 integrates the all the payment methods provided by Swedbank Pay into Magento 2 for enabling interaction with the Swedbank Pay eCommerce API.

## Requirements

* Magento Open Source/Commerce version 2.2 or newer

**Please Note:** When your  Merchant Account is created, there are a few things you need to attend to before you can
start using it.

## Installation 

Swedbank Pay Magento 2 Payments may be installed via Magento Marketplace or Composer.

### Magento Marketplace

If you have linked your Marketplace account to your Magento 2 store, you may install the Swedbank Pay Magento 2 Payments 
with the Magento Component Manager.

For installation using the Component Manager, please see [the official guide][component-manager].

### Composer

Swedbank Pay Payments for Magento 2 can alternatively be installed via composer with
the following instructions:

1. In the Magento root directory enter command:

    ```sh
    composer require swedbank-pay/magento2-payments --no-update
    ```

2. Install module and required packages:

    ```sh
    composer update swedbank-pay/magento2-payments --with-dependencies
    ```

3. Enable the modules:

    ```sh
    bin/magento module:enable --clear-static-content SwedbankPay_Core SwedbankPay_Payments
    ```

4. Upgrade setup:

    ```sh
    bin/magento setup:upgrade
    ```

5. Compile:

    ```sh
    bin/magento setup:di:compile
    ```

6. Clear the cache:

    ```sh
    bin/magento cache:clean
    ```


## Configuration

Swedbank Pay Payments configuration can be found under **Stores** >
**Configuration** > **Sales** > **Payment Methods** > **Swedbank Pay** >
**Configure**.

As parts of the Swedbank Pay Payments installation we have **Core**, **Payments** and **Payment Menu**
with configurable options as follows:

### Core

* **Enabled**: Status of the module.
* **Merchant Account**: Your Swedbank Pay Merchant Account ID.
* **Payee ID**: Your Swedbank Pay Payee ID.
* **Payee Name**: Your Swedbank Pay Payee Name.
* **Test Mode**: Only disable in live production site.
* **Debug Mode**: Enable this for more in-depth logging, should be off by default.

### Payments

* **Enabled**: Status of the module.
* **Available Instruments**: Enable different payment instruments. (Please go to **store view** scope to select payment instruments)

## Support

To find the customer service available in your country, please visit
[the Swedbank Pay website][swedbank-pay].

## License

Swedbank Pay Magento 2 Payments is released under [Apache V2.0 licence][license].

[opengraph-image]: https://repository-images.githubusercontent.com/211832427/a3dde300-53e7-11ea-9c04-7a2cacb27ad2
[component-manager]: http://docs.magento.com/marketplace/user_guide/quick-tour/install-extension.html
[license]: LICENSE
[swedbank-pay]: https://swedbankpay.com/
