<?php

namespace SwedbankPay\Payments\Model\Instrument;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Request\TransactionObject;
use SwedbankPay\Api\Service\Resource\Request as RequestResource;
use SwedbankPay\Api\Service\Swish\Transaction\Resource\Request\TransactionReversal;
use SwedbankPay\Payments\Api\Data\OrderInterface as SwedbankPayOrderInterface;

class Swish extends AbstractInstrument
{
    protected $instrument = 'swish';
    protected $paymentOperation = 'Purchase';
    protected $hostedUriRel = 'view-payment';
    protected $redirectUriRel = 'redirect-sale';
    protected $allowedCurrencies = 'SEK';

    /**
     * @return RequestResource
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createPaymentObject()
    {
        $payment = $this->subresourceFactory->create($this->instrument, 'Payment');
        $payment->setOperation('Purchase')
            ->setIntent('Sale')
            ->setCurrency($this->getCurrency())
            ->setDescription($this->getStoreName() . ' ' . __('Purchase'))
            ->setUserAgent($this->apiClient->getUserAgent())
            ->setLanguage($this->getLanguage())
            ->setUrls($this->createUrlObject())
            ->setPayeeInfo($this->createPayeeInfoObject())
            ->setSwish($this->createSwishObject())
            ->setPrices($this->createPricesCollectionObject());

        $paymentObject = $this->subresourceFactory->create($this->instrument, 'SwishPaymentObject');
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
        return null;
    }

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @return TransactionObject
     */
    public function createRefundTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder)
    {
        $transactionReversal = new TransactionReversal();
        $transactionReversal->setAmount($swedbankPayOrder->getAmount());
        $transactionReversal->setVatAmount($swedbankPayOrder->getVatAmount());
        $transactionReversal->setDescription('Reversing ' . $swedbankPayOrder->getAmount());
        $transactionReversal->setPayeeReference($this->generateRandomString(30));

        $transactionObject = new TransactionObject();
        $transactionObject->setTransaction($transactionReversal);

        return $transactionObject;
    }

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @return TransactionObject
     */
    public function createCancelTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder)
    {
        return null;
    }
}
