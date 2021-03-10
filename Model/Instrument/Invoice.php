<?php

namespace SwedbankPay\Payments\Model\Instrument;

use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order as MageOrder;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Theme\Block\Html\Header\Logo as HeaderLogo;
use SwedbankPay\Api\Client\Client as ApiClient;
use SwedbankPay\Api\Service\Invoice\Transaction\Resource\Request\Cancellation;
use SwedbankPay\Api\Service\Invoice\Transaction\Resource\Request\Capture;
use SwedbankPay\Api\Service\Invoice\Transaction\Resource\Request\Reversal;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\Item\ItemDescriptionListItem;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\Item\VatSummaryItem;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\ItemDescriptionListCollection;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\VatSummaryCollection;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Request\TransactionObject;
use SwedbankPay\Api\Service\Resource\Request as RequestResource;
use SwedbankPay\Core\Helper\Config as ClientConfig;
use SwedbankPay\Payments\Api\Data\OrderInterface as SwedbankPayOrderInterface;
use SwedbankPay\Payments\Helper\Factory\SubresourceFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Invoice extends AbstractInstrument
{
    protected $instrument = 'invoice';
    protected $paymentOperation = 'CreateInvoice';
    protected $hostedUriRel = 'view-authorization';
    protected $redirectUriRel = 'redirect-authorization';
    protected $allowedCurrencies = 'SEK,NOK,EUR';

    /**
     * @var MageOrderRepository
     */
    protected $mageOrderRepository;

    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var Calculation
     */
    protected $calculator;

    /**
     * Invoice constructor.
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlInterface
     * @param HeaderLogo $headerLogo
     * @param ApiClient $apiClient
     * @param ClientConfig $clientConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param LocaleResolver $localeResolver
     * @param SubresourceFactory $subresourceFactory
     * @param MageOrderRepository $mageOrderRepository
     * @param GroupRepositoryInterface $groupRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param Calculation $calculator
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
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
        MageOrderRepository $mageOrderRepository,
        GroupRepositoryInterface $groupRepository,
        PriceCurrencyInterface $priceCurrency,
        Calculation $calculator
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

        $this->mageOrderRepository = $mageOrderRepository;
        $this->groupRepository = $groupRepository;
        $this->priceCurrency = $priceCurrency;
        $this->calculator = $calculator;
    }

    /**
     * @return RequestResource
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function createPaymentObject()
    {
        $payment = $this->subresourceFactory->create($this->instrument, 'Payment');
        $payment->setOperation('FinancingConsumer')
            ->setIntent('Authorization')
            ->setCurrency($this->getCurrency())
            ->setDescription($this->getStoreName() . ' ' . __('Purchase'))
            ->setUserAgent($this->apiClient->getUserAgent())
            ->setLanguage($this->getLanguage())
            ->setUrls($this->createUrlObject())
            ->setPayeeInfo($this->createPayeeInfoObject())
            ->setPrices($this->createPricesCollectionObject());

        $invoice = $this->subresourceFactory->create($this->instrument, 'Invoice');
        $invoice->setInvoiceType($this->getSupportedInvoiceType());

        $paymentObject = $this->subresourceFactory->create($this->instrument, 'InvoicePaymentObject');
        $paymentObject->setPayment($payment);
        $paymentObject->setInvoice($invoice);

        return $paymentObject;
    }

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @param MageOrder $order
     * @return TransactionObject
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createCaptureTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder, MageOrder $order = null)
    {
        $transactionCapture = new Capture();
        $transactionCapture->setActivity('FinancingConsumer');
        $transactionCapture->setAmount($swedbankPayOrder->getAmount());
        $transactionCapture->setVatAmount($swedbankPayOrder->getVatAmount());
        $transactionCapture->setDescription('Capturing ' . $swedbankPayOrder->getAmount());
        $transactionCapture->setPayeeReference($this->generateRandomString(30));

        $transactionCapture->setItemDescriptions($this->createItemDescriptionCollectionObject($order));
        $transactionCapture->setVatSummary($this->createVatSummaryCollectionObject($order));

        $transactionObject = new TransactionObject();
        $transactionObject->setTransaction($transactionCapture);

        return $transactionObject;
    }

    /**
     * @param SwedbankPayOrderInterface $swedbankPayOrder
     * @return TransactionObject
     */
    public function createRefundTransactionObject(SwedbankPayOrderInterface $swedbankPayOrder)
    {
        $transaction = new Reversal();
        $transaction->setActivity('FinancingConsumer');
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
        $transaction = new Cancellation();
        $transaction->setActivity('FinancingConsumer');
        $transaction->setDescription('Cancelling ' . $swedbankPayOrder->getAmount());
        $transaction->setPayeeReference($this->generateRandomString(30));

        $transactionObject = new TransactionObject();
        $transactionObject->setTransaction($transaction);

        return $transactionObject;
    }

    /**
     * @param MageOrder $order
     * @return ItemDescriptionListCollection
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    protected function createItemDescriptionCollectionObject(MageOrder $order = null)
    {
        $itemDescriptions = new ItemDescriptionListCollection();

        $invoices = $order->getInvoiceCollection();

        /**
         * The latest invoice will contain only the selected items(and quantities) for the (partial) capture
         * @var \Magento\Sales\Model\Order\Invoice $invoice
         */
        $invoice = $invoices->getLastItem();

        /** @var InvoiceItem $item */
        foreach ($invoice->getItemsCollection() as $item) {
            $itemTotal = ($item->getBaseRowTotalInclTax() - $item->getBaseDiscountAmount()) * 100;

            $description = (string)$item->getName();
            if ($item->getBaseDiscountAmount()) {
                $formattedDiscountAmount = $this->priceCurrency->format(
                    $item->getBaseDiscountAmount(),
                    false,
                    PriceCurrencyInterface::DEFAULT_PRECISION,
                    $order->getStoreId()
                );
                $description .= ' - ' . __('Including') . ' ' . $formattedDiscountAmount . ' ' . __('discount');
            }

            $descriptionItem = new ItemDescriptionListItem();
            $descriptionItem->setAmount($itemTotal)
                ->setDescription($description);
            $itemDescriptions->addItem($descriptionItem);
        }

        if (!$order->getIsVirtual() && $order->getBaseShippingInclTax() > 0) {
            $shippingTotal = ($order->getBaseShippingInclTax() - $order->getBaseShippingDiscountAmount()) * 100;

            $description = (string)$order->getShippingDescription();
            if ($order->getBaseShippingDiscountAmount()) {
                $formattedDiscountAmount = $this->priceCurrency->format(
                    $order->getBaseShippingDiscountAmount(),
                    false,
                    PriceCurrencyInterface::DEFAULT_PRECISION,
                    $order->getStoreId()
                );
                $description .= ' - ' . __('Including') . ' ' . $formattedDiscountAmount . ' ' . __('discount');
            }

            $descriptionItem = new ItemDescriptionListItem();
            $descriptionItem->setAmount($shippingTotal)
                ->setDescription($description);
            $itemDescriptions->addItem($descriptionItem);
        }

        return $itemDescriptions;
    }

    /**
     * @param MageOrder $order
     * @return VatSummaryCollection
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    protected function createVatSummaryCollectionObject(MageOrder $order)
    {
        $vatSummaryRateAmounts = [];

        $invoices = $order->getInvoiceCollection();

        /**
         * The latest invoice will contain only the selected items(and quantities) for the (partial) capture
         * @var \Magento\Sales\Model\Order\Invoice $invoice
         */
        $invoice = $invoices->getLastItem();

        /** @var InvoiceItem $item */
        foreach ($invoice->getItemsCollection() as $item) {
            $itemTotal = ($item->getBaseRowTotalInclTax() - $item->getBaseDiscountAmount()) * 100;

            $rate = (int)$item->getOrderItem()->getTaxPercent();

            if (!isset($vatSummaryRateAmounts[$rate])) {
                $vatSummaryRateAmounts[$rate] = ['amount' => 0, 'vat_amount' => 0];
            }

            $vatSummaryRateAmounts[$rate]['amount'] += $itemTotal;
            $vatSummaryRateAmounts[$rate]['vat_amount'] += $item->getBaseTaxAmount() * 100;
        }

        if (!$order->getIsVirtual() && $order->getBaseShippingInclTax() > 0) {
            $shippingTotal = ($order->getBaseShippingInclTax() - $order->getBaseShippingDiscountAmount()) * 100;

            $rate = (int)$this->getTaxRate($order);

            if (!isset($vatSummaryRateAmounts[$rate])) {
                $vatSummaryRateAmounts[$rate] = ['amount' => 0, 'vat_amount' => 0];
            }

            $vatSummaryRateAmounts[$rate]['amount'] += $shippingTotal;
            $vatSummaryRateAmounts[$rate]['vat_amount'] += $order->getBaseShippingTaxAmount() * 100;
        }

        $vatSummaries = new VatSummaryCollection();

        foreach ($vatSummaryRateAmounts as $rate => $amounts) {
            $vatSummary = new VatSummaryItem();
            $vatSummary->setAmount($amounts['amount'])
                ->setVatAmount($amounts['vat_amount'])
                ->setVatPercent($rate);
            $vatSummaries->addItem($vatSummary);
        }

        return $vatSummaries;
    }

    /**
     * Getting back the tax rate
     *
     * @param MageOrder $order
     * @return float
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getTaxRate(MageOrder $order)
    {
        $store = $order->getStore();
        $taxClassId = null;

        $groupId = $order->getCustomerGroupId();
        if ($groupId !== null) {
            $taxClassId = $this->groupRepository->getById($groupId)->getTaxClassId();
        }

        /** @var DataObject|object $request */
        $request = $this->calculator->getRateRequest(
            $order->getShippingAddress(),
            $order->getBillingAddress(),
            $taxClassId,
            $store
        );

        $taxRateId = $this->scopeConfig->getValue(
            TaxConfig::CONFIG_XML_PATH_SHIPPING_TAX_CLASS,
            ScopeInterface::SCOPE_STORES,
            $store
        );

        return $this->calculator->getRate($request->setProductClassId($taxRateId));
    }
}
