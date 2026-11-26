=== StaffWarden: Staff Activity Log for WooCommerce ===
Contributors: iqbal511138
Tags: woocommerce, activity log, staff, audit log, shop manager
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Records who changed prices, stock, orders, refunds, coupons, payment settings and user roles in your store, with a clear timeline and refund emails.

== Description ==

A price is suddenly wrong. An order was cancelled and nobody knows why. A refund went out that you did not approve. When several people work in your store, "who did this?" is hard to answer, and WooCommerce does not keep a record.

StaffWarden keeps that record for you, in plain language.

= What it records =

* **Products:** regular and sale price, stock, stock status, publish status, name and SKU, with the value before and after.
* **Orders:** every status change made by a person.
* **Refunds:** amount, reason and who made it.
* **Coupons:** new coupons and changes to the discount, limits and expiry.
* **Settings:** payment methods and key store settings like currency, guest checkout and the store email address. Secret keys are never stored, only the fact that they changed.
* **User roles:** anyone made shop manager or administrator.

Automatic changes, like stock going down after a sale, are left out, so the timeline only shows what people did.

= Also included =

* **Timeline** you can filter by area and by person.
* **Refund email** for refunds above an amount you choose.
* **Dashboard** with changes, money refunded, price cuts and active people this week.

= Privacy =

StaffWarden stores staff user IDs and the changed values. It does not store customer details or secret keys. No external requests.

== Installation ==

1. Install and activate StaffWarden. WooCommerce must be active.
2. Changes are recorded from now on. Open StaffWarden to see them.

== Frequently Asked Questions ==

= Does it record changes made before it was installed? =

No. WooCommerce does not keep that information.

= How long is the history kept? =

90 days by default. You can choose between 7 and 730 days.

= Does it slow down the store? =

No. It only writes a small row when a person changes something.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
