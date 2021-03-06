<?php

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\EventListener;

use MauticPlugin\IntegrationsBundle\Exception\IntegrationNotFoundException;
use MauticPlugin\IntegrationsBundle\Helper\IntegrationsHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ControllerSubscriber implements EventSubscriberInterface
{
    /**
     * @var IntegrationsHelper
     */
    private $integrationsHelper;

    /**
     * @var ControllerResolverInterface
     */
    private $resolver;

    /**
     * ControllerSubscriber constructor.
     *
     * @param IntegrationsHelper          $integrationsHelper
     * @param ControllerResolverInterface $resolver
     */
    public function __construct(IntegrationsHelper $integrationsHelper, ControllerResolverInterface $resolver)
    {
        $this->integrationsHelper = $integrationsHelper;
        $this->resolver           = $resolver;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    /**
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();

        if ('Mautic\PluginBundle\Controller\PluginController::configAction' === $request->get('_controller')) {
            $integrationName = $request->get('name');
            $page            = $request->get('page');
            try {
                $this->integrationsHelper->getIntegration($integrationName);
                $request->attributes->add(
                    [
                        'integration'   => $integrationName,
                        'page'          => $page,
                        '_controller'   => 'MauticPlugin\IntegrationsBundle\Controller\ConfigController::editAction',
                        '_route_params' => [
                            'integration' => $integrationName,
                            'page'        => $page,
                        ],
                    ]
                );

                $controller = $this->resolver->getController($request);
                $event->setController($controller);
            } catch (IntegrationNotFoundException $exception) {
                // Old integration so ignore and let old PluginBundle code handle it
            }
        }
    }
}
