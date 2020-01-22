<?php

namespace SwedbankPay\Payments\Model\Instrument;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use PayEx\Api\Service\Creditcard\Resource\Request\PaymentPurchaseCreditcard;
use PayEx\Api\Service\Creditcard\Transaction\Resource\Request\TransactionCancellation;
use PayEx\Api\Service\Creditcard\Transaction\Resource\Request\TransactionCapture;
use PayEx\Api\Service\Creditcard\Transaction\Resource\Request\TransactionReversal;
use PayEx\Api\Service\Payment\Transaction\Resource\Request\TransactionObject;
use PayEx\Api\Service\Resource\Request as RequestResource;
use SwedbankPay\Payments\Api\Data\OrderInterface as SwedbankPayOrderInterface;

class Creditcard extends AbstractInstrument
{
    protected $instrument = 'creditcard';
    protected $instrumentPrettyName = 'Credit Card';
    protected $jsObjectName = 'creditCard';
    protected $paymentOperation = 'Purchase';
    protected $hostedUriRel = 'view-authorization';
    protected $redirectUriRel = 'redirect-authorization';

    /**
     * @return RequestResource
     * @throws NoSuchEntityException
     */
    public function createPaymentObject()
    {
        $payment = $this->subresourceFactory->create($this->instrument, 'PaymentPurchase');
        $payment->setOperation('Purchase')
            ->setIntent('Authorization')
            ->setCurrency($this->getCurrency())
            ->setGeneratePaymentToken(false)
            ->setDescription($this->getStoreName() . ' ' . __('Purchase'))
            ->setUserAgent($this->apiClient->getUserAgent())
            ->setLanguage($this->getLanguage())
            ->setUrls($this->createUrlObject())
            ->setPayeeInfo($this->createPayeeInfoObject())
            ->setPrices($this->createPricesCollectionObject());

        $creditCard = new PaymentPurchaseCreditcard();
        $creditCard->setNo3DSecure(false)
            ->setMailOrderTelephoneOrder(false)
            ->setRejectCardNot3DSecureEnrolled(false)
            ->setRejectCreditCards(false)
            ->setRejectDebitCards(false)
            ->setRejectConsumerCards(false)
            ->setRejectCorporateCards(false)
            ->setRejectAuthenticationStatusA(false)
            ->setRejectAuthenticationStatusU(false);

        $paymentObject = $this->subresourceFactory->create($this->instrument, 'PaymentPurchaseObject');
        $paymentObject->setPayment($payment);
        $paymentObject->setCreditCard($creditCard);

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
