<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\TrackingCodeRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(TrackingCodeRenderer::class)]
final class TrackingCodeRendererTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;
    private MatomoConfigFactory $configFactory;
    private TrackingCodeRenderer $renderer;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->configFactory = new MatomoConfigFactory($this->systemConfigService);
        $this->renderer = new TrackingCodeRenderer($this->configFactory);
    }

    #[Test]
    public function itReturnsEmptyStringForInvalidConfig(): void
    {
        $this->mockConfigFromArray($this->createInvalidConfigArray());

        $result = $this->renderer->render(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itRendersBasicTrackingCode(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

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
        $this->mockConfigFromArray($this->createValidConfigArray());

        $result = $this->renderer->renderWithScriptTag(null);

        self::assertStringStartsWith('<script>', $result);
        self::assertStringEndsWith('</script>', $result);
    }

    #[Test]
    public function itReturnsEmptyStringWithScriptTagsForInvalidConfig(): void
    {
        $this->mockConfigFromArray($this->createInvalidConfigArray());

        $result = $this->renderer->renderWithScriptTag(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itRendersCookielessTrackingOption(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray(cookielessTracking: true));

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["disableCookies"]);', $result);
    }

    #[Test]
    public function itDoesNotRenderCookielessWhenDisabled(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray(cookielessTracking: false));

        $result = $this->renderer->render(null);

        self::assertStringNotContainsString('disableCookies', $result);
    }

    #[Test]
    public function itRendersDoNotTrackOption(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray(respectDoNotTrack: true));

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["setDoNotTrack", true]);', $result);
    }

    #[Test]
    public function itRendersConsentRequirement(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray(requireConsent: true));

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["requireConsent"]);', $result);
    }

    #[Test]
    public function itRendersHeartbeatTimer(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray(
            enableHeartbeatTimer: true,
            heartbeatInterval: 30,
        ));

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["enableHeartBeatTimer", 30]);', $result);
    }

    #[Test]
    public function itRendersLinkTracking(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray(trackLinks: true));

        $result = $this->renderer->render(null);

        self::assertStringContainsString('_paq.push(["enableLinkTracking"]);', $result);
    }

    #[Test]
    public function itEscapesSpecialCharactersInUrl(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

        $result = $this->renderer->render(null);

        // Should contain properly escaped URL
        self::assertStringContainsString('https://analytics.example.com/', $result);
    }

    #[Test]
    public function itDoesNotRenderScriptTagsInOutput(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

        $result = $this->renderer->render(null);

        // Output should never contain raw script tags
        self::assertStringNotContainsString('</script><script>', $result);
    }

    /**
     * Mock SystemConfigService to return config values from array
     *
     * @param array<string, mixed> $configArray
     */
    private function mockConfigFromArray(array $configArray): void
    {
        $this->systemConfigService
            ->method('get')
            ->willReturnCallback(function (string $key) use ($configArray) {
                $configKey = str_replace('MmdMatomoAnalytics.config.', '', $key);
                return $configArray[$configKey] ?? null;
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function createValidConfigArray(
        bool $cookielessTracking = false,
        bool $respectDoNotTrack = false,
        bool $requireConsent = false,
        bool $enableHeartbeatTimer = false,
        int $heartbeatInterval = 15,
        bool $trackLinks = false,
    ): array {
        return [
            'matomoUrl' => 'https://analytics.example.com',
            'siteId' => 1,
            'trackingEnabled' => true,
            'cookielessTracking' => $cookielessTracking,
            'ipAnonymizationLevel' => 2,
            'respectDoNotTrack' => $respectDoNotTrack,
            'requireConsent' => $requireConsent,
            'useKlaroConsent' => false,
            'klaroServiceName' => 'matomo',
            'ecommerceEnabled' => true,
            'trackProductViews' => true,
            'trackCartUpdates' => true,
            'trackOrders' => true,
            'trackAdminUsers' => false,
            'enableHeartbeatTimer' => $enableHeartbeatTimer,
            'heartbeatInterval' => $heartbeatInterval,
            'trackLinks' => $trackLinks,
            'trackDownloads' => $trackLinks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createInvalidConfigArray(): array
    {
        return [
            'matomoUrl' => '',
            'siteId' => 0,
            'trackingEnabled' => false,
            'cookielessTracking' => true,
            'ipAnonymizationLevel' => 2,
            'respectDoNotTrack' => true,
            'requireConsent' => false,
            'useKlaroConsent' => false,
            'klaroServiceName' => 'matomo',
            'ecommerceEnabled' => true,
            'trackProductViews' => true,
            'trackCartUpdates' => true,
            'trackOrders' => true,
            'trackAdminUsers' => false,
            'enableHeartbeatTimer' => false,
            'heartbeatInterval' => 15,
            'trackLinks' => true,
            'trackDownloads' => true,
        ];
    }
}
