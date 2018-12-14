<?php
class Cammino_Sicoob_Model_Standard extends Mage_Payment_Model_Method_Abstract {

	protected $_code = 'sicoob';
	protected $_order = null;
	protected $_infoBlockType = 'sicoob/info';

	public function getCheckout() {
		return Mage::getSingleton('checkout/session');
	}

	public function getOrder($order_id) {
		if ($order_id != "") {
			$this->_order = Mage::getModel('sales/order')->load($order_id);
		}else{
			$orderIncrementId = $this->getCheckout()->getLastRealOrderId();
			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		}
		return $this->_order;
	}	

	public function getCheckoutFormFields($order_id, $tpPagamento) {
		$order = $this->getOrder($order_id);
		$orderData = $order->getData();

		$customerId = $order->getCustomerId();
        $customerData = Mage::getModel('customer/customer')->load($customerId);
		$customer = $customerData->getData();

		$address = $order->getIsVirtual() ? $order->getBillingAddress() : $order->getShippingAddress();
       
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $region = $read->fetchRow('SELECT * FROM '.Mage::getConfig()->getTablePrefix().'directory_country_region WHERE default_name = "'.$address->getRegion().'"');

		$fields = array(
            'numCliente'	   			=> $this->getConfigData('numCliente', $order->getStoreId()),
            'coopCartao'	   			=> $this->getConfigData('coopCartao', $order->getStoreId()),
            'chaveAcessoWeb'   			=> $this->getConfigData('chaveAcessoWeb', $order->getStoreId()),
            'numContaCorrente' 			=> $this->getConfigData('numContaCorrente', $order->getStoreId()),
            'codMunicipio' 	   			=> $this->getConfigData('codMunicipio', $order->getStoreId()),
            'nomeSacado' 	   			=> $this->getFormatedName($address->getFirstname(), $address->getLastname()),
            'dataNascimento'  			=> $this->getFormatedDateofBirthday($customer['dob']),
            'cpfCGC'   		  			=> $this->getFormatedTaxvat($customer["taxvat"]),
            'endereco'		   			=> $address->getStreet1(). ', '. $address->getStreet2(),
            'bairro'		   			=> $address->getStreet4(),
            'cidade'		   			=> $address->getCity(),
            'cep'              			=> $this->getFormatedPostcode($address->getPostcode()),
            'uf'			   			=> $region['code'],
            'telefone'         			=> $this->getFormatedPhone($address->getTelephone()),
            'ddd'              			=> $this->getFormatedDD($address->getTelephone()),
            'ramal'            			=> '',
            'bolRecebeBoletoEletronico' => 1,
            'email'						=> $customer['email'],
            'codEspDocumento'           => 'DM',
            'dataEmissao' 				=> date('Ymd'),
            'seuNumero'					=> '',
            'nomeSacador'               => '', //Nome do Sacador
            'numCGCCPFSacador'          => '', //CNPJ ou CPF do Sacador
            'qntMonetaria'              => 1,
            'valorTitulo'               => $this->getFormatedValue($order->getGrandTotal()),
            'codTipoVencimento'			=> '1',
            'dataVencimentoTit'			=> date('Ymd',strtotime("+3 day")),
            'valorAbatimento' 			=> '0',
            'valorIOF' 					=> '0',
            'bolAceite'					=> '1',
            'percTaxaMulta'				=> '0',
            'percTaxaMora'				=> '0',
            'dataPrimDesconto' 			=> NULL,
            'valorSegDesconto'   		=> NULL,
            'descInstrucao1'			=> 'Pedido #'.$orderData["increment_id"],
            'descInstrucao2'			=> 'Pedido efetuado na loja ' . Mage::getBaseUrl(),
            'descInstrucao3' 			=> 'Em 2(dois) dias úteis para confirmação',
            'descInstrucao4'			=> 'Não receber após o vencimento',
            'descInstrucao5' 			=> 'Não receber pagamento em cheque'
		);
		
		return $fields;
	}
	
