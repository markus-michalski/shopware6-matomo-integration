<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Subscriber;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\EcommerceTracker;
use Mmd\MatomoAnalytics\Struct\MatomoEcommerceStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for tracking completed orders
 *
 * Listens to CheckoutFinishPageLoadedEvent and tracks the completed order
 * with Matomo's trackEcommerceOrder call.
 */
final class OrderCompletedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderLineItemCollection> $orderRepository
     */
    public function __construct(
        private readonly EcommerceTracker $ecommerceTracker,
        private readonly MatomoConfigFactory $configFactory,
        private readonly EntityRepository $orderRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishPageLoaded',
        ];
    }

    public function onCheckoutFinishPageLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isEcommerceEnabled() || !$config->shouldTrackOrders()) {
            return;
        }

        $order = $event->getPage()->getOrder();

        // Ensure we have full order data with line items
        $order = $this->loadOrderWithAssociations($order, $event);

        if ($order === null) {
            return;
        }

        $trackingCode = $this->ecommerceTracker->trackOrder($order, $salesChannelId);

        if ($trackingCode === '') {
            return;
        }

        $event->getPage()->addExtension('matomoEcommerce', new MatomoEcommerceStruct(
            type: 'orderCompleted',
            trackingCode: $trackingCode,
        ));
    }

    /**
     * Load order with all required associations for tracking
     */
    private function loadOrderWithAssociations(OrderEntity $order, CheckoutFinishPageLoadedEvent $event): ?OrderEntity
    {
        // If line items are already loaded, use the existing order
        if ($order->getLineItems() !== null && $order->getLineItems()->count() > 0) {
            return $order;
        }

        // Load order with associations
        $criteria = new Criteria([$order->getId()]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries.shippingCosts');

        $context = $event->getSalesChannelContext()->getContext();

        /** @var OrderEntity|null $fullOrder */
        $fullOrder = $this->orderRepository->search($criteria, $context)->first();

        return $fullOrder;
    }
}
