<?php

namespace SwedbankPay\Payments\Gateway\Command;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\QuoteRepository as MageQuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;
use Magento\Store\Model\Store;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as ClientRequestService;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as PaymentOrderRepository;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface as PaymentQuoteRepository;
use SwedbankPay\Payments\Helper\Config as PaymentsConfig;
use SwedbankPay\Payments\Helper\PaymentData;

/**
 * Class Initialize
 *
 * @package SwedbankPay\Checkout\Gateway\Command
 */
class Initialize extends AbstractCommand
{
    const TYPE_AUTH = 'authorization';

    /**
     * @var PaymentsConfig
     */
    protected $paymentsConfig;

    /**
     * @var PaymentData
     */
    protected $paymentData;

    /**
     * Initialize constructor.
     * @param PaymentOrderRepository $paymentOrderRepo
     * @param PaymentQuoteRepository $paymentQuoteRepo
     * @param ClientRequestService $requestService
     * @param MageQuoteRepository $mageQuoteRepo
     * @param MageOrderRepository $mageOrderRepo
     * @param PaymentsConfig $paymentsConfig
     * @param PaymentData $paymentData
     * @param Logger $logger
     * @param array $data
     */
    public function __construct(
        PaymentOrderRepository $paymentOrderRepo,
        PaymentQuoteRepository $paymentQuoteRepo,
        ClientRequestService $requestService,
        MageQuoteRepository $mageQuoteRepo,
        MageOrderRepository $mageOrderRepo,
        PaymentsConfig $paymentsConfig,
        PaymentData $paymentData,
        Logger $logger,
        array $data = []
    ) {
        parent::__construct(
            $paymentOrderRepo,
            $paymentQuoteRepo,
            $requestService,
            $mageQuoteRepo,
            $mageOrderRepo,
            $logger,
            $data
        );

        $this->paymentsConfig = $paymentsConfig;
        $this->paymentData = $paymentData;
    }

    /**
     * Initialize command
     *
     * @param array $commandSubject
     *
     * @return null|Command\ResultInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException
     */
    public function execute(array $commandSubject)
    {
        /** @var InfoInterface|object $payment */
        $payment = $commandSubject['payment']->getPayment();

        /** @var DataObject|object $stateObject */
        $stateObject = $commandSubject['stateObject'];

        /** @var Order $order */
        $order = $payment->getOrder();

        $this->logger->debug(
            sprintf('Initialize command is called for Order increment ID %s', $order->getIncrementId())
        );

        $paymentQuote = $this->paymentData->getByOrder($order);

        /** @var Store $store */
        $store = $order->getStore();

        $state = Order::STATE_PROCESSING;
        $status = $this->paymentsConfig->getProcessedOrderStatus($store);

        if (0 >= $order->getGrandTotal()) {
            $state = Order::STATE_NEW;
            $status = $stateObject->getStatus();
        }

        $stateObject->setState($state);
        $stateObject->setStatus($status);

        $stateObject->setIsNotified(false);

        $transactionId = $paymentQuote->getPaymentId();
        $payment->setBaseAmountAuthorized($order->getBaseTotalDue());
        $payment->setAmountAuthorized($order->getTotalDue());
        $payment->setTransactionId($transactionId)->setIsTransactionClosed(0);
        $payment->addTransaction(self::TYPE_AUTH);

        return null;
    }
}
