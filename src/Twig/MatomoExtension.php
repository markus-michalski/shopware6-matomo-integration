<?php

declare(strict_types=1);

namespace Mmd\MatomoAnalytics\Twig;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Mmd\MatomoAnalytics\Service\TrackingCodeRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for Matomo tracking in templates
 *
 * Provides functions for rendering tracking code, opt-out iframes,
 * and checking if tracking is enabled.
 */
final class MatomoExtension extends AbstractExtension
{
    public function __construct(
        private readonly TrackingCodeRenderer $trackingCodeRenderer,
        private readonly MatomoConfigFactory $configFactory,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('matomo_tracking_code', [$this, 'renderTrackingCode'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('matomo_tracking_script', [$this, 'renderTrackingScript'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('matomo_opt_out_iframe', [$this, 'renderOptOutIframe'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('matomo_is_enabled', [$this, 'isEnabled']),
            new TwigFunction('matomo_ecommerce_enabled', [$this, 'isEcommerceEnabled']),
        ];
    }

    /**
     * Render the raw Matomo tracking code (without script tags)
     */
    public function renderTrackingCode(?string $salesChannelId = null): string
    {
        return $this->trackingCodeRenderer->render($salesChannelId);
    }

    /**
     * Render the complete Matomo tracking script (with script tags)
     */
    public function renderTrackingScript(?string $salesChannelId = null): string
    {
        return $this->trackingCodeRenderer->renderWithScriptTag($salesChannelId);
    }

    /**
     * Render the Matomo opt-out iframe
     *
     * @param string|null $salesChannelId Sales channel ID
     * @param string $language Language code (de, en, etc.)
     * @param string $backgroundColor Iframe background color
     * @param string $fontColor Iframe text color
     */
    public function renderOptOutIframe(
        ?string $salesChannelId = null,
        string $language = 'de',
        string $backgroundColor = 'ffffff',
        string $fontColor = '000000',
    ): string {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        if (!$config->isValid()) {
            return '';
        }

        $iframeSrc = sprintf(
            '%s/index.php?module=CoreAdminHome&action=optOut&language=%s&backgroundColor=%s&fontColor=%s&fontSize=16px&fontFamily=inherit',
            $config->getNormalizedMatomoUrl(),
            urlencode($language),
            urlencode($backgroundColor),
            urlencode($fontColor)
        );

        return sprintf(
            '<iframe style="border: 0; width: 100%%; height: auto; min-height: 200px;" src="%s"></iframe>',
            htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Check if Matomo tracking is enabled and configured
     */
    public function isEnabled(?string $salesChannelId = null): bool
    {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        return $config->isValid();
    }

    /**
     * Check if E-Commerce tracking is enabled
     */
    public function isEcommerceEnabled(?string $salesChannelId = null): bool
    {
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        return $config->isValid() && $config->isEcommerceEnabled();
    }
}
