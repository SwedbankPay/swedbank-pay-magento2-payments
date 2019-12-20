<?php

namespace SwedbankPay\Payments\Helper;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote as MageQuote;
use Magento\Sales\Api\Data\OrderInterface as MagentoOrderInterface;

use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as PaymentOrderRepository;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface as PaymentQuoteRepository;

use SwedbankPay\Payments\Api\Data\OrderInterface as PaymentOrderInterface;
use SwedbankPay\Payments\Api\Data\QuoteInterface as PaymentQuoteInterface;

use SwedbankPay\Payments\Model\QuoteFactory;

class PaymentData
{
    /**
     * @var PaymentOrderRepository
     */
    protected $paymentOrderRepo;

    /**
     * @var PaymentQuoteRepository
     */
    protected $paymentQuoteRepo;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * PaymentData constructor.
     * @param PaymentOrderRepository $paymentOrderRepo
     * @param PaymentQuoteRepository $paymentQuoteRepo
     * @param QuoteFactory $quoteFactory
     * @param CheckoutSession $checkoutSession
     * @param Logger $logger
     */
    public function __construct(
        PaymentOrderRepository $paymentOrderRepo,
        PaymentQuoteRepository $paymentQuoteRepo,
        QuoteFactory $quoteFactory,
        CheckoutSession $checkoutSession,
        Logger $logger
    ) {
        $this->paymentOrderRepo = $paymentOrderRepo;
        $this->paymentQuoteRepo = $paymentQuoteRepo;
        $this->quoteFactory = $quoteFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * Get SwedbankPay payment data by order
     *
     * @param MagentoOrderInterface|int|string $order
     *
     * @return PaymentOrderInterface|PaymentQuoteInterface|false
     * @throws NoSuchEntityException
     */
    public function getByOrder($order)
    {
        if (is_numeric($order)) {
            return $this->paymentOrderRepo->getByOrderId($order);
        }

        if ($order instanceof MagentoOrderInterface) {
            if ($order->getEntityId()) {
                return $this->paymentOrderRepo->getByOrderId($order->getEntityId());
            }
            return $this->paymentQuoteRepo->getByQuoteId($order->getQuoteId());
        }

        $this->logger->error(
            sprintf("Unable to find a SwedbankPay payment matching order:\n%s", print_r($order, true))
        );

        throw new NoSuchEntityException(
            new Phrase(sprintf("Unable to find a SwedbankPay payment matching order %s", $order->getIncrementId()))
        );
    }

    /**
     * Get SwedbankPay payment data by payment id
     *
     * @param string $paymentId
     *
     * @return PaymentOrderInterface|PaymentQuoteInterface|false
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public function getByPaymentId($paymentId)
    {
        $paymentData = null;

        try {
            $paymentData = $this->paymentOrderRepo->getByPaymentId($paymentId);
        } catch (NoSuchEntityException $e) {
        }

        if ($paymentData instanceof PaymentOrderInterface) {
            return $paymentData;
        }

        try {
            $paymentData = $this->paymentQuoteRepo->getByPaymentId($paymentId);
        } catch (NoSuchEntityException $e) {
        }

        if ($paymentData instanceof PaymentQuoteInterface) {
            return $paymentData;
        }

        $errorMessage = sprintf("Unable to find a SwedbankPay payment matching Payment ID:\n%s", $paymentId);

        $this->logger->error(
            $errorMessage
        );

        throw new NoSuchEntityException(
            new Phrase($errorMessage)
        );
    }

    /**
     * @param PaymentOrderInterface|PaymentQuoteInterface $payment
     * @return bool
     */
    public function update($payment)
    {
        if ($payment instanceof PaymentOrderInterface) {
            $this->paymentOrderRepo->save($payment);
            return true;
        }

        if ($payment instanceof PaymentQuoteInterface) {
            $this->paymentQuoteRepo->save($payment);
            return true;
        }

        return false;
    }

    /**
     * @param string $command
     * @param string|int|float $amount
     * @param PaymentOrderInterface|PaymentQuoteInterface $order
     */
    public function updateRemainingAmounts($command, $amount, $order)
    {
        switch ($command) {
            case 'capture':
                $order->setRemainingCapturingAmount($order->getRemainingCapturingAmount() - ($amount * 100));
                $order->setRemainingCancellationAmount($order->getRemainingCapturingAmount());
                $order->setRemainingReversalAmount($order->getRemainingReversalAmount() + ($amount * 100));
                break;
            case 'cancel':
                $order->setRemainingCancellationAmount($order->getRemainingCancellationAmount() - ($amount * 100));
                $order->setRemainingCapturingAmount($order->getRemainingCancellationAmount());
                break;
            case 'refund':
                $order->setRemainingReversalAmount($order->getRemainingReversalAmount() - ($amount * 100));
                break;
        }

        $this->paymentOrderRepo->save($order);
    }

    /**
     * @param array $response
     * @param string $instrument
     */
    public function saveQuoteToDB($response, $instrument)
    {
        /** @var MageQuote $mageQuote */
        $mageQuote = $this->checkoutSession->getQuote();

        $paymentIdPath = $response['payment']['id'];
        $paymentId = $this->getSwedbankPayPaymentId($paymentIdPath);

        // Gets row from swedbank_pay_payments_quotes by matching quote_id
        // Otherwise, Creates a new record
        try {
            $quote = $this->paymentQuoteRepo->getByQuoteId($mageQuote->getId());

            // If is_updated field is 0,
            // Then it doesn't update
            if ($paymentId == $quote->getPaymentId()
                && $quote->getIsUpdated() != 0) {
                return;
            }
        } catch (NoSuchEntityException $e) {
            $quote = $this->quoteFactory->create();
        }

        $quote->setPaymentId($paymentId);
        $quote->setPaymentIdPath($paymentIdPath);
        $quote->setInstrument($instrument);
        $quote->setDescription($response['payment']['description']);
        $quote->setOperation($response['payment']['operation']);
        $quote->setState($response['payment']['state']);
        $quote->setCurrency($response['payment']['currency']);
        $quote->setAmount($response['payment']['amount']);
        $quote->setVatAmount(0);
        $quote->setRemainingCapturingAmount($response['payment']['amount']);
        $quote->setRemainingCancellationAmount($response['payment']['amount']);
        $quote->setRemainingReversalAmount(0);
        $quote->setPayerToken('');
        $quote->setQuoteId($mageQuote->getId());
        $quote->setIsUpdated(0);
        $quote->setCreatedAt($response['payment']['created']);
        $quote->setUpdatedAt($response['payment']['updated']);
        $this->paymentQuoteRepo->save($quote);

        $this->logger->debug(
            sprintf(
                "Saved quote to database with payment id path: \n %s",
                $paymentIdPath
            )
        );
    }

    /**
     * Extracts Id from SwedbankPay Payment Id, ex: 5adc265f-f87f-4313-577e-08d3dca1a26c
     *
     * @param $paymentId
     * @return string
     */
    public function getSwedbankPayPaymentId($paymentId)
    {
        $validUri = preg_match('|/psp/([^/]+)/payments/([^/]+)|', $paymentId, $paymentParams);

        if ($validUri) {
            return $paymentParams[2];
        }
        return null;
    }
}
