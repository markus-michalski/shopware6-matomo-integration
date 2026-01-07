<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for checking user consent and Do-Not-Track status
 *
 * Provides multi-layer consent checking supporting:
 * - Do-Not-Track browser header
 * - Shopware Cookie Consent
 * - Common third-party consent managers
 */
final class ConsentChecker
{
    /**
     * Cookie names for common consent managers
     */
    private const CONSENT_COOKIES = [
        'matomo_consent',           // Custom Matomo consent cookie
        'CookieConsent',            // Cookiebot
        'CookieControl',            // Civic Cookie Control
        'cookie-agreed',            // Shopware default
    ];

    public function __construct(
        private readonly MatomoConfigFactory $configFactory,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Check if tracking is allowed for the current request
     *
     * @param string|null $salesChannelId Sales channel ID
     */
    public function isTrackingAllowed(?string $salesChannelId = null): bool
    {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return false;
        }

        // Check Do-Not-Track header if configured
        if ($config->shouldRespectDoNotTrack() && $this->isDoNotTrackEnabled($request)) {
            return false;
        }

        // If cookieless tracking is enabled, no consent needed
        if ($config->isCookielessTracking()) {
            return true;
        }

        // If consent is required, check for it
        if ($config->requiresConsent()) {
            return $this->hasTrackingConsent($request);
        }

        return true;
    }

    /**
     * Check if Do-Not-Track header is set
     */
    public function isDoNotTrackEnabled(Request $request): bool
    {
        $dnt = $request->headers->get('DNT');

        // DNT: 1 means user requests not to be tracked
        return $dnt === '1';
    }

    /**
     * Check if user has given tracking consent
     */
    public function hasTrackingConsent(Request $request): bool
    {
        // Check Shopware's built-in cookie consent
        if ($this->hasShopwareCookieConsent($request)) {
            return true;
        }

        // Check common consent manager cookies
        foreach (self::CONSENT_COOKIES as $cookieName) {
            if ($this->hasCookieConsent($request, $cookieName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check Shopware's built-in cookie consent system
     */
    private function hasShopwareCookieConsent(Request $request): bool
    {
        $cookiePreferences = $request->cookies->get('cookie-preference');

        if ($cookiePreferences === null) {
            return false;
        }

        // Shopware stores preferences as JSON
        $preferences = json_decode($cookiePreferences, true);

        if (!is_array($preferences)) {
            return false;
        }

        // Check for statistics/analytics consent group
        // Matomo should be registered in the 'statistics' group
        return isset($preferences['statistics']) && $preferences['statistics'] === true;
    }

    /**
     * Check for consent in a specific cookie
     */
    private function hasCookieConsent(Request $request, string $cookieName): bool
    {
        $cookie = $request->cookies->get($cookieName);

        if ($cookie === null) {
            return false;
        }

        // Simple check: cookie exists and is not explicitly "0" or "false"
        $value = strtolower((string) $cookie);

        return $value !== '0' && $value !== 'false' && $value !== '';
    }
}
