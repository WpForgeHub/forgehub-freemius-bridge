=== ForgeHub Freemius Bridge ===
Contributors: wpforgehub
Tags: freemius, woocommerce, webhook, license manager, saas bridge
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronize Freemius transactions, subscriptions, and licenses seamlessly with WooCommerce and Customer Portal.

== Description ==

Selling WordPress plugins via Freemius Merchant of Record (MoR) gives you global tax/VAT compliance and automated updates. However, integrating Freemius with your own WordPress website and customer account portal is often a custom headache.

**WPFH Freemius Bridge** connects Freemius and WooCommerce into a unified software sales powerhouse:

* **Instant Webhook Sync**: Listens to payments, subscriptions, and license issuances. Automatically matches or creates customer accounts, attaches license keys to customer profiles, and generates shadow WooCommerce orders for reporting.
* **Hybrid 3-Tier SaaS Pricing Table**: Shortcode `[wpfh_freemius_pricing]` generates an annual/lifetime billing toggle and 1-site, 5-site, and unlimited-site pricing tiers with direct Freemius Checkout modal triggers.
* **Seamless Customer Portal Sync**: Works in concert with `wpfh-customer-portal` so buyers immediately see their license keys and download links after purchasing via Freemius.

### Key Features (Free Version)
* **Secure Webhook Verification**: Cryptographic HMAC-SHA256 signature verification.
* **Auto Customer & License Association**: Maps incoming Freemius orders to WordPress users.
* **Modern Responsive Pricing Table**: Dark mode ready with annual/lifetime switch.
* **WooCommerce Shadow Order Creation**: Automatically mirrors Freemius sales in your WooCommerce analytics.
* **HPOS Compatible**: 100% compatible with WooCommerce High-Performance Order Storage.

### Pro Features
* **Automated Webhook Replay**: Retry failed webhooks automatically with exponential backoff.
* **Multi-Currency Live Rates**: Auto-detect customer currency (EUR, GBP, AUD, CAD).
* **Multi-Seat License Delegation**: Allow agencies to assign seats to client sites.
* **Affiliate & Referral Cookie Bridge**: Carry tracking parameters into Freemius Checkout.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wpfh-freemius-bridge`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your Freemius Secret Key and Plugin ID under **WooCommerce > Settings > Freemius Bridge**.
4. Add the Webhook URL (`https://yourdomain.com/wp-json/wpfh/v1/freemius-webhook`) to your Freemius Developer Dashboard.

== Changelog ==


= 1.2.0 =
* Refactor: Removed all hardcoded store IDs, keys, catalog maps, and prices; 100% generic architecture.
* Feature: Added WooCommerce Product Data "Freemius Bridge" tab and per-variation Freemius mapping fields (Edition, Billing Cycle, Licenses, Pricing ID, Label, Subtitle, Badge).
* Enhancement: Hybrid pricing selector now reads all prices and billing cycles directly from WooCommerce product variations and binds exact variation IDs to checkout.

= 1.1.3 =
* Rename: plugin is now "ForgeHub Freemius Bridge" (ForgeHub naming standard).
* Fix: Plugins screen now shows a consistent "Settings" link.
= 1.1.0 =
* Feature (Pro): Automated Webhook Replay Queue with exponential backoff and dead-letter admin controls.
* Feature (Pro): Real-time Multi-Currency rate converter (USD, EUR, GBP, CAD, AUD).
* Feature (Pro): Agency Multi-Seat delegation and child seat allocation.
* Feature (Pro): Affiliate Cookie Bridge with 30-day tracking cookie persistence.
* Enhancement: Freemius license filter integration (fs_can_use_premium_code__wpfh-freemius-bridge).

= 1.0.0 =
* Initial public release.
* HMAC-verified webhook endpoint.
* Customer profile license syncing.
* Shortcode `[wpfh_freemius_pricing]`.
* WooCommerce order synchronization.
