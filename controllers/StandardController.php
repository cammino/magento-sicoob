<?php
class Cammino_Sicoob_StandardController extends Mage_Core_Controller_Front_Action {
    
	protected  function getBoletoSicoob() {
		return Mage::getSingleton('sicoob/standard');
	}
	
	protected  function getCheckout() {
		return Mage::getSingleton('checkout/session');
	}
	
	public function successAction() {
		$boleto_sicoob = $this->getBoletoSicoob();
		$session = $this->getCheckout();
		$orderIncrementId = $session->getLastRealOrderId();
		$lastOrderId = $session->getLastOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

		if ($order->getId()) {
			if(!$order->getEmailSent()) {
				$order->sendNewOrderEmail();
				$order->setEmailSent(true);
				$order->save();
            }

			$this->loadLayout();
			$this->_initLayoutMessages('checkout/session');
			Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($lastOrderId)));
			$this->renderLayout();
		}else {
            $this->_redirect('');
        }
	}

	public function redirectAction() {
		$sicoob = $this->getBoletoSicoob();
		$session = $this->getCheckout();
		$orderIncrementId = $session->getLastRealOrderId();
		$lastOrderId = $session->getLastOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		
		if ($order->getId()) {
			if(!$order->getEmailSent()) {
				$order->sendNewOrderEmail();
				$order->setEmailSent(true);
				$order->save();
            }
            
			$html = $sicoob->createRedirectForm();
			$this->getResponse()->setHeader("Content-Type", "text/html; charset=utf-8", true);
			$this->getResponse()->setBody($html);
		}else {
            $this->_redirect('');
        }
	}

	public function adminredirectAction() {
		$orderId = (int) $this->getRequest()->getParam('order_id');
		$sicoob = $this->getBoletoSicoob();
		$html = $sicoob->createRedirectForm($orderId);
		$this->getResponse()->setHeader("Content-Type", "text/html; charset=utf-8", true);
		$this->getResponse()->setBody($html);
	}
	
	protected function _loadValidOrder($orderId = null) {
        if ($orderId == null) {
			$orderId = (int) $this->getRequest()->getParam('order_id');
		}
		if (!$orderId) {
			$this->_forward('noRoute');
			return false;
		}
	
		$order = Mage::getModel('sales/order')->load($orderId);
        
        if ($this->_canViewOrder($order)) {
			Mage::register('current_order', $order);
			return true;
		} else {
			$this->_redirect('sales/order/view/order_id/'.$orderId);
			return false;
		}
	}
	
	protected function _canViewOrder($order) {
		$customerId = Mage::getSingleton('customer/session')->getCustomerId();
		$availableStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
		$method = $order->getPayment()->getMethod();
		if ($order->getCustomerId() == $customerId && in_array($order->getState(), $availableStates, true) && strpos($method, 'boletobb') !== false) {
			return true;
		}
		return false;
	}
	
	protected function _loadValidOrderAdmin($orderId = null) {
		if ($orderId == null) {
			$orderId = (int) $this->getRequest()->getParam('order_id');
		}
        
        if (!$orderId) {
			$this->_forward('noRoute');
			return false;
		}
	
		$order = Mage::getModel('sales/order')->load($orderId);
        
        if ($this->_canViewOrderAdmin($order)) {
			Mage::register('current_order', $order);
			if (!$order->getCustomerId()) true;
			$customer = Mage::getModel('customer/customer')->load( $order->getCustomerId());
			Mage::register('order_customer', $customer);
			return true;
		} else {
			$this->_redirect('/sales_order/view/order_id/'.$orderId);
			return false;
		}
	}
	
	protected function _canViewOrderAdmin($order) {
		$availableStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
		$method = $order->getPayment()->getMethod();
		if (in_array($order->getState(), $availableStates, true) && strpos($method, 'sicoob') !== false) {			
			return true;
		}
		return false;
	}
}