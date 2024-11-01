## 2.0.3 (2023-01-24)
Bugfixes:
   - Ensure the WooCommerce session is available
   - Create a new order if current ID has been cancelled
   - Correct the array key for refunds
   - Correct the PSR-4 autoload name for Yith Gift Cards
Dev:
   - Added more logging

## 2.0.2 (2023-01-16)
Bugfixes:
   - Corrected comparison for order sync
   - Removed unused setting
   - Added missing translations
Dev:
   - Corrected changelog version for 2.0.1 

## 2.0.1 (2023-01-13)
Bugfixes:
   - Corrected call to esc_html__()
   - Catch the output from other sources before sending response to Svea in validation_callback

## 2.0.0 (2023-01-11)
Features:
   - Major rewrite of code
   - No longer needs the \[svea_checkout\] shortcode
   - No longer needs a seperate checkout page
   - Added global merchant

## 1.18.3 (2022-08-24)
Bugfixes:
   - Correctly map the payment methods Swish, Vipps and Mobilepay
   - Better delivery for orders in Svea Payment Admin when orders are without shipping

## 1.18.2 (2022-05-23)
Dev:
   - Allow filters to change the cleanup interval
   - Updated supported versions

## 1.18.1 (2022-04-12)
Dev:
   - Updated supported versions

## 1.18.0 (2021-01-11)
Dev:
   - Created a compat-class for easier compatability fixes in the future
Features:
   - Partiall support for YITH Coupons free version only
Bugfixes:
   - Changed order of sync and hook for better compability
## 1.17.2 (2020-10-07)

Bugfixes:
   - Observe postcode changes even if checkout is loaded earlier

## 1.17.1 (2020-09-21)

Bugfixes:
   - Update part pay logo
   - Fix line calculations

## 1.17.0 (2020-09-11)

Features:
   - Redirect to standard WooCommerce checkout page if cart total is 0

Bugfixes:
   - Properly calculate credit amounts when products have many decimals

## 1.16.0 (2020-08-05)

Features:
   - Shortcode for part payment widget

Bugfixes:
   - Create new order when there's a change in currency

## 1.15.1 (2020-05-14)

Bugfixes:
   - Handle problems with crediting orders that contain order rows with many decimals
   - Properly fetch the order row ID when adding order rows to an order

## 1.15.0 (2020-04-14)

Features:
   - Storing and displaying company reg numbers on view order page

## 1.14.3 (2020-04-10)

Features:
   - Optimizations

## 1.14.2 (2020-04-06)

Bugfixes:
   - Multisite support

## 1.14.1 (2020-03-27)

Features:
   - Checkout performance improvements

## 1.14.0 (2020-03-24)

Features:
   - Add support for WooCommerce 4.0.0
   - Add support for multisite network activation

## 1.13.2 (2020-02-03)

Bugfixes:
   - Split customer reference to populate first- and lastname fields on company orders

## 1.13.1 (2019-09-26)

Bugfixes:
   - Change format of do_action for woocommerce checkout order processed

## 1.13.0 (2019-06-27)

Features:
   - Add support for Denmark
   - Start logging pushes to add warnings in the future

## 1.12.0 (2019-06-18)

Features:
   - Add filter to enable reload of checkout
   
Bugfixes:
   - Target the Svea-checkout-form for changes to prevent unneeded ajax-requests

## 1.11.0 (2019-04-24)

Features:
   - Add filter to toggle order rows-sync after completed order
   
Bugfixes:
   - Add compatibility with WooCommerce 3.6
   
## 1.10.0 (2019-03-13)

Features:
   - Add ability to hide elements in the Svea iframe through settings

## 1.9.0 (2019-02-14)

Features:
   - On order status "final" sync invoice fee with WooCommerce
   - Add support for costs added by Svea to be synced with WooCommerce
   
## 1.8.0 (2019-02-01)

Features:
   - Add setting to keep cancelled orders
   
Bugfixes:
   - Prevent empty cancelled orders from showing up in the order list

## 1.7.2 (2019-01-03)

Features:
   - Upgrade Svea integration package
   - Test with WordPress 5.0.2
   - Changes to sync of unfinished orders

## 1.7.1 (2018-11-08)

Bugfixes:
   - Fix calculations for whether or not to display part payment widget on products

## 1.7.0 (2018-10-08)

