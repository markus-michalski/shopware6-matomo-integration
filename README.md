# Shopware 6 Matomo Analytics

Privacy-friendly web analytics for Shopware 6.5/6.6 with E-Commerce tracking, Klaro Consent integration, and GDPR-compliant features.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![Shopware](https://img.shields.io/badge/Shopware-6.6%20%7C%206.6-blue.svg)](https://www.shopware.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## Features

- **Cookieless Tracking** - Track visitors without cookies for minimal data collection
- **IP Anonymization** - Configurable anonymization levels (1-4 bytes)
- **Do-Not-Track Respect** - Honor browser DNT settings
- **Klaro Integration** - Seamless integration with Klaro Cookie Consent Manager
- **E-Commerce Tracking** - Product views, cart updates, order completions
- **Multi-Sales-Channel** - Different configurations per Sales Channel
- **Heartbeat Timer** - Accurate time-on-page measurement
- **Link & Download Tracking** - Track outbound links and file downloads

## Requirements

- Shopware 6.6.x
- PHP 8.2+
- Valid License
- Matomo instance (self-hosted or cloud)

## Installation

### Via Composer

```bash
composer require mmd/sw66-matomo-analytics
bin/console plugin:install --activate MmdMatomoAnalytics
bin/console cache:clear
```

## Update

Update to the latest version:

```bash
composer update mmd/sw66-matomo-analytics
bin/console cache:clear
```

## Migration to Shopware 6.7

If you're migrating to Shopware 6.7, please note:

1. Uninstall this plugin (`mmd/sw66-matomo-analytics`)
2. Install the 6.7 version (`mmd/sw67-matomo-analytics`)
3. Your configuration settings will be preserved (same config keys)

## Configuration

Plugin configuration in Shopware Admin:
**Settings > Extensions > Matomo Analytics**

### Matomo Server

| Setting | Required | Description |
|---------|----------|-------------|
| Matomo URL | Yes | Your Matomo installation URL (e.g., `https://analytics.example.com`) |
| Site ID | Yes | The Site ID from your Matomo installation |
| Enable Tracking | Yes | Master switch for all tracking |

### Privacy Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Cookieless Tracking | On | Track without cookies - reduces stored data |
| IP Anonymization | 2 bytes | How many bytes of the IP address to mask |
| Respect Do-Not-Track | On | Honor browser DNT settings |

### E-Commerce Tracking

| Setting | Default | Description |
|---------|---------|-------------|
| Enable E-Commerce Tracking | On | Master switch for E-Commerce features |
| Track Product Views | On | Track when users view product detail pages |
| Track Cart Updates | On | Track add-to-cart and cart changes |
| Track Orders | On | Track completed orders with revenue |

## Klaro Consent Manager Integration

If you're using the **Klaro Cookie Consent** plugin:

1. Enable **Use Klaro Consent Manager** in Matomo settings
2. Set **Klaro Service Name** to `matomo`
3. Create a matching service in Klaro with technical name `matomo`

## E-Commerce Tracking Details

The plugin automatically tracks:

- **Product Views** - SKU, name, category, price
- **Cart Updates** - All cart products with quantities
- **Order Completions** - Order number, revenue, tax, shipping, products

## Documentation

Full documentation available at:

- **[Documentation Wiki](https://faq.markus-michalski.net/en/shopware6/matomo-integration)** (English)
- **[Dokumentation Wiki](https://faq.markus-michalski.net/de/shopware6/matomo-integration)** (Deutsch)

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Support

Markus Michalski - [support@markus-michalski.net](mailto:support@markus-michalski.net)
