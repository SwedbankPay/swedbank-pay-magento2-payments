<?php


namespace SwedbankPay\Payments\Test\Unit\Model\Instrument;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Header\Logo as HeaderLogo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SwedbankPay\Api\Client\Client as ApiClient;
use SwedbankPay\Core\Helper\Config as ClientConfig;
use SwedbankPay\Payments\Helper\Factory\SubresourceFactory;

abstract class AbstractTestCase extends TestCase
{
    /**
     * @var Quote|MockObject
     */
    protected $quote;

    /**
     * @var Address|MockObject
     */
    protected $billingAddress;

    /**
     * @var Address|MockObject
     */
    protected $shippingAddress;

    /**
     * @var CheckoutSession|MockObject
     */
    protected $checkoutSession;

    /**
     * @var StoreManagerInterface|MockObject
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
     * @var ClientConfig|MockObject
     */
    protected $clientConfig;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ResolverInterface|MockObject
     */
    protected $resolver;

    /**
     * @var SubresourceFactory|MockObject
     */
    protected $subresourceFactory;

    public function setUp()
    {
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getGrandTotal', 'getBillingAddress', 'getShippingAddress', 'isVirtual', 'getAllVisibleItems'
            ])
            ->getMock();

        $this->billingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->setMethods(['getName', 'getTaxAmount', 'getCountryModel'])
            ->getMock();

        $this->shippingAddress = clone $this->billingAddress;

        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->urlInterface = $this->createMock(UrlInterface::class);
        $this->headerLogo = $this->createMock(HeaderLogo::class);
        $this->apiClient = $this->createMock(ApiClient::class);
        $this->clientConfig = $this->createMock(ClientConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->resolver = $this->createMock(ResolverInterface::class);

        $this->subresourceFactory = new SubresourceFactory();
    }

    abstract public function testClassExtendsAbstractInstrument();

    abstract public function testInstrumentName();
}
