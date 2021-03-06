<?php

namespace SwedbankPay\Payments\Plugin;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteRepository as MagentoQuoteRepository;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Helper\Config;
use SwedbankPay\Payments\Model\ResourceModel\QuoteRepository;

class QuoteRepositoryPlugin
{
    /** @var QuoteRepository  */
    protected $quoteRepository;

    /** @var Config $config */
    protected $config;

    /** @var Logger $logger */
    protected $logger;

    /**
     * QuoteRepositoryPlugin constructor.
     * @param QuoteRepository $quoteRepository
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        QuoteRepository $quoteRepository,
        Config $config,
        Logger $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param MagentoQuoteRepository $subject
     * @param null $result
     * @param CartInterface $quote
     * @throws AlreadyExistsException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(
        /** @noinspection PhpUnusedParameterInspection */ MagentoQuoteRepository $subject,
        $result,
        CartInterface $quote
    ) {
        if (!$this->config->isActive()) {
            return;
        }

        $this->logger->debug('QuoteRepositoryPlugin is called!');

        try {
            $swedbankPayQuote = $this->quoteRepository->getByQuoteId($quote->getId());
            $swedbankPayQuote->setIsUpdated(1);

            $this->quoteRepository->save($swedbankPayQuote);
        } catch (NoSuchEntityException $e) {
            $this->logger->debug(sprintf(
                'No SwedbankPay Quote record has been created yet with ID # %s',
                $quote->getId()
            ));

            $this->logger->debug(sprintf('SwedbankPay Quote update skipped!'));
        }
    }
}
