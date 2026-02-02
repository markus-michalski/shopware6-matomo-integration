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

namespace Mmd\MatomoAnalytics\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Struct for E-Commerce tracking data
 *
 * Used to pass tracking data from Subscribers to Twig templates
 * via page extensions.
 */
final class MatomoEcommerceStruct extends Struct
{
    /**
     * @param string $type Type of tracking event (productView, cartUpdate, orderCompleted)
     * @param string $trackingCode JavaScript code for the tracking event
     */
    public function __construct(
        protected readonly string $type,
        protected readonly string $trackingCode,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTrackingCode(): string
    {
        return $this->trackingCode;
    }
}
