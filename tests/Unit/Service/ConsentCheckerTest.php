<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfig;
use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\ConsentChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(ConsentChecker::class)]
final class ConsentCheckerTest extends TestCase
{
    private MatomoConfigFactory&MockObject $configFactory;
    private RequestStack&MockObject $requestStack;
    private ConsentChecker $checker;

    protected function setUp(): void
    {
        $this->configFactory = $this->createMock(MatomoConfigFactory::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->checker = new ConsentChecker($this->configFactory, $this->requestStack);
    }

    #[Test]
    public function itReturnsFalseWhenNoRequestAvailable(): void
    {
        $config = $this->createConfig(respectDnt: false, cookieless: false, requireConsent: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        self::assertFalse($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itReturnsFalseWhenDoNotTrackIsEnabled(): void
    {
        $config = $this->createConfig(respectDnt: true, cookieless: false, requireConsent: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $request = new Request();
        $request->headers->set('DNT', '1');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertFalse($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itIgnoresDoNotTrackWhenNotConfigured(): void
    {
        $config = $this->createConfig(respectDnt: false, cookieless: true, requireConsent: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $request = new Request();
        $request->headers->set('DNT', '1');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itAllowsTrackingWithCookielessMode(): void
    {
        $config = $this->createConfig(respectDnt: false, cookieless: true, requireConsent: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $request = new Request();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itRequiresConsentWhenConfigured(): void
    {
        $config = $this->createConfig(respectDnt: false, cookieless: false, requireConsent: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

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
        $config = $this->createConfig(respectDnt: false, cookieless: false, requireConsent: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $request = new Request();
        $request->cookies->set('cookie-preference', json_encode(['statistics' => true]));
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itDetectsMatomoConsentCookie(): void
    {
        $config = $this->createConfig(respectDnt: false, cookieless: false, requireConsent: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $request = new Request();
        $request->cookies->set('matomo_consent', '1');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertTrue($this->checker->isTrackingAllowed(null));
    }

    #[Test]
    public function itRejectsFalseConsentValues(): void
    {
        $config = $this->createConfig(respectDnt: false, cookieless: false, requireConsent: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $request = new Request();
        $request->cookies->set('matomo_consent', '0');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        self::assertFalse($this->checker->isTrackingAllowed(null));
    }

    private function createConfig(
        bool $respectDnt,
        bool $cookieless,
        bool $requireConsent,
    ): MatomoConfig {
        return new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: $cookieless,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: $respectDnt,
            requireConsent: $requireConsent,
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
