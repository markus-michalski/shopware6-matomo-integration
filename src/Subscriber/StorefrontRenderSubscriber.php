<?php

/**
 * Matomo Analytics for Shopware 6
 *
 * @package   Mmd\MatomoAnalytics
 * @author    Markus Michalski
 * @copyright 2024-2025 Markus Michalski
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

        // When using Klaro, skip consent check here - Klaro handles it in the frontend
        // For non-Klaro mode, check if tracking is allowed (DNT, consent, etc.)
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
