<?php


namespace SwedbankPay\Payments\Model\Instrument\Data;

use Magento\Sales\Model\Order;
use PayEx\Api\Service\Payment\Transaction\Resource\Request\TransactionObject;
use PayEx\Api\Service\Resource\Request as RequestResource;
use SwedbankPay\Payments\Api\Data\OrderInterface as SwedbankPayOrderInterface;

interface InstrumentInterface
{
    /**
     * @return string|null
     */
    public function getInstrumentName();

    /**
     * @return string|null
     */
    public function getInstrumentPrettyName();

    /**
     * @return string|null
     */
    public function getJsObjectName();

    /**
     * @return string|null
     */
    public function getPaymentOperation();

    /**
     * @return string|null
     */
    public function getHostedUriRel();

    /**
     * @return string|null
     */
    public function getRedirectUriRel();

    /**
     * @return RequestResource
     */
    public function createPaymentObject();

    /**
     * @return string|null
     */
    public function getAllowedCurrencies();

    /**
     * @param string|null $currency
     * @return bool
     */
    public function isCurrencySupported($currency = null);

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @param Order|null $order
     * @return TransactionObject
     */
    public function createCaptureTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder, Order $order = null);

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @return TransactionObject
     */
    public function createRefundTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder);

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @return TransactionObject
     */
    public function createCancelTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder);
}
