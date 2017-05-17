<?php

class Unit_RuleHelperTest extends PHPUnit_Framework_TestCase
{
    protected $helper;

    public function setUp()
    {
        $this->helper = Mage::helper('nypwidget/rule');
    }

    public function testItCanBeInstantiated()
    {
        $this->assertEquals('PriceWaiter_NYPWidget_Helper_Rule', get_class($this->helper));
    }

    public function testRulesThatApplyToShippingAmount()
    {
        $rule = new Varien_Object(['apply_to_shipping' => '1']);
        $this->assertTrue($this->helper->ruleAppliesToShippingOnly($rule));
    }

    public function testRulesWithMatchingItems()
    {
        $rule = new Varien_Object(['simple_free_shipping' => '1']);
        $this->assertTrue($this->helper->ruleAppliesToShippingOnly($rule));
    }

    public function testRulesForShipmentWithMatchingItems()
    {
        $rule = new Varien_Object(['simple_free_shipping' => '2']);
        $this->assertTrue($this->helper->ruleAppliesToShippingOnly($rule));
    }

    public function testRulesWithMoreThanJustShipping()
    {
        $rule = new Varien_Object(['foo' => 'bar']);
        $this->assertFalse($this->helper->ruleAppliesToShippingOnly($rule));
    }
}
