<?php

class Loomy_Liqpay_Model_Cc extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'lliqpay_cc';
    protected $_formBlockType = 'lliqpay/form';
    protected $_infoBlockType = 'lliqpay/info';

    protected $_isGateway                = false;
    protected $_canAuthorize            = false;
    protected $_canCapture                = true;
    protected $_canCapturePartial        = false;
    protected $_canRefund                = false;
    protected $_canVoid                    = false;
    protected $_canUseInternal            = false;
    protected $_canUseCheckout            = true;
    protected $_canUseForMultishipping    = false;

    protected $_order;
    protected $_client;
    protected $_apiUrl = 'https://www.liqpay.com/?do=api_xml';
    
    protected $_supportedCurrencies = array('RUB', 'EUR', 'USD', 'UAH');
    
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';
    const STATUS_VALIDATION = 'wait_secure';
    const STATUS_DELAYED = 'delayed';
    
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    public function getFormData()
    {
        if (Mage::getStoreConfig('payment/lliqpay_cc/goods_id') && $this->getOrder()->getItemsCollection() == 1) {
            $items = $this->getOrder()->getAllItems();
            $goodsId = $items[0]->getProductId();
        }
        else {
            $goodsId = '';
        }
        
        $xml = '
        <request>      
            <version>1.2</version>
            <merchant_id>' . $this->getMerchantId() . '</merchant_id>
            <result_url>' . $this->getSuccessUrl() . '</result_url>
            <server_url>' . $this->getCallbackUrl() . '</server_url>
            <order_id>' . $this->getOrder()->getRealOrderId() . '</order_id>
            <amount>' . round($this->getOrder()->getGrandTotal(), 2) . '</amount>
            <currency>' . $this->getCurrentCurrencyCode() . '</currency>
            <description>Order #' . $this->getOrder()->getRealOrderId() . '</description>
            <default_phone></default_phone>
            <pay_way>' . Mage::getStoreConfig('payment/lliqpay_cc/available_methods') . '</pay_way>
            <goods_id>' . $goodsId . '</goods_id>
            <exp_time>' . Mage::getStoreConfig('payment/lliqpay_cc/exp_time') . '</exp_time>
        </request>
        ';
            
        Mage::log ('Request: ' . $xml, null, 'liqpay.log');
//            $responseXml = '
//            <response>     
//                  <version>1.2</version>
//                  <merchant_id>' . $this->getMerchantId() . '</merchant_id>
//                  <order_id>' . $this->getOrder()->getRealOrderId() . '</order_id>
//                  <amount>' . round($this->getOrder()->getGrandTotal(), 2) . '</amount>
//                  <currency>' . $this->getCurrentCurrencyCode() . '</currency>
//                  <description>Order #' . $this->getOrder()->getRealOrderId() .'</description>
//                  <status>' . Loomy_Liqpay_Model_Cc::STATUS_SUCCESS . '</status>
//                  <code></code>
//                  <transaction_id>' . rand(100000, 999999) . '</transaction_id>
//                  <pay_way>card</pay_way>
//                  <sender_phone>+3801234567890</sender_phone>
//                  <goods_id></goods_id>
//                  <pays_count></pays_count>
//            </response>' ;
//            Mage:: log('Response xml: ' . $responseXml, null, 'temp.log');
//            Mage:: log('Response: ' . print_r( array(
//                    'signature' => base64_encode(sha1($this ->getMerchantSign() . $responseXml . $this->getMerchantSign(), 1)),
//                    'operation_xml' => base64_encode($responseXml)
//                ), true), null , 'temp.log' );
            
            
        return array(
            'signature' => base64_encode(sha1($this->getMerchantSign() . $xml . $this->getMerchantSign(), 1)),
            'operation_xml' => base64_encode($xml)
        );
    }
    
    public function getCurrentCurrencyCode()
    {
        $currentCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        if (in_array($currentCode, $this->_supportedCurrencies)) {
            // Temporary workaround before liqpay is fixing RUR to RUB
            if ($currentCode == 'RUB') {
                $currentCode = 'RUR';
            }
            
            return $currentCode;
        }
        else {
            Mage::getSingleton('core/session')->addError(Mage::helper('lliqpay')->__('Currency is not supported by Liqpay'));
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/failure'));
        }
    }
    
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
        }
        return $this->_order;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('lliqpay/processing/redirect');
    }

    public function getClient()
    {
        if (!$this->_client) {
            $this->_client = new Zend_Http_Client($this->_apiUrl);
            $this->_client->setHeaders('Content-type', 'text/xml;charset=UTF-8');
        }
        return $this->_client;
    }
    
    public function liqpayValidate()
    {
        /** @var $order Mage_Sales_Model_Order */
        $order = $this->getInfoInstance()->getOrder();
        
        if (!Mage::helper('lliqpay')->isChecking($order)) {
            return false;
        }

        $merchantId = Mage::getStoreConfig('payment/lliqpay_cc/merchant_id', $order->getStoreId());
        $merchantSign = Mage::getStoreConfig('payment/lliqpay_cc/merchant_sign', $order->getStoreId());
        $requestXml = '<?xml version="1.0" encoding="UTF-8"?>
            <request>
                <liqpay>
                    <operation_envelope>
                        <operation_xml>';
        $operationXml = '<request>
            <version>1.2</version>
            <action>view_transaction</action>
            <merchant_id>' . $merchantId . '</merchant_id>
            <transaction_order_id>' . $order->getRealOrderId() . '</transaction_order_id>
        </request>';
        $sign = base64_encode(sha1($merchantSign . $operationXml . $merchantSign, 1));
        $requestXml .= base64_encode($operationXml) . '</operation_xml>
                    <signature>' . $sign . '</signature>
                </operation_envelope>
            </liqpay>
        </request>';

        Mage::helper('lliqpay')->log('CRON $operationXml: ' . $operationXml);
        
        $response = $this->getClient()
            ->setRawData($requestXml)
            ->request(Zend_Http_Client::POST);
        
        $simplexml = simplexml_load_string($response->getBody());
        $responseXml = base64_decode($simplexml->liqpay->operation_envelope->operation_xml);
        $sign = base64_encode(sha1($merchantSign . $responseXml . $merchantSign, 1));
        if ($sign != $simplexml->liqpay->operation_envelope->signature) {
            return false;
        }

        Mage::helper('lliqpay')->log('CRON $responseXml: ' . $responseXml);

        $responseXml = simplexml_load_string($responseXml);
        $responseParams = array();
        foreach ($responseXml->children() as $var => $value) {
            $responseParams[$var] = (string)$value;
        }
        
        if ($responseParams['status'] == self::STATUS_SUCCESS) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage::helper('lliqpay')->__('Payment accepted')
            );
            $invoice = Mage::helper('lliqpay')->createInvoiceForOrder($order);
            if (!$order->getEmailSent()) {
                $order->sendNewOrderEmail();
            }
            if ($invoice && !$invoice->getEmailSent()) {
                $invoice->sendEmail();
            }
            $order->save();
            Mage::helper('lliqpay')->log('Payment accepted');

            return Mage_Sales_Model_Order::STATE_PROCESSING;
        }
        elseif ($responseParams['status'] == self::STATUS_FAILURE) {
            $expTime = $order->getPayment()->getAdditionalInformation();
            if (is_array($expTime) && isset($expTime['exp_time']) && $expTime['exp_time'] > 0) {
                // For cash payments only
                $expTime = $expTime['exp_time'];
                $creationTime = strtotime($order->getCreatedAt());
                $currentTime = Mage::getModel('core/date')->timestamp(time());
                if ($currentTime - $creationTime > $expTime*3600) {
                    $order->setState(
                        Mage_Sales_Model_Order::STATE_CANCELED,
                        Mage_Sales_Model_Order::STATE_CANCELED,
                        Mage::helper('lliqpay')->__('Order cash payment expired. Current expiration time is %s hours.', $expTime)
                    )->save();
                    $order->cancel();
                    return Mage_Sales_Model_Order::STATE_CANCELED;
                }
                else {
                    return false;
                }
            }
            elseif (strpos($responseParams['code'], 'no_trn') !== false) {
                Mage::helper('lliqpay')->log('no_trn status code... skipping...');
                return false;
            }
            else {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    Mage::helper('lliqpay')->__('Liqpay validation failed.')
                )->save();
                $order->cancel();
                return Mage_Sales_Model_Order::STATE_CANCELED;
            }
        }
        
        return false;
    }
    
    public function getFormAction()
    {
        return Mage::getStoreConfig('payment/lliqpay_cc/redirect_url');
    }
    
    public function getMerchantId()
    {
        return Mage::getStoreConfig('payment/lliqpay_cc/merchant_id');
    }
    
    public function getMerchantSign()
    {
        return Mage::getStoreConfig('payment/lliqpay_cc/merchant_sign');
    }
    
    public function getSuccessUrl()
    {
        return Mage::getUrl('lliqpay/processing/return');
    }
    
    public function getCallbackUrl()
    {
        return Mage::getUrl('lliqpay/processing/callback');
    }
}