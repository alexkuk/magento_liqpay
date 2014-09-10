<?php
class Loomy_Liqpay_Model_Source_Methods
{
    public function toOptionArray()
    {
        return array(
            array(
                'label' => Mage::helper('lliqpay')->__('Visa/Mastercard'),
                'value' => 'card'
            ),
            array(
                'label' => Mage::helper('lliqpay')->__('Liqpay account'),
                'value' => 'liqpay'
            ),
            array(
                'label' => Mage::helper('lliqpay')->__('Cash machine'),
                'value' => 'delayed'
            )
        );
    }
    
    public function getAllOptions()
    {
    	return $this->toOptionArray();
	}
}