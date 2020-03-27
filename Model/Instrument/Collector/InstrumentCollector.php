<?php

namespace SwedbankPay\Payments\Model\Instrument\Collector;

use Magento\Store\Model\StoreManagerInterface;
use SwedbankPay\Payments\Helper\Config;
use SwedbankPay\Payments\Model\Instrument\Data\InstrumentInterface;
use SwedbankPay\Payments\Model\Ui\ConfigProvider;

class InstrumentCollector
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var InstrumentInterface[]
     */
    protected $instruments;

    /**
     * @var Config
     */
    protected $config;

    /**
     * InstrumentCollectionProcessor constructor.
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param InstrumentInterface[] $instruments
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Config $config,
        array $instruments = []
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->instruments = $instruments;
    }

    /**
     * @return InstrumentInterface[]
     */
    public function getInstruments()
    {
        return $this->instruments;
    }

    /**
     * @return array
     */
    public function collectInstruments()
    {
        return array_values(array_map(function (InstrumentInterface $instrument) {
            return [
                'name' => $instrument->getInstrumentName(),
                'pretty_name' => $instrument->getInstrumentPrettyName(),
                'js_object_name' => $instrument->getJsObjectName()
            ];
        }, $this->getInstruments()));
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function collectActiveInstruments()
    {
        $store = $this->storeManager->getStore();

        $availableInstruments = explode(
            ',',
            $this->config->getPaymentValue('available_instruments', ConfigProvider::CODE, $store)
        );

        $instruments = $this->collectInstruments();

        return array_values(array_filter($instruments, function ($instrument) use ($availableInstruments) {
            if (in_array($instrument['name'], $availableInstruments)) {
                return true;
            }
            return false;
        }));
    }
}
