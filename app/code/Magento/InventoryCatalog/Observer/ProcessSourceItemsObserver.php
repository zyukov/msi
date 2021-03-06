<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Save;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Inventory\Model\IsSourceItemsManagementAllowedForProductTypeInterface;

/**
 * Save source product relations during product persistence via controller
 *
 * This needs to be handled in dedicated observer, because there is no pre-defined way of making several API calls for
 * Form submission handling
 */
class ProcessSourceItemsObserver implements ObserverInterface
{
    /**
     * @var IsSourceItemsManagementAllowedForProductTypeInterface
     */
    private $isSourceItemsManagementAllowedForProductType;

    /**
     * @var SourceItemsProcessor
     */
    private $sourceItemsProcessor;

    /**
     * @param IsSourceItemsManagementAllowedForProductTypeInterface $isSourceItemsManagementAllowedForProductType
     * @param SourceItemsProcessor $sourceItemsProcessor
     */
    public function __construct(
        IsSourceItemsManagementAllowedForProductTypeInterface $isSourceItemsManagementAllowedForProductType,
        SourceItemsProcessor $sourceItemsProcessor
    ) {
        $this->isSourceItemsManagementAllowedForProductType = $isSourceItemsManagementAllowedForProductType;
        $this->sourceItemsProcessor = $sourceItemsProcessor;
    }

    /**
     * Process source items during product saving via controller
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /** @var ProductInterface $product */
        $product = $observer->getEvent()->getProduct();
        if ($this->isSourceItemsManagementAllowedForProductType->execute($product->getTypeId()) === false) {
            return;
        }
        /** @var Save $controller */
        $controller = $observer->getEvent()->getController();

        $sources = $controller->getRequest()->getParam('sources', []);
        $assignedSources = isset($sources['assigned_sources']) && is_array($sources['assigned_sources'])
            ? $sources['assigned_sources'] : [];

        $this->sourceItemsProcessor->process(
            $product->getSku(),
            $assignedSources
        );
    }
}
