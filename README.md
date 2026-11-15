# WPFH Freemius Bridge for WooCommerce

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce HPOS](https://img.shields.io/badge/WooCommerce%20HPOS-Compatible-green.svg)](https://woocommerce.com/)
[![Release](https://img.shields.io/badge/Release-v1.1.0-purple.svg)](https://github.com/WpForgeHub/wpfh-freemius-bridge/releases/tag/v1.1.0)

**WPFH Freemius Bridge** bridges the gap between your self-hosted WooCommerce store and Freemius Merchant of Record (MoR).

---

## ⚡ What Does It Do?

1. **Webhook Receiver**: Receives real-time events from Freemius (`payment.created`, `license.created`, `subscription.cancelled`).
2. **Customer & License Sync**: Automatically creates WordPress customer accounts and stores license keys in user metadata for instant customer portal access.
3. **Shadow WooCommerce Orders**: Creates completed orders in WooCommerce for unified store reporting and customer order history.
4. **Hybrid SaaS Pricing Table**: Responsive 3-tier pricing table (`[wpfh_freemius_pricing]`) with annual/lifetime toggle and 1-click modal checkout.

---

## 🚀 Pro Features

* **Automated Webhook Replay**: Retry failed webhooks automatically with exponential backoff.
* **Real-Time Multi-Currency**: Geo-localized pricing for international buyers.
* **Agency License Delegation**: Let agencies assign sub-licenses to client domains.
* **Affiliate & Referral Tracking**: Pass tracking cookies to Freemius checkout modals.

---

## 📄 License

GPL-2.0+ &copy; [WPForgeHub](https://wpforgehub.com).
