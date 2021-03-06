<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogSearch\Model\Adapter\Mysql\Aggregation;

use Magento\Catalog\Model\Product;
use Magento\CatalogSearch\Model\Adapter\Mysql\Aggregation\DataProvider\SelectBuilderForAttribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Select;
use Magento\Framework\Search\Adapter\Mysql\Aggregation\DataProviderInterface;
use Magento\Framework\Search\Request\BucketInterface;

class DataProvider implements DataProviderInterface
{
    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * @var ScopeResolverInterface
     */
    private $scopeResolver;

    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @var SelectBuilderForAttribute
     */
    private $selectBuilderForAttribute;

    /**
     * @param Config $eavConfig
     * @param ResourceConnection $resource
     * @param ScopeResolverInterface $scopeResolver
     * @param null $customerSession @deprecated
     * @param SelectBuilderForAttribute|null $selectBuilderForAttribute
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        Config $eavConfig,
        ResourceConnection $resource,
        ScopeResolverInterface $scopeResolver,
        $customerSession,
        SelectBuilderForAttribute $selectBuilderForAttribute = null
    ) {
        $this->eavConfig = $eavConfig;
        $this->connection = $resource->getConnection();
        $this->scopeResolver = $scopeResolver;
        $this->selectBuilderForAttribute = $selectBuilderForAttribute
            ?: ObjectManager::getInstance()->get(SelectBuilderForAttribute::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSet(
        BucketInterface $bucket,
        array $dimensions,
        Table $entityIdsTable
    ) {
        $currentScope = $this->scopeResolver->getScope($dimensions['scope']->getValue())->getId();
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $bucket->getField());
        $select = $this->getSelect();

        $select->joinInner(
            ['entities' => $entityIdsTable->getName()],
            'main_table.entity_id  = entities.entity_id',
            []
        );
        $select = $this->selectBuilderForAttribute->build($select, $attribute, $currentScope);

        return $select;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Select $select)
    {
        return $this->connection->fetchAssoc($select);
    }

    /**
     * @return Select
     */
    private function getSelect()
    {
        return $this->connection->select();
    }
}
