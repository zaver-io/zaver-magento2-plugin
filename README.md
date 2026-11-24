# ZAVER PAYMENT MODULE FOR MAGENTO 2

The Zaver payment module supports Magento Open Source / Adobe Commerce 2.4.4 and later (PHP 7.4 to 8.4) in the following languages: <b>EN, DE</b>. Earlier Magento versions cannot install it: the Zaver PHP SDK needs Guzzle 7, which Magento ships from 2.4.4.

## Supported features

- One payment method *Zaver* whose checkout form lists the Zaver payment methods offered for the billing
  country, currency and cart amount; hosted Zaver checkout (redirect) with all line items.
- Zaver's status callbacks and the customer's return move the order: payment review (waiting for
  confirmation), processing (paid), complete (settled), cancelled; cancelling the order cancels the Zaver payment.
- Capture when a shipment is saved, for the shipped quantities with line items (partial shipments capture
  partially) with a paid invoice per capture; online refunds from credit memos, full or partial with line items.
- *Refresh Zaver status* on the order view with an order comment for every Zaver event; production and test
  credentials with a test mode switch at default or website scope.
- Not included yet: *Capture Online* on a manually created invoice (use shipments), a downloadable Zaver log
  (entries go to Magento's system log), Hyvä or GraphQL checkouts (Luma only), a financing badge or calculator.

## Installation

### Install via Composer 

Follow the below steps and run each command from the shop root directory:
 ##### 1. Run the below command to install the payment module
 From zip file: Copy the zip file 'zaver_module-payment-1.0.0.zip' to the shop root directory.
 ```
 composer config repositories.gclocal artifact ./
 composer require zaver/payment:1.0.0
 ```
 From GIT repository / packagist (not yet available).
 ```
 composer require zaver/payment:1.0.0
  ```

 ##### 2. Run the below command to enable the payment module 
 ```
  bin/magento module:status	
  bin/magento module:enable Zaver_Payment	
  bin/magento setup:upgrade	
  bin/magento setup:di:compile	
  bin/magento cache:clean	
  bin/magento cache:flush	
  ```

### Finalizing Steps
1. Log into the Magento Admin
2. Go to *Stores* / *Configuration*
3. Go to *Sales* / *Zaver Methods*
4. Enter your merchant connection data for the Zaver API.
5. Save the settings.
6. Enable the desired payment methods.
