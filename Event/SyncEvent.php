<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class SyncEvent
 *
 * @package MauticPlugin\IntegrationsBundle\Event
 */
class SyncEvent extends Event
{
    /** @var string */
    private $integrationName;
    /**
     * @var \DateTimeInterface|null
     */
    private $fromDateTime;
    /**
     * @var \DateTimeInterface|null
     */
    private $toDateTime;

    public function __construct(string $integrationName, \DateTimeInterface $fromDateTime = null, \DateTimeInterface $toDateTime = null)
    {
        $this->integrationName = $integrationName;
        $this->fromDateTime = $fromDateTime;
        $this->toDateTime = $toDateTime;
    }

    /**
     * @return string
     */
    public function getIntegrationName(): string
    {
        return $this->integrationName;
    }

    /**
     * @param string $integrationName
     *
     * @return bool
     */
    public function isIntegration(string $integrationName): bool {
        return $this->getIntegrationName() === $integrationName;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getFromDateTime(): ?\DateTimeInterface
    {
        return $this->fromDateTime;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getToDateTime(): ?\DateTimeInterface
    {
        return $this->toDateTime;
    }


}
