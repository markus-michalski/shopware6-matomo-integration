# Shopware 6 Matomo Analytics Plugin

## Projekt-Info

- **Name:** MmdMatomoAnalytics
- **Vendor:** mmd
- **Namespace:** `Mmd\MatomoAnalytics`
- **Shopware Version:** 6.5.x / 6.6.x (später 6.7.x)
- **PHP Version:** 8.1+

## Entwicklungs-Kontext

Matomo-Server: `https://analytics.markus-michalski.net`
osTicket: #735988

## Architektur

```
src/
├── Configuration/        # Value Objects und Factories
│   ├── MatomoConfig.php            # Immutable Config VO
│   └── MatomoConfigFactory.php     # Factory aus SystemConfig
├── Service/              # Business Logic
│   ├── TrackingCodeRenderer.php    # JS-Code Generator
│   ├── EcommerceTracker.php        # E-Commerce Events
│   └── ConsentChecker.php          # DSGVO/Consent Logik
├── Subscriber/           # Event Handling
│   ├── StorefrontRenderSubscriber.php
│   ├── ProductPageSubscriber.php
│   ├── CheckoutSubscriber.php
│   └── OrderCompletedSubscriber.php
├── Twig/                 # Template Functions
│   └── MatomoExtension.php
└── Storefront/Controller/
    └── MatomoOptOutController.php
```

## Wichtige Config-Keys

```
MmdMatomoAnalytics.config.matomoUrl
MmdMatomoAnalytics.config.siteId
MmdMatomoAnalytics.config.trackingEnabled
MmdMatomoAnalytics.config.cookielessTracking
MmdMatomoAnalytics.config.ipAnonymizationLevel
MmdMatomoAnalytics.config.respectDoNotTrack
MmdMatomoAnalytics.config.requireConsent
MmdMatomoAnalytics.config.ecommerceEnabled
```

## Twig Functions

```twig
{{ matomo_tracking_code() }}          {# Raw JS code #}
{{ matomo_tracking_script() }}        {# With <script> tags #}
{{ matomo_opt_out_iframe() }}         {# Opt-Out iFrame #}
{{ matomo_is_enabled() }}             {# bool #}
{{ matomo_ecommerce_enabled() }}      {# bool #}
```

## Test-Strategie

1. **Unit Tests:** Configuration, Services (ohne Shopware-Dependencies)
2. **Integration Tests:** Subscriber, Controller (mit Shopware Test-Framework)

## Befehle

```bash
# Tests ausführen
./vendor/bin/phpunit

# PHPStan
./vendor/bin/phpstan analyse src tests

# Plugin installieren (in Shopware)
bin/console plugin:refresh
bin/console plugin:install --activate MmdMatomoAnalytics
```
