<?php

namespace SwedbankPay\Payments\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\Manager as EventManager;
use PayEx\Api\Client\Exception;
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
        InstrumentFactory $instrumentFactory
    ) {
        parent::__construct($context, $resultJsonFactory, $eventManager, $configHelper, $logger);

        $this->httpRequest = $httpRequest;
        $this->serviceHelper = $serviceHelper;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->instrumentFactory = $instrumentFactory;
    }


    /**
     * @return Json|ResultInterface|ResponseInterface
     * @throws Exception
     * @throws ServiceException
     */
    public function execute()
    {
        if (!$this->configHelper->isActive()) {
            $this->logger->error(
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

        $redirectUrl = $responseService->getOperationByRel(
            'redirect-' . strtolower($paymentInstrument->getPaymentIntent()),
            'href'
        );

        if ($redirectUrl) {
            return $this->setResponse('redirect_url', $redirectUrl);
        }

        return $this->setResult('Redirect URL not found', 400);
    }
}
