<?php

namespace SwedbankPay\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Helper\Config;

class AfterOrderSuccessObserver implements ObserverInterface
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * AfterOrderPlaceObserver constructor.
     * @param OrderRepository $orderRepository
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        OrderRepository $orderRepository,
        Config $config,
        Logger $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isActive()) {
            return;
        }

        $this->logger->debug('AfterOrderSuccessObserver is called!');

        $orderIds = $observer->getEvent()->getOrderIds();
        $orderId = $orderIds[0];

        /** @var OrderInterface $order */
        $order = $this->orderRepository->get($orderId);

        /** @var OrderPaymentInterface $payment */
        $payment = $order->getPayment();

        if ($payment->getMethod() != $this->config->getPaymentMethodCode()) {
            return;
        }

        if ($order->getState() != Order::STATE_PROCESSING) {
            $order->setState(Order::STATE_PAYMENT_REVIEW);
            $order->setStatus(Order::STATE_PAYMENT_REVIEW);
        }

        $this->logger->debug(
            sprintf(
                'Order ID %s is updated to state \'%s\' & status \'%s\'',
                $order->getEntityId(),
                $order->getState(),
                $order->getStatus()
            )
        );

        $this->orderRepository->save($order);
    }
}
