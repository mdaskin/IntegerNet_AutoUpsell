<?php
/**
 * integer_net Magento Module
 *
 * @category   IntegerNet
 * @package    IntegerNet_AutoUpsell
 * @copyright  Copyright (c) 2015 integer_net GmbH (http://www.integer-net.de/)
 * @author     Fabian Schmengler <fs@integer-net.de>
 */

use Mage_Catalog_Model_Product as Product;
use Mage_Catalog_Model_Product_Link as RelatedProduct;
use Mage_Catalog_Model_Resource_Product_Link_Product_Collection as RelatedProductCollection;

class IntegerNet_AutoUpsell_Model_Observer
{
    /**
     * @see event catalog_product_upsell
     * @param Varien_Event_Observer $observer
     * @throws Mage_Core_Exception
     */
    public function fillUpsellCollection(Varien_Event_Observer $observer)
    {
        $collection = $observer->getCollection();
        if ($collection instanceof RelatedProductCollection
            && $collection->getLinkModel()->getLinkTypeId() === RelatedProduct::LINK_TYPE_UPSELL
            && $collection->count() < $observer->getLimit('upsell')
        ) {
            $this->addItemsFromCategory($collection, $observer->getLimit('upsell') - $collection->count(), $observer->getProduct());
        }
    }
    protected function addItemsFromCategory(RelatedProductCollection $collection, $numberOfItems, Product $product)
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $productsToAdd */
        
        $stockIds = Mage::getModel('cataloginventory/stock_item')
            ->getCollection()
            ->addQtyFilter('>=', 1) //can be ->addQtyFilter('>=', 30), depending on requirement
            ->getAllIds();
        
        $productsToAdd = $this->_getProductCategory($product)->getProductCollection();
        
        $productsToAdd
            ->addStoreFilter()
            ->addIdFilter($stockIds)
            ->addAttributeToFilter('price', array('gteq' => $product->getData('price')))
            ->addIdFilter([$product->getId()] + $collection->getAllIds(), true)
            ->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds());
        /*
         * To pick random products we don't ORDER BY RAND() because it results in a resource intensive table scan.
         * Instead we retrieve all ids, pick some randomly and retrieve these rows directly.
         */
        $candidateIds = $productsToAdd->getAllIds();
        shuffle($candidateIds);
        $choosenIds = array_splice($candidateIds, 0, $numberOfItems);

        $productsToAdd
            ->addIdFilter($choosenIds)
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addUrlRewrite();
        foreach ($productsToAdd as $product) {
            $collection->addItem($product);
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Category
     */
    protected function _getProductCategory(Product $product)
    {
        $category = $product->getCategoryCollection()
            ->setOrder('upsell_priority', Varien_Data_Collection_Db::SORT_ORDER_DESC)
            ->setPageSize(1)
            ->getFirstItem();
        return $category;
    }
}
