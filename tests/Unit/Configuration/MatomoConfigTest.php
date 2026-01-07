<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Configuration;

use Mmd\MatomoAnalytics\Configuration\MatomoConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MatomoConfig::class)]
final class MatomoConfigTest extends TestCase
{
    #[Test]
    public function itCreatesValidConfiguration(): void
    {
        $config = $this->createDefaultConfig();

        self::assertSame('https://analytics.example.com', $config->getMatomoUrl());
        self::assertSame(1, $config->getSiteId());
        self::assertTrue($config->isTrackingEnabled());
    }

    #[Test]
    public function itNormalizesUrlWithTrailingSlash(): void
    {
        $config = $this->createConfigWithUrl('https://analytics.example.com/');

        self::assertSame('https://analytics.example.com', $config->getNormalizedMatomoUrl());
    }

    #[Test]
    public function itNormalizesUrlWithoutTrailingSlash(): void
    {
        $config = $this->createConfigWithUrl('https://analytics.example.com');

        self::assertSame('https://analytics.example.com', $config->getNormalizedMatomoUrl());
    }

    #[Test]
    public function itReturnsCorrectTrackingUrl(): void
    {
        $config = $this->createConfigWithUrl('https://analytics.example.com/');

        self::assertSame('https://analytics.example.com/matomo.php', $config->getTrackingUrl());
    }

    #[Test]
    public function itReturnsCorrectJsTrackerUrl(): void
    {
        $config = $this->createConfigWithUrl('https://analytics.example.com/');

        self::assertSame('https://analytics.example.com/matomo.js', $config->getJsTrackerUrl());
    }

    #[Test]
    #[DataProvider('provideValidConfigurations')]
    public function itValidatesConfiguration(
        string $url,
        int $siteId,
        bool $trackingEnabled,
        bool $expectedValid
    ): void {
        $config = new MatomoConfig(
            matomoUrl: $url,
            siteId: $siteId,
            trackingEnabled: $trackingEnabled,
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

        self::assertSame($expectedValid, $config->isValid());
    }

    /**
     * @return iterable<string, array{string, int, bool, bool}>
     */
    public static function provideValidConfigurations(): iterable
    {
        yield 'valid configuration' => ['https://example.com', 1, true, true];
        yield 'tracking disabled' => ['https://example.com', 1, false, false];
        yield 'empty url' => ['', 1, true, false];
        yield 'zero site id' => ['https://example.com', 0, true, false];
        yield 'negative site id' => ['https://example.com', -1, true, false];
        yield 'all invalid' => ['', 0, false, false];
        yield 'http not allowed' => ['http://example.com', 1, true, false];
        yield 'invalid url format' => ['not-a-url', 1, true, false];
        yield 'xss attempt with script' => ['https://evil.com/<script>', 1, true, false];
        yield 'xss attempt with quotes' => ['https://evil.com/"onclick="alert(1)', 1, true, false];
    }

    #[Test]
    public function itReturnsCorrectPrivacySettings(): void
    {
        $config = new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: true,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: true,
            requireConsent: true,
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

        self::assertTrue($config->isCookielessTracking());
        self::assertSame(2, $config->getIpAnonymizationLevel());
        self::assertTrue($config->shouldRespectDoNotTrack());
        self::assertTrue($config->requiresConsent());
    }

    #[Test]
    public function itReturnsCorrectEcommerceSettings(): void
    {
        $config = $this->createDefaultConfig();

        self::assertTrue($config->isEcommerceEnabled());
        self::assertTrue($config->shouldTrackProductViews());
        self::assertTrue($config->shouldTrackCartUpdates());
        self::assertTrue($config->shouldTrackOrders());
    }

    #[Test]
    public function itDetectsAnyEcommerceTrackingEnabled(): void
    {
        $config = $this->createDefaultConfig();

        self::assertTrue($config->hasAnyEcommerceTracking());
    }

    #[Test]
    public function itDetectsNoEcommerceTrackingWhenAllDisabled(): void
    {
        $config = new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: true,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: true,
            requireConsent: false,
            useKlaroConsent: false,
            klaroServiceName: 'matomo',
            ecommerceEnabled: true,
            trackProductViews: false,
            trackCartUpdates: false,
            trackOrders: false,
            trackAdminUsers: false,
            enableHeartbeatTimer: false,
            heartbeatInterval: 15,
            trackLinks: true,
            trackDownloads: true,
        );

        self::assertFalse($config->hasAnyEcommerceTracking());
    }

    #[Test]
    public function itDetectsNoEcommerceTrackingWhenEcommerceDisabled(): void
    {
        $config = new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: true,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: true,
            requireConsent: false,
            useKlaroConsent: false,
            klaroServiceName: 'matomo',
            ecommerceEnabled: false,
            trackProductViews: true,
            trackCartUpdates: true,
            trackOrders: true,
            trackAdminUsers: false,
            enableHeartbeatTimer: false,
            heartbeatInterval: 15,
            trackLinks: true,
            trackDownloads: true,
        );

        self::assertFalse($config->hasAnyEcommerceTracking());
    }

    #[Test]
    public function itReturnsCorrectAdvancedSettings(): void
    {
        $config = new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
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
            trackAdminUsers: true,
            enableHeartbeatTimer: true,
            heartbeatInterval: 30,
            trackLinks: false,
            trackDownloads: false,
        );

        self::assertTrue($config->shouldTrackAdminUsers());
        self::assertTrue($config->isHeartbeatEnabled());
        self::assertSame(30, $config->getHeartbeatInterval());
        self::assertFalse($config->shouldTrackLinks());
        self::assertFalse($config->shouldTrackDownloads());
    }

    private function createDefaultConfig(): MatomoConfig
    {
        return new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
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
