<?php
/**
 * Paystation 3 party gateway processor, payment is processed on the gateway.
 * Need to set return URL on paystation account to:
 * http://yoursite.com/PaystationProcessor_ThreeParty/complete/
 * 
 * http://www.paystation.co.nz/cms_show_download.php?id=8
 * 
 */
class PaystationProcessor_ThreeParty extends PaymentProcessor {

  /**
   * Process the payment, first authorising payment and saving transaction ID, then redirecting user
   * to Paystation to enter cc details etc.
   * 
   * @param Array $data
   */
  public function capture($data) {

    parent::capture($data);

    //First authorise the gateway and get the transaction ID back to save on the Payment object
    $transactionID = $this->gateway->authorise($this->paymentData);
    $this->payment->TransactionID = $transactionID;
    $this->payment->write();

    // Send a request to the gateway
    $result = $this->gateway->process($this->paymentData);

    //processing may not get to here if all goes smoothly, customer will be at the 3rd party gateway 
    //$result may be a PaymentGateway_Result, if failure throw exception with message from gateway result
    //PaymentTestPage can then use the error message in the Excpetion or call gateway->validate() to get validaiton messages

    if ($result && !$result->isSuccess()) {

      //Gateway did not respond or did not validate
      //Need to get the gateway response and save HTTP Status, errors etc. to Payment

      $this->payment->updateStatus($result);
      throw new Exception($result->message());

      //$this->doRedirect();
    }
  }

  /**
   * Complete a payment, checking the Paystation API for payment status. Endpoint where Paystation will
   * redirect the user's browser to after payment has been processed at paystation.
   * 
   * Request get variables are commonly:
   * ms - Merchant Session, a copy of the value sent to Paystation in the pstn_ms initiation variable, String
   * ti - Transaction ID, ID assigned to the transaction attempt by Paystation server, String
   * am - Amount of the transaction in cents, Int
   * ec - Error code indicating success or failure of the transaction, Int
   * em - Error message a descriptio of the error code, String (URL encoded)
   * merchant_ref - Merchant reference set on the gateway and passed to Paystation, String 
   * 
   * @param SS_HTTPRequest $request
   */
  public function complete($request) {

    // Retrieve the payment object if none is referenced at this point
    if (!$this->payment) {
      $this->payment = $this->getPaymentObject($request);
    }

    // Reconstruct the gateway object
    $methodName = $this->payment->Method;
    $this->gateway = PaymentFactory::get_gateway($methodName);

    // Query the gateway for the payment result
    $result = $this->gateway->check($request);
    $this->payment->updateStatus($result);

    $this->doRedirect();
  }

  /**
   * Get Payment based on current request using transaction ID
   * 
   * @param SS_HTTPRequest $request
   * @return Paystation Subclass of Payment
   */
  public function getPaymentObject($request) {

    $transactionID = $request->getVar('ti');
    return Paystation::get()->filter('TransactionID', $transactionID)->first();
  }
}
