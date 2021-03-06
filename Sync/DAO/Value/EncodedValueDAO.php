<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\DAO\Value;

/**
 * Class EncodedValueDAO
 */
class EncodedValueDAO
{
    const STRING_TYPE = 'string';
    const INT_TYPE = 'int';
    const FLOAT_TYPE = 'float';
    const DOUBLE_TYPE = 'double';
    const DATETIME_TYPE = 'datetime';
    const BOOLEAN_TYPE = 'boolean';

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $value;

    /**
     * VariableEncodeDAO constructor.
     * @param string $type
     * @param string $value
     */
    public function __construct($type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
