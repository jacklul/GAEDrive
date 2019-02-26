<?php

namespace GAEDrive\FS\Collection;

use GAEDrive\FS\Principal;
use GAEDrive\FS\Traits\ProtectedPropertiesTrait;
use Sabre;
use Sabre\DAVACL\PrincipalCollection as BasePrincipalCollection;

class PrincipalCollection extends BasePrincipalCollection implements Sabre\DAV\IProperties
{
    use ProtectedPropertiesTrait;

    /**
     * @param array $principal
     *
     * @return Sabre\DAV\INode
     * @throws Sabre\DAV\Exception
     */
    function getChildForPrincipal(array $principal)
    {
        return new Principal($this->principalBackend, $principal);
    }
}
