<?php

namespace SwedbankPay\Payments\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository as MageQuoteRepository;
use Magento\Sales\Model\Order as MageOrder;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;
use SwedbankPay\Core\Helper\Order as OrderHelper;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as ClientRequestService;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as PaymentOrderRepository;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface as PaymentQuoteRepository;
use SwedbankPay\Payments\Helper\PaymentData;
use SwedbankPay\Payments\Helper\Service as ServiceHelper;

/**
 * Class Capture
 *
 * @package SwedbankPay\Payments\Gateway\Command
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Capture extends AbstractCommand
{
    /**
     * @var PaymentData
     */
    protected $paymentData;

    /**
     * @var ServiceHelper
     */
    protected $serviceHelper;

    /**
     * Capture constructor.
     *
     * @param PaymentOrderRepository $paymentOrderRepo
     * @param PaymentQuoteRepository $paymentQuoteRepo
     * @param ClientRequestService $requestService
     * @param MageQuoteRepository $mageQuoteRepo
     * @param MageOrderRepository $mageOrderRepo
     * @param PaymentData $paymentData
     * @param ServiceHelper $serviceHelper
     * @param Logger $logger
     * @param array $data
     */
    public function __construct(
        PaymentOrderRepository $paymentOrderRepo,
        PaymentQuoteRepository $paymentQuoteRepo,
        ClientRequestService $requestService,
        MageQuoteRepository $mageQuoteRepo,
        MageOrderRepository $mageOrderRepo,
        PaymentData $paymentData,
        ServiceHelper $serviceHelper,
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

        $this->paymentData = $paymentData;
        $this->serviceHelper = $serviceHelper;
    }

    /**
     * Capture command
     *
     * @param array $commandSubject
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws \SwedbankPay\Core\Exception\ServiceException
     * @throws \PayEx\Api\Client\Exception
     * @throws LocalizedException
     */
    public function execute(array $commandSubject)
    {
        /** @var \Magento\Payment\Model\InfoInterface|object $payment */
        $payment = $commandSubject['payment']->getPayment();
        $amount = $commandSubject['amount'] + 0;

        /** @var MageOrder $order */
        $order = $payment->getOrder();

        $this->logger->debug(
            sprintf('Capture command is called for Order ID %s', $order->getEntityId())
        );

        $swedbankPayOrder = $this->paymentOrderRepo->getByOrderId($order->getEntityId());

        $this->checkRemainingAmount('capture', $amount, $order, $swedbankPayOrder);

        if ($swedbankPayOrder->getIntent() == 'Sale') {
            /* Intent 'Sale' means 1-phase payment, no capture necessary */
            $this->paymentData->updateRemainingAmounts('capture', $amount, $swedbankPayOrder);
            return null;
        }

        $captureResponse = $this->serviceHelper->capture($swedbankPayOrder->getInstrument(), $swedbankPayOrder, $order);

        $this->checkResponseResource('capture', $captureResponse->getResponseResource(), $order, $swedbankPayOrder);

        /** @var array $captureResponseData */
        $captureResponseData = $captureResponse->getResponseData();

        $transactionResult = $this->getTransactionResult('capture', $captureResponseData, $order, $swedbankPayOrder);

        if ($transactionResult != 'complete') {
            $order->setStatus(OrderHelper::STATUS_PENDING);
            $this->mageOrderRepo->save($order);

            return null;
        }

        $this->paymentData->updateRemainingAmounts('capture', $amount, $swedbankPayOrder);
    }
}
