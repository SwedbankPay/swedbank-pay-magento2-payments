<?php


namespace SwedbankPay\Payments\Test\Unit\Helper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SwedbankPay\Api\Client\Exception;
use SwedbankPay\Api\Service\Data\ResponseInterface as ResponseServiceInterface;
use SwedbankPay\Api\Service\Payment\Resource\Response\Data\PaymentObjectInterface;
use SwedbankPay\Api\Service\Payment\Resource\Response\Data\PaymentResponseInterface;
use SwedbankPay\Api\Service\Payment\Resource\Response\Transactions as PaymentTransactions;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\Item\TransactionListItem;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Collection\TransactionListCollection;
use SwedbankPay\Api\Service\Payment\Transaction\Resource\Response\Data\TransactionInterface;
use SwedbankPay\Api\Service\Request;
use SwedbankPay\Core\Exception\ServiceException;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Model\Service as ClientRequestService;
use SwedbankPay\Payments\Helper\Factory\InstrumentFactory;
use SwedbankPay\Payments\Helper\Service;
use SwedbankPay\Payments\Model\Instrument\Collector\InstrumentCollector;
use SwedbankPay\Payments\Model\Instrument\Data\InstrumentInterface;

class ServiceTest extends TestCase
{
    /**
     * @var Service
     */
    protected $service;

    /**
     * @var ClientRequestService|MockObject
     */
    protected $requestService;

    /**
     * @var InstrumentCollector|MockObject
     */
    protected $instrumentCollector;

    /**
     * @var InstrumentFactory|MockObject
     */
    protected $instrumentFactory;

    /**
     * @var Logger|MockObject
     */
    protected $logger;

    public function setUp()
    {
        $this->requestService = $this->createMock(ClientRequestService::class);
        $this->instrumentCollector = $this->createMock(InstrumentCollector::class);
        $this->instrumentFactory = $this->createMock(InstrumentFactory::class);
        $this->logger = $this->createMock(Logger::class);

        $this->setUpInstruments();

        $this->service = new Service(
            $this->requestService,
            $this->instrumentCollector,
            $this->instrumentFactory,
            $this->logger
        );
    }

    public function setUpInstruments()
    {
        /** @var InstrumentInterface[] $instruments */
        $instruments = [];
        $instrumentNames = ['creditcard', 'swish', 'vipps', 'invoice'];

        foreach ($instrumentNames as $instrumentName) {
            $instrument = $this->createMock(InstrumentInterface::class);
            $instrument->method('getInstrumentName')->willReturn($instrumentName);
        }

        $this->instrumentCollector->method('getInstruments')->willReturn($instruments);
    }

    /**
     * @dataProvider paymentDataProvider
     * @param string $instrument
     * @param string $paymentId
     * @param array $expands
     * @throws Exception
     * @throws ServiceException
     */
    public function testCurrentPayment($instrument, $paymentId, $expands)
    {
        $this->assertNull($this->service->getPaymentResponseResource());

        $currentPayment = $this->createMock(PaymentObjectInterface::class);
        $currentPaymentResponse = $this->createMock(ResponseServiceInterface::class);
        $currentPaymentResponse->method('getResponseResource')->willReturn($currentPayment);

        $serviceRequest = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['setPaymentId', 'setExpands', 'send'])
            ->getMock();
        $serviceRequest->expects($this->once())->method('setPaymentId');
        $serviceRequest->expects($expands ? $this->once() : $this->never())->method('setExpands');
        $serviceRequest->expects($this->once())->method('send')->willReturn($currentPaymentResponse);

        $this->requestService
            ->expects($this->once())
            ->method('init')
            ->with(ucfirst($instrument), 'GetPayment')
            ->willReturn($serviceRequest);

        $this->logger->expects($this->once())->method('debug');

        $this->service->currentPayment($instrument, $paymentId, $expands);

        $this->assertInstanceOf(PaymentObjectInterface::class, $this->service->getPaymentResponseResource());
    }

    /**
     * @dataProvider paymentTransactionDataProvider
     * @param string $instrument
     * @param string $paymentId
     * @param array $expands
     * @param string $transactionNumber
     * @throws Exception
     * @throws ServiceException
     */
    public function testGetTransaction($instrument, $paymentId, $expands, $transactionNumber)
    {
        $transactionItem = $this->createMock(TransactionListItem::class);
        $transactionItem->method('getNumber')->willReturn($transactionNumber);

        $transactionListCollection = $this->createMock(TransactionListCollection::class);
        $transactionListCollection->method('getItems')->willReturn([$transactionItem]);

        $paymentTransactions = $this->createMock(PaymentTransactions::class);
        $paymentTransactions->method('getTransactionList')->willReturn($transactionListCollection);

        $paymentResponse = $this->createMock(PaymentResponseInterface::class);
        $paymentResponse->method('getTransactions')->willReturn($paymentTransactions);

        $currentPayment = $this->createMock(PaymentObjectInterface::class);
        $currentPayment->method('getPayment')->willReturn($paymentResponse);

        $currentPaymentResponse = $this->createMock(ResponseServiceInterface::class);
        $currentPaymentResponse->method('getResponseResource')->willReturn($currentPayment);

        $serviceRequest = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['setPaymentId', 'setExpands', 'send'])
            ->getMock();
        $serviceRequest->expects($this->once())->method('setPaymentId');
        $serviceRequest->expects($expands ? $this->once() : $this->never())->method('setExpands');
        $serviceRequest->expects($this->once())->method('send')->willReturn($currentPaymentResponse);

        $this->requestService
            ->expects($this->once())
            ->method('init')
            ->with(ucfirst($instrument), 'GetPayment')
            ->willReturn($serviceRequest);

        $this->logger->expects($this->once())->method('debug');

        $transaction = $this->service
            ->currentPayment($instrument, $paymentId, $expands)
            ->getTransaction($transactionNumber);

        $this->assertInstanceOf(TransactionInterface::class, $transaction);
    }

    public function testGetTransactionReturnsNull()
    {
        $this->assertNull($this->service->getTransaction(''));
    }

    /**
     * @return array
     */
    public function paymentDataProvider()
    {
        return [
            'test current creditcard payment with transactions' => [
                'creditcard',
                '/psp/creditcard/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                ['transactions']
            ],
            'test current swish payment with transactions' => [
                'swish',
                '/psp/swish/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                ['transactions']
            ],
            'test current vipps payment without transactions' => [
                'vipps',
                '/psp/vipps/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                []
            ],
            'test current invoice payment without transactions' => [
                'invoice',
                '/psp/invoice/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                []
            ]
        ];
    }

    /**
     * @return array
     */
    public function paymentTransactionDataProvider()
    {
        return [
            'test specific transaction for current creditcard payment' => [
                'creditcard',
                '/psp/creditcard/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                ['transactions'],
                '40106667417'
            ],
            'test specific transaction for current swish payment' => [
                'swish',
                '/psp/swish/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                ['transactions'],
                '40106667418'
            ],
            'test specific transaction for current vipps payment' => [
                'vipps',
                '/psp/vipps/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                ['transactions'],
                '40106667419'
            ],
            'test specific transaction for current invoice payment' => [
                'invoice',
                '/psp/invoice/payments/0dac01c4-3c2e-4f32-31d2-08d8df0d48aa',
                ['transactions'],
                '40106667420'
            ]
        ];
    }
}
