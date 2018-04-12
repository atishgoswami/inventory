<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\CatalogInventory\StockManagement;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\StockManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalog\Model\GetSkusByProductIdsInterface;
use Magento\InventoryReservations\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Api\AppendReservationsInterface;
use Magento\InventorySales\Model\StockByWebsiteIdResolver;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface;
use Magento\InventorySalesApi\Api\RegisterSalesEventInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

/**
 * Class provides around Plugin on \Magento\CatalogInventory\Model\StockManagement::registerProductsSale
 */
class ProcessRegisterProductsSalePlugin
{
    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    /**
     * @var StockByWebsiteIdResolver
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var ReservationBuilderInterface
     */
    private $reservationBuilder;

    /**
     * @var AppendReservationsInterface
     */
    private $appendReservations;

    /**
     * @var IsProductSalableForRequestedQtyInterface
     */
    private $isProductSalableForRequestedQty;

    /**
     * @var RegisterSalesEventInterface
     */
    private $registerSalesEvent;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelFactory;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    public function __construct(
        GetSkusByProductIdsInterface $getSkusByProductIds,
        StockByWebsiteIdResolver $stockByWebsiteIdResolver,
        ReservationBuilderInterface $reservationBuilder,
        AppendReservationsInterface $appendReservations,
        IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQty,
        RegisterSalesEventInterface $registerSalesEvent,
        SalesChannelInterfaceFactory $salesChannelFactory,
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->reservationBuilder = $reservationBuilder;
        $this->appendReservations = $appendReservations;
        $this->isProductSalableForRequestedQty = $isProductSalableForRequestedQty;
        $this->registerSalesEvent = $registerSalesEvent;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * @param StockManagement $subject
     * @param callable $proceed
     * @param float[] $items
     * @param int|null $websiteId
     * @return StockItemInterface[]
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundRegisterProductsSale(
        StockManagement $subject,
        callable $proceed,
        $items,
        $websiteId = null,
        $quoteId = null
    ) {
        if (empty($items)) {
            return [];
        }
        if (null === $websiteId) {
            throw new LocalizedException(__('$websiteId parameter is required'));
        }
        if (null === $quoteId) {
            //TODO: Do we need to throw exception?
        }

        // TODO use array functions to initialize $itemsBySku
        $productSkus = $this->getSkusByProductIds->execute(array_keys($items));
        $itemsBySku = [];
        foreach ($productSkus as $productId => $sku) {
            $itemsBySku[$sku] = $items[$productId];
        }

        $websiteCode = $this->websiteRepository->getById($websiteId)->getCode();
        $salesChannel = $this->salesChannelFactory->create();
        $salesChannel->setCode($websiteCode);
        $salesChannel->setType(SalesChannelInterface::TYPE_WEBSITE);

        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::TYPE_QUOTE,
            'objectId' => $quoteId
        ]);

        $this->registerSalesEvent->execute($itemsBySku, $salesChannel, $salesEvent);

        return [];
    }
}
