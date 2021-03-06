<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Tests\Sync\SyncDataExchange\Internal\ReportBuilder;


use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Request\ObjectDAO;
use MauticPlugin\IntegrationsBundle\Sync\Exception\FieldNotFoundException;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Helper\FieldHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectHelper\ContactObjectHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ReportBuilder\FieldBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

class FieldBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Router|\PHPUnit_Framework_MockObject_MockObject
     */
    private $router;

    /**
     * @var FieldHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $fieldHelper;

    /**
     * @var ContactObjectHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $contactObjectHelper;

    protected function setUp()
    {
        $this->router = $this->createMock(Router::class);
        $this->fieldHelper = $this->getMockBuilder(FieldHelper::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getNormalizedFieldType', 'getFieldObjectName',])
            ->getMock();
        $this->contactObjectHelper = $this->createMock(ContactObjectHelper::class);
    }

    public function testIdFieldIsAdded()
    {
        $field = $this->getFieldBuilder()->buildObjectField('mautic_internal_id', ['id' => 1], new ObjectDAO('Test'), 'Test');

        $this->assertEquals('mautic_internal_id', $field->getName());
        $this->assertEquals(1, $field->getValue()->getNormalizedValue());
    }

    public function testDoNotContactFieldIsAdded()
    {
        $this->contactObjectHelper->expects($this->once())
            ->method('getDoNotContactStatus')
            ->with(1, 'email')
            ->willReturn(0);

        $field = $this->getFieldBuilder()->buildObjectField('mautic_internal_dnc_email', ['id' => 1], new ObjectDAO('Test'), 'Test');

        $this->assertEquals('mautic_internal_dnc_email', $field->getName());
        $this->assertEquals(0, $field->getValue()->getNormalizedValue());
    }

    public function testTimelineFieldIsAdded()
    {
        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                'mautic_plugin_timeline_view',
                [
                    'integration' => 'Test',
                    'leadId'      => 1,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        $field = $this->getFieldBuilder()->buildObjectField('mautic_internal_contact_timeline', ['id' => 1], new ObjectDAO('Test'), 'Test');

        $this->assertEquals('mautic_internal_contact_timeline', $field->getName());
        $this->assertEquals(0, $field->getValue()->getNormalizedValue());
    }

    public function testCustomFieldsAreAdded()
    {
        $this->fieldHelper->expects($this->once())
            ->method('getFieldList')
            ->with('Test')
            ->willReturn(
                [
                    'email' => [
                        'type' => 'email'
                    ]
                ]
            );

        $field = $this->getFieldBuilder()->buildObjectField('email', ['id' => 1, 'email' => 'test@test.com'], new ObjectDAO('Test'), 'Test');

        $this->assertEquals('email', $field->getName());
        $this->assertEquals('test@test.com', $field->getValue()->getNormalizedValue());
    }

    public function testUnrecognizedFieldThrowsException()
    {
        $this->fieldHelper->expects($this->once())
            ->method('getFieldList')
            ->with('Test')
            ->willReturn(
                [
                    'email' => [
                        'type' => 'email'
                    ]
                ]
            );

        $this->expectException(FieldNotFoundException::class);

        $this->getFieldBuilder()->buildObjectField('badfield', ['id' => 1, 'email' => 'test@test.com'], new ObjectDAO('Test'), 'Test');
    }

    public function getFieldBuilder()
    {
        return new FieldBuilder($this->router, $this->fieldHelper, $this->contactObjectHelper);
    }
}