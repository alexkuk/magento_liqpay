<?php

class Loomy_Liqpay_Block_Redirect extends Mage_Core_Block_Template
{
    protected $_order;

    public function getPaymentMethod()
    {
        return $this->_getOrder()->getPayment()->getMethodInstance();
    }
    
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getOrder()
    {
        if ($this->_order) {
            return $this->_order;
        } elseif ($orderIncrementId = $this->_getCheckout()->getLastRealOrderId()) {
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
            return $this->_order;
        } else {
            return null;
        }
    }

    public function getFormData()
    {
        return $this->getPaymentMethod()->getFormData();
    }
    
    public function getFormAction()
    {
        return $this->getPaymentMethod()->getFormAction();
    }
}