Features:
   - New hooks added to checkout template enabling support for WooCommerce Subscribe to Newsletter and other third party plugins adding fields to the checkout
   - Add support for part payment widget on products single page
   - Add support to disable ZIP code syncing between Svea and WooCommerce

## 1.6.0 (2018-09-28)

Features:
   - New hooks enabling modification of orders and custom post meta
   - Add versioning to template files to inform of modifications
   - Add support for WooCommerce shipping calculator
   - Add JavaScript-event to enable triggering of checkout reload from plugins

## 1.5.0 (2018-06-21)

Features:
   - Selective syncing, only replace iframe if order-ID changes
   - Different shipping depending on postal code entered
   - Refactor Javascript, optimize
   - Add more documentation to code
   - Upgrade integration-package from Svea
   - Add Apache 2.0 license
   - Speed improvements for checkout

Bugfixes:
   - Change description on settings page
   - Prevent switching country while refreshing checkout

## 1.4.2 (2018-05-07)

Bugfixes:
   - Rework sync-method to prevent duplicate order e-mails and order stock reduction caused by simultaneous requests by customer and Svea

## 1.4.1 (2018-04-17)

Bugfixes:
   - Set customer shipping address to billing address upon changing country to display the correct shipping methods for the selected country
   
## 1.4.0 (2018-04-16)

Features:

   - Add country-selector in checkout, enabling purchases from multiple countries. Payments requiring a credit check (such as invoice) will need a matching currency for the selected country. For instance you can pay with invoice if the country selected i Sweden and the currency is SEK.
   - You can now choose which preset values that should be read-only, making it possible for logged-in users to enter different zip-codes than used in the first purchase made in the shop.
   - Ability to choose the default customer type
   - Merchant-info is now validated upon saving on the settings page for Svea Checkout, quickly sorting out issues with credentials

Bugfixes:

   - Sleep Svea push to prevent duplicate order confirmations when push is made at the same time as the user displays the confirmation page
   - Orders are now only updated if something has changed, removing problems caused by themes using empty url() in some CSS properties that would cause the page to be reloaded instantly in the background
   - Fix locale in checkout-iframe
   - Correctly sync all address info, fixing issues with some fields that were only entered in shipping and not in billing.

## 1.3.0 (2017-11-30)

Features:

   - Functionality to edit already processed orders, making it possible to change order row quantities and amounts or adding discounts afterwards
   - Possibility to choose if payments should only be processed for companies, individuals or both
   - Integration and testing with "WooCommerce Google Analytics Integration"
   - Display message if WooCommerce decimals is set to less than 2 to prevent problems with rounding
   

Bugfixes:

   - Only update order status once when the order is final to prevent duplicate e-mails sent to the end user

## 1.2.2 (2017-10-19)

Bugfixes:

   - Double orders when using WooCommerce Sequential Order Numbers
   
Features:

   - Delete orders that are more than 14 days old
   - Translation for SVEACARDPAY and SVEACARDPAY_PF
   
## 1.2.1 (2017-10-05)

Bugfixes:

   - Include WooCommerce CSS-classes on body element

## 1.2.0 (2017-10-02)

Features:

   - Support for WooCommerce Sequential Order Numbers
   - Include Company name in confirmation mail
   - Add fees to order
   - Display Svea payment method in admin for orders and on the confirmation page
   - Display payment reference in admin for orders and on confirmation page

## 1.1.1 (2017-05-29)

Features:

   - Styling to support more WordPress themes
   - Code cleanup and optimization   
   
Bugfixes:

   - Fixed error on confirmation page for non logged in users

## 1.1.0 (2017-05-29)

Features:

  - Add support for WooCommerce 3.0
  - Remove sync on cart update to prevent fraudulent behaviour
  - Styling to support different Wordpress themes
  - Code cleanup and optimization

Bugfixes:

   - Fix crediting of order calculations, fix discount tax division and add shipping last in cart items
   - Always hide incomplete orders from WooCommerce order list
   - Disallow searching for Svea Checkout hidden orders


## 1.0.4b (2017-05-08)

Features:

  - Add support for WooCommerce 2.6
  - Sync address regardless of status and save fullname for companies as the company name
  - Code cleanup and optimization

Bugfixes:

  - Fixed discount codes not being removable
  - Add cash rounding if total amount differs in Svea and WooCommerce
  - Correct filenames for languagefiles
  - Change conditions to prevent faulty redirections

## 1.0.0b (2017-03-20) - Beta Release

- Initial release of Svea Checkout for WooCommerce

