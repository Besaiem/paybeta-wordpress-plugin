# Changelog

All notable changes to the Paybeta WooCommerce Payment Gateway will be documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/) and this project adheres to [Semantic Versioning](https://semver.org/).


## 1.0.1 (2026-05-29)

## [1.0.0] - 2026-05-29

### Features

- Initial release of the official Paybeta WooCommerce Payment Gateway plugin
- Hosted payment link checkout flow — no PCI scope on merchant server
- Supports cards, bank transfers, and USSD via Paystack and Flutterwave
- Admin settings: sandbox/live toggle, live and test API keys, merchant ID, webhook secret
- `?wc-api=paybeta_return` endpoint — verifies payment status on customer return
- `?wc-api=paybeta_webhook` endpoint — receives and verifies Paybeta webhook events
- HMAC-SHA512 webhook signature verification with constant-time comparison
- WooCommerce High-Performance Order Storage (HPOS) compatibility declared
- Sandbox mode badge and admin notice in WooCommerce settings
- Structured WooCommerce order notes for all payment events
- PHP 8.0+ with zero external dependencies (uses WordPress core HTTP API)
