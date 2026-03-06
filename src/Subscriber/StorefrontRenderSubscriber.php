<?php

/**
 * Matomo Analytics for Shopware 6
 *
 * @package   Mmd\MatomoAnalytics
 * @author    Markus Michalski
 * @copyright 2024-2026 Markus Michalski
 * @license   Proprietary - see LICENSE file for details
 */

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Subscriber;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\ConsentChecker;
use Mmd\MatomoAnalytics\Service\TrackingCodeRenderer;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for injecting Matomo tracking code into storefront pages
 *
 * Listens to StorefrontRenderEvent and adds tracking code to the template
 * parameters, making it available as `matomoTrackingCode` in Twig.
 */
final class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TrackingCodeRenderer $trackingCodeRenderer,
        private readonly ConsentChecker $consentChecker,
        private readonly MatomoConfigFactory $configFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => ['onStorefrontRender', -100],
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isValid()) {
            return;
        }

        // DESIGN DECISION: In Klaro mode, the full tracking code is rendered into the page
        // with type="text/plain". Klaro blocks execution until consent is given client-side.
        // The Matomo URL and Site ID are visible in the HTML source - this is acceptable because:
        // 1. Users who manually change type="text/plain" to "text/javascript" only track themselves
        // 2. Matomo URL/Site ID are not secrets (they're in every tracked page's network requests)
        // 3. Server-side Klaro cookie checking would break Klaro's async consent workflow
        if (!$config->usesKlaroConsent() && !$this->consentChecker->isTrackingAllowed($salesChannelId)) {
            return;
        }

        $trackingCode = $this->trackingCodeRenderer->render($salesChannelId);

        if ($trackingCode === '') {
            return;
        }

        $event->setParameter('matomoTrackingCode', $trackingCode);
        $event->setParameter('matomoConfig', [
            'enabled' => true,
            'ecommerceEnabled' => $config->isEcommerceEnabled(),
            'matomoUrl' => $config->getNormalizedMatomoUrl(),
            'siteId' => $config->getSiteId(),
            'useKlaroConsent' => $config->usesKlaroConsent(),
            'klaroServiceName' => $config->getKlaroServiceName(),
        ]);
    }
}
