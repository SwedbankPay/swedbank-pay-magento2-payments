<?php

namespace SwedbankPay\Payments\Helper;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use SwedbankPay\Api\Client\Exception as ClientException;
use SwedbankPay\Api\Service\Data\ResponseInterface as ResponseServiceInterface;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\Item\TransactionListItem;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\TransactionListCollection;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Response\Data\TransactionInterface;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Response\Data\TransactionsInterface;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Response\Data\TransactionObjectInterface;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Response\Data\TransactionsObjectInterface;
use SwedbankPay\Api\Service\Paymentorder\Request\GetCurrentPayment;
use SwedbankPay\Api\Service\Paymentorder\Resource\Response\Data\GetCurrentPaymentInterface;
use SwedbankPay\Api\Service\Request;
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as ClientRequestService;
use SwedbankPay\Payments\Api\Data\OrderInterface;
use SwedbankPay\Payments\Api\Data\QuoteInterface;
use SwedbankPay\Payments\Helper\Factory\InstrumentFactory;
use SwedbankPay\Payments\Model\Instrument\Collector\InstrumentCollector;

class Service
{
    /**
     * @var ClientRequestService
     */
    protected $requestService;

    /**
     * @var InstrumentCollector
     */
    protected $instrumentCollector;

    /**
     * @var InstrumentFactory
     */
    protected $instrumentFactory;

    /**
     * @var PaymentData
     */
    protected $paymentDataHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Service constructor.
     * @param ClientRequestService $requestService
     * @param InstrumentCollector $instrumentCollector
     * @param InstrumentFactory $instrumentFactory
     * @param PaymentData $paymentDataHelper
     * @param Logger $logger
     */
    public function __construct(
        ClientRequestService $requestService,
        InstrumentCollector $instrumentCollector,
        InstrumentFactory $instrumentFactory,
        PaymentData $paymentDataHelper,
        Logger $logger
    ) {
        $this->requestService = $requestService;
        $this->instrumentCollector = $instrumentCollector;
        $this->instrumentFactory = $instrumentFactory;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->logger = $logger;
    }

    /**
     * @param $instrument
     * @return ResponseServiceInterface
     * @throws ClientException
     * @throws ServiceException
     */
    public function payment($instrument)
    {
        $paymentInstrument = $this->instrumentFactory->create($instrument);
        $paymentObject = $paymentInstrument->createPaymentObject();

        $request = $this->requestService->init(ucfirst($instrument), $paymentInstrument->getPaymentOperation());
        $request->setRequestResource($paymentObject);

        /** @var ResponseServiceInterface $responseService */
        $responseService = $request->send();

        $response = $responseService->getResponseData();

        $this->logger->debug(json_encode($response));

        return $responseService;
    }

    /**
     * @param $instrument
     * @param OrderInterface $swedbankPayOrder
     * @param Order $order
     * @return ResponseServiceInterface
     *
     * @throws ClientException
     * @throws ServiceException
     */
    public function capture($instrument, OrderInterface $swedbankPayOrder, Order $order)
    {
        $paymentInstrument = $this->instrumentFactory->create($instrument);
        $transactionObject = $paymentInstrument->createCaptureTransactionObject($swedbankPayOrder, $order);

        $captureRequest = $this->requestService->init($instrument . '/Transaction', 'CreateCapture');
        $captureRequest->setRequestEndpointVars($swedbankPayOrder->getPaymentId());
        $captureRequest->setRequestResource($transactionObject);

        /** @var ResponseServiceInterface $responseService */
        $responseService = $captureRequest->send();

        $response = $responseService->getResponseData();

        $this->logger->debug(json_encode($response));

        return $responseService;
    }

    /**
     * @param $instrument
     * @param OrderInterface $swedbankPayOrder
     * @return ResponseServiceInterface
     *
     * @throws ClientException
     * @throws ServiceException
     */
    public function refund($instrument, OrderInterface $swedbankPayOrder)
    {
        $paymentInstrument = $this->instrumentFactory->create($instrument);
        $transactionObject = $paymentInstrument->createRefundTransactionObject($swedbankPayOrder);

        $captureRequest = $this->requestService->init($instrument . '/Transaction', 'CreateReversal');
        $captureRequest->setRequestEndpointVars($swedbankPayOrder->getPaymentId());
        $captureRequest->setRequestResource($transactionObject);

        /** @var ResponseServiceInterface $responseService */
        $responseService = $captureRequest->send();

        $response = $responseService->getResponseData();

        $this->logger->debug(json_encode($response));

        return $responseService;
    }

    /**
     * @param $instrument
     * @param OrderInterface $swedbankPayOrder
     * @return ResponseServiceInterface
     *
     * @throws ClientException
     * @throws ServiceException
     */
    public function cancel($instrument, OrderInterface $swedbankPayOrder)
    {
        $paymentInstrument = $this->instrumentFactory->create($instrument);
        $transactionObject = $paymentInstrument->createCancelTransactionObject($swedbankPayOrder);

        $captureRequest = $this->requestService->init($instrument . '/Transaction', 'CreateCancellation');
        $captureRequest->setRequestEndpointVars($swedbankPayOrder->getPaymentId());
        $captureRequest->setRequestResource($transactionObject);

        /** @var ResponseServiceInterface $responseService */
        $responseService = $captureRequest->send();

        $response = $responseService->getResponseData();

        $this->logger->debug(json_encode($response));

        return $responseService;
    }

