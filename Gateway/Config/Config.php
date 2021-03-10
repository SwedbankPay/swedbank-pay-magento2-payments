<?php

namespace SwedbankPay\Payments\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    /** @var ConfigHelper */
    protected $configHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ConfigHelper $configHelper,
        $methodCode = null,
        $pathPattern = parent::DEFAULT_PATH_PATTERN
    ) {
        $this->configHelper = $configHelper;
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    /**
     * Gets Payment configuration status.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool)$this->configHelper->isActive($storeId);
    }
}
