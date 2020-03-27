/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* @api */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    if (window.checkoutConfig.SwedbankPay_Payments.viewType === 'hosted_view') {
        rendererList.push(
            {
                type: 'swedbank_pay_payments',
                component: 'SwedbankPay_Payments/js/view/payment/method-renderer/hosted-view-payment'
            }
        );
    } else {
        rendererList.push(
            {
                type: 'swedbank_pay_payments',
                component: 'SwedbankPay_Payments/js/view/payment/method-renderer/redirect-view-payment'
            }
        );
    }

    /** Add view logic here if needed */
    return Component.extend({});
});
