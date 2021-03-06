<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged;

use GuzzleHttp\ClientInterface;
use MauticPlugin\IntegrationsBundle\Auth\Provider\AuthProviderInterface;
use MauticPlugin\IntegrationsBundle\Exception\PluginNotConfiguredException;

/**
 * Factory for building HTTP clients that will sign the requests with Oauth1a headers.
 * Based on Guzzle OAuth 2.0 Subscriber - kamermans/guzzle-oauth2-subscriber package
 * @see https://github.com/kamermans/guzzle-oauth2-subscriber
 */
abstract class AbstractClientFactory implements AuthProviderInterface
{
    const NAME = 'oauth2_three_legged';

    /**
     * @return string
     */
    public function getAuthType(): string
    {
        return self::NAME;
    }

    /**
     * @param CredentialsInterface $credentials
     *
     * @return ClientInterface
     * @throws PluginNotConfiguredException
     */
    public function getClient($credentials): ClientInterface
    {
        if (!$this->credentialsAreConfigured($credentials)) {
            throw new PluginNotConfiguredException('Oauth2 credentials are not configured');
        }

        return $this->buildClient($credentials);
    }

    /**
     * @param CredentialsInterface $credentials
     *
     * @return ClientInterface
     */
    abstract protected function buildClient(CredentialsInterface $credentials): ClientInterface;

    /**
     * @param CredentialsInterface $credentials
     *
     * @return bool
     */
    abstract protected function credentialsAreConfigured(CredentialsInterface $credentials): bool;
}