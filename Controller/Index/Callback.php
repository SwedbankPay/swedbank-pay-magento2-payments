<?php

namespace SwedbankPay\Payments\Controller\Index;

use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestContentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use SwedbankPay\Api\Response;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Response\Data\TransactionInterface;
use SwedbankPay\Core\Helper\Order as OrderHelper;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Api\Data\OrderInterface;
use SwedbankPay\Payments\Api\Data\QuoteInterface;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;
use SwedbankPay\Payments\Helper\Service as ServiceHelper;
use SwedbankPay\Payments\Model\ResourceModel\OrderRepository;

/**
 * Class Callback
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Callback extends PaymentActionAbstract implements CsrfAwareActionInterface
{
    /**
     * @var RequestContentInterface;
     */
    protected $requestContent;

    /**
     * @var JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var OrderRepositoryInterface
     */
    protected $magentoOrderRepo;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var ServiceHelper
     */
    protected $serviceHelper;

    /**
     * Callback constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param EventManager $eventManager
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param ServiceHelper $serviceHelper
     * @param RequestContentInterface $requestContent
     * @param JsonFactory $jsonResultFactory
     * @param OrderHelper $orderHelper
     * @param OrderRepositoryInterface $magentoOrderRepo
     * @param OrderRepository $orderRepository
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        EventManager $eventManager,
        ConfigHelper $configHelper,
        Logger $logger,
        ServiceHelper $serviceHelper,
        RequestContentInterface $requestContent,
        JsonFactory $jsonResultFactory,
        OrderHelper $orderHelper,
        OrderRepositoryInterface $magentoOrderRepo,
        OrderRepository $orderRepository
    ) {
        parent::__construct($context, $resultJsonFactory, $eventManager, $configHelper, $logger);

        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderHelper = $orderHelper;
        $this->requestContent = $requestContent;
        $this->magentoOrderRepo = $magentoOrderRepo;
        $this->orderRepository = $orderRepository;
        $this->serviceHelper = $serviceHelper;

        $this->setEventName('callback');
        $this->setEventMethod([$this, 'updatePaymentData']);
    }

    /**
     * @return array|bool|ResponseInterface|ResultInterface|string
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function updatePaymentData()
    {
        $callbackContent = $this->requestContent->getContent();

        $this->logger->debug(
            sprintf(
                "Callback controller is called with request: \n %s",
                $callbackContent
            )
        );

        try {
            $response = new Response($callbackContent);
        } catch (\SwedbankPay\Api\Client\Exception $e) {
            return $this->createResult(
                'error',
                $e->getMessage(),
                "Request Data:\n" . $callbackContent
            );
        }

        $requestData = $response->toArray();

        $paymentId = $requestData['payment']['id'];
        $transactionId = $requestData['transaction']['id'];

        $paymentData = $this->serviceHelper->getPaymentData($paymentId);
        $transactionData = $this->serviceHelper->getTransactionData($transactionId, $paymentId);

//        $paymentData = null;
//        $transactionData = null;
//
//        foreach ($requestData as $requestKey => $requestValue) {
//            switch ($requestKey) {
//                case 'payment':
//                    $paymentData = $this->serviceHelper->getPaymentData($requestValue['id']);
//                    break;
//                case 'transaction':
//                    // Replaces specific transactions with generic 'transactions'
////                    $transactionUri = preg_replace(
////                        '|/psp/([^/]+)/payments/([^/]+)/([^/]+)/([^/]+)|',
////                        '/psp/$1/payments/$2/transactions/$4',
////                        $requestValue['id']
////                    );
//
//                    $transactionData = $this->serviceHelper->getTransactionData($requestValue['id'], $paymentData);
//                    break;
//            }
//        }

        if (!($transactionData instanceof TransactionInterface)) {
            return $this->createResult(
                'error',
                'Failed to retrieve transaction data',
                "Request Data:\n" . $callbackContent
            );
        }

        if (!$paymentData->getOrderId()) {
            return $this->createResult(
                'success',
                'Order does not exist with payment ID'
            );
        }

        /** @var $order MagentoOrder */
        $order = $this->magentoOrderRepo->get($paymentData->getOrderId());

        $this->updatePrices($paymentData, $transactionData);
        $this->updateState($paymentData, $transactionData);

        $this->logger->debug(
            sprintf(
                'Transaction in Callback controller, type: \'%s\' & state: \'%s\'',
                $transactionData->getType(),
                $transactionData->getState()
            )
        );

        $this->logger->debug(
            sprintf(
                'Order ID %s is updated to state \'%s\' & status \'%s\'',
                $order->getEntityId(),
                $order->getState(),
                $order->getStatus()
            )
        );

        return $this->createResult(
            'success',
            'Order was updated successfully'
        );
    }

    /**
     * @param QuoteInterface|OrderInterface $paymentData
     * @param TransactionInterface $transactionData
     *
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    protected function updatePrices($paymentData, $transactionData)
    {
        $swedbankPayOrder = $this->orderRepository->getByPaymentId($paymentData->getPaymentId());

        $swedbankPayOrder->setAmount($transactionData->getAmount());
        $swedbankPayOrder->setVatAmount($transactionData->getVatAmount());
        $swedbankPayOrder->setRemainingCapturingAmount($transactionData->getAmount());
        $swedbankPayOrder->setRemainingCancellationAmount($transactionData->getAmount());

        $this->orderRepository->save($swedbankPayOrder);
    }

    /**
     * @param QuoteInterface|OrderInterface $paymentData
     * @param TransactionInterface $transactionData
     *
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function updateState($paymentData, $transactionData)
    {
        $swedbankPayOrder = $this->orderRepository->getByPaymentId($paymentData->getPaymentId());
        $swedbankPayOrder->setState($transactionData->getState());

        $this->orderRepository->save($swedbankPayOrder);

        /** @var $order MagentoOrder */
        $order = $this->magentoOrderRepo->get($paymentData->getOrderId());

        switch ($transactionData->getState()) {
            case 'Initialized':
                $this->orderHelper->setStatus($order, OrderHelper::STATUS_PENDING);
                break;
            case 'Completed':
                if ($order->getState() == MagentoOrder::STATE_CANCELED &&
                    $transactionData->getType() == 'Cancellation') {
                    break;
                }

                if ($order->getState() == MagentoOrder::STATE_PENDING_PAYMENT ||
                    $order->getState() == MagentoOrder::STATE_PAYMENT_REVIEW ||
                    $order->getState() == MagentoOrder::STATE_CANCELED) {
                    $order->setState(MagentoOrder::STATE_PROCESSING);
                    $order->setStatus(MagentoOrder::STATE_PROCESSING);
                }

                $order->addCommentToStatusHistory('SwedbankPay payment processed successfully.', $order->getStatus());
                $this->magentoOrderRepo->save($order);

                if (($paymentData instanceof OrderInterface) && $paymentData->getIntent() == 'Sale') {
                    $this->orderHelper->createInvoice($order);
                }
                break;
            case 'Failed':
                $order->setState(MagentoOrder::STATE_CANCELED);
                $order->setStatus(MagentoOrder::STATE_CANCELED);

                $order->addCommentToStatusHistory('SwedbankPay payment failed, cancelled order.');
                $this->magentoOrderRepo->save($order);
                break;
        }
    }

    /**
     * @param $code
     * @param $message
     * @param string $debugInfo
     * @return Json
     */
    protected function createResult($code, $message, $debugInfo = '')
    {
        $result = $this->jsonResultFactory->create([
            "code" => $code,
            "message" => $message
        ]);

        if ($code == 'error') {
            $message = ($debugInfo) ? $message . "\nDebug Info:\n" . $debugInfo : $message;
            $this->logger->error(
                $message . ":\n" . $debugInfo
            );
        }

        return $result;
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
