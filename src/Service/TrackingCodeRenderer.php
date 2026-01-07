<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfig;
use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;

/**
 * Service for rendering Matomo tracking JavaScript code
 *
 * Generates the complete Matomo tracking script with all configured options
 * including cookieless tracking, IP anonymization, and consent handling.
 */
final class TrackingCodeRenderer
{
    public function __construct(
        private readonly MatomoConfigFactory $configFactory,
    ) {
    }

    /**
     * Render the complete Matomo tracking code for a sales channel
     *
     * @param string|null $salesChannelId Sales channel ID
     * @return string JavaScript code (without script tags)
     */
    public function render(?string $salesChannelId = null): string
    {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isValid()) {
            return '';
        }

        return $this->renderFromConfig($config);
    }

    /**
     * Render tracking code from a MatomoConfig instance
     *
     * @param MatomoConfig $config Configuration object
     * @return string JavaScript code (without script tags)
     */
    public function renderFromConfig(MatomoConfig $config): string
    {
        if (!$config->isValid()) {
            return '';
        }

        $lines = [
            'var _paq = window._paq = window._paq || [];',
        ];

        // Privacy settings
        $lines = array_merge($lines, $this->renderPrivacySettings($config));

        // Tracking settings
        $lines = array_merge($lines, $this->renderTrackingSettings($config));

        // Page tracking
        $lines[] = '_paq.push(["trackPageView"]);';

        // Link and download tracking
        if ($config->shouldTrackLinks() || $config->shouldTrackDownloads()) {
            $lines[] = '_paq.push(["enableLinkTracking"]);';
        }

        // Tracker initialization
        $lines = array_merge($lines, $this->renderTrackerInit($config));

        return implode("\n", $lines);
    }

    /**
     * Render the script tag wrapper for the tracking code
     */
    public function renderWithScriptTag(?string $salesChannelId = null): string
    {
        $code = $this->render($salesChannelId);

        if ($code === '') {
            return '';
        }

        return sprintf('<script>%s</script>', $code);
    }

    /**
     * Render privacy-related settings
     *
     * @return array<string>
     */
    private function renderPrivacySettings(MatomoConfig $config): array
    {
        $lines = [];

        // Cookieless tracking
        if ($config->isCookielessTracking()) {
            $lines[] = '_paq.push(["disableCookies"]);';
        }

        // Do-Not-Track support
        if ($config->shouldRespectDoNotTrack()) {
            $lines[] = '_paq.push(["setDoNotTrack", true]);';
        }

        // Consent requirement
        if ($config->requiresConsent()) {
            $lines[] = '_paq.push(["requireConsent"]);';
        }

        return $lines;
    }

    /**
     * Render tracking behavior settings
     *
     * @return array<string>
     */
    private function renderTrackingSettings(MatomoConfig $config): array
    {
        $lines = [];

        // Heartbeat timer
        if ($config->isHeartbeatEnabled()) {
            $lines[] = sprintf(
                '_paq.push(["enableHeartBeatTimer", %d]);',
                $config->getHeartbeatInterval()
            );
        }

        return $lines;
    }

    /**
     * Render the tracker initialization code
     *
     * @return array<string>
     */
    private function renderTrackerInit(MatomoConfig $config): array
    {
        $matomoUrl = $config->getNormalizedMatomoUrl();
        $siteId = $config->getSiteId();

        return [
            '(function() {',
            sprintf('  var u="%s/";', $this->escapeJs($matomoUrl)),
            '  _paq.push(["setTrackerUrl", u+"matomo.php"]);',
            sprintf('  _paq.push(["setSiteId", %d]);', $siteId),
            '  var d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0];',
            '  g.async=true; g.src=u+"matomo.js"; s.parentNode.insertBefore(g,s);',
            '})();',
        ];
    }

    /**
     * Escape string for safe JavaScript inclusion
     *
     * Uses json_encode which properly handles:
     * - Quotes and special characters
     * - Unicode characters
     * - Newlines
     * - Script tag breaking (</script>)
     *
     * Returns escaped value WITHOUT surrounding quotes (for sprintf use)
     */
    private function escapeJs(string $value): string
    {
        // json_encode handles all escaping properly, including </script> -> <\/script>
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($encoded === false) {
            return '';
        }

        // Remove surrounding quotes as we add them in sprintf
        return substr($encoded, 1, -1);
    }
}
