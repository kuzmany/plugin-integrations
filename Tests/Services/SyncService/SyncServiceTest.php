<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Tests\Services\SyncService;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;
use MauticPlugin\IntegrationsBundle\Sync\SyncService\SyncService;
use MauticPlugin\IntegrationsBundle\Tests\Services\SyncService\TestExamples\Sync\SyncDataExchange\ExampleSyncDataExchange;
use MauticPlugin\IntegrationsBundle\Tests\Services\SyncService\TestExamples\Integration\ExampleIntegration;

class SyncServiceTest extends MauticMysqlTestCase
{
    public function setUp()
    {
        parent::setUp();

        // Populate contacts
        $this->installDatabaseFixtures([dirname(__DIR__).'/../../../../app/bundles/LeadBundle/DataFixtures/ORM/LoadLeadData.php']);
    }

    public function testSync()
    {
        $this->markTestSkipped('disabled for now');

        // Sleep one second to ensure that the modified date/time stamps of the contacts just created are in the past
        sleep(1);

        // Record now because we're going to sync again
        $now = new \DateTime();

        $prefix     = $this->container->getParameter('mautic.db_table_prefix');
        $connection = $this->container->get('doctrine.dbal.default_connection');

        $dataExchange       = new ExampleSyncDataExchange();
        $exampleIntegration = new ExampleIntegration($dataExchange);

        $settings = new Integration();
        $settings->setFeatureSettings(['sync' => ['objects' => [MauticSyncDataExchange::OBJECT_CONTACT]]]);
        $settings->setIsPublished(true);
        $exampleIntegration->setIntegrationConfiguration($settings);

        $syncIntegrationsHelper = $this->container->get('mautic.integrations.helper.sync_integrations');
        $syncIntegrationsHelper->addIntegration($exampleIntegration);

        /** @var SyncService $syncService */
        $syncService = $this->container->get('mautic.integrations.sync.service');

        $syncService->processIntegrationSync(ExampleIntegration::NAME, true);
        $payload = $dataExchange->getOrderPayload();

        // Created the 48 known contacts already in Mautic
        $this->assertCount(48, $payload['create']);
        $this->assertCount(2, $payload['update']);

        $this->assertEquals(
            [
                4 =>
                    [
                        'id'         => 4,
                        'object'     => ExampleSyncDataExchange::OBJECT_LEAD,
                        'first_name' => 'Lewis',
                        'last_name'  => 'Syed',
                        'email'      => 'LewisTSyed@gustr.com',
                        'street1'    => '107 Yorkie Lane',
                    ],
                3 =>
                    [
                        'id'         => 3,
                        'object'     => ExampleSyncDataExchange::OBJECT_LEAD,
                        'first_name' => 'Nellie',
                        'last_name'  => 'Baird',
                        'email'      => 'NellieABaird@armyspy.com',
                        'street1'    => '1930 Uitsig St',
                    ],
            ],
            $payload['update']
        );

        // Validate mapping table
        /** @var Connection $connection */

        // All should be mapped to the OBJECT_LEAD object
        $qb      = $connection->createQueryBuilder();
        $results = $qb->select('count(*) as the_count, m.integration_object_name, m.integration')
            ->from($prefix.'sync_object_mapping', 'm')
            ->groupBy('m.integration, m.integration_object_name')
            ->execute()
            ->fetchAll();

        $this->assertCount(1, $results);
        $this->assertEquals(ExampleIntegration::NAME, $results[0]['integration']);
        $this->assertEquals(ExampleSyncDataExchange::OBJECT_LEAD, $results[0]['integration_object_name']);

        // All should be mapped to the Mautic contact object
        $qb      = $connection->createQueryBuilder();
        $results = $qb->select('count(*) as the_count, m.internal_object_name, m.integration')
            ->from($prefix.'sync_object_mapping', 'm')
            ->groupBy('m.integration, m.internal_object_name')
            ->execute()
            ->fetchAll();

        $this->assertCount(1, $results);
        $this->assertEquals(ExampleIntegration::NAME, $results[0]['integration']);
        $this->assertEquals(MauticSyncDataExchange::OBJECT_CONTACT, $results[0]['internal_object_name']);

        // There should be 50 entries
        $qb      = $connection->createQueryBuilder();
        $results = $qb->select('count(*) as the_count')
            ->from($prefix.'sync_object_mapping', 'm')
            ->execute()
            ->fetchAll();
        $this->assertEquals(50, $results[0]['the_count']);
    }
}
