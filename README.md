# Paybeta Payment Gateway — WordPress Plugin

Official WooCommerce payment gateway plugin for [Paybeta](https://paybeta.com). Enables merchants to accept payments via cards, bank transfers, and USSD directly in their WooCommerce checkout — with built-in escrow protection.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Payment Flow](#payment-flow)
- [Webhooks](#webhooks)
- [Sandbox / Test Mode](#sandbox--test-mode)
- [Order Statuses](#order-statuses)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Development & Contributing](#development--contributing)
- [File Structure](#file-structure)

---

## Requirements

| Requirement    | Minimum version |
|----------------|----------------|
| WordPress      | 6.0            |
| WooCommerce    | 7.0            |
| PHP            | 8.0            |
| Paybeta account| Active merchant account at [paybeta.com](https://paybeta.com) |

---

## Installation

### From source (manual)

1. Clone or download this repository.
2. Copy (or symlink) the `@paybeta-wordpress` folder into your WordPress plugins directory and rename it to `paybeta-payment-gateway`:

   ```bash
   cp -r @paybeta-wordpress /path/to/wordpress/wp-content/plugins/paybeta-payment-gateway
   ```

3. Log in to your WordPress admin panel.
4. Go to **Plugins → Installed Plugins**.
5. Find **Paybeta Payment Gateway** and click **Activate**.

### From WordPress admin (zip upload)

1. Zip the `@paybeta-wordpress` folder.
2. In WordPress admin go to **Plugins → Add New → Upload Plugin**.
3. Select the zip file and click **Install Now**, then **Activate**.

---

## Configuration

After activating the plugin, open:

**WooCommerce → Settings → Payments → Paybeta**

### Settings reference

| Setting          | Description |
|------------------|-------------|
| **Enable/Disable** | Toggle the gateway on or off at checkout. |
| **Title**          | Label shown to customers at the checkout payment step. Default: *Pay with Paybeta*. |
| **Description**    | Short text shown below the payment method title at checkout. |
| **Sandbox Mode**   | When enabled, uses the Test API Key and no real money is charged. Disable before going live. |
| **Live API Key**   | Your production API key from the Paybeta dashboard (`pb_live_…`). |
| **Test API Key**   | Your sandbox API key from the Paybeta dashboard (`pb_test_…`). |
| **Merchant ID**    | Your Paybeta merchant UUID — found in the Paybeta dashboard under **Settings → Account**. |
| **Webhook Secret** | The signing secret for verifying incoming webhook payloads. See [Webhooks](#webhooks). |

### Getting your credentials

1. Log in to the Paybeta dashboard at [app.paybeta.com](https://app.paybeta.com).
2. Go to **Settings → API Keys** to find your Merchant ID and generate API keys.
3. Go to **Settings → Webhooks** to configure your webhook endpoint and retrieve the signing secret.

---

## Payment Flow

The plugin uses Paybeta's **hosted payment link** flow — no custom checkout form is embedded in your store. This means:

- No PCI compliance burden on your server.
- Paybeta handles the PSP selection (Paystack / Flutterwave) and checkout UI.
- Your store only needs to redirect the customer and handle the return.

### Step-by-step

```
Customer places order in WooCommerce
         │
         ▼
Plugin calls POST /payment-links on Paybeta API
  → stores token + paymentId on the WC order
  → redirects customer to checkoutUrl
         │
         ▼
Customer completes payment on Paybeta-hosted page
  (card, bank transfer, or USSD via Paystack/Flutterwave)
         │
         ▼
Customer redirected back to your store
  ?wc-api=paybeta_return&order_id=123
         │
         ▼
Plugin calls GET /payment-links/complete/{token}
  SUCCESS  → order marked "Processing", thank-you page shown
  FAILED   → error notice, customer returned to checkout
  PROCESSING → customer sees pending order page
         │
         ▼ (async, independent of above)
Paybeta fires webhook to your store
  ?wc-api=paybeta_webhook
  → signature verified
  → order updated if not already paid
```

### Amount conversion

WooCommerce stores prices as decimals (e.g. `1500.00`). The plugin automatically converts to the smallest currency unit before sending to the Paybeta API:

- NGN: `1500.00` → `150000` kobo
- USD: `15.00` → `1500` cents

---

## Webhooks

Webhooks provide reliable, asynchronous order updates independent of the customer return redirect. You should configure both.

### Setting up

1. Copy the **Webhook URL** shown on the Paybeta settings page in your WooCommerce admin:
   ```
   https://yourstore.com/?wc-api=paybeta_webhook
   ```
2. Paste this URL into the Paybeta dashboard under **Settings → Webhooks → Add Endpoint**.
3. Paybeta will display a signing secret — copy it.
4. Paste the signing secret into the **Webhook Secret** field in the plugin settings and save.

### Signature verification

Every webhook request from Paybeta carries an `X-Paybeta-Signature` header containing an HMAC-SHA512 hex digest of the raw request body. The plugin verifies this using PHP's `hash_hmac` + `hash_equals` (constant-time comparison) before processing any event.

Requests with an invalid or missing signature are rejected with HTTP 401.

### Handled events

| Event type              | WooCommerce action                          |
|-------------------------|---------------------------------------------|
| `payment.completed`     | Order marked **Processing** (paid)          |
| `transaction.funded`    | Order marked **Processing** (paid)          |
| `payment.failed`        | Order marked **Failed**                     |

All events are logged to the WooCommerce system log under the `paybeta` source:

**WooCommerce → Status → Logs → paybeta**

### Idempotency

The webhook handler checks `$order->is_paid()` before calling `payment_complete()` so duplicate deliveries are safely ignored.

---

## Sandbox / Test Mode

Enable **Sandbox Mode** in the plugin settings and enter your **Test API Key** to run end-to-end payment flows without charging real money.

- The checkout page will show a **[TEST MODE]** badge next to the gateway title in WooCommerce admin.
- An admin notice is displayed on the settings page as a reminder.
- Disable sandbox and switch to your **Live API Key** before going live.

> **Never use a live API key while sandbox mode is enabled** — the environment your key is scoped to is determined by the key prefix (`pb_live_` vs `pb_test_`), not the plugin's sandbox toggle.

---

## Order Statuses

| Paybeta payment status | WooCommerce order status |
|------------------------|--------------------------|
| Link created (pending) | **Pending payment**      |
| `SUCCESS` / `FUNDED`   | **Processing**           |
| `PROCESSING`           | **Pending payment**      |
| `FAILED` / `CANCELLED` | **Failed**               |

Once an order reaches **Processing**, WooCommerce's standard fulfilment flow takes over (stock reduction, fulfilment emails, etc.).

---

## Frequently Asked Questions

**Which currencies are supported?**

Paybeta primarily supports NGN (Nigerian Naira). The plugin sends your WooCommerce store currency to the API. Ensure your WooCommerce currency matches a currency Paybeta supports on your plan.

**What payment methods are available to customers?**

Card, bank transfer, and USSD — available methods depend on the PSP (Paystack or Flutterwave) and your Paybeta plan. The customer selects their method on the Paybeta-hosted payment page.

**Does this plugin support WooCommerce HPOS (High-Performance Order Storage)?**

Yes. The plugin explicitly declares compatibility with WooCommerce's custom order tables (HPOS) feature.

**What happens if the customer closes the browser before returning?**

The webhook will still fire and update the order status asynchronously. The customer can also re-visit their order in **My Account → Orders** and the status will reflect the webhook update.

**Can I offer refunds through WooCommerce?**

The gateway declares `refunds` support. Programmatic refund handling via the WooCommerce refund UI requires a Paybeta refund API endpoint. Currently, issue refunds from the Paybeta dashboard and manually update the WooCommerce order status.

**Where are webhook events logged?**

**WooCommerce → Status → Logs**. Select `paybeta` from the log source dropdown. All received events, order lookups, and status updates are recorded there.

**The payment was successful but the order still shows "Pending payment". What should I check?**

1. Confirm the webhook URL is correctly configured in the Paybeta dashboard.
2. Check that the **Webhook Secret** in the plugin settings matches the one in Paybeta.
3. Review the WooCommerce log (`paybeta` source) for signature errors or order lookup failures.
4. Ensure your server is publicly reachable at `?wc-api=paybeta_webhook` (not behind a firewall or on `localhost`).

---

## Development & Contributing

### File structure

```
@paybeta-wordpress/
├── paybeta-payment-gateway.php     Main plugin file (WP header + bootstrapper)
├── includes/
│   ├── class-paybeta-api.php       HTTP client — wraps wp_remote_request
│   ├── class-paybeta-gateway.php   WC_Paybeta_Gateway extends WC_Payment_Gateway
│   └── class-paybeta-webhook.php   Incoming webhook handler
├── assets/
│   └── icon.svg                    Payment method icon (shown at checkout)
├── readme.txt                      WordPress.org directory readme
├── .gitignore
└── README.md                       This file
```

### Key classes

#### `WC_Paybeta_Gateway` (`includes/class-paybeta-gateway.php`)

The main gateway class. Extends `WC_Payment_Gateway`.

| Method              | Responsibility |
|---------------------|---------------|
| `__construct()`     | Register gateway ID, title, hooks, and form fields |
| `init_form_fields()`| Define admin settings fields |
| `process_payment()` | Create Paybeta payment link and redirect customer |
| `handle_return()`   | Verify payment status on customer return |
| `handle_webhook()`  | Delegate incoming webhook to `Paybeta_Webhook_Handler` |
| `api()`             | Instantiate `Paybeta_API` with the active key |

#### `Paybeta_API` (`includes/class-paybeta-api.php`)

Thin HTTP client. Uses WordPress core `wp_remote_request` — no Composer, no curl directly.

| Method                   | API call |
|--------------------------|----------|
| `create_payment_link()`  | `POST /payment-links` |
| `get_payment_status()`   | `GET /payment-links/complete/{token}` |

Returns `array` on success, `WP_Error` on failure — follows WordPress conventions.

#### `Paybeta_Webhook_Handler` (`includes/class-paybeta-webhook.php`)

Reads the raw POST body from `php://input`, verifies the HMAC-SHA512 signature, and dispatches to the appropriate order update method.

### Coding standards

- PHP 8.0+ syntax (constructor property promotion, `match`, union types, `never` return type).
- Follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- All user-facing strings are wrapped in `__()` / `esc_html__()` for translation.
- No external dependencies. No Composer.

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
