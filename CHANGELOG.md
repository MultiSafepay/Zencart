## 3.1.0-RC1
Release date: Jun 22th, 2020

### Added
+ DAVAMS-213: Add track & trace to shipment request
+ PLGZENS-71: Add Apple Pay
+ PLGZENS-72: Add Direct Bank Transfer
+ PLGZENS-44: Add Santander Betaal per Maand
+ PLGZENS-51: Add plugin version to backend
+ PLGZENS-43: Add AfterPay
+ PLGZENS-46: Add Trustly
+ PLGZENS-47: Add Alipay
+ PLGZENS-24: Add Belfius, KBC and ING Home'Pay

### Fixed 
+ Adjust prices with currency rate for multicurrency
+ Fix missing tax table when shipping is free
+ PLGZENS-73: Fix incorrect shipping tax in shopping cart
+ PLGZENS-58: Fix product details missing in confirmation email
+ PLGZENS-53: Fix cannot save orders when images are enabled
+ PLGZENS-50: Fix error when not choosing an iDEAL issuer at "Select your bank" screen
+ PLGZENS-64: Fix orders getting an incorrect status

### Changed
+ Bank transfer, Klarna, iDEAL, Pay After Delivery, E-Invoicing are now direct only
+ PLGZENS-28: Let Zen Cart handle order saving
+ PLGZENS-65: Add 'MultiSafepay' to "Updated by" on order status update
+ Add quantity to the items list in the transaction request
+ PLGZENS-41: Update Klarna logo
+ PLGZENS-29: Send shopping cart data for all payment methods when creating transaction
+ PLGZENS-54: Set order to status shipped for all payment methods

### Removed
+ PLGZENS-31: Remove unused admin folder
+ PLGZENS-60: Remove giftcards Lief, ParfumNL, Nationale Erotiekbon
+ PLGZENS-74: Remove FerBuy

***

## 3.0.0
Release date: Jul 17th, 2019

## Added
+ Add all currently available payment methods and giftcards
+ Add translations for Dutch, French, German, Italian, Portuguese and Spanish
+ Add a setting for “days_active”; automatically close an unpaid order without transaction after X number of days.
+ Add a setting to enable or disable payment method icons during the checkout process.

## Fixed
+ Resolved an issue causing the homepage to be shown, rather than the order confirmation page, after a successful order.

## Changed
+ Changed the plugin to make use of the JSON API, rather than the XML API.
