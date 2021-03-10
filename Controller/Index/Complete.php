<?php

namespace SwedbankPay\Payments\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use SwedbankPay\Api\Service\Payment\Resource\Response\Data\PaymentObjectInterface;
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Api\Data\OrderInterface;
use SwedbankPay\Payments\Api\Data\QuoteInterface;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as SwedbankOrderRepository;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;
use SwedbankPay\Payments\Helper\PaymentData as PaymentDataHelper;
use SwedbankPay\Payments\Helper\Service as ServiceHelper;
use SwedbankPay\Payments\Helper\ServiceFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Complete extends PaymentActionAbstract implements CsrfAwareActionInterface
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var SwedbankOrderRepository
     */
    protected $swedbankPayOrderRepo;

    /**
     * @var ServiceFactory
     */
    protected $serviceFactory;

    /**
     * @var PaymentDataHelper
     */
    protected $paymentDataHelper;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * Complete constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param EventManager $eventManager
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param CheckoutSession $checkoutSession
     * @param SwedbankOrderRepository $swedbankPayOrderRepo
     * @param ServiceFactory $serviceFactory
     * @param PaymentDataHelper $paymentDataHelper
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        EventManager $eventManager,
        ConfigHelper $configHelper,
        Logger $logger,
        CheckoutSession $checkoutSession,
        SwedbankOrderRepository $swedbankPayOrderRepo,
        ServiceFactory $serviceFactory,
        PaymentDataHelper $paymentDataHelper,
        UrlInterface $urlInterface
    ) {
        parent::__construct($context, $resultJsonFactory, $eventManager, $configHelper, $logger);

        $this->checkoutSession = $checkoutSession;
        $this->swedbankPayOrderRepo = $swedbankPayOrderRepo;
        $this->serviceFactory = $serviceFactory;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->urlInterface = $urlInterface;

        $this->setEventName('complete');
        $this->setEventMethod([$this, 'complete']);
    }

    /**
     * @throws ServiceException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \SwedbankPay\Api\Client\Exception
     */
    public function complete()
    {
        $this->logger->debug('Complete controller is called');

        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order->getEntityId()) {
            $url = $this->urlInterface->getUrl('checkout/cart');
            $this->setRedirect($url);

            return;
        }

        $swedbankPayOrder = $this->swedbankPayOrderRepo->getByOrderId($order->getEntityId());

        /** @var ServiceHelper $serviceHelper */
        $serviceHelper = $this->serviceFactory->create();

        $paymentResponseResource = $serviceHelper
            ->currentPayment(
                $swedbankPayOrder->getInstrument(),
                $swedbankPayOrder->getPaymentIdPath(),
                ['transactions']
            )
            ->getPaymentResponseResource();

        $this->updateIntent($swedbankPayOrder, $paymentResponseResource);

        switch ($swedbankPayOrder->getState()) {
            case 'Failed':
                $this->cancelOrder($order);
                break;
            default:
                $lastTransactionData = $serviceHelper->getLastTransaction();

                if ($lastTransactionData->getState() != 'Completed') {
                    $this->logger->debug(
                        sprintf(
                            'Order ID %s has Payment State \'%s\' but Transaction State \'%s\'',
                            $order->getEntityId(),
                            $swedbankPayOrder->getState(),
                            $lastTransactionData->getState()
                        )
                    );

                    $this->cancelOrder($order);
                    return;
                }

                $this->logger->debug(
                    sprintf(
                        'Order ID %s has Payment State \'%s\'',
                        $order->getEntityId(),
                        $swedbankPayOrder->getState()
                    )
                );

                $url = $this->urlInterface->getUrl('checkout/onepage/success');
                $this->setRedirect($url);
                break;
        }
    }

    /**
     * @param QuoteInterface|OrderInterface $paymentData
     * @param PaymentObjectInterface $paymentResponseResource
     */
    public function updateIntent($paymentData, $paymentResponseResource)
    {
        $paymentData->setIntent($paymentResponseResource->getPayment()->getIntent());
        $paymentData->setState($paymentResponseResource->getPayment()->getState());
        $paymentData->setAmount($paymentResponseResource->getPayment()->getAmount());

        $this->paymentDataHelper->update($paymentData);
    }

    /**
     * @param Order $order
     */
    protected function cancelOrder($order)
    {
        $this->logger->debug('Cancelling the Order # ' . $order->getEntityId());

        $url = $this->urlInterface->getUrl('SwedbankPayPayments/Index/Cancel');
        $this->setRedirect($url);
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
