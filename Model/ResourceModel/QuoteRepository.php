<?php

namespace SwedbankPay\Payments\Model\ResourceModel;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\NoSuchEntityException;
use SwedbankPay\Payments\Api\Data\QuoteInterface;
use SwedbankPay\Payments\Api\Data\QuoteSearchResultInterface;
use SwedbankPay\Payments\Api\Data\QuoteSearchResultInterfaceFactory;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface;
use SwedbankPay\Payments\Model\QuoteFactory;
use SwedbankPay\Payments\Model\Quote as QuoteModel;
use SwedbankPay\Payments\Model\ResourceModel\Quote as QuoteResource;
use SwedbankPay\Payments\Model\ResourceModel\Quote\Collection as QuoteCollection;
use SwedbankPay\Payments\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;

/**
 * Class QuoteRepository
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuoteRepository implements QuoteRepositoryInterface
{
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteResource
     */
    protected $quoteResource;

    /**
     * @var QuoteCollectionFactory
     */
    protected $quoteCollectionFactory;

    /**
     * @var QuoteSearchResultInterfaceFactory
     */
    protected $searchResultFactory;

    /**
     * QuoteRepository constructor.
     * @param QuoteFactory $quoteFactory
     * @param Quote $quoteResource
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param QuoteSearchResultInterfaceFactory $quoteSearchResultInterfaceFactory
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        QuoteResource $quoteResource,
        QuoteCollectionFactory $quoteCollectionFactory,
        QuoteSearchResultInterfaceFactory $quoteSearchResultInterfaceFactory
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->searchResultFactory = $quoteSearchResultInterfaceFactory;
        $this->quoteResource = $quoteResource;
    }

    /**
     * @param int $entityId
     * @return QuoteInterface|quoteResource
     * @throws NoSuchEntityException
     */
    public function getById($entityId)
    {
        /** @var QuoteModel $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $entityId);
        if (!$quote->getId()) {
            throw new NoSuchEntityException(
                __("The Quote that was requested doesn't exist. Verify the Quote id and try again.")
            );
        }
        return $quote;
    }

    /**
     * @param $quoteId
     * @return QuoteModel
     * @throws NoSuchEntityException
     */
    public function getByQuoteId($quoteId)
    {
        /** @var QuoteModel $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $quoteId, 'quote_id');
        if (!$quote->getId()) {
            throw new NoSuchEntityException(
                __("The Quote that was requested doesn't exist. Verify the Magento Quote id and try again.")
            );
        }
        return $quote;
    }

    /**
     * @param string $paymentId
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    public function getByPaymentId($paymentId)
    {
        /** @var QuoteModel $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $paymentId, 'payment_id');
        if (!$quote->getId()) {
            throw new NoSuchEntityException(
                __("The Quote that was requested doesn't exist. Verify the Payment Order id and try again.")
            );
        }
        return $quote;
    }

    /**
     * @param QuoteInterface $quote
     * @return QuoteInterface
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function save(QuoteInterface $quote)
    {
        /** @var QuoteModel $quote */
        $this->quoteResource->save($quote);
        return $quote;
    }

    /**
     * @param QuoteInterface $quote
     * @throws \Exception
     */
    public function delete(QuoteInterface $quote)
    {
        /** @var QuoteModel $quote */
        $this->quoteResource->delete($quote);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return QuoteSearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var QuoteCollection $collection */
        $collection = $this->quoteCollectionFactory->create();

        $this->addFiltersToCollection($searchCriteria, $collection);
        $this->addSortOrdersToCollection($searchCriteria, $collection);
        $this->addPagingToCollection($searchCriteria, $collection);

        $collection->load();

        return $this->buildSearchResult($searchCriteria, $collection);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param QuoteCollection $collection
     */
    protected function addFiltersToCollection(SearchCriteriaInterface $searchCriteria, QuoteCollection $collection)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $fields = $conditions = [];
            foreach ($filterGroup->getFilters() as $filter) {
                $fields[] = $filter->getField();
                $conditions[] = [$filter->getConditionType() => $filter->getValue()];
            }
            $collection->addFieldToFilter($fields, $conditions);
        }
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param QuoteCollection $collection
     */
    protected function addSortOrdersToCollection(SearchCriteriaInterface $searchCriteria, QuoteCollection $collection)
    {
        foreach ((array) $searchCriteria->getSortOrders() as $sortOrder) {
            /** @var string $direction */
            $direction = ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'asc' : 'desc';
            $collection->addOrder($sortOrder->getField(), $direction);
        }
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param QuoteCollection $collection
     */
    protected function addPagingToCollection(SearchCriteriaInterface $searchCriteria, QuoteCollection $collection)
    {
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->setCurPage($searchCriteria->getCurrentPage());
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param QuoteCollection $collection
     * @return QuoteSearchResultInterface
     */
    protected function buildSearchResult(SearchCriteriaInterface $searchCriteria, QuoteCollection $collection)
    {
        /** @var QuoteSearchResultInterface $searchResults */
        $searchResults = $this->searchResultFactory->create();

        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
