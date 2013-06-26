<?php

/**
 * Paystation 3 party gateway, payment is processed on the gateway.
 * 
 * http://www.paystation.co.nz/cms_show_download.php?id=8
 */
class PaystationGateway_ThreeParty extends PaymentGateway_GatewayHosted {

  protected $supportedCurrencies = array(
    'NZD' => 'New Zealand Dollar',
    'USD' => 'United States Dollar',
    'GBP' => 'Great British Pound'
  );
  
  public function getSupportedCurrencies() {

    $config = $this->getConfig();
    if (isset($config['supported_currencies'])) {
      $this->supportedCurrencies = $config['supported_currencies'];
    }
    return $this->supportedCurrencies;
  }

  /**
   * Error code (ec) and error message (em) details
   */
  public $errorCodes = array(
    0 => 'No error - transaction successful', 
    1 => 'Transaction Declined - Bank Error', 
    2 => 'Bank declined transaction', 
    3 => 'Transaction Declined - No Reply from Bank', 
    4 => 'Transaction Declined - Expired Card', 
    5 => 'Transaction Declined - Insufficient funds', 
    6 => 'Transaction Declined - Error Communicating with Bank', 
    7 => 'Payment Server Processing Error - Typically caused by invalid input data such as an invalid credit card number. Processing errors can also occur', 
    8 => 'Transaction Declined - Transaction Type Not Supported', 
    9 => 'Bank Declined Transaction (Do not contact Bank)', 
    10 => 'Purchase amount less or greater than merchant values', 
    11 => 'Paystation couldn’t create order based on inputs', 
    12 => 'Paystation couldn’t find merchant based on merchant ID', 
    13 => 'Transaction already in progress', 
    22 => 'Merchant_Ref contains invalid characters', 
    23 => 'Merchant Session (ms) contains invalid characters', 
    25 => 'URL encoding error', 
    26 => 'Invalid Amount', 
  );

  /**
   * URL to redirect to on PayStation site in order to pay for this order
   * 
   * @var String
   */
  public $digitalOrder;

  /**
   * Useful to match payments in local DB to payments in Paystation DB
   * 
   * @var String Per order identifier
   */
  public $merchantRef;

  /**
   * Authorise a payment, retrieves a transaction ID to save against the Payment and sets the URL that should
   * be used when redirecting user to Paystation to enter cc details etc.
   * 
   * @see self::$digitalOrder
   * @param Array $data
   * @return String Transaction ID from Paystation
   */
  public function authorise($data) {
  	
  	$paystationTransactionID = null;

    //Do Transaction Initiation POST
    $initiationResult = $this->makeAuthoriseRequest($data);

    $p = xml_parser_create();
    xml_parse_into_struct($p, $initiationResult, $vals, $tags);
    xml_parser_free($p);
    
    //Analyze the resulting XML from Transaction Initiation POST
    for ($j=0; $j < count($vals); $j++) {
      
      //Get URL to redirect to on PayStation site in order to pay for this order
      if (strcasecmp($vals[$j]["tag"], "DIGITALORDER") == 0 && isset($vals[$j]["value"])){
        $this->digitalOrder = $vals[$j]["value"];
      }
      
      //Get Paystation Transaction ID for reference
      if (strcasecmp($vals[$j]["tag"], "PAYSTATIONTRANSACTIONID") == 0 && isset($vals[$j]["value"])){
        $paystationTransactionID = $vals[$j]["value"];
      }
      
      //Get Paystation Error message
      if (strcasecmp($vals[$j]["tag"], "PAYSTATIONERRORMESSAGE") == 0 && isset($vals[$j]["value"])){
        $paystationErrorMessage = $vals[$j]["value"];
      }
    }

    return $paystationTransactionID;
  }
  
  public function makeAuthoriseRequest($data) {
  	
  	//Authorise the payment, return transaction ID
    $config = $this->getConfig();
    $url = Config::inst()->get('PaystationGateway_ThreeParty', 'url');
    
    $paystationURL  = $url;
    $amount = $data['Amount'] * 100;
    $pstn_pi  = $config['authentication']['paystation_id']; 
    $pstn_gi  = $config['authentication']['gateway_id'];
    $testMode = $config['test_mode']; 
    $site = $config['site_description'];
    $merchantSession  = urlencode($site.'-'.time().'-'.$this->makePaystationSessionID(8,8)); //max length of ms is 64 char 
    $pstn_mr = $this->merchantRef;


    //Create URL to initiate transation with PayStation
    $paystationParams = "paystation&pstn_pi=".$pstn_pi.
                        "&pstn_gi=".$pstn_gi.
                        "&pstn_ms=".$merchantSession.
                        "&pstn_am=".$amount.
                        "&pstn_mr=".$pstn_mr.
                        "&pstn_nr=t";
    
    if ($testMode) $paystationParams = $paystationParams."&pstn_tm=t";
    
    return $this->directTransaction($paystationURL, $paystationParams);
  }

  /**
   * Process the payment by redirecting user to Paystation
   * 
   * @param Array $data
   */
  public function process($data) {

    if (!isset($this->digitalOrder) || !$this->digitalOrder) {
      return new PaymentGateway_Failure('No URL for payment to be processed');
    }
    Controller::curr()->redirect($this->digitalOrder);
    return;
  }

  /**
   * Check the payment by using quick lookup API to retrieve payment status
   * 
   * @param SS_HTTPRequest $request
   * @return PaymentGateway_Result
   */
  public function check($request) {

    $config = $this->getConfig();

    $url = $config['lookup_url'];
    $transactionID = $request->getVar('ti');
    $paystationID = $config['authentication']['paystation_id'];

    $service = new RestfulService($url);
    $service->setQueryString(array(
      'pi' => $paystationID,
      'ti' => $transactionID
    ));
    $response = $service->request();

    $paymentStatus = $response->xpath_one('LookupResponse/PaystationErrorCode');
    $paymentStatus = (string)$paymentStatus;

    if ($paymentStatus == 0) {
      return new PaymentGateway_Success();
    }
    else {
      return new PaymentGateway_Failure($errorCodes[$paymentStatus]);
    }
  }

