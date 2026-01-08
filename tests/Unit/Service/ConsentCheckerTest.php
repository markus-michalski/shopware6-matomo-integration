<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\ConsentChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(ConsentChecker::class)]
final class ConsentCheckerTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;
    private MatomoConfigFactory $configFactory;
    private RequestStack&MockObject $requestStack;
    private ConsentChecker $checker;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->configFactory = new MatomoConfigFactory($this->systemConfigService);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->checker = new ConsentChecker($this->configFactory, $this->requestStack);
    }

    #[Test]
    public function itReturnsFalseWhenNoRequestAvailable(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: false,
            cookielessTracking: false,
            requireConsent: false,
        ));
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        self::assertFalse($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itReturnsFalseWhenDoNotTrackIsEnabled(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: true,
            cookielessTracking: false,
            requireConsent: false,
        ));

        $request = new Request();
        $request->headers->set('DNT', '1');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertFalse($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itIgnoresDoNotTrackWhenNotConfigured(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: false,
            cookielessTracking: true,
            requireConsent: false,
        ));

        $request = new Request();
        $request->headers->set('DNT', '1');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itAllowsTrackingWithCookielessMode(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: false,
            cookielessTracking: true,
            requireConsent: false,
        ));

        $request = new Request();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itRequiresConsentWhenConfigured(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: false,
            cookielessTracking: false,
            requireConsent: true,
        ));

        $request = new Request();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // No consent cookie present
        self::assertFalse($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itDetectsDoNotTrackHeader(): void
    {
        $request = new Request();
        $request->headers->set('DNT', '1');

        self::assertTrue($this->checker->isDoNotTrackEnabled($request));
    }

    #[Test]
    #[DataProvider('provideDoNotTrackValues')]
    public function itHandlesVariousDoNotTrackValues(string $value, bool $expected): void
    {
        $request = new Request();
        $request->headers->set('DNT', $value);

        self::assertSame($expected, $this->checker->isDoNotTrackEnabled($request));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideDoNotTrackValues(): iterable
    {
        yield 'DNT enabled' => ['1', true];
        yield 'DNT disabled' => ['0', false];
        yield 'DNT empty' => ['', false];
        yield 'DNT other value' => ['yes', false];
    }

    #[Test]
    public function itDetectsShopwareCookieConsent(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: false,
            cookielessTracking: false,
            requireConsent: true,
        ));

        $request = new Request();
        $request->cookies->set('cookie-preference', json_encode(['statistics' => true]));
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itDetectsMatomoConsentCookie(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: false,
            cookielessTracking: false,
            requireConsent: true,
        ));

        $request = new Request();
        $request->cookies->set('matomo_consent', '1');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itRejectsFalseConsentValues(): void
    {
        $this->mockConfigFromArray($this->createConfigArray(
            respectDoNotTrack: false,
            cookielessTracking: false,
            requireConsent: true,
        ));

        $request = new Request();
        $request->cookies->set('matomo_consent', '0');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertFalse($this->checker->isTrackingAllowed(null));
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
    private function createConfigArray(
        bool $respectDoNotTrack = false,
        bool $cookielessTracking = false,
        bool $requireConsent = false,
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
            'enableHeartbeatTimer' => false,
            'heartbeatInterval' => 15,
            'trackLinks' => true,
            'trackDownloads' => true,
        ];
    }
}
