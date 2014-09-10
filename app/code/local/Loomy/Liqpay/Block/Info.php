<?php

class Loomy_Liqpay_Block_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('lliqpay/info.phtml');
    }
    
    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }

    public function getOrder()
    {
        return $this->getInfo()->getOrder();
    }
}