<?php

namespace SwedbankPay\Payments\Model\Instrument;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Header\Logo as HeaderLogo;
use SwedbankPay\Api\Client\Client as ApiClient;
use SwedbankPay\Api\Service\Creditcard\Resource\Request\CardholderShippingAddress;
use SwedbankPay\Api\Service\Creditcard\Resource\Request\PaymentCardholder;
use SwedbankPay\Api\Service\Creditcard\Resource\Request\PaymentPurchase;
use SwedbankPay\Api\Service\Creditcard\Resource\Request\PaymentPurchaseCreditcard;
use SwedbankPay\Api\Service\Creditcard\Resource\Request\PaymentRiskIndicator;
use SwedbankPay\Api\Service\Creditcard\Transaction\Resource\Request\TransactionCancellation;
use SwedbankPay\Api\Service\Creditcard\Transaction\Resource\Request\TransactionCapture;
use SwedbankPay\Api\Service\Creditcard\Transaction\Resource\Request\TransactionReversal;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Request\TransactionObject;
use SwedbankPay\Api\Service\Resource\Request as RequestResource;
use SwedbankPay\Core\Helper\Config as ClientConfig;
use SwedbankPay\Payments\Api\Data\OrderInterface as SwedbankPayOrderInterface;
use SwedbankPay\Payments\Helper\Factory\SubresourceFactory;
use SwedbankPay\Payments\PluginHook;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Creditcard extends AbstractInstrument
{
    protected $instrument = 'creditcard';
    protected $instrumentPrettyName = 'Credit Card';
    protected $jsObjectName = 'creditCard';
    protected $paymentOperation = 'Purchase';
    protected $hostedUriRel = 'view-authorization';
    protected $redirectUriRel = 'redirect-authorization';

    /**
     * @var PluginHook
     */
    protected $pluginHook;

    public function __construct(
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        UrlInterface $urlInterface,
        HeaderLogo $headerLogo,
        ApiClient $apiClient,
        ClientConfig $clientConfig,
        ScopeConfigInterface $scopeConfig,
        LocaleResolver $localeResolver,
        SubresourceFactory $subresourceFactory,
        PluginHook $pluginHook
    ) {
        parent::__construct(
            $checkoutSession,
            $storeManager,
            $urlInterface,
            $headerLogo,
            $apiClient,
            $clientConfig,
            $scopeConfig,
            $localeResolver,
            $subresourceFactory
        );

        $this->pluginHook = $pluginHook;
    }

    /**
     * @return RequestResource
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function createPaymentObject()
    {
        /** @var PaymentPurchase $payment */
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
            ->setPrices($this->createPricesCollectionObject())
            ->setCardholder($this->createCardholderObject())
            ->setRiskIndicator($this->createRiskIndicatorObject());

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

    /**
     * @return PaymentCardholder|null
     */
    public function createCardholderObject()
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $quoteAddress = $quote->getBillingAddress();
        } catch (Exception $e) {
            return null;
        }

        $cardholder = new PaymentCardholder();

        if ($quoteAddress->getFirstname()) {
            $cardholder->setFirstName($quote->getBillingAddress()->getFirstname());
        }

        if ($quoteAddress->getLastname()) {
            $cardholder->setLastName($quote->getBillingAddress()->getLastname());
        }

        if ($quoteAddress->getEmail()) {
            $cardholder->setEmail($quote->getBillingAddress()->getEmail());
        }

        if ($quoteAddress->getTelephone()) {
            $cardholder->setMsisdn($quote->getBillingAddress()->getTelephone());
        }

        $cardholder->setShippingAddress($this->createShippingAddressObject());

        return $cardholder;
    }

    /**
     * @return CardholderShippingAddress|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function createShippingAddressObject()
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $quoteAddress = $quote->getShippingAddress();
        } catch (Exception $e) {
            return null;
        }

        $shippingAddress = new CardholderShippingAddress();

        if ($quoteAddress->getName()) {
            $shippingAddress->setAddressee($quoteAddress->getName());
        }

        if ($quoteAddress->getEmail()) {
            $shippingAddress->setEmail($quoteAddress->getEmail());
        }

        if ($quoteAddress->getTelephone()) {
            $shippingAddress->setMsisdn($quoteAddress->getTelephone());
        }

        if ($quoteAddress->getStreetFull()) {
            $shippingAddress->setStreetAddress($quoteAddress->getStreetFull());
        }

        if ($quoteAddress->getCity()) {
            $shippingAddress->setCity($quoteAddress->getCity());
        }

        if ($quoteAddress->getPostcode()) {
            $shippingAddress->setZipCode($quoteAddress->getPostcode());
        }

        if ($quoteAddress->getCountryModel() && $quoteAddress->getCountryModel()->getCountryId()) {
            $shippingAddress->setCountryCode($quoteAddress->getCountryModel()->getCountryId());
        }

        return $shippingAddress;
    }

    /**
     * @return PaymentRiskIndicator|null
     */
    public function createRiskIndicatorObject()
    {
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (Exception $e) {
            return null;
        }

        $riskIndicator = new PaymentRiskIndicator();

        $deliveryEmailAddress = $this->pluginHook->getDeliveryEmailAddress($quote, []);
        $deliveryTimeFrameIndicator = $this->pluginHook->getDeliveryTimeFrameIndicator($quote, []);
        $preOrderDate = $this->pluginHook->getPreOrderDate($quote, []);
        $preOrderPurchaseIndicator = $this->pluginHook->getPreOrderPurchaseIndicator($quote, []);
        $shipIndicator = $this->pluginHook->getShipIndicator($quote, []);
        $isGiftCardPurchase = $this->pluginHook->isGiftCardPurchase($quote, []);
        $reOrderPurchaseIndicator = $this->pluginHook->getReOrderPurchaseIndicator($quote, []);

        if ($deliveryEmailAddress) {
            $riskIndicator->setDeliveryEmailAddress($deliveryEmailAddress);
        }

        if ($deliveryTimeFrameIndicator) {
            $riskIndicator->setDeliveryTimeFrameIndicator($deliveryTimeFrameIndicator);
        }

        if ($preOrderDate) {
            $riskIndicator->setPreOrderDate($preOrderDate);
        }

        if ($preOrderPurchaseIndicator) {
            $riskIndicator->setPreOrderPurchaseIndicator($preOrderPurchaseIndicator);
        }

        if ($shipIndicator) {
            $riskIndicator->setShipIndicator($shipIndicator);
        }

        if ($reOrderPurchaseIndicator) {
            $riskIndicator->setReOrderPurchaseIndicator($reOrderPurchaseIndicator);
        }

        $riskIndicator->setGiftCardPurchase($isGiftCardPurchase);

        return $riskIndicator;
    }
}
