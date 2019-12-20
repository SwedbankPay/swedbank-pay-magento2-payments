<?php

namespace SwedbankPay\Payments\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;

class AdditionalConfigVars implements ConfigProviderInterface
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Resolver
     */
    protected $locale;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * AdditionalConfigVars constructor.
     * @param Resolver $locale
     * @param UrlInterface $urlBuilder
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Resolver $locale,
        UrlInterface $urlBuilder,
        ConfigHelper $configHelper
    ) {
        $this->locale = $locale;
        $this->urlBuilder = $urlBuilder;
        $this->configHelper = $configHelper;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'SwedbankPay_Payments' => [
                'isEnabled' => $this->configHelper->isActive(),
                'culture' => str_replace('_', '-', $this->locale->getLocale()),
                'OnInstrumentSelected' => $this->urlBuilder->getUrl('SwedbankPayPayments/Index/OnInstrumentSelected'),
                'onUpdated' => $this->urlBuilder->getUrl('SwedbankPayPayments/Index/OnUpdated')
            ]
        ];
    }
}