	public function createRedirectForm($order_id, $tpPagamento = 2)
	{
		$form = new Varien_Data_Form();
		$form->setAction($this->getBancoUrl())
		->setId('sicoob_checkout')
		->setName('pagamento')
		->setMethod('POST')
		->setUseContainer(true);
		
		$fields = $this->getCheckoutFormFields($order_id, $tpPagamento);
		foreach ($fields as $field => $value) {
			$form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
		}
		$html = $form->toHtml();
		$submit_script = 'document.getElementById(\'sicoob_checkout\').submit();';
	
		$html  = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
		$html .= '<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="pt-BR">';
		$html .= '<head>';
		$html .= '<meta http-equiv="Content-Language" content="pt-br" />';
		$html .= '<meta name="language" content="pt-br" />';
		$html .= '<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />';
		$html .= '<style type="text/css">';
		$html .= '* { font-family: Arial; font-size: 16px; line-height: 34px; text-align: center; color: #222222; }';
		$html .= 'small, a, a:link:visited:active, a:hover { font-size: 13px; line-height: normal; font-style: italic; }';
		$html .= 'a, a:link:visited:active { font-weight: bold; text-decoration: none; }';
		$html .= 'a:hover { font-weight: bold; text-decoration: underline; color: #555555; }';
		$html .= '</style>';
		$html .= '</head>';
		$html .= '<body onload="' . $submit_script . '">';
		$html .= 'Você será redirecionado ao <strong>Banco SICOOB</strong> em alguns instantes.<br />';
		$html .= '<small>Se a página não carregar, <a href="#" onclick="' . $submit_script . ' return false;">clique aqui</a>.</small>';
		$html .= $form->toHtml();
		$html .= '</body></html>';
		
		//echo utf8_decode($html);
		return $html;
	}
	
	public function getBancoUrl() {
		return 'https://geraboleto.sicoobnet.com.br/geradorBoleto/GerarBoleto.do';
	}
	
	public function getOrderPlaceRedirectUrl() {
		// return Mage::getUrl($this->getCode().'/standard/redirect', array('_secure' => true));
		return Mage::getUrl($this->getCode().'/standard/success', array('_secure' => true));
	}
        
    protected function _geraRefTran($idconvc,$nrOrder) {
        $count_idconv = strlen($idconvc);
        $count_nrOrder = strlen($nrOrder);
        
        $refTran = $idconvc;
        
        for ($i = 0; $i < ((($count_idconv+$count_nrOrder) - 17)*-1); $i++){
            $refTran.= '0';
        }
        
        $refTran.= $nrOrder;
        return $refTran;
    }

    private function getFormatedPhone($phone) {
		$phone = explode(")", $phone);
		$phone = $phone[1];
		$phone = str_replace("-", "", $phone);
		$phone = str_replace(" ", "", $phone);
		$phone = trim($phone);

		return $phone;
	}
	
	private function getFormatedDD($phone, $errorReturn = "") {
		$dd = explode(")", $phone);
		$dd = explode("(", $dd[0]);
		$dd = str_replace(" ", "", $dd[1]);
		return strlen($dd) > 1 ? $dd : $errorReturn;
	}

	private function getFormatedTaxvat($taxvat) {
		$taxvat = str_replace('-', '', $taxvat);
		$taxvat = str_replace('.', '', $taxvat);
		$taxvat = str_replace('/', '', $taxvat);
		$taxvat = str_replace('\\', '', $taxvat);
		$taxvat = str_replace(' ', '', $taxvat);
		return $taxvat;
	}

	private function getFormatedName($firstname, $lastname) {
		return $firstname . ' ' . $lastname;
	}

	private function getFormatedDateOfBirthday($date) {
		$date = new Zend_Date($date, 'YYYY-MM-dd HH:mm:ss');
		return $date->get('YYYYMMdd');
	}

	private function getFormatedPostcode($postcode) {
		return str_replace('-', '', $postcode);
	}

	private function getFormatedValue($value) {
		$value = number_format($value, 2, '.', ',');
		return str_replace(",", "", $value);
	}
}
?>