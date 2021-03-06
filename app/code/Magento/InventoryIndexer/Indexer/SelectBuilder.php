<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Indexer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Inventory\Model\ResourceModel\IsSalableCondition\GetIsSalableConditionInterface;
use Magento\Inventory\Model\ResourceModel\Source as SourceResourceModel;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;
use Magento\Inventory\Model\ResourceModel\StockSourceLink as StockSourceLinkResourceModel;
use Magento\Inventory\Model\StockSourceLink;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * Select builder data provider
 */
class SelectBuilder
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var GetIsSalableConditionInterface
     */
    private $getIsSalableCondition;

    /**
     * @param ResourceConnection $resourceConnection
     * @param GetIsSalableConditionInterface $getIsSalableCondition
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        GetIsSalableConditionInterface $getIsSalableCondition
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->getIsSalableCondition = $getIsSalableCondition;
    }

    /**
     * Prepare select.
     *
     * @param int $stockId
     * @return Select
     */
    public function execute($stockId): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $sourceItemTable = $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM);

        $quantityExpression = (string)$this->resourceConnection->getConnection()->getCheckSql(
            'source_item.' . SourceItemInterface::STATUS . ' = ' . SourceItemInterface::STATUS_OUT_OF_STOCK,
            0,
            SourceItemInterface::QUANTITY
        );
        $sourceCodes = $this->getSourceCodes($stockId);

        $select = $connection->select();
        $select->joinInner(
            ['product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
            'product_entity.sku = source_item.sku',
            []
        )->joinInner(
            ['legacy_stock_item' => $this->resourceConnection->getTableName('cataloginventory_stock_item')],
            'product_entity.entity_id = legacy_stock_item.product_id',
            []
        );

        $select->from(
            ['source_item' => $sourceItemTable],
            [
                SourceItemInterface::SKU,
                IndexStructure::QUANTITY => 'SUM(' . $quantityExpression . ')',
                IndexStructure::IS_SALABLE => $this->getIsSalableCondition->execute($select),
            ]
        )
            ->where('source_item.' . SourceItemInterface::SOURCE_CODE . ' IN (?)', $sourceCodes)
            ->group([SourceItemInterface::SKU]);

        return $select;
    }

    /**
     * Get all enabled sources related to stock
     *
     * @param int $stockId
     * @return array
     */
    private function getSourceCodes(int $stockId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $sourceTable = $this->resourceConnection->getTableName(SourceResourceModel::TABLE_NAME_SOURCE);
        $sourceStockLinkTable = $this->resourceConnection->getTableName(
            StockSourceLinkResourceModel::TABLE_NAME_STOCK_SOURCE_LINK
        );

        $select = $connection->select()
            ->from(['source' => $sourceTable], [SourceInterface::SOURCE_CODE])
            ->joinInner(
                ['stock_source_link' => $sourceStockLinkTable],
                'source.' . SourceItemInterface::SOURCE_CODE . ' = stock_source_link.' . StockSourceLink::SOURCE_CODE,
                []
            )
            ->where('stock_source_link.' . StockSourceLink::STOCK_ID . ' = ?', $stockId)
            ->where(SourceInterface::ENABLED . ' = ?', 1);

        $sourceCodes = $connection->fetchCol($select);
        return $sourceCodes;
    }
}
