<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Configuration;

use Mmd\MatomoAnalytics\Configuration\MatomoConfig;
use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(MatomoConfigFactory::class)]
final class MatomoConfigFactoryTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;
    private MatomoConfigFactory $factory;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->factory = new MatomoConfigFactory($this->systemConfigService);
    }

    #[Test]
    public function itCreatesConfigWithDefaultValues(): void
    {
        $this->systemConfigService
            ->method('get')
            ->willReturnCallback(fn (string $key) => $this->getDefaultConfigValue($key));

        $config = $this->factory->createForSalesChannel(null);

        self::assertInstanceOf(MatomoConfig::class, $config);
        self::assertSame('https://analytics.example.com', $config->getMatomoUrl());
        self::assertSame(1, $config->getSiteId());
        self::assertTrue($config->isTrackingEnabled());
    }

    #[Test]
    public function itCreatesConfigForSpecificSalesChannel(): void
    {
        $salesChannelId = 'test-sales-channel-id';

        $this->systemConfigService
            ->expects(self::atLeastOnce())
            ->method('get')
            ->willReturnCallback(function (string $key, ?string $scId) use ($salesChannelId) {
                self::assertSame($salesChannelId, $scId);
                return $this->getDefaultConfigValue($key);
            });

        $this->factory->createForSalesChannel($salesChannelId);
    }

    #[Test]
    public function itUsesDefaultValuesForMissingConfig(): void
    {
        $this->systemConfigService
            ->method('get')
            ->willReturn(null);

        $config = $this->factory->createForSalesChannel(null);

        // Check defaults are applied
        self::assertSame('', $config->getMatomoUrl());
        self::assertSame(0, $config->getSiteId());
        self::assertFalse($config->isTrackingEnabled());
        self::assertTrue($config->isCookielessTracking());
        self::assertSame(2, $config->getIpAnonymizationLevel());
        self::assertTrue($config->shouldRespectDoNotTrack());
        self::assertFalse($config->requiresConsent());
        self::assertFalse($config->usesKlaroConsent());
        self::assertSame('matomo', $config->getKlaroServiceName());
        self::assertTrue($config->isEcommerceEnabled());
    }

    #[Test]
    public function itHandlesBooleanConfigValues(): void
    {
        $this->systemConfigService
            ->method('get')
            ->willReturnCallback(function (string $key) {
                return match ($key) {
                    'MmdMatomoAnalytics.config.trackingEnabled' => true,
                    'MmdMatomoAnalytics.config.cookielessTracking' => false,
                    'MmdMatomoAnalytics.config.respectDoNotTrack' => false,
                    'MmdMatomoAnalytics.config.requireConsent' => true,
                    default => $this->getDefaultConfigValue($key),
                };
            });

        $config = $this->factory->createForSalesChannel(null);

        self::assertTrue($config->isTrackingEnabled());
        self::assertFalse($config->isCookielessTracking());
        self::assertFalse($config->shouldRespectDoNotTrack());
        self::assertTrue($config->requiresConsent());
    }

    #[Test]
    public function itHandlesIntegerConfigValues(): void
    {
        $this->systemConfigService
            ->method('get')
            ->willReturnCallback(function (string $key) {
                return match ($key) {
                    'MmdMatomoAnalytics.config.siteId' => 42,
                    'MmdMatomoAnalytics.config.ipAnonymizationLevel' => 3,
                    'MmdMatomoAnalytics.config.heartbeatInterval' => 30,
                    default => $this->getDefaultConfigValue($key),
                };
            });

        $config = $this->factory->createForSalesChannel(null);

        self::assertSame(42, $config->getSiteId());
        self::assertSame(3, $config->getIpAnonymizationLevel());
        self::assertSame(30, $config->getHeartbeatInterval());
    }

    #[Test]
    public function itHandlesStringConfigAsInteger(): void
    {
        $this->systemConfigService
            ->method('get')
            ->willReturnCallback(function (string $key) {
                return match ($key) {
                    'MmdMatomoAnalytics.config.siteId' => '5',
                    default => $this->getDefaultConfigValue($key),
                };
            });

        $config = $this->factory->createForSalesChannel(null);

        self::assertSame(5, $config->getSiteId());
    }

    #[Test]
    public function itHandlesKlaroConsentConfig(): void
    {
        $this->systemConfigService
            ->method('get')
            ->willReturnCallback(function (string $key) {
                return match ($key) {
                    'MmdMatomoAnalytics.config.useKlaroConsent' => true,
                    'MmdMatomoAnalytics.config.klaroServiceName' => 'matomo-analytics',
                    default => $this->getDefaultConfigValue($key),
                };
            });

        $config = $this->factory->createForSalesChannel(null);

        self::assertTrue($config->usesKlaroConsent());
        self::assertSame('matomo-analytics', $config->getKlaroServiceName());
    }

    private function getDefaultConfigValue(string $key): mixed
    {
        return match ($key) {
            'MmdMatomoAnalytics.config.matomoUrl' => 'https://analytics.example.com',
            'MmdMatomoAnalytics.config.siteId' => 1,
            'MmdMatomoAnalytics.config.trackingEnabled' => true,
            'MmdMatomoAnalytics.config.cookielessTracking' => true,
            'MmdMatomoAnalytics.config.ipAnonymizationLevel' => 2,
            'MmdMatomoAnalytics.config.respectDoNotTrack' => true,
            'MmdMatomoAnalytics.config.requireConsent' => false,
            'MmdMatomoAnalytics.config.useKlaroConsent' => false,
            'MmdMatomoAnalytics.config.klaroServiceName' => 'matomo',
            'MmdMatomoAnalytics.config.ecommerceEnabled' => true,
            'MmdMatomoAnalytics.config.trackProductViews' => true,
            'MmdMatomoAnalytics.config.trackCartUpdates' => true,
            'MmdMatomoAnalytics.config.trackOrders' => true,
            'MmdMatomoAnalytics.config.trackAdminUsers' => false,
            'MmdMatomoAnalytics.config.enableHeartbeatTimer' => false,
            'MmdMatomoAnalytics.config.heartbeatInterval' => 15,
            'MmdMatomoAnalytics.config.trackLinks' => true,
            'MmdMatomoAnalytics.config.trackDownloads' => true,
            default => null,
        };
    }
}
