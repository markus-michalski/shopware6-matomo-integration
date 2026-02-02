<?php

/**
 * Matomo Analytics for Shopware 6
 *
 * @package   Mmd\MatomoAnalytics
 * @author    Markus Michalski
 * @copyright 2024-2025 Markus Michalski
 * @license   Proprietary - see LICENSE file for details
 */

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Configuration;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Factory for creating MatomoConfig instances from SystemConfig
 *
 * Encapsulates the mapping between Shopware's SystemConfigService
 * and the typed MatomoConfig value object.
 */
final class MatomoConfigFactory
{
    private const CONFIG_PREFIX = 'MmdMatomoAnalytics.config.';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * Create configuration for a specific sales channel
     *
     * @param string|null $salesChannelId Sales channel ID or null for default
     */
    public function createForSalesChannel(?string $salesChannelId = null): MatomoConfig
    {
        return new MatomoConfig(
            matomoUrl: $this->getString('matomoUrl', $salesChannelId),
            siteId: $this->getInt('siteId', $salesChannelId),
            trackingEnabled: $this->getBool('trackingEnabled', $salesChannelId),
            cookielessTracking: $this->getBool('cookielessTracking', $salesChannelId, true),
            ipAnonymizationLevel: $this->getInt('ipAnonymizationLevel', $salesChannelId, 2),
            respectDoNotTrack: $this->getBool('respectDoNotTrack', $salesChannelId, true),
            requireConsent: $this->getBool('requireConsent', $salesChannelId),
            useKlaroConsent: $this->getBool('useKlaroConsent', $salesChannelId),
            klaroServiceName: $this->getString('klaroServiceName', $salesChannelId, 'matomo'),
            ecommerceEnabled: $this->getBool('ecommerceEnabled', $salesChannelId, true),
            trackProductViews: $this->getBool('trackProductViews', $salesChannelId, true),
            trackCartUpdates: $this->getBool('trackCartUpdates', $salesChannelId, true),
            trackOrders: $this->getBool('trackOrders', $salesChannelId, true),
            trackAdminUsers: $this->getBool('trackAdminUsers', $salesChannelId),
            enableHeartbeatTimer: $this->getBool('enableHeartbeatTimer', $salesChannelId),
            heartbeatInterval: $this->getInt('heartbeatInterval', $salesChannelId, 15),
            trackLinks: $this->getBool('trackLinks', $salesChannelId, true),
            trackDownloads: $this->getBool('trackDownloads', $salesChannelId, true),
        );
    }

    private function getString(string $key, ?string $salesChannelId, string $default = ''): string
    {
        $value = $this->systemConfigService->get(self::CONFIG_PREFIX . $key, $salesChannelId);

        return is_string($value) ? $value : $default;
    }

    private function getInt(string $key, ?string $salesChannelId, int $default = 0): int
    {
        $value = $this->systemConfigService->get(self::CONFIG_PREFIX . $key, $salesChannelId);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function getBool(string $key, ?string $salesChannelId, bool $default = false): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_PREFIX . $key, $salesChannelId);

        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }
}
