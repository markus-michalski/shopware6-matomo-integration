<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Subscriber;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\EcommerceTracker;
use Mmd\MatomoAnalytics\Struct\MatomoEcommerceStruct;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for tracking checkout cart events
 *
 * Tracks cart updates on the checkout cart and confirm pages.
 */
final class CheckoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EcommerceTracker $ecommerceTracker,
        private readonly MatomoConfigFactory $configFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCheckoutCartPageLoaded',
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
        ];
    }

    public function onCheckoutCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $this->trackCartUpdate($event->getSalesChannelContext()->getSalesChannelId(), $event);
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $this->trackCartUpdate($event->getSalesChannelContext()->getSalesChannelId(), $event);
    }

    private function trackCartUpdate(
        string $salesChannelId,
        CheckoutCartPageLoadedEvent|CheckoutConfirmPageLoadedEvent $event,
    ): void {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isEcommerceEnabled() || !$config->shouldTrackCartUpdates()) {
            return;
        }

        $cart = $event->getPage()->getCart();
        $trackingCode = $this->ecommerceTracker->trackCartUpdate($cart, $salesChannelId);

        if ($trackingCode === '') {
            return;
        }

        $event->getPage()->addExtension('matomoEcommerce', new MatomoEcommerceStruct(
            type: 'cartUpdate',
            trackingCode: $trackingCode,
        ));
    }
}
