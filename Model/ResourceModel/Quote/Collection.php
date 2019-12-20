<?php

namespace SwedbankPay\Payments\Model\ResourceModel\Quote;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class Collection extends AbstractCollection
{
    // phpcs:disable
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'swedbank_pay_payments_quotes_collection';
    protected $_eventObject = 'quotes_collection';
    // phpcs:enable

    /**
     * Define resource model
     *
     * @return void
     *
     * phpcs:disable
     */
    protected function _construct()
    {
        // phpcs:enable
        $this->_init('SwedbankPay\Payments\Model\Quote', 'SwedbankPay\Payments\Model\ResourceModel\Quote');
    }
}
