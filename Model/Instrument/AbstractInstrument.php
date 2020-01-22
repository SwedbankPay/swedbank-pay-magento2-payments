<?php

namespace SwedbankPay\Payments\Model\Instrument;

use Magento\Braintree\Model\LocaleResolver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Header\Logo as HeaderLogo;
use PayEx\Api\Client\Client as ApiClient;
use PayEx\Api\Service\Creditcard\Resource\Request\PaymentPayeeInfo;
use PayEx\Api\Service\Creditcard\Resource\Request\PaymentUrl;
use PayEx\Api\Service\Payment\Resource\Collection\Item\PriceItem;
use PayEx\Api\Service\Payment\Resource\Collection\PricesCollection;
use PayEx\Api\Service\Swish\Resource\Request\PaymentSwish as PaymentSwishObject;
use SwedbankPay\Core\Helper\Config as ClientConfig;
use SwedbankPay\Payments\Helper\Factory\SubresourceFactory;
use SwedbankPay\Payments\Model\Instrument\Data\InstrumentInterface;

/**
 * Class AbstractInstrument
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
abstract class AbstractInstrument implements InstrumentInterface
{
    /**
     * @var string
     */
    protected $instrument;

    /**
     * @var string
     */
    protected $instrumentPrettyName;

    /**
     * @var string
     */
    protected $jsObjectName;

    /**
     * @var string
     */
    protected $paymentOperation = 'Purchase';

    /**
     * @var string
     */
    protected $hostedUriRel = 'view-authorization';

    /**
     * @var string
     */
    protected $redirectUriRel = 'redirect-authorization';

    /**
     * @var string|null
     */
    protected $allowedCurrencies;

    /**
     * @var CheckoutSession $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * @var HeaderLogo
     */
    protected $headerLogo;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var ClientConfig $clientConfig
     */
    protected $clientConfig;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Resolver $localeResolver
     */
    protected $localeResolver;

    /**
     * @var SubresourceFactory
     */
    protected $subresourceFactory;

    /**
     * PaymentInstrument constructor.
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlInterface
     * @param HeaderLogo $headerLogo
     * @param ApiClient $apiClient
     * @param ClientConfig $clientConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param LocaleResolver $localeResolver
     * @param SubresourceFactory $subresourceFactory
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
        SubresourceFactory $subresourceFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->urlInterface = $urlInterface;
        $this->headerLogo = $headerLogo;
        $this->apiClient = $apiClient;
        $this->clientConfig = $clientConfig;
        $this->scopeConfig = $scopeConfig;
        $this->localeResolver = $localeResolver;
        $this->subresourceFactory = $subresourceFactory;
    }

    /**
     * @param $instrument
     */
    public function setInstrument($instrument)
    {
        $this->instrument = $instrument;
    }

    /**
     * @return string|null
     */
    public function getInstrumentName()
    {
        return $this->instrument;
    }

    /**
     * @return string|null
     */
    public function getInstrumentPrettyName()
    {
        if ($this->instrumentPrettyName) {
            return $this->instrumentPrettyName;
        }

        return ucfirst($this->instrument);
    }

    /**
     * @return string|null
     */
    public function getJsObjectName()
    {
        if ($this->jsObjectName) {
            return $this->jsObjectName;
        }

        return $this->getInstrumentName();
    }

    /**
     * @return string|null
     */
    public function getPaymentOperation()
    {
        return $this->paymentOperation;
    }

    /**
     * @return string|null
     */
    public function getHostedUriRel()
    {
        return $this->hostedUriRel;
    }

    /**
     * @return string|null
     */
    public function getRedirectUriRel()
    {
        return $this->redirectUriRel;
    }

    /**
     * @return string|null
     */
    public function getAllowedCurrencies()
    {
        return $this->allowedCurrencies;
    }

    public function createPricesCollectionObject()
    {
        /** @var Quote $quote */
        $quote = $this->checkoutSession->getQuote();

        $totalAmount = $quote->getGrandTotal() * 100;

        if ($quote->isVirtual()) {
            $vatAmount = $quote->getBillingAddress()->getTaxAmount() * 100;
        }

        if (!isset($vatAmount)) {
            $vatAmount = $quote->getShippingAddress()->getTaxAmount() * 100;
        }

        $price = new PriceItem();
        $price->setType(ucfirst($this->instrument))
            ->setAmount($totalAmount)
            ->setVatAmount($vatAmount);

        $prices = new PricesCollection();
        $prices->addItem($price);

        return $prices;
    }

    /**
     * @return string
     */
    protected function getStoreName()
    {
        $storeName = $this->scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );

        return $storeName;
    }

    /**
     * @return PaymentUrl
     * @SuppressWarnings(PHPMD.IfStatementAssignment)
     */
    public function createUrlObject()
    {
        $mageCompleteUrl = $this->urlInterface->getUrl('SwedbankPayPayments/Index/Complete');
        $mageCancelUrl = $this->urlInterface->getUrl('SwedbankPayPayments/Index/Cancel');
        $mageCallbackUrl = $this->urlInterface->getUrl('SwedbankPayPayments/Index/Callback');

        $hostUrls = [];
        $hostUrls[] = $this->urlInterface->getUrl('checkout');

        $urlData = $this->subresourceFactory->create($this->instrument, 'PaymentUrl');
        $urlData->setCompleteUrl($mageCompleteUrl)
            ->setCancelUrl($mageCancelUrl)
            ->setHostUrls($hostUrls)
            ->setCallbackUrl($mageCallbackUrl);

        if ($logoSrcUrl = $this->headerLogo->getLogoSrc()) {
            $urlData->setLogoUrl($logoSrcUrl);
        }

        return $urlData;
    }

    /**
     * @return PaymentPayeeInfo
     */
    public function createPayeeInfoObject()
    {
        $storeName = $this->getStoreName();

        $payeeInfo = $this->subresourceFactory->create($this->instrument, 'PaymentPayeeInfo');
        $payeeInfo->setPayeeId($this->clientConfig->getValue('payee_id'))
            ->setPayeeReference($this->generateRandomString(30))
            ->setPayeeName($storeName . ' Store');

        return $payeeInfo;
    }

    /**
     * @return PaymentSwishObject
     */
    public function createSwishObject()
    {
        $swish = new PaymentSwishObject();
        $swish->setEcomOnlyEnabled(true);

        return $swish;
    }

    /**
     * Generates a random string
     *
     * @param $length
     * @return bool|string
     */
    protected function generateRandomString($length)
    {
        return substr(str_shuffle(md5(time())), 0, $length);
    }

    /**
     * Gets language in SwedbankPay supported format, ex: nb-No
     *
     * @return string
     */
    protected function getLanguage()
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }

    /**
     * Gets country in SwedbankPay supported format, ex: Se|No|Fi
     *
     * @return string
     */
    protected function getCountry()
    {
        $locale = $this->localeResolver->getLocale();
        $countryCode = substr($locale, strpos($locale, '_') + 1);

        return ucfirst(strtolower($countryCode));
    }

    /**
     * Gets store currency code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getCurrency()
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $currency = $store->getCurrentCurrencyCode();

        return $currency;
    }


    /**
     * Gets if the store currency is supported by the instrument
     *
     * @param string|null $currency
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isCurrencySupported($currency = null)
    {
        if (!$this->getAllowedCurrencies()) {
            return true;
        }

        if (!$currency) {
            $currency = $this->getCurrency();
        }

        $allowedCurrencies = array_map('trim', explode(',', $this->getAllowedCurrencies()));

        if (in_array($currency, $allowedCurrencies)) {
            return true;
        }

        return false;
    }

    /**
     * Gets country in SwedbankPay invoice supported format,
     * ex: PayExFinancingSe|PayExFinancingNo|PayExFinancingFi
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getSupportedInvoiceType()
    {
        $currency = $this->getCurrency();

        $invoiceType = 'PayExFinancing';

        switch ($currency) {
            case 'SEK':
                $invoiceType .= 'Se';
                break;
            case 'NOK':
                $invoiceType .= 'No';
                break;
            case 'EUR':
                $invoiceType .= 'Fi';
                break;
        }

        return $invoiceType;
    }
}
