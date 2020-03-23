<?php

namespace SwedbankPay\Payments\Model\Ui;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const CODE = 'swedbank_pay_payments';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                ]
            ]
        ];
    }
}
