SilverStripe Payment Paymentstation Module
===========================================

**Work in progress**

Maintainer Contacts
-------------------
Frank Mullenger (frankmullenger_AT_gmail(dot)com)
* [Deadly Technology Blog](http://deadlytechnology.com/silverstripe/)
* [SwipeStripe Ecommerce](http://swipestripe.com)

Requirements
------------
* SilverStripe 3.0
* Payment module 1.0

Documentation
-------------
Paystation integration for payment module

Installation Instructions
-------------------------
1. Place this directory in the root of your SilverStripe installation and call it 'payment-paystation'.
2. Visit yoursite.com/dev/build?flush=1 to rebuild the database.

Usage Overview
--------------
1. Enable in your application YAML config

```yaml
PaymentGateway:
  environment:
    'dev'

PaymentProcessor:
  supported_methods:
    dev:
      - 'PaystationThreeParty'
    live:
      - 'PaystationThreeParty'
```
2. Configure using your PaymentExpress account details

```yaml
PaystationGateway_ThreeParty:
  live:
    authentication:
      paystation_id: 'Paystation ID'
      gateway_id: 'Paystation Gateway ID'
    site_description: 
      'Some site description'
    verify_ssl_mode:
      true
  dev:
    authentication:
      paystation_id: 'Paystation ID'
      gateway_id: 'Paystation Gateway ID'
    site_description: 
      'Some site description'
    verify_ssl_mode:
      false
```

3. Remember to ?flush=1 after changes to the config YAML files