  /**
   * Initiate transation with PayStation, this function mostly taken from PayStation directly for 
   * 3-party PHP demo.
   * 
   * If receiving a cURL error like: 'Curl error: SSL certificate problem, verify that the CA cert is OK.'
   * Possible that OpenSSL is not installed correctly. Can bypass certificate checks by setting:
   * curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
   * curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
   * 
   * @see PaystationHostedPayment::set_verify_ssl_mode()
   * @param String $url PayStation URL to POST to
   * @param String $params Data to POST
   * @return Mixed String|False On success returns string of XML respone from PayStation, failure returns false
   */
  protected function directTransaction ($url, $params){

    $userAgent = null;
    
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
      $userAgent = $_SERVER['HTTP_USER_AGENT'];
    }
    
    $defined_vars = get_defined_vars();
    if (isset($defined_vars['HTTP_USER_AGENT'])) {
      $userAgent = $defined_vars['HTTP_USER_AGENT'];
    }

    //POST data using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_URL, $url);
    
    $config = $this->getConfig();
    $verifySSLMode = isset($config['verify_ssl_mode']) ? $config['verify_ssl_mode'] : true;
    if ($verifySSLMode) {
      curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    else {
      curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }
    
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);

    //TODO change this to return the HTTP Response and errors can be handled in process()
    
    //Logging if curl errors
    if (curl_errno($ch)) {
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      SS_Log::log(new Exception(print_r("HTTP Code: $httpcode", true)), SS_Log::NOTICE);
      SS_Log::log(new Exception(print_r("Curl Error: ". curl_error($ch), true)), SS_Log::NOTICE);
    }

    curl_close ($ch);
    return $result; 
  }

  /**
   * Create a random string payment session ID, copied from PayStation 3-party PHP demo code.
   * 
   * @param Int $min
   * @param Int $max
   * @return String A new payment session ID
   */
  protected function makePaystationSessionID ($min = 8, $max = 8) {

    $seed = (double)microtime()*getrandmax();
    srand($seed);
  
    //Create string of $max characters with ASCII values of 40-122
    $pass = '';
    $p=0; while ($p < $max):
      $r=123-(rand()%75);
      $pass.=chr($r);
    $p++; endwhile;
  
    $pass=preg_replace("/[^a-zA-NP-Z1-9]+/","",$pass);
  
    //If string is too short, remake it
    if (strlen($pass)<$min):
      $pass=$this->makePaystationSessionID($min,$max);
    endif;
  
    return $pass;
  }

}

class PaystationGateway_ThreeParty_Mock extends PaystationGateway_ThreeParty {
	
	public function makeAuthoriseRequest($data) {

  	//Mock request string
    $mock = isset($data['mock']) ? $data['mock'] : false;
    if ($mock) {
    	switch($mock) {

	    	//Gateway could not be reached, curl_exec returns false
	    	case 'incomplete':
	    		$request_string = false;
	    		break;
	    	case 'failure':
	    		$request_string = '
					<InitiationRequestResponse>
						<Username>608622</Username>
						<RequestIP>121.74.176.232</RequestIP>
						<RequestUserAgent>Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:20.0) Gecko/20100101 Firefox/20.0</RequestUserAgent>
						<RequestHttpReferrer/>
						<PaymentRequestTime>2013-06-03 16:53:20</PaymentRequestTime>
						<DigitalOrder/>
						<DigitalOrderTime></DigitalOrderTime>
						<DigitalReceiptTime/>
						<PaystationTransactionID/>
					</InitiationRequestResponse>';
	    		break;
	    	case 'success':
	    	default:
					$request_string = '
					<InitiationRequestResponse>
						<Username>608622</Username>
						<RequestIP>121.74.176.232</RequestIP>
						<RequestUserAgent>Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:20.0) Gecko/20100101 Firefox/20.0</RequestUserAgent>
						<RequestHttpReferrer/>
						<PaymentRequestTime>2013-06-03 16:53:20</PaymentRequestTime>
						<DigitalOrder>https://payments.paystation.co.nz/hosted/?hk=nkuQnREHVcDJOI8NyoVDeUboEm5iMwniG9npMCrn2ns%3D</DigitalOrder>
						<DigitalOrderTime>2013-06-03 16:53:20</DigitalOrderTime>
						<DigitalReceiptTime/>
						<PaystationTransactionID>0021873828-01</PaystationTransactionID>
					</InitiationRequestResponse>';
	    		break;
	    }
    }
    else {
    	throw new Exception('Mock string not passed');
    }
    
    return $request_string;
  }

  /**
   * Check the payment by using quick lookup API to retrieve payment status
   * 
   * @param SS_HTTPRequest $request
   * @return PaymentGateway_Result
   */
  public function check($request) {

    $config = $this->getConfig();

    $url = $config['lookup_url'];
    $transactionID = $request->getVar('ti');
    $paystationID = $config['authentication']['paystation_id'];

    $service = new RestfulService($url);
    $service->setQueryString(array(
      'pi' => $paystationID,
      'ti' => $transactionID
    ));
    $response = $service->request();

    $paymentStatus = $response->xpath_one('LookupResponse/PaystationErrorCode');
    $paymentStatus = (string)$paymentStatus;

    if ($paymentStatus == 0) {
      return new PaymentGateway_Success();
    }
    else {
      return new PaymentGateway_Failure($errorCodes[$paymentStatus]);
    }
  }

}

