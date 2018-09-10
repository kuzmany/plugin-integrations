<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Tests\Services\SyncService\TestExamples\Sync\SyncDataExchange;

use MauticPlugin\IntegrationsBundle\Tests\Services\SyncService\TestExamples\Integration\ExampleIntegration;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\EntityMappingDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\ObjectChangeDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\OrderDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\FieldDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ObjectDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ReportDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Request\RequestDAO;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\SyncDataExchangeInterface;
use MauticPlugin\IntegrationsBundle\Sync\ValueNormalizer\ValueNormalizer;

class ExampleSyncDataExchange implements SyncDataExchangeInterface
{
    const OBJECT_LEAD = 'lead';
    const OBJECT_CONTACT = 'contact';

    /**
     * @var array
     */
    const FIELDS = [
        'id'            => [
            'label' => 'ID',
            'type'  => NormalizedValueDAO::INT_TYPE,
        ],
        'first_name'    => [
            'label' => 'First Name',
            'type'  => NormalizedValueDAO::STRING_TYPE,
        ],
        'last_name'     => [
            'label' => 'Last Name',
            'type'  => NormalizedValueDAO::STRING_TYPE,
        ],
        'email'         => [
            'label' => 'Email',
            'type'  => NormalizedValueDAO::STRING_TYPE,
        ],
        'last_modified' => [
            'label' => 'Last Modified',
            'type'  => NormalizedValueDAO::DATETIME_TYPE,
        ],
    ];

    /**
     * @var array
     */
    private $payload = ['create' => [], 'update' => []];

    /**
     * @var ValueNormalizer
     */
    private $valueNormalizer;

    /**
     * ExampleSyncDataExchange constructor.
     */
    public function __construct()
    {
        // Using the default normalizer for this example but each integration may need it's own if
        // it needs/has data formatted in a unique way
        $this->valueNormalizer = new ValueNormalizer();
    }

    /**
     * This pushes to the integration objects that were updated/created in Mautic. The "sync order" is
     * created by the SyncProcess service.
     *
     * @param OrderDAO $syncOrderDAO
     */
    public function executeSyncOrder(OrderDAO $syncOrderDAO)
    {
        $byEmail = [self::OBJECT_CONTACT => [], self::OBJECT_LEAD => []];

        $orderedObjects = $syncOrderDAO->getUnidentifiedObjects();
        foreach ($orderedObjects as $objectName => $unidentifiedObjects) {
            /**
             * @var mixed           $key
             * @var ObjectChangeDAO $unidentifiedObject
             */
            foreach ($unidentifiedObjects as $unidentifiedObject) {
                $fields = $unidentifiedObject->getFields();

                // Extract identifier fields for this integration to check if they exist before creating
                // Some integrations offer a upsert feature which may make this not necessary.
                $emailAddress = $unidentifiedObject->getField('email')->getValue()->getNormalizedValue();

                // Store by email address so they can be found again when we update the OrderDAO about mapping
                $byEmail[$unidentifiedObject->getObject()][$emailAddress] = $unidentifiedObject;

                // Build the person's profile
                $person = ['object' => $objectName];
                foreach ($fields as $field) {
                    $person[$field->getName()] = $this->valueNormalizer->normalizeForIntegration($field->getValue());
                }

                // Create by default because it is unknown if they exist upstream or not
                $this->payload['create'][$emailAddress] = $person;
            }

            // If applicable, do something to verify if email addresses exist and if so, update objects instead
            // $api->searchByEmail(array_keys($byEmail));
        }

        $orderedObjects = $syncOrderDAO->getIdentifiedObjects();
        foreach ($orderedObjects as $objectName => $identifiedObjects) {
            /**
             * @var mixed           $key
             * @var ObjectChangeDAO $identifiedObject
             */
            foreach ($identifiedObjects as $id => $identifiedObject) {
                $fields = $identifiedObject->getFields();

                // Build the person's profile
                $person = [
                    'id'     => $id,
                    'object' => $objectName
                ];
                foreach ($fields as $field) {
                    $person[$field->getName()] = $this->valueNormalizer->normalizeForIntegration($field->getValue());
                }

                $this->payload['update'][$id] = $person;
            }
        }

        // Deliver payload and get response
        $response = $this->deliverPayload();

        // Notify the order regarding IDs of created objects
        foreach ($response as $result) {
            if (201 === $result['code']) {
                /** @var ObjectChangeDAO $object */
                $object = $byEmail[$result['object']][$result['email']];

                $syncOrderDAO->addObjectMapping(
                    ExampleIntegration::NAME,
                    $object->getMappedObject(),
                    $object->getMappedObjectId(),
                    $result['object'],
                    $result['id'],
                    $result['last_modified']
                );
            }
        }
    }

