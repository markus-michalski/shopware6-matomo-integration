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

namespace Mmd\MatomoAnalytics\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Creates a Klaro Cookie Service entry for Matomo Analytics
 *
 * Only runs if the MmdKlaroConsent plugin is installed (table exists).
 * Skips silently if a matomo service already exists (manual or previous install).
 * Creates an analytics-purpose service with Matomo cookie patterns.
 */
final class Migration1738800001CreateMatomoKlaroService extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1738800001;
    }

    public function update(Connection $connection): void
    {
        // Check if Klaro plugin table exists
        $tableExists = $connection->fetchOne(
            "SHOW TABLES LIKE 'mmd_klaro_cookie_service'"
        );

        if (!$tableExists) {
            return;
        }

        // Check if service already exists (manual creation or previous install)
        $exists = $connection->fetchOne(
            "SELECT id FROM mmd_klaro_cookie_service WHERE technical_name = 'matomo'"
        );

        if ($exists) {
            return;
        }

        $serviceId = Uuid::randomBytes();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        // Create main service entry
        $connection->insert('mmd_klaro_cookie_service', [
            'id' => $serviceId,
            'technical_name' => 'matomo',
            'purpose' => 'analytics',
            'required' => 0,
            'default_enabled' => 0,
            'active' => 1,
            'cookies' => json_encode([
                ['pattern' => '/^_pk_id/', 'path' => '/'],
                ['pattern' => '/^_pk_ses/', 'path' => '/'],
                ['pattern' => '/^_pk_ref/', 'path' => '/'],
            ]),
            'only_once' => 1,
            'contextual_consent_only' => 0,
            'position' => 10,
            'created_at' => $now,
        ]);

        // German translation
        $deLanguageId = $this->getLanguageIdByLocale($connection, 'de-DE');
        if ($deLanguageId !== null) {
            $connection->insert('mmd_klaro_cookie_service_translation', [
                'mmd_klaro_cookie_service_id' => $serviceId,
                'language_id' => $deLanguageId,
                'title' => 'Matomo Analytics',
                'description' => 'Ermöglicht die Analyse des Nutzerverhaltens auf unserer Website. Matomo ist eine datenschutzfreundliche, selbst gehostete Web-Analyse-Lösung.',
                'created_at' => $now,
            ]);
        }

        // English translation
        $enLanguageId = $this->getLanguageIdByLocale($connection, 'en-GB');
        if ($enLanguageId !== null) {
            $connection->insert('mmd_klaro_cookie_service_translation', [
                'mmd_klaro_cookie_service_id' => $serviceId,
                'language_id' => $enLanguageId,
                'title' => 'Matomo Analytics',
                'description' => 'Enables analysis of user behavior on our website. Matomo is a privacy-friendly, self-hosted web analytics solution.',
                'created_at' => $now,
            ]);
        }

        // Assign to all storefront sales channels
        $salesChannelIds = $connection->fetchFirstColumn(
            'SELECT id FROM sales_channel WHERE type_id = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );

        foreach ($salesChannelIds as $salesChannelId) {
            $connection->insert('mmd_klaro_cookie_service_sales_channel', [
                'mmd_klaro_cookie_service_id' => $serviceId,
                'sales_channel_id' => $salesChannelId,
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $languageId = $connection->fetchOne(
            'SELECT l.id FROM language l
             INNER JOIN locale lo ON l.locale_id = lo.id
             WHERE lo.code = :locale',
            ['locale' => $locale]
        );

        return $languageId !== false ? (string) $languageId : null;
    }
}
