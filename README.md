# SilverStripe Payment Paymentstation Module

**Work in progress, some changes to the API still to come**

## Maintainer Contacts
*  [Frank Mullenger](https://github.com/frankmullenger)

## Requirements
* SilverStripe 3.0.x
* Payment module 1.0.x

## Documentation
Paystation integration for payment module. This module currently supports [3-party (hosted) processing](http://www.paystation.co.nz/Definitions) only, meaning payments are processed on the Paystation site.

## Installation Instructions
1. Place this directory in the root of your SilverStripe installation and call it 'payment-paystation'.
2. Visit yoursite.com/dev/build?flush=1 to rebuild the database.

## Usage Overview
Enable in your application YAML config (e.g: mysite/_config/payment.yaml):

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
Configure using your Paystation account details in the same file:

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

By default the gateway class can accept NZD, USD or GBP (see PaystationGateway_ThreeParty::$supportedCurrencies). Usually your Paystation account will be for a single currency that matches your merchant account. To specify this currency as the single acceptable currency alter the YAML config file e.g: a configuration that will only process payments in Australian dollars:

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
    supported_currencies:
      'AUD' : 'Australian Dollar'
  dev:
    authentication:
      paystation_id: 'Paystation ID'
      gateway_id: 'Paystation Gateway ID'
    site_description: 
      'Some site description'
    verify_ssl_mode:
      false
    supported_currencies:
      'AUD' : 'Australian Dollar'
```
Set the return URL on your Paystation account to: http://yoursite.com/PaystationProcessor_ThreeParty/complete/

**Note:** Remember to ?flush=1 after changes to the config YAML files.

