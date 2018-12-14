<?php
class Cammino_Sicoob_Block_Info extends Mage_Payment_Block_Info {
    
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('sicoob/info.phtml');
    }

    public function getOrder() {
        return $this->getInfo()->getOrder();
    }

    public function getOrderId() {
        return $this->getOrder()->getRealOrderId();
    }

}