<?php

namespace SwedbankPay\Payments\Observer;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Helper\Config;

class AfterCurrencyChangedObserver implements ObserverInterface
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * AfterCurrencyChangedObserver constructor.
     * @param ManagerInterface $messageManager
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $configWriter
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        WriterInterface $configWriter,
        Config $config,
        Logger $logger
    ) {
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->configWriter = $configWriter;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isActive()) {
            return;
        }

        $this->logger->debug('AfterCurrencyChangedObserver is called!');

        $configPath = $this->config->getPaymentConfigPath('available_instruments');

        $storeId = $observer->getEvent()->getData('store');
        $websiteId = $observer->getEvent()->getData('website');
        $notice = null;

        if ($storeId) {
            /** @var Store $store */
            $store = $this->storeManager->getStore($storeId);

            $this->configWriter->save($configPath, null, ScopeInterface::SCOPE_STORES, $store->getId());

            $notice = sprintf(
                'Available Instruments for %s have been reset in SwedbankPay_Payments module',
                $store->getName()
            );
        }

        if ($websiteId) {
            /** @var Website $website */
            $website = $this->storeManager->getWebsite($websiteId);

            $stores = $website->getStores();

            foreach ($stores as $store) {
                $this->configWriter->save($configPath, null, ScopeInterface::SCOPE_STORES, $store->getId());
            }

            $notice = sprintf(
                'Available Instruments for %s have been reset in SwedbankPay_Payments module',
                $website->getName()
            );
        }

        if (!$storeId && !$websiteId) {
            $stores = $this->storeManager->getStores();

            foreach ($stores as $store) {
                $this->configWriter->save($configPath, null, ScopeInterface::SCOPE_STORES, $store->getId());
            }

            $notice = sprintf(
                'Available Instruments for all store views have been reset in SwedbankPay_Payments module'
            );
        }

        if ($notice) {
            $this->messageManager->addNoticeMessage($notice);

            $this->logger->debug($notice);
        }
    }
}
