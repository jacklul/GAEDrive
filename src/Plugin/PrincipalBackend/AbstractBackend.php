<?php

namespace GAEDrive\Plugin\PrincipalBackend;

use Sabre;

abstract class AbstractBackend extends Sabre\DAVACL\PrincipalBackend\AbstractBackend
{
    /**
     * @return string|null
     */
    abstract public function getCurrentPrincipal();

    /**
     * @param $principal
     *
     * @return bool
     * @throws Sabre\DAV\Exception
     */
    abstract public function isAdministrator($principal = null);

    /**
     * @param $principal
     *
     * @return bool
     * @throws Sabre\DAV\Exception
     */
    abstract public function isGuest($principal = null);

    /**
     * @param $principal
     *
     * @return bool
     * @throws Sabre\DAV\Exception
     */
    abstract public function isLimited($principal = null);

    /**
     * @param $principal
     *
     * @return bool
     * @throws Sabre\DAV\Exception
     */
    abstract public function isReadOnly($principal = null);
}
