<?php

namespace SwedbankPay\Payments\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Store\Model\StoreManagerInterface;
use SwedbankPay\Api\Client\Exception;
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;
use SwedbankPay\Payments\Helper\Factory\InstrumentFactory;
use SwedbankPay\Payments\Helper\PaymentData as PaymentDataHelper;
use SwedbankPay\Payments\Helper\Service as ServiceHelper;

/**
 * Class OnInstrumentSelected
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OnInstrumentSelected extends PaymentActionAbstract
{
    /**
     * @var HttpRequest
     */
    protected $httpRequest;

    /**
     * @var ServiceHelper
     */
    protected $serviceHelper;

    /**
     * @var PaymentDataHelper
     */
    protected $paymentDataHelper;

    /**
     * @var InstrumentFactory
     */
    protected $instrumentFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * OnInstrumentSelected constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param EventManager $eventManager
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param HttpRequest $httpRequest
     * @param ServiceHelper $serviceHelper
     * @param PaymentDataHelper $paymentDataHelper
     * @param InstrumentFactory $instrumentFactory
     * @param StoreManagerInterface $storeManager
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        EventManager $eventManager,
        ConfigHelper $configHelper,
        Logger $logger,
        HttpRequest $httpRequest,
        ServiceHelper $serviceHelper,
        PaymentDataHelper $paymentDataHelper,
        InstrumentFactory $instrumentFactory,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context, $resultJsonFactory, $eventManager, $configHelper, $logger);

        $this->httpRequest = $httpRequest;
        $this->serviceHelper = $serviceHelper;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->instrumentFactory = $instrumentFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @return Json|ResultInterface|ResponseInterface
     * @throws Exception
     * @throws ServiceException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        if (!$this->configHelper->isActive()) {
            $this->logger->error(
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                basename(get_class($this)) . ' trigger error: Module is not active'
            );
            return $this->setResult(
                __('Not Found: The required SwedbankPay Payments resources do not seem to be available'),
                404
            );
        }

        $instrument = $this->httpRequest->getParam('instrument');

        try {
            $paymentInstrument = $this->instrumentFactory->create($instrument);
        } catch (\Exception $e) {
            return $this->setResult('Instrument not found', 400);
        }

        $responseService = $this->serviceHelper->payment($instrument);

        $this->paymentDataHelper->saveQuoteToDB($responseService->getResponseData(), $instrument);

        $store = $this->storeManager->getStore();

        $viewType = $this->configHelper->getViewType($store);

        if ($viewType === 'hosted_view') {
            $url = $responseService->getOperationByRel(
                $paymentInstrument->getHostedUriRel(),
                'href'
            );

            if ($url) {
                return $this->setResponse('hosted_url', $url);
            }

            return $this->setResult('Hosted URL not found', 400);
        }

        $url = $responseService->getOperationByRel(
            $paymentInstrument->getRedirectUriRel(),
            'href'
        );

        if ($url) {
            return $this->setResponse('redirect_url', $url);
        }

        return $this->setResult('Redirect URL not found', 400);
    }
}