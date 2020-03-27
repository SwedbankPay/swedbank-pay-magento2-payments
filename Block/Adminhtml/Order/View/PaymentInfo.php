<?php

namespace SwedbankPay\Payments\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use SwedbankPay\Core\Model\Service;
use SwedbankPay\Payments\Api\Data\OrderInterface;
use SwedbankPay\Payments\Api\OrderRepositoryInterface;

class PaymentInfo extends Template
{
    /**
     * @var Service
     */
    protected $service;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * PaymentInfo constructor.
     * @param Context $context
     * @param array $data
     * @param Service $service
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        /** @noinspection PhpOptionalBeforeRequiredParametersInspection */ array $data = [],
        /** phpcs:disable */Service $service,
        OrderRepositoryInterface $orderRepository /** phpcs:enable */
    ) {
        parent::__construct($context, $data);
        $this->service = $service;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCurrentPaymentInstrument()
    {
        $swedbankPayOrder = $this->getSwedbankPayOrder();

        return $swedbankPayOrder->getInstrument();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCurrentPaymentId()
    {
        $swedbankPayOrder = $this->getSwedbankPayOrder();

        return $swedbankPayOrder->getPaymentId();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCurrentPaymentIdPath()
    {
        $swedbankPayOrder = $this->getSwedbankPayOrder();

        return $swedbankPayOrder->getPaymentIdPath();
    }

    /**
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    protected function getSwedbankPayOrder()
    {
        $orderId = $this->getRequest()->getParam('order_id');

        return $this->orderRepository->getByOrderId($orderId);
    }
}
