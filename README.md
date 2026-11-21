# ForgeHub Freemius Bridge

<p align="center">
  <img src="assets/banner-1544x500.png" alt="Freemius Bridge banner" width="100%" />
</p>

[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-v1.2.1-purple.svg)](https://github.com/WpForgeHub/forgehub-freemius-bridge/releases/tag/v1.2.1)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)
[![Tested](https://img.shields.io/badge/Tested-WP%207.1-brightgreen.svg)](https://wordpress.org/)
[![wp.org](https://img.shields.io/badge/wp.org-pending%20review-orange.svg)](https://wordpress.org/plugins/)
[![Freemius](https://img.shields.io/badge/Freemius-Pro%20available-0d9488.svg)](https://wpforgehub.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Zero%20Bloat-06b6d4.svg)](https://wpforgehub.com/)

<p align="center">
  <a href="https://github.com/WpForgeHub/forgehub-freemius-bridge/releases/latest/download/forgehub-freemius-bridge-free.zip">
    <img src="https://img.shields.io/badge/📥_Download_Free_ZIP-v1.2.1-2563eb?style=for-the-badge&logo=wordpress&logoColor=white" alt="Download Free ZIP" />
  </a>
  <a href="https://wpforgehub.com/plugins/freemius-bridge/">
    <img src="https://img.shields.io/badge/🌐_Plugin_Homepage-WPForgeHub-0f172a?style=for-the-badge" alt="Plugin Page" />
  </a>
</p>

> ⚡ **Synchronize Freemius transactions, subscriptions, and licenses seamlessly with WooCommerce and Customer Portal.**

---

## ⚡ What Does It Do?

1. **Webhook Receiver**: Receives real-time events from Freemius (`payment.created`, `license.created`, `subscription.cancelled`).
2. **Customer & License Sync**: Automatically creates WordPress customer accounts and stores license keys in user metadata for instant customer portal access.
3. **Shadow WooCommerce Orders**: Creates completed orders in WooCommerce for unified store reporting and customer order history.
4. **Hybrid SaaS Pricing Table**: Responsive 3-tier pricing table (`[wpfh_freemius_pricing]`) with annual/lifetime toggle and 1-click modal checkout.

---

## 🚀 Key Features (Free Version)

* **Secure Webhook Verification**: Cryptographic HMAC-SHA256 signature verification.
* **Auto Customer & License Association**: Maps incoming Freemius orders to WordPress users.
* **Modern Responsive Pricing Table**: Dark mode ready with annual/lifetime switch.
* **WooCommerce Shadow Order Creation**: Automatically mirrors Freemius sales in your WooCommerce analytics.
* **HPOS Compatible**: 100% compatible with WooCommerce High-Performance Order Storage.

---

## 📊 Free vs Pro Comparison

| Feature | Community Free | Pro Commercial |
| :--- | :---: | :---: |
| HMAC-SHA256 Webhook Verification | ✅ | ✅ |
| Customer Account Provisioning | ✅ | ✅ |
| WooCommerce Shadow Orders | ✅ | ✅ |
| Responsive 3-Tier SaaS Pricing Table | ✅ | ✅ |
| Automated Dead-Letter Webhook Replay | ❌ | ✅ |
| Real-Time Multi-Currency Geolocation | ❌ | ✅ |
| Agency Team & Client Seat Manager | ❌ | ✅ |
| Affiliate & Referral Tracking Bridge | ❌ | ✅ |

---

## 🛠️ Installation

1. Upload the `forgehub-freemius-bridge` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WooCommerce -> Settings -> Freemius Bridge** to configure webhook credentials.

---

## 📄 License

GPLv2 or later. See [license.txt](license.txt) for details.
