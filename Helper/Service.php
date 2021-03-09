<?php

namespace SwedbankPay\Payments\Helper;

use Magento\Sales\Model\Order;
use SwedbankPay\Api\Client\Exception as ClientException;
use SwedbankPay\Api\Service\Data\ResponseInterface as ResponseServiceInterface;
use SwedbankPay\Api\Service\Payment\Resource\Response\Data\PaymentObjectInterface;
use SwedbankPay\Api\Service\Payment\Resource\Response\Data\PaymentResponseInterface;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Response\Data\TransactionInterface;
use SwedbankPay\Api\Service\Paymentorder\Request\GetCurrentPayment;
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as ClientRequestService;
use SwedbankPay\Payments\Api\Data\OrderInterface;
use SwedbankPay\Payments\Helper\Factory\InstrumentFactory;
use SwedbankPay\Payments\Model\Instrument\Collector\InstrumentCollector;

class Service
{
    /**
     * @var PaymentObjectInterface|null
     */
    private $paymentResponseResource = null;

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
     * @var Logger
     */
    protected $logger;

    /**
     * Service constructor.
     * @param ClientRequestService $requestService
     * @param InstrumentCollector $instrumentCollector
     * @param InstrumentFactory $instrumentFactory
     * @param Logger $logger
     */
    public function __construct(
        ClientRequestService $requestService,
        InstrumentCollector $instrumentCollector,
        InstrumentFactory $instrumentFactory,
        Logger $logger
    ) {
        $this->requestService = $requestService;
        $this->instrumentCollector = $instrumentCollector;
        $this->instrumentFactory = $instrumentFactory;
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

        $request = $this->requestService->init($instrument . '/Transaction', 'CreateCapture');
        $request->setPaymentId($swedbankPayOrder->getPaymentIdPath());
        $request->setRequestResource($transactionObject);

        /** @var ResponseServiceInterface $responseService */
        $responseService = $request->send();

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

        $request = $this->requestService->init($instrument . '/Transaction', 'CreateReversal');
        $request->setPaymentId($swedbankPayOrder->getPaymentIdPath());
        $request->setRequestResource($transactionObject);

        /** @var ResponseServiceInterface $responseService */
        $responseService = $request->send();

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

        $request = $this->requestService->init($instrument . '/Transaction', 'CreateCancellation');
        $request->setPaymentId($swedbankPayOrder->getPaymentIdPath());
        $request->setRequestResource($transactionObject);

        /** @var ResponseServiceInterface $responseService */
        $responseService = $request->send();

        $response = $responseService->getResponseData();

        $this->logger->debug(json_encode($response));

        return $responseService;
    }

    /**
     * @param string $instrument
     * @param string $paymentUri
     * @param array $expands
     * @return $this
     * @throws ClientException
     * @throws ServiceException
     */
    public function currentPayment($instrument, $paymentUri, $expands = [])
    {
        $instrument = $this->getInstrument($instrument);

        /** @var GetCurrentPayment $serviceRequest */
        $serviceRequest = $this->requestService->init(ucfirst($instrument), 'GetPayment');
        $serviceRequest->setPaymentId($paymentUri);

        if ($expands) {
            $serviceRequest->setExpands(['transactions']);
        }

        /** @var ResponseServiceInterface $responseService */
        $currentPaymentResponse = $serviceRequest->send();

        /** @var PaymentObjectInterface $currentPayment */
        $currentPayment = $currentPaymentResponse->getResponseResource();

        $this->logger->debug(
            sprintf(
                'Current Payment Response: \'%s\'',
                json_encode($currentPaymentResponse->getResponseData())
            )
        );

        $this->setPaymentResponseResource($currentPayment);

        return $this;
    }

    /**
     * @param string $transactionNumber
     * @return TransactionInterface|null
     */
    public function getTransaction($transactionNumber)
    {
        if (!$this->getPaymentResponseResource()) {
            return null;
        }

        /** @var PaymentResponseInterface $paymentResponse */
        $paymentResponse = $this->getPaymentResponseResource()->getPayment();

        /** @var TransactionInterface[] $transactions */
        $transactions = $paymentResponse->getTransactions()->getTransactionList()->getItems();

        foreach ($transactions as $transaction) {
            if ($transaction->getNumber() == $transactionNumber) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * @return TransactionInterface|null
     */
    public function getLastTransaction()
    {
        if (!$this->getPaymentResponseResource()) {
            return null;
        }

        /** @var PaymentResponseInterface $paymentResponse */
        $paymentResponse = $this->getPaymentResponseResource()->getPayment();

        /** @var TransactionInterface[] $transactions */
        $transactions = $paymentResponse->getTransactions()->getTransactionList()->getItems();

        /** @var TransactionInterface $lastTransaction */
        $lastTransaction = null;

        foreach ($transactions as $transaction) {
            if (!$lastTransaction) {
                $lastTransaction = $transaction;
            } elseif (strtotime($lastTransaction->getCreated()) < strtotime($transaction->getCreated())) {
                $lastTransaction = $transaction;
            }
        }

        return $lastTransaction;
    }

    /**
     * @return PaymentObjectInterface|null
     */
    public function getPaymentResponseResource()
    {
        return $this->paymentResponseResource;
    }

    /**
     * @param PaymentObjectInterface $paymentResponseResource
     * @return $this
     */
    public function setPaymentResponseResource($paymentResponseResource)
    {
        $this->paymentResponseResource = $paymentResponseResource;

        return $this;
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
