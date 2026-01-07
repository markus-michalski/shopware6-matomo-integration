<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Twig;

use Mmd\MatomoAnalytics\Configuration\MatomoConfig;
use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\TrackingCodeRenderer;
use Mmd\MatomoAnalytics\Twig\MatomoExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MatomoExtension::class)]
final class MatomoExtensionTest extends TestCase
{
    private TrackingCodeRenderer&MockObject $renderer;
    private MatomoConfigFactory&MockObject $configFactory;
    private MatomoExtension $extension;

    protected function setUp(): void
    {
        $this->renderer = $this->createMock(TrackingCodeRenderer::class);
        $this->configFactory = $this->createMock(MatomoConfigFactory::class);
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
        $this->renderer->method('render')->willReturn('var _paq = [];');

        $result = $this->extension->renderTrackingCode(null);

        self::assertSame('var _paq = [];', $result);
    }

    #[Test]
    public function itRendersTrackingScript(): void
    {
        $this->renderer->method('renderWithScriptTag')->willReturn('<script>var _paq = [];</script>');

        $result = $this->extension->renderTrackingScript(null);

        self::assertSame('<script>var _paq = [];</script>', $result);
    }

    #[Test]
    public function itRendersOptOutIframe(): void
    {
        $config = $this->createValidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

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
        $config = $this->createInvalidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $result = $this->extension->renderOptOutIframe(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itChecksIfEnabled(): void
    {
        $config = $this->createValidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        self::assertTrue($this->extension->isEnabled(null));
    }

    #[Test]
    public function itChecksIfNotEnabled(): void
    {
        $config = $this->createInvalidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        self::assertFalse($this->extension->isEnabled(null));
    }

    #[Test]
    public function itChecksIfEcommerceEnabled(): void
    {
        $config = $this->createValidConfig();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        self::assertTrue($this->extension->isEcommerceEnabled(null));
    }

    #[Test]
    public function itChecksIfEcommerceNotEnabled(): void
    {
        $config = $this->createConfigWithoutEcommerce();
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        self::assertFalse($this->extension->isEcommerceEnabled(null));
    }

    private function createValidConfig(): MatomoConfig
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

    private function createConfigWithoutEcommerce(): MatomoConfig
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
    }
}
