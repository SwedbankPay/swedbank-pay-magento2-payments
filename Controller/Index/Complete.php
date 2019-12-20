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
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as SwedbankOrderRepository;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;
use SwedbankPay\Payments\Helper\Service as ServiceHelper;

/**
 * Class Complete
 *
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
    protected $swedbankOrderRepo;

    /**
     * @var ServiceHelper
     */
    protected $serviceHelper;

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
     * @param UrlInterface $urlInterface
     * @param CheckoutSession $checkoutSession
     * @param SwedbankOrderRepository $swedbankOrderRepo
     * @param ServiceHelper $serviceHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        EventManager $eventManager,
        ConfigHelper $configHelper,
        Logger $logger,
        CheckoutSession $checkoutSession,
        SwedbankOrderRepository $swedbankOrderRepo,
        ServiceHelper $serviceHelper,
        UrlInterface $urlInterface
    ) {
        parent::__construct($context, $resultJsonFactory, $eventManager, $configHelper, $logger);

        $this->checkoutSession = $checkoutSession;
        $this->swedbankOrderRepo = $swedbankOrderRepo;
        $this->serviceHelper = $serviceHelper;
        $this->urlInterface = $urlInterface;

        $this->setEventName('complete');
        $this->setEventMethod([$this, 'complete']);
    }

    /**
     * @throws ServiceException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \PayEx\Api\Client\Exception
     */
    public function complete()
    {
        $this->logger->debug('Complete controller is called');

        $order = $this->checkoutSession->getLastRealOrder();

        $swedbankOrder = $this->swedbankOrderRepo->getByOrderId($order->getEntityId());

        $paymentData = $this->serviceHelper->getPaymentData($swedbankOrder->getPaymentIdPath());

        switch ($paymentData->getState()) {
            case 'Failed':
                $this->logger->debug('Cancelling the Order # ' . $order->getEntityId());

                $url = $this->urlInterface->getUrl('SwedbankPayPayments/Index/Cancel');
                $this->setRedirect($url);
                break;
            default:
                $this->logger->debug(
                    sprintf(
                        'Order ID %s has Transaction State \'%s\'',
                        $order->getEntityId(),
                        $paymentData->getState()
                    )
                );

                $url = $this->urlInterface->getUrl('checkout/onepage/success');
                $this->setRedirect($url);
                break;
        }
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
