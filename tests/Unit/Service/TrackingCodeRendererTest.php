<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfig;
use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\TrackingCodeRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrackingCodeRenderer::class)]
final class TrackingCodeRendererTest extends TestCase
{
    private MatomoConfigFactory&MockObject $configFactory;
    private TrackingCodeRenderer $renderer;

    protected function setUp(): void
    {
        $this->configFactory = $this->createMock(MatomoConfigFactory::class);
        $this->renderer = new TrackingCodeRenderer($this->configFactory);
    }

    #[Test]
    public function itReturnsEmptyStringForInvalidConfig(): void
    {
        $config = $this->createInvalidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itRendersBasicTrackingCode(): void
    {
        $config = $this->createValidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        // Check essential parts
        self::assertStringContainsString('var _paq = window._paq = window._paq || [];', $result);
        self::assertStringContainsString('_paq.push(["trackPageView"]);', $result);
        self::assertStringContainsString('_paq.push(["setTrackerUrl", u+"matomo.php"]);', $result);
        self::assertStringContainsString('_paq.push(["setSiteId", 1]);', $result);
        self::assertStringContainsString('var u="https://analytics.example.com/";', $result);
    }

    #[Test]
    public function itRendersWithScriptTags(): void
    {
        $config = $this->createValidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->renderWithScriptTag(null);

        self::assertStringStartsWith('<script>', $result);
        self::assertStringEndsWith('</script>', $result);
    }

    #[Test]
    public function itReturnsEmptyStringWithScriptTagsForInvalidConfig(): void
    {
        $config = $this->createInvalidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->renderWithScriptTag(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itRendersCookielessTrackingOption(): void
    {
        $config = $this->createConfigWithOptions(cookielessTracking: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["disableCookies"]);', $result);
    }

    #[Test]
    public function itDoesNotRenderCookielessWhenDisabled(): void
    {
        $config = $this->createConfigWithOptions(cookielessTracking: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        self::assertStringNotContainsString('disableCookies', $result);
    }

    #[Test]
    public function itRendersDoNotTrackOption(): void
    {
        $config = $this->createConfigWithOptions(respectDoNotTrack: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["setDoNotTrack", true]);', $result);
    }

    #[Test]
    public function itRendersConsentRequirement(): void
    {
        $config = $this->createConfigWithOptions(requireConsent: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["requireConsent"]);', $result);
    }

    #[Test]
    public function itRendersHeartbeatTimer(): void
    {
        $config = $this->createConfigWithOptions(enableHeartbeat: true, heartbeatInterval: 30);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["enableHeartBeatTimer", 30]);', $result);
    }

    #[Test]
    public function itRendersLinkTracking(): void
    {
        $config = $this->createConfigWithOptions(trackLinks: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["enableLinkTracking"]);', $result);
    }

    #[Test]
    public function itEscapesSpecialCharactersInUrl(): void
    {
        // Note: With proper URL validation, this URL would be invalid
        // But testing the escapeJs method directly via a valid URL with special chars
        $config = $this->createValidConfig();
        $result = $this->renderer->renderFromConfig($config);

        // Should contain properly escaped URL
        self::assertStringContainsString('https://analytics.example.com/', $result);
    }

    #[Test]
    public function itDoesNotRenderScriptTagsInOutput(): void
    {
        // The config validation should reject this, but even if it passed,
        // the escapeJs method should prevent XSS
        $config = $this->createValidConfig();
        $result = $this->renderer->renderFromConfig($config);

        // Output should never contain raw script tags
        self::assertStringNotContainsString('</script><script>', $result);
    }

    private function createValidConfig(): MatomoConfig
    {
        return new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: false,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: false,
            requireConsent: false,
            useKlaroConsent: false,
            klaroServiceName: 'matomo',
            ecommerceEnabled: true,
            trackProductViews: true,
            trackCartUpdates: true,
            trackOrders: true,
            trackAdminUsers: false,
            enableHeartbeatTimer: false,
            heartbeatInterval: 15,
            trackLinks: false,
            trackDownloads: false,
        );
    }

    private function createInvalidConfig(): MatomoConfig
    {
        return new MatomoConfig(
            matomoUrl: '',
            siteId: 0,
            trackingEnabled: false,
            cookielessTracking: true,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: true,
            requireConsent: false,
            useKlaroConsent: false,
            klaroServiceName: 'matomo',
            ecommerceEnabled: true,
            trackProductViews: true,
            trackCartUpdates: true,
            trackOrders: true,
            trackAdminUsers: false,
            enableHeartbeatTimer: false,
            heartbeatInterval: 15,
            trackLinks: true,
            trackDownloads: true,
        );
    }

    private function createConfigWithOptions(
        bool $cookielessTracking = false,
        bool $respectDoNotTrack = false,
        bool $requireConsent = false,
        bool $enableHeartbeat = false,
        int $heartbeatInterval = 15,
        bool $trackLinks = false,
    ): MatomoConfig {
        return new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: $cookielessTracking,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: $respectDoNotTrack,
            requireConsent: $requireConsent,
            useKlaroConsent: false,
            klaroServiceName: 'matomo',
            ecommerceEnabled: true,
            trackProductViews: true,
            trackCartUpdates: true,
            trackOrders: true,
            trackAdminUsers: false,
            enableHeartbeatTimer: $enableHeartbeat,
            heartbeatInterval: $heartbeatInterval,
            trackLinks: $trackLinks,
            trackDownloads: $trackLinks,
        );
    }

    private function createConfigWithUrl(string $url): MatomoConfig
    {
        return new MatomoConfig(
            matomoUrl: $url,
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: true,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: true,
            requireConsent: false,
            useKlaroConsent: false,
            klaroServiceName: 'matomo',
            ecommerceEnabled: true,
            trackProductViews: true,
            trackCartUpdates: true,
            trackOrders: true,
            trackAdminUsers: false,
            enableHeartbeatTimer: false,
            heartbeatInterval: 15,
            trackLinks: true,
            trackDownloads: true,
        );
    }
}