    /**
     * This fetches objects from the integration that needs to be updated or created in Mautic.
     * A "sync report" is created that will be processed by the SyncProcess service.
     *
     * @param RequestDAO $requestDAO
     *
     * @return ReportDAO
     */
    public function getSyncReport(RequestDAO $requestDAO)
    {
        // Build a report of objects that have been modified
        $syncReport = new ReportDAO(ExampleIntegration::NAME);

        if ($requestDAO->getSyncIteration() > 1) {
            // Prevent loop
            return $syncReport;
        }

        $requestedObjects = $requestDAO->getObjects();
        foreach ($requestedObjects as $requestedObject) {
            $objectName   = $requestedObject->getObject();
            $fromDateTime = $requestedObject->getFromDateTime();
            $mappedFields = $requestedObject->getFields();

            $updatedPeople = $this->getReportPayload($objectName, $fromDateTime, $mappedFields);
            foreach ($updatedPeople as $person) {
                // If the integration knows modified timestamps per field, use that. Otherwise, we're using the complete object's
                // last modified timestamp.
                $objectChangeTimestamp = new \DateTimeImmutable($person['last_modified']);

                $objectDAO = new ObjectDAO($objectName, $person['id'], $objectChangeTimestamp);

                foreach ($person as $field => $value) {
                    // Normalize the value from the API to what Mautic needs
                    $normalizedValue = $this->valueNormalizer->normalizeForMautic(self::FIELDS[$field]['type'], $value);
                    $reportFieldDAO  = new FieldDAO($field, $normalizedValue);

                    // If we know for certain that this specific field was modified at a specific date/time, set the change timestamp
                    // on the field itself for the judge to weigh certain versus possible changes
                    //$reportFieldDAO->setChangeTimestamp($fieldChangeTimestamp);

                    $objectDAO->addField($reportFieldDAO);
                }

                $syncReport->addObject($objectDAO);
            }
        }

        return $syncReport;
    }

    /**
     * @return array
     */
    public function getOrderPayload()
    {
        return $this->payload;
    }

    /**
     * @param                    $object
     * @param \DateTimeInterface $fromDateTime
     * @param array              $mappedFields
     *
     * @return mixed
     */
    private function getReportPayload($object, \DateTimeInterface $fromDateTime, array $mappedFields)
    {
        // Query integration's API for objects changed since $fromDateTime with the requested fields in $mappedFields if that's
        // applicable to the integration. I.e. Salesforce supports querying for specific fields in it's SOQL

        $payload = [
            self::OBJECT_CONTACT => [
                [
                    'id'            => 1,
                    'first_name'    => 'John',
                    'last_name'     => 'Contact',
                    'email'         => 'john.contact@test.com',
                    'last_modified' => '2018-08-02T10:02:00+05:00',
                ],
                [
                    'id'            => 2,
                    'first_name'    => 'Jane',
                    'last_name'     => 'Contact',
                    'email'         => 'jane.contact@test.com',
                    'last_modified' => '2018-08-02T10:07:00+05:00',
                ],
            ],
            self::OBJECT_LEAD    => [
                [
                    'id'            => 3,
                    'first_name'    => 'Overwrite',
                    'last_name'     => 'Me',
                    'email'         => 'NellieABaird@armyspy.com',
                    'last_modified' => '2018-08-02T10:02:00+05:00',
                ],
                [
                    'id'            => 4,
                    'first_name'    => 'Overwrite',
                    'last_name'     => 'Me',
                    'email'         => 'LewisTSyed@gustr.com',
                    'last_modified' => '2018-08-02T10:07:00+05:00',
                ],
            ],
        ];

        return $payload[$object];
    }

    /**
     * @return array
     */
    private function deliverPayload()
    {
        $now      = new \DateTime('now', new \DateTimeZone('UTC'));
        $response = [];
        $id       = 5;
        foreach ($this->payload['create'] as $person) {
            $person['code']          = 201;
            $person['id']            = $id;
            $person['last_modified'] = $now;
            $response[]              = $person;
            $id++;
        }

        return $response;
    }
}