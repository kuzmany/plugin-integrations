<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\Exception;

class FieldNotFoundException extends \Exception
{
    /**
     * FieldNotFoundException constructor.
     *
     * @param                 $field
     * @param                 $object
     * @param int             $code
     * @param \Exception|null $previous
     */
    public function __construct($field, $object, $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('The %s field is not mapped for the %s object.', $field, $object), $code, $previous);
    }
}