    /**
     * @param string $paymentUri
     * @return OrderInterface|QuoteInterface|bool
     * @throws ClientException
     * @throws ServiceException
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function getPaymentData($paymentUri)
    {
        /**
         * $paymentParams[0] Payment URI
         * $paymentParams[1] Payment Instrument
         * $paymentParams[2] Payment ID
         */
        $validUri = preg_match('|/psp/([^/]+)/payments/([^/]+)|', $paymentUri, $paymentParams);
        if (!$validUri) {
            return false;
        }

        list($paymentUri, $instrument, $paymentId) = $paymentParams;

        $instrument = $this->getInstrument($instrument);

        $paymentData = $this->paymentDataHelper->getByPaymentId($paymentId);

        /** @var GetCurrentPayment $serviceRequest */
        $serviceRequest = $this->requestService->init(ucfirst($instrument), 'GetPayment');
        $serviceRequest->setRequestEndpoint($paymentUri);

        /** @var GetCurrentPaymentInterface $currentPayment */
        $currentPaymentResponse = $serviceRequest->send();
        $currentPayment = $currentPaymentResponse->getResponseResource();

        $this->logger->debug(
            sprintf(
                'Current Payment Response: \'%s\'',
                json_encode($currentPaymentResponse->getResponseData())
            )
        );

        $paymentData->setIntent($currentPayment->getPayment()->getIntent());
        $paymentData->setState($currentPayment->getPayment()->getState());
        $paymentData->setAmount($currentPayment->getPayment()->getAmount());
        $this->paymentDataHelper->update($paymentData);

        return $paymentData;
    }

    /**
     * @param string $transactionUri
     * @return TransactionInterface|false
     * @throws ClientException
     * @throws ServiceException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function getTransactionData($transactionUri)
    {
        /**
         * $transactionParams[0] Transaction URI
         * $transactionParams[1] Payment Instrument
         * $transactionParams[2] Payment Resource ID
         * $transactionParams[3] Transaction Type
         * $transactionParams[4] Transaction ID
         */
        $validUri = preg_match('|/psp/([^/]+)/payments/([^/]+)/([^/]+)/([^/]+)|', $transactionUri, $transactionParams);
        if (!$validUri) {
            return false;
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        list($transactionUri, $instrument, $resourceId, $transactionType, $transactionId) = $transactionParams;

        $instrument = $this->getInstrument($instrument);

        /** @var Request $serviceRequest */
        $serviceRequest = $this->requestService->init(ucfirst($instrument) . '/Transaction', 'GetTransaction');
        $serviceRequest->setRequestEndpoint($transactionUri);

        /** @var TransactionObjectInterface $transactionObject */
        $transactionResponse = $serviceRequest->send();
        $transactionObject = $transactionResponse->getResponseResource();

        $this->logger->debug(
            sprintf(
                'Current Transaction Response: \'%s\'',
                json_encode($transactionResponse->getResponseData())
            )
        );

        return $transactionObject->getTransaction();
    }

    /**
     * @param string $transactionUri
     * @return TransactionInterface|false
     * @throws ClientException
     * @throws ServiceException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function getLastTransactionData($transactionUri)
    {
        /**
         * $transactionParams[0] Transaction URI
         * $transactionParams[1] Payment Instrument
         * $transactionParams[2] Payment Resource ID
         * $transactionParams[3] Transaction Type
         */
        $validUri = preg_match('|/psp/([^/]+)/payments/([^/]+)/([^/]+)|', $transactionUri, $transactionParams);
        if (!$validUri) {
            return false;
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        list($transactionUri, $instrument, $resourceId, $transactionType) = $transactionParams;

        $instrument = $this->getInstrument($instrument);

        /** @var Request $serviceRequest */
        $serviceRequest = $this->requestService->init(ucfirst($instrument) . '/Transaction', 'GetTransactions');
        $serviceRequest->setRequestEndpoint($transactionUri);

        /** @var TransactionsObjectInterface $transactionsObject */
        $transactionResponse = $serviceRequest->send();

        $transactionsObject = $transactionResponse->getResponseResource();

        /** @var TransactionsInterface $transactions */
        $transactions = $transactionsObject->getTransactions();

        /** @var TransactionListCollection $transactionList */
        $transactionList = $transactions->getTransactionList();

        /** @var TransactionListItem $lastTransaction */
        $lastTransaction = null;

        /** @var TransactionListItem $transaction */
        foreach ($transactionList->getItems() as $transaction) {
            if (!$lastTransaction) {
                $lastTransaction = $transaction;
            } elseif (strtotime($lastTransaction->getCreated()) < strtotime($transaction->getCreated())) {
                $lastTransaction = $transaction;
            }
        }

        return $lastTransaction;
    }

    /**
     * @param string $instrumentName
     * @return string
     */
    protected function getInstrument($instrumentName)
    {
        $instrumentList = $this->instrumentCollector->getInstruments();
        $instrumentName = strtolower($instrumentName);

        foreach ($instrumentList as $instrument) {
            if (strpos($instrumentName, $instrument->getInstrumentName()) !== false) {
                $instrumentName = $instrument->getInstrumentName();
                break;
            }
        }

        return $instrumentName;
    }
}
