<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Tests\Unit\Service;

use Mmd\MatomoAnalytics\Configuration\MatomoConfig;
use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\EcommerceTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(EcommerceTracker::class)]
final class EcommerceTrackerTest extends TestCase
{
    private MatomoConfigFactory&MockObject $configFactory;
    private EcommerceTracker $tracker;

    protected function setUp(): void
    {
        $this->configFactory = $this->createMock(MatomoConfigFactory::class);
        $this->tracker = new EcommerceTracker($this->configFactory);
    }

    #[Test]
    public function itReturnsEmptyStringWhenEcommerceDisabled(): void
    {
        $config = $this->createConfig(ecommerceEnabled: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $product = $this->createProduct('SW10001', 'Test Product', 29.99);

        $result = $this->tracker->trackProductView($product, 'Category/Sub', null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itReturnsEmptyStringWhenProductViewsDisabled(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackProductViews: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $product = $this->createProduct('SW10001', 'Test Product', 29.99);

        $result = $this->tracker->trackProductView($product, 'Category/Sub', null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itTracksProductView(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackProductViews: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $product = $this->createProduct('SW10001', 'Test Product', 29.99);

        $result = $this->tracker->trackProductView($product, 'Category/Sub', null);

        self::assertStringContainsString('setEcommerceView', $result);
        self::assertStringContainsString('"SW10001"', $result);
        self::assertStringContainsString('"Test Product"', $result);
        self::assertStringContainsString('"Category/Sub"', $result);
        self::assertStringContainsString('29.99', $result);
    }

    #[Test]
    public function itTracksProductViewWithoutCategory(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackProductViews: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $product = $this->createProduct('SW10001', 'Test Product', 29.99);

        $result = $this->tracker->trackProductView($product, null, null);

        self::assertStringContainsString('setEcommerceView', $result);
        self::assertStringContainsString('false', $result); // Category is false
    }

    #[Test]
    public function itReturnsEmptyStringWhenCartTrackingDisabled(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackCartUpdates: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $cart = $this->createCart([]);

        $result = $this->tracker->trackCartUpdate($cart, null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itTracksCartUpdate(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackCartUpdates: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $cart = $this->createCart([
            ['sku' => 'SW10001', 'name' => 'Product 1', 'price' => 19.99, 'qty' => 2],
            ['sku' => 'SW10002', 'name' => 'Product 2', 'price' => 9.99, 'qty' => 1],
        ]);

        $result = $this->tracker->trackCartUpdate($cart, null);

        self::assertStringContainsString('addEcommerceItem', $result);
        self::assertStringContainsString('"SW10001"', $result);
        self::assertStringContainsString('"Product 1"', $result);
        self::assertStringContainsString('trackEcommerceCartUpdate', $result);
        self::assertStringContainsString('49.97', $result); // Total: 19.99*2 + 9.99
    }

    #[Test]
    public function itReturnsEmptyStringWhenOrderTrackingDisabled(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackOrders: false);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $order = $this->createOrder('ORD-001', 99.99, 84.03, 15.96, 5.99);

        $result = $this->tracker->trackOrder($order, null);

        self::assertSame('', $result);
    }

    #[Test]
    public function itTracksOrder(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackOrders: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $order = $this->createOrder('ORD-001', 99.99, 84.03, 15.96, 5.99);

        $result = $this->tracker->trackOrder($order, null);

        self::assertStringContainsString('trackEcommerceOrder', $result);
        self::assertStringContainsString('"ORD-001"', $result);
        self::assertStringContainsString('99.99', $result); // Grand total
        self::assertStringContainsString('84.03', $result); // Net
        self::assertStringContainsString('15.96', $result); // Tax
        self::assertStringContainsString('5.99', $result);  // Shipping
    }

    #[Test]
    public function itTracksOrderWithLineItems(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackOrders: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $order = $this->createOrderWithLineItems('ORD-002', [
            ['sku' => 'SW10001', 'name' => 'Product 1', 'price' => 19.99, 'qty' => 2],
        ]);

        $result = $this->tracker->trackOrder($order, null);

        self::assertStringContainsString('addEcommerceItem', $result);
        self::assertStringContainsString('"SW10001"', $result);
        self::assertStringContainsString('"Product 1"', $result);
        self::assertStringContainsString('trackEcommerceOrder', $result);
    }

    #[Test]
    public function itTracksAddToCart(): void
    {
        $config = $this->createConfig(ecommerceEnabled: true, trackCartUpdates: true);
        $this->configFactory->method('createForSalesChannel')->willReturn($config);

        $lineItem = $this->createLineItem('SW10001', 'Test Product', 29.99, 1);

        $result = $this->tracker->trackAddToCart($lineItem, null);

        self::assertStringContainsString('addEcommerceItem', $result);
        self::assertStringContainsString('"SW10001"', $result);
        self::assertStringContainsString('"Test Product"', $result);
    }

    private function createConfig(
        bool $ecommerceEnabled = true,
        bool $trackProductViews = true,
        bool $trackCartUpdates = true,
        bool $trackOrders = true,
    ): MatomoConfig {
        return new MatomoConfig(
            matomoUrl: 'https://analytics.example.com',
            siteId: 1,
            trackingEnabled: true,
            cookielessTracking: true,
            ipAnonymizationLevel: 2,
            respectDoNotTrack: true,
            requireConsent: false,
            ecommerceEnabled: $ecommerceEnabled,
            trackProductViews: $trackProductViews,
            trackCartUpdates: $trackCartUpdates,
            trackOrders: $trackOrders,
            trackAdminUsers: false,
            enableHeartbeatTimer: false,
            heartbeatInterval: 15,
            trackLinks: true,
            trackDownloads: true,
        );
    }

    private function createProduct(string $productNumber, string $name, float $price): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber($productNumber);
        $product->setName($name);
        $product->setCalculatedPrice(
            new CalculatedPrice(
                $price,
                $price,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            )
        );

        return $product;
    }

    /**
     * @param array<array{sku: string, name: string, price: float, qty: int}> $items
     */
    private function createCart(array $items): Cart
    {
        $cart = new Cart(Uuid::randomHex());
        $lineItems = new LineItemCollection();
        $total = 0.0;

        foreach ($items as $item) {
            $lineItem = $this->createLineItem($item['sku'], $item['name'], $item['price'], $item['qty']);
            $lineItems->add($lineItem);
            $total += $item['price'] * $item['qty'];
        }

        $cart->setLineItems($lineItems);
        $cart->setPrice(
            new CartPrice(
                $total,
                $total,
                $total,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS
            )
        );

        return $cart;
    }

    private function createLineItem(string $sku, string $name, float $price, int $quantity): LineItem
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setLabel($name);
        $lineItem->setQuantity($quantity);
        $lineItem->setPayload(['productNumber' => $sku]);
        $lineItem->setPrice(
            new CalculatedPrice(
                $price,
                $price * $quantity,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                $quantity
            )
        );

        return $lineItem;
    }

    private function createOrder(
        string $orderNumber,
        float $total,
        float $net,
        float $tax,
        float $shipping
    ): OrderEntity {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber($orderNumber);
        $order->setAmountTotal($total);
        $order->setAmountNet($net);
        $order->setShippingCosts(
            new CalculatedPrice(
                $shipping,
                $shipping,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            )
        );
        $order->setLineItems(new OrderLineItemCollection());

        return $order;
    }

    /**
     * @param array<array{sku: string, name: string, price: float, qty: int}> $items
     */
    private function createOrderWithLineItems(string $orderNumber, array $items): OrderEntity
    {
        $order = $this->createOrder($orderNumber, 100.0, 84.03, 15.97, 5.99);

        $lineItems = new OrderLineItemCollection();
        foreach ($items as $item) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId(Uuid::randomHex());
            $lineItem->setIdentifier(Uuid::randomHex());
            $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
            $lineItem->setLabel($item['name']);
            $lineItem->setQuantity($item['qty']);
            $lineItem->setUnitPrice($item['price']);
            $lineItem->setPayload(['productNumber' => $item['sku']]);

            $lineItems->add($lineItem);
        }

        $order->setLineItems($lineItems);

        return $order;
    }
}
