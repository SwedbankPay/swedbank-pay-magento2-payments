<?php

namespace SwedbankPay\Payments\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;

class AdditionalConfigVars implements ConfigProviderInterface
{
    /**
     * @var Resolver
     */
    protected $locale;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * AdditionalConfigVars constructor.
     * @param Resolver $locale
     * @param UrlInterface $urlBuilder
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Resolver $locale,
        UrlInterface $urlBuilder,
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->locale = $locale;
        $this->urlBuilder = $urlBuilder;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfig()
    {
        $store = $this->storeManager->getStore();

        return [
            'SwedbankPay_Payments' => [
                'isEnabled' => $this->configHelper->isActive(),
                'viewType' => $this->configHelper->getViewType($store),
                'culture' => str_replace('_', '-', $this->locale->getLocale()),
                'OnInstrumentSelected' => $this->urlBuilder->getUrl('SwedbankPayPayments/Index/OnInstrumentSelected'),
                'onUpdated' => $this->urlBuilder->getUrl('SwedbankPayPayments/Index/OnUpdated')
            ]
        ];
    }
}
