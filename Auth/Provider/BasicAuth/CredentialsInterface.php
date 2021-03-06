<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Integration\Auth\Provider\BasicAuth;


interface CredentialsInterface
{
    /**
     * @return null|string
     */
    public function getUsername(): ?string;

    /**
     * @return null|string
     */
    public function getPassword(): ?string;
}