<?php

namespace SwedbankPay\Payments\Helper\Factory;

use Magento\Framework\ObjectManagerInterface;

class SubresourceFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * SubresourceFactory constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param string $instrument
     * @param $subresource
     * @return mixed
     */
    public function create($instrument, $subresource)
    {
        $className =
            'SwedbankPay\\Api\\Service\\' .
            ucfirst($instrument) .
            '\\Resource\\Request\\' .
            ucfirst($subresource);

        return $this->objectManager->create($className);
    }
}
