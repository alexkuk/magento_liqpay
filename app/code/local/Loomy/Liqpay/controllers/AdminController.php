<?php

class Loomy_Liqpay_AdminController extends Mage_Core_Controller_Front_Action
{
    public function checkAction()
    {
        $orderId = $this->getRequest()->getPost('order');
        if (!$orderId) {
            $this->_redirect('/');
        }
        
        $order = Mage::getModel('sales/order');
        $order->load($orderId);
        if (!$order->getId()) {
            $this->_redirect('/');
        }
        
        $payment = $order->getPayment()->getMethodInstance();
        $payment->liqpayValidate();
        $this->_redirectUrl($this->getRequest()->getHeader('Referer'));
    }

}
