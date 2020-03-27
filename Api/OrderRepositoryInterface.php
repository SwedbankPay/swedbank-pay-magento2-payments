<?php

namespace SwedbankPay\Payments\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use SwedbankPay\Payments\Api\Data\OrderInterface;

interface OrderRepositoryInterface
{
    /**
     * @param int $entityId
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getById($entityId);

    /**
     * @param int $orderId
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByOrderId($orderId);

    /**
     * @param string $paymentOrderId
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByPaymentId($paymentOrderId);

    /**
     * @param string $paymentIdPath
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByPaymentIdPath($paymentIdPath);

    /**
     * @param OrderInterface $order
     * @return OrderInterface
     */
    public function save(OrderInterface $order);

    /**
     * @param OrderInterface $order
     * @return void
     */
    public function delete(OrderInterface $order);

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return \SwedbankPay\Payments\Api\Data\OrderSearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
