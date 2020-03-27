<?php

namespace SwedbankPay\Payments\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use SwedbankPay\Payments\Helper\Factory\InstrumentFactory;

class AvailableInstrumentsValidator extends Value
{
    /**
     * @var InstrumentFactory
     */
    protected $instrumentFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * AvailableInstruments constructor.
     * @param InstrumentFactory $instrumentFactory
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        InstrumentFactory $instrumentFactory,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->instrumentFactory = $instrumentFactory;
        $this->storeManager = $storeManager;
        $this->request = $request;

        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return Value|void
     * @throws NoSuchEntityException
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        /** @var string[] $values */
        $values = $this->getValue();

        $currency = $this->getCurrency();

        $errors = [];

        foreach ($values as $value) {
            $instrument = $this->instrumentFactory->create($value);

            if (!$instrument->isCurrencySupported($currency)) {
                $errors[] = sprintf(
                    '%s does not support currency: %s',
                    $instrument->getInstrumentPrettyName(),
                    $currency
                );
            }
        }

        if (count($errors) > 0) {
            throw new ValidatorException(new Phrase(implode("\n", $errors)));
        }

        parent::beforeSave();
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    protected function getCurrency()
    {
        $storeId = $this->request->getParam('store');

        if ($storeId) {
            /** @var Store $store */
            $store = $this->storeManager->getStore($storeId);
            $this->storeManager->setCurrentStore($store->getCode());

            return $store->getCurrentCurrencyCode();
        }

        return null;
    }
}
