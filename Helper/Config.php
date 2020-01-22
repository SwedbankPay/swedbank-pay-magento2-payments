<?php

namespace SwedbankPay\Payments\Helper;

use SwedbankPay\Payments\Model\Ui\ConfigProvider;
use SwedbankPay\Core\Helper\Config as CoreConfig;

class Config extends CoreConfig
{
    const XML_CONFIG_GROUP = 'payments';

    protected $moduleDependencies = [
        'SwedbankPay_Core'
    ];

    /**
     * Get the order status that should be set on orders that have been processed by SwedbankPay
     *
     * @param int|string|null  $store
     *
     * @return string
     */
    public function getProcessedOrderStatus($store = null)
    {
        return $this->getPaymentValue('order_status', $this->getPaymentMethodCode(), $store);
    }

    /**
     * Get the view type ex: 'hosted_view' or 'redirect_view'
     *
     * @param int|string|null $store
     * @return string
     */
    public function getViewType($store = null)
    {
        return $this->getPaymentValue('view_type', $this->getPaymentMethodCode(), $store);
    }

    /**
     * Get the payment method code
     *
     * @return string
     */
    public function getPaymentMethodCode()
    {
        return ConfigProvider::CODE;
    }
}
