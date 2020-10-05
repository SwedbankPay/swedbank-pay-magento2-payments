<?php


namespace SwedbankPay\Payments\Test\Unit\Model\Instrument;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Request\TransactionObject;
use SwedbankPay\Api\Service\Resource\Request as RequestResource;
use SwedbankPay\Api\Service\Swish\Resource\Request\SwishPaymentObject;
use SwedbankPay\Payments\Api\Data\OrderInterface as SwedbankPayOrderInterface;
use SwedbankPay\Payments\Model\Instrument\AbstractInstrument;
use SwedbankPay\Payments\Model\Instrument\Swish;

class SwishTest extends AbstractTestCase
{
    /**
     * @var Swish
     */
    protected $swishInstrument;

    public function setUp()
    {
        parent::setUp();

        $this->swishInstrument = new Swish(
            $this->checkoutSession,
            $this->storeManager,
            $this->urlInterface,
            $this->headerLogo,
            $this->apiClient,
            $this->clientConfig,
            $this->scopeConfig,
            $this->resolver,
            $this->subresourceFactory
        );
    }

    public function testClassExtendsAbstractInstrument()
    {
        $this->assertInstanceOf(Swish::class, $this->swishInstrument);
        $this->assertInstanceOf(AbstractInstrument::class, $this->swishInstrument);
    }

    public function testInstrumentName()
    {
        $this->assertEquals('swish', $this->swishInstrument->getInstrumentName());
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

        $this->quote->expects($this->once())->method('isVirtual')->willReturn(false);
        $this->quote->expects($this->once())->method('getGrandTotal')->willReturn($grandTotal);
        $this->quote->expects($this->any())->method('getBillingAddress')->willReturn($this->billingAddress);
        $this->quote->expects($this->any())->method('getShippingAddress')->willReturn($this->shippingAddress);

        $this->checkoutSession->expects($this->once())->method('getQuote')->willReturn($this->quote);

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('getCurrentCurrencyCode')->willReturn('SEK');

        $this->storeManager->expects($this->once())->method('getStore')->willReturn($store);

        /** @var SwishPaymentObject $paymentObject */
        $paymentObject = $this->swishInstrument->createPaymentObject();

        $this->assertInstanceOf(RequestResource::class, $paymentObject);
        $this->assertInstanceOf(SwishPaymentObject::class, $paymentObject);
        $this->assertEquals('Purchase', $paymentObject->getPayment()->getOperation());
        $this->assertEquals('Sale', $paymentObject->getPayment()->getIntent());
    }

    public function testCreateCaptureTransactionObject()
    {
        /** @var SwedbankPayOrderInterface|MockObject $swedbankPayOrder */
        $swedbankPayOrder = $this->createMock(SwedbankPayOrderInterface::class);

        $transactionObject = $this->swishInstrument->createCaptureTransactionObject($swedbankPayOrder);

        $this->assertNull($transactionObject);
        $this->assertNotInstanceOf(TransactionObject::class, $transactionObject);
    }

    public function testCreateRefundTransactionObject()
    {
        /** @var SwedbankPayOrderInterface|MockObject $swedbankPayOrder */
        $swedbankPayOrder = $this->createMock(SwedbankPayOrderInterface::class);

        $transactionObject = $this->swishInstrument->createRefundTransactionObject($swedbankPayOrder);

        $this->assertNotNull($transactionObject);
        $this->assertInstanceOf(TransactionObject::class, $transactionObject);
    }

    public function testCreateCancelTransactionObject()
    {
        /** @var SwedbankPayOrderInterface|MockObject $swedbankPayOrder */
        $swedbankPayOrder = $this->createMock(SwedbankPayOrderInterface::class);

        $transactionObject = $this->swishInstrument->createCancelTransactionObject($swedbankPayOrder);

        $this->assertNull($transactionObject);
        $this->assertNotInstanceOf(TransactionObject::class, $transactionObject);
    }
}
