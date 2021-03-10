<?php

namespace SwedbankPay\Payments\Model\ResourceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class Collection extends AbstractCollection
{
    // phpcs:disable
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'swedbank_pay_payments_orders_collection';
    protected $_eventObject = 'orders_collection';
    // phpcs:enable

    /**
     * Define resource model
     *
     * @return void
     *
     *  phpcs:disable
     */
    protected function _construct()
    {
        // phpcs:enable
        $this->_init(\SwedbankPay\Payments\Model\Order::class, \SwedbankPay\Payments\Model\ResourceModel\Order::class);
    }
}
