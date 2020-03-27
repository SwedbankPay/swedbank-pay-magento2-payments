<?php

namespace SwedbankPay\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use SwedbankPay\Core\Helper\Order as OrderHelper;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Helper\Config;

class AfterOrderPlaceObserver implements ObserverInterface
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
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isActive()) {
            return;
        }

        $this->logger->debug('AfterOrderPlaceObserver is called!');

        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');

        /** @var OrderPaymentInterface $payment */
        $payment = $order->getPayment();

        if ($payment->getMethod() != $this->config->getPaymentMethodCode()) {
            return;
        }

        // Prevents sending emails to customer while placing order
        $order->setCanSendNewEmailFlag(false);

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(OrderHelper::STATUS_PENDING);

        $this->logger->debug(
            sprintf(
                'Order Increment ID %s is initialized with state \'%s\' & status \'%s\'',
                $order->getIncrementId(),
                $order->getState(),
                $order->getStatus()
            )
        );

        $this->orderRepository->save($order);
    }
}
