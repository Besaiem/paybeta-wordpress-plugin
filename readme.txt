=== Paybeta Payment Gateway ===
Contributors: paybeta
Tags: woocommerce, payment, gateway, nigeria, ngn, escrow, paystack, flutterwave
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments via Paybeta in your WooCommerce store — cards, bank transfers, USSD, and escrow protection.

== Description ==

**Paybeta** is a Nigerian fintech platform that combines traditional payment processing with built-in escrow protection. This plugin adds Paybeta as a payment option in your WooCommerce checkout.

= How it works =

1. Customer selects "Pay with Paybeta" at checkout and places the order.
2. They are redirected to a Paybeta-hosted payment page where they complete payment (card, bank transfer, or USSD via Paystack or Flutterwave).
3. After payment the customer returns to your store and the order is automatically marked as paid.
4. Paybeta also sends a webhook to your store for real-time order updates.

= Features =

* One-click redirect checkout — no complex frontend integration required.
* Supports NGN (Naira) and other currencies.
* Sandbox / live mode toggle — test without real payments.
* HMAC-SHA512 webhook signature verification.
* WooCommerce High-Performance Order Storage (HPOS) compatible.
* Zero external PHP dependencies — uses WordPress core HTTP API.

= Requirements =

* WordPress 6.0+
* WooCommerce 7.0+
* PHP 8.0+
* An active Paybeta merchant account — sign up at [paybeta.com](https://paybeta.com).

== Installation ==

1. Upload the `paybeta-payment-gateway` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → Payments** and click **Paybeta**.
4. Fill in your Merchant ID and API key (from your Paybeta dashboard).
5. Copy the **Webhook URL** shown on the settings page and paste it into your Paybeta dashboard under webhook settings.
6. Set your webhook secret in both places.
7. Enable the gateway and save.

== Configuration ==

= Sandbox mode =
Enable **Sandbox Mode** and enter your **Test API Key** to process test payments without real money.
Disable sandbox and enter your **Live API Key** before going live.

= Webhook URL =
Your webhook endpoint is automatically generated:
`https://yourstore.com/?wc-api=paybeta_webhook`

Paste this into the Paybeta merchant dashboard under **Settings → Webhooks**.
Copy the webhook secret from the dashboard into the **Webhook Secret** field in this plugin.

== Frequently Asked Questions ==

= Which currencies are supported? =
Paybeta primarily serves NGN (Nigerian Naira). The plugin passes your WooCommerce store currency to the API.

= What payment methods are available? =
Card, bank transfer, USSD — the specific methods available depend on your Paybeta plan and selected PSP (Paystack or Flutterwave).

= Is escrow supported? =
Yes. Paybeta's Growth and Enterprise plans include escrow. Escrow logic is managed on the Paybeta platform; WooCommerce sees a simple paid/failed status.

= Where do I find my Merchant ID and API key? =
Log in to your Paybeta dashboard at [app.paybeta.com](https://app.paybeta.com), go to **Settings → API Keys**.

= What happens if the webhook fails to deliver? =
Paybeta retries webhook delivery. The return URL also performs an independent status check, so most orders will update correctly even without a webhook.

== Screenshots ==

1. Paybeta gateway settings page in WooCommerce admin.
2. Paybeta payment method displayed at WooCommerce checkout.
3. Paybeta-hosted payment page (card/bank transfer/USSD).

== Changelog ==

= 1.0.0 =
* Initial release.
* Hosted payment link checkout flow.
* Webhook handler with HMAC-SHA512 verification.
* Sandbox / live mode toggle.
* HPOS compatibility declared.
