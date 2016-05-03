<?php

class PriceWaiter_NYPWidget_Model_Category extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        $this->_init('nypwidget/category', 'entity_id');
    }

    public function loadByCategory($category, $storeId = null)
    {
        if (is_object($category)) {
            $categoryID = $category->getId();
        } else {
            $categoryID = $category;
        }

        if (is_null($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }

        $collection = Mage::getModel('nypwidget/category')
            ->getCollection()
            ->addFieldToFilter('category_id', $categoryID)
            ->addFieldToFilter('store_id', $storeId);

        if (count($collection)) {
            $this->load($collection->getFirstItem()->getEntityId());
        } else {
            $this->setData('category_id', $categoryID);
            $this->setData('store_id', $storeId);
            $this->save();
        }

        return $this;
    }

    public function isActive($admin = false)
    {
        // If we are in the admin, we want to skip all this, so that we return
        // the info on this specific category, not parents
        if (!$admin) {
            if (!$this->_checkParents('isActive')) {
                return false;
            }
        }

        // If the category isn't yet set in the table, default to true.
        // Otherwise, check the nypwidget_enabled field.
        if (is_null($this->getData('category_id')) or $this->getData('nypwidget_enabled') == 1) {
            return true;
        }

        if (is_null($this->getData('nypwidget_enabled'))) {
            return true;
        }

        return false;
    }

    public function isConversionToolsEnabled($admin = false)
    {
        // If we are in the admin, we want to skip all this, so that we return
        // the info on this specific category, not parents
        if (!$admin) {
            if (!$this->_checkParents('isConversionToolsEnabled')) {
                return false;
            }
        }

        // If the category isn't yet set in the table, default to true.
        // Otherwise, check the nypwidget_enabled field.
        if (is_null($this->getData('category_id')) or $this->getData('nypwidget_ct_enabled') == 1) {
            return true;
        }

        if (is_null($this->getData('nypwidget_ct_enabled'))) {
            return true;
        }

        return false;
    }

    private function _checkParents($func)
    {
        // First, check the "All Store Views" store (0)
        if ($this->getStoreId() != 0) {
            $allStoresCategory = Mage::getModel('nypwidget/category')
                ->loadByCategory($this->getCategoryId(), 0);
            if (!call_user_func(array($allStoresCategory, $func))) {
                return false;
            }
        }

        // Check the parent category if we already have a category_id and have a parent
        if (!is_null($this->getData('category_id'))) {
            $category = Mage::getModel('catalog/category')->load($this->getData('category_id'));
            if ($category->getParentId() != 0) {
                $parentCategory = Mage::getModel('nypwidget/category')->loadByCategory($category->getParentId());
                if (!call_user_func(array($parentCategory, $func))) {
                    return false;
                }
            }
        }

        return true;
    }
}
