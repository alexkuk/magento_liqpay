<?php

class Loomy_Liqpay_Helper_Data extends Mage_Payment_Helper_Data
{
    public function isCaptured($order)
    {
        switch ($order->getState()) {
            case Mage_Sales_Model_Order::STATE_NEW:
            case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
            case Mage_Sales_Model_Order::STATE_CANCELED:
            case Mage_Sales_Model_Order::STATE_CLOSED:
                return false;
            default:
                return true;
        }
    }
    
    public function isChecking($order)
    {
        switch ($order->getState()) {
            case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
                return true;
            default:
                return false;
        }
    }

    public function log($text)
    {
        Mage::log($text, null, 'liqpay.log');
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool|Mage_Sales_Model_Order_Invoice
     */
    public function createInvoiceForOrder(Mage_Sales_Model_Order $order)
    {
        if (!Mage::getStoreConfig('payment/lliqpay_cc/create_invoice') || !$order->canInvoice()) {
            return false;
        }

        $invoice = $order->prepareInvoice();
        if (!$invoice) {
            return false;
        }
        $invoice->register()
            ->pay();

        Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();

        return $invoice;
    }

}