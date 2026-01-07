<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Configuration;

/**
 * Immutable Value Object for Matomo configuration
 *
 * Encapsulates all plugin configuration values with type safety
 * and validation. This is the single source of truth for config values.
 */
final class MatomoConfig
{
    public function __construct(
        private readonly string $matomoUrl,
        private readonly int $siteId,
        private readonly bool $trackingEnabled,
        private readonly bool $cookielessTracking,
        private readonly int $ipAnonymizationLevel,
        private readonly bool $respectDoNotTrack,
        private readonly bool $requireConsent,
        private readonly bool $useKlaroConsent,
        private readonly string $klaroServiceName,
        private readonly bool $ecommerceEnabled,
        private readonly bool $trackProductViews,
        private readonly bool $trackCartUpdates,
        private readonly bool $trackOrders,
        private readonly bool $trackAdminUsers,
        private readonly bool $enableHeartbeatTimer,
        private readonly int $heartbeatInterval,
        private readonly bool $trackLinks,
        private readonly bool $trackDownloads,
    ) {
    }

    public function getMatomoUrl(): string
    {
        return $this->matomoUrl;
    }

    /**
     * Get the Matomo URL without trailing slash
     */
    public function getNormalizedMatomoUrl(): string
    {
        return rtrim($this->matomoUrl, '/');
    }

    /**
     * Get the tracking endpoint URL
     */
    public function getTrackingUrl(): string
    {
        return $this->getNormalizedMatomoUrl() . '/matomo.php';
    }

    /**
     * Get the JavaScript tracker URL
     */
    public function getJsTrackerUrl(): string
    {
        return $this->getNormalizedMatomoUrl() . '/matomo.js';
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function isTrackingEnabled(): bool
    {
        return $this->trackingEnabled;
    }

    public function isCookielessTracking(): bool
    {
        return $this->cookielessTracking;
    }

    public function getIpAnonymizationLevel(): int
    {
        return $this->ipAnonymizationLevel;
    }

    public function shouldRespectDoNotTrack(): bool
    {
        return $this->respectDoNotTrack;
    }

    public function requiresConsent(): bool
    {
        return $this->requireConsent;
    }

    /**
     * Check if Klaro consent manager integration is enabled
     */
    public function usesKlaroConsent(): bool
    {
        return $this->useKlaroConsent;
    }

    /**
     * Get the Klaro service name for this tracking service
     */
    public function getKlaroServiceName(): string
    {
        return $this->klaroServiceName;
    }

    public function isEcommerceEnabled(): bool
    {
        return $this->ecommerceEnabled;
    }

    public function shouldTrackProductViews(): bool
    {
        return $this->trackProductViews;
    }

    public function shouldTrackCartUpdates(): bool
    {
        return $this->trackCartUpdates;
    }

    public function shouldTrackOrders(): bool
    {
        return $this->trackOrders;
    }

    public function shouldTrackAdminUsers(): bool
    {
        return $this->trackAdminUsers;
    }

    public function isHeartbeatEnabled(): bool
    {
        return $this->enableHeartbeatTimer;
    }

    public function getHeartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    public function shouldTrackLinks(): bool
    {
        return $this->trackLinks;
    }

    public function shouldTrackDownloads(): bool
    {
        return $this->trackDownloads;
    }

    /**
     * Check if configuration is valid for tracking
     *
     * Validates:
     * - Tracking is enabled
     * - Matomo URL is a valid HTTPS URL
     * - Site ID is positive
     */
    public function isValid(): bool
    {
        return $this->trackingEnabled
            && $this->isValidMatomoUrl()
            && $this->siteId > 0;
    }

    /**
     * Validate that Matomo URL is a proper HTTPS URL
     *
     * Security: Prevents XSS through malformed URLs
     */
    private function isValidMatomoUrl(): bool
    {
        if ($this->matomoUrl === '') {
            return false;
        }

        // Must be a valid URL
        if (filter_var($this->matomoUrl, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // Must use HTTPS (security requirement)
        if (!str_starts_with($this->matomoUrl, 'https://')) {
            return false;
        }

        // Must not contain dangerous characters that could break JS
        if (preg_match('/[<>"\'\\\\]/', $this->matomoUrl)) {
            return false;
        }

        return true;
    }

    /**
     * Check if any E-Commerce feature is enabled
     */
    public function hasAnyEcommerceTracking(): bool
    {
        return $this->ecommerceEnabled
            && ($this->trackProductViews || $this->trackCartUpdates || $this->trackOrders);
    }
}
