# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-01-07

### Added
- Initial release of MmdMatomoAnalytics plugin
- Basic Matomo tracking code integration
- E-Commerce tracking support (product views, cart updates, orders)
- Cookieless tracking mode for GDPR compliance without consent
- Do-Not-Track header support
- IP anonymization (configurable levels 0-3)
- Cookie consent integration (Shopware native)
- Klaro Consent Manager integration
- Multi-Sales-Channel support with individual configurations
- Heartbeat timer for accurate time-on-page tracking
- Outbound link and download tracking
- Twig extension for template integration
- Opt-out iframe widget support

### Security
- XSS prevention via json_encode with proper flags
- HTTPS-only URL validation for Matomo server
- Dangerous character filtering in URLs
