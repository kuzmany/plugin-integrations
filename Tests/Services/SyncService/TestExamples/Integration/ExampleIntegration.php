<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

namespace MauticPlugin\IntegrationsBundle\Tests\Services\SyncService\TestExamples\Integration;

use MauticPlugin\IntegrationsBundle\Integration\BasicIntegration;
use MauticPlugin\IntegrationsBundle\Integration\Interfaces\IntegrationInterface;
use MauticPlugin\IntegrationsBundle\Integration\Interfaces\SyncInterface;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\MappingManualDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\ObjectMappingDAO;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\SyncDataExchangeInterface;
use MauticPlugin\IntegrationsBundle\Tests\Services\SyncService\TestExamples\Sync\SyncDataExchange\ExampleSyncDataExchange;

final class ExampleIntegration extends BasicIntegration implements IntegrationInterface, SyncInterface
{
    const NAME = 'Example';

    /**
     * @var ExampleSyncDataExchange
     */
    private $syncDataExchange;

    /**
     * ExampleIntegration constructor.
     *
     * @param ExampleSyncDataExchange $syncDataExchange
     */
    public function __construct(ExampleSyncDataExchange $syncDataExchange)
    {
        $this->syncDataExchange = $syncDataExchange;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return true;
    }

    /**
     * Get if data priority is enabled in the integration or not default is false.
     *
     * @return bool
     */
    public function getDataPriority(): bool
    {
        return true;
    }

    public function getSyncDataExchange(): SyncDataExchangeInterface
    {
        return $this->syncDataExchange;
    }

    /**
     * @return MappingManualDAO
     */
    public function getMappingManual(): MappingManualDAO
    {
        // Generate mapping manual that will be passed to the sync service. This instructs the sync service how to map Mautic fields to integration fields
        $mappingManual = new MappingManualDAO(self::NAME);

        // Each object like lead, contact, user, company, account, etc, will need it's own ObjectMappingDAO
        // In this example, Mautic's Contact object is mapped to the Example's Lead object
        $leadObjectMapping = new ObjectMappingDAO(
            MauticSyncDataExchange::OBJECT_CONTACT,
            ExampleSyncDataExchange::OBJECT_LEAD
        );
        $mappingManual->addObjectMapping($leadObjectMapping);

        // Get field mapping as configured in Mautic's integration config
        $mappedFields = $this->getConfiguredFieldMapping();

        foreach ($mappedFields as $integrationField => $mauticField) {
            // In this case, we're just adding each field to each of the objects
            // Of course, other integrations may need more logic

            // Sync bidirectionally by default but also can use ObjectMappingDAO::SYNC_TO_MAUTIC or ObjectMappingDAO::SYNC_TO_INTEGRATION

            if ('email' === $mauticField) {
                // Set email as a required field so that it maps a value regardless if changed
                $leadObjectMapping->addFieldMapping($mauticField, $integrationField, ObjectMappingDAO::SYNC_BIDIRECTIONALLY, true);
            } else {
                $leadObjectMapping->addFieldMapping($mauticField, $integrationField);
            }
        }

        return $mappingManual;
    }

    /**
     * Likely will get this mapping out of the Integration's settings
     *
     * @return array
     */
    private function getConfiguredFieldMapping()
    {
        return [
            'first_name' => 'firstname',
            'last_name'  => 'lastname',
            'email'      => 'email',
            'street1'    => 'address1',
        ];
    }
}
