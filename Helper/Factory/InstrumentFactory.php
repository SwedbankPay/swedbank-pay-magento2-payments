<?php

namespace SwedbankPay\Payments\Helper\Factory;

use Magento\Framework\ObjectManagerInterface;
use SwedbankPay\Payments\Model\Instrument\Data\InstrumentInterface;

class InstrumentFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * InstrumentFactory constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param string $instrument
     * @return InstrumentInterface
     */
    public function create($instrument)
    {
        $className = 'SwedbankPay\\Payments\\Model\\Instrument\\' . ucfirst($instrument);

        return $this->objectManager->create($className);
    }
}
