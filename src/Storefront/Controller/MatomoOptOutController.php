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

namespace Mmd\MatomoAnalytics\Storefront\Controller;

use Mmd\MatomoAnalytics\Configuration\MatomoConfigFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for Matomo opt-out functionality
 *
 * Provides a standalone opt-out page that can be linked from
 * privacy policy pages.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class MatomoOptOutController extends StorefrontController
{
    public function __construct(
        private readonly MatomoConfigFactory $configFactory,
    ) {
    }

    /**
     * Render the opt-out page
     */
    #[Route(
        path: '/matomo/opt-out',
        name: 'frontend.matomo.optout',
        methods: ['GET']
    )]
    public function optOut(Request $request, SalesChannelContext $context): Response
    {
        $salesChannelId = $context->getSalesChannelId();
        $config = $this->configFactory->createForSalesChannel($salesChannelId);

        return $this->renderStorefront('@MmdMatomoAnalytics/storefront/page/matomo-opt-out.html.twig', [
            'matomoEnabled' => $config->isValid(),
            'matomoUrl' => $config->getNormalizedMatomoUrl(),
        ]);
    }
}
