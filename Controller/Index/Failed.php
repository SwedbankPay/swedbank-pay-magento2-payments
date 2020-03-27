<?php

namespace SwedbankPay\Payments\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;

class Failed extends PaymentActionAbstract
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

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
     * @param ManagerInterface $messageManager
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        EventManager $eventManager,
        ConfigHelper $configHelper,
        Logger $logger,
        ManagerInterface $messageManager,
        UrlInterface $urlInterface
    ) {
        parent::__construct($context, $resultJsonFactory, $eventManager, $configHelper, $logger);

        $this->messageManager = $messageManager;
        $this->urlInterface = $urlInterface;

        $this->setEventName('failed');
        $this->setEventMethod([$this, 'failed']);
    }

    public function failed()
    {
        $message = 'The payment was not successful. Please try again';
        $this->messageManager->addError($message);

        $url = $this->urlInterface->getUrl('checkout/cart');
        $this->setRedirect($url);
    }
}
