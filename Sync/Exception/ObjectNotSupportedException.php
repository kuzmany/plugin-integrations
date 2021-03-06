<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\Exception;

class ObjectNotSupportedException extends \Exception
{
    /**
     * ObjectNotSupportedException constructor.
     *
     * @param string $integration
     * @param string $object
     */
    public function __construct(string $integration, string $object)
    {
        parent::__construct("$integration does not support a $object object");
    }
}