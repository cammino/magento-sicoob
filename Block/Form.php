<?php
class Cammino_Sicoob_Block_Form extends Mage_Payment_Block_Form {
    
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('sicoob/form.phtml');
    }
}