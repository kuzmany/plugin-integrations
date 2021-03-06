<?php

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\IntegrationsBundle\Event\ConfigSaveEvent;
use MauticPlugin\IntegrationsBundle\Event\FormLoadEvent;
use MauticPlugin\IntegrationsBundle\Exception\IntegrationNotFoundException;
use MauticPlugin\IntegrationsBundle\Form\Type\IntegrationConfigType;
use MauticPlugin\IntegrationsBundle\Helper\ConfigIntegrationsHelper;
use MauticPlugin\IntegrationsBundle\Helper\FieldMergerHelper;
use MauticPlugin\IntegrationsBundle\Helper\FieldValidationHelper;
use MauticPlugin\IntegrationsBundle\Integration\BasicIntegration;
use MauticPlugin\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\IntegrationsBundle\Integration\Interfaces\ConfigFormSyncInterface;
use MauticPlugin\IntegrationsBundle\IntegrationEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class ConfigController extends AbstractFormController
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Form
     */
    private $form;

    /**
     * @var BasicIntegration|ConfigFormInterface
     */
    private $integrationObject;

    /**
     * @var Integration
     */
    private $integrationConfiguration;

    /**
     * @var ConfigIntegrationsHelper
     */
    private $integrationsHelper;

    /**
     * @param Request $request
     * @param string  $integration
     *
     * @return array|JsonResponse|RedirectResponse|Response
     */
    public function editAction(Request $request, string $integration)
    {
        // Check ACL
        if (!$this->get('mautic.security')->isGranted('plugin:plugins:manage')) {
            return $this->accessDenied();
        }

        // Find the integration
        /** @var ConfigIntegrationsHelper $integrationsHelper */
        $this->integrationsHelper = $this->get('mautic.integrations.helper.config_integrations');
        try {
            $this->integrationObject        = $this->integrationsHelper->getIntegration($integration);
            $this->integrationConfiguration = $this->integrationObject->getIntegrationConfiguration();
        } catch (IntegrationNotFoundException $exception) {
            return $this->notFound();
        }

        $dispatcher = $this->get('event_dispatcher');
        $event      = new FormLoadEvent($this->integrationConfiguration);
        $dispatcher->dispatch(IntegrationEvents::INTEGRATION_CONFIG_FORM_LOAD, $event);

        // Set the request for private methods
        $this->request = $request;

        // Create the form
        $this->form = $this->getForm();

        if (Request::METHOD_POST === $request->getMethod()) {
            return $this->submitForm();
        }

        // Clear the session of previously stored fields in case it got stuck
        /** @var Session $session */
        $session = $this->get('session');
        $session->remove("$integration-fields");

        return $this->showForm();
    }

    /**
     * @return JsonResponse|Response
     */
    private function submitForm()
    {
        if ($cancelled = $this->isFormCancelled($this->form)) {
            return $this->closeForm();
        }

        // Dispatch event prior to saving the Integration
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->get('event_dispatcher');
        $configEvent = new ConfigSaveEvent($this->integrationConfiguration);
        $eventDispatcher->dispatch(IntegrationEvents::INTEGRATION_CONFIG_BEFORE_SAVE, $configEvent);

        // Get the fields before the form binds partial data due to pagination
        $settings      = $this->integrationConfiguration->getFeatureSettings();
        $fieldMappings = (isset($settings['sync']['fieldMappings'])) ? $settings['sync']['fieldMappings'] : [];

        // Submit the form
        $this->form->handleRequest($this->request);
        if ($this->integrationObject instanceof ConfigFormSyncInterface) {
            $integration   = $this->integrationObject->getName();
            $settings      = $this->integrationConfiguration->getFeatureSettings();
            $session       = $this->get('session');
            $updatedFields = $session->get("$integration-fields", []);

            $fieldMerger = new FieldMergerHelper($this->integrationObject, $fieldMappings);

            foreach ($updatedFields as $object => $fields) {
                $fieldMerger->mergeSyncFieldMapping($object, $fields);
            }

            $settings['sync']['fieldMappings'] = $fieldMerger->getFieldMappings();

            /** @var FieldValidationHelper $fieldValidator */
            $fieldValidator = $this->get('mautic.integrations.helper.field_validator');
            $fieldValidator->validateRequiredFields($this->form, $this->integrationObject, $settings['sync']['fieldMappings']);

            $this->integrationConfiguration->setFeatureSettings($settings);
        }

        // Show the form if there are errors
        if (!$this->form->isValid()) {
            return $this->showForm();
        }

        // Save the integration configuration
        $this->integrationsHelper->saveIntegrationConfiguration($this->integrationConfiguration);

        // Dispatch after save event
        $eventDispatcher->dispatch(IntegrationEvents::INTEGRATION_CONFIG_AFTER_SAVE, $configEvent);

        // Show the form if the apply button was clicked
        if ($this->isFormApplied($this->form)) {
            // Regenerate the form
            $this->form = $this->getForm();

            return $this->showForm();
        }

        // Otherwise close the modal
        return $this->closeForm();
    }

    /**
     * @return Form
     */
    private function getForm()
    {
        return $this->get('form.factory')->create(
            $this->integrationObject->getConfigFormName() ? $this->integrationObject->getConfigFormName() : IntegrationConfigType::class,
            $this->integrationConfiguration,
            [
                'action'      => $this->generateUrl('mautic_integration_config', ['integration' => $this->integrationObject->getName()]),
                'integration' => $this->integrationObject->getName(),
            ]
        );
    }

    /**
     * @return JsonResponse|Response
     */
    private function showForm()
    {
        return $this->delegateView(
            [
                'viewParameters'  => [
                    'integrationObject' => $this->integrationObject,
                    'form'              => $this->setFormTheme($this->form, 'IntegrationsBundle:Config:form.html.php'),
                    'activeTab'         => $this->request->get('activeTab'),
                ],
                'contentTemplate' => $this->integrationObject->getConfigFormContentTemplate()
                    ? $this->integrationObject->getConfigFormContentTemplate()
                    : 'IntegrationsBundle:Config:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_plugin_index',
                    'mauticContent' => 'integrationsConfig',
                    'route'         => false,
                ],
            ]
        );
    }

    /**
     * @return JsonResponse
     */
    private function closeForm()
    {
        /** @var Session $session */
        $session = $this->get('session');
        $session->remove("{$this->integrationObject->getName()}-fields");

        return new JsonResponse(
            [
                'closeModal'    => 1,
                'enabled'       => $this->integrationConfiguration->getIsPublished(),
                'name'          => $this->integrationConfiguration->getName(),
                'mauticContent' => 'integrationsConfig',
            ]
        );
    }


}
