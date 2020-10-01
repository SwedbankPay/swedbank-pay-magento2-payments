<?php


namespace SwedbankPay\Payments\Test\Unit\Gateway\Command;

use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SwedbankPay\Core\Exception\SwedbankPayException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as RequestService;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as SwedbankPayOrderRepository;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface as SwedbankPayQuoteRepository;
use SwedbankPay\Payments\Gateway\Command\AbstractCommand;
use SwedbankPay\Payments\Model\Order as SwedbankPayOrder;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class AbstractCommandTest extends TestCase
{
    /**
     * @var AbstractCommand
     */
    protected $abstractCommand;

    /**
     * @var SwedbankPayOrderRepository|MockObject
     */
    protected $swedbankPayOrderRepository;

    /**
     * @var SwedbankPayQuoteRepository|MockObject
     */
    protected $swedbankPayQuoteRepository;

    /**
     * @var RequestService|MockObject
     */
    protected $requestService;

    /**
     * @var QuoteRepository|MockObject
     */
    protected $quoteRepository;

    /**
     * @var OrderRepository|MockObject
     */
    protected $orderRepository;

    /**
     * @var Logger|MockObject
     */
    protected $logger;

    public function setUp()
    {
        $this->swedbankPayOrderRepository = $this->createMock(SwedbankPayOrderRepository::class);
        $this->swedbankPayQuoteRepository = $this->createMock(SwedbankPayQuoteRepository::class);
        $this->requestService = $this->createMock(RequestService::class);
        $this->quoteRepository = $this->createMock(QuoteRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->logger = $this->createMock(Logger::class);

        $this->abstractCommand = $this->getMockBuilder(AbstractCommand::class)
            ->setConstructorArgs([
                $this->swedbankPayOrderRepository,
                $this->swedbankPayQuoteRepository,
                $this->requestService,
                $this->quoteRepository,
                $this->orderRepository,
                $this->logger
            ])
            ->getMockForAbstractClass();
    }

    /**
     * @param float $amount
     * @param int $remainingAmount
     * @dataProvider amountProvider
     * @throws SwedbankPayException
     */
    public function testCheckRemainingAmount($amount, $remainingAmount)
    {
        /** @var Order|MockObject $order */
        $order = $this->createMock(Order::class);

        /** @var SwedbankPayOrder|MockObject $swedbankPayOrder */
        $swedbankPayOrder = $this->getMockBuilder(SwedbankPayOrder::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRemainingCaptureAmount'])
            ->getMock();

        $swedbankPayOrder
            ->expects($this->once())
            ->method('getRemainingCaptureAmount')
            ->willReturn($remainingAmount);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->abstractCommand->checkRemainingAmount('capture', $amount, $order, $swedbankPayOrder);
    }

    /**
     * @param float $amount
     * @param int $remainingAmount
     * @dataProvider incorrectAmountProvider
     * @throws SwedbankPayException
     */
    public function testCheckRemainingAmountThrowsException($amount, $remainingAmount)
    {
        /** @var Order|MockObject $order */
        $order = $this->createMock(Order::class);

        /** @var SwedbankPayOrder|MockObject $swedbankPayOrder */
        $swedbankPayOrder = $this->getMockBuilder(SwedbankPayOrder::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRemainingCaptureAmount'])
            ->getMock();

        $swedbankPayOrder
            ->expects($this->once())
            ->method('getRemainingCaptureAmount')
            ->willReturn($remainingAmount);

        $this->logger
            ->expects($this->once())
            ->method('error');

        $this->expectException(SwedbankPayException::class);
        $this->abstractCommand->checkRemainingAmount('capture', $amount, $order, $swedbankPayOrder);
    }

    /**
     * @return array
     */
    public function amountProvider()
    {
        return [
            [77.04, 7704],
            [543.83, 54383],
            [10.30, 1030]
        ];
    }

    /**
     * @return array
     */
    public function incorrectAmountProvider()
    {
        return [
            [77.04, 7703],
            [543.83, 54380],
            [10.30, 1029]
        ];
    }
}
