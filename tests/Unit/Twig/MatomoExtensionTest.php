<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Twig;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\TrackingCodeRenderer;
use Mmd\MatomoAnalytics\Twig\MatomoExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(MatomoExtension::class)]
final class MatomoExtensionTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;
    private MatomoConfigFactory $configFactory;
    private TrackingCodeRenderer $renderer;
    private MatomoExtension $extension;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->configFactory = new MatomoConfigFactory($this->systemConfigService);
        $this->renderer = new TrackingCodeRenderer($this->configFactory);
        $this->extension = new MatomoExtension($this->renderer, $this->configFactory);
    }

    #[Test]
    public function itRegistersTwigFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $functionNames = array_map(fn ($f) => $f->getName(), $functions);

        self::assertContains('matomo_tracking_code', $functionNames);
        self::assertContains('matomo_tracking_script', $functionNames);
        self::assertContains('matomo_opt_out_iframe', $functionNames);
        self::assertContains('matomo_is_enabled', $functionNames);
        self::assertContains('matomo_ecommerce_enabled', $functionNames);
    }

    #[Test]
    public function itRendersTrackingCode(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

        $result = $this->extension->renderTrackingCode(null);

        self::assertStringContainsString('var _paq', $result);
        self::assertStringContainsString('trackPageView', $result);
    }

    #[Test]
    public function itRendersTrackingScript(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

        $result = $this->extension->renderTrackingScript(null);

        self::assertStringStartsWith('<script>', $result);
        self::assertStringEndsWith('</script>', $result);
    }

    #[Test]
    public function itRendersOptOutIframe(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

        $result = $this->extension->renderOptOutIframe(null, 'de', 'ffffff', '000000');

        self::assertStringContainsString('<iframe', $result);
        self::assertStringContainsString('https://analytics.example.com/index.php', $result);
        self::assertStringContainsString('language=de', $result);
        self::assertStringContainsString('backgroundColor=ffffff', $result);
        self::assertStringContainsString('fontColor=000000', $result);
    }

    #[Test]
    public function itReturnsEmptyStringForOptOutIframeWhenInvalid(): void
    {
        $this->mockConfigFromArray($this->createInvalidConfigArray());

        $result = $this->extension->renderOptOutIframe(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itChecksIfEnabled(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

        self::assertTrue($this->extension->isEnabled(null));
    }

    #[Test]
    public function itChecksIfNotEnabled(): void
    {
        $this->mockConfigFromArray($this->createInvalidConfigArray());

        self::assertFalse($this->extension->isEnabled(null));
    }

    #[Test]
    public function itChecksIfEcommerceEnabled(): void
    {
        $this->mockConfigFromArray($this->createValidConfigArray());

        self::assertTrue($this->extension->isEcommerceEnabled(null));
    }

    #[Test]
    public function itChecksIfEcommerceNotEnabled(): void
    {
        $this->mockConfigFromArray($this->createConfigWithoutEcommerceArray());

        self::assertFalse($this->extension->isEcommerceEnabled(null));
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
    private function createValidConfigArray(): array
    {
        return [
            'matomoUrl' => 'https://analytics.example.com',
            'siteId' => 1,
            'trackingEnabled' => true,
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

    /**
     * @return array<string, mixed>
     */
    private function createConfigWithoutEcommerceArray(): array
    {
        return [
            'matomoUrl' => 'https://analytics.example.com',
            'siteId' => 1,
            'trackingEnabled' => true,
            'cookielessTracking' => true,
            'ipAnonymizationLevel' => 2,
            'respectDoNotTrack' => true,
            'requireConsent' => false,
            'useKlaroConsent' => false,
            'klaroServiceName' => 'matomo',
            'ecommerceEnabled' => false,
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
