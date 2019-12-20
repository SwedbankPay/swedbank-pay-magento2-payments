<?php

namespace SwedbankPay\Payments\Gateway\Command;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Command;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Quote\Model\QuoteRepository as MageQuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterface as MagentoOrderInterface;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;
use PayEx\Api\Service\Data\RequestInterface;
use PayEx\Api\Service\Resource\Data\ResponseInterface as ResponseResourceInterface;
use PayEx\Framework\AbstractDataTransferObject;
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Exception\SwedbankPayException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as ClientRequestService;
use SwedbankPay\Payments\Api\Data\OrderInterface as PaymentOrderInterface;
use SwedbankPay\Payments\Api\Data\QuoteInterface as PaymentQuoteInterface;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as PaymentOrderRepository;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface as PaymentQuoteRepository;

/**
 * Class AbstractCommand
 *
 * @package SwedbankPay\Payments\Gateway\Command
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractCommand extends DataObject implements CommandInterface
{
    const GATEWAY_COMMAND_CAPTURE = 'capture';
    const GATEWAY_COMMAND_CANCEL = 'cancel';
    const GATEWAY_COMMAND_REFUND = 'refund';

    const TRANSACTION_ACTION_CAPTURE = 'capture';
    const TRANSACTION_ACTION_CANCEL = 'cancellation';
    const TRANSACTION_ACTION_REFUND = 'reversal';

    /**
     * @var PaymentOrderRepository
     */
    protected $paymentOrderRepo;

    /**
     * @var PaymentQuoteRepository
     */
    protected $paymentQuoteRepo;

    /**
     * @var ClientRequestService
     */
    protected $requestService;

    /**
     * @var MageQuoteRepository
     */
    protected $mageQuoteRepo;

    /**
     * @var MageOrderRepository
     */
    protected $mageOrderRepo;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $cmdTransActionMap = [];

    /**
     * AbstractCommand constructor.
     *
     * @param PaymentOrderRepository $paymentOrderRepo
     * @param PaymentQuoteRepository $paymentQuoteRepo
     * @param ClientRequestService $requestService
     * @param MageQuoteRepository $mageQuoteRepo
     * @param MageOrderRepository $mageOrderRepo
     * @param Logger $logger
     * @param array $data
     */
    public function __construct(
        PaymentOrderRepository $paymentOrderRepo,
        PaymentQuoteRepository $paymentQuoteRepo,
        ClientRequestService $requestService,
        MageQuoteRepository $mageQuoteRepo,
        MageOrderRepository $mageOrderRepo,
        Logger $logger,
        array $data = []
    ) {
        parent::__construct($data);
        $this->paymentOrderRepo = $paymentOrderRepo;
        $this->paymentQuoteRepo = $paymentQuoteRepo;
        $this->requestService = $requestService;
        $this->mageQuoteRepo = $mageQuoteRepo;
        $this->mageOrderRepo = $mageOrderRepo;
        $this->logger = $logger;

        $this->cmdTransActionMap = [
            self::GATEWAY_COMMAND_CAPTURE => self::TRANSACTION_ACTION_CAPTURE,
            self::GATEWAY_COMMAND_CANCEL => self::TRANSACTION_ACTION_CANCEL,
            self::GATEWAY_COMMAND_REFUND => self::TRANSACTION_ACTION_REFUND
        ];
    }

    /**
     * AbstractCommand command
     *
     * @param array $commandSubject
     *
     * @return null|Command\ResultInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    abstract public function execute(array $commandSubject);

    /**
     * Get client request service class
     *
     * @param string $service
     * @param string $operation
     * @param AbstractDataTransferObject|null $dataTransferObject
     * @return RequestInterface|string
     * @throws ServiceException
     */
    protected function getRequestService($service, $operation, $dataTransferObject = null)
    {
        return $this->requestService->init($service, $operation, $dataTransferObject);
    }

    /**
     * Generates a random string
     *
     * @param $length
     * @return bool|string
     */
    protected function generateRandomString($length)
    {
        return substr(str_shuffle(md5(time())), 0, $length);
    }

    /**
     * @param string $command
     * @param ResponseResourceInterface $responseResource
     * @param OrderInterface $mageOrder
     * @param string $paymentId
     * @throws SwedbankPayException
     */
    protected function checkResponseResource($command, $responseResource, $mageOrder, $paymentId)
    {
        if ($responseResource instanceof ResponseResourceInterface) {
            return;
        }

        $this->logger->error(
            sprintf(
                "Failed to %s order %s with SwedbankPay payment id %s, response resource:\n%s",
                $command,
                $mageOrder->getEntityId(),
                $paymentId,
                print_r($responseResource, true)
            )
        );

        throw new SwedbankPayException(
            new Phrase(
                sprintf(
                    "SwedbankPay %s Error: Failed to parse response for SwedbankPay payment %s.",
                    ucfirst($command),
                    $paymentId
                )
            )
        );
    }

    /**
     * @param string $command
     * @param array $responseData
     * @param OrderInterface $mageOrder
     * @param string $paymentId
     * @throws SwedbankPayException
     */
    protected function checkResponseData($command, $responseData, $mageOrder, $paymentId)
    {
        if (is_array($responseData)
            && isset($responseData[$this->cmdTransActionMap[$command]]['transaction']['state'])
            && $responseData[$this->cmdTransActionMap[$command]]['transaction']['state'] == 'Completed') {
            return;
        }

        $this->logger->error(
            sprintf(
                "Failed to %s order %s with SwedbankPay payment order id %s, response data:\n%s",
                $command,
                $mageOrder->getEntityId(),
                $paymentId,
                print_r($responseData, true)
            )
        );

        throw new SwedbankPayException(
            new Phrase(
                sprintf(
                    "SwedbankPay %s Error: Failed to %s SwedbankPay payment order %s.",
                    ucfirst($command),
                    $command,
                    $paymentId
                )
            )
        );
    }

    /**
     * Get SwedbankPay payment order data
     *
     * @param MagentoOrderInterface|int|string $order
     *
     * @return PaymentOrderInterface|PaymentQuoteInterface|false
     * @throws NoSuchEntityException
     */
    protected function getSwedbankPayPaymentData($order)
    {
        if (is_numeric($order)) {
            return $this->paymentOrderRepo->getByOrderId($order);
        }

        if ($order instanceof MagentoOrderInterface) {
            if ($order->getEntityId()) {
                return $this->paymentOrderRepo->getByOrderId($order->getEntityId());
            }
            return $this->paymentQuoteRepo->getByQuoteId($order->getQuoteId());
        }

        $this->logger->error(
            sprintf("Unable to find a SwedbankPay payment matching order:\n%s", print_r($order, true))
        );

        throw new NoSuchEntityException(
            new Phrase(
                sprintf("Unable to find a SwedbankPay payment matching order %s", $order->getIncrementId())
            )
        );
    }

    /**
     * @param string $command
     * @param string|int|float $amount
     * @param OrderInterface $mageOrder
     * @param PaymentOrderInterface|PaymentQuoteInterface $swedbankPayOrder
     * @throws SwedbankPayException
     */
    protected function checkRemainingAmount($command, $amount, $mageOrder, $swedbankPayOrder)
    {
        $getMethod = 'getRemaining' . ucfirst($this->cmdTransActionMap[$command]) . 'Amount';
        $remainingAmount = (int)call_user_func([$swedbankPayOrder, $getMethod]);

        if ($remainingAmount >= ($amount * 100)) {
            return;
        }

        $this->logger->error(
            sprintf(
                "Failed to %s order %s with SwedbankPay payment id %s:" .
                "The amount of %s exceeds the remaining %s.",
                $command,
                $mageOrder->getEntityId(),
                $swedbankPayOrder->getPaymentId(),
                $amount,
                $remainingAmount
            )
        );

        throw new SwedbankPayException(
            new Phrase(
                sprintf(
                    "SwedbankPay %s Error: The amount of %s exceeds the remaining %s.",
                    ucfirst($command),
                    $amount,
                    $remainingAmount
                )
            )
        );
    }

    /**
     * @param string $command
     * @param array $responseData
     * @param OrderInterface $mageOrder
     * @param PaymentOrderInterface|PaymentQuoteInterface $swedbankPayOrder
     * @return string
     * @throws SwedbankPayException
     */
    protected function getTransactionResult($command, $responseData, $mageOrder, $swedbankPayOrder)
    {
        $state = isset($responseData[$this->cmdTransActionMap[$command]]['transaction']['state']) ?
            $responseData[$this->cmdTransActionMap[$command]]['transaction']['state'] : 'Failed';

        switch ($state) {
            case "Initialized":
            case "AwaitingActivity":
                $status = "pending";
                break;
            case "Completed":
                $status = "complete";
                break;
            case "Failed":
            default:
                $this->logger->error(
                    sprintf(
                        "Failed to %s order %s with SwedbankPay payment order id %s, response data:\n%s",
                        $command,
                        $mageOrder->getEntityId(),
                        $swedbankPayOrder->getPaymentId(),
                        print_r($responseData, true)
                    )
                );
                throw new SwedbankPayException(
                    new Phrase(
                        sprintf(
                            "SwedbankPay %s Error: Failed to %s SwedbankPay payment order %s.",
                            ucfirst($command),
                            $command,
                            $swedbankPayOrder->getPaymentId()
                        )
                    )
                );
                break;
        }

        return $status;
    }
}
