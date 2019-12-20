<?php

namespace SwedbankPay\Payments\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use SwedbankPay\Payments\Api\Data\QuoteInterface;

interface QuoteRepositoryInterface
{
    /**
     * @param int $entityId
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    public function getById($entityId);

    /**
     * @param int $quoteId
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    public function getByQuoteId($quoteId);

    /**
     * @param string $paymentOrderId
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    public function getByPaymentId($paymentOrderId);

    /**
     * @param QuoteInterface $quote
     * @return QuoteInterface
     */
    public function save(QuoteInterface $quote);

    /**
     * @param QuoteInterface $quote
     * @return void
     */
    public function delete(QuoteInterface $quote);

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return \SwedbankPay\Payments\Api\Data\QuoteSearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
