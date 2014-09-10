<?php

class Loomy_Liqpay_ProcessingController extends Mage_Core_Controller_Front_Action
{
    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool|Mage_Sales_Model_Order_Invoice
     */
    protected function _createInvoiceForOrder(Mage_Sales_Model_Order $order)
    {
        return Mage::helper('lliqpay')->createInvoiceForOrder($order);
    }
    
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function redirectAction()
    {
        try {
            $session = $this->_getCheckout();
    
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());
            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }
            $order->addStatusHistoryComment(
                Mage::helper('lliqpay')->__('Customer was redirected to Liqpay.')
            )->save();
            
            $this->loadLayout();
            $this->renderLayout();
        }
        catch (Exception $e) {
            Mage::log(__CLASS__ . '::' . __METHOD__ . ' - Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), null, 'liqpay.log');
            $this->_redirect('checkout/onepage/failure');
        }
    }

    public function callbackAction()
    {
        try {
        	if (!$this->getRequest()->getPost('operation_xml') || !$this->getRequest()->getPost('signature')) {
                die('NO');
            }
            
        	$xml = base64_decode($this->getRequest()->getPost('operation_xml'));
        	Mage::log('Response: ' . $xml, null, 'liqpay.log');
        	
        	$signature = base64_encode(sha1(Mage::getStoreConfig('payment/lliqpay_cc/merchant_sign') . $xml . Mage::getStoreConfig('payment/lliqpay_cc/merchant_sign'), 1));
            if ($signature != $this->getRequest()->getPost('signature')) {
                Mage::log('Error: signature is incorrect', null, 'liqpay.log');
            	die('NO');
            }
            
        	$simplexml = simplexml_load_string($xml);
        	$operationParams = array();
            foreach ($simplexml->children() as $var => $value) {
            	$operationParams[$var] = (string)$value;
            }
            
            if (Mage::getStoreConfig('payment/lliqpay_cc/merchant_id') != $operationParams['merchant_id']) {
                Mage::log('Error: wrong merchant id in API response', null, 'liqpay.log');
            	die('NO');
            }
            
        	$order = Mage::getModel('sales/order');
            $order->loadByIncrementId((int)$operationParams['order_id']);
            if (!$order->getId()) {
                Mage::log('Error: order not found', null, 'liqpay.log');
                die('NO');
            }

            if ($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {
                Mage::helper('lliqpay')->log('Order is already in processing state');
                die();
            }
            
        	if ($operationParams['amount'] != round($order->getGrandTotal(), 2)) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    Mage::helper('lliqpay')->__('Liqpay amount validation failed.')
                )->save();
                $order->cancel();
                Mage::log('Error: Liqpay amount validation failed.', null, 'liqpay.log');
                die();
            }
            
            switch ($operationParams['status']) {
            	case Loomy_Liqpay_Model_Cc::STATUS_FAILURE:
            		$order->setState(
    	                Mage_Sales_Model_Order::STATE_CANCELED,
    	                Mage_Sales_Model_Order::STATE_CANCELED,
    	                Mage::helper('lliqpay')->__('Payment failed')
    	            )->save();
    	            $order->cancel();
    	            Mage::log('Payment failed', null, 'liqpay.log');
    	            break;
            	case Loomy_Liqpay_Model_Cc::STATUS_VALIDATION:
            		$order->setState(
    	                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
    	                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
    	                Mage::helper('lliqpay')->__('Payment is being validated by Liqpay')
    	            );
                    if (!$order->getEmailSent()) {
    	                $order->sendNewOrderEmail();
                    }
    	            $order->save();
    	            Mage::log('Payment is being validated by Liqpay', null, 'liqpay.log');
            		break;
            	case Loomy_Liqpay_Model_Cc::STATUS_SUCCESS:
            		$order->setState(
    	                Mage_Sales_Model_Order::STATE_PROCESSING,
    	                Mage_Sales_Model_Order::STATE_PROCESSING,
    	                Mage::helper('lliqpay')->__('Payment accepted')
    	            );
    	            $invoice = $this->_createInvoiceForOrder($order);
                    if (!$order->getEmailSent()) {
                        $order->sendNewOrderEmail();
                    }
                    if ($invoice && !$invoice->getEmailSent()) {
                        $invoice->sendEmail();
                    }
    	            $order->save();
    	            Mage::log('Payment accepted', null, 'liqpay.log');
            		break;
            	default:
            		$order->setState(
    	                Mage_Sales_Model_Order::STATE_CANCELED,
    	                Mage_Sales_Model_Order::STATE_CANCELED,
    	                Mage::helper('lliqpay')->__('Payment failed - unknown status returned')
    	            )->save();
    	            $order->cancel();
    	            Mage::log('Payment failed - unknown status returned', null, 'liqpay.log');
    	            break;
            }
        }
        catch (Exception $e) {
            Mage::log(__CLASS__ . '::' . __METHOD__ . ' - Exception: ' . 
                $e->getMessage() . "\n" . $e->getTraceAsString() . "\nXML:\n" . $xml, null, 'liqpay.log');
        }
    }
    
    public function returnAction()
    {
        try {
            $session = $this->_getCheckout();
            
        	$xml = base64_decode($this->getRequest()->getPost('operation_xml'));
        	if ($xml) {
        	    $operationParams = array();
            	$simplexml = simplexml_load_string($xml);
                foreach ($simplexml->children() as $var => $value) {
                	$operationParams[$var] = (string)$value;
                }
                
                switch ($operationParams['status']) {
                    case Loomy_Liqpay_Model_Cc::STATUS_SUCCESS:
                        $session->addSuccess(Mage::helper('lliqpay')->__('Payment accepted'));
                		$this->_redirect('checkout/onepage/success');
                		break;
                    case Loomy_Liqpay_Model_Cc::STATUS_VALIDATION:
                        $session->addSuccess(Mage::helper('lliqpay')->__('Payment is being validated by Liqpay'));
                		$this->_redirect('checkout/onepage/success');
                		break;
                    case Loomy_Liqpay_Model_Cc::STATUS_FAILURE:
                        $this->_restoreCart();
                		$session->addError(Mage::helper('lliqpay')->__('Payment failed'));
                		$this->_redirect('checkout/onepage/failure');
                		break;
                    case Loomy_Liqpay_Model_Cc::STATUS_DELAYED:
                        $order = Mage::getModel('sales/order');
                        $order->loadByIncrementId($session->getLastRealOrderId());
                        if (!$order->getId()) {
                            $this->_restoreCart();
                        	$session->addError(Mage::helper('lliqpay')->__('No order for processing found'));
                            $this->_redirect('checkout/onepage/failure');
                            return;
                        }
                        $order->setState(
        	                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
        	                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
        	                Mage::helper('lliqpay')->__('Order is waiting for offline payment')
        	            );
        	            if (isset($operationParams['exp_time']) && $operationParams['exp_time'] > 0) {
        	                $order->getPayment()
        	                   ->setAdditionalInformation(array('exp_time' => $operationParams['exp_time']))
        	                   ->save();
        	            }
        	            $order->save();
        	            Mage::log('Order #' . $order->getIncrementId() . ' is waiting for offline payment', null, 'liqpay.log');
                        $session->addSuccess(Mage::helper('lliqpay')->__('Your order is placed and waiting for the payment.'));
                        $this->_redirect('checkout/onepage/success');
                        break;
                	default:
                        $this->_restoreCart();
                		$this->_redirect('checkout/onepage/failure');
                		break;
                }
        	}
        	else {
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($session->getLastRealOrderId());
                if (!$order->getId()) {
                	$session->addError(Mage::helper('lliqpay')->__('No order for processing found'));
                    $this->_redirect('checkout/onepage/failure');
                }
                
            	switch ($order->getState()) {
                	case Mage_Sales_Model_Order::STATE_PROCESSING:
                		$session->addSuccess(Mage::helper('lliqpay')->__('Payment accepted'));
                		$this->_redirect('checkout/onepage/success');
                		break;
                	case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
                		$session->addSuccess(Mage::helper('lliqpay')->__('Payment is being validated by Liqpay'));
                		$this->_redirect('checkout/onepage/success');
                		break;
                	case Mage_Sales_Model_Order::STATE_CANCELED:
                        $this->_restoreCart();
                		$session->addError(Mage::helper('lliqpay')->__('Payment failed'));
                		$this->_redirect('checkout/onepage/failure');
                		break;
                	default:
                        $this->_restoreCart();
                		$this->_redirect('checkout/onepage/failure');
                		break;
                }
        	}
        }
        catch (Exception $e) {
            Mage::log(__CLASS__ . '::' . __METHOD__ . ' - Exception: ' . 
                $e->getMessage() . "\n" . $e->getTraceAsString() . "\nXML:\n" . $xml, null, 'liqpay.log');
        	$this->_redirect('checkout/onepage/failure');
        }
    }

    /**
     * set quote to active
     */
    protected function _restoreCart()
    {
        $session = $this->_getCheckout();
        if ($quoteId = $session->getLastQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
        }
        return $this;
    }
}
