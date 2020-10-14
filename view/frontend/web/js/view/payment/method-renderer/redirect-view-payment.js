define([
    'Magento_Checkout/js/view/payment/default',
    'ko',
    'jquery',
    'mage/storage',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/payment-service',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/checkout-data-resolver',
    'uiRegistry',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Ui/js/model/messages',
    'uiLayout',
    'Magento_Checkout/js/action/redirect-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function (
    Component,
    ko,
    $,
    storage,
    placeOrderAction,
    selectPaymentMethodAction,
    quote,
    customer,
    paymentService,
    checkoutData,
    checkoutDataResolver,
    registry,
    additionalValidators,
    Messages,
    layout,
    redirectOnSuccessAction,
    fullscreenLoader,
    customerData
) {
    'use strict';

    var redirectUrl = ko.observable('');
    var isRedirectUrlVisible = ko.observable(false);
    var paymentErrors = ko.observable([]);

    return Component.extend({
        defaults: {
            template: 'SwedbankPay_Payments/payment/redirect-view'
        },
        config: {
            data: {
                culture: 'en-US',
                logo: 'SwedbankPay_Payments/images/swedbank-pay-logo.svg'
            }
        },
        redirectUrl: redirectUrl,
        isRedirectUrlVisible: isRedirectUrlVisible,
        paymentErrors: paymentErrors,
        logoUrl: function () {
            return require.toUrl(this.config.data.logo);
        },
        initialize: function () {
            var self = this;
            self.totals = {};
            self.instrumentScript = '';
            self.instrumentElement = {};

            self._super();
            Object.assign(this.config.data, window.checkoutConfig.SwedbankPay_Payments);
            Object.assign(this.config.data, window.checkoutConfig.SwedbankPay_Payments_Instrument_List);

            quote.totals.subscribe(function(totals) {
                if (self.totals.grand_total !== totals.grand_total) {
                    if (self.getCode() === self.isChecked() && Object.keys(self.instrumentElement).length !== 0) {
                        self.onPaymentInstrumentSelected(self.instrumentElement, null);
                    }
                }

                self.totals = totals;
            });
        },
        getAvailableInstruments: function () {
            let self = this;
            let instruments = self.config.data.active_instruments;

            return instruments;
        },
        onPaymentInstrumentSelected: function (element, event) {
            let self = this;
            console.log(element.pretty_name + ' Payment Instrument Selected');

            self.instrumentElement = element;
            self.startLoader();

            storage.get(
                self.config.data.OnInstrumentSelected + "?instrument=" + element.name,
                '',
                true
            ).done(function(response){
                self.stopLoader();

                if (response.hasOwnProperty('redirect_url')) {
                    console.log(response.redirect_url);
                    self.renderRedirectUrl(response.redirect_url);
                }
            }).fail(function(message){
                console.error(message);

                var response = JSON.parse(message.responseJSON.result);

                self.paymentErrors(response.problems);
                self.showError();
            });

            return true;
        },
        renderRedirectUrl: function(redirectUrl) {
            let self = this;

            self.redirectUrl(redirectUrl);
            self.paymentErrors([]);

            if (self.redirectUrl === '') {
                self.isRedirectUrlVisible(false);
            } else {
                self.isRedirectUrlVisible(true);
            }
        },
        onRedirectSelected: function(element, event) {
            let self = this;

            fullscreenLoader.startLoader();

            self.redirectAfterPlaceOrder = false;

            if(!self.placeOrder()) {
                fullscreenLoader.stopLoader();

                console.error('Error occurred while placing the order');
            } else {
                fullscreenLoader.stopLoader();
                customerData.invalidate(['cart']);
                window.location.href = self.redirectUrl();
            }
        },
        getPaymentErrors: function () {
            let self = this;

            return self.paymentErrors();
        },
        startLoader: function () {
            $('.payment-instrument-list .payment-instrument .view .content').hide();
            $('.payment-instrument-list .payment-instrument .view .spinner').show();
            $('.payment-instrument-list .payment-instrument .view .error').hide();
        },
        stopLoader: function () {
            $('.payment-instrument-list .payment-instrument .view .content').show();
            $('.payment-instrument-list .payment-instrument .view .spinner').hide();
            $('.payment-instrument-list .payment-instrument .view .error').hide();
        },
        showError: function () {
            $('.payment-instrument-list .payment-instrument .view .error').show();
            $('.payment-instrument-list .payment-instrument .view .spinner').hide();
            $('.payment-instrument-list .payment-instrument .view .content').hide();
        }
    })
});
