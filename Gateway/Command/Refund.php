<?php

namespace SwedbankPay\Payments\Gateway\Command;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\QuoteRepository as MageQuoteRepository;
use Magento\Sales\Model\Order as MageOrder;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;
use SwedbankPay\Api\Client\Exception;
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Exception\SwedbankPayException;
use SwedbankPay\Core\Helper\Order as OrderHelper;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as ClientRequestService;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as PaymentOrderRepository;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface as PaymentQuoteRepository;
use SwedbankPay\Payments\Helper\PaymentData;
use SwedbankPay\Payments\Helper\Service as ServiceHelper;
use SwedbankPay\Payments\Helper\ServiceFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Refund extends AbstractCommand
{
    /**
     * @var PaymentData
     */
    protected $paymentData;

    /**
     * @var ServiceFactory
     */
    protected $serviceFactory;

    /**
     * Refund constructor.
     *
     * @param PaymentOrderRepository $paymentOrderRepo
     * @param PaymentQuoteRepository $paymentQuoteRepo
     * @param ClientRequestService $requestService
     * @param MageQuoteRepository $mageQuoteRepo
     * @param MageOrderRepository $mageOrderRepo
     * @param ServiceFactory $serviceFactory
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
        ServiceFactory $serviceFactory,
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

        $this->paymentData = $paymentData;
        $this->serviceFactory = $serviceFactory;
    }

    /**
     * Refund command
     *
     * @param array $commandSubject
     *
     * @return Command\ResultInterface|null
     *
     * @throws AlreadyExistsException
     * @throws Exception
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws ServiceException
     * @throws SwedbankPayException
     */
    public function execute(array $commandSubject)
    {
        /** @var InfoInterface|object $payment */
        $payment = $commandSubject['payment']->getPayment();
        $amount = $commandSubject['amount'] + 0;

        /** @var MageOrder $order */
        $order = $payment->getOrder();

        $this->logger->debug(
            sprintf('Refund command is called for Order ID %s', $order->getEntityId())
        );

        $swedbankPayOrder = $this->paymentOrderRepo->getByOrderId($order->getEntityId());

        $this->checkRemainingAmount('refund', $amount, $order, $swedbankPayOrder);

        /** @var ServiceHelper $serviceHelper */
        $serviceHelper = $this->serviceFactory->create();
        $reversalResponse = $serviceHelper->refund($swedbankPayOrder->getInstrument(), $swedbankPayOrder);

        $this->checkResponseResource('refund', $reversalResponse->getResponseResource(), $order, $swedbankPayOrder);

        $reversalResponseData = $reversalResponse->getResponseData();

        $transactionResult = $this->getTransactionResult('refund', $reversalResponseData, $order, $swedbankPayOrder);

        if ($transactionResult != 'complete') {
            $order->setStatus(OrderHelper::STATUS_PENDING);
            $this->mageOrderRepo->save($order);

            return null;
        }

        $this->paymentData->updateRemainingAmounts('refund', $amount, $swedbankPayOrder);

        $order->setStatus(OrderHelper::STATUS_REVERSED);
        $this->mageOrderRepo->save($order);

        return null;
    }
}
