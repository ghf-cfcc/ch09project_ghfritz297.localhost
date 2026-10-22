=== Ever Checkout – Sell Digital Products & Downloads ===
Contributors: byteever, pluginever, manikmist09
Tags: sell digital products, digital downloads, digital store, buy now button, payments
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Put a buy button on any page and sell digital products from WordPress. Your customer pays, then downloads the file from their receipt.

== Description ==

Ever Checkout lets you sell digital products from your WordPress site without setting up a full online store. Add a product, attach the file and put a buy button on any page. Your customer pays, lands on a receipt page with the download link, and gets the same link by email.

Products, orders and customers live in your own WordPress database, and payments go straight to the account you connect.

= How it works =

1. Add a product with a name, a price and one or more files.
2. Place the buy button. Use the block in the editor, or the `[ecout_buy_button]` shortcode in any theme or page builder.
3. Your customer enters a name and an email address and pays. There's no account to create.
4. The receipt page and the receipt email both carry the download links.
5. You follow orders, customers and sales from your WordPress dashboard.

= What you can sell =

Ebooks and PDFs, software, plugins and themes, music and sample packs, photos and illustrations, video lessons and course files, printables, templates, fonts and presets. Anything you can deliver as a file works.

= Features =

**Selling**

* Unlimited products, each with one or more files
* A buy button block and a shortcode
* Guest checkout: a name and an email address are all your customer types
* A receipt page and a receipt email, both with the download links
* Any currency, with prices formatted for your locale
* Product import from a CSV file, with a sample file included

**Payments**

* Card payments, including the wallets available in your customer's region, through Stripe
* Bank transfer, for orders you confirm by hand once the money arrives
* A test gateway that only you can see, so you can run a full purchase before going live
* Refunds from the orders screen

**Protected downloads**

* Files are kept in a protected folder outside the media library, under random names
* Every download is checked against its order when it is served, so a shared link does not share the file
* Download limits and link expiry, set for the whole store or per product
* A refunded order loses its downloads straight away

**Orders and customers**

* Orders with search and filters
* A customer record for every buyer, created at checkout
* A dashboard with your sales and a short list of what's left before you go live
* Checkouts that were never paid are cancelled automatically every day

= Who it's for =

Ever Checkout is for people who sell files: writers, developers, designers, musicians, photographers and teachers. It handles one-time purchases. If you need a cart, shipping, stock or subscriptions, a full store plugin is the better fit.

= Your data stays on your site =

There is no outside dashboard and no hosted store. Card details never reach your site, because your customer pays on the payment provider's own page. Customer data works with the WordPress personal data export and erase tools, and suggested privacy policy text is added under Settings > Privacy.

== Installation ==

1. Install and activate Ever Checkout.
2. Go to Ever Checkout > Settings, pick your currency and turn on a payment gateway. Start with the test gateway to try a full purchase.
3. Add a product, attach its file and set a price.
4. Add the Ever Checkout buy button block to a page, or use `[ecout_buy_button id="123"]`, where 123 is the product ID.

== Frequently Asked Questions ==

= Do I need an ecommerce plugin to use Ever Checkout? =

No. Ever Checkout works on its own and doesn't depend on any other plugin.

= How do I sell an ebook or a PDF on my WordPress site? =

Add a product, attach the PDF and set a price, then place the buy button on a page. Your customer pays, and the download link appears on the receipt page and in the receipt email.

= Which payment methods are included? =

Stripe for cards and wallets, and bank transfer for payments you confirm by hand. A test gateway is included as well, visible only to you.

= Do my customers need an account? =

No. They enter a name and an email address and pay. They don't sign up for anything, so there's no password to forget.

= Can a customer share the download link? =

Not usefully. Each download is checked against the order when it's served, and you can cap how many times a file is downloaded and how long the link works.

= What happens when I refund an order? =

Refund it from the orders screen, or in your Stripe dashboard. For a bank transfer, set the order to refunded. In every case the order's download links stop working at once.

= Can I sell subscriptions or license keys? =

Not at the moment. Ever Checkout sells one-time purchases.

= Does the buy button work with my theme or page builder? =

Yes. Use the block in the block editor, or the shortcode anywhere else: classic themes, page builders, widgets and templates.

= Can I try it before taking real payments? =

Yes. Turn on the test gateway and run the whole flow, from checkout to receipt, email and download, with no money moving. Switch to a live gateway when you're happy.

= Where is my store data kept, and what is stored? =

In your own WordPress database. Each order stores the customer's email address, the IP address the checkout came from and the name and country the payment provider reports. Each download stores the IP address and browser that fetched it. The IP address is also used to limit how many checkouts one address can start in a minute.

= Is Ever Checkout translation ready? =

Yes. A translation template ships in the languages folder.

== Screenshots ==

1. The dashboard, with your sales and what's left before you go live
2. Products, with search and filters
3. Orders, with search and filters
4. Customers, with a record for every buyer
5. Store settings
6. Payment settings
7. Email settings
8. Download settings
9. Advanced settings
10. The buy button block in the editor
11. Checkout, where your customer enters a name and an email address and pays
12. The receipt page with the download links

== External services ==

Ever Checkout connects to a payment provider only after you turn on its gateway and enter your own account keys.

**Stripe**, by Stripe, Inc., takes card payments and refunds. [Terms of service](https://stripe.com/legal/ssa), [privacy policy](https://stripe.com/privacy).

* When a customer starts a checkout, the plugin sends their email address, the product name, price, currency and quantity, the order number and order key, and the addresses on your site the customer returns to after paying or cancelling.
* When Stripe reports a payment to your site, the plugin asks Stripe for that payment's details to confirm the amount and the payment method. Stripe sends the payer's email address, name and country, which are saved on the order and the customer record.
* When you refund an order, the plugin sends the payment reference, the amount and your note.

Card details never reach your site. Errors returned by Stripe are written to a log file in the plugin's protected uploads folder.

== Changelog ==

= 1.0.0 =
* New: First release.
