<?php

namespace SwedbankPay\Payments\Model\Instrument;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Request\TransactionObject;
use SwedbankPay\Api\Service\Resource\Request as RequestResource;
use SwedbankPay\Api\Service\Vipps\Transaction\Resource\Request\TransactionCancellation;
use SwedbankPay\Api\Service\Vipps\Transaction\Resource\Request\TransactionCapture;
use SwedbankPay\Api\Service\Vipps\Transaction\Resource\Request\TransactionReversal;
use SwedbankPay\Payments\Api\Data\OrderInterface as SwedbankPayOrderInterface;

class Vipps extends AbstractInstrument
{
    protected $instrument = 'vipps';
    protected $paymentOperation = 'Purchase';
    protected $hostedUriRel = 'view-authorization';
    protected $redirectUriRel = 'redirect-authorization';
    protected $allowedCurrencies = 'NOK';

    /**
     * @return RequestResource
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createPaymentObject()
    {
        $payment = $this->subresourceFactory->create($this->instrument, 'Payment');
        $payment->setOperation('Purchase')
            ->setIntent('Authorization')
            ->setCurrency($this->getCurrency())
            ->setDescription($this->getStoreName() . ' ' . __('Purchase'))
            ->setUserAgent($this->apiClient->getUserAgent())
            ->setLanguage($this->getLanguage())
            ->setUrls($this->createUrlObject())
            ->setPayeeInfo($this->createPayeeInfoObject())
            ->setPrices($this->createPricesCollectionObject());

        $paymentObject = $this->subresourceFactory->create($this->instrument, 'VippsPaymentObject');
        $paymentObject->setPayment($payment);

        return $paymentObject;
    }

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @param Order|null $order
     * @return TransactionObject
     */
    public function createCaptureTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder, Order $order = null)
    {
        $transaction = new TransactionCapture();
        $transaction->setAmount($swedbankPayOrder->getAmount());
        $transaction->setVatAmount($swedbankPayOrder->getVatAmount());
        $transaction->setDescription('Capturing ' . $swedbankPayOrder->getAmount());
        $transaction->setPayeeReference($this->generateRandomString(30));

        $transactionObject = new TransactionObject();
        $transactionObject->setTransaction($transaction);

        return $transactionObject;
    }

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @return TransactionObject
     */
    public function createRefundTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder)
    {
        $transaction = new TransactionReversal();
        $transaction->setAmount($swedbankPayOrder->getAmount());
        $transaction->setVatAmount($swedbankPayOrder->getVatAmount());
        $transaction->setDescription('Reversing ' . $swedbankPayOrder->getAmount());
        $transaction->setPayeeReference($this->generateRandomString(30));

        $transactionObject = new TransactionObject();
        $transactionObject->setTransaction($transaction);

        return $transactionObject;
    }

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @return TransactionObject
     */
    public function createCancelTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder)
    {
        $transaction = new TransactionCancellation();
        $transaction->setDescription('Cancelling ' . $swedbankPayOrder->getAmount());
        $transaction->setPayeeReference($this->generateRandomString(30));

        $transactionObject = new TransactionObject();
        $transactionObject->setTransaction($transaction);

        return $transactionObject;
    }
}
