<?php

class Loomy_Liqpay_Model_Cron extends Mage_Core_Model_Abstract
{
    public function validateAll()
    {
        $orders = Mage::getResourceModel('sales/order_collection');
        $orders->addAttributeToSearchFilter('state', Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $orders->getSelect()
            ->join(
                array('payment' => $orders->getTable('sales/order_payment')),
                '(main_table.entity_id = payment.parent_id AND payment.method = "lliqpay_cc")',
                array()
            );
        $orders->load();
        
        foreach ($orders as $order) {
            $method = $order->getPayment()->getMethodInstance();
            $method->liqpayValidate();
        }
    }
}