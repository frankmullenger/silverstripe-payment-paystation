<?php

class PaystationTest extends SapphireTest {
	
	public $data;
	public $processor;
  
  function setUp() {
    parent::setUp();
    
    Config::inst()->remove('PaymentGateway', 'environment');
		Config::inst()->update('PaymentGateway', 'environment', 'test');
    
		Config::inst()->remove('PaymentProcessor', 'supported_methods');
		Config::inst()->update('PaymentProcessor', 'supported_methods', array(
			'test' => array(
				'PaystationThreeParty'
		)));

		Config::inst()->remove('PaystationGateway_ThreeParty', 'test');
		Config::inst()->update('PaystationGateway_ThreeParty', 'test', array(
			'authentication' => array(
				'paystation_id' => 'mock_paystation_id',
				'gateway_id' => 'mock_gateway_id'
			),
			'verify_ssl_mode' => array(
				false	
			)
		));

		$this->processor = PaymentFactory::factory('PaystationThreeParty');
		
		$this->data = array(
			'Amount' => '10',
			'Currency' => 'NZD'
		);
  }
  
  public function testProcessorConfig() {

		$this->assertEquals(get_class($this->processor), 'PaystationProcessor_ThreeParty');
		$this->assertEquals(get_class($this->processor->gateway), 'PaystationGateway_ThreeParty_Mock');
		$this->assertEquals(get_class($this->processor->payment), 'Paystation');
	}
	
	public function testGatewayConfig() {
  	$config = $this->processor->gateway->getConfig();

    $URL    = Config::inst()->get('PaystationGateway_ThreeParty', 'url');
    $paystationID = $config['authentication']['paystation_id'];
    $gatewayID    = $config['authentication']['gateway_id'];
    
    $this->assertEquals($URL, 'https://www.paystation.co.nz/direct/paystation.dll');
    $this->assertTrue(isset($paystationID));
    $this->assertTrue(isset($gatewayID));
  }
  
  // public function testPaymentAuthoriseSuccess() {

  // 	$this->data['mock'] = 'success';
		// $this->processor->capture($this->data);
		
		// $payment = Payment::get()->byID($this->processor->payment->ID);
		// $this->assertEquals($payment->Status, Payment::PENDING);
  // }
  
  public function testPaymentAuthoriseFailure() {

  	$this->data['mock'] = 'failure';
		$this->processor->capture($this->data);

		$payment = Payment::get()->byID($this->processor->payment->ID);
		$this->assertEquals($payment->Status, Payment::FAILURE);
  }
}