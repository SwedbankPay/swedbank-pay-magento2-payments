<?php

namespace SwedbankPay\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Order extends AbstractDb
{
    const MAIN_TABLE = 'swedbank_pay_payments_orders';
    const ID_FIELD_NAME = 'id';

    // phpcs:disable
    protected function _construct()
    {
        // phpcs:enable
        $this->_init(self::MAIN_TABLE, self::ID_FIELD_NAME);
    }
}
