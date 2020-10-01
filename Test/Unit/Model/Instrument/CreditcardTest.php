<?php


namespace SwedbankPay\Payments\Test\Unit\Model\Instrument;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use SwedbankPay\Api\Service\Creditcard\Resource\Request\PaymentPurchaseObject;
use SwedbankPay\Api\Service\Resource;
use SwedbankPay\Payments\Model\Instrument\AbstractInstrument;
use SwedbankPay\Payments\Model\Instrument\Creditcard;
use SwedbankPay\Payments\PluginHook;

class CreditcardTest extends AbstractTestCase
{
    /**
     * @var Creditcard
     */
    protected $creditcardInstrument;

    /**
     * @var PluginHook|MockObject
     */
    protected $pluginHook;

    public function setUp()
    {
        parent::setUp();

        $this->pluginHook = $this->createMock(PluginHook::class);

        $this->creditcardInstrument = new Creditcard(
            $this->checkoutSession,
            $this->storeManager,
            $this->urlInterface,
            $this->headerLogo,
            $this->apiClient,
            $this->clientConfig,
            $this->scopeConfig,
            $this->resolver,
            $this->subresourceFactory,
            $this->pluginHook
        );
    }

    public function testClassExtendsAbstractInstrument()
    {
        $this->assertInstanceOf(Creditcard::class, $this->creditcardInstrument);
        $this->assertInstanceOf(AbstractInstrument::class, $this->creditcardInstrument);
    }

    public function testInstrumentName()
    {
        $this->assertEquals('creditcard', $this->creditcardInstrument->getInstrumentName());
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testCreatePaymentObject()
    {
        $grandTotal = 99.50;

        $this->billingAddress->method('getTaxAmount')->will($this->returnValue(25));
        $this->shippingAddress->method('getTaxAmount')->will($this->returnValue(0));

        $this->quote->expects($this->once())->method('isVirtual')->will($this->returnValue(false));
        $this->quote->expects($this->once())->method('getGrandTotal')->will($this->returnValue($grandTotal));
        $this->quote->expects($this->any())->method('getBillingAddress')->willReturn($this->billingAddress);
        $this->quote->expects($this->any())->method('getShippingAddress')->willReturn($this->shippingAddress);

        $this->checkoutSession->expects($this->any())->method('getQuote')->willReturn($this->quote);

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('getCurrentCurrencyCode')->willReturn('SEK');

        $this->storeManager->expects($this->once())->method('getStore')->willReturn($store);

        /** @var PaymentPurchaseObject $paymentObject */
        $paymentObject = $this->creditcardInstrument->createPaymentObject();

        $this->assertInstanceOf(Resource::class, $paymentObject);
        $this->assertInstanceOf(PaymentPurchaseObject::class, $paymentObject);
        $this->assertEquals('Purchase', $paymentObject->getPayment()->getOperation());
        $this->assertEquals('Authorization', $paymentObject->getPayment()->getIntent());
    }
}
