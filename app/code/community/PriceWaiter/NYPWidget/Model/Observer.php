<?php
/*
 * Copyright 2012 PriceWaiter, LLC
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */
class PriceWaiter_NYPWidget_Model_Observer
{
	public function addTab(Varien_Event_Observer $observer)
	{
		$object = $observer->getEvent()->getTabs();
		$object->addTab('pricewaiter', array(
        	'label'		=> Mage::helper('catalog')->__('PriceWaiter'),
        	'content'	=> $object->getLayout()->createBlock(
        		'nypwidget/category')->toHtml(),
        ));
		return true;
	}
}