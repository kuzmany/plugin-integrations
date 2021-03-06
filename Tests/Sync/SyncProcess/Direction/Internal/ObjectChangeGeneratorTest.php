<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Tests\Sync\SyncProcess\Direction\Internal;


use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\MappingManualDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\ObjectMappingDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\InformationChangeRequestDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Helper\FieldHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ReportDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\FieldDAO as ReportFieldDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ObjectDAO as ReportObjectDAO;
use MauticPlugin\IntegrationsBundle\Sync\SyncJudge\SyncJudgeInterface;
use MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Helper\ValueHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Internal\ObjectChangeGenerator;

class ObjectChangeGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SyncJudgeInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $syncJudge;

    /**
     * @var ValueHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $valueHelper;

    /**
     * @var FieldHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $fieldHelper;

    protected function setUp()
    {
        $this->syncJudge   = $this->createMock(SyncJudgeInterface::class);
        $this->valueHelper = $this->createMock(ValueHelper::class);
        $this->fieldHelper = $this->createMock(FieldHelper::class);
    }

    public function testFieldsAreAddedToObjectChangeAndIntegrationFirstNameWins()
    {
        $this->valueHelper->method('getValueForMautic')
            ->willReturnCallback(
                function(NormalizedValueDAO $normalizedValueDAO, string $fieldState, string $syncDirection) {
                    return $normalizedValueDAO;
                }
            );

        $integration = 'Test';
        $objectName  = 'Contact';

        $mappingManual = $this->getMappingManual($integration, $objectName);
        $syncReport    = $this->getIntegrationSyncReport($integration, $objectName);

        $internalReportObject = new ReportObjectDAO(MauticSyncDataExchange::OBJECT_CONTACT, 1);
        $internalReportObject->addField(new ReportFieldDAO('email', new NormalizedValueDAO(NormalizedValueDAO::EMAIL_TYPE, 'test@test.com')));
        $internalReportObject->addField(new ReportFieldDAO('firstname', new NormalizedValueDAO(NormalizedValueDAO::TEXT_TYPE, 'Bob')));

        $this->syncJudge->expects($this->exactly(2))
            ->method('adjudicate')
            ->willReturnCallback(
                function($mode, InformationChangeRequestDAO $internalInformationChangeRequest, InformationChangeRequestDAO $integrationInformationChangeRequest) {
                    return $integrationInformationChangeRequest;
                }
            );

        $objectChangeDAO       = $this->getObjectGenerator()->getSyncObjectChange(
            $syncReport,
            $mappingManual,
            $mappingManual->getObjectMapping(MauticSyncDataExchange::OBJECT_CONTACT, $objectName),
            $internalReportObject,
            $syncReport->getObject($objectName, 2)
        );

        $this->assertEquals($integration, $objectChangeDAO->getIntegration());

        // object and object ID should be Mautic's (from the Mautic's POV)
        $this->assertEquals(MauticSyncDataExchange::OBJECT_CONTACT, $objectChangeDAO->getObject());
        $this->assertEquals(1, $objectChangeDAO->getObjectId());

        // mapped object and ID should be the integrations
        $this->assertEquals($objectName, $objectChangeDAO->getMappedObject());
        $this->assertEquals(2, $objectChangeDAO->getMappedObjectId());

        // Email should be a required field
        $requiredFields = $objectChangeDAO->getRequiredFields();
        $this->assertTrue(isset($requiredFields['email']));

        // Both fields should be included
        $fields = $objectChangeDAO->getFields();
        $this->assertTrue(isset($fields['email']) && isset($fields['firstname']));

        // First name is presumed to be changed
        $changedFields = $objectChangeDAO->getChangedFields();
        $this->assertTrue(isset($changedFields['firstname']));

        // First name should have changed to Robert because the sync judge returned the integration's information change request
        $this->assertEquals('Robert', $changedFields['firstname']->getValue()->getNormalizedValue());
    }

    public function testFieldsAreAddedToObjectChangeAndInternalFirstNameWins()
    {
        $this->valueHelper->method('getValueForMautic')
            ->willReturnCallback(
                function(NormalizedValueDAO $normalizedValueDAO, string $fieldState, string $syncDirection) {
                    return $normalizedValueDAO;
                }
            );

        $integration = 'Test';
        $objectName  = 'Contact';

        $mappingManual = $this->getMappingManual($integration, $objectName);
        $syncReport    = $this->getIntegrationSyncReport($integration, $objectName);

        $internalReportObject = new ReportObjectDAO(MauticSyncDataExchange::OBJECT_CONTACT, 1);
        $internalReportObject->addField(new ReportFieldDAO('email', new NormalizedValueDAO(NormalizedValueDAO::EMAIL_TYPE, 'test@test.com')));
        $internalReportObject->addField(new ReportFieldDAO('firstname', new NormalizedValueDAO(NormalizedValueDAO::TEXT_TYPE, 'Bob')));

        $this->syncJudge->expects($this->exactly(2))
            ->method('adjudicate')
            ->willReturnCallback(
                function($mode, InformationChangeRequestDAO $internalInformationChangeRequest, InformationChangeRequestDAO $integrationInformationChangeRequest) {
                    return $internalInformationChangeRequest;
                }
            );

        $objectChangeDAO       = $this->getObjectGenerator()->getSyncObjectChange(
            $syncReport,
            $mappingManual,
            $mappingManual->getObjectMapping(MauticSyncDataExchange::OBJECT_CONTACT, $objectName),
            $internalReportObject,
            $syncReport->getObject($objectName, 2)
        );

        $this->assertEquals($integration, $objectChangeDAO->getIntegration());

        // object and object ID should be Mautic's (from the Mautic's POV)
        $this->assertEquals(MauticSyncDataExchange::OBJECT_CONTACT, $objectChangeDAO->getObject());
        $this->assertEquals(1, $objectChangeDAO->getObjectId());

        // mapped object and ID should be the integrations
        $this->assertEquals($objectName, $objectChangeDAO->getMappedObject());
        $this->assertEquals(2, $objectChangeDAO->getMappedObjectId());

        // Email should be a required field
        $requiredFields = $objectChangeDAO->getRequiredFields();
        $this->assertTrue(isset($requiredFields['email']));

        // Both fields should be included
        $fields = $objectChangeDAO->getFields();
        $this->assertTrue(isset($fields['email']) && isset($fields['firstname']));

        // First name is presumed to be changed
        $changedFields = $objectChangeDAO->getChangedFields();
        $this->assertTrue(isset($changedFields['firstname']));

        // First name should have changed to Robert because the sync judge returned the integration's information change request
        $this->assertEquals('Bob', $changedFields['firstname']->getValue()->getNormalizedValue());
    }

    /**
     * @param string $integration
     * @param string $objectName
     *
     * @return MappingManualDAO
     */
    private function getMappingManual(string $integration, string $objectName)
    {
        $mappingManual = new MappingManualDAO($integration);
        $objectMapping = new ObjectMappingDAO(MauticSyncDataExchange::OBJECT_CONTACT, $objectName);
        $objectMapping->addFieldMapping('email', 'email', ObjectMappingDAO::SYNC_BIDIRECTIONALLY, true);
        $objectMapping->addFieldMapping('firstname', 'first_name');
        $mappingManual->addObjectMapping($objectMapping);

        return $mappingManual;
    }

    /**
     * @param string $integration
     * @param string $objectName
     *
     * @return ReportDAO
     */
    private function getIntegrationSyncReport(string $integration, string $objectName)
    {
        $syncReport = new ReportDAO($integration);
        $reportObject = new ReportObjectDAO($objectName, 2);
        $reportObject->addField(new ReportFieldDAO('email', new NormalizedValueDAO(NormalizedValueDAO::EMAIL_TYPE, 'test@test.com'), ReportFieldDAO::FIELD_REQUIRED));
        $reportObject->addField(new ReportFieldDAO('first_name', new NormalizedValueDAO(NormalizedValueDAO::TEXT_TYPE, 'Robert')));

        $syncReport->addObject($reportObject);

        return $syncReport;
    }

    /**
     * @return ObjectChangeGenerator
     */
    private function getObjectGenerator()
    {
        return new ObjectChangeGenerator($this->syncJudge, $this->valueHelper, $this->fieldHelper);
    }
}