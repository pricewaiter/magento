<?php
/*
 * Copyright 2012 PriceWaiter, LLC
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
class PriceWaiter_NYPWidget_Model_Category extends Mage_Core_Model_Abstract
{
	public function _construct()
	{
		$this->_init('nypwidget/category', 'entity_id');
	}

	public function loadByCategory($category, $storeId = null)
	{
		if (is_null($storeId)) {
			$storeId = Mage::app()->getStore()->getId();
		}

		$collection = Mage::getModel('nypwidget/category')
			->getCollection()
			->addFieldToFilter('category_id', $category->getId())
			->addFieldToFilter('store_id', $storeId);

		if (count($collection)) {
			$this->load($collection->getFirstItem()->getEntityId());
		} else {
			$this->setData('category_id', $category->getId());
			$this->setData('store_id', $storeId);
			$this->save();
		}

		return $this;
	}

	public function isActive()
	{
		// If the category isn't yet set in the table, default to true.
		// Otherwise, check the nypwidget_enabled field.
		if (is_null($this->getData('category_id')) or $this->getData('nypwidget_enabled') == 1) {
			return true;
		}

		return false;
	}
}