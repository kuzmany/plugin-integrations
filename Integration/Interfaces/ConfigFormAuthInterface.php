<?php

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Integration\Interfaces;


interface ConfigFormAuthInterface
{
    /**
     * Return the name of the form type service for the authorization tab which should include all the fields required for the API to work
     *
     * @return string
     */
    public function getAuthConfigFormName(): string;
}