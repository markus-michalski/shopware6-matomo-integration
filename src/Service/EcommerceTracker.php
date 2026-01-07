<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

/**
 * Service for generating Matomo E-Commerce tracking JavaScript
 *
 * Generates JavaScript arrays for:
 * - Product view tracking (setEcommerceView)
 * - Cart update tracking (trackEcommerceCartUpdate)
 * - Order tracking (trackEcommerceOrder)
 */
final class EcommerceTracker
{
    public function __construct(
        private readonly MatomoConfigFactory $configFactory,
    ) {
    }

    /**
     * Generate JavaScript for tracking a product view
     *
     * @param SalesChannelProductEntity $product The product being viewed
     * @param string|null $categoryPath Category breadcrumb path
     * @return string JavaScript code (without script tags)
     */
    public function trackProductView(
        SalesChannelProductEntity $product,
        ?string $categoryPath = null,
        ?string $salesChannelId = null,
    ): string {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isEcommerceEnabled() || !$config->shouldTrackProductViews()) {
            return '';
        }

        $sku = $product->getProductNumber();
        $name = $product->getTranslation('name') ?? $product->getName() ?? '';
        $price = $this->getProductPrice($product);

        $lines = [
            sprintf(
                '_paq.push(["setEcommerceView", %s, %s, %s, %s]);',
                $this->jsonEncode($sku),
                $this->jsonEncode($name),
                $categoryPath !== null ? $this->jsonEncode($categoryPath) : 'false',
                $price !== null ? number_format($price, 2, '.', '') : 'false'
            ),
        ];

        return implode("\n", $lines);
    }

    /**
     * Generate JavaScript for tracking cart contents
     *
     * @param Cart $cart The shopping cart
     * @return string JavaScript code (without script tags)
     */
    public function trackCartUpdate(Cart $cart, ?string $salesChannelId = null): string
    {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isEcommerceEnabled() || !$config->shouldTrackCartUpdates()) {
            return '';
        }

        $lines = [];

        // Add each line item
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $itemJs = $this->buildAddEcommerceItemJs($lineItem);
            if ($itemJs !== null) {
                $lines[] = $itemJs;
            }
        }

        // Track cart update with total
        $total = $cart->getPrice()->getTotalPrice();
        $lines[] = sprintf(
            '_paq.push(["trackEcommerceCartUpdate", %s]);',
            number_format($total, 2, '.', '')
        );

        return implode("\n", $lines);
    }

    /**
     * Generate JavaScript for tracking a completed order
     *
     * @param OrderEntity $order The completed order
     * @return string JavaScript code (without script tags)
     */
    public function trackOrder(OrderEntity $order, ?string $salesChannelId = null): string
    {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isEcommerceEnabled() || !$config->shouldTrackOrders()) {
            return '';
        }

        $lines = [];
        $lineItems = $order->getLineItems();

        // Add each line item
        if ($lineItems !== null) {
            foreach ($lineItems as $lineItem) {
                if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                    continue;
                }

                $lines[] = sprintf(
                    '_paq.push(["addEcommerceItem", %s, %s, "", %s, %d]);',
                    $this->jsonEncode($lineItem->getPayload()['productNumber'] ?? $lineItem->getIdentifier()),
                    $this->jsonEncode($lineItem->getLabel() ?? ''),
                    number_format($lineItem->getUnitPrice(), 2, '.', ''),
                    $lineItem->getQuantity()
                );
            }
        }

        // Track the order
        $grandTotal = $order->getAmountTotal();
        $subTotal = $order->getAmountNet();
        $tax = $order->getAmountTotal() - $order->getAmountNet();
        $shipping = $this->getShippingCosts($order);

        $lines[] = sprintf(
            '_paq.push(["trackEcommerceOrder", %s, %s, %s, %s, %s]);',
            $this->jsonEncode($order->getOrderNumber() ?? $order->getId()),
            number_format($grandTotal, 2, '.', ''),
            number_format($subTotal, 2, '.', ''),
            number_format($tax, 2, '.', ''),
            number_format($shipping, 2, '.', '')
        );

        return implode("\n", $lines);
    }

    /**
     * Generate JavaScript for adding a single item to cart
     *
     * @param LineItem $lineItem The line item being added
     * @return string JavaScript code (without script tags)
     */
    public function trackAddToCart(LineItem $lineItem, ?string $salesChannelId = null): string
    {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isEcommerceEnabled() || !$config->shouldTrackCartUpdates()) {
            return '';
        }

        $js = $this->buildAddEcommerceItemJs($lineItem);

        return $js ?? '';
    }

    /**
     * Build the addEcommerceItem JavaScript call for a line item
     */
    private function buildAddEcommerceItemJs(LineItem $lineItem): ?string
    {
        $payload = $lineItem->getPayload();
        $sku = $payload['productNumber'] ?? $lineItem->getIdentifier();
        $name = $lineItem->getLabel() ?? '';
        $price = $lineItem->getPrice()?->getUnitPrice() ?? 0.0;
        $quantity = $lineItem->getQuantity();

        return sprintf(
            '_paq.push(["addEcommerceItem", %s, %s, "", %s, %d]);',
            $this->jsonEncode($sku),
            $this->jsonEncode($name),
            number_format($price, 2, '.', ''),
            $quantity
        );
    }

    /**
     * Get the product price for tracking
     */
    private function getProductPrice(SalesChannelProductEntity $product): ?float
    {
        $calculatedPrice = $product->getCalculatedPrice();

        if ($calculatedPrice !== null) {
            return $calculatedPrice->getUnitPrice();
        }

        return null;
    }

    /**
     * Get shipping costs from order
     */
    private function getShippingCosts(OrderEntity $order): float
    {
        $shippingCosts = $order->getShippingCosts();

        return $shippingCosts->getTotalPrice();
    }

    /**
     * Safely JSON encode a value
     */
    private function jsonEncode(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : '""';
    }
}
