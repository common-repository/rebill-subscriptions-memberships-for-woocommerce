=== Rebill Subscription for WooCommerce ===
Contributors: Rebill, kijam
Tags: rebill, ecommerce, mercadopago, woocommerce
Requires at least: 4.9.10
Tested up to: 5.8
Requires PHP: 5.6
Stable tag: 1.0.12
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Subscriptions & memberships management solution for companies selling in Latin America.

== Description ==
The official Rebill plugin allows you to process recurring payments in Latin American local currencies for your online store.

To install it, you don’t need to have technical knowledge: you can follow step-by-step instructions on how to integrate it from our customer support portal and start processing recurring payments today.

= WHAT TO DO WITH THE REBILL PLUGIN? =
- Charge subscriptions in your local currency connected to your WooCommerce!
- Allow your customers to combine multiple one-time purchases and subscriptions in the same cart!
- Receive orders automatically from each subscription and directly in your WooCommerce.
- Allow your customers to manage their subscriptions from their “My Account” section.
- Custom email templates automatically notify your customers before any changes to subscriptions and payments.
- Automatic retries to recover declined collections.
- Enable any SKU as a subscription.
- Define different prices for an SKU for one-time charge and subscription, and allow your customer to select the one they prefer on the product page. Avoid having to create or duplicate products for this.
- Define a free trial for each SKU.
- Define the exact day of payment from your clients.
- Redirect your client to the page you need after they subscribe.
- Edit active subscriptions without affecting your other subscribers. Avoid having to cancel them or contact your customers again. Easily modify its price, frequency, exact collection day, and status.
- Refund the money of an order to your client when you need it, directly from your WooCommerce.

**IMPORTANT:** Currently, the plugin only works connecting a Mercado Pago account with Rebill. You will receive the money from your sales directly on it.

= ADAPTED TO YOUR BUSINESS =

Prepared for any type of store and category: subscription e-commerce, digital goods and services, physical products, and whatever you want! Just focus on selling, and we’ll take care of the security as a PCI DSS certificated company.

Boost your recurring payments with Rebill Subscriptions & Memberships for WooCommerce!

= PRICING =

Starter Plan:
USD 299/mo
Includes USD 50k/mo of revenue

**Processing more than USD 50k/mo? Chat with Us**

== Installation ==
= Minimum Technical Requirements =
* WordPress
* WooCommerce
* LAMP Environment (Linux, Apache, MySQL, PHP)
* SSL Certificate
* Additional configuration: safe_mode off, memory_limit higher than 256MB

Install the module in two different ways: automatically, from the "**Plugins**" section of WordPress, or manually, downloading and copying the plugin files into your directory.

Automatic Installation by WordPress admin
1. Access "**Plugins**" from the navigation side menu of your WordPress administrator.
2. Once inside Plugins, click on "**Add New**" and search for "**Rebill Subscription**" in the WordPress Plugin list
3. Click on "**Install**"

Done! It will be in the "Installed Plugins" section and from there you can activate it.

Manual Installation
1. Download from WordPress Module https://es.wordpress.org/plugins/rebill-subscriptions-memberships-for-woocommerce/
2. Unzip the folder and **rename** it to "**rebill-subscriptions-memberships-for-woocommerce**"
3. Copy the "**rebill-subscriptions-memberships-for-woocommerce**" file into your WordPress directory "**/wp-content/plugins/**".

Done!

= Installing this plugin does not affect the speed of your store! =

If you installed it correctly, you will see it in your list of "**Installed Plugins**" on the WordPress work area. Please proceed to activate it and then configure the Access token of your MercadoPago account.

=  Configuration =
Set up both the plugin and the checkouts you want to activate on your payment avenue. Follow these five steps instructions and get everything ready to receive payments:

1. Add your **credentials** to test the store and charge with your Rebill account.
2. Approve your account in order to charge.
3. Fill in the basic information of your business in the plugin configuration.
4. Set up **payment preferences** for your customers.
5. Access **advanced** plugin and checkout **settings** only when you want to change the default settings.

== Screenshots ==

== Changelog ==
= v1.0.12 (17/06/2022) =
- Important bugfixes for invalid subscription order
= v1.0.11 (10/06/2022) =
- Added Feature: Customer and Seller can modify shipping address in active subscription
- Added Feature: Allow only one subscription active by customer
- Added Feature: Allow only one subscription products by cart
= v1.0.10 (07/06/2022) =
- Fixed nonce check issues on some forms
- Fixed cancelled subscription
= v1.0.9 (04/05/2022) =
- Bugfix bad format of Add to Cart script
- Bugfix empty DNI/Country in some case
= v1.0.8 (28/04/2022) =
- Fix DNI request
- Added Billing Address in card payment
= v1.0.7 (17/03/2022) =
- Removed DNI request in MX
= v1.0.6 (10/02/2022) =
- Responsive template in request card
= v1.0.5 (12/01/2022) =
- Removed bugfix of API "hack" for invalid value of External Reference in Description
= v1.0.4 (10/01/2022) =
- Fix duplication payment
- Obfuscate CVV/Expiration credit card in Front-end.
= v1.0.3 (10/01/2022) =
- Fix identification type by country 
= v1.0.2 (30/12/2021) =
- Fix translations
= v1.0.1 (28/12/2021) =
- Initial Version