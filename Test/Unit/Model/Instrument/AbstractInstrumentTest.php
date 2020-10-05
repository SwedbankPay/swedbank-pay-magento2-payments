<?php


namespace SwedbankPay\Payments\Test\Unit\Model\Instrument;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use SwedbankPay\Api\Service\Payment\Resource\Collection\Item\PriceItem;
use SwedbankPay\Api\Service\Payment\Resource\Collection\PricesCollection;
use SwedbankPay\Payments\Model\Instrument\AbstractInstrument;

class AbstractInstrumentTest extends AbstractTestCase
{
    /**
     * @var AbstractInstrument
     */
    protected $abstractInstrument;

    public function setUp()
    {
        parent::setUp();

        $this->abstractInstrument = $this->getMockBuilder(AbstractInstrument::class)
            ->setConstructorArgs([
                $this->checkoutSession,
                $this->storeManager,
                $this->urlInterface,
                $this->headerLogo,
                $this->apiClient,
                $this->clientConfig,
                $this->scopeConfig,
                $this->resolver,
                $this->subresourceFactory
            ])
            ->getMockForAbstractClass();
    }

    public function testClassExtendsAbstractInstrument()
    {
        $this->assertInstanceOf(AbstractInstrument::class, $this->abstractInstrument);
    }

    public function testInstrumentName()
    {
        $this->assertNull($this->abstractInstrument->getInstrumentName());
    }

    public function testPaymentOperation()
    {
        $this->assertEquals('Purchase', $this->abstractInstrument->getPaymentOperation());
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testCreatePricesCollectionObjectWithNonVirtualQuote()
    {
        $isVirtual = false;
        $grandTotal = 99.50;

        $this->billingAddress->method('getTaxAmount')->will($this->returnValue(25));
        $this->shippingAddress->method('getTaxAmount')->will($this->returnValue(0));

        $this->quote->expects($this->once())->method('isVirtual')->willReturn($isVirtual);
        $this->quote->expects($this->once())->method('getGrandTotal')->willReturn($grandTotal);
        $this->quote->expects($this->never())->method('getBillingAddress')->willReturn($this->billingAddress);
        $this->quote->expects($this->once())->method('getShippingAddress')->willReturn($this->shippingAddress);

        $this->checkoutSession->expects($this->once())->method('getQuote')->willReturn($this->quote);

        $prices = $this->abstractInstrument->createPricesCollectionObject();

        /** @var PriceItem $price */
        $price = $prices->getItems()[0];

        $this->assertInstanceOf(PricesCollection::class, $prices);
        $this->assertEquals($grandTotal * 100, $price->getAmount());
        $this->assertEquals(0, $price->getVatAmount());
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testCreatePricesCollectionObjectWithVirtualQuote()
    {
        $isVirtual = true;
        $grandTotal = 99.50;

        $this->billingAddress->method('getTaxAmount')->will($this->returnValue(25));
        $this->shippingAddress->method('getTaxAmount')->will($this->returnValue(0));

        $this->quote->expects($this->once())->method('isVirtual')->willReturn($isVirtual);
        $this->quote->expects($this->once())->method('getGrandTotal')->willReturn($grandTotal);
        $this->quote->expects($this->once())->method('getBillingAddress')->willReturn($this->billingAddress);
        $this->quote->expects($this->never())->method('getShippingAddress')->willReturn($this->shippingAddress);

        $this->checkoutSession->expects($this->once())->method('getQuote')->willReturn($this->quote);

        $prices = $this->abstractInstrument->createPricesCollectionObject();

        /** @var PriceItem $price */
        $price = $prices->getItems()[0];

        $this->assertInstanceOf(PricesCollection::class, $prices);
        $this->assertEquals($grandTotal * 100, $price->getAmount());
        $this->assertEquals(25 * 100, $price->getVatAmount());
    }
}
