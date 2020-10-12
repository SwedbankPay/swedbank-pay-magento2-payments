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
    'Magento_Ui/js/model/messageList',
    'uiLayout',
    'Magento_Checkout/js/action/redirect-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Customer/js/customer-data',
    'mage/url',
    'mage/cookies',
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
    messageContainer,
    layout,
    redirectOnSuccessAction,
    fullscreenLoader,
    customerData,
    urlBuilder,
    cookies
) {
    'use strict';

    const hostedUrl = ko.observable('');
    var paymentErrors = ko.observable([]);

    return Component.extend({
        defaults: {
            template: 'SwedbankPay_Payments/payment/hosted-view'
        },
        config: {
            data: {
                culture: 'en-US',
                logo: 'SwedbankPay_Payments/images/swedbank-pay-logo.svg'
            }
        },
        hostedUrl: hostedUrl,
        paymentErrors: paymentErrors,
        logoUrl: function () {
            return require.toUrl(this.config.data.logo);
        },
        initialize: function () {
            let self = this;
            self.totals = {};
            self.instrumentScript = '';
            self.instrumentElement = {};

            self._super();
            Object.assign(this.config.data, window.checkoutConfig.SwedbankPay_Payments);
            Object.assign(this.config.data, window.checkoutConfig.SwedbankPay_Payments_Instrument_List);


            quote.totals.subscribe(function(totals) {
                if (self.totals.grand_total !== totals.grand_total) {
                    if (self.getCode() === self.isChecked()) {
                        self.onPaymentInstrumentSelected(self.instrumentElement, null);
                    }
                }

                self.totals = totals;
            });
        },
        clearPaymentScript: function() {
            let self = this;

            self.getAvailableInstruments().forEach(function (instrument) {
                if (typeof payex !== "undefined" && typeof payex.hostedView[instrument.js_object_name] !== "undefined") {
                    payex.hostedView[instrument.js_object_name]().close();
                }
            });

            $('#paymentMenuScript').remove();
            $('.swedbank-pay-content').empty();
        },
        renderPaymentScript: function(scriptSrc) {
            let self = this;
            let script = document.createElement('script');

            script.type = "text/javascript";
            script.id = "paymentMenuScript";
            self.paymentErrors([]);

            $('.checkout-index-index').append(script);

            script.onload = function(){
                if(self.instrumentScript === scriptSrc) {
                    self.swedbankPaySetupHostedView();
                }
            };

            script.src = scriptSrc;
        },
        swedbankPaySetupHostedView: function() {
            let self = this;

            self.getAvailableInstruments().forEach(function (instrument) {
                if (payex.hostedView.hasOwnProperty(instrument.js_object_name)) {
                    payex.hostedView[instrument.js_object_name]({
                        container: 'swedbank-pay-content-' + instrument.name,
                        onPaymentCompleted: self.onPaymentCompleted.bind(self),
                        onPaymentFailed: self.onPaymentFailed.bind(self),
                        externalRedirect: self.externalRedirect.bind(self)
                    }).open();
                }
            });
        },
        // updatePaymentScript: function(){
        //     let self = this;
        //
        //     fullscreenLoader.startLoader();
        //
        //     storage.get(
        //         self.config.data.onUpdated,
        //         "",
        //         true
        //     ).done(function(response){
        //         if(self.instrumentScript != response.result) {
        //             self.clearPaymentScript();
        //             self.renderPaymentScript(response.result);
        //
        //             self.instrumentScript = response.result;
        //             fullscreenLoader.stopLoader();
        //         }
        //     }).fail(function(message){
        //         console.error(message);
        //         fullscreenLoader.stopLoader();
        //     });
        // },
        getAvailableInstruments: function() {
            let self = this;

            return self.config.data.active_instruments;
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
            ).done(function(response) {
                self.stopLoader();

                if (response.hasOwnProperty('hosted_url')) {
                    console.log(response.hosted_url);
                    self.clearPaymentScript();

                    self.instrumentScript = response.hosted_url;
                    self.renderPaymentScript(response.hosted_url);
                }
            }).fail(function(message) {
                console.error(message);

                var response = JSON.parse(message.responseJSON.result);

                self.paymentErrors(response.problems);
                self.showError();
            });

            return true;
        },
        onPaymentCompleted: function(event) {
            let self = this;
            fullscreenLoader.startLoader();

            if (!self.placeOrder()) {
                fullscreenLoader.stopLoader();
                console.error('Error occurred while placing the order');
            }
        },
        onPaymentFailed: function(event) {
            let self = this;

            console.log('Payment failed');
            window.location.href = urlBuilder.build('SwedbankPayPayments/Index/Failed');
        },
        externalRedirect: function(event) {
            let self = this;
            fullscreenLoader.startLoader();

            if (!self.placeOrder()) {
                fullscreenLoader.stopLoader();
                console.error('Error occurred while placing the order');
            } else {
                fullscreenLoader.stopLoader();
                customerData.invalidate(['cart']);
                window.location.href = event.url;
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
