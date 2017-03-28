<?php
namespace IngatlanCom\ApiClient\Composer;

use IngatlanCom\ApiClient\Exception\MissingRequirementsException;

class ScriptHandler
{
    /**
     * Ellenőrzi, hogy az "imagick", vagy a "gd" bővítmény telepítve van e.
     *
     * @throws MissingRequirementsException
     */
    public static function checkRequirements()
    {
        if (!extension_loaded('imagick') && !extension_loaded('gd')) {
            throw new MissingRequirementsException(
                'Az Api kliens használatához kérjük telepítse az "imagick", vagy a "gd" PHP bővítmények valamelyikét!'
            );
        }
    }
}
