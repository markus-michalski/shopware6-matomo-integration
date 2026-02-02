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
use Mmd\MatomoAnalytics\Service\EcommerceTracker;
use Mmd\MatomoAnalytics\Struct\MatomoEcommerceStruct;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for tracking product page views
 *
 * Listens to ProductPageLoadedEvent and adds E-Commerce view tracking data
 * to the page for Matomo's setEcommerceView call.
 */
final class ProductPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EcommerceTracker $ecommerceTracker,
        private readonly MatomoConfigFactory $configFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isEcommerceEnabled() || !$config->shouldTrackProductViews()) {
            return;
        }

        $product = $event->getPage()->getProduct();
        $categoryPath = $this->buildCategoryPath($event);

        $trackingCode = $this->ecommerceTracker->trackProductView(
            $product,
            $categoryPath,
            $salesChannelId
        );

        if ($trackingCode === '') {
            return;
        }

        $page = $event->getPage();

        // Add tracking code as page extension
        $page->addExtension('matomoEcommerce', new MatomoEcommerceStruct(
            type: 'productView',
            trackingCode: $trackingCode,
        ));
    }

    /**
     * Build category breadcrumb path for the product
     */
    private function buildCategoryPath(ProductPageLoadedEvent $event): ?string
    {
        $breadcrumb = $event->getPage()->getHeader()->getNavigation()?->getActive();

        if ($breadcrumb === null) {
            return null;
        }

        $path = $this->collectBreadcrumbPath($breadcrumb);

        return empty($path) ? null : implode('/', $path);
    }

    /**
     * Recursively collect category names from breadcrumb
     *
     * @return array<string>
     */
    private function collectBreadcrumbPath(?CategoryEntity $category): array
    {
        if ($category === null) {
            return [];
        }

        $path = [];

        // Get parent path first
        $parent = $category->getParent();
        if ($parent !== null) {
            $path = $this->collectBreadcrumbPath($parent);
        }

        // Add current category name
        $name = $category->getTranslation('name') ?? $category->getName();
        if ($name !== null && $name !== '') {
            $path[] = $name;
        }

        return $path;
    }
}
